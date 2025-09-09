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
use Psr\Log\LoggerInterface;

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
        private SecteurService $secteurService,
        private \App\Service\CommuneGeometryCacheService $cacheService,
        private \App\Service\GoogleCalendarService $googleCalendarService,
        private LoggerInterface $logger
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
        $stats = $this->dashboardService->getWorkflowDashboardStats($user->getId());

        // Dernières activités
        $recentDevis = $this->entityManager->getRepository(Devis::class)
            ->findBy([], ['updatedAt' => 'DESC'], 5);
        $recentCommandes = $this->entityManager->getRepository(Commande::class)
            ->findBy([], ['updatedAt' => 'DESC'], 5);
        $recentFactures = $this->entityManager->getRepository(Facture::class)
            ->findBy([], ['updatedAt' => 'DESC'], 5);

        // Générer les jours ouvrés (lundi à vendredi) pour le calendrier
        $currentWeekOffset = 0;
        $startOfWeek = new \DateTime('monday this week');
        $startOfWeek->modify($currentWeekOffset . ' weeks');
        
        $weekDays = [];
        for ($i = 0; $i < 5; $i++) { // Seulement 5 jours : lundi à vendredi
            $day = clone $startOfWeek;
            $day->modify('+' . $i . ' days');
            
            $weekDays[] = [
                'date' => $day,
                'day_number' => $day->format('j'),
                'day_short_fr' => $this->getDayShortFr($day->format('N')),
                'is_today' => $day->format('Y-m-d') === (new \DateTime())->format('Y-m-d')
            ];
        }

        // Récupérer les préférences utilisateur pour les calendriers
        $preferences = $this->entityManager->getRepository(\App\Entity\UserPreferences::class)
            ->findOneBy(['user' => $user]);
        
        $selectedCalendarIds = $preferences ? $preferences->getSelectedCalendarIds() : ['primary'];
        
        // Récupérer les événements Google Calendar pour la semaine courante
        $weekEvents = [];
        try {
            if ($user->isGoogleAccount()) {
                $weekEvents = $this->googleCalendarService->getWeekEvents($user, $startOfWeek, $selectedCalendarIds);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, continuer sans les événements calendar
            $this->logger->warning('Erreur récupération calendrier Google', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }

        return $this->render('workflow/dashboard.html.twig', [
            'stats' => $stats,
            'recent_devis' => $recentDevis,
            'recent_commandes' => $recentCommandes,
            'recent_factures' => $recentFactures,
            'google_maps_api_key' => $this->getParameter('google.maps.api.key'),
            'calendar_available' => true, // Calendrier réactivé
            'week_events' => $weekEvents, // Événements récupérés depuis les calendriers sélectionnés
            'week_days' => $weekDays,
            'start_of_week' => $startOfWeek,
            'current_week_offset' => $currentWeekOffset,
            'selected_calendar_ids' => $selectedCalendarIds
        ]);
    }

    #[Route('/dashboard/mon-secteur-test', name: 'workflow_mon_secteur_test', methods: ['GET'])]
    public function getMonSecteurTest(): JsonResponse
    {
        try {
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }
            
            return $this->json([
                'success' => true,
                'message' => 'Test endpoint OK',
                'user' => $user->getNom() . ' ' . $user->getPrenom(),
                'user_id' => $user->getId()
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/dashboard/mon-secteur', name: 'workflow_mon_secteur', methods: ['GET'])]
    public function getMonSecteur(): JsonResponse
    {
        error_log("🔍 DEBUG: getMonSecteur - Version simplifiée pour dashboard commercial");
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                error_log("❌ DEBUG: Utilisateur non authentifié");
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }
            
            error_log("👤 DEBUG: Utilisateur connecté: " . $user->getNom() . " " . $user->getPrenom() . " (ID: " . $user->getId() . ")");
            
            // Récupérer SEULEMENT les secteurs de l'utilisateur connecté
            $secteurs = $this->entityManager->getRepository(Secteur::class)
                ->findBy(['commercial' => $user, 'isActive' => true]);
            
            error_log("🎯 DEBUG: Secteurs trouvés: " . count($secteurs));
            
            if (empty($secteurs)) {
                return $this->json([
                    'success' => true,
                    'secteurs' => [],
                    'contrats_actifs' => [],
                    'message' => 'Aucun secteur assigné à cet utilisateur',
                    'debug_user_id' => $user->getId(),
                    'debug_user_nom' => $user->getNom() . ' ' . $user->getPrenom()
                ]);
            }
            
            // Construire les données secteurs simples pour le JavaScript commercial
            $secteursData = [];
            
            foreach ($secteurs as $secteur) {
                error_log("📍 DEBUG: Traitement secteur: " . $secteur->getNomSecteur());
                
                // Calculer le centre basé sur les attributions
                $centreCoords = $this->calculateSecteurCenterFromAttributions($secteur);
                
                $secteurData = [
                    'id' => $secteur->getId(),
                    'nom' => $secteur->getNomSecteur(),
                    'couleur' => $secteur->getCouleurHex() ?: '#3498db',
                    'commercial' => $secteur->getCommercial() ? 
                        trim(($secteur->getCommercial()->getPrenom() ?: '') . ' ' . ($secteur->getCommercial()->getNom() ?: '')) : 
                        null,
                    'description' => $secteur->getDescription(),
                    'isActive' => $secteur->getIsActive(),
                    // Champs attendus par le JavaScript commercial
                    'nombre_divisions' => count($secteur->getAttributions()),
                    'nombre_clients' => count($secteur->getClients()),
                    'resume_territoire' => $secteur->getNomSecteur(),
                    'latitude' => $centreCoords['lat'],
                    'longitude' => $centreCoords['lng'],
                    // Ajouter les attributions pour compatibilité avec l'affichage polygones si nécessaire
                    'attributions' => []
                ];
                
                error_log("📍 DEBUG: Secteur {$secteur->getNomSecteur()} - Centre: ({$centreCoords['lat']}, {$centreCoords['lng']})");
                
                $secteursData[] = $secteurData;
            }
            
            $response = [
                'success' => true,
                'secteurs' => $secteursData,
                'contrats_actifs' => [], // Vide pour l'instant
                'total_contrats' => 0,
                'statistics' => [
                    'total_secteurs' => count($secteursData),
                    'total_clients' => array_sum(array_column($secteursData, 'nombre_clients')),
                    'total_zones' => array_sum(array_column($secteursData, 'nombre_divisions'))
                ],
                'debug' => [
                    'user_id' => $user->getId(),
                    'user_nom' => $user->getNom() . ' ' . $user->getPrenom(),
                    'methode' => 'simple_commercial_dashboard'
                ]
            ];
            
            error_log("✅ DEBUG: Réponse construite avec " . count($secteursData) . " secteurs");
            return $this->json($response);
            
        } catch (\Exception $e) {
            error_log("❌ ERREUR getMonSecteur WorkflowController: " . $e->getMessage());
            error_log("❌ ERREUR Stack trace: " . $e->getTraceAsString());
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
                'debug_error' => $e->getMessage()
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
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ]);
            }
            
            // Utiliser le service pour calculer les performances
            $performances = $this->secteurService->calculerPerformancesCommercial($user);
            
            // Ajouter quelques données réelles basiques
            $statsReelles = $this->dashboardService->getWorkflowDashboardStats($user->getId());
            
            return $this->json([
                'success' => true,
                'performances' => $performances,
                'stats_reelles' => [
                    'devis_brouillons' => $statsReelles['devis_brouillons'] ?? 0,
                    'devis_envoyes' => $statsReelles['devis_envoyes'] ?? 0,
                    'devis_signes' => $statsReelles['devis_signes'] ?? 0,
                    'ca_mensuel_estime' => $performances['realise_mensuel']
                ],
                'user_info' => [
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'objectif_mensuel' => $user->getObjectifMensuel() ?? 15000
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des performances: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/devis-brouillons', name: 'workflow_devis_brouillons', methods: ['GET'])]
    public function devisBrouillons(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        try {
            // Récupérer les devis en statut brouillon pour l'utilisateur connecté
            $devisBrouillons = $this->entityManager->getRepository(Devis::class)
                ->createQueryBuilder('d')
                ->leftJoin('d.client', 'c')
                ->where('d.statut = :statut')
                ->andWhere('d.commercial = :commercial')
                ->setParameter('statut', 'brouillon')
                ->setParameter('commercial', $user)
                ->orderBy('d.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
            
            $data = [];
            foreach ($devisBrouillons as $devis) {
                $data[] = [
                    'id' => $devis->getId(),
                    'numeroDevis' => $devis->getNumeroDevis(),
                    'client_nom' => $devis->getClient() ? $devis->getClient()->getNomEntreprise() : 'N/A',
                    'totalTtc' => $devis->getTotalTtc(),
                    'dateCreation' => $devis->getDateCreation() ? $devis->getDateCreation()->format('d/m/Y') : null,
                    'dateValidite' => $devis->getDateValidite() ? $devis->getDateValidite()->format('d/m/Y') : null,
                    'statut' => $devis->getStatut(),
                    'url_edit' => $this->generateUrl('app_devis_edit', ['id' => $devis->getId()]),
                    'url_show' => $this->generateUrl('app_devis_show', ['id' => $devis->getId()])
                ];
            }
            
            return $this->json([
                'success' => true,
                'data' => $data,
                'total' => count($data)
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des devis : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/devis-relances', name: 'workflow_devis_relances', methods: ['GET'])]
    public function devisRelances(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        try {
            // Récupérer les devis envoyés mais pas encore signés (à relancer)
            // et dont la date de validité approche (moins de 7 jours)
            $dateLimit = new \DateTime('+7 days');
            
            $devisARelancer = $this->entityManager->getRepository(Devis::class)
                ->createQueryBuilder('d')
                ->leftJoin('d.client', 'c')
                ->where('d.statut = :statut')
                ->andWhere('d.commercial = :commercial')
                ->andWhere('d.dateSignature IS NULL')
                ->andWhere('d.dateValidite <= :dateLimit')
                ->andWhere('d.dateEnvoi IS NOT NULL')
                ->setParameter('statut', 'envoye')
                ->setParameter('commercial', $user)
                ->setParameter('dateLimit', $dateLimit)
                ->orderBy('d.dateValidite', 'ASC')
                ->getQuery()
                ->getResult();
            
            $data = [];
            foreach ($devisARelancer as $devis) {
                $joursRestants = $devis->getDateValidite() ? 
                    (new \DateTime())->diff($devis->getDateValidite())->days : 0;
                    
                $data[] = [
                    'id' => $devis->getId(),
                    'numeroDevis' => $devis->getNumeroDevis(),
                    'client_nom' => $devis->getClient() ? $devis->getClient()->getNomEntreprise() : 'N/A',
                    'totalTtc' => $devis->getTotalTtc(),
                    'dateEnvoi' => $devis->getDateEnvoi() ? $devis->getDateEnvoi()->format('d/m/Y') : null,
                    'dateValidite' => $devis->getDateValidite() ? $devis->getDateValidite()->format('d/m/Y') : null,
                    'joursRestants' => $joursRestants,
                    'urgence' => $joursRestants <= 2 ? 'high' : ($joursRestants <= 5 ? 'medium' : 'low'),
                    'statut' => $devis->getStatut(),
                    'url_show' => $this->generateUrl('app_devis_show', ['id' => $devis->getId()]),
                    'url_resend' => $this->generateUrl('app_devis_resend', ['id' => $devis->getId()])
                ];
            }
            
            return $this->json([
                'success' => true,
                'data' => $data,
                'total' => count($data)
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des devis à relancer : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/commandes-sans-livraison', name: 'workflow_commandes_sans_livraison', methods: ['GET'])]
    public function commandesSansLivraison(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        try {
            // Récupérer les commandes confirmées mais pas encore livrées
            $commandesSansLivraison = $this->entityManager->getRepository(Commande::class)
                ->createQueryBuilder('cmd')
                ->leftJoin('cmd.client', 'c')
                ->where('cmd.statut IN (:statuts)')
                ->andWhere('cmd.commercial = :commercial')
                ->andWhere('cmd.dateLivraison IS NULL')
                ->setParameter('statuts', ['confirmee', 'en_production'])
                ->setParameter('commercial', $user)
                ->orderBy('cmd.dateCommande', 'ASC')
                ->getQuery()
                ->getResult();
            
            $data = [];
            foreach ($commandesSansLivraison as $commande) {
                $joursAttente = $commande->getDateCommande() ? 
                    (new \DateTime())->diff($commande->getDateCommande())->days : 0;
                    
                $data[] = [
                    'id' => $commande->getId(),
                    'numeroCommande' => $commande->getNumeroCommande(),
                    'client_nom' => $commande->getClient() ? $commande->getClient()->getNomEntreprise() : 'N/A',
                    'totalTtc' => $commande->getTotalTtc(),
                    'dateCommande' => $commande->getDateCommande() ? $commande->getDateCommande()->format('d/m/Y') : null,
                    'dateLivraisonPrevue' => $commande->getDateLivraisonPrevue() ? $commande->getDateLivraisonPrevue()->format('d/m/Y') : null,
                    'joursAttente' => $joursAttente,
                    'urgence' => $joursAttente > 14 ? 'high' : ($joursAttente > 7 ? 'medium' : 'low'),
                    'statut' => $commande->getStatut(),
                    'url_show' => $this->generateUrl('app_commande_show', ['id' => $commande->getId()]),
                    'url_expedier' => $this->generateUrl('app_commande_expedier', ['id' => $commande->getId()])
                ];
            }
            
            return $this->json([
                'success' => true,
                'data' => $data,
                'total' => count($data)
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/livraisons-facturer', name: 'workflow_livraisons_facturer', methods: ['GET'])]
    public function livraisonsFacturer(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        try {
            // Récupérer les commandes livrées mais pas encore facturées
            $livraisonsAFacturer = $this->entityManager->getRepository(Commande::class)
                ->createQueryBuilder('cmd')
                ->leftJoin('cmd.client', 'c')
                ->leftJoin('cmd.factures', 'f')
                ->where('cmd.statut = :statut')
                ->andWhere('cmd.commercial = :commercial')
                ->andWhere('cmd.dateLivraison IS NOT NULL')
                ->andWhere('f.id IS NULL') // Pas encore de facture associée
                ->setParameter('statut', 'livree')
                ->setParameter('commercial', $user)
                ->orderBy('cmd.dateLivraison', 'ASC')
                ->getQuery()
                ->getResult();
            
            $data = [];
            foreach ($livraisonsAFacturer as $commande) {
                $joursDepuisLivraison = $commande->getDateLivraison() ? 
                    (new \DateTime())->diff($commande->getDateLivraison())->days : 0;
                    
                $data[] = [
                    'id' => $commande->getId(),
                    'numeroCommande' => $commande->getNumeroCommande(),
                    'client_nom' => $commande->getClient() ? $commande->getClient()->getNomEntreprise() : 'N/A',
                    'totalTtc' => $commande->getTotalTtc(),
                    'dateCommande' => $commande->getDateCommande() ? $commande->getDateCommande()->format('d/m/Y') : null,
                    'dateLivraison' => $commande->getDateLivraison() ? $commande->getDateLivraison()->format('d/m/Y') : null,
                    'joursDepuisLivraison' => $joursDepuisLivraison,
                    'urgence' => $joursDepuisLivraison > 5 ? 'high' : ($joursDepuisLivraison > 2 ? 'medium' : 'low'),
                    'statut' => $commande->getStatut(),
                    'url_show' => $this->generateUrl('app_commande_show', ['id' => $commande->getId()]),
                    'url_facturer' => $this->generateUrl('app_facture_create_from_commande', ['id' => $commande->getId()])
                ];
            }
            
            return $this->json([
                'success' => true,
                'data' => $data,
                'total' => count($data)
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons à facturer : ' . $e->getMessage()
            ], 500);
        }
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

    /**
     * Récupérer toutes les communes d'un type géographique donné
     */
    private function getCommunesPourType(string $type, $division, EntityManagerInterface $entityManager): array
    {
        switch ($type) {
            case 'commune':
                // Une seule commune
                return [[
                    'codeInseeCommune' => $division->getCodeInseeCommune(),
                    'nomCommune' => $division->getNomCommune()
                ]];
                
            case 'code_postal':
                // Toutes les communes de ce code postal
                $communes = $entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codePostal = :codePostal 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codePostal', $division->getCodePostal())
                ->getResult();
                return $communes;
                
            case 'epci':
                // Toutes les communes de cet EPCI
                $communes = $entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeEpci = :codeEpci 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeEpci', $division->getCodeEpci())
                ->getResult();
                return $communes;
                
            case 'departement':
                // Toutes les communes de ce département
                $communes = $entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeDepartement = :codeDepartement 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeDepartement', $division->getCodeDepartement())
                ->getResult();
                return $communes;
                
            case 'region':
                // Toutes les communes de cette région
                $communes = $entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeRegion = :codeRegion 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeRegion', $division->getCodeRegion())
                ->getResult();
                return $communes;
                
            default:
                return [];
        }
    }

    /**
     * Calcule les bounds d'un secteur avec positionnement des coordonnées
     */
    private function calculerBoundsSecteurHierarchique(array &$secteurData, Secteur $secteur, array $communesAvecGeometries): void
    {
        $minLat = $minLng = PHP_FLOAT_MAX;
        $maxLat = $maxLng = PHP_FLOAT_MIN;
        $hasCoordinates = false;
        $totalLat = $totalLng = 0;
        $countCoords = 0;
        
        foreach ($communesAvecGeometries as $commune) {
            if (isset($commune['coordinates']) && is_array($commune['coordinates'])) {
                foreach ($commune['coordinates'] as $coord) {
                    if (isset($coord['lat']) && isset($coord['lng'])) {
                        $lat = (float) $coord['lat'];
                        $lng = (float) $coord['lng'];
                        $minLat = min($minLat, $lat);
                        $maxLat = max($maxLat, $lat);
                        $minLng = min($minLng, $lng);
                        $maxLng = max($maxLng, $lng);
                        $totalLat += $lat;
                        $totalLng += $lng;
                        $countCoords++;
                        $hasCoordinates = true;
                    }
                }
            }
        }
        
        if ($hasCoordinates && $countCoords > 0) {
            // Calculer le centre géométrique
            $centerLat = $totalLat / $countCoords;
            $centerLng = $totalLng / $countCoords;
            
            // Mise à jour des coordonnées du secteur pour le JavaScript
            $secteurData['latitude'] = round($centerLat, 6);
            $secteurData['longitude'] = round($centerLng, 6);
            $secteurData['hasCoordinates'] = true;
            
            // Optionnel: ajouter les bounds pour usage futur
            $latMargin = ($maxLat - $minLat) * 0.1;
            $lngMargin = ($maxLng - $minLng) * 0.1;
            
            $secteurData['bounds'] = [
                'southwest' => [
                    'lat' => $minLat - $latMargin,
                    'lng' => $minLng - $lngMargin
                ],
                'northeast' => [
                    'lat' => $maxLat + $latMargin,
                    'lng' => $maxLng + $lngMargin
                ]
            ];
            
            error_log("📍 Secteur {$secteurData['nom']}: Centre calculé ({$centerLat}, {$centerLng}) à partir de {$countCoords} coordonnées");
        }
    }

    /**
     * Calcule le centre géographique d'un secteur basé sur ses attributions
     * Utilise les coordonnées des divisions administratives pour un calcul précis
     */
    private function calculateSecteurCenterFromAttributions(Secteur $secteur): array
    {
        $attributions = $secteur->getAttributions();
        
        if ($attributions->isEmpty()) {
            // Centre Toulouse par défaut si aucune attribution
            return ['lat' => 43.6, 'lng' => 1.4];
        }
        
        $totalLat = 0;
        $totalLng = 0;
        $count = 0;
        
        foreach ($attributions as $attribution) {
            $division = $attribution->getDivisionAdministrative();
            
            if ($division && $division->getLatitude() && $division->getLongitude()) {
                $lat = (float) $division->getLatitude();
                $lng = (float) $division->getLongitude();
                
                // Éviter les coordonnées par défaut ou invalides
                if ($lat !== 0.0 && $lng !== 0.0 && $lat !== 43.6 && $lng !== 1.4) {
                    $totalLat += $lat;
                    $totalLng += $lng;
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
        
        // Si aucune coordonnée valide trouvée, utiliser le centre France/Toulouse
        return ['lat' => 43.6, 'lng' => 1.4];
    }

    private function calculateSecteurCenter(Secteur $secteur): array
    {
        // Utiliser la nouvelle méthode basée sur les attributions
        return $this->calculateSecteurCenterFromAttributions($secteur);
    }

    /**
     * Convertit le numéro de jour de semaine en abréviation française
     */
    private function getDayShortFr(int $dayNumber): string
    {
        $days = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
            5 => 'Ven',
            6 => 'Sam',
            7 => 'Dim'
        ];
        
        return $days[$dayNumber] ?? 'Lun';
    }
}