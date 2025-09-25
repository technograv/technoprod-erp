<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\DevisItem;
use App\Entity\DevisVersion;
use App\Entity\LayoutElement;
use App\Form\DevisType;
use App\Repository\DevisRepository;
use App\Repository\ClientRepository;
use App\Repository\DevisVersionRepository;
use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Adresse;
use App\Entity\ModeReglement;
use App\Repository\ProduitRepository;
use App\Repository\TauxTVARepository;
use App\Repository\ModeReglementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Service\GmailMailerService;
use App\Service\DocumentNumerotationService;
use App\Service\DevisLoggerService;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/devis')]
#[IsGranted('ROLE_USER')]
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
    public function new(Request $request, EntityManagerInterface $entityManager, ClientRepository $clientRepository, ModeReglementRepository $modeReglementRepository, DocumentNumerotationService $numerotationService, DevisLoggerService $loggerService): Response
    {
        $devis = new Devis();
        
        // Auto-assignment du commercial à l'utilisateur connecté
        $user = $this->getUser();
        $devis->setCommercial($user);
        
        // Définir le statut par défaut comme "brouillon"
        $devis->setStatut('brouillon');
        
        // Définir l'acompte par défaut depuis la configuration société
        if ($user && $user->getSocietePrincipale()) {
            $societe = $user->getSocietePrincipale();
            $acompteDefaut = $societe->getAcompteDefautPercentAvecHeritage();
            if ($acompteDefaut > 0) {
                $devis->setAcomptePercent((string)$acompteDefaut);
            }
        }
        
        // Générer le prochain numéro de devis avec le nouveau système
        $nextDevisNumber = $numerotationService->previewProchainNumero('DE');
        
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
            $prospectId = $request->request->get('client');
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
            $emailEnvoiAutomatique = $request->request->get('email_envoi_automatique');

            // Récupération des adresses modifiées pour le projet
            $adresseProjetLigne1 = $request->request->get('adresse_projet_ligne1');
            $adresseProjetCodePostal = $request->request->get('adresse_projet_code_postal');
            $adresseProjetVille = $request->request->get('adresse_projet_ville');

            $adresseFacturationLigne1 = $request->request->get('adresse_facturation_ligne1');
            $adresseFacturationCodePostal = $request->request->get('adresse_facturation_code_postal');
            $adresseFacturationVille = $request->request->get('adresse_facturation_ville');

            // Validation basique
            if (!$prospectId) {
                $this->addFlash('error', 'Veuillez sélectionner un client/prospect.');
                return $this->redirectToRoute('app_devis_new');
            }

            // Trouver le prospect
            $prospect = $clientRepository->find($prospectId);
            if (!$prospect) {
                $this->addFlash('error', 'Client/prospect introuvable.');
                return $this->redirectToRoute('app_devis_new');
            }

            // Configurer le devis avec le numéro généré par le service
            $devis->setClient($prospect);
            $numeroGenere = $numerotationService->genererProchainNumero('DE', 'Devis');
            $devis->setNumeroDevis($numeroGenere);
            // Gestion des dates avec validation du format
            if ($dateCreation && !empty($dateCreation)) {
                try {
                    $devis->setDateCreation(new \DateTime($dateCreation));
                    // Debug: log de la date reçue
                    error_log("Date création reçue: " . $dateCreation . " - Date parsée: " . $devis->getDateCreation()->format('Y-m-d'));
                } catch (\Exception $e) {
                    error_log("Erreur parsing date création: " . $e->getMessage());
                    $devis->setDateCreation(new \DateTime('now'));
                }
            } else {
                error_log("Date création vide ou null - utilisation de 'now'");
                $devis->setDateCreation(new \DateTime('now'));
            }
            
            if ($dateValidite && !empty($dateValidite)) {
                try {
                    $devis->setDateValidite(new \DateTime($dateValidite));
                } catch (\Exception $e) {
                    $devis->setDateValidite(new \DateTime('+30 days'));
                }
            } else {
                $devis->setDateValidite(new \DateTime('+30 days'));
            }
            // Gestion du délai de livraison et de la date de livraison séparément
            $devis->setDelaiLivraison($delaiLivraison);
            
            // Gestion de la date de livraison dans son propre champ
            if ($dateLivraison && !empty($dateLivraison)) {
                try {
                    $devis->setDateLivraison(new \DateTime($dateLivraison));
                    error_log("Date livraison reçue: " . $dateLivraison . " - Date parsée: " . (new \DateTime($dateLivraison))->format('Y-m-d'));
                } catch (\Exception $e) {
                    error_log("Erreur parsing date livraison: " . $e->getMessage());
                }
            }
            
            // Définir le nom du projet
            if ($nomProjet) {
                $devis->setNomProjet($nomProjet);
            }
            
            $devis->setNotesClient($notePublique);
            $devis->setNotesInternes($notePrivee);

            // Définir l'email d'envoi automatique et le modèle de document
            if ($emailEnvoiAutomatique) {
                $devis->setEmailEnvoiAutomatique($emailEnvoiAutomatique);
            }
            if ($modeleDocument) {
                $devis->setModeleDocument($modeleDocument);
            }
            
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
            
            // Gestion des contacts sélectionnés
            if ($contactFacturation) {
                $contact = $entityManager->getRepository(Contact::class)->find($contactFacturation);
                if ($contact) {
                    $devis->setContactFacturation($contact);
                }
            }
            
            // Le contact en charge du projet devient le contact de livraison
            if ($contactDefaut) {
                $contact = $entityManager->getRepository(Contact::class)->find($contactDefaut);
                if ($contact) {
                    $devis->setContactLivraison($contact);
                    // Définir l'adresse de livraison depuis l'adresse du contact si elle existe
                    if ($contact->getAdresse()) {
                        $devis->setAdresseLivraison($contact->getAdresse());
                    }
                }
            }
            
            // Gestion des adresses modifiées pour le projet
            $notesAdresses = '';
            if ($adresseProjetLigne1 && $adresseProjetVille) {
                $notesAdresses .= "ADRESSE DE LIVRAISON MODIFIÉE POUR CE PROJET:\n";
                $notesAdresses .= $adresseProjetLigne1 . "\n";
                if ($adresseProjetCodePostal && $adresseProjetVille) {
                    $notesAdresses .= $adresseProjetCodePostal . ' ' . $adresseProjetVille . "\n";
                }
                $notesAdresses .= "\n";
            }
            
            if ($adresseFacturationLigne1 && $adresseFacturationVille) {
                $notesAdresses .= "ADRESSE DE FACTURATION MODIFIÉE POUR CE PROJET:\n";
                $notesAdresses .= $adresseFacturationLigne1 . "\n";
                if ($adresseFacturationCodePostal && $adresseFacturationVille) {
                    $notesAdresses .= $adresseFacturationCodePostal . ' ' . $adresseFacturationVille . "\n";
                }
            }
            
            if ($notesAdresses) {
                $notesInternes = $devis->getNotesInternes() ?: '';
                if ($notesInternes) {
                    $notesInternes .= "\n\n" . $notesAdresses;
                } else {
                    $notesInternes = $notesAdresses;
                }
                $devis->setNotesInternes($notesInternes);
            }

            // Sauvegarder le devis
            $entityManager->persist($devis);
            $entityManager->flush(); // Flush pour obtenir l'ID du devis
            
            // Log de la création du devis
            $loggerService->logCreated($devis);
            
            // Créer 3 lignes produits par défaut pour améliorer l'UX
            for ($i = 1; $i <= 3; $i++) {
                $element = new \App\Entity\DevisElement();
                $element->setDevis($devis);
                $element->setType('product');
                $element->setPosition($i);
                $element->setQuantite('1');
                $element->setPrixUnitaireHt('0.00');
                $element->setRemisePercent('0.00');
                
                // Utiliser le taux de TVA par défaut
                $defaultTva = $entityManager->getRepository(\App\Entity\TauxTVA::class)
                    ->findOneBy(['parDefaut' => true, 'actif' => true]);
                $tvaRate = $defaultTva ? (string) $defaultTva->getTaux() : '20.00';
                $element->setTvaPercent($tvaRate);
                
                $element->calculateTotal();
                $entityManager->persist($element);
            }
            
            $entityManager->flush();
            
            $this->addFlash('success', 'Devis créé avec succès ! 3 lignes produits ont été ajoutées par défaut.');
            return $this->redirectToRoute('app_devis_edit', ['id' => $devis->getId()]);
        }
        
        // Récupérer tous les prospects pour le sélecteur avec leurs contacts, adresses et formes juridiques
        $prospects = $entityManager->createQuery('
            SELECT c, contacts, adresse, fj, contactFacturationDefault, contactLivraisonDefault, modeReg
            FROM App\Entity\Client c
            LEFT JOIN c.contacts contacts
            LEFT JOIN contacts.adresse adresse
            LEFT JOIN c.formeJuridique fj
            LEFT JOIN c.contactFacturationDefault contactFacturationDefault
            LEFT JOIN c.contactLivraisonDefault contactLivraisonDefault
            LEFT JOIN c.modeReglement modeReg
            ORDER BY c.nomEntreprise ASC, contactFacturationDefault.nom ASC
        ')->getResult();

        // Récupérer les formes juridiques pour le modal de création client
        $formesJuridiques = $entityManager->getRepository(\App\Entity\FormeJuridique::class)
            ->findBy(['actif' => true], ['ordre' => 'ASC']);

        // Récupérer tous les modes de règlement pour le formulaire
        $modesReglement = $modeReglementRepository->findBy(['actif' => true], ['nom' => 'ASC']);

        return $this->render('devis/new.html.twig', [
            'devis' => $devis,
            'prospects' => $prospects,
            'next_devis_number' => $nextDevisNumber,
            'formes_juridiques' => $formesJuridiques,
            'modes_reglement' => $modesReglement,
        ]);
    }


    #[Route('/{id}', name: 'app_devis_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Devis $devis, DevisLoggerService $loggerService, EntityManagerInterface $entityManager): Response
    {
        $logs = $loggerService->getDevisLogs($devis);
        
        // Récupérer tous les clients pour la modal de duplication
        $clientsRepository = $entityManager->getRepository(Client::class);
        $clients = $clientsRepository->findAll();
        
        return $this->render('devis/show.html.twig', [
            'devis' => $devis,
            'logs' => $logs,
            'clients' => $clients,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_devis_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Devis $devis, EntityManagerInterface $entityManager, DevisLoggerService $loggerService): Response
    {
        // Rediriger les devis non-brouillon vers la page de consultation
        if ($devis->getStatut() !== 'brouillon') {
            $this->addFlash('warning', 'Ce devis a été envoyé et ne peut plus être modifié. Vous pouvez le consulter ci-dessous.');
            return $this->redirect($this->generateUrl('app_devis_show', ['id' => $devis->getId()]) . '#lignes');
        }

        // Créer une version si le devis a été envoyé et sera modifié
        $shouldCreateVersion = false;
        $originalDevisData = null;
        $originalStatut = $devis->getStatut(); // Capturer le statut original
        if (in_array($originalStatut, ['envoye', 'signe']) && $request->isMethod('POST')) {
            $shouldCreateVersion = true;
            // Sauvegarder l'état actuel AVANT les modifications pour l'archiver
            $originalDevisData = $this->captureDevisState($devis);
        }

        $form = $this->createForm(DevisType::class, $devis);
        
        // Debug: Log du client avant traitement du formulaire
        if ($request->isMethod('POST')) {
            error_log("=== DEBUG DEVIS EDIT POST ===");
            error_log("Devis ID: " . $devis->getId());
            error_log("Client actuel avant traitement: " . ($devis->getClient() ? $devis->getClient()->getId() . " - " . $devis->getClient()->getNomComplet() : "null"));
            
            $devisData = $request->request->all('devis');
            error_log("Client ID dans données POST: " . ($devisData['client'] ?? 'non défini'));
        }
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // S'assurer que chaque ligne a ses totaux calculés
            foreach ($devis->getDevisItems() as $item) {
                $item->calculateTotal();
            }
            
            // Recalculer les totaux globaux
            $devis->calculateTotals();
            
            // CORRECTION ORDRE GLOBAL : Réorganiser tous les éléments selon un ordre unifié
            $this->synchronizeGlobalOrder($devis, $entityManager);
            
            $entityManager->flush();
            
            // Log de la modification du devis
            $loggerService->logUpdated($devis);

            // Créer la version APRÈS les modifications avec l'état original
            if ($shouldCreateVersion && $originalDevisData) {
                $this->createDevisVersionFromState($devis, $entityManager, $originalDevisData, 'Modification après envoi');
            }

            // Vérifier si l'action est "save_and_send"
            $action = $request->request->get('action');
            if ($action === 'save_and_send') {
                // Changer le statut en "envoyé" et rediriger vers la page show avec modal d'envoi
                $devis->setStatut('envoye');
                $entityManager->flush();
                
                if ($shouldCreateVersion) {
                    $this->addFlash('success', 'Devis modifié et marqué comme envoyé avec succès ! Une version a été créée pour conserver l\'historique.');
                } else {
                    $this->addFlash('success', 'Devis modifié et marqué comme envoyé avec succès !');
                }
                
                // Rediriger vers la page show avec un paramètre pour ouvrir la modal d'envoi
                return $this->redirectToRoute('app_devis_show', [
                    'id' => $devis->getId(),
                    'open_send_modal' => '1'
                ], Response::HTTP_SEE_OTHER);
            }

            if ($shouldCreateVersion) {
                $this->addFlash('success', 'Devis modifié avec succès ! Une version a été créée pour conserver l\'historique.');
            } else {
                $this->addFlash('success', 'Devis modifié avec succès !');
            }
            
            return $this->redirectToRoute('app_devis_show', ['id' => $devis->getId()], Response::HTTP_SEE_OTHER);
        }

        // Debug: Si le formulaire n'est pas valide, loguer et afficher les erreurs
        if ($form->isSubmitted() && !$form->isValid()) {
            $errorMessages = [];
            foreach ($form->getErrors(true) as $error) {
                $errorMessage = 'Erreur formulaire devis: ' . $error->getMessage();
                error_log($errorMessage);
                $errorMessages[] = $error->getMessage();
            }
            
            if (!empty($errorMessages)) {
                $this->addFlash('error', 'Erreurs de validation : ' . implode(', ', $errorMessages));
            }
            
            // Log des détails supplémentaires
            error_log('Formulaire soumis mais invalide. Client actuel: ' . ($devis->getClient() ? $devis->getClient()->getId() . ' - ' . $devis->getClient()->getNomComplet() : 'null'));
        }

        return $this->render('devis/edit.html.twig', [
            'devis' => $devis,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Nouvelle interface d'édition simplifiée (VERSION 2)
     */
    #[Route('/{id}/edit-v2', name: 'app_devis_edit_v2', methods: ['GET'])]
    public function editV2(Devis $devis): Response
    {
        return $this->render('devis/edit_v2.html.twig', [
            'devis' => $devis,
        ]);
    }

    /**
     * Auto-sauvegarde AJAX du devis pendant l'édition
     */
    #[Route('/{id}/auto-save', name: 'app_devis_auto_save', methods: ['POST'])]
    public function autoSave(Request $request, Devis $devis, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier que le devis peut être modifié
        if (!in_array($devis->getStatut(), ['brouillon', 'envoye', 'signe'])) {
            return new JsonResponse(['success' => false, 'message' => 'Ce devis ne peut plus être modifié'], 400);
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
            }

            // Debug : Logger les données reçues en cas d'erreur ultérieure
            error_log('Auto-save data received: ' . json_encode($data, JSON_PRETTY_PRINT));

            // Mettre à jour les champs principaux du devis si fournis
            if (isset($data['client_id']) && is_numeric($data['client_id']) && $data['client_id'] > 0) {
                $client = $entityManager->getRepository(Client::class)->find((int)$data['client_id']);
                if ($client) {
                    $devis->setClient($client);
                }
            }

            if (isset($data['contact_facturation_id'])) {
                if (is_numeric($data['contact_facturation_id']) && $data['contact_facturation_id'] > 0) {
                    $contact = $entityManager->getRepository(Contact::class)->find((int)$data['contact_facturation_id']);
                    $devis->setContactFacturation($contact);
                } else {
                    $devis->setContactFacturation(null);
                }
            }

            if (isset($data['contact_livraison_id'])) {
                if (is_numeric($data['contact_livraison_id']) && $data['contact_livraison_id'] > 0) {
                    $contact = $entityManager->getRepository(Contact::class)->find((int)$data['contact_livraison_id']);
                    $devis->setContactLivraison($contact);
                } else {
                    $devis->setContactLivraison(null);
                }
            }

            if (isset($data['adresse_facturation_id'])) {
                if (is_numeric($data['adresse_facturation_id']) && $data['adresse_facturation_id'] > 0) {
                    $adresse = $entityManager->getRepository(Adresse::class)->find((int)$data['adresse_facturation_id']);
                    $devis->setAdresseFacturation($adresse);
                } else {
                    $devis->setAdresseFacturation(null);
                }
            }

            if (isset($data['adresse_livraison_id'])) {
                if (is_numeric($data['adresse_livraison_id']) && $data['adresse_livraison_id'] > 0) {
                    $adresse = $entityManager->getRepository(Adresse::class)->find((int)$data['adresse_livraison_id']);
                    $devis->setAdresseLivraison($adresse);
                } else {
                    $devis->setAdresseLivraison(null);
                }
            }

            // Mettre à jour les lignes de devis
            if (isset($data['items']) && is_array($data['items'])) {
                // Supprimer les anciennes lignes qui ne sont plus présentes
                $existingItems = $devis->getDevisItems()->toArray();
                $submittedItemIds = [];
                
                // Collecter uniquement les IDs valides (non null et numériques)
                foreach ($data['items'] as $itemData) {
                    if (isset($itemData['id']) && is_numeric($itemData['id']) && $itemData['id'] > 0) {
                        $submittedItemIds[] = (int)$itemData['id'];
                    }
                }
                
                foreach ($existingItems as $existingItem) {
                    if ($existingItem->getId() && !in_array($existingItem->getId(), $submittedItemIds)) {
                        $devis->removeDevisItem($existingItem);
                        $entityManager->remove($existingItem);
                    }
                }

                // Traiter chaque ligne
                foreach ($data['items'] as $itemData) {
                    $devisItem = null;
                    
                    if (isset($itemData['id']) && is_numeric($itemData['id']) && $itemData['id'] > 0) {
                        // Ligne existante - la modifier
                        $devisItem = $entityManager->getRepository(DevisItem::class)->find((int)$itemData['id']);
                    }
                    
                    if (!$devisItem) {
                        // Nouvelle ligne - la créer
                        $devisItem = new DevisItem();
                        $devis->addDevisItem($devisItem);
                    }

                    // Mettre à jour les champs de la ligne
                    if (isset($itemData['designation'])) {
                        $devisItem->setDesignation($itemData['designation']);
                    }
                    if (isset($itemData['description'])) {
                        $devisItem->setDescription($itemData['description']);
                    }
                    if (isset($itemData['quantite'])) {
                        $devisItem->setQuantite((string)$itemData['quantite']);
                    }
                    if (isset($itemData['prix_unitaire_ht'])) {
                        $devisItem->setPrixUnitaireHt((string)$itemData['prix_unitaire_ht']);
                    }
                    if (isset($itemData['remise_percent'])) {
                        $remisePercent = $itemData['remise_percent'];
                        if ($remisePercent === null || $remisePercent === '' || $remisePercent === 0) {
                            $devisItem->setRemisePercent(null);
                        } else {
                            $devisItem->setRemisePercent((string)$remisePercent);
                        }
                    }
                    if (isset($itemData['remise_montant'])) {
                        $remiseMontant = $itemData['remise_montant'];
                        if ($remiseMontant === null || $remiseMontant === '' || $remiseMontant === 0) {
                            $devisItem->setRemiseMontant(null);
                        } else {
                            $devisItem->setRemiseMontant((string)$remiseMontant);
                        }
                    }
                    if (isset($itemData['tva_percent'])) {
                        $devisItem->setTvaPercent((string)$itemData['tva_percent']);
                    }
                    if (isset($itemData['ordre_affichage'])) {
                        $devisItem->setOrdreAffichage((int)$itemData['ordre_affichage']);
                    }

                    // Calculer le total de la ligne
                    $devisItem->calculateTotal();
                }
            }

            // Recalculer les totaux généraux du devis
            $devis->calculateTotals();
            $devis->setUpdatedAt(new \DateTimeImmutable());

            // CORRECTION ORDRE GLOBAL : Synchroniser l'ordre de tous les éléments
            $this->synchronizeGlobalOrder($devis, $entityManager);

            // Sauvegarder en base
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Devis sauvegardé automatiquement',
                'saved_at' => (new \DateTime())->format('H:i:s'),
                'totals' => [
                    'total_ht' => $devis->getTotalHt(),
                    'total_tva' => $devis->getTotalTva(), 
                    'total_ttc' => $devis->getTotalTtc()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/delete', name: 'app_devis_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Devis $devis, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$devis->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($devis);
            $entityManager->flush();
            $this->addFlash('success', 'Devis supprimé avec succès !');
        }

        return $this->redirectToRoute('app_devis_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/pdf', name: 'app_devis_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
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
    public function envoyer(Request $request, Devis $devis, EntityManagerInterface $entityManager, GmailMailerService $gmailMailer, DevisLoggerService $loggerService): Response
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
            
            // Log de l'envoi du devis
            $loggerService->logSent($devis, $email);

            $this->addFlash('success', '📧 Devis envoyé avec succès à ' . $email . ' (vérifiez les logs pour les détails en mode développement)');
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

    #[Route('/api/taux-tva', name: 'app_devis_api_taux_tva', methods: ['GET'])]
    public function apiTauxTva(TauxTVARepository $tauxTVARepository): JsonResponse
    {
        $tauxTva = $tauxTVARepository->findBy(['actif' => true], ['ordre' => 'ASC']);
        
        $result = [];
        foreach ($tauxTva as $taux) {
            $result[] = [
                'id' => $taux->getId(),
                'nom' => $taux->getNom(),
                'taux' => $taux->getTaux(),
                'par_defaut' => $taux->isParDefaut()
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
            
            // Gestion de la forme juridique
            if (!empty($data['forme_juridique_id'])) {
                $formeJuridique = $entityManager->getRepository(\App\Entity\FormeJuridique::class)->find($data['forme_juridique_id']);
                if ($formeJuridique) {
                    $prospect->setFormeJuridique($formeJuridique);
                }
            }
            
            // Générer un code temporaire court (max 20 caractères)
            $tempCode = 'T' . substr(uniqid(), -7) . substr(time(), -7);
            $prospect->setCode($tempCode);
            
            $entityManager->persist($prospect);
            $entityManager->flush();
            
            // Maintenant générer le vrai code avec l'ID
            $prospect->setCode($prospect->generateCode());
            
            // Créer une adresse automatique si les données obligatoires sont fournies
            $adresseAutomatique = null;
            if (!empty($data['adresse']) && !empty($data['code_postal']) && !empty($data['ville'])) {
                $adresseAutomatique = new Adresse();
                $adresseAutomatique->setClient($prospect); // IMPORTANT: Assigner le client à l'adresse
                $adresseAutomatique->setNom('Automatique');
                $adresseAutomatique->setLigne1($data['adresse']);
                $adresseAutomatique->setCodePostal($data['code_postal']);
                $adresseAutomatique->setVille($data['ville']);
                $adresseAutomatique->setPays($data['pays'] ?? 'France');
                
                $entityManager->persist($adresseAutomatique);
            }
            
            // Créer automatiquement un contact par défaut pour tous les clients (avec nom et prénom)
            if (!empty($data['nom'])) {
                $contact = new Contact();
                $contact->setClient($prospect);
                $contact->setCivilite($data['civilite'] ?? 'M.');
                $contact->setNom($data['nom']); // Nom de famille de la personne
                $contact->setPrenom($data['prenom'] ?? '');
                
                // Fonction selon le type de client
                if (!empty($data['nom_entreprise'])) {
                    $contact->setFonction('Contact principal');
                } else {
                    $contact->setFonction(''); // Pas de fonction pour les particuliers
                }
                
                $contact->setEmail($data['email']);
                $contact->setTelephone($data['telephone'] ?? '');
                $contact->setIsFacturationDefault(true);
                $contact->setIsLivraisonDefault(true);
                
                // Assigner l'adresse automatique au contact
                if ($adresseAutomatique) {
                    $contact->setAdresse($adresseAutomatique);
                }
                
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
                        $adresse = null;
                        if ($contact->getAdresse()) {
                            $adresse = [
                                'id' => $contact->getAdresse()->getId(),
                                'ligne1' => $contact->getAdresse()->getLigne1(),
                                'ligne2' => $contact->getAdresse()->getLigne2(),
                                'codePostal' => $contact->getAdresse()->getCodePostal(),
                                'ville' => $contact->getAdresse()->getVille(),
                                'pays' => $contact->getAdresse()->getPays() ?? 'France'
                            ];
                        }
                        return [
                            'id' => $contact->getId(),
                            'nom' => $contact->getNom(),
                            'prenom' => $contact->getPrenom(),
                            'fonction' => $contact->getFonction(),
                            'label' => $label ?: 'Contact sans nom',
                            'is_facturation_default' => $contact->isFacturationDefault(),
                            'is_livraison_default' => $contact->isLivraisonDefault(),
                            'adresse' => $adresse
                        ];
                    }, $prospect->getContacts()->toArray()),
                    'projects' => [],  // Nouveau client, pas encore de projets
                    'default_facturation_id' => $prospect->getContactFacturationDefault() ? $prospect->getContactFacturationDefault()->getId() : null,
                    'default_livraison_id' => $prospect->getContactLivraisonDefault() ? $prospect->getContactLivraisonDefault()->getId() : null
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
            $displayName = $contactName;
            // Ajouter la fonction seulement si elle existe et n'est pas générique
            if ($contact->getFonction() && !in_array($contact->getFonction(), ['Contact', 'Contact facturation', 'Contact livraison'])) {
                $displayName .= ' - ' . $contact->getFonction();
            }
            
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

    /**
     * Crée une version du devis pour conserver l'historique (état précédent avant modifications)
     */
    private function createDevisVersion(Devis $devis, EntityManagerInterface $entityManager, string $reason = null): DevisVersion
    {
        $version = new DevisVersion();
        $version->setDevis($devis);
        $version->setVersionNumber($devis->getNextVersionNumber());
        $version->setModifiedBy($this->getUser());
        $version->setModificationReason($reason);
        $version->setTotalTtcAtTime($devis->getTotalTtc());
        $version->setStatutAtTime($devis->getStatut());

        // Créer un snapshot complet du devis et de ses items
        $snapshot = [
            'devis' => [
                'id' => $devis->getId(),
                'numeroDevis' => $devis->getNumeroDevis(),
                'dateCreation' => $devis->getDateCreation()->format('Y-m-d'),
                'dateValidite' => $devis->getDateValidite()->format('Y-m-d'),
                'statut' => $devis->getStatut(),
                'totalHt' => $devis->getTotalHt(),
                'totalTva' => $devis->getTotalTva(),
                'totalTtc' => $devis->getTotalTtc(),
                'notesClient' => $devis->getNotesClient(),
                'notesInternes' => $devis->getNotesInternes(),
                'delaiLivraison' => $devis->getDelaiLivraison(),
                'acomptePercent' => $devis->getAcomptePercent(),
                'acompteMontant' => $devis->getAcompteMontant(),
                'remiseGlobalePercent' => $devis->getRemiseGlobalePercent(),
                'remiseGlobaleMontant' => $devis->getRemiseGlobaleMontant(),
                // Informations tiers
                'tiersCivilite' => $devis->getTiersCivilite(),
                'tiersNom' => $devis->getTiersNom(),
                'tiersPrenom' => $devis->getTiersPrenom(),
                'tiersAdresse' => $devis->getTiersAdresse(),
                'tiersCodePostal' => $devis->getTiersCodePostal(),
                'tiersVille' => $devis->getTiersVille(),
                'tiersModeReglement' => $devis->getTiersModeReglement(),
                // Informations client
                'client' => $devis->getClient() ? [
                    'id' => $devis->getClient()->getId(),
                    'nom' => $devis->getClient()->getNom(),
                    'prenom' => $devis->getClient()->getPrenom(),
                    'nomEntreprise' => $devis->getClient()->getNomEntreprise()
                ] : null,
                'contactFacturation' => $devis->getContactFacturation() ? [
                    'id' => $devis->getContactFacturation()->getId(),
                    'nom' => $devis->getContactFacturation()->getNom(),
                    'prenom' => $devis->getContactFacturation()->getPrenom(),
                    'email' => $devis->getContactFacturation()->getEmail()
                ] : null,
                'contactLivraison' => $devis->getContactLivraison() ? [
                    'id' => $devis->getContactLivraison()->getId(),
                    'nom' => $devis->getContactLivraison()->getNom(),
                    'prenom' => $devis->getContactLivraison()->getPrenom(),
                    'email' => $devis->getContactLivraison()->getEmail()
                ] : null
            ],
            'items' => []
        ];

        // Ajouter les items du devis
        foreach ($devis->getDevisItems() as $item) {
            $snapshot['items'][] = [
                'id' => $item->getId(),
                'designation' => $item->getDesignation(),
                'description' => $item->getDescription(),
                'quantite' => $item->getQuantite(),
                'prixUnitaireHt' => $item->getPrixUnitaireHt(),
                'totalLigneHt' => $item->getTotalLigneHt(),
                'tvaPercent' => $item->getTvaPercent(),
                'ordreAffichage' => $item->getOrdreAffichage(),
                'produit' => $item->getProduit() ? [
                    'id' => $item->getProduit()->getId(),
                    'reference' => $item->getProduit()->getReference(),
                    'designation' => $item->getProduit()->getDesignation()
                ] : null
            ];
        }

        $version->setSnapshotData($snapshot);

        $entityManager->persist($version);
        $entityManager->flush();

        return $version;
    }

    /**
     * Capture l'état actuel du devis avant modifications
     */
    private function captureDevisState(Devis $devis): array
    {
        $devisData = [
            'statut' => $devis->getStatut(),
            'totalHt' => $devis->getTotalHt(),
            'totalTva' => $devis->getTotalTva(),
            'totalTtc' => $devis->getTotalTtc(),
            'dateValidite' => $devis->getDateValidite() ? $devis->getDateValidite()->format('Y-m-d') : null,
            'notesClient' => $devis->getNotesClient(),
            'notesInternes' => $devis->getNotesInternes(),
            'delaiLivraison' => $devis->getDelaiLivraison(),
            'acomptePercent' => $devis->getAcomptePercent(),
            'acompteMontant' => $devis->getAcompteMontant(),
            'remiseGlobalePercent' => $devis->getRemiseGlobalePercent(),
            'remiseGlobaleMontant' => $devis->getRemiseGlobaleMontant(),
            'nomProjet' => $devis->getNomProjet(),
            // Client information for template compatibility
            'client' => $devis->getClient() ? [
                'id' => $devis->getClient()->getId(),
                'nom' => $devis->getClient()->getNom(),
                'prenom' => $devis->getClient()->getPrenom(),
                'nomEntreprise' => $devis->getClient()->getNomEntreprise()
            ] : null,
            'contactFacturation' => $devis->getContactFacturation() ? [
                'id' => $devis->getContactFacturation()->getId(),
                'nom' => $devis->getContactFacturation()->getNom(),
                'prenom' => $devis->getContactFacturation()->getPrenom(),
                'email' => $devis->getContactFacturation()->getEmail()
            ] : null,
            'contactLivraison' => $devis->getContactLivraison() ? [
                'id' => $devis->getContactLivraison()->getId(),
                'nom' => $devis->getContactLivraison()->getNom(),
                'prenom' => $devis->getContactLivraison()->getPrenom(),
                'email' => $devis->getContactLivraison()->getEmail()
            ] : null,
        ];

        $elements = [];
        foreach ($devis->getElements() as $element) {
            $elements[] = [
                'type' => $element->getType(),
                'position' => $element->getPosition(),
                'designation' => $element->getDesignation(),
                'description' => $element->getDescription(),
                'quantite' => $element->getQuantite(),
                'prix_unitaire_ht' => $element->getPrixUnitaireHt(),
                'remise_percent' => $element->getRemisePercent(),
                'tva_percent' => $element->getTvaPercent(),
                'total_ligne_ht' => $element->getTotalLigneHt(),
                'titre' => $element->getTitre(),
                'imageVisible' => $element->getImageVisible(),
                'produit_id' => $element->getProduit() ? $element->getProduit()->getId() : null,
                'produit' => $element->getProduit() ? [
                    'id' => $element->getProduit()->getId(),
                    'reference' => $element->getProduit()->getReference(),
                    'designation' => $element->getProduit()->getDesignation()
                ] : null,
            ];
        }

        // Capturer aussi les DevisItems (ancien système)
        $items = [];
        foreach ($devis->getDevisItems() as $item) {
            $items[] = [
                'is_product' => true,
                'designation' => $item->getDesignation(),
                'description' => $item->getDescription(),
                'quantite' => $item->getQuantite(),
                'prix_unitaire_ht' => $item->getPrixUnitaireHt(),
                'remise_percent' => $item->getRemisePercent(),
                'tva_percent' => $item->getTvaPercent(),
                'total_ligne_ht' => $item->getTotalLigneHt(),
                'ordre_affichage' => $item->getOrdreAffichage(),
                'produit_id' => $item->getProduit() ? $item->getProduit()->getId() : null,
            ];
        }

        return [
            'devis_data' => $devisData,
            'elements' => $elements,
            'items' => $items  // Pour compatibilité avec l'ancien système
        ];
    }

    /**
     * Crée une version à partir d'un état sauvegardé (pour archiver l'ancienne version)
     */
    private function createDevisVersionFromState(Devis $devis, EntityManagerInterface $entityManager, array $stateData, string $reason = null): DevisVersion
    {
        $version = new DevisVersion();
        $version->setDevis($devis);
        
        $versionNumber = $devis->getNextVersionNumber();
        $version->setVersionNumber($versionNumber);
        $version->setModifiedBy($this->getUser());
        $version->setModificationReason($reason);
        $version->setTotalTtcAtTime($stateData['devis_data']['totalTtc'] ?? '0.00');
        $version->setStatutAtTime($stateData['devis_data']['statut'] ?? 'brouillon');

        // Définir automatiquement le label pour la première version
        // Vérifier s'il s'agit vraiment de la première version créée pour ce devis
        $existingVersionsCount = $entityManager->getRepository(\App\Entity\DevisVersion::class)
            ->count(['devis' => $devis]);
        
        if ($existingVersionsCount === 0) {
            // C'est vraiment la première version de ce devis
            $version->setVersionLabel('Devis initial');
        }

        // Le snapshot contient l'état AVANT les modifications (= ancienne version)
        $version->setSnapshotData($stateData);

        $entityManager->persist($version);
        $entityManager->flush();

        return $version;
    }

    /**
     * Redirige vers l'onglet historique de la page principale du devis
     */
    #[Route('/{id}/versions', name: 'app_devis_versions', methods: ['GET'])]
    public function versions(Devis $devis): Response
    {
        $url = $this->generateUrl('app_devis_show', ['id' => $devis->getId()]) . '#versions';
        return $this->redirect($url, 301);
    }

    /**
     * Affiche une version spécifique d'un devis
     */
    #[Route('/{id}/versions/{versionNumber}', name: 'app_devis_version_show', methods: ['GET'], requirements: ['versionNumber' => '\d+'])]
    public function showVersion(Devis $devis, string $versionNumber, DevisVersionRepository $versionRepository): Response
    {
        // Convertir en entier de manière sûre
        $versionNumberInt = (int) $versionNumber;
        
        $version = $versionRepository->findVersionByNumber($devis, $versionNumberInt);
        
        if (!$version) {
            throw $this->createNotFoundException('Version not found');
        }

        // Déterminer si c'est la version la plus récente (la dernière créée)
        $latestVersion = $versionRepository->findLatestVersion($devis);
        $isLatestVersion = ($latestVersion && $latestVersion->getId() === $version->getId());

        return $this->render('devis/version_show.html.twig', [
            'devis' => $devis,
            'version' => $version,
            'isLatestVersion' => $isLatestVersion,
        ]);
    }

    /**
     * API pour récupérer les versions d'un devis
     */
    #[Route('/{id}/api/versions', name: 'app_devis_api_versions', methods: ['GET'])]
    public function apiVersions(Devis $devis, DevisVersionRepository $versionRepository): JsonResponse
    {
        $versions = $versionRepository->findVersionsByDevis($devis);
        
        $result = [];
        foreach ($versions as $version) {
            $devisData = $version->getDevisData();
            
            $result[] = [
                'id' => $version->getId(),
                'versionNumber' => $version->getVersionNumber(),
                'versionLabel' => $version->generateAutoLabel(),
                'createdAt' => $version->getCreatedAt()->format('d/m/Y à H:i'),
                'modifiedBy' => $version->getModifiedBy() ? 
                    $version->getModifiedBy()->getPrenom() . ' ' . $version->getModifiedBy()->getNom() : 'Inconnu',
                'modificationReason' => $version->getModificationReason(),
                'totalTtcAtTime' => $version->getTotalTtcAtTime(),
                'totalHtAtTime' => $devisData['totalHt'] ?? '0.00',
                'totalTvaAtTime' => $devisData['totalTva'] ?? '0.00',
                'statutAtTime' => $version->getStatutAtTime(),
                'isInitialVersion' => $version->isInitialVersion()
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Crée manuellement une version avec un label personnalisé
     */
    #[Route('/{id}/create-version', name: 'app_devis_create_version', methods: ['POST'])]
    public function createVersion(Request $request, Devis $devis, EntityManagerInterface $entityManager, DevisLoggerService $loggerService): Response
    {
        if (!$devis->canCreateVersion()) {
            $this->addFlash('error', 'Impossible de créer une version pour ce devis.');
            return $this->redirectToRoute('app_devis_show', ['id' => $devis->getId()]);
        }

        $reason = $request->request->get('reason', 'Version créée manuellement');
        $label = $request->request->get('label');

        // Capturer l'état actuel avant de permettre les modifications
        $currentState = $this->captureDevisState($devis);
        
        // Créer une version qui archive l'ANCIEN ÉTAT
        $version = $this->createDevisVersionFromState($devis, $entityManager, $currentState, $reason);
        
        // Déterminer le label de la version (devrait utiliser l'ancien nom du projet)
        if ($label && $label !== $devis->getNomProjet()) {
            // Si l'utilisateur a fourni un nouveau nom, la version garde l'ancien nom du projet
            $version->setVersionLabel($devis->getNomProjet() ?: 'Version ' . $version->getVersionNumber());
            // Et on met à jour le devis avec le nouveau nom
            $devis->setNomProjet($label);
        } elseif ($version->getVersionNumber() === 1) {
            // La première version est toujours le "Devis initial"
            $version->setVersionLabel('Devis initial');
        } else {
            // Utiliser l'ancien nom du projet pour cette version
            $version->setVersionLabel($devis->getNomProjet() ?: 'Version ' . $version->getVersionNumber());
        }
        
        // Changer le statut du devis pour permettre l'édition (le contenu reste le même)
        $devis->setStatut('brouillon');
        $entityManager->flush();
        
        // Log de la création de version
        $loggerService->logVersionCreated($devis, $version->getVersionNumber(), $version->getVersionLabel());

        $this->addFlash('success', 'Version créée avec succès. Vous pouvez maintenant modifier le devis.');
        
        // Rediriger vers l'édition si c'était une création automatique pour modification
        if ($request->headers->get('Accept') === 'application/json' || $request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'message' => 'Version créée avec succès']);
        }
        
        // Rediriger vers l'onglet historique de la page principale
        $url = $this->generateUrl('app_devis_show', ['id' => $devis->getId()]) . '#versions';
        return $this->redirect($url);
    }

    /**
     * Ajouter un élément de mise en page au devis
     */
    #[Route('/{id}/layout-element', name: 'app_devis_add_layout_element', methods: ['POST'])]
    public function addLayoutElement(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $devis = $entityManager->getRepository(Devis::class)->find($id);
        
        if (!$devis) {
            return $this->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['type'], $data['position'])) {
            return $this->json(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        // Valider le type d'élément
        $allowedTypes = ['line_break', 'page_break', 'subtotal', 'section_title', 'separator'];
        if (!in_array($data['type'], $allowedTypes)) {
            return $this->json(['success' => false, 'message' => 'Type d\'élément invalide'], 400);
        }

        try {
            // Réorganiser les ordres des éléments existants pour faire de la place
            $layoutRepo = $entityManager->getRepository(LayoutElement::class);
            $layoutRepo->reorganizeOrderAfterInsert($devis, $data['position']);

            // Créer le nouvel élément de mise en page
            $layoutElement = new LayoutElement();
            $layoutElement->setDevis($devis);
            $layoutElement->setType($data['type']);
            $layoutElement->setOrdreAffichage($data['position']);
            
            // Paramètres optionnels
            if (isset($data['titre'])) {
                $layoutElement->setTitre($data['titre']);
            }
            if (isset($data['contenu'])) {
                $layoutElement->setContenu($data['contenu']);
            }
            if (isset($data['parametres']) && is_array($data['parametres'])) {
                $layoutElement->setParametres($data['parametres']);
            }

            $entityManager->persist($layoutElement);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Élément de mise en page ajouté avec succès',
                'element' => $layoutElement->toArray()
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout de l\'élément: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un élément de mise en page
     */
    #[Route('/{id}/layout-element/{elementId}', name: 'app_devis_remove_layout_element', methods: ['DELETE'])]
    public function removeLayoutElement(
        int $id,
        int $elementId,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $devis = $entityManager->getRepository(Devis::class)->find($id);
        $layoutElement = $entityManager->getRepository(LayoutElement::class)->find($elementId);
        
        if (!$devis || !$layoutElement || $layoutElement->getDevis() !== $devis) {
            return $this->json(['success' => false, 'message' => 'Élément introuvable'], 404);
        }

        try {
            $deletedPosition = $layoutElement->getOrdreAffichage();
            
            $entityManager->remove($layoutElement);
            $entityManager->flush();

            // Réorganiser les ordres des éléments restants
            $layoutRepo = $entityManager->getRepository(LayoutElement::class);
            $layoutRepo->reorganizeOrderAfterDelete($devis, $deletedPosition);

            return $this->json([
                'success' => true,
                'message' => 'Élément de mise en page supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour l'ordre des éléments de mise en page
     */
    #[Route('/{id}/layout-elements/reorder', name: 'app_devis_reorder_layout_elements', methods: ['POST'])]
    public function reorderLayoutElements(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $devis = $entityManager->getRepository(Devis::class)->find($id);
        
        if (!$devis) {
            return $this->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['orders']) || !is_array($data['orders'])) {
            return $this->json(['success' => false, 'message' => 'Ordres manquants'], 400);
        }

        try {
            $layoutRepo = $entityManager->getRepository(LayoutElement::class);
            $updated = $layoutRepo->updateOrdersFromArray($devis, $data['orders']);

            return $this->json([
                'success' => true,
                'message' => "Ordre des éléments mis à jour ($updated éléments modifiés)"
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la réorganisation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les éléments de mise en page d'un devis
     */
    #[Route('/{id}/layout-elements', name: 'app_devis_layout_elements', methods: ['GET'])]
    public function getLayoutElements(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $devis = $entityManager->getRepository(Devis::class)->find($id);
        
        if (!$devis) {
            return $this->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        }

        $layoutElements = $entityManager->getRepository(LayoutElement::class)->findByDevisOrdered($devis);
        
        $elements = [];
        foreach ($layoutElements as $element) {
            $elements[] = $element->toArray();
        }

        return $this->json([
            'success' => true,
            'elements' => $elements,
            'count' => count($elements)
        ]);
    }

    /**
     * Synchronise l'ordre global de tous les éléments d'un devis (DevisItems + LayoutElements)
     * pour résoudre les problèmes de réordonnancement après sauvegarde
     */
    private function synchronizeGlobalOrder(Devis $devis, EntityManagerInterface $entityManager): void
    {
        // Récupérer tous les DevisItems et LayoutElements du devis
        $devisItems = $devis->getDevisItems()->toArray();
        $layoutElements = $entityManager->getRepository(LayoutElement::class)->findByDevisOrdered($devis);
        
        // Créer un tableau unifié avec tous les éléments et leurs ordres actuels
        $allElements = [];
        
        foreach ($devisItems as $item) {
            $allElements[] = [
                'type' => 'devis_item',
                'entity' => $item,
                'order' => $item->getOrdreAffichage() ?? 999999
            ];
        }
        
        foreach ($layoutElements as $element) {
            $allElements[] = [
                'type' => 'layout_element',
                'entity' => $element,
                'order' => $element->getOrdreAffichage() ?? 999999
            ];
        }
        
        // Trier tous les éléments par ordre d'affichage
        usort($allElements, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        // Réassigner les ordres de manière séquentielle (1, 2, 3, ...)
        foreach ($allElements as $index => $elementData) {
            $newOrder = $index + 1;
            
            if ($elementData['type'] === 'devis_item') {
                $elementData['entity']->setOrdreAffichage($newOrder);
                error_log("DevisController: DevisItem ID " . $elementData['entity']->getId() . " → ordre " . $newOrder);
            } else {
                $elementData['entity']->setOrdreAffichage($newOrder);
                error_log("DevisController: LayoutElement ID " . $elementData['entity']->getId() . " → ordre " . $newOrder);
            }
        }
        
        error_log("DevisController: Synchronisation ordre global terminée - " . count($allElements) . " éléments réorganisés");
    }

    #[Route('/{id}/update-totals', name: 'app_devis_update_totals', methods: ['POST'])]
    public function updateTotals(Request $request, Devis $devis, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['totalHt'], $data['totalTva'], $data['totalTtc'])) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }
        
        // Mettre à jour les totaux
        $devis->setTotalHt($data['totalHt']);
        $devis->setTotalTva($data['totalTva']);
        $devis->setTotalTtc($data['totalTtc']);
        
        // Mettre à jour les remises globales si présentes
        if (isset($data['remiseGlobalePercent'])) {
            $devis->setRemiseGlobalePercent($data['remiseGlobalePercent'] > 0 ? (string)$data['remiseGlobalePercent'] : null);
        }
        
        if (isset($data['remiseGlobaleMontant'])) {
            $devis->setRemiseGlobaleMontant($data['remiseGlobaleMontant'] > 0 ? (string)$data['remiseGlobaleMontant'] : null);
        }
        
        // Mettre à jour les acomptes si présents
        if (isset($data['acomptePercent'])) {
            $devis->setAcomptePercent($data['acomptePercent'] > 0 ? (string)$data['acomptePercent'] : null);
        }
        
        if (isset($data['acompteMontant'])) {
            $devis->setAcompteMontant($data['acompteMontant'] > 0 ? (string)$data['acompteMontant'] : null);
        }
        
        $entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Totaux, remise et acompte mis à jour automatiquement'
        ]);
    }

    #[Route('/version/{id}/duplicate', name: 'app_devis_version_duplicate', methods: ['POST'])]
    public function duplicateFromVersion(
        int $id,
        Request $request,
        DevisVersionRepository $versionRepository,
        ClientRepository $clientRepository,
        EntityManagerInterface $entityManager,
        DocumentNumerotationService $numerotationService
    ): Response {
        $version = $versionRepository->find($id);
        
        if (!$version) {
            $this->addFlash('error', 'Version introuvable');
            return $this->redirectToRoute('app_devis_index');
        }
        
        $originalDevis = $version->getDevis();
        
        // Créer le nouveau devis
        $newDevis = new Devis();
        
        // Déterminer le client
        $duplicationType = $request->request->get('duplicationType', 'same');
        if ($duplicationType === 'different') {
            $newClientId = $request->request->get('newClientId');
            if ($newClientId) {
                $newClient = $clientRepository->find($newClientId);
                if ($newClient) {
                    $newDevis->setClient($newClient);
                } else {
                    $this->addFlash('error', 'Client sélectionné introuvable');
                    return $this->redirectToRoute('app_devis_show', ['id' => $originalDevis->getId()]);
                }
            } else {
                $this->addFlash('error', 'Veuillez sélectionner un client');
                return $this->redirectToRoute('app_devis_show', ['id' => $originalDevis->getId()]);
            }
        } else {
            // Même client
            $newDevis->setClient($originalDevis->getClient());
        }
        
        // Assigner les contacts par défaut du client sélectionné
        $client = $newDevis->getClient();
        if ($client) {
            // Contact de livraison par défaut
            foreach ($client->getContacts() as $contact) {
                if ($contact->isLivraisonDefault()) {
                    $newDevis->setContactLivraison($contact);
                    break;
                }
            }
            
            // Contact de facturation par défaut
            foreach ($client->getContacts() as $contact) {
                if ($contact->isFacturationDefault()) {
                    $newDevis->setContactFacturation($contact);
                    break;
                }
            }
            
            // Email d'envoi automatique = email du contact de livraison ou facturation
            if ($newDevis->getContactLivraison() && $newDevis->getContactLivraison()->getEmail()) {
                $newDevis->setEmailEnvoiAutomatique($newDevis->getContactLivraison()->getEmail());
            } elseif ($newDevis->getContactFacturation() && $newDevis->getContactFacturation()->getEmail()) {
                $newDevis->setEmailEnvoiAutomatique($newDevis->getContactFacturation()->getEmail());
            }
        }
        
        // Paramètres du nouveau devis
        $newDevis->setStatut('brouillon');
        $newDevis->setDateCreation(new \DateTime());
        $newDevis->setDateValidite((new \DateTime())->modify('+1 month'));
        $newDevis->setCommercial($this->getUser());
        
        // Générer un nouveau numéro de devis
        $numeroDevis = $numerotationService->genererProchainNumero('DE');
        $newDevis->setNumeroDevis($numeroDevis);
        
        // Récupérer les données du snapshot de la version
        $snapshotData = $version->getSnapshotData();
        $devisData = $snapshotData['devis_data'] ?? $snapshotData['devis'] ?? [];
        
        
        // Copier les conditions commerciales depuis le snapshot
        if (isset($devisData['remiseGlobalePercent'])) {
            $newDevis->setRemiseGlobalePercent($devisData['remiseGlobalePercent']);
        }
        if (isset($devisData['remiseGlobaleMontant'])) {
            $newDevis->setRemiseGlobaleMontant($devisData['remiseGlobaleMontant']);
        }
        if (isset($devisData['acomptePercent'])) {
            $newDevis->setAcomptePercent($devisData['acomptePercent']);
        }
        if (isset($devisData['acompteMontant'])) {
            $newDevis->setAcompteMontant($devisData['acompteMontant']);
        }
        
        // Copier les autres champs utiles
        if (isset($devisData['delaiLivraison'])) {
            $newDevis->setDelaiLivraison($devisData['delaiLivraison']);
        }
        if (isset($devisData['modeleDocument'])) {
            $newDevis->setModeleDocument($devisData['modeleDocument']);
        }
        
        // Notes avec indication de duplication
        $notes = $request->request->get('notes', '');
        if ($notes) {
            $newDevis->setNotesInternes($notes);
        } else {
            $newDevis->setNotesInternes('Dupliqué depuis ' . $originalDevis->getNumeroDevis() . ' - ' . $version->generateAutoLabel());
        }
        
        // Persister le devis avant d'ajouter les éléments
        $entityManager->persist($newDevis);
        $entityManager->flush();
        
        // Copier les éléments depuis le snapshot
        $elements = $snapshotData['elements'] ?? $snapshotData['items'] ?? [];
        
        // Si on a des éléments dans le nouveau format (DevisElement)
        if (method_exists($newDevis, 'addElement')) {
            foreach ($elements as $elementData) {
                $element = new \App\Entity\DevisElement();
                $element->setDevis($newDevis);
                $element->setType($elementData['type'] ?? 'product');
                $element->setPosition($elementData['position'] ?? 0);
                
                if ($element->getType() === 'product') {
                    $element->setDesignation($elementData['designation'] ?? '');
                    $element->setDescription($elementData['description'] ?? '');
                    $element->setQuantite($elementData['quantite'] ?? 1);
                    
                    // Support des différents formats de noms de clés
                    $element->setPrixUnitaireHt(
                        $elementData['prix_unitaire_ht'] ?? 
                        $elementData['prixUnitaireHt'] ?? '0'
                    );
                    $element->setRemisePercent(
                        $elementData['remise_percent'] ?? 
                        $elementData['remisePercent'] ?? '0'
                    );
                    $element->setTvaPercent(
                        $elementData['tva_percent'] ?? 
                        $elementData['tvaPercent'] ?? '20'
                    );
                    $element->setTotalLigneHt(
                        $elementData['total_ligne_ht'] ?? 
                        $elementData['totalLigneHt'] ?? '0'
                    );
                    
                    // Associer le produit si référencé (support des différents formats)
                    $produitId = $elementData['produit_id'] ?? $elementData['produitId'] ?? null;
                    if ($produitId) {
                        $produit = $entityManager->getRepository(\App\Entity\Produit::class)->find($produitId);
                        if ($produit) {
                            $element->setProduit($produit);
                            // S'assurer que la désignation et le prix correspondent au produit
                            if (empty($elementData['designation'])) {
                                $element->setDesignation($produit->getNom());
                            }
                            if (empty($elementData['prix_unitaire_ht']) || $elementData['prix_unitaire_ht'] === '0') {
                                $element->setPrixUnitaireHt($produit->getPrixVenteHt());
                            }
                            
                            // Le code produit sera automatiquement disponible via la relation
                            // $element->getProduit()->getReference()
                        }
                    }
                    
                    // Recalculer le total de la ligne
                    $quantite = floatval($element->getQuantite());
                    $prixUnitaire = floatval($element->getPrixUnitaireHt());
                    $remisePercent = floatval($element->getRemisePercent());
                    
                    $totalLigne = $quantite * $prixUnitaire;
                    if ($remisePercent > 0) {
                        $totalLigne = $totalLigne * (1 - $remisePercent / 100);
                    }
                    
                    $element->setTotalLigneHt(number_format($totalLigne, 2, '.', ''));
                } else {
                    // Élément de mise en page
                    if (isset($elementData['titre'])) {
                        $element->setTitre($elementData['titre']);
                    }
                    if (isset($elementData['contenu'])) {
                        $element->setContenu($elementData['contenu']);
                    }
                    if (isset($elementData['icon'])) {
                        $element->setIcon($elementData['icon']);
                    }
                }
                
                $entityManager->persist($element);
                $newDevis->addElement($element);
            }
        } else {
            // Ancien format avec DevisItem et LayoutElement
            // Copier les DevisItem
            foreach ($elements as $itemData) {
                if (isset($itemData['is_product']) && $itemData['is_product']) {
                    $item = new DevisItem();
                    $item->setDevis($newDevis);
                    $item->setDesignation($itemData['designation'] ?? '');
                    $item->setDescription($itemData['description'] ?? '');
                    $item->setQuantite($itemData['quantite'] ?? 1);
                    
                    // Support des différents formats de noms de clés
                    $item->setPrixUnitaireHt(
                        $itemData['prix_unitaire_ht'] ?? 
                        $itemData['prixUnitaireHt'] ?? '0'
                    );
                    $item->setRemisePercent(
                        $itemData['remise_percent'] ?? 
                        $itemData['remisePercent'] ?? '0'
                    );
                    $item->setTvaPercent(
                        $itemData['tva_percent'] ?? 
                        $itemData['tvaPercent'] ?? '20'
                    );
                    $item->setTotalLigneHt(
                        $itemData['total_ligne_ht'] ?? 
                        $itemData['totalLigneHt'] ?? '0'
                    );
                    $item->setOrdreAffichage(
                        $itemData['ordre_affichage'] ?? 
                        $itemData['ordreAffichage'] ?? 0
                    );
                    
                    // Associer le produit si référencé (support des différents formats)
                    $produitId = $itemData['produit_id'] ?? $itemData['produitId'] ?? null;
                    if ($produitId) {
                        $produit = $entityManager->getRepository(\App\Entity\Produit::class)->find($produitId);
                        if ($produit) {
                            $item->setProduit($produit);
                            // S'assurer que la désignation et le prix correspondent au produit
                            if (empty($itemData['designation'])) {
                                $item->setDesignation($produit->getNom());
                            }
                            if (empty($itemData['prix_unitaire_ht']) || $itemData['prix_unitaire_ht'] === '0') {
                                $item->setPrixUnitaireHt($produit->getPrixVenteHt());
                            }
                        }
                    }
                    
                    // Recalculer le total de la ligne
                    $quantite = floatval($item->getQuantite());
                    $prixUnitaire = floatval($item->getPrixUnitaireHt());
                    $remisePercent = floatval($item->getRemisePercent());
                    
                    $totalLigne = $quantite * $prixUnitaire;
                    if ($remisePercent > 0) {
                        $totalLigne = $totalLigne * (1 - $remisePercent / 100);
                    }
                    
                    $item->setTotalLigneHt(number_format($totalLigne, 2, '.', ''));
                    
                    $entityManager->persist($item);
                    $newDevis->addDevisItem($item);
                }
            }
            
            // Copier les LayoutElement
            $layoutElements = $snapshotData['layout_elements'] ?? [];
            foreach ($layoutElements as $layoutData) {
                $layoutElement = new LayoutElement();
                $layoutElement->setDevis($newDevis);
                $layoutElement->setType($layoutData['type'] ?? 'section_title');
                $layoutElement->setTitre($layoutData['titre'] ?? '');
                $layoutElement->setContenu($layoutData['contenu'] ?? '');
                $layoutElement->setIcon($layoutData['icon'] ?? '');
                $layoutElement->setOrdreAffichage($layoutData['ordre_affichage'] ?? 0);
                
                $entityManager->persist($layoutElement);
                $newDevis->addLayoutElement($layoutElement);
            }
        }
        
        // Calculer les totaux
        $newDevis->calculateTotals();
        
        $entityManager->flush();
        
        $this->addFlash('success', sprintf(
            'Devis %s créé avec succès (dupliqué depuis %s - %s)',
            $newDevis->getNumeroDevis(),
            $originalDevis->getNumeroDevis(),
            $version->generateAutoLabel()
        ));
        
        // Rediriger vers l'édition du nouveau devis
        return $this->redirectToRoute('app_devis_edit', ['id' => $newDevis->getId()]);
    }
}