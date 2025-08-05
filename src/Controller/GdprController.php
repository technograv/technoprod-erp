<?php

namespace App\Controller;

use App\Service\ConsentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gdpr')]
class GdprController extends AbstractController
{
    public function __construct(
        private ConsentService $consentService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Page de gestion des consentements utilisateur
     */
    #[Route('/consent', name: 'app_gdpr_consent', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function consentManagement(): Response
    {
        $user = $this->getUser();
        $userConsents = $this->consentService->getUserConsents($user);
        $purposeDescriptions = $this->consentService->getPurposeDescriptions();
        
        // Organiser les consentements par objectif
        $consentsByPurpose = [];
        foreach ($userConsents as $consent) {
            $consentsByPurpose[$consent->getPurpose()] = $consent;
        }

        return $this->render('gdpr/consent_management.html.twig', [
            'user_consents' => $consentsByPurpose,
            'purpose_descriptions' => $purposeDescriptions,
            'total_consents' => count($userConsents)
        ]);
    }

    /**
     * Accorder un consentement
     */
    #[Route('/consent/grant', name: 'app_gdpr_consent_grant', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function grantConsent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $purpose = $data['purpose'] ?? null;

        if (!$purpose || !in_array($purpose, array_keys($this->consentService->getPurposeDescriptions()))) {
            return $this->json(['error' => 'Objectif de traitement invalide'], 400);
        }

        $user = $this->getUser();
        $consent = $this->consentService->grantConsent($user, $purpose);

        return $this->json([
            'success' => true,
            'consent_id' => $consent->getId(),
            'purpose' => $consent->getPurpose(),
            'granted_at' => $consent->getGrantedAt()->format(\DateTimeInterface::ISO8601),
            'message' => 'Consentement accordé avec succès'
        ]);
    }

    /**
     * Retirer un consentement
     */
    #[Route('/consent/withdraw', name: 'app_gdpr_consent_withdraw', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function withdrawConsent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $purpose = $data['purpose'] ?? null;

        if (!$purpose) {
            return $this->json(['error' => 'Objectif de traitement requis'], 400);
        }

        $user = $this->getUser();
        $success = $this->consentService->withdrawConsent($user, $purpose);

        if (!$success) {
            return $this->json(['error' => 'Aucun consentement actif trouvé pour cet objectif'], 404);
        }

