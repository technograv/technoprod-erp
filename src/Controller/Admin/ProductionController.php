<?php

namespace App\Controller\Admin;

use App\Entity\Production\PosteTravail;
use App\Entity\Production\Nomenclature;
use App\Entity\Production\Gamme;
use App\Repository\Production\PosteTravailRepository;
use App\Repository\Production\CategoriePosteRepository;
use App\Repository\Production\NomenclatureRepository;
use App\Repository\Production\GammeRepository;
use App\Repository\Catalogue\ProduitCatalogueRepository;
use App\Form\PosteTravailType;
use App\Form\NomenclatureType;
use App\Form\GammeType;
use App\Form\ProduitCatalogueType;
use App\Entity\Catalogue\ProduitCatalogue;
use App\Entity\Production\CategoriePoste;
use App\Entity\User;
use App\Entity\Unite;
use App\Repository\UserRepository;
use App\Repository\UniteRepository;
use App\Repository\ProduitRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin')]
#[IsGranted('ADMIN_ACCESS')]
class ProductionController extends AbstractAdminController
{
    /**
     * Gestion des catégories de postes
     */
    #[Route('/production/categories-postes', name: 'app_admin_categories_postes', methods: ['GET'])]
    public function categoriesPostes(CategoriePosteRepository $categoriePosteRepository): Response
    {
        $categories = $categoriePosteRepository->findBy([], ['ordre' => 'ASC']);

        return $this->render('admin/production/categories_postes.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Catégories de Postes - Production',
            'categories' => $categories
        ]));
    }

    /**
     * API - Créer/modifier catégorie poste
     */
    #[Route('/api/production/categories-postes', name: 'app_api_categorie_poste_save', methods: ['POST'])]
    public function saveCategoriePoste(Request $request, EntityManagerInterface $em, CategoriePosteRepository $repository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $categorie = isset($data['id']) ? $repository->find($data['id']) : new CategoriePoste();

        if (!$categorie) {
            return $this->json(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
        }

        $categorie->setCode($data['code'] ?? '');
        $categorie->setLibelle($data['libelle'] ?? '');
        $categorie->setDescription($data['description'] ?? null);
        $categorie->setIcone($data['icone'] ?? null);
        $categorie->setCouleur($data['couleur'] ?? null);
        $categorie->setActif($data['actif'] ?? true);

        if (!$categorie->getId()) {
            $em->persist($categorie);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'categorie' => [
                'id' => $categorie->getId(),
                'code' => $categorie->getCode(),
                'libelle' => $categorie->getLibelle(),
                'description' => $categorie->getDescription(),
                'icone' => $categorie->getIcone(),
                'couleur' => $categorie->getCouleur(),
                'actif' => $categorie->isActif(),
                'nb_postes' => $categorie->getPostes()->count()
            ]
        ]);
    }

    /**
     * API - Récupérer une catégorie poste
     */
    #[Route('/api/production/categories-postes/{id}', name: 'app_api_categorie_poste_get', methods: ['GET'])]
    public function getCategoriePoste(int $id, CategoriePosteRepository $repository): JsonResponse
    {
        $categorie = $repository->find($id);

        if (!$categorie) {
            return $this->json(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
        }

        return $this->json([
            'id' => $categorie->getId(),
            'code' => $categorie->getCode(),
            'libelle' => $categorie->getLibelle(),
            'description' => $categorie->getDescription(),
            'icone' => $categorie->getIcone(),
            'couleur' => $categorie->getCouleur(),
            'actif' => $categorie->isActif()
        ]);
    }

    /**
     * API - Supprimer catégorie poste
     */
    #[Route('/api/production/categories-postes/{id}', name: 'app_api_categorie_poste_delete', methods: ['DELETE'])]
    public function deleteCategoriePoste(int $id, CategoriePosteRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $categorie = $repository->find($id);

        if (!$categorie) {
            return $this->json(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
        }

        // Vérifier qu'il n'y a pas de postes liés
        if ($categorie->getPostes()->count() > 0) {
            return $this->json(['success' => false, 'message' => 'Impossible de supprimer : des postes sont liés à cette catégorie'], 400);
        }

        $em->remove($categorie);
        $em->flush();

        return $this->json(['success' => true]);
    }

    /**
     * API - Réorganiser catégories postes
     */
    #[Route('/api/production/categories-postes/reorder', name: 'app_api_categories_postes_reorder', methods: ['POST'])]
    public function reorderCategoriesPostes(Request $request, EntityManagerInterface $em, CategoriePosteRepository $repository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $order = $data['order'] ?? [];

        foreach ($order as $index => $id) {
            $categorie = $repository->find($id);
            if ($categorie) {
                $categorie->setOrdre($index);
            }
        }

        $em->flush();

        return $this->json(['success' => true]);
    }

    /**
     * Liste des postes de travail
     */
    #[Route('/production/postes-travail', name: 'app_admin_postes_travail', methods: ['GET'])]
    public function postesTravail(
        PosteTravailRepository $posteTravailRepository,
        CategoriePosteRepository $categoriePosteRepository
    ): Response
    {
        $postes = $posteTravailRepository->findAll();
        $categories = $categoriePosteRepository->findAll();

        return $this->render('admin/production/postes_travail.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Postes de Travail - Production',
            'postes' => $postes,
            'categories' => $categories
        ]));
    }

    /**
     * Création d'un nouveau poste de travail
     */
    #[Route('/production/postes-travail/new', name: 'app_admin_poste_new', methods: ['GET', 'POST'])]
    public function newPoste(Request $request, EntityManagerInterface $em): Response
    {
        $poste = new PosteTravail();
        $form = $this->createForm(PosteTravailType::class, $poste);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($poste);
            $em->flush();

            $this->addFlash('success', 'Poste de travail créé avec succès.');
            return $this->redirectToRoute('app_admin_postes_travail');
        }

        return $this->render('admin/production/poste_form.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Nouveau Poste de Travail',
            'form' => $form,
            'poste' => $poste
        ]));
    }

    /**
     * Édition d'un poste de travail
     */
    #[Route('/production/postes-travail/{id}/edit', name: 'app_admin_poste_edit', methods: ['GET', 'POST'])]
    public function editPoste(Request $request, PosteTravail $poste, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PosteTravailType::class, $poste);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Poste de travail modifié avec succès.');
            return $this->redirectToRoute('app_admin_postes_travail');
        }

        return $this->render('admin/production/poste_form.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Modifier Poste de Travail',
            'form' => $form,
            'poste' => $poste
        ]));
    }

    /**
     * API - Liste des postes de travail (AJAX)
     */
    #[Route('/api/production/postes-travail', name: 'app_api_postes_travail', methods: ['GET'])]
    public function apiPostesTravail(PosteTravailRepository $repository): JsonResponse
    {
        $postes = $repository->findAll();

        $data = array_map(function($poste) {
            return [
                'id' => $poste->getId(),
                'code' => $poste->getCode(),
                'libelle' => $poste->getLibelle(),
                'categorie' => $poste->getCategorie()->getLibelle(),
                'categorie_code' => $poste->getCategorie()->getCode(),
                'cout_horaire' => (float)$poste->getCoutHoraire(),
                'temps_setup' => $poste->getTempsSetup(),
                'temps_nettoyage' => $poste->getTempsNettoyage(),
                'polyvalent' => $poste->isPolyvalent(),
                'actif' => $poste->isActif()
            ];
        }, $postes);

        return $this->json($data);
    }

    /**
     * Toggle actif d'un poste de travail
     */
    #[Route('/api/production/postes-travail/{id}/toggle', name: 'app_api_poste_toggle', methods: ['POST'])]
    public function togglePoste(int $id, PosteTravailRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $poste = $repository->find($id);

        if (!$poste) {
            return $this->json(['success' => false, 'message' => 'Poste non trouvé'], 404);
        }

        $poste->setActif(!$poste->isActif());
        $em->flush();

        return $this->json([
            'success' => true,
            'actif' => $poste->isActif()
        ]);
    }

    /**
     * Gestion des nomenclatures
     */
    #[Route('/production/nomenclatures', name: 'app_admin_nomenclatures', methods: ['GET'])]
    public function nomenclatures(
        NomenclatureRepository $nomenclatureRepository
    ): Response
    {
        $nomenclatures = $nomenclatureRepository->findAll();

        return $this->render('admin/production/nomenclatures.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Nomenclatures (BOM) - Production',
            'nomenclatures' => $nomenclatures
        ]));
    }

    /**
     * Création d'une nouvelle nomenclature
     */
    #[Route('/production/nomenclatures/new', name: 'app_admin_nomenclature_new', methods: ['GET', 'POST'])]
    public function newNomenclature(Request $request, EntityManagerInterface $em, UniteRepository $uniteRepository): Response
    {
        $nomenclature = new Nomenclature();
        $form = $this->createForm(NomenclatureType::class, $nomenclature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($nomenclature->getLignes() as $ligne) {
                $ligne->setNomenclature($nomenclature);
            }

            $em->persist($nomenclature);
            $em->flush();

            $this->addFlash('success', 'Nomenclature créée avec succès.');
            return $this->redirectToRoute('app_admin_nomenclatures');
        }

        $unites = $uniteRepository->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/production/nomenclature_form.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Nouvelle Nomenclature',
            'unites' => $unites,
            'form' => $form,
            'nomenclature' => $nomenclature
        ]));
    }

    /**
     * Édition d'une nomenclature
     */
    #[Route('/production/nomenclatures/{id}/edit', name: 'app_admin_nomenclature_edit', methods: ['GET', 'POST'])]
    public function editNomenclature(Request $request, Nomenclature $nomenclature, EntityManagerInterface $em, UniteRepository $uniteRepository): Response
    {
        $form = $this->createForm(NomenclatureType::class, $nomenclature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($nomenclature->getLignes() as $ligne) {
                $ligne->setNomenclature($nomenclature);
            }

            $em->flush();

            $this->addFlash('success', 'Nomenclature modifiée avec succès.');
            return $this->redirectToRoute('app_admin_nomenclatures');
        }

        $unites = $uniteRepository->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/production/nomenclature_form.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Modifier Nomenclature',
            'unites' => $unites,
            'form' => $form,
            'nomenclature' => $nomenclature
        ]));
    }

    /**
     * Toggle actif d'une nomenclature
     */
    #[Route('/api/production/nomenclatures/{id}/toggle', name: 'app_api_nomenclature_toggle', methods: ['POST'])]
    public function toggleNomenclature(int $id, NomenclatureRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $nomenclature = $repository->find($id);

        if (!$nomenclature) {
            return $this->json(['success' => false, 'message' => 'Nomenclature non trouvée'], 404);
        }

        $nomenclature->setActif(!$nomenclature->isActif());
        $em->flush();

        return $this->json([
            'success' => true,
            'actif' => $nomenclature->isActif()
        ]);
    }

    /**
     * Gestion des gammes de fabrication
     */
    #[Route('/production/gammes', name: 'app_admin_gammes', methods: ['GET'])]
    public function gammes(
        GammeRepository $gammeRepository
    ): Response
    {
        $gammes = $gammeRepository->findAll();

        return $this->render('admin/production/gammes.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Gammes de Fabrication - Production',
            'gammes' => $gammes
        ]));
    }

    /**
     * Création d'une nouvelle gamme
     */
    #[Route('/production/gammes/new', name: 'app_admin_gamme_new', methods: ['GET', 'POST'])]
    public function newGamme(Request $request, EntityManagerInterface $em): Response
    {
        $gamme = new Gamme();
        $form = $this->createForm(GammeType::class, $gamme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($gamme->getOperations() as $operation) {
                $operation->setGamme($gamme);
            }

            $em->persist($gamme);
            $em->flush();

            $this->addFlash('success', 'Gamme créée avec succès.');
            return $this->redirectToRoute('app_admin_gammes');
        }

        return $this->render('admin/production/gamme_form.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Nouvelle Gamme',
            'form' => $form,
            'gamme' => $gamme
        ]));
    }

    /**
     * Édition d'une gamme
     */
    #[Route('/production/gammes/{id}/edit', name: 'app_admin_gamme_edit', methods: ['GET', 'POST'])]
    public function editGamme(Request $request, Gamme $gamme, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(GammeType::class, $gamme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($gamme->getOperations() as $operation) {
                $operation->setGamme($gamme);
            }

            $em->flush();

            $this->addFlash('success', 'Gamme modifiée avec succès.');
            return $this->redirectToRoute('app_admin_gammes');
        }

        return $this->render('admin/production/gamme_form.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Modifier Gamme',
            'form' => $form,
            'gamme' => $gamme
        ]));
    }

    /**
     * Toggle actif d'une gamme
     */
    #[Route('/api/production/gammes/{id}/toggle', name: 'app_api_gamme_toggle', methods: ['POST'])]
    public function toggleGamme(int $id, GammeRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $gamme = $repository->find($id);

        if (!$gamme) {
            return $this->json(['success' => false, 'message' => 'Gamme non trouvée'], 404);
        }

        $gamme->setActif(!$gamme->isActif());
        $em->flush();

        return $this->json([
            'success' => true,
            'actif' => $gamme->isActif()
        ]);
    }

    /**
     * Gestion des produits catalogue
     */
    #[Route('/production/produits-catalogue', name: 'app_admin_produits_catalogue', methods: ['GET'])]
    public function produitsCatalogue(
        ProduitCatalogueRepository $produitCatalogueRepository
    ): Response
    {
        $produitsCatalogue = $produitCatalogueRepository->findAll();

        return $this->render('admin/production/produits_catalogue.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Produits Catalogue - Production',
            'produitsCatalogue' => $produitsCatalogue
        ]));
    }

    /**
     * Création d'un nouveau produit catalogue
     */
    #[Route('/production/produits-catalogue/new', name: 'app_admin_produit_catalogue_new', methods: ['GET', 'POST'])]
    public function newProduitCatalogue(Request $request, EntityManagerInterface $em): Response
    {
        $produitCatalogue = new ProduitCatalogue();
        $form = $this->createForm(ProduitCatalogueType::class, $produitCatalogue);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($produitCatalogue);
            $em->flush();

            $this->addFlash('success', 'Produit catalogue créé avec succès.');
            return $this->redirectToRoute('app_admin_produits_catalogue');
        }

        return $this->render('admin/production/produit_catalogue_form.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Nouveau Produit Catalogue',
            'form' => $form,
            'produitCatalogue' => $produitCatalogue
        ]));
    }

    /**
     * Édition d'un produit catalogue
     */
    #[Route('/production/produits-catalogue/{id}/edit', name: 'app_admin_produit_catalogue_edit', methods: ['GET', 'POST'])]
    public function editProduitCatalogue(Request $request, ProduitCatalogue $produitCatalogue, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProduitCatalogueType::class, $produitCatalogue);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Produit catalogue modifié avec succès.');
            return $this->redirectToRoute('app_admin_produits_catalogue');
        }

        return $this->render('admin/production/produit_catalogue_form.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Modifier Produit Catalogue',
            'form' => $form,
            'produitCatalogue' => $produitCatalogue
        ]));
    }

    /**
     * API de recherche de produits pour autocomplétion
     */
    #[Route('/api/production/produits/search', name: 'app_api_produits_search', methods: ['GET'])]
    public function searchProduits(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');

            if (strlen($query) < 2) {
                return $this->json(['results' => []]);
            }

            // Requête SQL directe pour éviter les problèmes de lazy loading
            $sql = "SELECT id, reference, designation FROM produit
                    WHERE actif = true
                    AND (reference ILIKE :query OR designation ILIKE :query)
                    ORDER BY reference ASC
                    LIMIT 20";

            $stmt = $em->getConnection()->prepare($sql);
            $result = $stmt->executeQuery(['query' => '%' . $query . '%']);
            $produits = $result->fetchAllAssociative();

            $results = [];
            foreach ($produits as $produit) {
                $results[] = [
                    'id' => $produit['id'],
                    'text' => $produit['reference'] . ' - ' . $produit['designation'],
                    'reference' => $produit['reference'],
                    'designation' => $produit['designation']
                ];
            }

            return $this->json(['results' => $results]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => true,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'results' => []
            ], 200); // Retourner 200 pour voir l'erreur dans la console
        }
    }

    /**
     * Toggle actif d'un produit catalogue
     */
    #[Route('/api/production/produits-catalogue/{id}/toggle', name: 'app_api_produit_catalogue_toggle', methods: ['POST'])]
    public function toggleProduitCatalogue(int $id, ProduitCatalogueRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $produitCatalogue = $repository->find($id);

        if (!$produitCatalogue) {
            return $this->json(['success' => false, 'message' => 'Produit catalogue non trouvé'], 404);
        }

        $produitCatalogue->setActif(!$produitCatalogue->isActif());
        $em->flush();

        return $this->json([
            'success' => true,
            'actif' => $produitCatalogue->isActif()
        ]);
    }

    /**
     * Gestion des assignations utilisateurs-postes
     */
    #[Route('/production/user-postes', name: 'app_admin_user_postes', methods: ['GET'])]
    public function userPostes(
        UserRepository $userRepository,
        PosteTravailRepository $posteTravailRepository,
        CategoriePosteRepository $categoriePosteRepository
    ): Response
    {
        $users = $userRepository->findAll();
        $postes = $posteTravailRepository->findBy(['actif' => true], ['code' => 'ASC']);
        $categories = $categoriePosteRepository->findAll();

        // Organiser les postes par catégorie
        $postesByCategorie = [];
        foreach ($postes as $poste) {
            $categorieName = $poste->getCategorie() ? $poste->getCategorie()->getNom() : 'Sans catégorie';
            if (!isset($postesByCategorie[$categorieName])) {
                $postesByCategorie[$categorieName] = [];
            }
            $postesByCategorie[$categorieName][] = $poste;
        }

        return $this->render('admin/production/user_postes.html.twig', array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Assignation Utilisateurs-Postes',
            'users' => $users,
            'postes' => $postes,
            'postesByCategorie' => $postesByCategorie
        ]));
    }

    /**
     * API - Récupérer les postes assignés à un utilisateur
     */
    #[Route('/api/production/users/{id}/postes', name: 'app_api_user_postes_get', methods: ['GET'])]
    public function getUserPostes(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        $postesIds = $user->getPostesAssignesIds();

        return $this->json([
            'success' => true,
            'postes' => $postesIds
        ]);
    }

    /**
     * API - Mettre à jour les postes assignés à un utilisateur
     */
    #[Route('/api/production/users/{id}/postes', name: 'app_api_user_postes_update', methods: ['POST'])]
    public function updateUserPostes(
        int $id,
        Request $request,
        UserRepository $userRepository,
        PosteTravailRepository $posteTravailRepository,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $postesIds = $data['postes'] ?? [];

        // Supprimer toutes les assignations actuelles
        foreach ($user->getPostesAssignes() as $poste) {
            $user->removePosteAssigne($poste);
        }

        // Ajouter les nouvelles assignations
        foreach ($postesIds as $posteId) {
            $poste = $posteTravailRepository->find($posteId);
            if ($poste) {
                $user->addPosteAssigne($poste);
            }
        }

        $em->flush();

        // Retourner les postes avec leurs codes pour mise à jour UI
        $postesData = [];
        foreach ($user->getPostesAssignes() as $poste) {
            $postesData[] = [
                'id' => $poste->getId(),
                'code' => $poste->getCode(),
                'libelle' => $poste->getLibelle()
            ];
        }

        return $this->json([
            'success' => true,
            'postes' => $postesData
        ]);
    }

    protected function getBreadcrumb(): array
    {
        return [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Production', 'url' => null]
        ];
    }
}
