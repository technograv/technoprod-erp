<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\DevisVersion;
use App\Service\DevisLoggerService;
use App\Service\GmailMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/devis/{id}/client')]
#[IsGranted('PUBLIC_ACCESS')]
final class DevisClientController extends AbstractController
{
    #[Route('/{token}', name: 'app_devis_client_acces', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        Devis $devis,
        string $token,
        EntityManagerInterface $entityManager,
        DevisLoggerService $loggerService,
        \App\Service\DashboardService $dashboardService
    ): Response {
        // Vérifier le token actuel (version courante)
        $expectedToken = $devis->getClientAccessToken();

        $isArchivedVersion = false;
        $archivedVersion = null;

        if ($token !== $expectedToken) {
            // Token ne correspond pas au token sécurisé, vérifier l'ancien format MD5 (rétrocompatibilité)
            $legacyToken = md5($devis->getId() . $devis->getCreatedAt()->format('Y-m-d'));

            if ($token === $legacyToken) {
                // Ancien lien MD5 valide, on l'accepte pour la version courante
                // (Optionnel) On pourrait aussi mettre à jour le token en base ici
            } else {
                // Token ne correspond pas à la version courante, chercher dans les versions archivées
                $archivedVersion = $entityManager->getRepository(DevisVersion::class)
                    ->findOneBy([
                        'devis' => $devis,
                        'clientAccessToken' => $token
                    ]);

                if (!$archivedVersion) {
                    throw $this->createNotFoundException('Accès non autorisé');
                }

                $isArchivedVersion = true;
            }
        }

        // Vérifier si le devis est en cours d'actualisation (statut brouillon) - sauf si version archivée
        if (!$isArchivedVersion && $devis->getStatut() === 'brouillon') {
            return $this->render('devis/client_actualisation_en_cours.html.twig', [
                'devis' => $devis,
            ]);
        }

        // Si c'est une version archivée, afficher en lecture seule
        if ($isArchivedVersion) {
            return $this->renderArchivedVersion($devis, $archivedVersion, $token, $entityManager);
        }

        // Vérifier si le devis est expiré
        $devisExpire = false;
        if ($devis->getDateValidite() && $devis->getDateValidite() < new \DateTime('today')) {
            $devisExpire = true;
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            // Bloquer la signature si le devis est expiré
            if ($devisExpire && $action === 'signer') {
                $this->addFlash('error', 'Ce devis est expiré. Veuillez demander une actualisation.');
                return $this->redirectToRoute('app_devis_client_acces', [
                    'id' => $devis->getId(),
                    'token' => $token
                ]);
            }


            if ($action === 'signer') {
                $signatureNom = $request->request->get('signature_nom');
                $signatureEmail = $request->request->get('signature_email');
                $signatureData = $request->request->get('signature_data');

                if ($signatureNom && $signatureEmail && $signatureData) {
                    // Signature du devis
                    $devis->setSignatureNom($signatureNom);
                    $devis->setSignatureEmail($signatureEmail);
                    $devis->setSignatureData($signatureData);
                    $devis->setDateSignature(new \DateTime());
                    $devis->setStatut('signe');

                    $entityManager->flush();

                    // Invalider le cache du dashboard du commercial
                    if ($devis->getCommercial()) {
                        $dashboardService->invalidateUserCache($devis->getCommercial()->getId());
                    }

                    // Logger la signature
                    $loggerService->logSigned($devis, $signatureNom, $signatureEmail);

                    $this->addFlash('success', 'Devis signé avec succès !');
                }
            } elseif ($action === 'refuser') {
                $devis->setStatut('refuse');
                $entityManager->flush();

                // Invalider le cache du dashboard du commercial
                if ($devis->getCommercial()) {
                    $dashboardService->invalidateUserCache($devis->getCommercial()->getId());
                }

                $this->addFlash('info', 'Devis refusé.');
            }
        }

        // Récupérer la société assignée au devis (figée à la création)
        $societe = $devis->getSociete();

        // Fallback si le devis n'a pas de société assignée (anciens devis)
        if (!$societe) {
            if ($devis->getTemplate() && $devis->getTemplate()->getSociete()) {
                $societe = $devis->getTemplate()->getSociete();
            } else {
                $societe = $entityManager->getRepository(\App\Entity\Societe::class)
                    ->findOneBy(['ordre' => 1, 'active' => true])
                    ?: $entityManager->getRepository(\App\Entity\Societe::class)
                        ->findOneBy(['active' => true], ['ordre' => 'ASC']);
            }
        }

        // Récupérer la banque (depuis le template ou banque par défaut)
        $banque = null;
        if ($devis->getTemplate() && $devis->getTemplate()->getBanque()) {
            $banque = $devis->getTemplate()->getBanque();
        } else {
            $banque = $entityManager->getRepository(\App\Entity\Banque::class)
                ->findOneBy(['ordre' => 1, 'actif' => true])
                ?: $entityManager->getRepository(\App\Entity\Banque::class)
                    ->findOneBy(['actif' => true], ['ordre' => 'ASC']);
        }

        // Récupérer les CGV depuis le template
        $conditionsVente = null;
        if ($devis->getTemplate() && $devis->getTemplate()->getConditionsVente()) {
            $conditionsVente = $devis->getTemplate()->getConditionsVente();
        }

        return $this->render('devis/client_acces.html.twig', [
            'devis' => $devis,
            'token' => $token,
            'societe' => $societe,
            'banquePrincipale' => $banque,
            'conditionsVente' => $conditionsVente,
            'devisExpire' => $devisExpire,
        ]);
    }

    #[Route('/{token}/actualisation', name: 'app_devis_client_actualisation_request', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function actualisationRequest(
        Request $request,
        Devis $devis,
        string $token,
        EntityManagerInterface $entityManager,
        DevisLoggerService $loggerService,
        GmailMailerService $gmailMailer
    ): Response {
        // Vérifier le token
        $expectedToken = $devis->getClientAccessToken();
        $legacyToken = md5($devis->getId() . $devis->getCreatedAt()->format('Y-m-d'));

        if ($token !== $expectedToken && $token !== $legacyToken) {
            throw $this->createNotFoundException('Accès non autorisé');
        }

        // Récupérer les données du formulaire
        $clientNom = $request->request->get('client_nom');
        $clientEmail = $request->request->get('client_email');
        $message = $request->request->get('message');

        if (!$clientNom || !$clientEmail) {
            $this->addFlash('error', 'Veuillez renseigner votre nom et email.');
            return $this->redirectToRoute('app_devis_client_acces', [
                'id' => $devis->getId(),
                'token' => $token
            ]);
        }

        // Envoyer un email HTML professionnel au commercial si disponible
        if ($devis->getCommercial() && $devis->getCommercial()->getEmail()) {
            $commercialEmail = $devis->getCommercial()->getEmail();
            $commercial = $devis->getCommercial();

            // Vérifier si le devis est expiré
            $isExpired = $devis->getDateValidite() && $devis->getDateValidite() < new \DateTime('today');

            // Générer le lien de modification absolu
            $lienEdition = $this->generateUrl('app_devis_edit', ['id' => $devis->getId()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

            $subject = sprintf('[TechnoProd] %s - Devis %s',
                $isExpired ? 'Demande d\'actualisation' : 'Demande de modification',
                $devis->getNumeroDevis()
            );

            try {
                $email = (new \Symfony\Component\Mime\Email())
                    ->to($commercialEmail)
                    ->subject($subject)
                    ->html($this->renderView('emails/demande_actualisation.html.twig', [
                        'devis' => $devis,
                        'clientNom' => $clientNom,
                        'clientEmail' => $clientEmail,
                        'message' => $message,
                        'dateDemande' => new \DateTime(),
                        'isExpired' => $isExpired,
                        'lienEdition' => $lienEdition
                    ]));

                // Utiliser le même service Gmail que pour l'envoi au client
                $gmailMailer->sendWithUserGmail($email, $commercial);
            } catch (\Exception $e) {
                // Log l'erreur mais continuer le processus
                error_log('Erreur envoi email actualisation : ' . $e->getMessage());
            }
        }

        // Créer une nouvelle version pour tracer l'actualisation
        $currentState = $this->captureDevisState($devis);
        $reason = sprintf(
            "Actualisation demandée par %s (%s)",
            $clientNom,
            $clientEmail
        );
        $version = $this->createDevisVersionFromState($devis, $entityManager, $currentState, $reason);

        // Définir le label de la version
        if ($version->getVersionNumber() === 1) {
            $version->setVersionLabel('Version initiale expirée');
        } else {
            $version->setVersionLabel($devis->getNomProjet() ?: 'Version ' . $version->getVersionNumber());
        }

        // Changer le statut en "actualisation demandée" pour mise en valeur dans le dashboard
        $devis->setStatut('actualisation_demandee');

        // Mettre à jour les dates pour la nouvelle version
        $devis->setDateCreation(new \DateTime());
        $dateValidite = clone $devis->getDateCreation();
        $dateValidite->modify('+30 days');
        $devis->setDateValidite($dateValidite);

        // Vider les données de signature pour la nouvelle version
        $devis->setSignatureNom(null);
        $devis->setSignatureEmail(null);
        $devis->setSignatureData(null);
        $devis->setDateSignature(null);
        $devis->setDateEnvoi(null);

        $entityManager->flush();

        // Logger l'événement avec le message complet du client
        $now = new \DateTime();
        $details = sprintf(
            "Demande d'actualisation par %s (%s)\nDemande reçue le %s à %s\nDate validité originale: %s\n\n%s",
            $clientNom,
            $clientEmail,
            $now->format('d/m/Y'),
            $now->format('H:i'),
            $devis->getDateValidite()?->format('d/m/Y') ?? 'Non définie',
            $message ? "Message du client:\n\"" . $message . "\"" : 'Aucun message spécifique.'
        );
        $loggerService->log($devis, 'actualisation_demandee', $details);

        // Récupérer la société du template
        $societe = null;
        if ($devis->getTemplate() && $devis->getTemplate()->getSociete()) {
            $societe = $devis->getTemplate()->getSociete();
        } else {
            $societe = $entityManager->getRepository(\App\Entity\Societe::class)
                ->findOneBy(['ordre' => 1, 'active' => true])
                ?: $entityManager->getRepository(\App\Entity\Societe::class)
                    ->findOneBy(['active' => true], ['ordre' => 'ASC']);
        }

        // Rediriger vers la page de confirmation
        return $this->render('devis/actualisation_confirmee.html.twig', [
            'devis' => $devis,
            'token' => $token,
            'clientNom' => $clientNom,
            'clientEmail' => $clientEmail,
            'societe' => $societe
        ]);
    }

    /**
     * Capture l'état actuel du devis pour créer une version
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
            'items' => $items
        ];
    }

    /**
     * Crée une version à partir d'un état sauvegardé
     */
    private function createDevisVersionFromState(Devis $devis, EntityManagerInterface $entityManager, array $stateData, string $reason = null): DevisVersion
    {
        $version = new DevisVersion();
        $version->setDevis($devis);
        $version->setSnapshotData($stateData);

        // Utiliser le commercial du devis comme créateur de la version
        if ($devis->getCommercial()) {
            $version->setModifiedBy($devis->getCommercial());
        } else {
            // Fallback: utiliser le premier utilisateur trouvé (système)
            $systemUser = $entityManager->getRepository(\App\Entity\User::class)->findOneBy([], ['id' => 'ASC']);
            $version->setModifiedBy($systemUser);
        }

        // Calculer le numéro de version
        $existingVersions = $entityManager->getRepository(DevisVersion::class)
            ->findBy(['devis' => $devis], ['versionNumber' => 'DESC']);
        $lastVersionNumber = !empty($existingVersions) ? $existingVersions[0]->getVersionNumber() : 0;
        $version->setVersionNumber($lastVersionNumber + 1);

        if ($reason) {
            $version->setModificationReason($reason);
        }

        // Capturer le statut et total TTC au moment de la création
        $version->setStatutAtTime($stateData['devis_data']['statut'] ?? 'inconnu');
        $version->setTotalTtcAtTime($stateData['devis_data']['totalTtc'] ?? '0.00');

        $entityManager->persist($version);

        return $version;
    }

    /**
     * Affiche une version archivée du devis en lecture seule
     */
    private function renderArchivedVersion(Devis $devis, DevisVersion $version, string $token, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les données du snapshot
        $snapshotData = $version->getSnapshotData();
        $devisData = $snapshotData['devis_data'] ?? [];

        // Récupérer la société assignée au devis (figée à la création)
        $societe = $devis->getSociete();

        // Fallback si le devis n'a pas de société assignée (anciens devis)
        if (!$societe) {
            if ($devis->getTemplate() && $devis->getTemplate()->getSociete()) {
                $societe = $devis->getTemplate()->getSociete();
            } else {
                $societe = $entityManager->getRepository(\App\Entity\Societe::class)
                    ->findOneBy(['ordre' => 1, 'active' => true])
                    ?: $entityManager->getRepository(\App\Entity\Societe::class)
                        ->findOneBy(['active' => true], ['ordre' => 'ASC']);
            }
        }

        // Récupérer la banque (depuis le template ou banque par défaut)
        $banque = null;
        if ($devis->getTemplate() && $devis->getTemplate()->getBanque()) {
            $banque = $devis->getTemplate()->getBanque();
        } else {
            $banque = $entityManager->getRepository(\App\Entity\Banque::class)
                ->findOneBy(['ordre' => 1, 'actif' => true])
                ?: $entityManager->getRepository(\App\Entity\Banque::class)
                    ->findOneBy(['actif' => true], ['ordre' => 'ASC']);
        }

        // Récupérer les CGV depuis le template
        $conditionsVente = null;
        if ($devis->getTemplate() && $devis->getTemplate()->getConditionsVente()) {
            $conditionsVente = $devis->getTemplate()->getConditionsVente();
        }

        return $this->render('devis/client_acces_archived.html.twig', [
            'devis' => $devis,
            'version' => $version,
            'token' => $token,
            'societe' => $societe,
            'banquePrincipale' => $banque,
            'conditionsVente' => $conditionsVente,
            'isArchivedVersion' => true,
        ]);
    }
}