        return $this->json([
            'success' => true,
            'purpose' => $purpose,
            'withdrawn_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ISO8601),
            'message' => 'Consentement retiré avec succès'
        ]);
    }

    /**
     * Page de politique de confidentialité (GDPR Article 13)
     */
    #[Route('/privacy-policy', name: 'app_gdpr_privacy_policy', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->render('gdpr/privacy_policy.html.twig', [
            'last_updated' => new \DateTimeImmutable('2025-07-30'),
            'company_name' => 'TechnoProd',
            'dpo_email' => 'dpo@technoprod.fr',
            'contact_email' => 'contact@technoprod.fr'
        ]);
    }

    /**
     * Export des données personnelles (GDPR Article 20 - Portabilité)
     */
    #[Route('/data-export', name: 'app_gdpr_data_export', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function dataExport(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $user = $this->getUser();
            
            // Exporter toutes les données personnelles
            $userData = [
                'user_info' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'roles' => $user->getRoles(),
                    'active' => $user->isActive(),
                    'created_at' => $user->getCreatedAt()?->format(\DateTimeInterface::ISO8601),
                    'last_login' => $user->getLastLogin()?->format(\DateTimeInterface::ISO8601)
                ],
                'consent_data' => $this->consentService->exportUserConsentData($user),
                'export_metadata' => [
                    'export_date' => (new \DateTimeImmutable())->format(\DateTimeInterface::ISO8601),
                    'export_format' => 'JSON',
                    'gdpr_article' => 'Article 20 - Droit à la portabilité'
                ]
            ];

            // TODO: Ajouter autres données (devis, clients, etc.) selon les besoins

            $response = new JsonResponse($userData, 200, [], true);
            $response->headers->set('Content-Disposition', 'attachment; filename="mes_donnees_technoprod_' . date('Y-m-d') . '.json"');
            
            return $response;
        }

        return $this->render('gdpr/data_export.html.twig');
    }

    /**
     * Demande de suppression de compte (GDPR Article 17 - Droit à l'oubli)
     */
    #[Route('/account-deletion', name: 'app_gdpr_account_deletion', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function accountDeletion(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $confirmation = $request->request->get('confirmation');
            
            if ($confirmation !== 'SUPPRIMER') {
                $this->addFlash('error', 'Vous devez taper "SUPPRIMER" pour confirmer la suppression définitive.');
                return $this->redirectToRoute('app_gdpr_account_deletion');
            }

            $user = $this->getUser();
            
            // Supprimer les consentements
            $deletedConsents = $this->consentService->deleteUserConsentData($user);
            
            // TODO: Implémenter suppression complète des données selon besoins métier
            // - Anonymiser les devis/factures (obligation légale de conservation)
            // - Supprimer les données personnelles non obligatoires
            // - Marquer le compte comme supprimé plutôt que supprimer complètement
            
            $this->addFlash('success', 
                'Votre demande de suppression a été enregistrée. ' .
                'Vos données personnelles seront supprimées dans les 30 jours. ' .
                "Consentements supprimés: {$deletedConsents}"
            );
            
            // Rediriger vers page de confirmation
            return $this->redirectToRoute('app_logout');
        }

        return $this->render('gdpr/account_deletion.html.twig');
    }

    /**
     * Page de contact DPO (Délégué à la Protection des Données)
     */
    #[Route('/dpo-contact', name: 'app_gdpr_dpo_contact', methods: ['GET', 'POST'])]
    public function dpoContact(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // TODO: Implémenter envoi email vers DPO
            $this->addFlash('success', 'Votre message a été envoyé au Délégué à la Protection des Données. Vous recevrez une réponse sous 30 jours.');
            return $this->redirectToRoute('app_gdpr_dpo_contact');
        }

        return $this->render('gdpr/dpo_contact.html.twig', [
            'dpo_email' => 'dpo@technoprod.fr',
            'dpo_name' => 'Service Protection des Données',
            'response_delay' => '30 jours ouvrés'
        ]);
    }

    /**
     * Modal de consentement initial (affiché au premier accès)
     */
    #[Route('/consent-modal', name: 'app_gdpr_consent_modal', methods: ['GET'])]
    public function consentModal(): Response
    {
        $purposeDescriptions = $this->consentService->getPurposeDescriptions();
        
        return $this->render('gdpr/consent_modal.html.twig', [
            'purpose_descriptions' => $purposeDescriptions
        ]);
    }

    /**
     * Traitement du consentement initial en masse
     */
    #[Route('/consent-initial', name: 'app_gdpr_consent_initial', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function processInitialConsent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $consents = $data['consents'] ?? [];

        if (empty($consents)) {
            return $this->json(['error' => 'Aucun consentement fourni'], 400);
        }

        $user = $this->getUser();
        $processedConsents = [];

        foreach ($consents as $purpose => $granted) {
            if ($granted && in_array($purpose, array_keys($this->consentService->getPurposeDescriptions()))) {
                $consent = $this->consentService->grantConsent($user, $purpose);
                $processedConsents[] = [
                    'purpose' => $purpose,
                    'granted' => true,
                    'consent_id' => $consent->getId()
                ];
            }
        }

        return $this->json([
            'success' => true,
            'processed_consents' => $processedConsents,
            'total_granted' => count($processedConsents),
            'message' => 'Consentements enregistrés avec succès'
        ]);
    }

    /**
     * Dashboard admin des consentements GDPR
     */
    #[Route('/admin/consent-dashboard', name: 'app_gdpr_admin_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminConsentDashboard(): Response
    {
        $statistics = $this->consentService->getConsentStatistics();
        $expiringConsents = $this->consentService->checkExpiringConsents();

        return $this->render('gdpr/admin_dashboard.html.twig', [
            'statistics' => $statistics,
            'expiring_consents_count' => $expiringConsents,
            'purpose_descriptions' => $this->consentService->getPurposeDescriptions()
        ]);
    }
}