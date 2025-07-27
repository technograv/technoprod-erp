<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\DevisItem;
use App\Form\DevisType;
use App\Repository\DevisRepository;
use App\Repository\ClientRepository;
use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Adresse;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Service\GmailMailerService;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/devis')]
final class DevisController extends AbstractController
{
    #[Route(name: 'app_devis_index', methods: ['GET'])]
    public function index(DevisRepository $devisRepository, Request $request): Response
    {
        // Filtres
        $statut = $request->query->get('statut');
        $prospect = $request->query->get('prospect');
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $criteria = [];
        if ($statut) {
            $criteria['statut'] = $statut;
        }

        $devis = $devisRepository->findBy($criteria, ['createdAt' => 'DESC']);

        // Statistiques
        $stats = [
            'total' => count($devis),
            'brouillon' => count(array_filter($devis, fn($d) => $d->getStatut() === 'brouillon')),
            'envoye' => count(array_filter($devis, fn($d) => $d->getStatut() === 'envoye')),
            'signe' => count(array_filter($devis, fn($d) => $d->getStatut() === 'signe')),
            'accepte' => count(array_filter($devis, fn($d) => $d->getStatut() === 'accepte')),
            'ca_potentiel' => array_sum(array_map(fn($d) => floatval($d->getTotalTtc()), $devis))
        ];

        return $this->render('devis/index.html.twig', [
            'devis' => $devis,
            'stats' => $stats,
            'current_filters' => [
                'statut' => $statut,
                'prospect' => $prospect,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ]
        ]);
    }

    #[Route('/new', name: 'app_devis_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ClientRepository $clientRepository): Response
    {
        // Redirection vers la nouvelle interface améliorée
        return $this->redirectToRoute('app_devis_new_improved');
    }

