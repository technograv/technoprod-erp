<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\FormeJuridique;
use App\Entity\Secteur;
use App\Entity\Zone;
use App\Entity\Produit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        // Statistiques générales pour le dashboard admin
        $stats = [
            'users' => $entityManager->getRepository(User::class)->count([]),
            'formes_juridiques' => $entityManager->getRepository(FormeJuridique::class)->count([]),
            'users_actifs' => $entityManager->getRepository(User::class)->count(['isActive' => true]),
            'admins' => $entityManager->getConnection()->fetchOne(
                'SELECT COUNT(id) FROM "user" WHERE CAST(roles AS TEXT) LIKE ?',
                ['%ROLE_ADMIN%']
            ),
            'secteurs' => $entityManager->getRepository(Secteur::class)->count([]),
            'zones' => $entityManager->getRepository(Zone::class)->count([]),
            'produits' => $entityManager->getRepository(Produit::class)->count([])
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/formes-juridiques', name: 'app_admin_formes_juridiques', methods: ['GET'])]
    public function formesJuridiques(EntityManagerInterface $entityManager): Response
    {
        $formesJuridiques = $entityManager->getRepository(FormeJuridique::class)->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/formes_juridiques.html.twig', [
            'formes_juridiques' => $formesJuridiques,
        ]);
    }

    #[Route('/formes-juridiques/create', name: 'app_admin_formes_juridiques_create', methods: ['POST'])]
    public function createFormeJuridique(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validation des données
        if (empty($data['nom']) || empty($data['templateFormulaire'])) {
            return $this->json(['error' => 'Nom et template requis'], 400);
        }

        // Vérifier que le nom n'existe pas déjà
        $existing = $entityManager->getRepository(FormeJuridique::class)->findOneBy(['nom' => $data['nom']]);
        if ($existing) {
            return $this->json(['error' => 'Cette forme juridique existe déjà'], 400);
        }

        $formeJuridique = new FormeJuridique();
        $formeJuridique->setNom($data['nom']);
        $formeJuridique->setTemplateFormulaire($data['templateFormulaire']);
        $formeJuridique->setActif($data['actif'] ?? true);

        $entityManager->persist($formeJuridique);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'id' => $formeJuridique->getId(),
            'nom' => $formeJuridique->getNom(),
            'templateFormulaire' => $formeJuridique->getTemplateFormulaire(),
            'actif' => $formeJuridique->isActif()
        ]);
    }

    #[Route('/formes-juridiques/{id}/update', name: 'app_admin_formes_juridiques_update', methods: ['PUT'])]
    public function updateFormeJuridique(FormeJuridique $formeJuridique, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['nom'])) {
            $formeJuridique->setNom($data['nom']);
        }
        if (isset($data['templateFormulaire'])) {
            $formeJuridique->setTemplateFormulaire($data['templateFormulaire']);
        }
        if (isset($data['actif'])) {
            $formeJuridique->setActif($data['actif']);
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'id' => $formeJuridique->getId(),
            'nom' => $formeJuridique->getNom(),
            'templateFormulaire' => $formeJuridique->getTemplateFormulaire(),
            'actif' => $formeJuridique->isActif()
        ]);
    }

    #[Route('/formes-juridiques/{id}/delete', name: 'app_admin_formes_juridiques_delete', methods: ['DELETE'])]
    public function deleteFormeJuridique(FormeJuridique $formeJuridique, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier que la forme juridique n'est pas utilisée par des clients
        $clientsCount = $entityManager->createQuery(
            'SELECT COUNT(c.id) FROM App\Entity\Client c WHERE c.formeJuridique = :forme'
        )->setParameter('forme', $formeJuridique)->getSingleScalarResult();

        if ($clientsCount > 0) {
            return $this->json([
                'error' => "Impossible de supprimer: {$clientsCount} client(s) utilisent cette forme juridique"
            ], 400);
        }

        $entityManager->remove($formeJuridique);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(EntityManagerInterface $entityManager): Response
    {
        $users = $entityManager->getRepository(User::class)->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/{id}/toggle-active', name: 'app_admin_users_toggle_active', methods: ['POST'])]
    public function toggleUserActive(User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        $user->setIsActive(!$user->isActive());
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'isActive' => $user->isActive()
        ]);
    }

    #[Route('/users/{id}/update-roles', name: 'app_admin_users_update_roles', methods: ['PUT'])]
    public function updateUserRoles(User $user, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['roles']) || !is_array($data['roles'])) {
            return $this->json(['error' => 'Rôles invalides'], 400);
        }

        // S'assurer que ROLE_USER est toujours présent
        $roles = $data['roles'];
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }

        $user->setRoles($roles);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'roles' => $user->getRoles()
        ]);
    }

    #[Route('/settings', name: 'app_admin_settings', methods: ['GET'])]
    public function settings(): Response
    {
        // Pour l'instant, utiliser une valeur par défaut
        // Plus tard, cela pourra être stocké en base de données
        $signatureEntreprise = 'TechnoProd - Votre partenaire technologique
Tél: 01 23 45 67 89
Email: contact@technoprod.com
www.technoprod.com';
        
        return $this->render('admin/settings.html.twig', [
            'signature_entreprise' => $signatureEntreprise,
        ]);
    }

    #[Route('/settings/update', name: 'app_admin_settings_update', methods: ['POST'])]
    public function updateSettings(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Pour l'instant, on simule la sauvegarde
        // Dans une version plus avancée, cela pourrait écrire dans un fichier de config
        // ou une table de paramètres en base de données
        
        $this->addFlash('success', 'Paramètres mis à jour avec succès !');
        
        return $this->json(['success' => true]);
    }

    #[Route('/secteurs', name: 'app_admin_secteurs', methods: ['GET'])]
    public function secteurs(EntityManagerInterface $entityManager): Response
    {
        $secteurs = $entityManager->getRepository(Secteur::class)->findBy([], ['nomSecteur' => 'ASC']);

        return $this->render('admin/secteurs.html.twig', [
            'secteurs' => $secteurs,
        ]);
    }

    #[Route('/zones', name: 'app_admin_zones', methods: ['GET'])]
    public function zones(EntityManagerInterface $entityManager): Response
    {
        $zones = $entityManager->getRepository(Zone::class)->findBy([], ['ville' => 'ASC']);

        return $this->render('admin/zones.html.twig', [
            'zones' => $zones,
        ]);
    }

    #[Route('/produits', name: 'app_admin_produits', methods: ['GET'])]
    public function produits(): Response
    {
        // Pour l'instant, on redirige vers l'API existante
        // Plus tard on pourra créer une vraie interface d'administration
        return $this->render('admin/produits.html.twig');
    }
}