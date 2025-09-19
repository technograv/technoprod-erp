<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Adresse;
use App\Entity\Contact;
use App\Entity\CommuneFrancaise;
use App\Entity\FormeJuridique;
use App\Form\ClientType;
use App\Service\ClientLoggerService;
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
    public function new(Request $request, EntityManagerInterface $entityManager, ClientLoggerService $clientLogger): Response
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
            $this->handleCustomFormSubmission($request, $client, $entityManager, $clientLogger);
            
            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }
        
        // Afficher les erreurs du formulaire si il y en a
        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true, false) as $error) {
                $this->addFlash('error', 'Erreur: ' . $error->getMessage());
            }
        }

        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }
    
    private function handleCustomFormSubmission(Request $request, Client $client, EntityManagerInterface $entityManager, ClientLoggerService $clientLogger): void
    {
        // Générer le prochain code disponible
        $repository = $entityManager->getRepository(Client::class);
        $nextCode = $client->getStatut() === 'client' 
            ? $repository->getNextClientCode() 
            : $repository->getNextProspectCode();
        $client->setCode($nextCode);
        
        // Gérer les données spécifiques à la forme juridique
        if ($client->getFormeJuridique() && $client->getFormeJuridique()->isPersonnePhysique()) {
            // Pour une personne physique, les données nom/prénom/civilité sont maintenant dans Contact
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
        
        // Logger la création du client
        $clientLogger->logCreated($client);
        
        // Logger la création du contact si créé
        if (isset($contact) && $contact) {
            $clientLogger->logContactAdded($client, $contact);
        }
        
        // Logger la création de l'adresse si créée
        if (isset($adresse) && $adresse) {
            $clientLogger->logAddressAdded($client, $adresse);
        }

        $this->addFlash('success', 'Client créé avec succès !');
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(Client $client, EntityManagerInterface $entityManager, ClientLoggerService $clientLogger): Response
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
            
        // Récupérer les logs du client
        $logs = $clientLogger->getClientLogs($client);
        
        return $this->render('client/show.html.twig', [
            'client' => $client,
            'logs' => $logs,
        ]);
    }


    #[Route('/{id}/convert', name: 'app_client_convert_to_client', methods: ['POST'])]
    public function convertToClient(Client $client, EntityManagerInterface $entityManager, ClientLoggerService $clientLogger): Response
    {
        if ($client->isClient()) {
            $this->addFlash('error', 'Ce prospect est déjà un client !');
            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        $client->convertToClient();
        $entityManager->flush();
        
        // Logger la conversion
        $clientLogger->logConvertedToClient($client);

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
                'label' => $label,
                'adresse_id' => $contact->getAdresse() ? $contact->getAdresse()->getId() : null,
                'prenom' => $contact->getPrenom(),
                'nom' => $contact->getNom(),
                'fonction' => $contact->getFonction(),
                'email' => $contact->getEmail(),
                'is_facturation_default' => $contact->isFacturationDefault(),
                'is_livraison_default' => $contact->isLivraisonDefault()
            ];
        }

        return $this->json($contacts);
    }

    #[Route('/{id}/addresses', name: 'app_client_addresses', methods: ['GET'])]
    public function getAddresses(Client $client): JsonResponse
    {
        $addresses = [];
        $addressesAdded = []; // Pour éviter les doublons
        
        foreach ($client->getContacts() as $contact) {
            $adresse = $contact->getAdresse();
            if ($adresse && !in_array($adresse->getId(), $addressesAdded)) {
                $label = $adresse->getDisplayLabel();
                
                $addresses[] = [
                    'id' => $adresse->getId(),
                    'label' => $label
                ];
                
                $addressesAdded[] = $adresse->getId(); // Marquer comme ajoutée
            }
        }

        return $this->json($addresses);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager, ClientLoggerService $clientLogger): Response
    {
        if ($request->isMethod('POST')) {
            // Traitement de la soumission du formulaire
            $data = $request->request->all();
            
            try {
                // Tracker les changements sur les champs principaux du client
                $clientChanges = [];
                
                // Dénomination
                $oldNom = $client->getNom();
                if (isset($data['nom']) && $oldNom !== $data['nom']) {
                    $clientChanges['nom'] = ['old' => $oldNom, 'new' => $data['nom']];
                    $client->setNom($data['nom']);
                } elseif (isset($data['nom'])) {
                    $client->setNom($data['nom']);
                }
                
                // Forme juridique
                $oldFormeJuridique = $client->getFormeJuridique();
                if (isset($data['forme_juridique']) && !empty($data['forme_juridique'])) {
                    $formeJuridique = $entityManager->getRepository(\App\Entity\FormeJuridique::class)->find($data['forme_juridique']);
                    if ($formeJuridique && $oldFormeJuridique !== $formeJuridique) {
                        $clientChanges['forme_juridique'] = [
                            'old' => $oldFormeJuridique ? $oldFormeJuridique->getNom() : null,
                            'new' => $formeJuridique->getNom()
                        ];
                        $client->setFormeJuridique($formeJuridique);
                    } elseif ($formeJuridique) {
                        $client->setFormeJuridique($formeJuridique);
                    }
                }
                
                // Délai de paiement
                $oldDelaiPaiement = $client->getDelaiPaiement();
                if (isset($data['delai_paiement'])) {
                    $newDelaiPaiement = (int)$data['delai_paiement'];
                    if ($oldDelaiPaiement !== $newDelaiPaiement) {
                        $clientChanges['delai_paiement'] = ['old' => $oldDelaiPaiement, 'new' => $newDelaiPaiement];
                    }
                    $client->setDelaiPaiement($newDelaiPaiement);
                }
                
                // Mode de paiement
                $oldModePaiement = $client->getModePaiement();
                if (isset($data['mode_paiement']) && $oldModePaiement !== $data['mode_paiement']) {
                    $clientChanges['mode_paiement'] = ['old' => $oldModePaiement, 'new' => $data['mode_paiement']];
                    $client->setModePaiement($data['mode_paiement']);
                } elseif (isset($data['mode_paiement'])) {
                    $client->setModePaiement($data['mode_paiement']);
                }
                
                // Conditions tarifaires
                $oldConditionsTarifs = $client->getConditionsTarifs();
                if (isset($data['conditions_tarifs']) && $oldConditionsTarifs !== $data['conditions_tarifs']) {
                    $clientChanges['conditions_tarifs'] = ['old' => $oldConditionsTarifs, 'new' => $data['conditions_tarifs']];
                    $client->setConditionsTarifs($data['conditions_tarifs']);
                } elseif (isset($data['conditions_tarifs'])) {
                    $client->setConditionsTarifs($data['conditions_tarifs']);
                }
                
                // Notes
                $oldNotes = $client->getNotes();
                if (isset($data['notes']) && $oldNotes !== $data['notes']) {
                    $clientChanges['notes'] = ['old' => $oldNotes, 'new' => $data['notes']];
                    $client->setNotes($data['notes']);
                } elseif (isset($data['notes'])) {
                    $client->setNotes($data['notes']);
                }
                
                // Assujetti TVA
                $oldAssujettiTva = $client->isAssujettiTva();
                $newAssujettiTva = isset($data['assujetti_tva']);
                if ($oldAssujettiTva !== $newAssujettiTva) {
                    $clientChanges['assujetti_tva'] = ['old' => $oldAssujettiTva, 'new' => $newAssujettiTva];
                }
                $client->setAssujettiTva($newAssujettiTva);
                
                // Famille (pas de log spécifique car champ technique)
                if (isset($data['famille'])) $client->setFamille($data['famille']);
            
            // Gestion des contacts - Créer une correspondance pour les nouveaux contacts
            $contactIdMapping = []; // Correspondance entre IDs temporaires et vrais IDs
            $newContacts = []; // Pour tracker les nouveaux contacts à logger
            $updatedContacts = []; // Pour tracker les contacts modifiés
            
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
                        $newContacts[] = $contact; // Tracker pour logging
                    } else {
                        // Contact existant
                        $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                        if ($contact && $contact->getClient() === $client) {
                            // Vérifier s'il y a eu des changements
                            $hasChanges = false;
                            $changes = [];
                            
                            if ($contact->getNom() !== ($contactData['nom'] ?? '')) {
                                $changes['nom'] = ['old' => $contact->getNom(), 'new' => $contactData['nom'] ?? ''];
                                $hasChanges = true;
                            }
                            if ($contact->getPrenom() !== ($contactData['prenom'] ?? '')) {
                                $changes['prenom'] = ['old' => $contact->getPrenom(), 'new' => $contactData['prenom'] ?? ''];
                                $hasChanges = true;
                            }
                            if ($contact->getEmail() !== ($contactData['email'] ?? '')) {
                                $changes['email'] = ['old' => $contact->getEmail(), 'new' => $contactData['email'] ?? ''];
                                $hasChanges = true;
                            }
                            if ($contact->getTelephone() !== ($contactData['telephone'] ?? '')) {
                                $changes['telephone'] = ['old' => $contact->getTelephone(), 'new' => $contactData['telephone'] ?? ''];
                                $hasChanges = true;
                            }
                            
                            if ($hasChanges) {
                                $updatedContacts[] = ['contact' => $contact, 'changes' => $changes];
                            }
                            
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
            $newAddresses = []; // Pour tracker les nouvelles adresses à logger
            $updatedAddresses = []; // Pour tracker les adresses modifiées
            
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
                        $newAddresses[] = $adresse; // Tracker pour logging
                    } else {
                        // Adresse existante
                        $adresse = $entityManager->getRepository(Adresse::class)->find($adresseId);
                        if ($adresse && $adresse->getClient() === $client) {
                            // Vérifier s'il y a eu des changements
                            $hasChanges = false;
                            $changes = [];
                            
                            if ($adresse->getNom() !== ($adresseData['nom'] ?? '')) {
                                $changes['nom'] = ['old' => $adresse->getNom(), 'new' => $adresseData['nom'] ?? ''];
                                $hasChanges = true;
                            }
                            if ($adresse->getLigne1() !== ($adresseData['ligne1'] ?? '')) {
                                $changes['ligne1'] = ['old' => $adresse->getLigne1(), 'new' => $adresseData['ligne1'] ?? ''];
                                $hasChanges = true;
                            }
                            if ($adresse->getCodePostal() !== ($adresseData['code_postal'] ?? '')) {
                                $changes['code_postal'] = ['old' => $adresse->getCodePostal(), 'new' => $adresseData['code_postal'] ?? ''];
                                $hasChanges = true;
                            }
                            if ($adresse->getVille() !== ($adresseData['ville'] ?? '')) {
                                $changes['ville'] = ['old' => $adresse->getVille(), 'new' => $adresseData['ville'] ?? ''];
                                $hasChanges = true;
                            }
                            if ($adresse->getPays() !== ($adresseData['pays'] ?? 'France')) {
                                $changes['pays'] = ['old' => $adresse->getPays(), 'new' => $adresseData['pays'] ?? 'France'];
                                $hasChanges = true;
                            }
                            
                            if ($hasChanges) {
                                $updatedAddresses[] = ['adresse' => $adresse, 'changes' => $changes];
                            }
                            
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
            $addressAssignments = []; // Pour tracker les assignations d'adresses
            
            if (isset($data['contacts'])) {
                foreach ($data['contacts'] as $contactId => $contactData) {
                    $contact = null;
                    
                    // Récupérer le contact (nouveau ou existant)
                    if (isset($contactIdMapping[$contactId])) {
                        $contact = $contactIdMapping[$contactId];
                    } elseif (is_numeric($contactId) && strpos($contactId, 'new_') === false) {
                        $contact = $entityManager->getRepository(Contact::class)->find((int)$contactId);
                    }
                    
                    if ($contact && $contact->getClient() === $client) {
                        $oldAdresse = $contact->getAdresse();
                        $newAdresseId = $contactData['adresse_id'] ?? null;
                        
                        if (!empty($newAdresseId)) {
                            if (is_numeric($newAdresseId) && strpos($newAdresseId, 'new_') === false) {
                                $newAdresse = $entityManager->getRepository(Adresse::class)->find((int)$newAdresseId);
                                if ($newAdresse && $newAdresse->getClient() === $client) {
                                    // Vérifier si c'est un changement d'adresse
                                    if (!$oldAdresse || $oldAdresse->getId() !== $newAdresse->getId()) {
                                        $addressAssignments[] = ['contact' => $contact, 'adresse' => $newAdresse];
                                    }
                                    $contact->setAdresse($newAdresse);
                                }
                            }
                        } else {
                            // Adresse supprimée du contact
                            if ($oldAdresse) {
                                $contact->setAdresse(null);
                            }
                        }
                    }
                }
            }
            
            // Tracker les changements de contacts par défaut
            $contactFacturationChange = null;
            $contactLivraisonChange = null;
            
            // Gestion des contacts par défaut - utiliser la correspondance d'IDs
            if (isset($data['contact_facturation_default']) && !empty($data['contact_facturation_default'])) {
                $oldContactFacturation = $client->getContactFacturationDefault();
                
                // Reset tous les contacts facturation par défaut
                foreach ($client->getContacts() as $contact) {
                    $contact->setIsFacturationDefault(false);
                }
                
                $facturationDefaultId = $data['contact_facturation_default'];
                $defaultContact = null;
                
                // Vérifier d'abord dans la correspondance (nouveaux et existants)
                if (isset($contactIdMapping[$facturationDefaultId])) {
                    $defaultContact = $contactIdMapping[$facturationDefaultId];
                } elseif (is_numeric($facturationDefaultId) && strpos($facturationDefaultId, 'new_') === false) {
                    // Fallback pour les contacts existants non modifiés - seulement si c'est numérique et pas temporaire
                    $defaultContact = $entityManager->getRepository(Contact::class)->find((int)$facturationDefaultId);
                }
                
                if ($defaultContact && $defaultContact->getClient() === $client) {
                    $defaultContact->setIsFacturationDefault(true);
                    $client->setContactFacturationDefault($defaultContact);
                    
                    // Tracker le changement si différent
                    if ($oldContactFacturation !== $defaultContact) {
                        $contactFacturationChange = ['old' => $oldContactFacturation, 'new' => $defaultContact];
                    }
                }
            }
            
            if (isset($data['contact_livraison_default']) && !empty($data['contact_livraison_default'])) {
                $oldContactLivraison = $client->getContactLivraisonDefault();
                
                // Reset tous les contacts livraison par défaut
                foreach ($client->getContacts() as $contact) {
                    $contact->setIsLivraisonDefault(false);
                }
                
                $livraisonDefaultId = $data['contact_livraison_default'];
                $defaultContact = null;
                
                // Vérifier d'abord dans la correspondance (nouveaux et existants)
                if (isset($contactIdMapping[$livraisonDefaultId])) {
                    $defaultContact = $contactIdMapping[$livraisonDefaultId];
                } elseif (is_numeric($livraisonDefaultId) && strpos($livraisonDefaultId, 'new_') === false) {
                    // Fallback pour les contacts existants non modifiés - seulement si c'est numérique et pas temporaire
                    $defaultContact = $entityManager->getRepository(Contact::class)->find((int)$livraisonDefaultId);
                }
                
                if ($defaultContact && $defaultContact->getClient() === $client) {
                    $defaultContact->setIsLivraisonDefault(true);
                    $client->setContactLivraisonDefault($defaultContact);
                    
                    // Tracker le changement si différent
                    if ($oldContactLivraison !== $defaultContact) {
                        $contactLivraisonChange = ['old' => $oldContactLivraison, 'new' => $defaultContact];
                    }
                }
            }
            
            try {
                $entityManager->flush();
                
                // Logger les actions spécifiques sur les champs principaux du client
                foreach ($clientChanges as $field => $change) {
                    switch ($field) {
                        case 'forme_juridique':
                            $clientLogger->logFormeJuridiqueChanged($client, $change['old'], $change['new']);
                            break;
                        case 'nom':
                            $clientLogger->logDenominationChanged($client, $change['old'], $change['new']);
                            break;
                        case 'delai_paiement':
                            $clientLogger->logDelaiPaiementChanged($client, $change['old'], $change['new']);
                            break;
                        case 'mode_paiement':
                            $clientLogger->logModePaiementChanged($client, $change['old'], $change['new']);
                            break;
                        case 'conditions_tarifs':
                            $clientLogger->logConditionsTarifsChanged($client, $change['old'], $change['new']);
                            break;
                        case 'notes':
                            $clientLogger->logNotesChanged($client, $change['old'], $change['new']);
                            break;
                        case 'assujetti_tva':
                            $clientLogger->logAssujettiTvaChanged($client, $change['old'], $change['new']);
                            break;
                    }
                }
                
                // Logger les actions spécifiques sur les contacts
                foreach ($newContacts as $contact) {
                    $clientLogger->logContactAdded($client, $contact);
                }
                
                foreach ($updatedContacts as $contactUpdate) {
                    $clientLogger->logContactUpdated($client, $contactUpdate['contact'], $contactUpdate['changes']);
                }
                
                // Logger les actions spécifiques sur les adresses
                foreach ($newAddresses as $adresse) {
                    $clientLogger->logAddressAdded($client, $adresse);
                }
                
                foreach ($updatedAddresses as $adresseUpdate) {
                    $clientLogger->logAddressUpdated($client, $adresseUpdate['adresse'], $adresseUpdate['changes']);
                }
                
                // Logger les assignations d'adresses aux contacts
                foreach ($addressAssignments as $assignment) {
                    $clientLogger->logAddressAssigned($client, $assignment['contact'], $assignment['adresse']);
                }
                
                // Logger les changements de contacts par défaut
                if ($contactFacturationChange) {
                    $clientLogger->logContactFacturationDefaultChanged($client, $contactFacturationChange['old'], $contactFacturationChange['new']);
                }
                
                if ($contactLivraisonChange) {
                    $clientLogger->logContactLivraisonDefaultChanged($client, $contactLivraisonChange['old'], $contactLivraisonChange['new']);
                }
                
                $this->addFlash('success', 'Client mis à jour avec succès !');
                return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());
            }
        }

        // Vérifier si c'est une requête pour modale
        $isModal = $request->query->get('modal') === '1' || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
        
        $template = $isModal ? 'client/edit_modal.html.twig' : 'client/edit.html.twig';
        
        return $this->render($template, [
            'client' => $client,
            'isModal' => $isModal,
        ]);
    }

    #[Route('/{id}/archive', name: 'app_client_archive', methods: ['POST'])]
    public function archive(Client $client, EntityManagerInterface $entityManager, ClientLoggerService $clientLogger): Response
    {
        // Archiver le client (le marquer comme inactif)
        // Marquer tous les contacts comme inactifs (équivalent ancien setActif)
        foreach ($client->getContacts() as $contact) {
            $contact->setActif(false);
        }
        
        try {
            $entityManager->flush();
            
            // Logger l'archivage
            $clientLogger->logArchived($client);
            
            $this->addFlash('success', 'Client archivé avec succès ! Il est maintenant inactif.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'archivage : ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_client_index');
    }

    #[Route('/api/clients/search', name: 'app_api_clients_search', methods: ['GET'])]
    public function searchClients(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([]);
        }
        
        try {
            $clients = $entityManager->getRepository(Client::class)
                ->createQueryBuilder('c')
                ->leftJoin('c.formeJuridique', 'fj')
                ->where('LOWER(c.nom) LIKE LOWER(:query) OR LOWER(c.prenom) LIKE LOWER(:query) OR LOWER(c.code) LIKE LOWER(:query)')
                ->setParameter('query', '%' . $query . '%')
                ->setMaxResults(10)
                ->orderBy('c.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
            
            $results = [];
            foreach ($clients as $client) {
                $nomEntreprise = $client->getNom() ?: ($client->getPrenom() . ' ' . $client->getCivilite());
                $formeJuridique = $client->getFormeJuridique() ? $client->getFormeJuridique()->getNom() : '';
                
                $results[] = [
                    'id' => $client->getId(),
                    'nom_entreprise' => trim($nomEntreprise),
                    'forme_juridique' => $formeJuridique,
                    'code_client' => $client->getCode() ?: ''
                ];
            }
            
            return $this->json($results);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    #[Route('/api/communes/search', name: 'app_api_communes_search', methods: ['GET'])]
    public function searchCommunes(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $request->query->get('q', '');
        $postal = $request->query->get('postal', '');
        $ville = $request->query->get('ville', '');
        
        // Si on a un paramètre postal ou ville spécifique
        if ($postal) {
            if (strlen($postal) < 2) {
                return $this->json(['communes' => []]);
            }
            
            $communes = $entityManager->getRepository(CommuneFrancaise::class)
                ->findBy(['codePostal' => $postal], null, 10);
            
            $results = [];
            foreach ($communes as $commune) {
                $results[] = [
                    'id' => $commune->getId(),
                    'nom' => $commune->getNomCommune(),
                    'codePostal' => $commune->getCodePostal(),
                    'nomCommune' => $commune->getNomCommune(),
                    'nomDepartement' => $commune->getNomDepartement()
                ];
            }
            
            return $this->json(['communes' => $results]);
        }
        
        if ($ville) {
            if (strlen($ville) < 3) {
                return $this->json(['communes' => []]);
            }
            
            $communes = $entityManager->getRepository(CommuneFrancaise::class)
                ->createQueryBuilder('c')
                ->where('LOWER(c.nomCommune) LIKE LOWER(:ville)')
                ->setParameter('ville', '%' . $ville . '%')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
            
            $results = [];
            foreach ($communes as $commune) {
                $results[] = [
                    'id' => $commune->getId(),
                    'nom' => $commune->getNomCommune(),
                    'codePostal' => $commune->getCodePostal(),
                    'nomCommune' => $commune->getNomCommune(),
                    'nomDepartement' => $commune->getNomDepartement()
                ];
            }
            
            return $this->json(['communes' => $results]);
        }
        
        // Recherche générale avec paramètre q (pour compatibilité)
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

    #[Route('/{id}/contact/{contactId}/delete', name: 'app_client_contact_delete', methods: ['POST'])]
    public function deleteContact(Client $client, int $contactId, EntityManagerInterface $entityManager, ClientLoggerService $clientLogger): JsonResponse
    {
        try {
            $contact = $entityManager->getRepository(Contact::class)->find($contactId);
            
            if (!$contact || $contact->getClient() !== $client) {
                return $this->json(['success' => false, 'message' => 'Contact non trouvé']);
            }
            
            // Vérifier si le contact peut être supprimé (pas le seul contact, pas contact par défaut)
            if ($contact->isFacturationDefault() || $contact->isLivraisonDefault()) {
                return $this->json(['success' => false, 'message' => 'Impossible de supprimer un contact par défaut']);
            }
            
            if ($client->getContacts()->count() <= 1) {
                return $this->json(['success' => false, 'message' => 'Impossible de supprimer le seul contact']);
            }
            
            $contactName = $contact->getNomComplet();
            
            $entityManager->remove($contact);
            $entityManager->flush();
            
            // Logger la suppression
            $clientLogger->logContactDeleted($client, $contactName);
            
            return $this->json(['success' => true, 'message' => 'Contact supprimé avec succès']);
            
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
        }
    }

    #[Route('/{id}/address/{addressId}/delete', name: 'app_client_address_delete', methods: ['POST'])]
    public function deleteAddress(Client $client, int $addressId, EntityManagerInterface $entityManager, ClientLoggerService $clientLogger): JsonResponse
    {
        try {
            $adresse = $entityManager->getRepository(Adresse::class)->find($addressId);
            
            if (!$adresse || $adresse->getClient() !== $client) {
                return $this->json(['success' => false, 'message' => 'Adresse non trouvée']);
            }
            
            // Vérifier si l'adresse peut être supprimée (pas utilisée par un contact, pas la seule adresse)
            $isUsed = false;
            foreach ($client->getContacts() as $contact) {
                if ($contact->getAdresse() && $contact->getAdresse()->getId() === $adresse->getId()) {
                    $isUsed = true;
                    break;
                }
            }
            
            if ($isUsed) {
                return $this->json(['success' => false, 'message' => 'Impossible de supprimer une adresse utilisée par un contact']);
            }
            
            // Compter seulement les adresses non supprimées
            $activeAddresses = $client->getAdresses()->filter(function($addr) {
                return !$addr->isDeleted();
            });
            
            if ($activeAddresses->count() <= 1) {
                return $this->json(['success' => false, 'message' => 'Impossible de supprimer la seule adresse']);
            }
            
            $addressName = $adresse->getNom();
            
            // Soft delete au lieu de suppression réelle
            $adresse->softDelete();
            $entityManager->flush();
            
            // Logger la suppression
            $clientLogger->logAddressDeleted($client, $addressName);
            
            return $this->json(['success' => true, 'message' => 'Adresse supprimée avec succès']);
            
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
        }
    }

    #[Route('/list-json', name: 'app_client_list_json', methods: ['GET'])]
    public function listJson(EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Vérifier l'authentification
            if (!$this->getUser()) {
                return $this->json([
                    'error' => true,
                    'message' => 'Authentification requise',
                    'data' => []
                ], 401);
            }
            
            // Utiliser une requête DQL simple pour éviter les problèmes de lazy loading
            $dql = 'SELECT c.id, c.nom, c.prenom, c.statut FROM App\Entity\Client c ORDER BY c.nom ASC';
            $query = $entityManager->createQuery($dql);
            $results = $query->getArrayResult();
            
            $data = [];
            foreach ($results as $row) {
                try {
                    $nom = $row['nom'] ?? 'Client sans nom';
                    $prenom = $row['prenom'] ?? '';
                    
                    // Construction simple du nom complet
                    $nomComplet = trim($prenom . ' ' . $nom);
                    if (empty($nomComplet)) {
                        $nomComplet = 'Client #' . $row['id'];
                    }
                    
                    $data[] = [
                        'id' => $row['id'],
                        'nom' => $nom,
                        'nomComplet' => $nomComplet,
                        'statut' => $row['statut'] ?? 'prospect'
                    ];
                } catch (\Exception $e) {
                    // Si un client pose problème, on l'ignore mais on continue
                    continue;
                }
            }
            
            return $this->json($data);
            
        } catch (\Exception $e) {
            // En cas d'erreur, retourner une liste vide avec un message
            return $this->json([
                'error' => true,
                'message' => 'Erreur lors du chargement des clients: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    #[Route('/modal/new', name: 'app_client_modal_new', methods: ['GET', 'POST'])]
    public function modalNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        try {
            if ($request->isMethod('GET')) {
                // Récupérer les formes juridiques
                $formesJuridiques = $entityManager->getRepository(\App\Entity\FormeJuridique::class)
                    ->findBy(['actif' => true], ['ordre' => 'ASC']);
                
                return $this->render('client/modal_new_working.html.twig', [
                    'client' => new Client(),
                    'type' => 'client',
                    'formes_juridiques' => $formesJuridiques
                ]);
            }
            
            if ($request->isMethod('POST')) {
                // Créer un nouveau client
                $client = new Client();
                $type = $request->query->get('type', 'client');
                $client->setStatut($type === 'prospect' ? 'prospect' : 'client');
                
                // Traitement des données du formulaire POST
                $formeJuridiqueId = $request->request->get('formeJuridique');
                $nom = $request->request->get('nom');
                $prenom = $request->request->get('prenom');
                $nomEntreprise = $request->request->get('nomEntreprise');
                $civilite = $request->request->get('civilite');
                
                // Récupérer email/téléphone pour les particuliers et notes pour tous
                $emailParticulier = $request->request->get('emailParticulier');
                $telephoneParticulier = $request->request->get('telephoneParticulier');
                $notes = $request->request->get('notes');

                if ($formeJuridiqueId && ($nom || $nomEntreprise)) {
                    // Récupérer la forme juridique
                    $formeJuridique = $entityManager->getRepository(\App\Entity\FormeJuridique::class)->find($formeJuridiqueId);
                    if ($formeJuridique) {
                        $client->setFormeJuridique($formeJuridique);
                    }
                    
                    // Configurer les champs de base du client
                    if ($formeJuridique && $formeJuridique->getTemplateFormulaire() === 'personne_physique') {
                        // Pour un particulier, les données vont dans le contact principal
                        // (nom/prénom/civilité/email/telephone maintenant dans Contact)
                        // Ne plus définir directement sur Client depuis refactoring BDD
                    } else {
                        // Pour une entreprise
                        $client->setNomEntreprise($nomEntreprise);
                    }
                    
                    // Ajouter les notes si fournies
                    if ($notes) {
                        $client->setNotes($notes);
                    }
                    
                    // Générer le code client automatiquement
                    $code = $this->generateClientCode($entityManager, $type);
                    $client->setCode($code);

                    $entityManager->persist($client);
                    
                    // Créer contact et adresse selon le type de personne
                    if ($formeJuridique && $formeJuridique->getTemplateFormulaire() === 'personne_physique') {
                        // Pour un particulier, créer un contact basé sur ses propres infos
                        if ($nom) {
                            $contact = new \App\Entity\Contact();
                            $contact->setClient($client);
                            $contact->setCivilite($civilite);
                            $contact->setNom($nom);
                            $contact->setPrenom($prenom);
                            $contact->setEmail($emailParticulier);
                            $contact->setTelephone($telephoneParticulier);
                            $contact->setIsFacturationDefault(true);
                            $contact->setIsLivraisonDefault(true);
                            
                            $entityManager->persist($contact);
                            
                            // Créer l'adresse personnelle
                            $adresseLigne1 = $request->request->get('particulier_adresse_ligne1');
                            if ($adresseLigne1) {
                                $adresse = new \App\Entity\Adresse();
                                $adresse->setClient($client);
                                $adresse->setNom('Domicile');
                                $adresse->setLigne1($adresseLigne1);
                                $adresse->setCodePostal($request->request->get('particulier_adresse_codePostal'));
                                $adresse->setVille($request->request->get('particulier_adresse_ville'));
                                $adresse->setPays($request->request->get('particulier_adresse_pays') ?: 'France');
                                
                                $entityManager->persist($adresse);
                                
                                // Associer l'adresse au contact
                                $contact->setAdresse($adresse);
                            }
                            
                            // Définir comme contacts par défaut pour le client
                            $client->setContactFacturationDefault($contact);
                            $client->setContactLivraisonDefault($contact);
                        }
                    } elseif ($formeJuridique && $formeJuridique->getTemplateFormulaire() === 'personne_morale') {
                        // Créer le contact principal
                        $contactNom = $request->request->get('contact_nom');
                        if ($contactNom) {
                            $contact = new \App\Entity\Contact();
                            $contact->setClient($client);
                            $contact->setCivilite($request->request->get('contact_civilite'));
                            $contact->setNom($contactNom);
                            $contact->setPrenom($request->request->get('contact_prenom'));
                            $contact->setFonction($request->request->get('contact_fonction'));
                            $contact->setEmail($request->request->get('contact_email'));
                            $contact->setTelephone($request->request->get('contact_telephone'));
                            $contact->setIsFacturationDefault(true);
                            $contact->setIsLivraisonDefault(true);
                            
                            $entityManager->persist($contact);
                            
                            // Créer l'adresse du siège social
                            $adresseLigne1 = $request->request->get('adresse_ligne1');
                            if ($adresseLigne1) {
                                $adresse = new \App\Entity\Adresse();
                                $adresse->setClient($client);
                                $adresse->setNom('Siège social');
                                $adresse->setLigne1($adresseLigne1);
                                $adresse->setLigne2($request->request->get('adresse_ligne2'));
                                $adresse->setCodePostal($request->request->get('adresse_codePostal'));
                                $adresse->setVille($request->request->get('adresse_ville'));
                                $adresse->setPays($request->request->get('adresse_pays') ?: 'France');
                                
                                $entityManager->persist($adresse);
                                
                                // Associer l'adresse au contact
                                $contact->setAdresse($adresse);
                                
                                // Définir comme contacts par défaut pour le client
                                $client->setContactFacturationDefault($contact);
                                $client->setContactLivraisonDefault($contact);
                            }
                        }
                    }
                    
                    $entityManager->flush();

                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'success' => true,
                            'message' => ($type === 'prospect' ? 'Prospect' : 'Client') . ' créé avec succès',
                            'client' => [
                                'id' => $client->getId(),
                                'label' => $client->getNomEntreprise() ?: ($client->getPrenom() . ' ' . $client->getNom()),
                                'nom' => $client->getNom(),
                                'nomEntreprise' => $client->getNomEntreprise(),
                                'email' => $client->getEmail(),
                                'code' => $client->getCode()
                            ]
                        ]);
                    }

                    return $this->redirectToRoute('app_client_edit', ['id' => $client->getId()]);
                }

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Le nom ou l\'entreprise est obligatoire'
                    ], 400);
                }
                
                // Retourner le template avec les erreurs
                $formesJuridiques = $entityManager->getRepository(\App\Entity\FormeJuridique::class)
                    ->findBy(['actif' => true], ['ordre' => 'ASC']);
                
                return $this->render('client/modal_new_working.html.twig', [
                    'client' => $client,
                    'type' => 'client',
                    'formes_juridiques' => $formesJuridiques,
                    'error' => 'Le nom ou l\'entreprise est obligatoire'
                ]);
            }
        } catch (\Exception $e) {
            // Log de débogage
            error_log("Erreur modalNew: " . $e->getMessage());
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur serveur: ' . $e->getMessage()
                ], 500);
            }
            throw $e;
        }
    }
    
    #[Route('/modal/edit/{id}', name: 'app_client_modal_edit', methods: ['GET', 'POST'])]
    public function modalEdit(Client $client, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            // Traitement des données du formulaire POST
            $nom = $request->request->get('nom');
            $prenom = $request->request->get('prenom');
            $nomEntreprise = $request->request->get('nomEntreprise');
            $email = $request->request->get('email');
            $telephone = $request->request->get('telephone');
            $notes = $request->request->get('notes');
            $formeJuridiqueId = $request->request->get('formeJuridique');

            if ($nom || $nomEntreprise) {
                // Mettre à jour les données sur Client
                $client->setNomEntreprise($nomEntreprise);
                $client->setNotes($notes);
                
                // Gérer la forme juridique
                if ($formeJuridiqueId) {
                    $formeJuridique = $entityManager->getRepository(FormeJuridique::class)->find($formeJuridiqueId);
                    if ($formeJuridique) {
                        $client->setFormeJuridique($formeJuridique);
                    }
                }

                // Mettre à jour le contact de facturation par défaut avec nom/prénom/email/telephone
                $contact = $client->getContactFacturationDefault();
                if ($contact && ($nom || $prenom || $email || $telephone)) {
                    if ($nom) $contact->setNom($nom);
                    if ($prenom) $contact->setPrenom($prenom);
                    if ($email) $contact->setEmail($email);
                    if ($telephone) $contact->setTelephone($telephone);
                }

                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Client modifié avec succès',
                        'client' => [
                            'id' => $client->getId(),
                            'label' => $client->getNomEntreprise() ?: ($client->getPrenom() . ' ' . $client->getNom()),
                            'nom' => $client->getNom(),
                            'nomEntreprise' => $client->getNomEntreprise(),
                            'email' => $client->getEmail(),
                            'code' => $client->getCode()
                        ]
                    ]);
                }

                return $this->redirectToRoute('app_client_edit', ['id' => $client->getId()]);
            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le nom ou l\'entreprise est obligatoire'
                ], 400);
            }
        }

        // Récupérer les formes juridiques pour le sélecteur
        $formeJuridiqueRepository = $entityManager->getRepository(FormeJuridique::class);
        $formesJuridiques = $formeJuridiqueRepository->findBy(['actif' => true], ['nom' => 'ASC']);

        return $this->render('client/modal_edit.html.twig', [
            'client' => $client,
            'formes_juridiques' => $formesJuridiques
        ]);
    }
    
    private function generateClientCode(EntityManagerInterface $entityManager, string $type): string
    {
        $prefix = $type === 'prospect' ? 'P' : 'C';
        $year = date('Y');
        
        // Trouver le dernier code pour ce type et cette année
        $lastClient = $entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->where('c.code LIKE :pattern')
            ->setParameter('pattern', $prefix . $year . '%')
            ->orderBy('c.code', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($lastClient && $lastClient->getCode()) {
            // Extraire le numéro et l'incrémenter
            $lastNumber = intval(substr($lastClient->getCode(), -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . $year . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}