    #[Route('/new-improved', name: 'app_devis_new_improved', methods: ['GET', 'POST'])]
    public function newImproved(Request $request, EntityManagerInterface $entityManager, ClientRepository $clientRepository): Response
    {
        $devis = new Devis();
        
        // Auto-assignment du commercial à l'utilisateur connecté
        $devis->setCommercial($this->getUser());
        
        // Définir le statut par défaut comme "brouillon"
        $devis->setStatut('brouillon');
        
        // Générer le prochain numéro de devis pour l'affichage
        $year = date('Y');
        $count = $entityManager->getRepository(Devis::class)->count([]);
        $nextDevisNumber = $year . '-DEV-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        // Si un prospect est passé en paramètre
        $prospectId = $request->query->get('prospect');
        if ($prospectId) {
            $prospect = $clientRepository->find($prospectId);
            if ($prospect) {
                $devis->setClient($prospect);
                
                // Pré-remplir les informations tiers éditables
                $devis->setTiersCivilite($prospect->getCivilite());
                $devis->setTiersNom($prospect->getNom());
                $devis->setTiersPrenom($prospect->getPrenom());
                $devis->setTiersModeReglement($prospect->getModePaiement());
                
                // Pré-remplir l'adresse de facturation
                if ($prospect->getAdresseFacturation()) {
                    $adresse = $prospect->getAdresseFacturation();
                    $devis->setTiersAdresse($adresse->getLigne1());
                    $devis->setTiersCodePostal($adresse->getCodePostal());
                    $devis->setTiersVille($adresse->getVille());
                    $devis->setAdresseFacturation($adresse);
                }
                
                // Pré-remplir les autres informations
                if ($prospect->getContactFacturation()) {
                    $devis->setContactFacturation($prospect->getContactFacturation());
                }
                if ($prospect->getContactLivraison()) {
                    $devis->setContactLivraison($prospect->getContactLivraison());
                }
                if ($prospect->getAdresseLivraison()) {
                    $devis->setAdresseLivraison($prospect->getAdresseLivraison());
                }
            }
        }

        // Traitement POST - sauvegarde du devis
        if ($request->isMethod('POST')) {
            // Récupérer les données du formulaire
            $prospectId = $request->request->get('prospect');
            $dateCreation = $request->request->get('date_creation');
            $dateValidite = $request->request->get('date_validite');
            $conditionsReglement = $request->request->get('conditions_reglement');
            $modeReglement = $request->request->get('mode_reglement');
            $delaiLivraison = $request->request->get('delai_livraison');
            $methodeExpedition = $request->request->get('methode_expedition');
            $dateLivraison = $request->request->get('date_livraison');
            $modeleDocument = $request->request->get('modele_document');
            $nomProjet = $request->request->get('nom_projet');
            $notePublique = $request->request->get('note_publique');
            $notePrivee = $request->request->get('note_privee');
            $contactDefaut = $request->request->get('contact_defaut');
            $projetExistant = $request->request->get('projet_existant');
            $contactFacturation = $request->request->get('contact_facturation');
            $contactLivraison = $request->request->get('contact_livraison');
            $adresseFacturation = $request->request->get('adresse_facturation');
            $adresseLivraison = $request->request->get('adresse_livraison');
            
            // Validation basique
            if (!$prospectId) {
                $this->addFlash('error', 'Veuillez sélectionner un client/prospect.');
                return $this->redirectToRoute('app_devis_new_improved');
            }
            
            // Trouver le prospect
            $prospect = $clientRepository->find($prospectId);
            if (!$prospect) {
                $this->addFlash('error', 'Client/prospect introuvable.');
                return $this->redirectToRoute('app_devis_new_improved');
            }
            
            // Configurer le devis
            $devis->setClient($prospect);
            $devis->setNumeroDevis($nextDevisNumber);
            $devis->setDateCreation(new \DateTime($dateCreation ?: 'now'));
            $devis->setDateValidite(new \DateTime($dateValidite ?: '+30 days'));
            $devis->setDelaiLivraison($delaiLivraison);
            $devis->setNotesClient($notePublique);
            $devis->setNotesInternes($notePrivee);
            
            // Pré-remplir les informations tiers depuis le prospect
            $devis->setTiersCivilite($prospect->getCivilite());
            $devis->setTiersNom($prospect->getNom());
            $devis->setTiersPrenom($prospect->getPrenom());
            $devis->setTiersModeReglement($modeReglement ?: $prospect->getModePaiement());
            
            if ($prospect->getAdresseFacturation()) {
                $adresse = $prospect->getAdresseFacturation();
                $devis->setTiersAdresse($adresse->getLigne1());
                $devis->setTiersCodePostal($adresse->getCodePostal());
                $devis->setTiersVille($adresse->getVille());
                $devis->setAdresseFacturation($adresse);
            }
            
            // Gestion des contacts et adresses sélectionnés
            if ($contactFacturation) {
                $contact = $entityManager->getRepository(Contact::class)->find($contactFacturation);
                if ($contact) {
                    $devis->setContactFacturation($contact);
                }
            }
            
            if ($contactLivraison) {
                $contact = $entityManager->getRepository(Contact::class)->find($contactLivraison);
                if ($contact) {
                    $devis->setContactLivraison($contact);
                }
            }
            
            if ($adresseFacturation) {
                $adresse = $entityManager->getRepository(Adresse::class)->find($adresseFacturation);
                if ($adresse) {
                    $devis->setAdresseFacturation($adresse);
                }
            }
            
            if ($adresseLivraison) {
                $adresse = $entityManager->getRepository(Adresse::class)->find($adresseLivraison);
                if ($adresse) {
                    $devis->setAdresseLivraison($adresse);
                }
            }
            
            // Sauvegarder le devis
            $entityManager->persist($devis);
            $entityManager->flush();
            
            $this->addFlash('success', 'Devis créé avec succès ! Vous pouvez maintenant ajouter les lignes.');
            return $this->redirectToRoute('app_devis_edit', ['id' => $devis->getId()]);
        }
        
        // Récupérer tous les prospects pour le sélecteur
        $prospects = $clientRepository->findAll();

        return $this->render('devis/new_improved.html.twig', [
            'devis' => $devis,
            'prospects' => $prospects,
            'next_devis_number' => $nextDevisNumber,
        ]);
    }

