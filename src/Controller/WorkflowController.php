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
use App\Service\AlerteManager;
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
        private AlerteManager $alerteManager,
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
    public function dashboard(Request $request): Response
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

        // Récupérer l'offset de semaine depuis la requête
        $currentWeekOffset = (int) $request->query->get('week', 0);
        
        // Générer les jours de la semaine (lundi à dimanche) pour le calendrier
        $startOfWeek = new \DateTime('monday this week');
        $startOfWeek->modify($currentWeekOffset . ' weeks');

        $weekDays = [];
        for ($i = 0; $i < 7; $i++) { // 7 jours : lundi à dimanche
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
        
        // Récupérer les événements Google Calendar pour la semaine demandée
        $weekEvents = [];
        try {
            if ($user->isGoogleAccount()) {
                $this->logger->info('Chargement calendrier pour semaine', [
                    'user_id' => $user->getId(),
                    'week_offset' => $currentWeekOffset,
                    'start_of_week' => $startOfWeek->format('Y-m-d')
                ]);
                $weekEvents = $this->googleCalendarService->getWeekEvents($user, $startOfWeek, $selectedCalendarIds);
                $this->logger->info('Événements chargés', [
                    'user_id' => $user->getId(),
                    'events_count' => count($weekEvents)
                ]);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, continuer sans les événements calendar
            $this->logger->warning('Erreur récupération calendrier Google', [
                'user_id' => $user->getId(),
                'week_offset' => $currentWeekOffset,
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
    public function calendarAjax(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Récupérer l'offset de semaine depuis les paramètres de requête
            $weekOffset = (int) $request->query->get('week', 0);
            
            // Calculer la semaine demandée
            $startOfWeek = new \DateTime('monday this week');
            $startOfWeek->modify($weekOffset . ' weeks');
            
            // Générer les jours de la semaine (lundi à dimanche) pour la nouvelle semaine
            $weekDays = [];
            for ($i = 0; $i < 7; $i++) {
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
            
            // Récupérer les événements Google Calendar pour la semaine demandée
            $weekEvents = [];
            try {
                if ($user->isGoogleAccount()) {
                    $startTime = microtime(true);
                    $weekEvents = $this->googleCalendarService->getWeekEvents($user, $startOfWeek, $selectedCalendarIds);
                    $endTime = microtime(true);
                    
                    $this->logger->info('Calendrier Google chargé via AJAX', [
                        'user_id' => $user->getId(),
                        'week_offset' => $weekOffset,
                        'duration_ms' => round(($endTime - $startTime) * 1000, 2),
                        'events_count' => count($weekEvents)
                    ]);
                }
            } catch (\Exception $e) {
                // En cas d'erreur, continuer sans les événements calendar
                $this->logger->warning('Erreur récupération calendrier Google pour AJAX', [
                    'user_id' => $user->getId(),
                    'week_offset' => $weekOffset,
                    'error' => $e->getMessage()
                ]);
                $weekEvents = [];
            }

            // Générer le HTML du calendrier pour la nouvelle semaine
            $calendarHtml = $this->renderView('workflow/partials/calendar_week.html.twig', [
                'week_events' => $weekEvents,
                'week_days' => $weekDays,
                'start_of_week' => $startOfWeek
            ]);

            // Formater le titre de la semaine
            $endOfWeek = clone $startOfWeek;
            $endOfWeek->modify('+6 days'); // Dimanche

            $weekTitle = 'Semaine du ' . $startOfWeek->format('d/m') . ' au ' . $endOfWeek->format('d/m/Y');

            return $this->json([
                'success' => true,
                'calendar_html' => $calendarHtml,
                'week_title' => $weekTitle,
                'week_offset' => $weekOffset,
                'start_date' => $startOfWeek->format('Y-m-d'),
                'end_date' => $endOfWeek->format('Y-m-d')
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur dans calendarAjax', [
                'user_id' => $this->getUser()?->getId(),
                'week_offset' => $request->query->get('week', 0),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du calendrier: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/dashboard/calendar-events-ajax', name: 'workflow_dashboard_calendar_events_ajax', methods: ['GET'])]
    public function calendarEventsAjax(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Récupérer l'offset de semaine depuis les paramètres de requête
            $weekOffset = (int) $request->query->get('week', 0);
            
            // Calculer la semaine demandée
            $startOfWeek = new \DateTime('monday this week');
            $startOfWeek->modify($weekOffset . ' weeks');
            
            // Récupérer les préférences utilisateur pour les calendriers
            $preferences = $this->entityManager->getRepository(\App\Entity\UserPreferences::class)
                ->findOneBy(['user' => $user]);
            
            $selectedCalendarIds = $preferences ? $preferences->getSelectedCalendarIds() : ['primary'];
            
            // Charger les événements Google Calendar
            $weekEvents = [];
            try {
                if ($user->isGoogleAccount()) {
                    $startTime = microtime(true);
                    $weekEvents = $this->googleCalendarService->getWeekEvents($user, $startOfWeek, $selectedCalendarIds);
                    $endTime = microtime(true);
                    
                    $this->logger->info('Événements Google Calendar chargés en async', [
                        'user_id' => $user->getId(),
                        'week_offset' => $weekOffset,
                        'duration_ms' => round(($endTime - $startTime) * 1000, 2),
                        'events_count' => count($weekEvents)
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Erreur récupération événements Google Calendar async', [
                    'user_id' => $user->getId(),
                    'week_offset' => $weekOffset,
                    'error' => $e->getMessage()
                ]);
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur chargement calendrier: ' . $e->getMessage()
                ]);
            }

            return $this->json([
                'success' => true,
                'events' => $weekEvents,
                'week_offset' => $weekOffset
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur dans calendarEventsAjax', [
                'user_id' => $this->getUser()?->getId(),
                'week_offset' => $request->query->get('week', 0),
                'error' => $e->getMessage()
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/prospection-telephonique', name: 'workflow_prospection_telephonique', methods: ['GET', 'POST'])]
    public function prospectionTelephonique(Request $request): Response|JsonResponse
    {
        $user = $this->getUser();

        // Supporter à la fois GET et POST pour compatibilité cache navigateur
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $zone = $data['zone'] ?? '';

            // Parser la zone (format: "CODE_POSTAL - NOM_COMMUNE")
            if (preg_match('/^(\d{5})\s*-\s*(.+)$/', $zone, $matches)) {
                $codePostal = $matches[1];
                $commune = $matches[2];
            } else {
                $codePostal = '';
                $commune = '';
            }

            // Pour les requêtes AJAX, retourner l'URL de redirection
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Veuillez rafraîchir votre page (F5 ou Ctrl+F5) puis réessayer',
                    'redirect' => $this->generateUrl('workflow_prospection_telephonique', [
                        'codePostal' => $codePostal,
                        'commune' => $commune
                    ])
                ]);
            }

            // Pour les requêtes POST normales, rediriger
            return $this->redirectToRoute('workflow_prospection_telephonique', [
                'codePostal' => $codePostal,
                'commune' => $commune
            ]);
        }

        $codePostal = $request->query->get('codePostal', '');
        $commune = $request->query->get('commune', '');

        // Récupérer les coordonnées du point de départ
        $pointDepart = null;
        if (!empty($codePostal)) {
            $pointDepart = $this->entityManager->getRepository(\App\Entity\CommuneFrancaise::class)
                ->findOneBy(['codePostal' => $codePostal]);
        } elseif (!empty($commune)) {
            $pointDepart = $this->entityManager->getRepository(\App\Entity\CommuneFrancaise::class)
                ->createQueryBuilder('cf')
                ->where('cf.nomCommune LIKE :commune')
                ->setParameter('commune', '%' . $commune . '%')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        // Si on a un point de départ avec coordonnées GPS, trier par distance
        if ($pointDepart && $pointDepart->getLatitude() && $pointDepart->getLongitude()) {
            $lat = (float) $pointDepart->getLatitude();
            $lon = (float) $pointDepart->getLongitude();

            // Requête SQL native pour calculer les distances avec formule Haversine
            $sql = "
                SELECT DISTINCT ON (c.id)
                    c.id,
                    6371 * acos(
                        LEAST(1.0, GREATEST(-1.0,
                            cos(radians(:lat)) * cos(radians(CAST(cf.latitude AS NUMERIC))) *
                            cos(radians(CAST(cf.longitude AS NUMERIC)) - radians(:lon)) +
                            sin(radians(:lat)) * sin(radians(CAST(cf.latitude AS NUMERIC)))
                        ))
                    ) as distance_km
                FROM client c
                LEFT JOIN forme_juridique fj ON c.forme_juridique_id = fj.id
                LEFT JOIN contact cont_liv ON c.contact_livraison_default_id = cont_liv.id
                LEFT JOIN adresse a_liv ON cont_liv.adresse_id = a_liv.id
                LEFT JOIN contact cont_fact ON c.contact_facturation_default_id = cont_fact.id
                LEFT JOIN adresse a_fact ON cont_fact.adresse_id = a_fact.id
                LEFT JOIN commune_francaise cf ON COALESCE(a_liv.code_postal, a_fact.code_postal) = cf.code_postal
                WHERE (a_liv.id IS NOT NULL OR a_fact.id IS NOT NULL)
                AND cf.latitude IS NOT NULL
                AND cf.longitude IS NOT NULL
                AND (fj.template_formulaire IS NULL OR fj.template_formulaire != 'personne_physique')
                ORDER BY c.id, distance_km ASC
            ";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $results = $stmt->executeQuery([
                'lat' => $lat,
                'lon' => $lon
            ])->fetchAllAssociative();

            // Récupérer les entités Client complètes avec distance
            $prospects = [];
            foreach ($results as $result) {
                $client = $this->entityManager->getRepository(\App\Entity\Client::class)->find($result['id']);
                if ($client) {
                    // Ajouter la distance comme propriété temporaire
                    $client->distanceKm = round($result['distance_km'], 1);
                    $prospects[] = $client;
                }
            }
        } else {
            // Pas de point de départ : afficher tous les clients avec adresse
            $qb = $this->entityManager->getRepository(\App\Entity\Client::class)
                ->createQueryBuilder('c')
                ->leftJoin('c.formeJuridique', 'fj')
                ->leftJoin('c.contactLivraisonDefault', 'contLiv')
                ->leftJoin('contLiv.adresse', 'aLiv')
                ->leftJoin('c.contactFacturationDefault', 'contFact')
                ->leftJoin('contFact.adresse', 'aFact')
                ->where('aLiv.id IS NOT NULL OR aFact.id IS NOT NULL')
                ->andWhere('fj.templateFormulaire IS NULL OR fj.templateFormulaire != :personnePhysique')
                ->setParameter('personnePhysique', 'personne_physique')
                ->orderBy('c.nomEntreprise', 'ASC');

            $prospects = $qb->getQuery()->getResult();
        }

        // Récupérer les secteurs de l'utilisateur pour info
        $secteurs = $this->entityManager->getRepository(Secteur::class)
            ->findBy(['commercial' => $user]);

        // Préparer les données pour le JavaScript
        $prospectsData = [];
        foreach ($prospects as $prospect) {
            $adresseLivraison = $prospect->getAdresseLivraison();
            $adresseFacturation = $prospect->getAdresseFacturation();
            $adresse = $adresseLivraison ?: $adresseFacturation;

            $prospectsData[] = [
                'id' => $prospect->getId(),
                'nomComplet' => $prospect->getNomComplet(),
                'statut' => $prospect->getStatut(),
                'telephone' => $prospect->getTelephone(),
                'email' => $prospect->getEmail(),
                'secteur' => $prospect->getSecteur() ? [
                    'id' => $prospect->getSecteur()->getId(),
                    'nomSecteur' => $prospect->getSecteur()->getNomSecteur()
                ] : null,
                'adresse' => $adresse ? [
                    'ville' => $adresse->getVille(),
                    'codePostal' => $adresse->getCodePostal()
                ] : null,
                'distanceKm' => $prospect->distanceKm ?? null,
                'createdAt' => $prospect->getCreatedAt() ? $prospect->getCreatedAt()->format('Y-m-d') : null
            ];
        }

        return $this->render('workflow/prospection_telephonique.html.twig', [
            'prospects' => $prospects,
            'prospectsData' => $prospectsData,
            'codePostal' => $codePostal,
            'commune' => $commune,
            'secteurs' => $secteurs
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
            // Récupérer le délai de relance configuré dans la société
            $societe = $user->getEffectiveSocietePrincipale();
            $delaiRelance = $societe ? $societe->getDelaiRelanceDevis() : 14; // Par défaut 14 jours

            $dateLimit = new \DateTime('-' . $delaiRelance . ' days');

            $devisARelancer = $this->entityManager->getRepository(Devis::class)
                ->createQueryBuilder('d')
                ->leftJoin('d.client', 'c')
                ->where('d.statut = :statut')
                ->andWhere('d.commercial = :commercial')
                ->andWhere('d.dateSignature IS NULL')
                ->andWhere('d.dateEnvoi IS NOT NULL')
                ->andWhere('d.dateEnvoi < :dateLimit')
                ->andWhere('d.dateValidite >= :today')
                ->setParameter('statut', 'envoye')
                ->setParameter('commercial', $user)
                ->setParameter('dateLimit', $dateLimit)
                ->setParameter('today', new \DateTime('today'))
                ->orderBy('d.dateEnvoi', 'ASC')
                ->getQuery()
                ->getResult();

            $data = [];
            foreach ($devisARelancer as $devis) {
                // Calculer les jours depuis l'envoi
                $joursDepuisEnvoi = $devis->getDateEnvoi() ?
                    $devis->getDateEnvoi()->diff(new \DateTime())->days : 0;

                // Vérifier si la validité est expirée
                $validiteExpiree = $devis->getDateValidite() && $devis->getDateValidite() < new \DateTime();

                $data[] = [
                    'id' => $devis->getId(),
                    'numeroDevis' => $devis->getNumeroDevis(),
                    'client_nom' => $devis->getClient() ? $devis->getClient()->getNomEntreprise() : 'N/A',
                    'totalTtc' => $devis->getTotalTtc(),
                    'dateEnvoi' => $devis->getDateEnvoi() ? $devis->getDateEnvoi()->format('d/m/Y') : null,
                    'dateValidite' => $devis->getDateValidite() ? $devis->getDateValidite()->format('d/m/Y') : null,
                    'joursDepuisEnvoi' => $joursDepuisEnvoi,
                    'validiteExpiree' => $validiteExpiree,
                    'urgence' => $joursDepuisEnvoi >= 30 ? 'high' : ($joursDepuisEnvoi >= 14 ? 'medium' : 'low'),
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
            // Récupérer les commandes validées sans date de livraison prévue
            $commandesSansLivraison = $this->entityManager->getRepository(Commande::class)
                ->createQueryBuilder('cmd')
                ->leftJoin('cmd.client', 'c')
                ->where('cmd.statut = :statut')
                ->andWhere('cmd.commercial = :commercial')
                ->andWhere('cmd.dateLivraisonPrevue IS NULL')
                ->setParameter('statut', 'validee')
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
                    'url_show' => $this->generateUrl('app_commande_show', ['id' => $commande->getId()])
                ];
            }

            return $this->json([
                'success' => true,
                'data' => $data,
                'total' => count($data)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération commandes sans livraison', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes'
            ], 500);
        }
    }

    #[Route('/livraisons-a-facturer', name: 'workflow_livraisons_a_facturer', methods: ['GET'])]
    public function livraisonsAFacturer(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            // Récupérer les commandes livrées sans facture finalisée
            $livraisonsAFacturer = $this->entityManager->getRepository(Commande::class)
                ->createQueryBuilder('cmd')
                ->leftJoin('cmd.client', 'c')
                ->leftJoin('App\Entity\Facture', 'f', 'WITH', 'f.commande = cmd AND f.statut != :statutBrouillon')
                ->where('cmd.commercial = :commercial')
                ->andWhere('cmd.dateLivraisonReelle IS NOT NULL')
                ->andWhere('f.id IS NULL')
                ->setParameter('commercial', $user)
                ->setParameter('statutBrouillon', 'brouillon')
                ->orderBy('cmd.dateLivraisonReelle', 'ASC')
                ->getQuery()
                ->getResult();

            $data = [];
            foreach ($livraisonsAFacturer as $commande) {
                $joursDepuisLivraison = $commande->getDateLivraisonReelle() ?
                    (new \DateTime())->diff($commande->getDateLivraisonReelle())->days : 0;

                $data[] = [
                    'id' => $commande->getId(),
                    'numeroCommande' => $commande->getNumeroCommande(),
                    'client_nom' => $commande->getClient() ? $commande->getClient()->getNomEntreprise() : 'N/A',
                    'totalTtc' => $commande->getTotalTtc(),
                    'dateLivraisonReelle' => $commande->getDateLivraisonReelle() ? $commande->getDateLivraisonReelle()->format('d/m/Y H:i') : null,
                    'joursDepuisLivraison' => $joursDepuisLivraison,
                    'urgence' => $joursDepuisLivraison > 30 ? 'high' : ($joursDepuisLivraison > 15 ? 'medium' : 'low'),
                    'statut' => $commande->getStatut(),
                    'url_show' => $this->generateUrl('app_commande_show', ['id' => $commande->getId()])
                ];
            }

            return $this->json([
                'success' => true,
                'data' => $data,
                'total' => count($data)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération livraisons à facturer', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons à facturer'
            ], 500);
        }
    }

    /**
     * Récupère les alertes actives pour l'utilisateur connecté (système unifié)
     * Filtre selon les rôles et sociétés - inclut alertes manuelles et automatiques
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

            // Utiliser le service AlerteManager pour récupérer toutes les alertes filtrées
            $alertes = $this->alerteManager->getAlertsForUser($user);

            $alertesData = [];
            foreach ($alertes as $alerte) {
                $message = $alerte->getMessage();

                // Générer URL vers l'entité si c'est une alerte automatique
                if ($alerte->isAutomatic() && $alerte->getEntityType() && $alerte->getEntityId()) {
                    $editUrl = null;
                    $metadata = $alerte->getMetadata();

                    if ($alerte->getEntityType() === 'App\\Entity\\Client') {
                        $editUrl = $this->generateUrl('app_prospect_show', ['id' => $alerte->getEntityId()]);
                    } elseif ($alerte->getEntityType() === 'App\\Entity\\Contact' && isset($metadata['client_id'])) {
                        $editUrl = $this->generateUrl('app_prospect_show', ['id' => $metadata['client_id']]);
                    }

                    if ($editUrl) {
                        $message .= ' <a href="' . $editUrl . '" target="_blank" class="alert-link">Voir détails</a>';
                    }
                }

                $alertesData[] = [
                    'id' => ($alerte->isManual() ? 'manual_' : 'auto_') . $alerte->getId(),
                    'titre' => $alerte->getTitre(),
                    'message' => $message,
                    'type' => $alerte->getType(),
                    'typeBootstrap' => $alerte->getTypeBootstrap(),
                    'typeIcon' => $alerte->getTypeIcon(),
                    'dismissible' => $alerte->isDismissible(),
                    'dateExpiration' => $alerte->getDateExpiration() ? $alerte->getDateExpiration()->format('d/m/Y à H:i') : null,
                    'createdAt' => $alerte->getCreatedAt()->format('d/m/Y à H:i'),
                    'source' => $alerte->isManual() ? 'manual' : 'automatic'
                ];
            }

            return $this->json([
                'success' => true,
                'alertes' => $alertesData,
                'total' => count($alertesData)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
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
     * Marque une alerte comme résolue (système unifié)
     */
    #[Route('/dashboard/alerte/{id}/resolve', name: 'workflow_alerte_resolve', methods: ['POST'])]
    public function resolveAlerte(int $id): JsonResponse
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

            // Récupérer l'alerte
            $alerte = $this->entityManager->getRepository(Alerte::class)->find($id);

            if (!$alerte) {
                return $this->json([
                    'success' => false,
                    'message' => 'Alerte introuvable'
                ], 404);
            }

            // Vérifier que l'utilisateur a le droit de résoudre cette alerte
            $userRoles = $user->getRoles();
            $userSociete = $user->getSocietePrincipale();

            $cibles = $alerte->getCibles() ?? [];
            $societesCibles = $alerte->getSocietesCibles() ?? [];

            // Vérifier les rôles
            $roleMatch = empty($cibles) || array_intersect($userRoles, $cibles);

            // Vérifier les sociétés
            $societeMatch = empty($societesCibles) ||
                ($userSociete && in_array($userSociete->getId(), $societesCibles));

            if (!$roleMatch || !$societeMatch) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas les droits pour résoudre cette alerte'
                ], 403);
            }

            // Utiliser AlerteManager pour résoudre
            $this->alerteManager->resolveAlerte($alerte, $user);

            return $this->json([
                'success' => true,
                'message' => 'Alerte résolue avec succès'
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