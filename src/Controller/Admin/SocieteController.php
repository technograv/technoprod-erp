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
        
        return $this->render('admin/societes.html.twig', [
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
            'type' => $societe->isMere() ? 'mere' : 'fille',
            'parentId' => $societe->getSocieteParent()?->getId(),
            'adresse' => $societe->getAdresse(),
            'codePostal' => $societe->getCodePostal(),
            'ville' => $societe->getVille(),
            'telephone' => $societe->getTelephone(),
            'email' => $societe->getEmail(),
            'siret' => $societe->getSiret(),
            'couleurPrimaire' => $societe->getCouleurPrimaire(),
            'couleurSecondaire' => $societe->getCouleurSecondaire(),
            'active' => $societe->isActive(),
            'ordre' => $societe->getOrdre()
        ]);
    }

    #[Route('/societes', name: 'app_admin_societe_create', methods: ['POST'])]
    public function createSociete(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['nom'])) {
                return $this->json(['error' => 'Nom obligatoire'], 400);
            }

            $societe = new Societe();
            $societe->setNom($data['nom']);
            $societe->setAdresse($data['adresse'] ?? '');
            $societe->setCodePostal($data['codePostal'] ?? '');
            $societe->setVille($data['ville'] ?? '');
            $societe->setTelephone($data['telephone'] ?? '');
            $societe->setEmail($data['email'] ?? '');
            $societe->setSiret($data['siret'] ?? '');
            $societe->setCouleurPrimaire($data['couleurPrimaire'] ?? '#dc3545');
            $societe->setCouleurSecondaire($data['couleurSecondaire'] ?? '#6c757d');
            $societe->setActive($data['active'] ?? true);

            // Gestion du type et société parent
            if (isset($data['type']) && $data['type'] === 'mere') {
                $societe->setType('mere');
            } else {
                $societe->setType('fille');
                if (isset($data['societeParentId']) && !empty($data['societeParentId'])) {
                    $parent = $this->entityManager->find(Societe::class, $data['societeParentId']);
                    if ($parent) {
                        $societe->setSocieteParent($parent);
                    }
                }
            }

            // Déterminer l'ordre automatiquement
            $maxOrdre = $this->entityManager->getRepository(Societe::class)
                ->createQueryBuilder('s')
                ->select('MAX(s.ordre)')
                ->getQuery()
                ->getSingleScalarResult();

            $societe->setOrdre(($maxOrdre ?? 0) + 1);

            $this->entityManager->persist($societe);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Société créée avec succès'
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
            if (isset($data['adresse'])) {
                $societe->setAdresse($data['adresse']);
            }
            if (isset($data['codePostal'])) {
                $societe->setCodePostal($data['codePostal']);
            }
            if (isset($data['ville'])) {
                $societe->setVille($data['ville']);
            }
            if (isset($data['telephone'])) {
                $societe->setTelephone($data['telephone']);
            }
            if (isset($data['email'])) {
                $societe->setEmail($data['email']);
            }
            if (isset($data['siret'])) {
                $societe->setSiret($data['siret']);
            }
            if (isset($data['couleurPrimaire'])) {
                $societe->setCouleurPrimaire($data['couleurPrimaire']);
            }
            if (isset($data['couleurSecondaire'])) {
                $societe->setCouleurSecondaire($data['couleurSecondaire']);
            }
            if (isset($data['active'])) {
                $societe->setActive($data['active']);
            }

            // Gestion du type et société parent
            if (isset($data['type'])) {
                if ($data['type'] === 'mere') {
                    $societe->setType('mere');
                    $societe->setSocieteParent(null); // Une société mère n'a pas de parent
                } else {
                    $societe->setType('fille');
                    if (isset($data['societeParentId']) && !empty($data['societeParentId'])) {
                        $parent = $this->entityManager->find(Societe::class, $data['societeParentId']);
                        if ($parent && $parent->getId() !== $societe->getId()) {
                            $societe->setSocieteParent($parent);
                        }
                    }
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Société mise à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/societes/{id}/toggle', name: 'app_admin_societe_toggle', methods: ['POST'])]
    public function toggleSociete(Societe $societe): JsonResponse
    {
        try {
            $societe->setActive(!$societe->isActive());
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'isActive' => $societe->isActive(),
                'message' => $societe->isActive() ? 'Société activée' : 'Société désactivée'
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

            if (!isset($data['societes']) || !is_array($data['societes'])) {
                return $this->json(['error' => 'Format de données invalide'], 400);
            }

            foreach ($data['societes'] as $item) {
                if (isset($item['id']) && isset($item['ordre'])) {
                    $societe = $this->entityManager->find(Societe::class, $item['id']);
                    if ($societe) {
                        $societe->setOrdre($item['ordre']);
                    }
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
            ->findBy(['active' => true], ['ordre' => 'ASC', 'nom' => 'ASC']);
        
        $tree = $this->buildSocietesTree($societes);
        
        return $this->json($tree);
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
                'display_name' => $societe->getNom(),
                'type' => $societe->getType(),
                'parent_id' => $societe->getSocieteParent()?->getId(),
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