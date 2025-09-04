<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Adresse;
use App\Entity\Contact;
use App\Entity\CommuneFrancaise;
use App\Form\ClientType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
#[IsGranted('ROLE_USER')]
final class ClientController extends AbstractController
{
    #[Route('/', name: 'app_client_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $repository = $entityManager->getRepository(Client::class);
        
        // Récupérer les paramètres de filtrage
        $statut = $request->query->get('statut');
        $famille = $request->query->get('famille');
        $secteurId = $request->query->get('secteur');
        
        // Construire la requête avec filtres
        $queryBuilder = $repository->createQueryBuilder('c')
            ->leftJoin('c.contacts', 'contacts')
            ->leftJoin('c.adresses', 'a')
            ->leftJoin('c.secteur', 's')
            ->leftJoin('c.commercial', 'co');
        
        if ($statut) {
            $queryBuilder->andWhere('c.statut = :statut')
                         ->setParameter('statut', $statut);
        }
        
        if ($famille) {
            $queryBuilder->andWhere('c.famille LIKE :famille')
                         ->setParameter('famille', '%' . $famille . '%');
        }
        
        if ($secteurId) {
            $queryBuilder->andWhere('c.secteur = :secteur')
                         ->setParameter('secteur', $secteurId);
        }
        
        $clients = $queryBuilder->orderBy('c.updatedAt', 'DESC')
                                ->getQuery()
                                ->getResult();
        
        // Calculer les statistiques
        $statsQb = $repository->createQueryBuilder('c');
        $totalProspects = $statsQb->select('COUNT(c.id)')
                                  ->where('c.statut = :statut')
                                  ->setParameter('statut', 'prospect')
                                  ->getQuery()
                                  ->getSingleScalarResult();
        
        $totalClients = $statsQb->select('COUNT(c.id)')
                               ->where('c.statut = :statut')
                               ->setParameter('statut', 'client')
                               ->getQuery()
                               ->getSingleScalarResult();
        
        $thisMonth = new \DateTimeImmutable('first day of this month');
        $conversionsThisMonth = $statsQb->select('COUNT(c.id)')
                                        ->where('c.statut = :statut')
                                        ->andWhere('c.dateConversionClient >= :thisMonth')
                                        ->setParameter('statut', 'client')
                                        ->setParameter('thisMonth', $thisMonth)
                                        ->getQuery()
                                        ->getSingleScalarResult();
        
        $stats = [
            'prospects' => $totalProspects,
            'clients' => $totalClients,
            'conversions' => $conversionsThisMonth
        ];
        
        // Récupérer la liste des secteurs pour le filtre
        $secteurs = $entityManager->getRepository(\App\Entity\Secteur::class)->findBy([], ['nomSecteur' => 'ASC']);

        return $this->render('client/index.html.twig', [
            'clients' => $clients,
            'stats' => $stats,
            'secteurs' => $secteurs,
        ]);
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = new Client();
        
        $form = $this->createForm(ClientType::class, $client);
        
