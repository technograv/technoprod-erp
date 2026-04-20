<?php

namespace App\Controller\Admin;

use App\Entity\Produit;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class CatalogController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    // ================================
    // PRODUITS
    // ================================

    #[Route('/produits', name: 'app_admin_produits', methods: ['GET'])]
    public function produits(): Response
    {
        $produits = $this->entityManager
            ->getRepository(Produit::class)
            ->findBy([], ['designation' => 'ASC']);
        
        return $this->render('admin/catalog/produits.html.twig', [
            'produits' => $produits
        ]);
    }

    // ================================
    // TAGS CLIENTS
    // ================================

    #[Route('/tags/reorder', name: 'app_admin_tags_reorder', methods: ['POST'])]
    public function reorderTags(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['tags']) || !is_array($data['tags'])) {
                return $this->json(['error' => 'Format de données invalide'], 400);
            }

            foreach ($data['tags'] as $item) {
                if (isset($item['id']) && isset($item['ordre'])) {
                    $tag = $this->entityManager->find(Tag::class, $item['id']);
                    if ($tag) {
                        $tag->setOrdre($item['ordre']);
                    }
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Ordre des tags mis à jour'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la réorganisation: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/tags', name: 'app_admin_tags', methods: ['GET'])]
    public function tags(): Response
    {
        $tags = $this->entityManager
            ->getRepository(Tag::class)
            ->findBy([], ['nom' => 'ASC']);
        
        return $this->render('admin/catalog/tags.html.twig', [
            'tags' => $tags
        ]);
    }

    #[Route('/tags-test', name: 'app_admin_tags_test', methods: ['GET'])]
    public function tagsTest(): Response
    {
        // Page de test pour les tags - utile pour débugger
        $tags = $this->entityManager
            ->getRepository(Tag::class)
            ->findAll();
        
        $tagsData = [];
        foreach ($tags as $tag) {
            $tagsData[] = [
                'id' => $tag->getId(),
                'nom' => $tag->getNom(),
                'couleur' => $tag->getCouleur(),
                'clients_count' => $tag->getClients()->count()
            ];
        }
        
        return $this->render('admin/catalog/tags_test.html.twig', [
            'tags' => $tags,
            'tags_data' => $tagsData
        ]);
    }

    #[Route('/tags/create', name: 'app_admin_tags_create', methods: ['POST'])]
    public function createTag(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom'])) {
                return $this->json(['error' => 'Le nom est obligatoire'], 400);
            }

            // Vérifier l'unicité du nom
            $existingTag = $this->entityManager->getRepository(Tag::class)
                ->findOneBy(['nom' => $data['nom']]);
            
            if ($existingTag) {
                return $this->json(['error' => 'Ce nom de tag existe déjà'], 400);
            }

            $tag = new Tag();
            $tag->setNom($data['nom']);
            $tag->setDescription($data['description'] ?? '');
            $tag->setCouleur($data['couleur'] ?? '#007bff');
            $tag->setActif($data['actif'] ?? true);
            
            $this->entityManager->persist($tag);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tag créé avec succès',
                'tag' => [
                    'id' => $tag->getId(),
                    'nom' => $tag->getNom(),
                    'couleur' => $tag->getCouleur(),
                    'actif' => $tag->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/tags/{id}/update', name: 'app_admin_tags_update', methods: ['PUT'])]
    public function updateTag(Request $request, Tag $tag): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                // Vérifier l'unicité du nom (sauf pour le tag actuel)
                $existingTag = $this->entityManager->getRepository(Tag::class)
                    ->createQueryBuilder('t')
                    ->where('t.nom = :nom')
                    ->andWhere('t.id != :id')
                    ->setParameter('nom', $data['nom'])
                    ->setParameter('id', $tag->getId())
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($existingTag) {
                    return $this->json(['error' => 'Ce nom de tag existe déjà'], 400);
                }
                
                $tag->setNom($data['nom']);
            }
            
            if (isset($data['description'])) {
                $tag->setDescription($data['description']);
            }
            
            if (isset($data['couleur'])) {
                // Validation format hexadécimal
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['couleur'])) {
                    return $this->json(['error' => 'Format de couleur invalide'], 400);
                }
                $tag->setCouleur($data['couleur']);
            }
            
            if (isset($data['actif'])) {
                $tag->setActif($data['actif']);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tag mis à jour avec succès',
                'tag' => [
                    'id' => $tag->getId(),
                    'nom' => $tag->getNom(),
                    'couleur' => $tag->getCouleur(),
                    'actif' => $tag->isActif(),
                    'clients_count' => $tag->getClients()->count()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/tags/{id}/delete', name: 'app_admin_tags_delete', methods: ['DELETE'])]
    public function deleteTag(Tag $tag): JsonResponse
    {
        try {
            // Vérifier que le tag n'est pas utilisé par des clients
            $clientsCount = $tag->getClients()->count();
            
            if ($clientsCount > 0) {
                return $this->json([
                    'error' => 'Ce tag ne peut pas être supprimé car il est utilisé par ' . $clientsCount . ' client(s)'
                ], 400);
            }

            $this->entityManager->remove($tag);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tag supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // STATISTIQUES & API
    // ================================

    #[Route('/catalog/stats', name: 'app_admin_catalog_stats', methods: ['GET'])]
    public function getCatalogStats(): JsonResponse
    {
        try {
            $stats = [
                'produits' => [
                    'total' => $this->entityManager->getRepository(Produit::class)->count([]),
                    'actifs' => $this->entityManager->getRepository(Produit::class)->count(['actif' => true])
                ],
                'tags' => [
                    'total' => $this->entityManager->getRepository(Tag::class)->count([]),
                    'actifs' => $this->entityManager->getRepository(Tag::class)->count(['actif' => true])
                ]
            ];
            
            return $this->json(['stats' => $stats]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/tags/search', name: 'app_admin_tags_search', methods: ['GET'])]
    public function searchTags(Request $request): JsonResponse
    {
        $terme = $request->query->get('q', '');
        
        if (strlen($terme) < 2) {
            return $this->json(['tags' => []]);
        }
        
        $tags = $this->entityManager
            ->getRepository(Tag::class)
            ->createQueryBuilder('t')
            ->where('t.nom LIKE :terme')
            ->andWhere('t.actif = true')
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('t.nom', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
        
        $result = [];
        foreach ($tags as $tag) {
            $result[] = [
                'id' => $tag->getId(),
                'nom' => $tag->getNom(),
                'couleur' => $tag->getCouleur(),
                'clients_count' => $tag->getClients()->count()
            ];
        }
        
        return $this->json(['tags' => $result]);
    }
}