    #[Route('/{id}', name: 'app_devis_show', methods: ['GET'])]
    public function show(Devis $devis): Response
    {
        return $this->render('devis/show.html.twig', [
            'devis' => $devis,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_devis_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Devis $devis, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DevisType::class, $devis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // S'assurer que chaque ligne a ses totaux calculés
            foreach ($devis->getDevisItems() as $item) {
                $item->calculateTotal();
            }
            
            // Recalculer les totaux globaux
            $devis->calculateTotals();
            
            $entityManager->flush();

            $this->addFlash('success', 'Devis modifié avec succès !');
            return $this->redirectToRoute('app_devis_show', ['id' => $devis->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('devis/edit.html.twig', [
            'devis' => $devis,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_devis_delete', methods: ['POST'])]
    public function delete(Request $request, Devis $devis, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$devis->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($devis);
            $entityManager->flush();
            $this->addFlash('success', 'Devis supprimé avec succès !');
        }

        return $this->redirectToRoute('app_devis_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/pdf', name: 'app_devis_pdf', methods: ['GET'])]
    public function generatePdf(Devis $devis): Response
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        
        $html = $this->renderView('devis/pdf.html.twig', [
            'devis' => $devis
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="devis-' . $devis->getNumeroDevis() . '.pdf"'
            ]
        );
    }

    #[Route('/{id}/envoyer', name: 'app_devis_envoyer', methods: ['POST'])]
    public function envoyer(Request $request, Devis $devis, EntityManagerInterface $entityManager, GmailMailerService $gmailMailer): Response
    {
        $email = $request->request->get('email');
        $message = $request->request->get('message', '');

        if (!$email) {
            $this->addFlash('error', 'L\'adresse email est obligatoire');
            return $this->redirectToRoute('app_devis_show', ['id' => $devis->getId()]);
        }

        try {
            // Générer le PDF
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $dompdf = new Dompdf($options);
            
            $html = $this->renderView('devis/pdf.html.twig', ['devis' => $devis]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Préparer l'email (sans définir l'expéditeur, le service s'en charge)
            $emailMessage = (new Email())
                ->to($email)
                ->subject('Devis ' . $devis->getNumeroDevis() . ' - TechnoProd')
                ->html($this->renderView('emails/devis.html.twig', [
                    'devis' => $devis,
                    'message' => $message
                ]))
                ->attach($dompdf->output(), 'devis-' . $devis->getNumeroDevis() . '.pdf', 'application/pdf');

            // Générer l'URL d'accès client AVANT l'envoi de l'email
            $baseUrl = $_ENV['APP_BASE_URL'] ?? 'https://test.decorpub.fr:8080';
            $token = md5($devis->getId() . $devis->getCreatedAt()->format('Y-m-d'));
            $urlAcces = $baseUrl . '/devis/' . $devis->getId() . '/client/' . $token;
            $devis->setUrlAccesClient($urlAcces);

            // Envoyer l'email avec le service Gmail
            $gmailMailer->sendWithUserGmail($emailMessage, $this->getUser());

            // Mettre à jour le statut et la date d'envoi
            $devis->setStatut('envoye');
            $devis->setDateEnvoi(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'Devis envoyé avec succès à ' . $email);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_devis_show', ['id' => $devis->getId()]);
    }

    #[Route('/{id}/client/{token}', name: 'app_devis_client_acces', methods: ['GET', 'POST'])]
    public function clientAcces(Request $request, Devis $devis, string $token, EntityManagerInterface $entityManager): Response
    {
        // Vérifier le token
        $expectedToken = md5($devis->getId() . $devis->getCreatedAt()->format('Y-m-d'));
        if ($token !== $expectedToken) {
            throw $this->createNotFoundException('Accès non autorisé');
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            if ($action === 'signer') {
                $signatureNom = $request->request->get('signature_nom');
                $signatureEmail = $request->request->get('signature_email');
                $signatureData = $request->request->get('signature_data');

                if ($signatureNom && $signatureEmail && $signatureData) {
                    $devis->setSignatureNom($signatureNom);
                    $devis->setSignatureEmail($signatureEmail);
                    $devis->setSignatureData($signatureData);
                    $devis->setDateSignature(new \DateTime());
                    $devis->setStatut('signe');

                    $entityManager->flush();

                    $this->addFlash('success', 'Devis signé avec succès !');
                }
            } elseif ($action === 'refuser') {
                $devis->setStatut('refuse');
                $entityManager->flush();

                $this->addFlash('info', 'Devis refusé.');
            }
        }

        return $this->render('devis/client_acces.html.twig', [
            'devis' => $devis,
            'token' => $token
        ]);
    }

    #[Route('/{id}/paiement-acompte', name: 'app_devis_paiement_acompte', methods: ['POST'])]
    public function paiementAcompte(Request $request, Devis $devis, EntityManagerInterface $entityManager): Response
    {
        $transactionId = $request->request->get('transaction_id');
        $modePaiement = $request->request->get('mode_paiement');

        if ($transactionId && $modePaiement) {
            $devis->setTransactionId($transactionId);
            $devis->setModePaiement($modePaiement);
            $devis->setDatePaiementAcompte(new \DateTime());
            $devis->setStatut('acompte_regle');

            $entityManager->flush();

            return new JsonResponse(['status' => 'success', 'message' => 'Paiement enregistré']);
        }

        return new JsonResponse(['status' => 'error', 'message' => 'Données manquantes'], 400);
    }

    #[Route('/api/produits', name: 'app_devis_api_produits', methods: ['GET'])]
    public function apiProduits(Request $request, ProduitRepository $produitRepository): JsonResponse
    {
        $term = $request->query->get('q', '');
        
        if (strlen($term) < 2) {
            return new JsonResponse([]);
        }

        $produits = $produitRepository->search($term);
        
        $result = [];
        foreach ($produits as $produit) {
            $result[] = [
                'id' => $produit->getId(),
                'reference' => $produit->getReference(),
                'designation' => $produit->getDesignation(),
                'description' => $produit->getDescription(),
                'prix_vente_ht' => $produit->getPrixVenteHt(),
                'taux_tva' => $produit->getTvaPercent(),
                'unite' => $produit->getUnite(),
                'text' => $produit->getReference() . ' - ' . $produit->getDesignation() . ' (' . $produit->getPrixVenteHt() . '€ HT)'
            ];
        }

        return new JsonResponse($result);
    }

    #[Route('/ajax/create-client', name: 'app_devis_api_client_create', methods: ['POST'])]
    public function apiCreateClient(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation des données obligatoires
            if (!isset($data['nom']) || !isset($data['email'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le nom et l\'email sont obligatoires'
                ], 400);
            }
            
            // Vérifier si un prospect/client existe déjà (par email ou nom d'entreprise)
            $existingByEmail = $entityManager->getRepository(Client::class)
                ->findOneBy(['email' => $data['email']]);
            
            if ($existingByEmail) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Un client avec cet email existe déjà : ' . $existingByEmail->getNom()
                ], 400);
            }
            
            // Si nom d'entreprise fourni, vérifier s'il existe déjà
            if (!empty($data['nom_entreprise'])) {
                $existingByCompany = $entityManager->getRepository(Client::class)
                    ->findOneBy(['nomEntreprise' => $data['nom_entreprise']]);
                
                if ($existingByCompany) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'L\'entreprise "' . $data['nom_entreprise'] . '" existe déjà. Voulez-vous ajouter un nouveau contact à cette entreprise ?'
                    ], 400);
                }
            }
            
            // Créer le nouveau prospect
            $prospect = new Client();
            $prospect->setCivilite($data['civilite'] ?? 'M.');
            
            // Si nom d'entreprise fourni, l'utiliser comme nom principal
            if (!empty($data['nom_entreprise'])) {
                $prospect->setNom($data['nom_entreprise']); // Nom principal = entreprise
                $prospect->setNomEntreprise($data['nom_entreprise']);
                $prospect->setPrenom($data['prenom'] ?? '');
                // Le nom de famille devient un attribut du contact principal
            } else {
                // Personne physique
                $prospect->setNom($data['nom']); // Nom de famille
                $prospect->setPrenom($data['prenom'] ?? '');
                $prospect->setNomEntreprise('');
            }
            
            $prospect->setEmail($data['email']);
            $prospect->setTelephone($data['telephone'] ?? '');
            
            // Définir des valeurs par défaut  
            $prospect->setStatut($data['type'] ?? 'prospect');
            
            // Déterminer le type de personne selon le contexte
            if (!empty($data['nom_entreprise'])) {
                $prospect->setTypePersonne('morale'); // Entreprise
            } else {
                $prospect->setTypePersonne('physique'); // Personne physique
            }
            
            $prospect->setModePaiement('virement');
            
            // Générer un code temporaire court (max 20 caractères)
            $tempCode = 'T' . substr(uniqid(), -7) . substr(time(), -7);
            $prospect->setCode($tempCode);
            
            $entityManager->persist($prospect);
            $entityManager->flush();
            
            // Maintenant générer le vrai code avec l'ID
            $prospect->setCode($prospect->generateCode());
            
            // Créer automatiquement un contact principal si c'est une entreprise avec une personne de contact
            if (!empty($data['nom_entreprise']) && !empty($data['nom'])) {
                $contact = new Contact();
                $contact->setClient($prospect);
                $contact->setCivilite($data['civilite'] ?? 'M.');
                $contact->setNom($data['nom']); // Nom de famille de la personne
                $contact->setPrenom($data['prenom'] ?? '');
                $contact->setFonction('Contact principal');
                $contact->setEmail($data['email']);
                $contact->setTelephone($data['telephone'] ?? '');
                $contact->setIsFacturationDefault(true);
                $contact->setIsLivraisonDefault(true);
                
                $prospect->addContact($contact);
                $entityManager->persist($contact);
            }
            
            $entityManager->flush();
            
            // Préparer la réponse avec affichage intelligent
            if ($prospect->getNomEntreprise()) {
                // Entreprise : "Nom Entreprise (Contact : Prénom Nom)"
                $displayName = $prospect->getNomEntreprise();
                if (!empty($data['nom'])) {
                    $contactName = trim(($data['prenom'] ?? '') . ' ' . $data['nom']);
                    $displayName .= ' (Contact : ' . $contactName . ')';
                }
            } else {
                // Personne physique : "Prénom Nom"
                $displayName = trim($prospect->getPrenom() . ' ' . $prospect->getNom());
            }
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Client créé avec succès',
                'client' => [
                    'id' => $prospect->getId(),
                    'nom' => $prospect->getNom(),
                    'prenom' => $prospect->getPrenom(),
                    'nom_entreprise' => $prospect->getNomEntreprise(),
                    'email' => $prospect->getEmail(),
                    'telephone' => $prospect->getTelephone(),
                    'display_name' => $displayName,
                    'contacts' => array_map(function($contact) {
                        $label = trim(($contact->getCivilite() ?? '') . ' ' . ($contact->getPrenom() ?? '') . ' ' . ($contact->getNom() ?? ''));
                        return [
                            'id' => $contact->getId(),
                            'nom' => $contact->getNom(),
                            'prenom' => $contact->getPrenom(),
                            'fonction' => $contact->getFonction(),
                            'label' => $label ?: 'Contact sans nom'
                        ];
                    }, $prospect->getContacts()->toArray()),
                    'projects' => []  // Nouveau client, pas encore de projets
                ]
            ]);
            
        } catch (\Exception $e) {
            // Log l'erreur pour debug
            error_log('Erreur création client: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la création du client: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/ajax/create-contact', name: 'app_devis_api_contact_create', methods: ['POST'])]
    public function apiCreateContact(Request $request, EntityManagerInterface $entityManager, ClientRepository $clientRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation des données obligatoires
            if (!isset($data['nom']) || !isset($data['email']) || !isset($data['prospect_id'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le nom, l\'email et le prospect sont obligatoires'
                ], 400);
            }
            
            // Vérifier que le prospect existe
            $prospect = $clientRepository->find($data['prospect_id']);
            if (!$prospect) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Prospect introuvable'
                ], 400);
            }
            
            // Déterminer le type de contact (facturation par défaut)
            $contactType = $data['type'] ?? 'facturation';
            
            // Créer le contact approprié
            $contact = new Contact();
            $contact->setClient($prospect);
            
            // Remplir les données du contact
            $contact->setCivilite($data['civilite'] ?? 'M.');
            $contact->setNom($data['nom']);
            $contact->setPrenom($data['prenom'] ?? '');
            $contact->setFonction($data['fonction'] ?? '');
            $contact->setEmail($data['email']);
            $contact->setTelephone($data['telephone'] ?? '');
            
            if ($contactType === 'facturation' && isset($data['telephone_mobile'])) {
                $contact->setTelephoneMobile($data['telephone_mobile']);
            }
            
            // Définir les flags par défaut selon le type
            if ($contactType === 'livraison') {
                $contact->setIsFacturationDefault(false);
                $contact->setIsLivraisonDefault(true);
            } else {
                $contact->setIsFacturationDefault(true);
                $contact->setIsLivraisonDefault(false);
            }
            
            $prospect->addContact($contact);
            $entityManager->persist($contact);
            
            $entityManager->flush();
            
            // Préparer la réponse
            $contactName = trim($contact->getPrenom() . ' ' . $contact->getNom());
            $displayName = $contactName . ' (' . ($contact->getFonction() ?: 'Contact ' . $contactType) . ')';
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Contact créé avec succès',
                'contact' => [
                    'id' => $contact->getId(),
                    'nom' => $contact->getNom(),
                    'prenom' => $contact->getPrenom(),
                    'fonction' => $contact->getFonction() ?: 'Contact ' . $contactType,
                    'email' => $contact->getEmail(),
                    'telephone' => $contact->getTelephone(),
                    'display_name' => $displayName,
                    'type' => $contactType
                ]
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la création du contact: ' . $e->getMessage()
            ], 500);
        }
    }
}