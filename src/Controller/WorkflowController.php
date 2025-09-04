<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\Commande;
use App\Entity\Facture;
use App\Entity\User;
use App\Entity\Secteur;
use App\Entity\Alerte;
use App\Entity\AlerteUtilisateur;
use App\Service\WorkflowService;
use App\Service\DashboardService;
use App\Service\AlerteService;
use App\Service\SecteurService;
use App\DTO\Dashboard\ProductionStatusUpdateDto;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/workflow')]
#[IsGranted('ROLE_USER')]
class WorkflowController extends AbstractController
{
    public function __construct(
        private WorkflowService $workflowService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private DashboardService $dashboardService,
        private AlerteService $alerteService,
        private SecteurService $secteurService
    ) {}

    #[Route('/devis/{id}/action/{action}', name: 'workflow_devis_action', methods: ['POST'])]
    public function devisAction(Devis $devis, string $action): JsonResponse
    {
        try {
            if ($action === 'convert_to_commande') {
                $commande = $this->workflowService->convertDevisToCommande($devis);
                
                $this->addFlash('success', "Commande {$commande->getNumeroCommande()} créée avec succès !");
                
                return $this->json([
                    'success' => true,
                    'message' => 'Devis converti en commande',
                    'redirect' => $this->generateUrl('app_commande_show', ['id' => $commande->getId()])
                ]);
            } else {
                $this->workflowService->changeDevisStatut($devis, $action);
                
                $this->addFlash('success', "Statut du devis mis à jour : {$devis->getStatut()}");
                
                return $this->json([
                    'success' => true,
                    'message' => 'Statut mis à jour',
                    'new_statut' => $devis->getStatut(),
                    'statut_label' => $devis->getStatut()
                ]);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/commande/{id}/action/{action}', name: 'workflow_commande_action', methods: ['POST'])]
    public function commandeAction(Commande $commande, string $action): JsonResponse
    {
        try {
            if ($action === 'convert_to_facture') {
                $facture = $this->workflowService->convertCommandeToFacture($commande);
                
                $this->addFlash('success', "Facture {$facture->getNumeroFacture()} créée avec succès !");
                
                return $this->json([
                    'success' => true,
                    'message' => 'Commande convertie en facture',
                    'redirect' => $this->generateUrl('app_facture_show', ['id' => $facture->getId()])
                ]);
            } else {
                $this->workflowService->changeCommandeStatut($commande, $action);
                
                $this->addFlash('success', "Statut de la commande mis à jour : {$commande->getStatutLabel()}");
                
                return $this->json([
                    'success' => true,
                    'message' => 'Statut mis à jour',
                    'new_statut' => $commande->getStatut(),
                    'statut_label' => $commande->getStatutLabel()
                ]);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/facture/{id}/action/{action}', name: 'workflow_facture_action', methods: ['POST'])]
    public function factureAction(Facture $facture, string $action): JsonResponse
    {
        try {
            $this->workflowService->changeFactureStatut($facture, $action);
            
            $this->addFlash('success', "Statut de la facture mis à jour : {$facture->getStatutLabel()}");
            
            return $this->json([
                'success' => true,
                'message' => 'Statut mis à jour',
                'new_statut' => $facture->getStatut(),
                'statut_label' => $facture->getStatutLabel()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/commande/{id}/production/{itemId}', name: 'workflow_commande_item_production', methods: ['POST'])]
    public function updateProductionStatus(Commande $commande, int $itemId, #[MapRequestPayload] ProductionStatusUpdateDto $dto): JsonResponse
    {
        try {
            $errors = $this->validator->validate($dto);
            
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                return $this->json(['success' => false, 'errors' => $errorMessages], 400);
            }
            
            $item = null;
            foreach ($commande->getCommandeItems() as $commandeItem) {
                if ($commandeItem->getId() === $itemId) {
                    $item = $commandeItem;
                    break;
                }
            }
            
            if (!$item) {
                return $this->json(['success' => false, 'message' => 'Ligne non trouvée'], 404);
            }
            
            $item->setStatutProduction($dto->statut);
            $item->setUpdatedAt(new \DateTimeImmutable());
            
            // Mettre à jour les dates selon le statut
            switch ($dto->statut) {
                case 'en_cours':
                    if (!$item->getDateProductionPrevue()) {
                        $item->setDateProductionPrevue(new \DateTime('+2 days'));
                    }
                    break;
                case 'terminee':
                    $item->setDateProductionReelle(new \DateTime());
                    break;
            }
            
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Statut de production mis à jour',
                'new_statut' => $item->getStatutProduction(),
                'statut_label' => $item->getStatutProductionLabel()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/dashboard', name: 'workflow_dashboard')]
    public function dashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Utiliser le service pour obtenir les statistiques
        $stats = $this->dashboardService->getDashboardStats($user);

        // Dernières activités
        $recentDevis = $this->entityManager->getRepository(Devis::class)
            ->findBy([], ['updatedAt' => 'DESC'], 5);
        $recentCommandes = $this->entityManager->getRepository(Commande::class)
            ->findBy([], ['updatedAt' => 'DESC'], 5);
        $recentFactures = $this->entityManager->getRepository(Facture::class)
            ->findBy([], ['updatedAt' => 'DESC'], 5);

        return $this->render('workflow/dashboard.html.twig', [
            'stats' => $stats,
            'recent_devis' => $recentDevis,
            'recent_commandes' => $recentCommandes,
            'recent_factures' => $recentFactures,
            'google_maps_api_key' => $this->getParameter('google.maps.api.key'),
            'calendar_available' => false, // Désactiver temporairement le calendrier
            'week_events' => [], // Événements vides pour éviter d'autres erreurs
            'start_of_week' => new \DateTime('monday this week'),
            'current_week_offset' => 0
        ]);
    }

    #[Route('/dashboard/mon-secteur', name: 'workflow_mon_secteur', methods: ['GET'])]
    public function getMonSecteur(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ]);
            }
            
            // Utiliser le service optimisé
            $data = $this->secteurService->getSecteurDataForCommercial($user);
            
            if (empty($data['secteurs'])) {
                return $this->json([
                    'success' => true,
                    'secteurs' => [],
                    'contrats_actifs' => [],
                    'message' => 'Aucun secteur assigné'
                ]);
            }
            
            // Enrichir avec coordonnées pour Google Maps
            $secteursData = [];
            foreach ($data['secteurs'] as $secteurInfo) {
                // Récupérer l'entité pour calculer le centre
                $secteur = $this->entityManager->getRepository(Secteur::class)->find($secteurInfo['id']);
                $centreCoords = $this->calculateSecteurCenter($secteur);
                
                $secteursData[] = [
                    'id' => $secteurInfo['id'],
                    'nom' => $secteurInfo['nom'],
                    'couleur' => $secteurInfo['couleur'] ?: '#007bff',
                    'nombre_divisions' => $secteurInfo['nombre_zones'],
                    'resume_territoire' => $secteurInfo['nom'],
                    'latitude' => $centreCoords['lat'],
                    'longitude' => $centreCoords['lng']
                ];
            }
            
            return $this->json([
                'success' => true,
                'secteurs' => $secteursData,
                'contrats_actifs' => $data['contrats_actifs'],
                'total_contrats' => count($data['contrats_actifs']),
                'statistics' => $data['statistics']
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/dashboard/calendar-ajax', name: 'workflow_dashboard_calendar_ajax', methods: ['GET'])]
    public function calendarAjax(): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité calendrier temporairement désactivée'
        ]);
    }

    #[Route('/prospection-telephonique', name: 'workflow_prospection_telephonique', methods: ['POST'])]
    public function prospectionTelephonique(Request $request): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité prospection téléphonique temporairement désactivée'
        ]);
    }

    #[Route('/prospection-terrain', name: 'workflow_prospection_terrain', methods: ['POST'])]
    public function prospectionTerrain(Request $request): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité prospection terrain temporairement désactivée'
        ]);
    }

    #[Route('/echeances-contrat', name: 'workflow_echeances_contrat', methods: ['GET'])]
    public function echeancesContrat(): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité échéances contrat temporairement désactivée'
        ]);
    }

    #[Route('/visites-clients', name: 'workflow_visites_clients', methods: ['GET'])]
    public function visitesClients(): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité visites clients temporairement désactivée'
        ]);
    }

    #[Route('/dashboard/mes-performances', name: 'workflow_mes_performances', methods: ['GET'])]
    public function getMesPerformances(): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité mes performances temporairement désactivée'
        ]);
    }

    #[Route('/devis-brouillons', name: 'workflow_devis_brouillons', methods: ['GET'])]
    public function devisBrouillons(): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité devis brouillons temporairement désactivée'
        ]);
    }

    #[Route('/devis-relances', name: 'workflow_devis_relances', methods: ['GET'])]
    public function devisRelances(): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité devis relances temporairement désactivée'
        ]);
    }

    #[Route('/commandes-sans-livraison', name: 'workflow_commandes_sans_livraison', methods: ['GET'])]
    public function commandesSansLivraison(): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité commandes sans livraison temporairement désactivée'
        ]);
    }

    #[Route('/livraisons-facturer', name: 'workflow_livraisons_facturer', methods: ['GET'])]
    public function livraisonsFacturer(): JsonResponse
    {
        // Route placeholder pour éviter l'erreur de template
        return $this->json([
            'success' => false,
            'message' => 'Fonctionnalité livraisons à facturer temporairement désactivée'
        ]);
    }

    /**
     * Récupère les alertes actives pour l'utilisateur connecté
     * Filtre selon les rôles et exclut les alertes déjà fermées
     */
    #[Route('/dashboard/mes-alertes', name: 'workflow_mes_alertes', methods: ['GET'])]
    public function getMesAlertes(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ]);
            }

            // Utiliser le service optimisé pour obtenir les alertes visibles
            $alertes = $this->alerteService->getVisibleAlertsForUser($user);

            // Formater les alertes
            $alertesData = [];
            foreach ($alertes as $alerte) {
                $alertesData[] = [
                    'id' => $alerte->getId(),
                    'titre' => $alerte->getTitre(),
                    'message' => $alerte->getMessage(),
                    'type' => $alerte->getType(),
                    'typeBootstrap' => $alerte->getTypeBootstrap(),
                    'typeIcon' => $alerte->getTypeIcon(),
                    'dismissible' => $alerte->isDismissible(),
                    'dateExpiration' => $alerte->getDateExpiration() ? $alerte->getDateExpiration()->format('d/m/Y à H:i') : null,
                    'createdAt' => $alerte->getCreatedAt()->format('d/m/Y à H:i')
                ];
            }

            return $this->json([
                'success' => true,
                'alertes' => $alertesData,
                'total' => count($alertesData),
                'debug_user' => $user->getPrenom() . ' ' . $user->getNom()
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
                'debug_trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Permet à un utilisateur de fermer une alerte individuellement
     * Crée un enregistrement AlerteUtilisateur pour tracking
     */
    #[Route('/dashboard/alerte/{id}/dismiss', name: 'workflow_alerte_dismiss', methods: ['POST'])]
    public function dismissAlerte(Alerte $alerte): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ]);
            }

            // Utiliser le service pour fermer l'alerte
            $success = $this->alerteService->dismissAlertForUser($alerte, $user);
            
            if (!$success) {
                return $this->json([
                    'success' => false,
                    'message' => 'Cette alerte ne peut pas être fermée'
                ]);
            }

            return $this->json([
                'success' => true,
                'message' => 'Alerte fermée avec succès'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateSecteurCenter(Secteur $secteur): array
    {
        $zones = $secteur->getAttributions();
        
        if ($zones->isEmpty()) {
            // Centre Toulouse par défaut
            return ['lat' => 43.6, 'lng' => 1.4];
        }
        
        $totalLat = 0;
        $totalLng = 0;
        $count = 0;
        
        foreach ($zones as $attribution) {
            $zone = $attribution->getZone();
            
            // Si la zone a des communes liées, utiliser leur centre
            if ($zone->getCommuneFrancaise()) {
                $commune = $zone->getCommuneFrancaise();
                if ($commune->getLatitude() && $commune->getLongitude()) {
                    $totalLat += (float) $commune->getLatitude();
                    $totalLng += (float) $commune->getLongitude();
                    $count++;
                }
            }
        }
        
        if ($count > 0) {
            return [
                'lat' => round($totalLat / $count, 6),
                'lng' => round($totalLng / $count, 6)
            ];
        }
        
        // Fallback Toulouse
        return ['lat' => 43.6, 'lng' => 1.4];
    }
}