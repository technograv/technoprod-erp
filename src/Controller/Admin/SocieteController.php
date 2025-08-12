<?php

namespace App\Controller\Admin;

use App\Entity\Societe;
use App\Entity\User;
use App\Repository\SocieteRepository;
use App\Service\TenantService;
use App\Service\InheritanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class SocieteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantService $tenantService,
        private InheritanceService $inheritanceService
    ) {}

    // ================================
    // SOCIETES MANAGEMENT
    // ================================

    #[Route('/societes', name: 'app_admin_societes', methods: ['GET'])]
    public function societes(): Response
    {
        $societes = $this->entityManager
            ->getRepository(Societe::class)
            ->findBy([], ['ordre' => 'ASC', 'nom' => 'ASC']);
        
        // Déterminer si c'est une société mère pour les permissions d'interface
        $currentSociete = $this->tenantService->getCurrentSociete();
        $isSocieteMere = $currentSociete ? $currentSociete->isMere() : true;
        
        return $this->render('admin/societe/societes.html.twig', [
            'societes' => $societes,
            'is_societe_mere' => $isSocieteMere
        ]);
    }

    #[Route('/societes/{id}', name: 'app_admin_societe_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getSociete(Societe $societe): JsonResponse
    {
        return $this->json([
            'id' => $societe->getId(),
            'nom' => $societe->getNom(),
            'code' => $societe->getCode(),
            'description' => $societe->getDescription(),
            'siret' => $societe->getSiret(),
            'siren' => $societe->getSiren(),
            'adresse' => $societe->getAdresse(),
            'code_postal' => $societe->getCodePostal(),
            'ville' => $societe->getVille(),
            'pays' => $societe->getPays(),
            'telephone' => $societe->getTelephone(),
            'email' => $societe->getEmail(),
            'site_web' => $societe->getSiteWeb(),
            'logo_url' => $societe->getLogoUrl(),
            'couleur_primaire' => $societe->getCouleurPrimaire(),
            'couleur_secondaire' => $societe->getCouleurSecondaire(),
            'actif' => $societe->isActif(),
            'ordre' => $societe->getOrdre(),
            'parent_id' => $societe->getParent()?->getId(),
            'parent_nom' => $societe->getParent()?->getNom(),
            'enfants_count' => $societe->getEnfants()->count(),
            'created_at' => $societe->getCreatedAt()?->format('d/m/Y H:i'),
            'updated_at' => $societe->getUpdatedAt()?->format('d/m/Y H:i')
        ]);
    }

    #[Route('/societes', name: 'app_admin_societe_create', methods: ['POST'])]
    public function createSociete(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom']) || !isset($data['code'])) {
                return $this->json(['error' => 'Nom et code obligatoires'], 400);
            }

            // Vérifier l'unicité du code
            $existingSociete = $this->entityManager->getRepository(Societe::class)
                ->findOneBy(['code' => $data['code']]);
            
            if ($existingSociete) {
                return $this->json(['error' => 'Ce code est déjà utilisé'], 400);
            }

            $societe = new Societe();
            $societe->setNom($data['nom']);
            $societe->setCode($data['code']);
            $societe->setDescription($data['description'] ?? '');
            $societe->setSiret($data['siret'] ?? '');
            $societe->setSiren($data['siren'] ?? '');
            $societe->setAdresse($data['adresse'] ?? '');
            $societe->setCodePostal($data['code_postal'] ?? '');
            $societe->setVille($data['ville'] ?? '');
            $societe->setPays($data['pays'] ?? 'France');
            $societe->setTelephone($data['telephone'] ?? '');
            $societe->setEmail($data['email'] ?? '');
            $societe->setSiteWeb($data['site_web'] ?? '');
            $societe->setCouleurPrimaire($data['couleur_primaire'] ?? '#007bff');
            $societe->setCouleurSecondaire($data['couleur_secondaire'] ?? '#6c757d');
            $societe->setActif($data['actif'] ?? true);
            
            // Gestion de la société parent
            if (isset($data['parent_id']) && !empty($data['parent_id'])) {
                $parent = $this->entityManager->find(Societe::class, $data['parent_id']);
                if ($parent) {
                    $societe->setParent($parent);
                }
            }
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $repository = $this->entityManager->getRepository(Societe::class);
                $repository->reorganizeOrdres(intval($data['ordre']));
                $societe->setOrdre(intval($data['ordre']));
            }
            
            $this->entityManager->persist($societe);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Société créée avec succès',
                'societe' => [
                    'id' => $societe->getId(),
                    'nom' => $societe->getNom(),
                    'code' => $societe->getCode(),
                    'actif' => $societe->isActif(),
                    'ordre' => $societe->getOrdre()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/societes/{id}', name: 'app_admin_societe_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateSociete(Request $request, Societe $societe): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $societe->setNom($data['nom']);
            }
            
            if (isset($data['code'])) {
                // Vérifier l'unicité du code (sauf pour la société actuelle)
                $existingSociete = $this->entityManager->getRepository(Societe::class)
                    ->createQueryBuilder('s')
                    ->where('s.code = :code')
                    ->andWhere('s.id != :id')
                    ->setParameter('code', $data['code'])
                    ->setParameter('id', $societe->getId())
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($existingSociete) {
                    return $this->json(['error' => 'Ce code est déjà utilisé'], 400);
                }
                
                $societe->setCode($data['code']);
            }
            
            // Mise à jour des autres champs
            if (isset($data['description'])) {
                $societe->setDescription($data['description']);
            }
            if (isset($data['siret'])) {
                $societe->setSiret($data['siret']);
            }
            if (isset($data['siren'])) {
                $societe->setSiren($data['siren']);
            }
            if (isset($data['adresse'])) {
                $societe->setAdresse($data['adresse']);
            }
            if (isset($data['code_postal'])) {
                $societe->setCodePostal($data['code_postal']);
            }
            if (isset($data['ville'])) {
                $societe->setVille($data['ville']);
            }
            if (isset($data['pays'])) {
                $societe->setPays($data['pays']);
            }
            if (isset($data['telephone'])) {
                $societe->setTelephone($data['telephone']);
            }
            if (isset($data['email'])) {
                $societe->setEmail($data['email']);
            }
            if (isset($data['site_web'])) {
                $societe->setSiteWeb($data['site_web']);
            }
            if (isset($data['couleur_primaire'])) {
                $societe->setCouleurPrimaire($data['couleur_primaire']);
            }
            if (isset($data['couleur_secondaire'])) {
                $societe->setCouleurSecondaire($data['couleur_secondaire']);
            }
            if (isset($data['actif'])) {
                $societe->setActif($data['actif']);
            }
            
            // Gestion de la société parent
            if (isset($data['parent_id'])) {
                if (!empty($data['parent_id'])) {
                    $parent = $this->entityManager->find(Societe::class, $data['parent_id']);
                    if ($parent && $parent->getId() !== $societe->getId()) {
                        $societe->setParent($parent);
                    }
                } else {
                    $societe->setParent(null);
                }
            }
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $repository = $this->entityManager->getRepository(Societe::class);
                $repository->reorganizeOrdres(intval($data['ordre']));
                $societe->setOrdre(intval($data['ordre']));
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Société mise à jour avec succès',
                'societe' => [
                    'id' => $societe->getId(),
                    'nom' => $societe->getNom(),
                    'code' => $societe->getCode(),
                    'actif' => $societe->isActif(),
                    'ordre' => $societe->getOrdre()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/societes/{id}/toggle', name: 'app_admin_societe_toggle', methods: ['POST'])]
    public function toggleSociete(Societe $societe): JsonResponse
    {
        try {
            $societe->setActif(!$societe->isActif());
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'actif' => $societe->isActif(),
                'message' => $societe->isActif() ? 'Société activée' : 'Société désactivée'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/societes/{id}', name: 'app_admin_societe_delete', methods: ['DELETE'])]
    public function deleteSociete(Societe $societe): JsonResponse
    {
        try {
            // Vérifier que la société n'est pas utilisée
            $usersCount = $this->entityManager->getRepository(User::class)
                ->count(['societePrincipale' => $societe]);
            
            if ($usersCount > 0) {
                return $this->json([
                    'error' => 'Cette société ne peut pas être supprimée car elle est utilisée par ' . $usersCount . ' utilisateur(s)'
                ], 400);
            }

            // Vérifier les sociétés enfants
            if ($societe->getEnfants()->count() > 0) {
                return $this->json([
                    'error' => 'Cette société ne peut pas être supprimée car elle a des sociétés enfants'
                ], 400);
            }

            $this->entityManager->remove($societe);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Société supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/societes/reorder', name: 'app_admin_societes_reorder', methods: ['POST'])]
    public function reorderSocietes(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['ordre']) || !is_array($data['ordre'])) {
                return $this->json(['error' => 'Ordre invalide'], 400);
            }

            foreach ($data['ordre'] as $index => $societeId) {
                $societe = $this->entityManager->find(Societe::class, $societeId);
                if ($societe) {
                    $societe->setOrdre($index + 1);
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Ordre des sociétés mis à jour'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la réorganisation: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // API UTILITIES
    // ================================

    #[Route('/api/societes-tree', name: 'app_admin_api_societes_tree', methods: ['GET'])]
    public function getSocietesTree(): JsonResponse
    {
        $societes = $this->entityManager->getRepository(Societe::class)
            ->findBy(['actif' => true], ['ordre' => 'ASC', 'nom' => 'ASC']);
        
        $tree = $this->buildSocietesTree($societes);
        
        return $this->json(['societes' => $tree]);
    }

    // ================================
    // SETTINGS
    // ================================

    #[Route('/settings', name: 'app_admin_settings', methods: ['GET'])]
    public function settings(): Response
    {
        // Configuration système globale
        $settings = [
            'app_name' => 'TechnoProd',
            'app_version' => '2.1',
            'maintenance_mode' => false,
            'signature_entreprise' => 'TechnoProd - Votre partenaire technologique'
        ];
        
        return $this->render('admin/societe/settings.html.twig', [
            'settings' => $settings
        ]);
    }

    #[Route('/settings/update', name: 'app_admin_settings_update', methods: ['POST'])]
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            $data = $request->request->all();
            
            // Dans un vrai système, ces paramètres seraient stockés en base
            // ou dans des fichiers de configuration
            
            return $this->json([
                'success' => true,
                'message' => 'Paramètres mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // HELPER METHODS
    // ================================

    private function buildSocietesTree(array $societes): array
    {
        $tree = [];
        $lookup = [];
        
        // Index des sociétés
        foreach ($societes as $societe) {
            $lookup[$societe->getId()] = [
                'id' => $societe->getId(),
                'nom' => $societe->getNom(),
                'code' => $societe->getCode(),
                'parent_id' => $societe->getParent()?->getId(),
                'enfants' => []
            ];
        }
        
        // Construction de l'arbre
        foreach ($lookup as $id => $item) {
            if ($item['parent_id']) {
                if (isset($lookup[$item['parent_id']])) {
                    $lookup[$item['parent_id']]['enfants'][] = &$lookup[$id];
                }
            } else {
                $tree[] = &$lookup[$id];
            }
        }
        
        return $tree;
    }
}