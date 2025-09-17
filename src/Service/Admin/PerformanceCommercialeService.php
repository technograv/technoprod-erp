<?php

namespace App\Service\Admin;

use App\Entity\User;
use App\Entity\Secteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class PerformanceCommercialeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function getCommerciaux(): JsonResponse
    {
        $this->logger->info("Récupération des commerciaux");
        
        try {
            // Récupérer tous les utilisateurs et filtrer en PHP pour éviter les problèmes avec JSON
            $allUsers = $this->entityManager->getRepository(User::class)->findAll();
            $commerciaux = [];
            
            foreach ($allUsers as $user) {
                $roles = $user->getRoles();
                if (in_array('ROLE_COMMERCIAL', $roles) || in_array('ROLE_ADMIN', $roles)) {
                    $commerciaux[] = $user;
                }
            }

            $data = [];
            foreach ($commerciaux as $commercial) {
                $secteurs = [];
                foreach ($commercial->getSecteursCommercial() as $secteur) {
                    $secteurs[] = [
                        'id' => $secteur->getId(),
                        'nom' => $secteur->getNomSecteur()
                    ];
                }

                $data[] = [
                    'id' => $commercial->getId(),
                    'nom' => $commercial->getNom(),
                    'prenom' => $commercial->getPrenom(),
                    'email' => $commercial->getEmail(),
                    'secteurs' => $secteurs,
                    'objectif_mensuel' => $commercial->getObjectifMensuel(),
                    'objectif_semestriel' => $commercial->getObjectifSemestriel(),
                    'notes_objectifs' => $commercial->getNotesObjectifs()
                ];
            }

            return new JsonResponse([
                'success' => true,
                'commerciaux' => $data
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération des commerciaux: {$e->getMessage()}");
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commerciaux: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSecteursList(): JsonResponse
    {
        $this->logger->info("Récupération de la liste des secteurs");
        
        try {
            $secteurs = $this->entityManager->getRepository(Secteur::class)
                ->createQueryBuilder('s')
                ->where('s.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('s.nomSecteur')
                ->getQuery()
                ->getResult();

            $data = [];
            foreach ($secteurs as $secteur) {
                $data[] = [
                    'id' => $secteur->getId(),
                    'nom' => $secteur->getNomSecteur()
                ];
            }

            return new JsonResponse([
                'success' => true,
                'secteurs' => $data
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération des secteurs: {$e->getMessage()}");
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la récupération des secteurs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSecteursAdmin(): Response
    {
        $this->logger->info("Chargement de l'interface admin des secteurs");
        
        try {
            // Pour l'instant, retourner un contenu simple en attendant de résoudre le template
            return new Response('
                <div class="admin-section">
                    <h3 class="section-title">
                        <i class="fas fa-map me-2"></i>Gestion des secteurs commerciaux
                    </h3>
                    <p class="text-muted">Interface de gestion des secteurs en cours de développement...</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette interface sera bientôt disponible avec toutes les fonctionnalités de gestion des secteurs.
                    </div>
                </div>
            ');
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors du chargement des secteurs admin: {$e->getMessage()}");
            return new Response('
                <div class="alert alert-danger">
                    <h4>Erreur lors du chargement des secteurs</h4>
                    <p>' . htmlspecialchars($e->getMessage()) . '</p>
                </div>
            ');
        }
    }

    public function updateObjectifsCommercial(int $id, Request $request): JsonResponse
    {
        $this->logger->info("Mise à jour des objectifs du commercial ID: {$id}");
        
        try {
            $user = $this->entityManager->getRepository(User::class)->find($id);
            if (!$user) {
                return new JsonResponse(['success' => false, 'error' => 'Commercial non trouvé']);
            }

            $data = json_decode($request->getContent(), true);
            
            // Mise à jour des objectifs
            if (isset($data['mensuel'])) {
                $user->setObjectifMensuel($data['mensuel']);
            }
            if (isset($data['semestriel'])) {
                $user->setObjectifSemestriel($data['semestriel']);
            }
            if (isset($data['annuel'])) {
                $user->setObjectifAnnuel($data['annuel']);
            }

            $this->entityManager->flush();
            $this->logger->info("Objectifs du commercial {$user->getNom()} mis à jour avec succès");

            return new JsonResponse([
                'success' => true,
                'message' => 'Objectifs mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la mise à jour des objectifs: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getPerformancesCommerciales(Request $request): JsonResponse
    {
        $this->logger->info("Récupération des performances commerciales");
        
        try {
            $commercial = $request->query->get('commercial');
            $periode = $request->query->get('periode', 'mois');
            $annee = $request->query->get('annee', date('Y'));

            // TODO: Implémenter le calcul des performances réelles
            // Pour l'instant, données simulées
            $performances = [
                [
                    'periode' => 'Janvier 2024',
                    'commercial' => 'Jean Martin',
                    'realise' => 15000,
                    'objectif' => 12000,
                    'nb_devis' => 25,
                    'nb_commandes' => 8,
                    'taux_conversion' => 32.0
                ],
                [
                    'periode' => 'Février 2024',
                    'commercial' => 'Jean Martin',
                    'realise' => 18000,
                    'objectif' => 12000,
                    'nb_devis' => 30,
                    'nb_commandes' => 12,
                    'taux_conversion' => 40.0
                ]
            ];

            return new JsonResponse([
                'success' => true,
                'performances' => $performances
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération des performances: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function exportPerformancesCommerciales(Request $request): Response
    {
        $this->logger->info("Export des performances commerciales");
        
        $commercial = $request->query->get('commercial');
        $periode = $request->query->get('periode', 'mois');
        $annee = $request->query->get('annee', date('Y'));

        // TODO: Implémenter l'export Excel/PDF
        // Pour l'instant, retour CSV simple
        $filename = "performances_commerciales_{$annee}_{$periode}.csv";
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
        
        $content = "Période,Commercial,CA Réalisé,Objectif,Écart,Taux Réalisation\n";
        $content .= "Janvier 2024,Jean Martin,15000,12000,3000,125%\n";
        $content .= "Février 2024,Jean Martin,18000,12000,6000,150%\n";
        
        $response->setContent($content);
        return $response;
    }
}