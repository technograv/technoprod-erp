<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Societe;
use App\Entity\FormeJuridique;
use App\Entity\Secteur;
use App\Entity\Produit;
use App\Entity\ModeReglement;
use App\Entity\ModePaiement;
use App\Entity\Banque;
use App\Entity\Tag;
use App\Entity\TauxTVA;
use App\Entity\Unite;
use App\Entity\Civilite;
use App\Entity\FraisPort;
use App\Entity\Transporteur;
use App\Entity\MethodeExpedition;
use App\Entity\ModeleDocument;
use App\Entity\DivisionAdministrative;
use App\Entity\TypeSecteur;
use App\Entity\AttributionSecteur;
use App\Entity\GroupeUtilisateur;
use App\Service\TenantService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $entityManager, TenantService $tenantService): Response
    {
        // Vérifier les permissions d'administration
        $this->denyAccessUnlessGranted('ADMIN_ACCESS');
        
        // Récupération de la société courante
        $currentSociete = $tenantService->getCurrentSociete();
        $isSocieteMere = $currentSociete ? $currentSociete->isMere() : true;
        
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
            'zones' => 0, // Zones obsolètes supprimées
            'produits' => $entityManager->getRepository(Produit::class)->count([]),
            'modes_reglement' => $entityManager->getRepository(ModeReglement::class)->count([]),
            'modes_paiement' => $entityManager->getRepository(ModePaiement::class)->count([]),
            'banques' => $entityManager->getRepository(Banque::class)->count([]),
            'tags' => $entityManager->getRepository(Tag::class)->count([]),
            'taux_tva' => $entityManager->getRepository(TauxTVA::class)->count([]),
            'unites' => $entityManager->getRepository(Unite::class)->count([]),
            'civilites' => $entityManager->getRepository(Civilite::class)->count([]),
            'frais_port' => $entityManager->getRepository(FraisPort::class)->count([]),
            'transporteurs' => $entityManager->getRepository(Transporteur::class)->count([]),
            'methodes_expedition' => $entityManager->getRepository(MethodeExpedition::class)->count([]),
            'modeles_document' => $entityManager->getRepository(ModeleDocument::class)->count([]),
            // Nouvelles entités système secteurs
            'divisions_administratives' => $entityManager->getRepository(DivisionAdministrative::class)->count(['actif' => true]),
            'types_secteur' => $entityManager->getRepository(TypeSecteur::class)->count(['actif' => true]),
            'attributions_secteur' => $entityManager->getRepository(AttributionSecteur::class)->count([]),
            // Statistiques sociétés
            'societes_meres' => $entityManager->getRepository(Societe::class)->count(['type' => 'mere']),
            'societes_filles' => $entityManager->getRepository(Societe::class)->count(['type' => 'fille']),
            // Statistiques groupes utilisateurs
            'groupes_utilisateurs' => $entityManager->getRepository(GroupeUtilisateur::class)->count([]),
            'groupes_actifs' => $entityManager->getRepository(GroupeUtilisateur::class)->count(['actif' => true]),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'google_maps_api_key' => $this->getParameter('google.maps.api.key'),
            'secteurs' => $entityManager->getRepository(Secteur::class)->findBy([], ['nomSecteur' => 'ASC']),
            'current_societe' => $currentSociete,
            'is_societe_mere' => $isSocieteMere,
        ]);
    }

    // ================================
    // FONCTIONS DEBUG TEMPORAIRES
    // ================================

    #[Route('/debug/secteurs', name: 'app_admin_debug_secteurs', methods: ['GET'])]
    public function debugSecteurs(EntityManagerInterface $entityManager): JsonResponse
    {
        $secteurs = $entityManager->getRepository(Secteur::class)->findAll();
        $data = [];
        
        foreach ($secteurs as $secteur) {
            $data[] = [
                'id' => $secteur->getId(),
                'nom' => $secteur->getNomSecteur(),
                'attributions_count' => $secteur->getAttributions()->count()
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/debug/attributions', name: 'app_admin_debug_attributions', methods: ['GET'])]
    public function debugAttributions(EntityManagerInterface $entityManager): Response
    {
        $attributions = $entityManager->getRepository(AttributionSecteur::class)->findAll();
        
        $data = [];
        foreach ($attributions as $attribution) {
            $data[] = [
                'id' => $attribution->getId(),
                'secteur' => $attribution->getSecteur()->getNomSecteur(),
                'division' => [
                    'code' => $attribution->getDivisionAdministrative()->getCode(),
                    'nom' => $attribution->getDivisionAdministrative()->getNom(),
                    'type' => $attribution->getDivisionAdministrative()->getType(),
                ]
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/debug-auth', name: 'app_admin_debug_auth', methods: ['GET'])]
    public function debugAuth(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'No user logged in']);
        }
        
        return $this->json([
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'is_admin' => in_array('ROLE_ADMIN', $user->getRoles()),
            'has_admin_access' => $this->isGranted('ADMIN_ACCESS'),
        ]);
    }
}