        // Traitement personnalisé avant validation du formulaire
        if ($request->isMethod('POST')) {
            // Récupérer les données du formulaire personnalisé
            $formData = $request->request->all('client');
            
            // Adapter les données selon le type de personne
            if (isset($formData['typePersonne'])) {
                if ($formData['typePersonne'] === 'physique') {
                    // Pour personne physique : pas de dénomination (NULL) et famille "Particulier"
                    $formData['nom'] = null; // Pas de dénomination pour les particuliers
                    $formData['famille'] = 'Particulier'; // Famille forcée à "Particulier"
                } 
                // Pour personne morale, le nom est déjà dans formData['nom'] et doit être obligatoire
                
                // Mettre à jour les données du formulaire
                $request->request->set('client', $formData);
            }
        }
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traitement du formulaire personnalisé avec contact et adresse intégrés
            $this->handleCustomFormSubmission($request, $client, $entityManager);
            
            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }
        
        // Afficher les erreurs du formulaire si il y en a
        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true, false) as $error) {
                $this->addFlash('error', 'Erreur: ' . $error->getMessage());
            }
        }

        return $this->render('client/new_improved.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }
    
    private function handleCustomFormSubmission(Request $request, Client $client, EntityManagerInterface $entityManager): void
    {
        // Générer le prochain code disponible
        $repository = $entityManager->getRepository(Client::class);
        $nextCode = $client->getStatut() === 'client' 
            ? $repository->getNextClientCode() 
            : $repository->getNextProspectCode();
        $client->setCode($nextCode);
        
        // Gérer les données spécifiques à la forme juridique
        if ($client->getFormeJuridique() && $client->getFormeJuridique()->isPersonnePhysique()) {
            // Pour une personne physique, pas de dénomination (reste NULL) mais stocker prénom/nom séparément
            $client->setNom(null); // Pas de dénomination pour les particuliers
            $client->setPrenom($request->request->get('personne_prenom'));
            $client->setCivilite($request->request->get('personne_civilite'));
            $client->setFamille('Particulier'); // Définir automatiquement la famille
            
            // Pour une personne physique, créer un contact avec les données du client
            $contactData = [
                'civilite' => $request->request->get('personne_civilite'),
                'nom' => $request->request->get('personne_nom'),
                'prenom' => $request->request->get('personne_prenom'),
                'fonction' => $request->request->get('personne_profession'),
                'email' => $request->request->get('personne_email'),
                'telephone' => $request->request->get('personne_telephone'),
                'telephone_mobile' => $request->request->get('personne_telephone_mobile'),
                'facturation_default' => true,
                'livraison_default' => true,
            ];
            
            // Pour une personne physique, utiliser l'adresse personnelle
            $adresseData = [
                'nom' => 'Domicile',
                'ligne1' => $request->request->get('personne_adresse_ligne1'),
                'ligne2' => $request->request->get('personne_adresse_ligne2'),
                'ligne3' => $request->request->get('personne_adresse_ligne3'),
                'code_postal' => $request->request->get('personne_adresse_code_postal'),
                'ville' => $request->request->get('personne_adresse_ville'),
                'pays' => $request->request->get('personne_adresse_pays', 'France'),
            ];
        } else {
            // Pour une personne morale, utiliser les données du contact
            $contactData = [
                'civilite' => $request->request->get('contact_civilite'),
                'nom' => $request->request->get('contact_nom'),
                'prenom' => $request->request->get('contact_prenom'),
                'fonction' => $request->request->get('contact_fonction'),
                'email' => $request->request->get('contact_email'),
                'telephone' => $request->request->get('contact_telephone'),
                'telephone_mobile' => $request->request->get('contact_telephone_mobile'),
                'facturation_default' => true, // Premier contact = par défaut
                'livraison_default' => true, // Premier contact = par défaut
            ];
            
            // Pour une personne morale, utiliser les données d'adresse standard
            $adresseData = [
                'nom' => $request->request->get('adresse_nom', 'Siège'),
                'ligne1' => $request->request->get('adresse_ligne1'),
                'ligne2' => $request->request->get('adresse_ligne2'),
                'ligne3' => $request->request->get('adresse_ligne3'),
                'code_postal' => $request->request->get('adresse_code_postal'),
                'ville' => $request->request->get('adresse_ville'),
                'pays' => $request->request->get('adresse_pays', 'France'),
            ];
        }
        
        // Créer et configurer le contact
        if ($contactData['nom']) {
            $contact = new Contact();
            $contact->setCivilite($contactData['civilite']);
            $contact->setNom($contactData['nom']);
            $contact->setPrenom($contactData['prenom']);
            $contact->setFonction($contactData['fonction']);
            $contact->setEmail($contactData['email']);
            $contact->setTelephone($contactData['telephone']);
            $contact->setTelephoneMobile($contactData['telephone_mobile']);
            $contact->setIsFacturationDefault($contactData['facturation_default']);
            $contact->setIsLivraisonDefault($contactData['livraison_default']);
            $contact->setClient($client);
            
            // Créer et configurer l'adresse
            if ($adresseData['ligne1']) {
                $adresse = new Adresse();
                $adresse->setNom($adresseData['nom']);
                $adresse->setLigne1($adresseData['ligne1']);
                $adresse->setLigne2($adresseData['ligne2']);
                $adresse->setLigne3($adresseData['ligne3']);
                $adresse->setCodePostal($adresseData['code_postal']);
                $adresse->setVille($adresseData['ville']);
                $adresse->setPays($adresseData['pays']);
                $adresse->setClient($client);
                
                // Associer l'adresse au contact
                $contact->setAdresse($adresse);
                
                $entityManager->persist($adresse);
            }
            
            // Définir les contacts par défaut
            if ($contactData['facturation_default']) {
                $client->setContactFacturationDefault($contact);
            }
            if ($contactData['livraison_default']) {
                $client->setContactLivraisonDefault($contact);
            }
            
            $entityManager->persist($contact);
        }
        
        // Persister le client
        $entityManager->persist($client);
        $entityManager->flush();

        $this->addFlash('success', 'Client créé avec succès !');
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(Client $client, EntityManagerInterface $entityManager): Response
    {
        // Forcer le chargement des relations pour éviter les problèmes lazy loading
        $client = $entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.contacts', 'contacts')
            ->leftJoin('c.adresses', 'adresses')
            ->addSelect('contacts', 'adresses')
            ->where('c.id = :id')
            ->setParameter('id', $client->getId())
            ->getQuery()
            ->getOneOrNullResult();
            
        return $this->render('client/show_improved.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        // Redirection vers la nouvelle interface améliorée
        return $this->redirectToRoute('app_client_edit_improved', ['id' => $client->getId()]);
    }

    #[Route('/{id}/convert', name: 'app_client_convert_to_client', methods: ['POST'])]
    public function convertToClient(Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($client->isClient()) {
            $this->addFlash('error', 'Ce prospect est déjà un client !');
            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        $client->convertToClient();
        $entityManager->flush();

        $this->addFlash('success', 'Prospect converti en client avec succès !');

        return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$client->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($client);
            $entityManager->flush();
            
            $this->addFlash('success', 'Client supprimé avec succès !');
        }

        return $this->redirectToRoute('app_client_index');
    }

    #[Route('/{id}/data', name: 'app_client_data', methods: ['GET'])]
    public function getData(Client $client): JsonResponse
    {
        $data = [
            'civilite' => $client->getCivilite(),
            'nom' => $client->getNom(),
            'prenom' => $client->getPrenom(),
            'modeReglement' => $client->getModePaiement(),
            'adresse' => null,
            'codePostal' => null,
            'ville' => null
        ];

        // Récupérer l'adresse de facturation si elle existe
        if ($client->getAdresseFacturation()) {
            $adresse = $client->getAdresseFacturation();
            $data['adresse'] = $adresse->getLigne1();
            $data['codePostal'] = $adresse->getCodePostal();
            $data['ville'] = $adresse->getVille();
        }

        return $this->json($data);
    }

    #[Route('/{id}/contacts', name: 'app_client_contacts', methods: ['GET'])]
    public function getContacts(Client $client): JsonResponse
    {
        $contacts = [];
        foreach ($client->getContacts() as $contact) {
            $label = trim(($contact->getCivilite() ?? '') . ' ' . ($contact->getPrenom() ?? '') . ' ' . ($contact->getNom() ?? ''));
            if (empty($label)) {
                $label = $contact->getEmail() ?? 'Contact sans nom';
            }
            
            $contacts[] = [
                'id' => $contact->getId(),
                'label' => $label
            ];
        }

        return $this->json($contacts);
    }

    #[Route('/{id}/addresses', name: 'app_client_addresses', methods: ['GET'])]
    public function getAddresses(Client $client): JsonResponse
    {
        $addresses = [];
        foreach ($client->getContacts() as $contact) {
            $adresse = $contact->getAdresse();
            if ($adresse) {
                $label = ($adresse->getNom() ?? 'Adresse') . ' - ' . $adresse->getLigne1() . ' - ' . $adresse->getVille();
                
                $addresses[] = [
                    'id' => $adresse->getId(),
                    'label' => $label
                ];
            }
        }

        return $this->json($addresses);
    }

    #[Route('/{id}/edit-improved', name: 'app_client_edit_improved', methods: ['GET', 'POST'])]
    public function editImproved(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            // Traitement de la soumission du formulaire
            $data = $request->request->all();
            
            // Mise à jour des informations générales
            if (isset($data['nom'])) $client->setNom($data['nom']);
            if (isset($data['forme_juridique']) && !empty($data['forme_juridique'])) {
                $formeJuridique = $entityManager->getRepository(\App\Entity\FormeJuridique::class)->find($data['forme_juridique']);
                if ($formeJuridique) {
                    $client->setFormeJuridique($formeJuridique);
                }
            }
            if (isset($data['famille'])) $client->setFamille($data['famille']);
            if (isset($data['delai_paiement'])) $client->setDelaiPaiement((int)$data['delai_paiement']);
            if (isset($data['mode_paiement'])) $client->setModePaiement($data['mode_paiement']);
            if (isset($data['conditions_tarifs'])) $client->setConditionsTarifs($data['conditions_tarifs']);
            if (isset($data['notes'])) $client->setNotes($data['notes']);
            $client->setAssujettiTva(isset($data['assujetti_tva']));
            
            // Gestion des contacts - Créer une correspondance pour les nouveaux contacts
            $contactIdMapping = []; // Correspondance entre IDs temporaires et vrais IDs
            
            if (isset($data['contacts'])) {
                foreach ($data['contacts'] as $contactId => $contactData) {
                    if (strpos($contactId, 'new_') === 0) {
                        // Nouveau contact
                        $contact = new Contact();
                        $contact->setClient($client);
                        $contact->setNom($contactData['nom'] ?? '');
                        $contact->setPrenom($contactData['prenom'] ?? '');
                        $contact->setEmail($contactData['email'] ?? '');
                        $contact->setTelephone($contactData['telephone'] ?? '');
                        $entityManager->persist($contact);
                        
                        // Sauvegarder temporairement la correspondance
                        $contactIdMapping[$contactId] = $contact;
                    } else {
                        // Contact existant
                        $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                        if ($contact && $contact->getClient() === $client) {
                            $contact->setNom($contactData['nom'] ?? '');
                            $contact->setPrenom($contactData['prenom'] ?? '');
                            $contact->setEmail($contactData['email'] ?? '');
                            $contact->setTelephone($contactData['telephone'] ?? '');
                            
                            // Ajouter aussi les contacts existants à la correspondance
                            $contactIdMapping[$contactId] = $contact;
                        }
                    }
                }
            }
            
            // Sauvegarder en base pour obtenir les vrais IDs
            $entityManager->flush();
            
            // Gestion des adresses (maintenant liées au client directement)
            if (isset($data['adresses'])) {
                foreach ($data['adresses'] as $adresseId => $adresseData) {
                    if (strpos($adresseId, 'new_') === 0) {
                        // Nouvelle adresse
                        $adresse = new Adresse();
                        $adresse->setClient($client);
                        $adresse->setNom($adresseData['nom'] ?? '');
                        $adresse->setLigne1($adresseData['ligne1'] ?? '');
                        $adresse->setCodePostal($adresseData['code_postal'] ?? '');
                        $adresse->setVille($adresseData['ville'] ?? '');
                        $adresse->setPays($adresseData['pays'] ?? 'France');
                        $entityManager->persist($adresse);
                    } else {
                        // Adresse existante
                        $adresse = $entityManager->getRepository(Adresse::class)->find($adresseId);
                        if ($adresse && $adresse->getClient() === $client) {
                            $adresse->setNom($adresseData['nom'] ?? '');
                            $adresse->setLigne1($adresseData['ligne1'] ?? '');
                            $adresse->setCodePostal($adresseData['code_postal'] ?? '');
                            $adresse->setVille($adresseData['ville'] ?? '');
                            $adresse->setPays($adresseData['pays'] ?? 'France');
                        }
                    }
                }
            }

            // Gestion de l'assignation des adresses aux contacts
            if (isset($data['contacts'])) {
                foreach ($data['contacts'] as $contactId => $contactData) {
                    if (strpos($contactId, 'new_') !== 0) { // Contact existant
                        $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                        if ($contact && $contact->getClient() === $client && isset($contactData['adresse_id']) && !empty($contactData['adresse_id'])) {
                            $adresse = $entityManager->getRepository(Adresse::class)->find($contactData['adresse_id']);
                            if ($adresse && $adresse->getClient() === $client) {
                                $contact->setAdresse($adresse);
                            }
                        }
                    }
                }
            }
            
            // Gestion des contacts par défaut - utiliser la correspondance d'IDs
            if (isset($data['contact_facturation_default'])) {
                // Reset tous les contacts facturation par défaut
                foreach ($client->getContacts() as $contact) {
                    $contact->setIsFacturationDefault(false);
                }
                
                $facturationDefaultId = $data['contact_facturation_default'];
                $defaultContact = null;
                
                // Vérifier d'abord dans la correspondance (nouveaux et existants)
                if (isset($contactIdMapping[$facturationDefaultId])) {
                    $defaultContact = $contactIdMapping[$facturationDefaultId];
                } else {
                    // Fallback pour les contacts existants non modifiés
                    $defaultContact = $entityManager->getRepository(Contact::class)->find($facturationDefaultId);
                }
                
                if ($defaultContact && $defaultContact->getClient() === $client) {
                    $defaultContact->setIsFacturationDefault(true);
                    $client->setContactFacturationDefault($defaultContact);
                }
            }
            
            if (isset($data['contact_livraison_default'])) {
                // Reset tous les contacts livraison par défaut
                foreach ($client->getContacts() as $contact) {
                    $contact->setIsLivraisonDefault(false);
                }
                
                $livraisonDefaultId = $data['contact_livraison_default'];
                $defaultContact = null;
                
                // Vérifier d'abord dans la correspondance (nouveaux et existants)
                if (isset($contactIdMapping[$livraisonDefaultId])) {
                    $defaultContact = $contactIdMapping[$livraisonDefaultId];
                } else {
                    // Fallback pour les contacts existants non modifiés
                    $defaultContact = $entityManager->getRepository(Contact::class)->find($livraisonDefaultId);
                }
                
                if ($defaultContact && $defaultContact->getClient() === $client) {
                    $defaultContact->setIsLivraisonDefault(true);
                    $client->setContactLivraisonDefault($defaultContact);
                }
            }
            
            try {
                $entityManager->flush();
                $this->addFlash('success', 'Client mis à jour avec succès !');
                return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        return $this->render('client/edit.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}/archive', name: 'app_client_archive', methods: ['POST'])]
    public function archive(Client $client, EntityManagerInterface $entityManager): Response
    {
        // Archiver le client (le marquer comme inactif)
        $client->setActif(false);
        
        try {
            $entityManager->flush();
            $this->addFlash('success', 'Client archivé avec succès ! Il est maintenant inactif.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'archivage : ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_client_index');
    }

    #[Route('/api/communes/search', name: 'app_api_communes_search', methods: ['GET'])]
    public function searchCommunes(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([]);
        }
        
        $communes = $entityManager->getRepository(CommuneFrancaise::class)
            ->searchForAutocomplete($query, 20);
        
        $results = [];
        foreach ($communes as $commune) {
            $results[] = [
                'id' => $commune->getId(),
                'text' => $commune->getCodePostal() . ' ' . $commune->getNomCommune(),
                'codePostal' => $commune->getCodePostal(),
                'nomCommune' => $commune->getNomCommune(),
                'nomDepartement' => $commune->getNomDepartement()
            ];
        }
        
        return $this->json($results);
    }
}