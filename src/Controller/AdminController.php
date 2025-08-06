<?php

namespace App\Controller;

use App\Entity\User;
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
use App\Entity\PalierFraisPort;
use App\Entity\Transporteur;
use App\Entity\MethodeExpedition;
use App\Entity\ModeleDocument;
use App\Entity\DivisionAdministrative;
use App\Entity\TypeSecteur;
use App\Entity\AttributionSecteur;
use App\Entity\ExclusionSecteur;
use App\Service\DocumentNumerotationService;
use App\Service\EpciBoundariesService;
use App\Service\CommuneGeometryService;
use App\Service\GeographicBoundariesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

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
            'attributions_secteur' => $entityManager->getRepository(AttributionSecteur::class)->count([])
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'google_maps_api_key' => $this->getParameter('google.maps.api.key'),
            'secteurs' => $entityManager->getRepository(Secteur::class)->findBy([], ['nomSecteur' => 'ASC']),
        ]);
    }

    #[Route('/formes-juridiques', name: 'app_admin_formes_juridiques', methods: ['GET'])]
    public function formesJuridiques(EntityManagerInterface $entityManager): Response
    {
        $formesJuridiques = $entityManager->getRepository(FormeJuridique::class)->findBy([], ['ordre' => 'ASC']);

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

        // Si forme par défaut demandée, désactiver les autres
        if (!empty($data['formeParDefaut'])) {
            $entityManager->createQuery('UPDATE App\Entity\FormeJuridique f SET f.formeParDefaut = false')->execute();
        }

        $formeJuridique = new FormeJuridique();
        $formeJuridique->setNom($data['nom']);
        $formeJuridique->setTemplateFormulaire($data['templateFormulaire']);
        $formeJuridique->setActif($data['actif'] ?? true);
        $formeJuridique->setFormeParDefaut($data['formeParDefaut'] ?? false);
        // Gestion intelligente de l'ordre pour une nouvelle forme juridique
        if (isset($data['ordre']) && $data['ordre'] > 0) {
            // Si un ordre spécifique est demandé, l'assigner temporairement
            $formeJuridique->setOrdre($data['ordre']);
            $entityManager->persist($formeJuridique);
            $entityManager->flush();
            
            // Puis réorganiser tous les ordres
            $repository = $entityManager->getRepository(FormeJuridique::class);
            $repository->reorganizeOrdres($formeJuridique, $data['ordre']);
        } else {
            // Si pas d'ordre spécifié, prendre le prochain ordre disponible
            $maxOrdre = $entityManager->createQuery('SELECT MAX(f.ordre) FROM App\Entity\FormeJuridique f')
                ->getSingleScalarResult();
            $formeJuridique->setOrdre(($maxOrdre ?? 0) + 1);
            
            $entityManager->persist($formeJuridique);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $formeJuridique->getId(),
            'nom' => $formeJuridique->getNom(),
            'templateFormulaire' => $formeJuridique->getTemplateFormulaire(),
            'actif' => $formeJuridique->isActif(),
            'formeParDefaut' => $formeJuridique->isFormeParDefaut(),
            'ordre' => $formeJuridique->getOrdre()
        ]);
    }

    #[Route('/formes-juridiques/{id}/update', name: 'app_admin_formes_juridiques_update', methods: ['PUT'])]
    public function updateFormeJuridique(FormeJuridique $formeJuridique, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Si forme par défaut demandée, désactiver les autres d'abord
        if (isset($data['formeParDefaut']) && $data['formeParDefaut']) {
            $entityManager->createQuery('UPDATE App\Entity\FormeJuridique f SET f.formeParDefaut = false WHERE f.id != :id')
                ->setParameter('id', $formeJuridique->getId())
                ->execute();
        }

        if (isset($data['nom'])) {
            $formeJuridique->setNom($data['nom']);
        }
        if (isset($data['templateFormulaire'])) {
            $formeJuridique->setTemplateFormulaire($data['templateFormulaire']);
        }
        if (isset($data['actif'])) {
            $formeJuridique->setActif($data['actif']);
        }
        if (isset($data['formeParDefaut'])) {
            $formeJuridique->setFormeParDefaut($data['formeParDefaut']);
        }
        
        // Gestion intelligente de l'ordre avec réorganisation automatique
        if (isset($data['ordre'])) {
            $newOrdre = (int)$data['ordre'];
            
            // Utiliser la méthode de réorganisation du repository
            $repository = $entityManager->getRepository(FormeJuridique::class);
            $repository->reorganizeOrdres($formeJuridique, $newOrdre);
        } else {
            // Si pas de changement d'ordre, flush normal
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $formeJuridique->getId(),
            'nom' => $formeJuridique->getNom(),
            'templateFormulaire' => $formeJuridique->getTemplateFormulaire(),
            'actif' => $formeJuridique->isActif(),
            'formeParDefaut' => $formeJuridique->isFormeParDefaut(),
            'ordre' => $formeJuridique->getOrdre()
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

    // Route /secteurs supprimée - utiliser l'onglet Secteurs du panneau d'administration


    #[Route('/produits', name: 'app_admin_produits', methods: ['GET'])]
    public function produits(): Response
    {
        // Pour l'instant, on redirige vers l'API existante
        // Plus tard on pourra créer une vraie interface d'administration
        return $this->render('admin/produits.html.twig');
    }

    #[Route('/numerotation', name: 'app_admin_numerotation', methods: ['GET'])]
    public function numerotation(DocumentNumerotationService $numerotationService): Response
    {
        $numerotations = $numerotationService->getToutesLesNumerotations();

        return $this->render('admin/numerotation.html.twig', [
            'numerotations' => $numerotations,
        ]);
    }

    #[Route('/numerotation/{prefixe}/update', name: 'app_admin_numerotation_update', methods: ['POST'])]
    public function updateNumerotation(string $prefixe, Request $request, DocumentNumerotationService $numerotationService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $nouveauCompteur = (int) $data['compteur'];

        if ($nouveauCompteur < 1) {
            return $this->json([
                'success' => false,
                'error' => 'Le compteur doit être supérieur ou égal à 1'
            ], 400);
        }

        try {
            $numerotationService->setCompteur($prefixe, $nouveauCompteur);
            
            $prochainNumero = $numerotationService->previewProchainNumero($prefixe);

            return $this->json([
                'success' => true,
                'message' => 'Compteur mis à jour avec succès',
                'compteur' => $nouveauCompteur,
                'prochain_numero' => $prochainNumero
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }


    // =====================================================
    // GESTION DES MODES DE RÈGLEMENT
    // =====================================================

    #[Route('/modes-reglement', name: 'app_admin_modes_reglement', methods: ['GET'])]
    public function modesReglement(EntityManagerInterface $entityManager): Response
    {
        $modes = $entityManager->getRepository(ModeReglement::class)->findAllWithModePaiement();
        $modesPaiement = $entityManager->getRepository(ModePaiement::class)->findActive();

        return $this->render('admin/modes_reglement.html.twig', [
            'modes_reglement' => $modes,
            'modes_paiement' => $modesPaiement,
        ]);
    }

    #[Route('/modes-reglement/create', name: 'app_admin_modes_reglement_create', methods: ['POST'])]
    public function createModeReglement(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['code']) || empty($data['nom']) || empty($data['typeReglement']) || empty($data['modePaiementId'])) {
            return $this->json(['error' => 'Code, nom, type de règlement et mode de paiement requis'], 400);
        }

        // Vérifier l'unicité du code
        $existingMode = $entityManager->getRepository(ModeReglement::class)->findByCode($data['code']);
        if ($existingMode) {
            return $this->json(['error' => 'Ce code existe déjà'], 400);
        }

        // Validation du jour de règlement
        if (isset($data['jourReglement']) && ($data['jourReglement'] < 1 || $data['jourReglement'] > 31)) {
            return $this->json(['error' => 'Le jour de règlement doit être entre 1 et 31'], 400);
        }

        // Récupérer le mode de paiement
        $modePaiement = $entityManager->getRepository(ModePaiement::class)->find($data['modePaiementId']);
        if (!$modePaiement) {
            return $this->json(['error' => 'Mode de paiement introuvable'], 400);
        }

        if (!empty($data['modeParDefaut'])) {
            $entityManager->createQuery('UPDATE App\Entity\ModeReglement m SET m.modeParDefaut = false')->execute();
        }

        $mode = new ModeReglement();
        $mode->setCode($data['code']);
        $mode->setNom($data['nom']);
        $mode->setNombreJours($data['nombreJours'] ?? null);
        $mode->setTypeReglement($data['typeReglement']);
        $mode->setJourReglement($data['jourReglement'] ?? null);
        $mode->setModePaiement($modePaiement);
        $mode->setNote($data['note'] ?? null);
        $mode->setActif($data['actif'] ?? true);
        $mode->setModeParDefaut($data['modeParDefaut'] ?? false);
        
        if (isset($data['ordre']) && $data['ordre'] > 0) {
            $mode->setOrdre($data['ordre']);
            $entityManager->persist($mode);
            $entityManager->flush();
            
            $repository = $entityManager->getRepository(ModeReglement::class);
            $repository->reorganizeOrdres($mode, $data['ordre']);
        } else {
            $maxOrdre = $entityManager->createQuery('SELECT MAX(m.ordre) FROM App\Entity\ModeReglement m')
                ->getSingleScalarResult();
            $mode->setOrdre(($maxOrdre ?? 0) + 1);
            
            $entityManager->persist($mode);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $mode->getId(),
            'code' => $mode->getCode(),
            'nom' => $mode->getNom(),
            'nombreJours' => $mode->getNombreJours(),
            'typeReglement' => $mode->getTypeReglement(),
            'jourReglement' => $mode->getJourReglement(),
            'modePaiementId' => $mode->getModePaiement()->getId(),
            'modePaiementNom' => $mode->getModePaiement()->getNom(),
            'note' => $mode->getNote(),
            'actif' => $mode->isActif(),
            'modeParDefaut' => $mode->isModeParDefaut(),
            'ordre' => $mode->getOrdre()
        ]);
    }

    #[Route('/modes-reglement/{id}/update', name: 'app_admin_modes_reglement_update', methods: ['PUT'])]
    public function updateModeReglement(ModeReglement $mode, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation du jour de règlement
        if (isset($data['jourReglement']) && $data['jourReglement'] !== null && ($data['jourReglement'] < 1 || $data['jourReglement'] > 31)) {
            return $this->json(['error' => 'Le jour de règlement doit être entre 1 et 31'], 400);
        }

        if (isset($data['modeParDefaut']) && $data['modeParDefaut']) {
            $entityManager->createQuery('UPDATE App\Entity\ModeReglement m SET m.modeParDefaut = false WHERE m.id != :id')
                ->setParameter('id', $mode->getId())
                ->execute();
        }

        if (isset($data['code'])) $mode->setCode($data['code']);
        if (isset($data['nom'])) $mode->setNom($data['nom']);
        if (isset($data['nombreJours'])) $mode->setNombreJours($data['nombreJours']);
        if (isset($data['typeReglement'])) $mode->setTypeReglement($data['typeReglement']);
        if (isset($data['jourReglement'])) $mode->setJourReglement($data['jourReglement']);
        if (isset($data['note'])) $mode->setNote($data['note']);
        if (isset($data['actif'])) $mode->setActif($data['actif']);
        if (isset($data['modeParDefaut'])) $mode->setModeParDefaut($data['modeParDefaut']);
        
        // Gestion du changement de mode de paiement
        if (isset($data['modePaiementId'])) {
            $modePaiement = $entityManager->getRepository(ModePaiement::class)->find($data['modePaiementId']);
            if (!$modePaiement) {
                return $this->json(['error' => 'Mode de paiement introuvable'], 400);
            }
            $mode->setModePaiement($modePaiement);
        }
        
        if (isset($data['ordre'])) {
            $repository = $entityManager->getRepository(ModeReglement::class);
            $repository->reorganizeOrdres($mode, (int)$data['ordre']);
        } else {
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $mode->getId(),
            'code' => $mode->getCode(),
            'nom' => $mode->getNom(),
            'nombreJours' => $mode->getNombreJours(),
            'typeReglement' => $mode->getTypeReglement(),
            'jourReglement' => $mode->getJourReglement(),
            'modePaiementId' => $mode->getModePaiement()->getId(),
            'modePaiementNom' => $mode->getModePaiement()->getNom(),
            'note' => $mode->getNote(),
            'actif' => $mode->isActif(),
            'modeParDefaut' => $mode->isModeParDefaut(),
            'ordre' => $mode->getOrdre()
        ]);
    }

    #[Route('/modes-reglement/{id}/delete', name: 'app_admin_modes_reglement_delete', methods: ['DELETE'])]
    public function deleteModeReglement(ModeReglement $mode, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $entityManager->remove($mode);
            $entityManager->flush();
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // =====================================================
    // GESTION DES MÉTHODES D'EXPÉDITION
    // =====================================================

    #[Route('/methodes-expedition', name: 'app_admin_methodes_expedition', methods: ['GET'])]
    public function methodesExpedition(EntityManagerInterface $entityManager): Response
    {
        $methodes = $entityManager->getRepository(MethodeExpedition::class)->findBy([], ['ordre' => 'ASC']);

        return $this->render('admin/methodes_expedition.html.twig', [
            'methodes_expedition' => $methodes,
        ]);
    }

    #[Route('/methodes-expedition/create', name: 'app_admin_methodes_expedition_create', methods: ['POST'])]
    public function createMethodeExpedition(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['nom'])) {
            return $this->json(['error' => 'Nom requis'], 400);
        }

        if (!empty($data['methodeParDefaut'])) {
            $entityManager->createQuery('UPDATE App\Entity\MethodeExpedition m SET m.methodeParDefaut = false')->execute();
        }

        $methode = new MethodeExpedition();
        $methode->setNom($data['nom']);
        $methode->setDescription($data['description'] ?? '');
        $methode->setTarifBase($data['tarifBase'] ?? null);
        $methode->setDelaiMoyen($data['delaiMoyen'] ?? null);
        $methode->setActif($data['actif'] ?? true);
        $methode->setMethodeParDefaut($data['methodeParDefaut'] ?? false);
        
        if (isset($data['ordre']) && $data['ordre'] > 0) {
            $methode->setOrdre($data['ordre']);
            $entityManager->persist($methode);
            $entityManager->flush();
            
            $repository = $entityManager->getRepository(MethodeExpedition::class);
            $repository->reorganizeOrdres($methode, $data['ordre']);
        } else {
            $maxOrdre = $entityManager->createQuery('SELECT MAX(m.ordre) FROM App\Entity\MethodeExpedition m')
                ->getSingleScalarResult();
            $methode->setOrdre(($maxOrdre ?? 0) + 1);
            
            $entityManager->persist($methode);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $methode->getId(),
            'nom' => $methode->getNom(),
            'description' => $methode->getDescription(),
            'tarifBase' => $methode->getTarifBase(),
            'delaiMoyen' => $methode->getDelaiMoyen(),
            'actif' => $methode->isActif(),
            'methodeParDefaut' => $methode->isMethodeParDefaut(),
            'ordre' => $methode->getOrdre()
        ]);
    }

    #[Route('/methodes-expedition/{id}/update', name: 'app_admin_methodes_expedition_update', methods: ['PUT'])]
    public function updateMethodeExpedition(MethodeExpedition $methode, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['methodeParDefaut']) && $data['methodeParDefaut']) {
            $entityManager->createQuery('UPDATE App\Entity\MethodeExpedition m SET m.methodeParDefaut = false WHERE m.id != :id')
                ->setParameter('id', $methode->getId())
                ->execute();
        }

        if (isset($data['nom'])) $methode->setNom($data['nom']);
        if (isset($data['description'])) $methode->setDescription($data['description']);
        if (isset($data['tarifBase'])) $methode->setTarifBase($data['tarifBase']);
        if (isset($data['delaiMoyen'])) $methode->setDelaiMoyen($data['delaiMoyen']);
        if (isset($data['actif'])) $methode->setActif($data['actif']);
        if (isset($data['methodeParDefaut'])) $methode->setMethodeParDefaut($data['methodeParDefaut']);
        
        if (isset($data['ordre'])) {
            $repository = $entityManager->getRepository(MethodeExpedition::class);
            $repository->reorganizeOrdres($methode, (int)$data['ordre']);
        } else {
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $methode->getId(),
            'nom' => $methode->getNom(),
            'description' => $methode->getDescription(),
            'tarifBase' => $methode->getTarifBase(),
            'delaiMoyen' => $methode->getDelaiMoyen(),
            'actif' => $methode->isActif(),
            'methodeParDefaut' => $methode->isMethodeParDefaut(),
            'ordre' => $methode->getOrdre()
        ]);
    }

    #[Route('/methodes-expedition/{id}/delete', name: 'app_admin_methodes_expedition_delete', methods: ['DELETE'])]
    public function deleteMethodeExpedition(MethodeExpedition $methode, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $entityManager->remove($methode);
            $entityManager->flush();
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // =====================================================
    // GESTION DES MODÈLES DE DOCUMENT
    // =====================================================

    #[Route('/modeles-document', name: 'app_admin_modeles_document', methods: ['GET'])]
    public function modelesDocument(EntityManagerInterface $entityManager): Response
    {
        $modeles = $entityManager->getRepository(ModeleDocument::class)->findBy([], ['ordre' => 'ASC']);

        return $this->render('admin/modeles_document.html.twig', [
            'modeles_document' => $modeles,
        ]);
    }

    #[Route('/modeles-document/create', name: 'app_admin_modeles_document_create', methods: ['POST'])]
    public function createModeleDocument(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['nom']) || empty($data['typeDocument'])) {
            return $this->json(['error' => 'Nom et type de document requis'], 400);
        }

        if (!empty($data['modeleParDefaut'])) {
            $entityManager->createQuery('UPDATE App\Entity\ModeleDocument m SET m.modeleParDefaut = false WHERE m.typeDocument = :type')
                ->setParameter('type', $data['typeDocument'])
                ->execute();
        }

        $modele = new ModeleDocument();
        $modele->setNom($data['nom']);
        $modele->setDescription($data['description'] ?? '');
        $modele->setTypeDocument($data['typeDocument']);
        $modele->setTemplateFile($data['templateFile'] ?? null);
        $modele->setCss($data['css'] ?? null);
        $modele->setActif($data['actif'] ?? true);
        $modele->setModeleParDefaut($data['modeleParDefaut'] ?? false);
        
        if (isset($data['ordre']) && $data['ordre'] > 0) {
            $modele->setOrdre($data['ordre']);
            $entityManager->persist($modele);
            $entityManager->flush();
            
            $repository = $entityManager->getRepository(ModeleDocument::class);
            $repository->reorganizeOrdres($modele, $data['ordre']);
        } else {
            $maxOrdre = $entityManager->createQuery('SELECT MAX(m.ordre) FROM App\Entity\ModeleDocument m')
                ->getSingleScalarResult();
            $modele->setOrdre(($maxOrdre ?? 0) + 1);
            
            $entityManager->persist($modele);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $modele->getId(),
            'nom' => $modele->getNom(),
            'description' => $modele->getDescription(),
            'typeDocument' => $modele->getTypeDocument(),
            'templateFile' => $modele->getTemplateFile(),
            'css' => $modele->getCss(),
            'actif' => $modele->isActif(),
            'modeleParDefaut' => $modele->isModeleParDefaut(),
            'ordre' => $modele->getOrdre()
        ]);
    }

    #[Route('/modeles-document/{id}/update', name: 'app_admin_modeles_document_update', methods: ['PUT'])]
    public function updateModeleDocument(ModeleDocument $modele, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['modeleParDefaut']) && $data['modeleParDefaut']) {
            $entityManager->createQuery('UPDATE App\Entity\ModeleDocument m SET m.modeleParDefaut = false WHERE m.typeDocument = :type AND m.id != :id')
                ->setParameter('type', $modele->getTypeDocument())
                ->setParameter('id', $modele->getId())
                ->execute();
        }

        if (isset($data['nom'])) $modele->setNom($data['nom']);
        if (isset($data['description'])) $modele->setDescription($data['description']);
        if (isset($data['typeDocument'])) $modele->setTypeDocument($data['typeDocument']);
        if (isset($data['templateFile'])) $modele->setTemplateFile($data['templateFile']);
        if (isset($data['css'])) $modele->setCss($data['css']);
        if (isset($data['actif'])) $modele->setActif($data['actif']);
        if (isset($data['modeleParDefaut'])) $modele->setModeleParDefaut($data['modeleParDefaut']);
        
        if (isset($data['ordre'])) {
            $repository = $entityManager->getRepository(ModeleDocument::class);
            $repository->reorganizeOrdres($modele, (int)$data['ordre']);
        } else {
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $modele->getId(),
            'nom' => $modele->getNom(),
            'description' => $modele->getDescription(),
            'typeDocument' => $modele->getTypeDocument(),
            'templateFile' => $modele->getTemplateFile(),
            'css' => $modele->getCss(),
            'actif' => $modele->isActif(),
            'modeleParDefaut' => $modele->isModeleParDefaut(),
            'ordre' => $modele->getOrdre()
        ]);
    }

    #[Route('/modeles-document/{id}/delete', name: 'app_admin_modeles_document_delete', methods: ['DELETE'])]
    public function deleteModeleDocument(ModeleDocument $modele, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $entityManager->remove($modele);
            $entityManager->flush();
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // =====================================================
    // GESTION DES MODES DE PAIEMENT
    // =====================================================

    #[Route('/modes-paiement', name: 'app_admin_modes_paiement', methods: ['GET'])]
    public function modesPaiement(EntityManagerInterface $entityManager): Response
    {
        $modes = $entityManager->getRepository(ModePaiement::class)->findBy([], ['ordre' => 'ASC']);
        $banques = $entityManager->getRepository(Banque::class)->findBy(['actif' => true], ['ordre' => 'ASC']);

        return $this->render('admin/modes_paiement.html.twig', [
            'modes_paiement' => $modes,
            'banques' => $banques,
        ]);
    }

    #[Route('/modes-paiement/create', name: 'app_admin_modes_paiement_create', methods: ['POST'])]
    public function createModePaiement(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['code']) || empty($data['nom'])) {
            return $this->json(['error' => 'Code et nom requis'], 400);
        }

        // Vérifier l'unicité du code
        $existingMode = $entityManager->getRepository(ModePaiement::class)->findByCode($data['code']);
        if ($existingMode) {
            return $this->json(['error' => 'Ce code existe déjà'], 400);
        }

        if (!empty($data['modePaiementParDefaut'])) {
            $entityManager->createQuery('UPDATE App\Entity\ModePaiement m SET m.modePaiementParDefaut = false')->execute();
        }

        $modePaiement = new ModePaiement();
        $modePaiement->setCode($data['code']);
        $modePaiement->setNom($data['nom']);
        $modePaiement->setNature($data['nature'] ?? null);
        $modePaiement->setNote($data['note'] ?? null);
        $modePaiement->setActif($data['actif'] ?? true);
        $modePaiement->setModePaiementParDefaut($data['modePaiementParDefaut'] ?? false);
        $modePaiement->setRemettreEnBanque($data['remettreEnBanque'] ?? false);
        $modePaiement->setCodeJournalRemise($data['codeJournalRemise'] ?? null);
        $modePaiement->setCompteRemise($data['compteRemise'] ?? null);
        
        // Gérer la relation avec la banque
        if (!empty($data['banqueParDefaut'])) {
            $banque = $entityManager->getRepository(Banque::class)->find($data['banqueParDefaut']);
            if ($banque) {
                $modePaiement->setBanqueParDefaut($banque);
            }
        }
        
        if (isset($data['ordre']) && $data['ordre'] > 0) {
            $entityManager->persist($modePaiement);
            
            $repository = $entityManager->getRepository(ModePaiement::class);
            $repository->reorganizeOrdres($modePaiement, (int)$data['ordre']);
        } else {
            $maxOrdre = $entityManager->createQuery('SELECT MAX(m.ordre) FROM App\Entity\ModePaiement m')
                ->getSingleScalarResult();
            $modePaiement->setOrdre(($maxOrdre ?? 0) + 1);
            
            $entityManager->persist($modePaiement);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $modePaiement->getId(),
            'code' => $modePaiement->getCode(),
            'nom' => $modePaiement->getNom(),
            'nature' => $modePaiement->getNature(),
            'banqueParDefaut' => $modePaiement->getBanqueParDefaut()?->getNom(),
            'remettreEnBanque' => $modePaiement->isRemettreEnBanque(),
            'codeJournalRemise' => $modePaiement->getCodeJournalRemise(),
            'compteRemise' => $modePaiement->getCompteRemise(),
            'note' => $modePaiement->getNote(),
            'actif' => $modePaiement->isActif(),
            'modePaiementParDefaut' => $modePaiement->isModePaiementParDefaut(),
            'ordre' => $modePaiement->getOrdre()
        ]);
    }

    #[Route('/modes-paiement/{id}/update', name: 'app_admin_modes_paiement_update', methods: ['PUT'])]
    public function updateModePaiement(ModePaiement $modePaiement, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['modePaiementParDefaut']) && $data['modePaiementParDefaut']) {
            $entityManager->createQuery('UPDATE App\Entity\ModePaiement m SET m.modePaiementParDefaut = false WHERE m.id != :id')
                ->setParameter('id', $modePaiement->getId())
                ->execute();
        }

        if (isset($data['code'])) $modePaiement->setCode($data['code']);
        if (isset($data['nom'])) $modePaiement->setNom($data['nom']);
        if (isset($data['nature'])) $modePaiement->setNature($data['nature']);
        if (isset($data['note'])) $modePaiement->setNote($data['note']);
        if (isset($data['actif'])) $modePaiement->setActif($data['actif']);
        if (isset($data['modePaiementParDefaut'])) $modePaiement->setModePaiementParDefaut($data['modePaiementParDefaut']);
        if (isset($data['remettreEnBanque'])) $modePaiement->setRemettreEnBanque($data['remettreEnBanque']);
        if (isset($data['codeJournalRemise'])) $modePaiement->setCodeJournalRemise($data['codeJournalRemise']);
        if (isset($data['compteRemise'])) $modePaiement->setCompteRemise($data['compteRemise']);
        
        // Gérer la relation avec la banque
        if (isset($data['banqueParDefaut'])) {
            if (!empty($data['banqueParDefaut'])) {
                $banque = $entityManager->getRepository(Banque::class)->find($data['banqueParDefaut']);
                $modePaiement->setBanqueParDefaut($banque);
            } else {
                $modePaiement->setBanqueParDefaut(null);
            }
        }
        
        // Toujours faire un flush pour sauvegarder toutes les modifications
        $entityManager->flush();
        
        // Puis réorganiser les ordres si nécessaire
        if (isset($data['ordre'])) {
            $repository = $entityManager->getRepository(ModePaiement::class);
            $repository->reorganizeOrdres($modePaiement, (int)$data['ordre']);
        }

        return $this->json([
            'success' => true,
            'id' => $modePaiement->getId(),
            'code' => $modePaiement->getCode(),
            'nom' => $modePaiement->getNom(),
            'nature' => $modePaiement->getNature(),
            'banqueParDefaut' => $modePaiement->getBanqueParDefaut() ? $modePaiement->getBanqueParDefaut()->getNom() : null,
            'banqueParDefautId' => $modePaiement->getBanqueParDefaut() ? $modePaiement->getBanqueParDefaut()->getId() : null,
            'remettreEnBanque' => $modePaiement->isRemettreEnBanque(),
            'codeJournalRemise' => $modePaiement->getCodeJournalRemise(),
            'compteRemise' => $modePaiement->getCompteRemise(),
            'note' => $modePaiement->getNote(),
            'actif' => $modePaiement->isActif(),
            'modePaiementParDefaut' => $modePaiement->isModePaiementParDefaut(),
            'ordre' => $modePaiement->getOrdre(),
        ]);
    }

    #[Route('/modes-paiement/{id}/delete', name: 'app_admin_modes_paiement_delete', methods: ['DELETE'])]
    public function deleteModePaiement(ModePaiement $modePaiement, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier si le mode de paiement est utilisé par des modes de règlement
        $usageCount = $entityManager->createQuery('SELECT COUNT(mr) FROM App\Entity\ModeReglement mr WHERE mr.modePaiement = :modePaiement')
            ->setParameter('modePaiement', $modePaiement)
            ->getSingleScalarResult();

        if ($usageCount > 0) {
            return $this->json(['success' => false, 'error' => 'Impossible de supprimer: ce mode de paiement est utilisé par ' . $usageCount . ' mode(s) de règlement'], 400);
        }

        try {
            $entityManager->remove($modePaiement);
            $entityManager->flush();
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // =====================================================
    // GESTION DES BANQUES
    // =====================================================

    #[Route('/banques', name: 'app_admin_banques', methods: ['GET'])]
    public function banques(EntityManagerInterface $entityManager): Response
    {
        $banques = $entityManager->getRepository(Banque::class)->findBy([], ['ordre' => 'ASC']);

        return $this->render('admin/banques.html.twig', [
            'banques' => $banques,
        ]);
    }

    #[Route('/banques/create', name: 'app_admin_banques_create', methods: ['POST'])]
    public function createBanque(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['code']) || empty($data['nom'])) {
            return $this->json(['error' => 'Code et nom requis'], 400);
        }

        // Vérifier l'unicité du code
        $existingBanque = $entityManager->getRepository(Banque::class)->findOneBy(['code' => $data['code']]);
        if ($existingBanque) {
            return $this->json(['error' => 'Ce code existe déjà'], 400);
        }

        $banque = new Banque();
        $banque->setCode($data['code']);
        $banque->setNom($data['nom']);
        $banque->setAdresse($data['adresse'] ?? null);
        $banque->setCodePostal($data['codePostal'] ?? null);
        $banque->setVille($data['ville'] ?? null);
        $banque->setPays($data['pays'] ?? 'France');
        $banque->setTelephone($data['telephone'] ?? null);
        $banque->setFax($data['fax'] ?? null);
        $banque->setEmail($data['email'] ?? null);
        $banque->setSiteWeb($data['siteWeb'] ?? null);
        $banque->setCodeJournal($data['codeJournal'] ?? null);
        $banque->setCompteComptable($data['compteComptable'] ?? null);
        $banque->setCodeJournalRemise($data['codeJournalRemise'] ?? null);
        $banque->setComptePaiementsEncaisser($data['comptePaiementsEncaisser'] ?? null);
        $banque->setRibBban($data['ribBban'] ?? null);
        $banque->setIban($data['iban'] ?? null);
        $banque->setBic($data['bic'] ?? null);
        $banque->setNumeroNationalEmetteur($data['numeroNationalEmetteur'] ?? null);
        $banque->setIdentifiantCreancierSepa($data['identifiantCreancierSepa'] ?? null);
        $banque->setNotes($data['notes'] ?? null);
        $banque->setActif($data['actif'] ?? true);
        
        if (isset($data['ordre']) && $data['ordre'] > 0) {
            $banque->setOrdre($data['ordre']);
            $entityManager->persist($banque);
            $entityManager->flush();
            
            $repository = $entityManager->getRepository(Banque::class);
            $repository->reorganizeOrdres($banque, $data['ordre']);
        } else {
            $maxOrdre = $entityManager->createQuery('SELECT MAX(b.ordre) FROM App\Entity\Banque b')
                ->getSingleScalarResult();
            $banque->setOrdre(($maxOrdre ?? 0) + 1);
            
            $entityManager->persist($banque);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $banque->getId(),
            'code' => $banque->getCode(),
            'nom' => $banque->getNom(),
            'actif' => $banque->isActif(),
            'ordre' => $banque->getOrdre()
        ]);
    }

    #[Route('/banques/{id}/update', name: 'app_admin_banques_update', methods: ['PUT'])]
    public function updateBanque(Banque $banque, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['code'])) $banque->setCode($data['code']);
        if (isset($data['nom'])) $banque->setNom($data['nom']);
        if (isset($data['adresse'])) $banque->setAdresse($data['adresse']);
        if (isset($data['codePostal'])) $banque->setCodePostal($data['codePostal']);
        if (isset($data['ville'])) $banque->setVille($data['ville']);
        if (isset($data['pays'])) $banque->setPays($data['pays']);
        if (isset($data['telephone'])) $banque->setTelephone($data['telephone']);
        if (isset($data['fax'])) $banque->setFax($data['fax']);
        if (isset($data['email'])) $banque->setEmail($data['email']);
        if (isset($data['siteWeb'])) $banque->setSiteWeb($data['siteWeb']);
        if (isset($data['codeJournal'])) $banque->setCodeJournal($data['codeJournal']);
        if (isset($data['compteComptable'])) $banque->setCompteComptable($data['compteComptable']);
        if (isset($data['codeJournalRemise'])) $banque->setCodeJournalRemise($data['codeJournalRemise']);
        if (isset($data['comptePaiementsEncaisser'])) $banque->setComptePaiementsEncaisser($data['comptePaiementsEncaisser']);
        if (isset($data['ribBban'])) $banque->setRibBban($data['ribBban']);
        if (isset($data['iban'])) $banque->setIban($data['iban']);
        if (isset($data['bic'])) $banque->setBic($data['bic']);
        if (isset($data['numeroNationalEmetteur'])) $banque->setNumeroNationalEmetteur($data['numeroNationalEmetteur']);
        if (isset($data['identifiantCreancierSepa'])) $banque->setIdentifiantCreancierSepa($data['identifiantCreancierSepa']);
        if (isset($data['notes'])) $banque->setNotes($data['notes']);
        if (isset($data['actif'])) $banque->setActif($data['actif']);
        
        if (isset($data['ordre'])) {
            $repository = $entityManager->getRepository(Banque::class);
            $repository->reorganizeOrdres($banque, (int)$data['ordre']);
        } else {
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'id' => $banque->getId(),
            'code' => $banque->getCode(),
            'nom' => $banque->getNom(),
            'actif' => $banque->isActif(),
            'ordre' => $banque->getOrdre()
        ]);
    }

    #[Route('/banques/{id}/delete', name: 'app_admin_banques_delete', methods: ['DELETE'])]
    public function deleteBanque(Banque $banque, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier si la banque est utilisée par des modes de paiement
        $usageCount = $entityManager->createQuery('SELECT COUNT(mp) FROM App\Entity\ModePaiement mp WHERE mp.banqueParDefaut = :banque')
            ->setParameter('banque', $banque)
            ->getSingleScalarResult();

        if ($usageCount > 0) {
            return $this->json(['success' => false, 'error' => 'Impossible de supprimer: cette banque est utilisée par ' . $usageCount . ' moyen(s) de paiement'], 400);
        }

        try {
            $entityManager->remove($banque);
            $entityManager->flush();
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/tags', name: 'app_admin_tags', methods: ['GET'])]
    public function tags(EntityManagerInterface $entityManager): Response
    {
        try {
            // Vérifier l'authentification
            $user = $this->getUser();
            if (!$user) {
                error_log('Tags Controller: User not authenticated');
                return $this->render('admin/tags.html.twig', [
                    'tags' => [],
                    'error' => 'User not authenticated'
                ]);
            }
            
            if (!in_array('ROLE_ADMIN', $user->getRoles())) {
                error_log('Tags Controller: User not admin: ' . $user->getUserIdentifier());
                return $this->render('admin/tags.html.twig', [
                    'tags' => [],
                    'error' => 'Access denied - not admin'
                ]);
            }
            
            // Récupération des tags avec chargement des clients
            $tags = $entityManager->getRepository(Tag::class)
                ->createQueryBuilder('t')
                ->leftJoin('t.clients', 'c')
                ->addSelect('c')
                ->orderBy('t.ordre', 'ASC')
                ->getQuery()
                ->getResult();
            
            error_log('Tags Controller SUCCESS - User: ' . $user->getUserIdentifier() . ' - Count: ' . count($tags));
            
            return $this->render('admin/tags.html.twig', [
                'tags' => $tags,
            ]);
        } catch (\Exception $e) {
            error_log('Tags Controller EXCEPTION: ' . $e->getMessage());
            error_log('Tags Controller Stack: ' . $e->getTraceAsString());
            
            // Return a fallback template with empty tags array
            return $this->render('admin/tags.html.twig', [
                'tags' => [],
                'error' => 'Exception: ' . $e->getMessage(),
            ]);
        }
    }

    #[Route('/tags-test', name: 'app_admin_tags_test', methods: ['GET'])]
    public function tagsTest(EntityManagerInterface $entityManager): Response
    {
        $tags = $entityManager->getRepository(Tag::class)->findBy([], ['ordre' => 'ASC']);
        
        return $this->render('admin/tags-test.html.twig', [
            'tags' => $tags,
        ]);
    }

    #[Route('/debug-auth', name: 'app_admin_debug_auth', methods: ['GET'])]
    public function debugAuth(): JsonResponse
    {
        $user = $this->getUser();
        
        return $this->json([
            'authenticated' => $user !== null,
            'user_email' => $user ? $user->getUserIdentifier() : null,
            'roles' => $user ? $user->getRoles() : [],
            'has_admin_role' => $user ? in_array('ROLE_ADMIN', $user->getRoles()) : false,
            'timestamp' => new \DateTime(),
            'session_id' => session_id(),
            'php_version' => PHP_VERSION,
        ]);
    }

    #[Route('/tags/create', name: 'app_admin_tags_create', methods: ['POST'])]
    public function createTag(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $tag = new Tag();
            $tag->setNom($data['nom']);
            $tag->setCouleur($data['couleur'] ?? '#3498db');
            $tag->setDescription($data['description'] ?? null);
            $tag->setActif($data['actif'] ?? true);
            $tag->setAssignationAutomatique($data['assignation_automatique'] ?? true);
            
            // Assigner l'ordre
            if (isset($data['ordre'])) {
                $tag->setOrdre((int)$data['ordre']);
            } else {
                // Si pas d'ordre spécifique, mettre à la fin
                $maxOrdre = $entityManager->getRepository(Tag::class)->createQueryBuilder('t')
                    ->select('MAX(t.ordre)')
                    ->getQuery()
                    ->getSingleScalarResult();
                $tag->setOrdre(($maxOrdre ?? 0) + 1);
            }

            $entityManager->persist($tag);
            $entityManager->flush();

            // Réorganiser les ordres si nécessaire
            if (isset($data['ordre'])) {
                $entityManager->getRepository(Tag::class)->reorganizeOrdres();
            }

            return $this->json([
                'success' => true,
                'message' => 'Tag créé avec succès',
                'tag' => [
                    'id' => $tag->getId(),
                    'nom' => $tag->getNom(),
                    'couleur' => $tag->getCouleur(),
                    'description' => $tag->getDescription(),
                    'actif' => $tag->isActif(),
                    'assignation_automatique' => $tag->isAssignationAutomatique(),
                    'ordre' => $tag->getOrdre()
                ]
            ]);
        } catch (Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/tags/{id}/update', name: 'app_admin_tags_update', methods: ['PUT'])]
    public function updateTag(Tag $tag, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $tag->setNom($data['nom']);
            $tag->setCouleur($data['couleur'] ?? $tag->getCouleur());
            $tag->setDescription($data['description'] ?? null);
            $tag->setActif($data['actif'] ?? $tag->isActif());
            $tag->setAssignationAutomatique($data['assignation_automatique'] ?? $tag->isAssignationAutomatique());

            $entityManager->flush();

            // Réorganiser les ordres si nécessaire
            if (isset($data['ordre']) && $data['ordre'] != $tag->getOrdre()) {
                $entityManager->getRepository(Tag::class)->reorganizeOrdres();
            }

            return $this->json(['success' => true, 'message' => 'Tag mis à jour avec succès']);
        } catch (Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/tags/{id}/delete', name: 'app_admin_tags_delete', methods: ['DELETE'])]
    public function deleteTag(Tag $tag, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $entityManager->remove($tag);
            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Tag supprimé avec succès']);
        } catch (Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], 400);
        }
    }

    // ================================
    // GESTION DES TAUX DE TVA
    // ================================

    #[Route('/taux-tva', name: 'app_admin_taux_tva', methods: ['GET'])]
    public function tauxTva(EntityManagerInterface $entityManager): Response
    {
        $tauxTva = $entityManager->getRepository(TauxTVA::class)->findAllOrdered();
        
        return $this->render('admin/taux_tva.html.twig', [
            'taux_tva' => $tauxTva,
        ]);
    }

    #[Route('/taux-tva/create', name: 'app_admin_taux_tva_create', methods: ['POST'])]
    public function createTauxTva(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $tauxTva = new TauxTVA();
            $tauxTva->setNom($data['nom']);
            $tauxTva->setTaux($data['taux']);
            $tauxTva->setActif($data['actif'] ?? true);
            $tauxTva->setOrdre($data['ordre'] ?? 1);
            
            // Comptes de vente
            if (isset($data['vente_compte_debits'])) $tauxTva->setVenteCompteDebits($data['vente_compte_debits']);
            if (isset($data['vente_compte_encaissements'])) $tauxTva->setVenteCompteEncaissements($data['vente_compte_encaissements']);
            if (isset($data['vente_compte_biens'])) $tauxTva->setVenteCompteBiens($data['vente_compte_biens']);
            if (isset($data['vente_compte_services'])) $tauxTva->setVenteCompteServices($data['vente_compte_services']);
            if (isset($data['vente_compte_ports'])) $tauxTva->setVenteComptePorts($data['vente_compte_ports']);
            if (isset($data['vente_compte_eco_contribution'])) $tauxTva->setVenteCompteEcoContribution($data['vente_compte_eco_contribution']);
            if (isset($data['vente_compte_eco_contribution_mobilier'])) $tauxTva->setVenteCompteEcoContributionMobilier($data['vente_compte_eco_contribution_mobilier']);
            
            // Comptes d'achat
            if (isset($data['achat_compte_debits'])) $tauxTva->setAchatCompteDebits($data['achat_compte_debits']);
            if (isset($data['achat_compte_encaissements'])) $tauxTva->setAchatCompteEncaissements($data['achat_compte_encaissements']);
            if (isset($data['achat_compte_autoliquidation_biens'])) $tauxTva->setAchatCompteAutoliquidationBiens($data['achat_compte_autoliquidation_biens']);
            if (isset($data['achat_compte_autoliquidation_services'])) $tauxTva->setAchatCompteAutoliquidationServices($data['achat_compte_autoliquidation_services']);
            if (isset($data['achat_compte_biens'])) $tauxTva->setAchatCompteBiens($data['achat_compte_biens']);
            if (isset($data['achat_compte_services'])) $tauxTva->setAchatCompteServices($data['achat_compte_services']);
            if (isset($data['achat_compte_ports'])) $tauxTva->setAchatComptePorts($data['achat_compte_ports']);
            if (isset($data['achat_compte_eco_contribution'])) $tauxTva->setAchatCompteEcoContribution($data['achat_compte_eco_contribution']);
            if (isset($data['achat_compte_eco_contribution_mobilier'])) $tauxTva->setAchatCompteEcoContributionMobilier($data['achat_compte_eco_contribution_mobilier']);

            $entityManager->persist($tauxTva);

            // Gestion du taux par défaut
            if ($data['par_defaut'] ?? false) {
                $entityManager->getRepository(TauxTVA::class)->setAsDefault($tauxTva);
            } else {
                // Réorganisation des ordres si nécessaire
                if (isset($data['ordre'])) {
                    $entityManager->getRepository(TauxTVA::class)->reorganizeOrdres($tauxTva, (int)$data['ordre']);
                } else {
                    $entityManager->flush();
                }
            }

            return $this->json(['success' => true, 'message' => 'Taux de TVA créé avec succès', 'id' => $tauxTva->getId()]);
        } catch (Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/taux-tva/{id}/update', name: 'app_admin_taux_tva_update', methods: ['PUT'])]
    public function updateTauxTva(TauxTVA $tauxTva, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['nom'])) $tauxTva->setNom($data['nom']);
            if (isset($data['taux'])) $tauxTva->setTaux($data['taux']);
            if (isset($data['actif'])) $tauxTva->setActif($data['actif']);

            // Comptes de vente
            if (isset($data['vente_compte_debits'])) $tauxTva->setVenteCompteDebits($data['vente_compte_debits'] ?: null);
            if (isset($data['vente_compte_encaissements'])) $tauxTva->setVenteCompteEncaissements($data['vente_compte_encaissements'] ?: null);
            if (isset($data['vente_compte_biens'])) $tauxTva->setVenteCompteBiens($data['vente_compte_biens'] ?: null);
            if (isset($data['vente_compte_services'])) $tauxTva->setVenteCompteServices($data['vente_compte_services'] ?: null);
            if (isset($data['vente_compte_ports'])) $tauxTva->setVenteComptePorts($data['vente_compte_ports'] ?: null);
            if (isset($data['vente_compte_eco_contribution'])) $tauxTva->setVenteCompteEcoContribution($data['vente_compte_eco_contribution'] ?: null);
            if (isset($data['vente_compte_eco_contribution_mobilier'])) $tauxTva->setVenteCompteEcoContributionMobilier($data['vente_compte_eco_contribution_mobilier'] ?: null);
            
            // Comptes d'achat
            if (isset($data['achat_compte_debits'])) $tauxTva->setAchatCompteDebits($data['achat_compte_debits'] ?: null);
            if (isset($data['achat_compte_encaissements'])) $tauxTva->setAchatCompteEncaissements($data['achat_compte_encaissements'] ?: null);
            if (isset($data['achat_compte_autoliquidation_biens'])) $tauxTva->setAchatCompteAutoliquidationBiens($data['achat_compte_autoliquidation_biens'] ?: null);
            if (isset($data['achat_compte_autoliquidation_services'])) $tauxTva->setAchatCompteAutoliquidationServices($data['achat_compte_autoliquidation_services'] ?: null);
            if (isset($data['achat_compte_biens'])) $tauxTva->setAchatCompteBiens($data['achat_compte_biens'] ?: null);
            if (isset($data['achat_compte_services'])) $tauxTva->setAchatCompteServices($data['achat_compte_services'] ?: null);
            if (isset($data['achat_compte_ports'])) $tauxTva->setAchatComptePorts($data['achat_compte_ports'] ?: null);
            if (isset($data['achat_compte_eco_contribution'])) $tauxTva->setAchatCompteEcoContribution($data['achat_compte_eco_contribution'] ?: null);
            if (isset($data['achat_compte_eco_contribution_mobilier'])) $tauxTva->setAchatCompteEcoContributionMobilier($data['achat_compte_eco_contribution_mobilier'] ?: null);

            // Gestion du taux par défaut
            if (isset($data['par_defaut']) && $data['par_defaut']) {
                $entityManager->getRepository(TauxTVA::class)->setAsDefault($tauxTva);
            } else {
                // Réorganisation des ordres si nécessaire
                if (isset($data['ordre']) && $data['ordre'] != $tauxTva->getOrdre()) {
                    $entityManager->getRepository(TauxTVA::class)->reorganizeOrdres($tauxTva, (int)$data['ordre']);
                } else {
                    $entityManager->flush();
                }
            }

            return $this->json(['success' => true, 'message' => 'Taux de TVA mis à jour avec succès']);
        } catch (Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/taux-tva/{id}/delete', name: 'app_admin_taux_tva_delete', methods: ['DELETE'])]
    public function deleteTauxTva(TauxTVA $tauxTva, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Vérifier que ce n'est pas le taux par défaut
            if ($tauxTva->isParDefaut()) {
                return $this->json(['success' => false, 'message' => 'Impossible de supprimer le taux de TVA par défaut'], 400);
            }

            $entityManager->remove($tauxTva);
            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Taux de TVA supprimé avec succès']);
        } catch (Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/taux-tva/get', name: 'app_admin_taux_tva_get', methods: ['GET'])]
    public function getTauxTva(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'ID requis'], 400);
        }

        $tauxTva = $entityManager->getRepository(TauxTVA::class)->find($id);
        if (!$tauxTva) {
            return $this->json(['error' => 'Taux TVA non trouvé'], 404);
        }

        return $this->json([
            'id' => $tauxTva->getId(),
            'nom' => $tauxTva->getNom(),
            'taux' => $tauxTva->getTaux(),
            'actif' => $tauxTva->isActif(),
            'parDefaut' => $tauxTva->isParDefaut(),
            'ordre' => $tauxTva->getOrdre(),
            // Comptes vente
            'venteCompteDebits' => $tauxTva->getVenteCompteDebits(),
            'venteCompteEncaissements' => $tauxTva->getVenteCompteEncaissements(),
            'venteCompteBiens' => $tauxTva->getVenteCompteBiens(),
            'venteCompteServices' => $tauxTva->getVenteCompteServices(),
            'venteComptePorts' => $tauxTva->getVenteComptePorts(),
            'venteCompteEcoContribution' => $tauxTva->getVenteCompteEcoContribution(),
            'venteCompteEcoContributionMobilier' => $tauxTva->getVenteCompteEcoContributionMobilier(),
            // Comptes achat
            'achatCompteDebits' => $tauxTva->getAchatCompteDebits(),
            'achatCompteEncaissements' => $tauxTva->getAchatCompteEncaissements(),
            'achatCompteAutoliquidationBiens' => $tauxTva->getAchatCompteAutoliquidationBiens(),
            'achatCompteAutoliquidationServices' => $tauxTva->getAchatCompteAutoliquidationServices(),
            'achatCompteBiens' => $tauxTva->getAchatCompteBiens(),
            'achatCompteServices' => $tauxTva->getAchatCompteServices(),
            'achatComptePorts' => $tauxTva->getAchatComptePorts(),
            'achatCompteEcoContribution' => $tauxTva->getAchatCompteEcoContribution(),
            'achatCompteEcoContributionMobilier' => $tauxTva->getAchatCompteEcoContributionMobilier(),
        ]);
    }

    // ================================
    // GESTION DES UNITÉS
    // ================================

    #[Route('/unites', name: 'app_admin_unites', methods: ['GET'])]
    public function unites(EntityManagerInterface $entityManager): Response
    {
        $unites = $entityManager->getRepository(Unite::class)->findAllOrdered();
        
        return $this->render('admin/unites.html.twig', [
            'unites' => $unites,
        ]);
    }

    #[Route('/unites/get', name: 'app_admin_unites_get', methods: ['GET'])]
    public function getUnite(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'ID requis'], 400);
        }

        $unite = $entityManager->getRepository(Unite::class)->find($id);
        if (!$unite) {
            return $this->json(['error' => 'Unité non trouvée'], 404);
        }

        return $this->json([
            'id' => $unite->getId(),
            'code' => $unite->getCode(),
            'nom' => $unite->getNom(),
            'type' => $unite->getType(),
            'decimalesPrix' => $unite->getDecimalesPrix(),
            'coefficientConversion' => $unite->getCoefficientConversion(),
            'notes' => $unite->getNotes(),
            'actif' => $unite->isActif(),
            'ordre' => $unite->getOrdre(),
        ]);
    }

    #[Route('/unites/create', name: 'app_admin_unites_create', methods: ['POST'])]
    public function createUnite(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation des données requises
            if (empty($data['code']) || empty($data['nom'])) {
                return $this->json(['success' => false, 'message' => 'Code et nom sont requis'], 400);
            }

            // Vérifier l'unicité du code
            if ($entityManager->getRepository(Unite::class)->codeExists($data['code'])) {
                return $this->json(['success' => false, 'message' => 'Ce code d\'unité existe déjà'], 400);
            }
            
            $unite = new Unite();
            $unite->setCode($data['code']);
            $unite->setNom($data['nom']);
            $unite->setType($data['type'] ?? null);
            $unite->setDecimalesPrix($data['decimales_prix'] ?? 2);
            $unite->setCoefficientConversion($data['coefficient_conversion'] ?? null);
            $unite->setNotes($data['notes'] ?? null);
            $unite->setActif($data['actif'] ?? true);
            $unite->setOrdre($data['ordre'] ?? 1);

            $entityManager->persist($unite);

            // Réorganisation des ordres si nécessaire
            if (isset($data['ordre'])) {
                $entityManager->getRepository(Unite::class)->reorganizeOrdres($unite, (int)$data['ordre']);
            } else {
                $entityManager->flush();
            }

            return $this->json(['success' => true, 'message' => 'Unité créée avec succès', 'id' => $unite->getId()]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/unites/{id}/update', name: 'app_admin_unites_update', methods: ['PUT'])]
    public function updateUnite(Unite $unite, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['code'])) {
                // Vérifier l'unicité du code (exclure l'unité actuelle)
                if ($entityManager->getRepository(Unite::class)->codeExists($data['code'], $unite->getId())) {
                    return $this->json(['success' => false, 'message' => 'Ce code d\'unité existe déjà'], 400);
                }
                $unite->setCode($data['code']);
            }
            
            if (isset($data['nom'])) $unite->setNom($data['nom']);
            if (isset($data['type'])) $unite->setType($data['type'] ?: null);
            if (isset($data['decimales_prix'])) $unite->setDecimalesPrix($data['decimales_prix']);
            if (isset($data['coefficient_conversion'])) $unite->setCoefficientConversion($data['coefficient_conversion'] ?: null);
            if (isset($data['notes'])) $unite->setNotes($data['notes'] ?: null);
            if (isset($data['actif'])) $unite->setActif($data['actif']);

            // Réorganisation des ordres si nécessaire
            if (isset($data['ordre']) && $data['ordre'] != $unite->getOrdre()) {
                $entityManager->getRepository(Unite::class)->reorganizeOrdres($unite, (int)$data['ordre']);
            } else {
                $entityManager->flush();
            }

            return $this->json(['success' => true, 'message' => 'Unité mise à jour avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/unites/{id}/delete', name: 'app_admin_unites_delete', methods: ['DELETE'])]
    public function deleteUnite(Unite $unite, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // TODO: Vérifier que l'unité n'est pas utilisée dans des produits/devis/factures
            // Pour l'instant, on autorise la suppression
            
            $entityManager->remove($unite);
            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Unité supprimée avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/unites/types', name: 'app_admin_unites_types', methods: ['GET'])]
    public function getUniteTypes(EntityManagerInterface $entityManager): JsonResponse
    {
        $types = $entityManager->getRepository(Unite::class)->findAllTypes();
        return $this->json($types);
    }

    // ================================
    // GESTION DES CIVILITÉS
    // ================================

    #[Route('/civilites', name: 'app_admin_civilites', methods: ['GET'])]
    public function civilites(EntityManagerInterface $entityManager): Response
    {
        $civilites = $entityManager->getRepository(Civilite::class)->findAllOrdered();
        
        return $this->render('admin/civilites.html.twig', [
            'civilites' => $civilites,
        ]);
    }

    #[Route('/civilites/get', name: 'app_admin_civilites_get', methods: ['GET'])]
    public function getCivilite(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'ID requis'], 400);
        }

        $civilite = $entityManager->getRepository(Civilite::class)->find($id);
        if (!$civilite) {
            return $this->json(['error' => 'Civilité non trouvée'], 404);
        }

        return $this->json([
            'id' => $civilite->getId(),
            'code' => $civilite->getCode(),
            'nom' => $civilite->getNom(),
            'abrege' => $civilite->getAbrege(),
            'notes' => $civilite->getNotes(),
            'actif' => $civilite->isActif(),
            'ordre' => $civilite->getOrdre(),
        ]);
    }

    #[Route('/civilites/create', name: 'app_admin_civilites_create', methods: ['POST'])]
    public function createCivilite(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation des données requises
            if (empty($data['code']) || empty($data['nom'])) {
                return $this->json(['success' => false, 'message' => 'Code et nom sont requis'], 400);
            }

            // Vérifier l'unicité du code
            if ($entityManager->getRepository(Civilite::class)->codeExists($data['code'])) {
                return $this->json(['success' => false, 'message' => 'Ce code de civilité existe déjà'], 400);
            }
            
            $civilite = new Civilite();
            $civilite->setCode($data['code']);
            $civilite->setNom($data['nom']);
            $civilite->setAbrege($data['abrege'] ?? null);
            $civilite->setNotes($data['notes'] ?? null);
            $civilite->setActif($data['actif'] ?? true);
            $civilite->setOrdre($data['ordre'] ?? 1);

            $entityManager->persist($civilite);

            // Réorganisation des ordres si nécessaire
            if (isset($data['ordre'])) {
                $entityManager->getRepository(Civilite::class)->reorganizeOrdres($civilite, (int)$data['ordre']);
            } else {
                $entityManager->flush();
            }

            return $this->json(['success' => true, 'message' => 'Civilité créée avec succès', 'id' => $civilite->getId()]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/civilites/{id}/update', name: 'app_admin_civilites_update', methods: ['PUT'])]
    public function updateCivilite(Civilite $civilite, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['code'])) {
                // Vérifier l'unicité du code (exclure la civilité actuelle)
                if ($entityManager->getRepository(Civilite::class)->codeExists($data['code'], $civilite->getId())) {
                    return $this->json(['success' => false, 'message' => 'Ce code de civilité existe déjà'], 400);
                }
                $civilite->setCode($data['code']);
            }
            
            if (isset($data['nom'])) $civilite->setNom($data['nom']);
            if (isset($data['abrege'])) $civilite->setAbrege($data['abrege'] ?: null);
            if (isset($data['notes'])) $civilite->setNotes($data['notes'] ?: null);
            if (isset($data['actif'])) $civilite->setActif($data['actif']);

            // Réorganisation des ordres si nécessaire
            if (isset($data['ordre']) && $data['ordre'] != $civilite->getOrdre()) {
                $entityManager->getRepository(Civilite::class)->reorganizeOrdres($civilite, (int)$data['ordre']);
            } else {
                $entityManager->flush();
            }

            return $this->json(['success' => true, 'message' => 'Civilité mise à jour avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/civilites/{id}/delete', name: 'app_admin_civilites_delete', methods: ['DELETE'])]
    public function deleteCivilite(Civilite $civilite, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // TODO: Vérifier que la civilité n'est pas utilisée par des clients/contacts
            // Pour l'instant, on autorise la suppression
            
            $entityManager->remove($civilite);
            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Civilité supprimée avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], 400);
        }
    }

    // ===== GESTION DES TRANSPORTEURS =====

    #[Route('/transporteurs', name: 'app_admin_transporteurs', methods: ['GET'])]
    public function transporteurs(EntityManagerInterface $entityManager): Response
    {
        $transporteurs = $entityManager->getRepository(Transporteur::class)->findAllOrdered();

        return $this->render('admin/transporteurs.html.twig', [
            'transporteurs' => $transporteurs,
        ]);
    }

    #[Route('/transporteurs/get', name: 'app_admin_transporteurs_get', methods: ['GET'])]
    public function getTransporteur(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'ID requis'], 400);
        }

        $transporteur = $entityManager->getRepository(Transporteur::class)->find($id);
        if (!$transporteur) {
            return $this->json(['error' => 'Transporteur non trouvé'], 404);
        }

        return $this->json([
            'id' => $transporteur->getId(),
            'code' => $transporteur->getCode(),
            'nom' => $transporteur->getNom(),
            'contact' => $transporteur->getContact(),
            'adresse' => $transporteur->getAdresse(),
            'codePostal' => $transporteur->getCodePostal(),
            'ville' => $transporteur->getVille(),
            'pays' => $transporteur->getPays(),
            'telephone' => $transporteur->getTelephone(),
            'fax' => $transporteur->getFax(),
            'email' => $transporteur->getEmail(),
            'siteWeb' => $transporteur->getSiteWeb(),
            'numeroCompte' => $transporteur->getNumeroCompte(),
            'apiUrl' => $transporteur->getApiUrl(),
            'apiKey' => $transporteur->getApiKey(),
            'actif' => $transporteur->isActif(),
            'ordre' => $transporteur->getOrdre(),
            'notes' => $transporteur->getNotes(),
        ]);
    }

    #[Route('/transporteurs/create', name: 'app_admin_transporteurs_create', methods: ['POST'])]
    public function createTransporteur(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation des champs obligatoires
            if (empty($data['code']) || empty($data['nom'])) {
                return $this->json(['success' => false, 'message' => 'Code et nom sont obligatoires'], 400);
            }

            // Vérifier l'unicité du code
            if ($entityManager->getRepository(Transporteur::class)->codeExists($data['code'])) {
                return $this->json(['success' => false, 'message' => 'Ce code de transporteur existe déjà'], 400);
            }

            $transporteur = new Transporteur();
            $transporteur->setCode($data['code']);
            $transporteur->setNom($data['nom']);
            $transporteur->setContact($data['contact'] ?? null);
            $transporteur->setAdresse($data['adresse'] ?? null);
            $transporteur->setCodePostal($data['codePostal'] ?? null);
            $transporteur->setVille($data['ville'] ?? null);
            $transporteur->setPays($data['pays'] ?? null);
            $transporteur->setTelephone($data['telephone'] ?? null);
            $transporteur->setFax($data['fax'] ?? null);
            $transporteur->setEmail($data['email'] ?? null);
            $transporteur->setSiteWeb($data['siteWeb'] ?? null);
            $transporteur->setNumeroCompte($data['numeroCompte'] ?? null);
            $transporteur->setApiUrl($data['apiUrl'] ?? null);
            $transporteur->setApiKey($data['apiKey'] ?? null);
            $transporteur->setActif($data['actif'] ?? true);
            $transporteur->setNotes($data['notes'] ?? null);

            // Gestion intelligente de l'ordre
            if (isset($data['ordre'])) {
                $entityManager->persist($transporteur);
                $entityManager->flush(); // Pour obtenir l'ID
                $entityManager->getRepository(Transporteur::class)->reorganizeOrdres($transporteur, (int)$data['ordre']);
            } else {
                $entityManager->persist($transporteur);
                $entityManager->flush();
            }

            return $this->json(['success' => true, 'message' => 'Transporteur créé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/transporteurs/{id}/update', name: 'app_admin_transporteurs_update', methods: ['PUT'])]
    public function updateTransporteur(Transporteur $transporteur, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['code'])) {
                // Vérifier l'unicité du code (exclure le transporteur actuel)
                if ($entityManager->getRepository(Transporteur::class)->codeExists($data['code'], $transporteur->getId())) {
                    return $this->json(['success' => false, 'message' => 'Ce code de transporteur existe déjà'], 400);
                }
                $transporteur->setCode($data['code']);
            }

            if (isset($data['nom'])) $transporteur->setNom($data['nom']);
            if (isset($data['contact'])) $transporteur->setContact($data['contact'] ?: null);
            if (isset($data['adresse'])) $transporteur->setAdresse($data['adresse'] ?: null);
            if (isset($data['codePostal'])) $transporteur->setCodePostal($data['codePostal'] ?: null);
            if (isset($data['ville'])) $transporteur->setVille($data['ville'] ?: null);
            if (isset($data['pays'])) $transporteur->setPays($data['pays'] ?: null);
            if (isset($data['telephone'])) $transporteur->setTelephone($data['telephone'] ?: null);
            if (isset($data['fax'])) $transporteur->setFax($data['fax'] ?: null);
            if (isset($data['email'])) $transporteur->setEmail($data['email'] ?: null);
            if (isset($data['siteWeb'])) $transporteur->setSiteWeb($data['siteWeb'] ?: null);
            if (isset($data['numeroCompte'])) $transporteur->setNumeroCompte($data['numeroCompte'] ?: null);
            if (isset($data['apiUrl'])) $transporteur->setApiUrl($data['apiUrl'] ?: null);
            if (isset($data['apiKey'])) $transporteur->setApiKey($data['apiKey'] ?: null);
            if (isset($data['actif'])) $transporteur->setActif($data['actif']);
            if (isset($data['notes'])) $transporteur->setNotes($data['notes'] ?: null);

            // Réorganisation des ordres si nécessaire
            if (isset($data['ordre']) && $data['ordre'] != $transporteur->getOrdre()) {
                $entityManager->getRepository(Transporteur::class)->reorganizeOrdres($transporteur, (int)$data['ordre']);
            } else {
                $entityManager->flush();
            }

            return $this->json(['success' => true, 'message' => 'Transporteur mis à jour avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/transporteurs/{id}/delete', name: 'app_admin_transporteurs_delete', methods: ['DELETE'])]
    public function deleteTransporteur(Transporteur $transporteur, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Vérifier que le transporteur n'est pas utilisé par des frais de port
            $fraisPortUtilisant = $entityManager->getRepository(FraisPort::class)->findBy(['transporteur' => $transporteur]);
            if (!empty($fraisPortUtilisant)) {
                return $this->json([
                    'success' => false, 
                    'message' => 'Impossible de supprimer ce transporteur car il est utilisé par ' . count($fraisPortUtilisant) . ' frais de port'
                ], 400);
            }

            $entityManager->remove($transporteur);
            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Transporteur supprimé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], 400);
        }
    }

    // ===== GESTION DES FRAIS DE PORT =====

    #[Route('/frais-port', name: 'app_admin_frais_port', methods: ['GET'])]
    public function fraisPort(EntityManagerInterface $entityManager): Response
    {
        $fraisPort = $entityManager->getRepository(FraisPort::class)->findAllOrdered();
        $tauxTva = $entityManager->getRepository(TauxTVA::class)->findAllOrdered();
        $transporteurs = $entityManager->getRepository(Transporteur::class)->findAllActiveOrdered();

        return $this->render('admin/frais_port.html.twig', [
            'frais_port' => $fraisPort,
            'taux_tva' => $tauxTva,
            'transporteurs' => $transporteurs,
        ]);
    }

    #[Route('/frais-port/get', name: 'app_admin_frais_port_get', methods: ['GET'])]
    public function getFraisPort(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'ID requis'], 400);
        }

        $fraisPort = $entityManager->getRepository(FraisPort::class)->findWithPaliers($id);
        if (!$fraisPort) {
            return $this->json(['error' => 'Frais de port non trouvé'], 404);
        }

        $paliers = [];
        foreach ($fraisPort->getPaliers() as $palier) {
            $paliers[] = [
                'id' => $palier->getId(),
                'limiteJusqua' => $palier->getLimiteJusqua(),
                'valeur' => $palier->getValeur(),
                'description' => $palier->getDescription(),
            ];
        }

        return $this->json([
            'id' => $fraisPort->getId(),
            'code' => $fraisPort->getCode(),
            'nom' => $fraisPort->getNom(),
            'modeCalcul' => $fraisPort->getModeCalcul(),
            'valeur' => $fraisPort->getValeur(),
            'tauxTvaId' => $fraisPort->getTauxTva()->getId(),
            'transporteurId' => $fraisPort->getTransporteur()?->getId(),
            'actif' => $fraisPort->isActif(),
            'ordre' => $fraisPort->getOrdre(),
            'notes' => $fraisPort->getNotes(),
            'paliers' => $paliers,
        ]);
    }

    #[Route('/frais-port/create', name: 'app_admin_frais_port_create', methods: ['POST'])]
    public function createFraisPort(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation des champs obligatoires
            if (empty($data['code']) || empty($data['nom']) || empty($data['tauxTvaId'])) {
                return $this->json(['success' => false, 'message' => 'Code, nom et taux TVA sont obligatoires'], 400);
            }

            // Vérifier l'unicité du code
            if ($entityManager->getRepository(FraisPort::class)->codeExists($data['code'])) {
                return $this->json(['success' => false, 'message' => 'Ce code de frais de port existe déjà'], 400);
            }

            // Récuper les entités liées
            $tauxTva = $entityManager->getRepository(TauxTVA::class)->find($data['tauxTvaId']);
            if (!$tauxTva) {
                return $this->json(['success' => false, 'message' => 'Taux TVA non trouvé'], 400);
            }

            $transporteur = null;
            if (!empty($data['transporteurId'])) {
                $transporteur = $entityManager->getRepository(Transporteur::class)->find($data['transporteurId']);
                if (!$transporteur) {
                    return $this->json(['success' => false, 'message' => 'Transporteur non trouvé'], 400);
                }
            }

            $fraisPort = new FraisPort();
            $fraisPort->setCode($data['code']);
            $fraisPort->setNom($data['nom']);
            $fraisPort->setModeCalcul($data['modeCalcul'] ?? FraisPort::MODE_MONTANT_FIXE);
            $fraisPort->setValeur($data['valeur'] ?? null);
            $fraisPort->setTauxTva($tauxTva);
            $fraisPort->setTransporteur($transporteur);
            $fraisPort->setActif($data['actif'] ?? true);
            $fraisPort->setNotes($data['notes'] ?? null);

            // Gestion des paliers si nécessaire
            if (!empty($data['paliers']) && $fraisPort->utiliserPaliers()) {
                foreach ($data['paliers'] as $palierData) {
                    if (!empty($palierData['limiteJusqua']) && !empty($palierData['valeur'])) {
                        $palier = new PalierFraisPort();
                        $palier->setLimiteJusqua($palierData['limiteJusqua']);
                        $palier->setValeur($palierData['valeur']);
                        $palier->setDescription($palierData['description'] ?? null);
                        $fraisPort->addPalier($palier);
                    }
                }
            }

            // Gestion intelligente de l'ordre
            if (isset($data['ordre'])) {
                $entityManager->persist($fraisPort);
                $entityManager->flush(); // Pour obtenir l'ID
                $entityManager->getRepository(FraisPort::class)->reorganizeOrdres($fraisPort, (int)$data['ordre']);
            } else {
                $entityManager->persist($fraisPort);
                $entityManager->flush();
            }

            return $this->json(['success' => true, 'message' => 'Frais de port créé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/frais-port/{id}/update', name: 'app_admin_frais_port_update', methods: ['PUT'])]
    public function updateFraisPort(FraisPort $fraisPort, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['code'])) {
                // Vérifier l'unicité du code (exclure le frais de port actuel)
                if ($entityManager->getRepository(FraisPort::class)->codeExists($data['code'], $fraisPort->getId())) {
                    return $this->json(['success' => false, 'message' => 'Ce code de frais de port existe déjà'], 400);
                }
                $fraisPort->setCode($data['code']);
            }

            if (isset($data['nom'])) $fraisPort->setNom($data['nom']);
            if (isset($data['modeCalcul'])) $fraisPort->setModeCalcul($data['modeCalcul']);
            if (isset($data['valeur'])) $fraisPort->setValeur($data['valeur'] ?: null);
            if (isset($data['actif'])) $fraisPort->setActif($data['actif']);
            if (isset($data['notes'])) $fraisPort->setNotes($data['notes'] ?: null);

            // Mise à jour du taux TVA
            if (isset($data['tauxTvaId'])) {
                $tauxTva = $entityManager->getRepository(TauxTVA::class)->find($data['tauxTvaId']);
                if (!$tauxTva) {
                    return $this->json(['success' => false, 'message' => 'Taux TVA non trouvé'], 400);
                }
                $fraisPort->setTauxTva($tauxTva);
            }

            // Mise à jour du transporteur
            if (isset($data['transporteurId'])) {
                $transporteur = null;
                if ($data['transporteurId']) {
                    $transporteur = $entityManager->getRepository(Transporteur::class)->find($data['transporteurId']);
                    if (!$transporteur) {
                        return $this->json(['success' => false, 'message' => 'Transporteur non trouvé'], 400);
                    }
                }
                $fraisPort->setTransporteur($transporteur);
            }

            // Gestion des paliers
            if (isset($data['paliers'])) {
                // Supprimer les anciens paliers
                foreach ($fraisPort->getPaliers() as $palier) {
                    $fraisPort->removePalier($palier);
                    $entityManager->remove($palier);
                }

                // Ajouter les nouveaux paliers si mode palier
                if ($fraisPort->utiliserPaliers()) {
                    foreach ($data['paliers'] as $palierData) {
                        if (!empty($palierData['limiteJusqua']) && !empty($palierData['valeur'])) {
                            $palier = new PalierFraisPort();
                            $palier->setLimiteJusqua($palierData['limiteJusqua']);
                            $palier->setValeur($palierData['valeur']);
                            $palier->setDescription($palierData['description'] ?? null);
                            $fraisPort->addPalier($palier);
                        }
                    }
                }
            }

            // Réorganisation des ordres si nécessaire
            if (isset($data['ordre']) && $data['ordre'] != $fraisPort->getOrdre()) {
                $entityManager->getRepository(FraisPort::class)->reorganizeOrdres($fraisPort, (int)$data['ordre']);
            } else {
                $entityManager->flush();
            }

            return $this->json(['success' => true, 'message' => 'Frais de port mis à jour avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/frais-port/{id}/delete', name: 'app_admin_frais_port_delete', methods: ['DELETE'])]
    public function deleteFraisPort(FraisPort $fraisPort, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // TODO: Vérifier que le frais de port n'est pas utilisé par des documents
            // Pour l'instant, on autorise la suppression

            $entityManager->remove($fraisPort);
            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Frais de port supprimé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], 400);
        }
    }

    // ===== GESTION SECTEURS MODERNISÉE =====

    #[Route('/secteurs-admin', name: 'app_admin_secteurs_moderne', methods: ['GET'])]
    public function secteursModerne(EntityManagerInterface $entityManager): Response
    {
        error_log("🔍 DEBUG: secteursModerne() appelée");
        
        try {
            // Test 1: Récupération des secteurs de base
            error_log("🔍 DEBUG: Récupération des secteurs...");
            $secteurs = $entityManager->getRepository(Secteur::class)->findBy([], ['nomSecteur' => 'ASC']);
            error_log("🔍 DEBUG: Secteurs trouvés: " . count($secteurs));
            
            // Test 2: Récupération des types de secteurs
            error_log("🔍 DEBUG: Récupération des types de secteurs...");
            $typesSecteursDisponibles = $entityManager->getRepository(TypeSecteur::class)->findBy(['actif' => true], ['nom' => 'ASC']);
            error_log("🔍 DEBUG: Types secteurs trouvés: " . count($typesSecteursDisponibles));
            
            // Test 3: Récupération des commerciaux
            error_log("🔍 DEBUG: Récupération des commerciaux...");
            $commerciaux = $entityManager->getRepository(User::class)->findBy(['isActive' => true], ['nom' => 'ASC']);
            error_log("🔍 DEBUG: Commerciaux trouvés: " . count($commerciaux));
            
            // Test 4: Récupération des divisions administratives
            error_log("🔍 DEBUG: Récupération des divisions administratives...");
            $divisions = $entityManager->getRepository(DivisionAdministrative::class)->findBy(['actif' => true], ['nomCommune' => 'ASC'], 50);
            error_log("🔍 DEBUG: Divisions trouvées: " . count($divisions));
            
            // Test 5: Statistiques
            error_log("🔍 DEBUG: Calcul des statistiques...");
            $stats = [
                'divisions_administratives' => $entityManager->getRepository(DivisionAdministrative::class)->count(['actif' => true]),
                'types_secteur' => $entityManager->getRepository(TypeSecteur::class)->count(['actif' => true]),
                'attributions_secteur' => $entityManager->getRepository(AttributionSecteur::class)->count([]),
                'secteurs' => count($secteurs)
            ];
            error_log("🔍 DEBUG: Stats calculées: " . json_encode($stats));
            
            // Test 6: Rendu du template
            error_log("🔍 DEBUG: Rendu du template secteurs_moderne.html.twig...");
            
            return $this->render('admin/secteurs_moderne.html.twig', [
                'secteurs' => $secteurs,
                'types_secteurs' => $typesSecteursDisponibles,
                'commerciaux' => $commerciaux,  
                'divisions' => $divisions,
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            error_log("❌ ERREUR dans secteursModerne: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            
            return new Response('
                <div class="alert alert-danger">
                    <h4>Erreur de chargement des secteurs</h4>
                    <p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                    <p><strong>Fichier:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>
                </div>
            ', 500);
        }
    }

    // ===== API POUR GESTION DES ATTRIBUTIONS SECTEURS =====
    
    #[Route('/secteur/{id}/attributions', name: 'app_admin_secteur_attributions', methods: ['GET'])]
    public function getSecteurAttributions(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $secteur = $entityManager->getRepository(Secteur::class)->find($id);
            if (!$secteur) {
                return new JsonResponse(['success' => false, 'message' => 'Secteur non trouvé'], 404);
            }
            
            $attributions = $entityManager->getRepository(AttributionSecteur::class)
                ->createQueryBuilder('a')
                ->leftJoin('a.divisionAdministrative', 'd')
                ->where('a.secteur = :secteur')
                ->setParameter('secteur', $secteur)
                ->orderBy('a.typeCritere', 'ASC')
                ->addOrderBy('d.nomCommune', 'ASC')
                ->getQuery()
                ->getResult();
            
            $data = [];
            foreach ($attributions as $attribution) {
                $data[] = [
                    'id' => $attribution->getId(),
                    'typeCritere' => $attribution->getTypeCritere(),
                    'valeurCritere' => $attribution->getValeurCritere(),
                    'notes' => $attribution->getNotes(),
                    'divisionAdministrative' => [
                        'id' => $attribution->getDivisionAdministrative()->getId(),
                        'nom' => $this->getDivisionNom($attribution->getDivisionAdministrative(), $attribution->getTypeCritere()),
                        'details' => $this->getDivisionDetails($attribution->getDivisionAdministrative(), $attribution->getTypeCritere())
                    ]
                ];
            }
            
            return new JsonResponse(['success' => true, 'attributions' => $data]);
            
        } catch (\Exception $e) {
            error_log("❌ Erreur getSecteurAttributions: " . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
    
    #[Route('/secteur/attribution/create', name: 'app_admin_secteur_attribution_create', methods: ['POST'])]
    public function createSecteurAttribution(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation des données
            if (!isset($data['secteurId'], $data['divisionId'], $data['typeCritere'], $data['valeurCritere'])) {
                return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
            }
            
            $secteur = $entityManager->getRepository(Secteur::class)->find($data['secteurId']);
            $division = $entityManager->getRepository(DivisionAdministrative::class)->find($data['divisionId']);
            
            if (!$secteur || !$division) {
                return new JsonResponse(['success' => false, 'message' => 'Secteur ou division non trouvé'], 404);
            }
            
            // Vérifier si l'attribution existe déjà
            $existingAttribution = $entityManager->getRepository(AttributionSecteur::class)
                ->findOneBy([
                    'secteur' => $secteur,
                    'divisionAdministrative' => $division,
                    'typeCritere' => $data['typeCritere']
                ]);
                
            if ($existingAttribution) {
                return new JsonResponse(['success' => false, 'message' => 'Cette attribution existe déjà'], 409);
            }
            
            // Créer l'attribution
            $attribution = new AttributionSecteur();
            $attribution->setSecteur($secteur);
            $attribution->setDivisionAdministrative($division);
            $attribution->setTypeCritere($data['typeCritere']);
            $attribution->setValeurCritere($data['valeurCritere']);
            
            if (!empty($data['notes'])) {
                $attribution->setNotes($data['notes']);
            }
            
            $entityManager->persist($attribution);
            $entityManager->flush();
            
            // Règle d'exclusion automatique : créer les zones conflictuelles selon la hiérarchie
            try {
                $this->appliquerReglesExclusionGeographique($attribution, $entityManager);
                $entityManager->flush();
            } catch (\Exception $exclusionError) {
                // Log l'erreur mais ne pas faire échouer la création de l'attribution
                error_log("⚠️ Erreur lors de l'application des exclusions géographiques : " . $exclusionError->getMessage());
                // L'attribution reste créée même si les exclusions échouent
            }
            
            return new JsonResponse(['success' => true, 'message' => 'Attribution créée avec succès', 'id' => $attribution->getId()]);
            
        } catch (\Exception $e) {
            error_log("❌ Erreur createSecteurAttribution: " . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
    
    #[Route('/secteur/attribution/{id}', name: 'app_admin_secteur_attribution_delete', methods: ['DELETE'])]
    public function deleteSecteurAttribution(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $attribution = $entityManager->getRepository(AttributionSecteur::class)->find($id);
            
            if (!$attribution) {
                return new JsonResponse(['success' => false, 'message' => 'Attribution non trouvée'], 404);
            }
            
            // Avant de supprimer l'attribution, gérer les exclusions
            $this->gererExclusionsAvantSuppression($attribution, $entityManager);
            
            $entityManager->remove($attribution);
            $entityManager->flush();
            
            return new JsonResponse(['success' => true, 'message' => 'Attribution supprimée avec succès']);
            
        } catch (\Exception $e) {
            error_log("❌ Erreur deleteSecteurAttribution: " . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
    
    #[Route('/divisions-administratives/recherche', name: 'app_admin_divisions_recherche', methods: ['GET'])]
    public function rechercheDivisions(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $type = $request->query->get('type');
            $terme = $request->query->get('terme');
            
            if (!$type || !$terme || strlen($terme) < 2) {
                return new JsonResponse(['success' => false, 'message' => 'Paramètres manquants'], 400);
            }
            
            $qb = $entityManager->getRepository(DivisionAdministrative::class)->createQueryBuilder('d');
            $qb->select('d')->where('d.actif = true');
            
            // Recherche selon le type de division administrative
            switch ($type) {
                case 'code_postal':
                    // Pour les codes postaux, récupérer une seule entrée par code postal
                    // Solution simple : faire une requête directe avec une sous-requête native
                    $connection = $entityManager->getConnection();
                    
                    // Construire la requête SQL native pour récupérer les IDs uniques par code postal
                    $sql = "SELECT DISTINCT ON (code_postal) id FROM division_administrative 
                            WHERE actif = true AND code_postal LIKE ? 
                            ORDER BY code_postal, id ASC";
                    
                    $stmt = $connection->prepare($sql);
                    $stmt->bindValue(1, $terme . '%');
                    $result = $stmt->executeQuery();
                    $ids = $result->fetchFirstColumn();
                    
                    if (!empty($ids)) {
                        $qb->andWhere('d.id IN (:ids)')
                           ->setParameter('ids', $ids)
                           ->orderBy('d.codePostal', 'ASC');
                    } else {
                        // Aucun résultat trouvé, forcer une condition impossible
                        $qb->andWhere('d.id = -1');
                    }
                    break;
                    
                case 'commune':
                    $qb->andWhere('LOWER(d.nomCommune) LIKE LOWER(:terme)')
                       ->setParameter('terme', '%' . $terme . '%')
                       ->orderBy('d.nomCommune', 'ASC')
                       ->addOrderBy('d.codePostal', 'ASC');
                    break;
                    
                case 'canton':
                    // Pour les cantons, on sélectionne un seul résultat par canton
                    $qb->select('MIN(d.id) as min_id')
                       ->addSelect('d.codeCanton')
                       ->addSelect('d.nomCanton')
                       ->addSelect('d.nomDepartement')
                       ->addSelect('d.codeDepartement')
                       ->andWhere('LOWER(d.nomCanton) LIKE LOWER(:terme)')
                       ->andWhere('d.nomCanton IS NOT NULL')
                       ->andWhere('d.codeCanton IS NOT NULL')
                       ->setParameter('terme', '%' . $terme . '%')
                       ->groupBy('d.codeCanton, d.nomCanton, d.nomDepartement, d.codeDepartement')
                       ->orderBy('d.nomCanton', 'ASC');
                    break;
                    
                case 'epci':
                    // Pour les EPCI, on sélectionne un seul résultat par EPCI
                    $qb->select('MIN(d.id) as min_id')
                       ->addSelect('d.codeEpci')
                       ->addSelect('d.nomEpci')
                       ->addSelect('d.typeEpci')
                       ->addSelect('d.nomDepartement')
                       ->addSelect('d.codeDepartement')
                       ->andWhere('LOWER(d.nomEpci) LIKE LOWER(:terme)')
                       ->andWhere('d.nomEpci IS NOT NULL')
                       ->andWhere('d.codeEpci IS NOT NULL')
                       ->setParameter('terme', '%' . $terme . '%')
                       ->groupBy('d.codeEpci, d.nomEpci, d.typeEpci, d.nomDepartement, d.codeDepartement')
                       ->orderBy('d.nomEpci', 'ASC');
                    break;
                    
                case 'departement':
                    // Pour les départements, on sélectionne une division par département
                    $qb->select('MIN(d.id) as min_id')
                       ->addSelect('d.codeDepartement')
                       ->addSelect('d.nomDepartement')
                       ->addSelect('d.nomRegion')
                       ->andWhere('LOWER(d.nomDepartement) LIKE LOWER(:terme)')
                       ->andWhere('d.nomDepartement IS NOT NULL')
                       ->andWhere('d.codeDepartement IS NOT NULL')
                       ->setParameter('terme', '%' . $terme . '%')
                       ->groupBy('d.codeDepartement, d.nomDepartement, d.nomRegion')
                       ->orderBy('d.nomDepartement', 'ASC');
                    break;
                    
                case 'region':
                    // Pour les régions, on sélectionne une division par région
                    $qb->select('MIN(d.id) as min_id')
                       ->addSelect('d.codeRegion')
                       ->addSelect('d.nomRegion')
                       ->andWhere('LOWER(d.nomRegion) LIKE LOWER(:terme)')
                       ->andWhere('d.nomRegion IS NOT NULL')
                       ->andWhere('d.codeRegion IS NOT NULL')
                       ->setParameter('terme', '%' . $terme . '%')
                       ->groupBy('d.codeRegion, d.nomRegion')
                       ->orderBy('d.nomRegion', 'ASC');
                    break;
                    
                default:
                    return new JsonResponse(['success' => false, 'message' => 'Type de recherche non supporté'], 400);
            }
            
            $results = $qb->setMaxResults(20)->getQuery()->getResult();
            
            $data = [];
            
            // Traitement spécial pour types qui utilisent des requêtes groupées (départements, régions, cantons, EPCI)
            if ($type === 'departement') {
                foreach ($results as $result) {
                    $data[] = [
                        'id' => $result['min_id'],
                        'nom' => $result['nomDepartement'] ?? 'Département non défini',
                        'valeur' => $result['codeDepartement'] ?? '',
                        'details' => 'Code: ' . ($result['codeDepartement'] ?? '') . ' - ' . ($result['nomRegion'] ?? 'Région inconnue')
                    ];
                }
            } elseif ($type === 'region') {
                foreach ($results as $result) {
                    $data[] = [
                        'id' => $result['min_id'],
                        'nom' => $result['nomRegion'] ?? 'Région non définie',
                        'valeur' => $result['codeRegion'] ?? '',
                        'details' => 'Code: ' . ($result['codeRegion'] ?? '')
                    ];
                }
            } elseif ($type === 'canton') {
                foreach ($results as $result) {
                    $data[] = [
                        'id' => $result['min_id'],
                        'nom' => $result['nomCanton'] ?? 'Canton non défini',
                        'valeur' => $result['codeCanton'] ?? '',
                        'details' => ($result['nomDepartement'] ?? '') . ' (' . ($result['codeDepartement'] ?? '') . ')'
                    ];
                }
            } elseif ($type === 'epci') {
                foreach ($results as $result) {
                    $data[] = [
                        'id' => $result['min_id'],
                        'nom' => $result['nomEpci'] ?? 'EPCI non défini',
                        'valeur' => $result['codeEpci'] ?? '',
                        'details' => ($result['typeEpci'] ?? 'Type inconnu') . ' - ' . ($result['nomDepartement'] ?? 'Département inconnu')
                    ];
                }
            } else {
                // Traitement standard pour les autres types
                foreach ($results as $division) {
                    $data[] = [
                        'id' => $division->getId(),
                        'nom' => $this->getDivisionNom($division, $type),
                        'valeur' => $this->getDivisionValeur($division, $type),
                        'details' => $this->getDivisionDetails($division, $type)
                    ];
                }
            }
            
            return new JsonResponse(['success' => true, 'results' => $data]);
            
        } catch (\Exception $e) {
            error_log("❌ Erreur rechercheDivisions: " . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
    
    // Méthodes utilitaires pour formater les divisions administratives
    private function getDivisionNom(DivisionAdministrative $division, string $type): string
    {
        switch ($type) {
            case 'code_postal':
                return $division->getCodePostal();
            case 'commune':
                return $division->getNomCommune() . ' (' . $division->getCodePostal() . ')';
            case 'canton':
                return $division->getNomCanton() ?: 'Canton non défini';
            case 'epci':
                return $division->getNomEpci() ?: 'EPCI non défini';
            case 'departement':
                return $division->getNomDepartement() ?: 'Département non défini';
            case 'region':
                return $division->getNomRegion() ?: 'Région non définie';
            default:
                return $division->getNomCommune();
        }
    }
    
    private function getDivisionValeur(DivisionAdministrative $division, string $type): string
    {
        switch ($type) {
            case 'code_postal':
                return $division->getCodePostal();
            case 'commune':
                return $division->getCodeInseeCommune();
            case 'canton':
                return $division->getCodeCanton() ?: '';
            case 'epci':
                return $division->getCodeEpci() ?: '';
            case 'departement':
                return $division->getCodeDepartement() ?: '';
            case 'region':
                return $division->getCodeRegion() ?: '';
            default:
                return $division->getCodeInseeCommune();
        }
    }
    
    private function getDivisionDetails(DivisionAdministrative $division, string $type): string
    {
        switch ($type) {
            case 'code_postal':
                return $division->getNomDepartement() . ' (' . $division->getCodeDepartement() . ')';
            case 'commune':
                return $division->getCodePostal() . ' - ' . $division->getNomDepartement();
            case 'canton':
                return $division->getNomDepartement() . ' (' . $division->getCodeDepartement() . ')';
            case 'epci':
                return $division->getTypeEpci() . ' - ' . $division->getNomDepartement();
            case 'departement':
                return 'Code: ' . $division->getCodeDepartement() . ' - ' . $division->getNomRegion();
            case 'region':
                return 'Code: ' . $division->getCodeRegion();
            default:
                return $division->getNomDepartement();
        }
    }

    #[Route('/divisions-administratives', name: 'app_admin_divisions_administratives', methods: ['GET'])]
    public function divisionsAdministratives(EntityManagerInterface $entityManager): Response
    {
        $divisions = $entityManager->getRepository(DivisionAdministrative::class)
            ->rechercheAvancee(['limit' => 100]);

        $statistiques = $entityManager->getRepository(DivisionAdministrative::class)
            ->getStatistiquesCouverture();

        return $this->render('admin/divisions_administratives.html.twig', [
            'divisions' => $divisions,
            'statistiques' => $statistiques,
        ]);
    }

    #[Route('/divisions-administratives/search', name: 'app_admin_divisions_search', methods: ['GET'])]
    public function searchDivisions(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $terme = $request->query->get('terme', '');
        $type = $request->query->get('type');
        $limit = (int) $request->query->get('limit', 50);

        $divisions = $entityManager->getRepository(DivisionAdministrative::class)
            ->search($terme, $type, $limit);

        $results = [];
        foreach ($divisions as $division) {
            $results[] = [
                'id' => $division->getId(),
                'code_postal' => $division->getCodePostal(),
                'nom_commune' => $division->getNomCommune(),
                'nom_departement' => $division->getNomDepartement(),
                'nom_region' => $division->getNomRegion(),
                'affichage_complet' => $division->getAffichageComplet()
            ];
        }

        return $this->json($results);
    }

    #[Route('/types-secteur', name: 'app_admin_types_secteur', methods: ['GET'])]
    public function typesSecteur(EntityManagerInterface $entityManager): Response
    {
        $types = $entityManager->getRepository(TypeSecteur::class)->findAllOrdered();
        $statistiques = $entityManager->getRepository(TypeSecteur::class)->getStatistiquesUtilisation();

        return $this->render('admin/types_secteur.html.twig', [
            'types_secteur' => $types,
            'statistiques' => $statistiques,
        ]);
    }

    #[Route('/types-secteur/create', name: 'app_admin_types_secteur_create', methods: ['POST'])]
    public function createTypeSecteur(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['nom']) || empty($data['type'])) {
            return $this->json(['error' => 'Nom et type requis'], 400);
        }

        $typeSecteur = new TypeSecteur();
        $typeSecteur->setNom($data['nom'])
                   ->setType($data['type'])
                   ->setDescription($data['description'] ?? null)
                   ->setActif($data['actif'] ?? true);

        // Générer un code unique automatiquement
        $code = $entityManager->getRepository(TypeSecteur::class)->genererCodeUnique($data['nom']);
        $typeSecteur->setCode($code);

        // Assigner l'ordre
        if (!empty($data['ordre'])) {
            $entityManager->getRepository(TypeSecteur::class)->insererAOrdre($typeSecteur, (int) $data['ordre']);
        } else {
            $ordre = $entityManager->getRepository(TypeSecteur::class)->getProchainOrdre();
            $typeSecteur->setOrdre($ordre);
            $entityManager->persist($typeSecteur);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'message' => 'Type de secteur créé avec succès',
            'data' => [
                'id' => $typeSecteur->getId(),
                'code' => $typeSecteur->getCode(),
                'nom' => $typeSecteur->getNom(),
                'type' => $typeSecteur->getType(),
                'ordre' => $typeSecteur->getOrdre()
            ]
        ]);
    }

    #[Route('/types-secteur/{id}', name: 'app_admin_types_secteur_update', methods: ['PUT'])]
    public function updateTypeSecteur(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $typeSecteur = $entityManager->getRepository(TypeSecteur::class)->find($id);
        if (!$typeSecteur) {
            return $this->json(['error' => 'Type de secteur non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Valider les données
        $erreurs = $entityManager->getRepository(TypeSecteur::class)->validerDonnees($typeSecteur);
        if (!empty($erreurs)) {
            return $this->json(['error' => implode(', ', $erreurs)], 400);
        }

        if (isset($data['nom'])) $typeSecteur->setNom($data['nom']);
        if (isset($data['description'])) $typeSecteur->setDescription($data['description']);
        if (isset($data['actif'])) $typeSecteur->setActif($data['actif']);
        
        if (isset($data['ordre']) && $data['ordre'] != $typeSecteur->getOrdre()) {
            $entityManager->getRepository(TypeSecteur::class)->insererAOrdre($typeSecteur, (int) $data['ordre']);
        } else {
            $entityManager->flush();
        }

        return $this->json(['success' => true, 'message' => 'Type de secteur mis à jour avec succès']);
    }

    #[Route('/types-secteur/{id}', name: 'app_admin_types_secteur_delete', methods: ['DELETE'])]
    public function deleteTypeSecteur(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $typeSecteur = $entityManager->getRepository(TypeSecteur::class)->find($id);
        if (!$typeSecteur) {
            return $this->json(['error' => 'Type de secteur non trouvé'], 404);
        }

        // Vérifier qu'aucun secteur n'utilise ce type
        $nbSecteurs = $entityManager->getRepository(TypeSecteur::class)->countSecteursUtilisant($typeSecteur);
        if ($nbSecteurs > 0) {
            return $this->json([
                'error' => "Impossible de supprimer : {$nbSecteurs} secteur(s) utilisent ce type"
            ], 400);
        }

        try {
            $entityManager->remove($typeSecteur);
            $entityManager->flush();
            
            // Réorganiser les ordres
            $entityManager->getRepository(TypeSecteur::class)->reorganizeOrdres();

            return $this->json(['success' => true, 'message' => 'Type de secteur supprimé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], 400);
        }
    }

    /**
     * API pour récupérer les données géographiques d'un secteur pour la cartographie
     */
    #[Route('/secteur/{id}/geo-data', name: 'app_admin_secteur_geo_data', methods: ['GET'])]
    public function getSecteurGeoData(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $secteur = $entityManager->getRepository(Secteur::class)->find($id);
            
            if (!$secteur) {
                return $this->json(['error' => 'Secteur non trouvé'], 404);
            }

            $geoData = [
                'secteur' => [
                    'id' => $secteur->getId(),
                    'nom' => $secteur->getNomSecteur(),
                    'couleur' => $secteur->getCouleurHex() ?: '#3498db', // Couleur par défaut bleue
                    'commercial' => $secteur->getCommercial()?->getNom()
                ],
                'attributions' => [],
                'bounds' => null // Sera calculé
            ];

            $minLat = $minLng = PHP_FLOAT_MAX;
            $maxLat = $maxLng = PHP_FLOAT_MIN;
            $hasCoordinates = false;

            foreach ($secteur->getAttributions() as $attribution) {
                $division = $attribution->getDivisionAdministrative();
                if (!$division) continue;

                $attributionData = [
                    'id' => $attribution->getId(),
                    'type' => $attribution->getTypeCritere(),
                    'valeur' => $attribution->getValeurCritere(),
                    'nom' => (string) $attribution,
                    'notes' => $attribution->getNotes(),
                    'coordinates' => []
                ];

                // Pour les EPCI, récupérer toutes les communes de cet EPCI
                if ($attribution->getTypeCritere() === 'epci') {
                    $communesEpci = $entityManager->getRepository(DivisionAdministrative::class)
                        ->createQueryBuilder('d')
                        ->where('d.codeEpci = :codeEpci')
                        ->andWhere('d.actif = true')
                        ->andWhere('d.latitude IS NOT NULL')
                        ->andWhere('d.longitude IS NOT NULL')
                        ->setParameter('codeEpci', $division->getCodeEpci())
                        ->getQuery()
                        ->getResult();

                    foreach ($communesEpci as $commune) {
                        $lat = (float) $commune->getLatitude();
                        $lng = (float) $commune->getLongitude();
                        
                        $attributionData['coordinates'][] = [
                            'lat' => $lat,
                            'lng' => $lng,
                            'commune' => $commune->getNomCommune(),
                            'codePostal' => $commune->getCodePostal()
                        ];

                        // Mettre à jour les bounds
                        $minLat = min($minLat, $lat);
                        $maxLat = max($maxLat, $lat);
                        $minLng = min($minLng, $lng);
                        $maxLng = max($maxLng, $lng);
                        $hasCoordinates = true;
                    }
                } else {
                    // Pour les autres types, essayer de récupérer les vraies frontières
                    $attributionData['boundary_type'] = 'real';
                    
                    // Déterminer le code et le type pour l'API
                    $code = null;
                    $apiType = null;
                    
                    switch ($attribution->getTypeCritere()) {
                        case 'code_postal':
                            $code = $attribution->getValeurCritere();
                            $apiType = 'code_postal';
                            break;
                        case 'canton':
                            $code = $attribution->getValeurCritere();
                            $apiType = 'canton';
                            break;
                        case 'departement':
                            $code = $division->getCodeDepartement();
                            $apiType = 'departement';
                            break;
                        case 'region':
                            $code = $division->getCodeRegion();
                            $apiType = 'region';
                            break;
                        case 'commune':
                            $code = $division->getCodeInseeCommune();
                            $apiType = 'commune';
                            break;
                    }
                    
                    if ($code && $apiType) {
                        $attributionData['api_type'] = $apiType;
                        $attributionData['api_code'] = $code;
                        error_log("🗺️ Marquage pour frontières réelles: {$apiType} {$code}");
                    } else {
                        error_log("❌ Pas de code/apiType pour " . $attribution->getTypeCritere() . " = " . $attribution->getValeurCritere());
                    }
                    
                    // Fallback vers point unique si nécessaire pour centrage
                    if ($division->getLatitude() && $division->getLongitude()) {
                        $lat = (float) $division->getLatitude();
                        $lng = (float) $division->getLongitude();
                        
                        $attributionData['coordinates'][] = [
                            'lat' => $lat,
                            'lng' => $lng,
                            'commune' => $division->getNomCommune(),
                            'codePostal' => $division->getCodePostal()
                        ];

                        // Mettre à jour les bounds
                        $minLat = min($minLat, $lat);
                        $maxLat = max($maxLat, $lat);
                        $minLng = min($minLng, $lng);
                        $maxLng = max($maxLng, $lng);
                        $hasCoordinates = true;
                    }
                }

                // Ajouter des informations spécifiques selon le type
                switch ($attribution->getTypeCritere()) {
                    case 'code_postal':
                        $attributionData['codePostal'] = $division->getCodePostal();
                        $attributionData['commune'] = $division->getNomCommune();
                        $attributionData['departement'] = $division->getNomDepartement();
                        break;
                    case 'commune':
                        $attributionData['commune'] = $division->getNomCommune();
                        $attributionData['codePostal'] = $division->getCodePostal();
                        $attributionData['codeInsee'] = $division->getCodeInseeCommune(); // Ajout code INSEE pour géométries
                        $attributionData['departement'] = $division->getNomDepartement();
                        error_log("🔍 DEBUG: COMMUNE - nom=" . $division->getNomCommune() . " codeInsee=" . $division->getCodeInseeCommune());
                        break;
                    case 'epci':
                        $attributionData['epci'] = $division->getNomEpci();
                        $attributionData['codeEpci'] = $division->getCodeEpci();
                        $attributionData['typeEpci'] = $division->getTypeEpci();
                        $attributionData['departement'] = $division->getNomDepartement();
                        break;
                    case 'departement':
                        $attributionData['departement'] = $division->getNomDepartement();
                        $attributionData['codeDepartement'] = $division->getCodeDepartement();
                        $attributionData['region'] = $division->getNomRegion();
                        break;
                    case 'region':
                        $attributionData['region'] = $division->getNomRegion();
                        $attributionData['codeRegion'] = $division->getCodeRegion();
                        break;
                }

                $geoData['attributions'][] = $attributionData;
            }

            // Calculer les bounds si on a des coordonnées
            if ($hasCoordinates) {
                // Ajouter une marge de 10% pour un meilleur affichage
                $latMargin = ($maxLat - $minLat) * 0.1;
                $lngMargin = ($maxLng - $minLng) * 0.1;
                
                $geoData['bounds'] = [
                    'southwest' => [
                        'lat' => $minLat - $latMargin,
                        'lng' => $minLng - $lngMargin
                    ],
                    'northeast' => [
                        'lat' => $maxLat + $latMargin,
                        'lng' => $maxLng + $lngMargin
                    ]
                ];

                // Centre de la carte
                $geoData['center'] = [
                    'lat' => ($minLat + $maxLat) / 2,
                    'lng' => ($minLng + $maxLng) / 2
                ];
            }

            return $this->json($geoData);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API pour récupérer tous les secteurs avec leurs données géographiques pour la carte générale
     */
    #[Route('/secteurs/all-geo-data', name: 'app_admin_secteurs_all_geo_data', methods: ['GET'])]
    public function getAllSecteursGeoData(EntityManagerInterface $entityManager, EpciBoundariesService $epciBoundariesService, CommuneGeometryService $communeGeometryService, \App\Service\CommuneGeometryCacheService $cacheService): JsonResponse
    {
        error_log("🔍 DEBUG: getAllSecteursGeoData appelée");
        try {
            $secteurs = $entityManager->getRepository(Secteur::class)
                ->createQueryBuilder('s')
                ->where('s.isActive = true')
                ->orderBy('s.nomSecteur', 'ASC')
                ->getQuery()
                ->getResult();

            $secteursData = [];

            error_log("🔍 DEBUG: " . count($secteurs) . " secteurs trouvés");
            if (count($secteurs) === 0) {
                error_log("❌ Aucun secteur actif trouvé dans la base");
                return $this->json(['success' => true, 'secteurs' => [], 'total' => 0]);
            }
            foreach ($secteurs as $secteur) {
                error_log("🔍 DEBUG: Traitement secteur " . $secteur->getNomSecteur());
                $secteurInfo = [
                    'id' => $secteur->getId(),
                    'nom' => $secteur->getNomSecteur(),
                    'couleur' => $secteur->getCouleurHex() ?: '#3498db',
                    'commercial' => $secteur->getCommercial() ? 
                        trim(($secteur->getCommercial()->getPrenom() ?: '') . ' ' . ($secteur->getCommercial()->getNom() ?: '')) : 
                        null,
                    'description' => $secteur->getDescription(),
                    'isActive' => $secteur->getIsActive(),
                    'attributions' => [],
                    'bounds' => null,
                    'center' => null,
                    'hasCoordinates' => false
                ];

                $minLat = $minLng = PHP_FLOAT_MAX;
                $maxLat = $maxLng = PHP_FLOAT_MIN;
                $hasCoordinates = false;

                error_log("🔍 DEBUG: Secteur " . $secteur->getNomSecteur() . " a " . count($secteur->getAttributions()) . " attributions");
                foreach ($secteur->getAttributions() as $attribution) {
                    $division = $attribution->getDivisionAdministrative();
                    if (!$division) continue;
                    error_log("🔍 DEBUG: Attribution type=" . $attribution->getTypeCritere() . " valeur=" . $attribution->getValeurCritere());

                    $attributionData = [
                        'id' => $attribution->getId(),
                        'type' => $attribution->getTypeCritere(),
                        'valeur' => $attribution->getValeurCritere(),
                        'nom' => (string) $attribution,
                        'coordinates' => []
                    ];

                    // Pour les EPCI, récupérer les communes directement
                    if ($attribution->getTypeCritere() === 'epci') {
                        error_log("🏢 Récupération communes pour EPCI: " . $division->getCodeEpci());
                        
                        // Récupérer toutes les communes de cet EPCI
                        $communes = $entityManager->createQuery('
                            SELECT d FROM App\Entity\DivisionAdministrative d 
                            WHERE d.codeEpci = :codeEpci 
                            AND d.codeInseeCommune IS NOT NULL
                            ORDER BY d.nomCommune
                        ')
                        ->setParameter('codeEpci', $division->getCodeEpci())
                        ->getResult();
                        
                        // Récupérer les exclusions pour cette attribution
                        $exclusions = $entityManager->getRepository(ExclusionSecteur::class)
                            ->findBy(['attributionSecteur' => $attribution]);
                        
                        // Créer un tableau des codes INSEE exclus (gérer tous types d'exclusions)
                        $communesExclues = [];
                        foreach ($exclusions as $exclusion) {
                            // Pour EPCI, on peut avoir des exclusions de communes, codes postaux, ou autres EPCIs
                            switch ($exclusion->getTypeExclusion()) {
                                case 'commune':
                                    $communesExclues[] = $exclusion->getValeurExclusion();
                                    error_log("🚫 Exclusion commune " . $exclusion->getValeurExclusion() . " de l'EPCI " . $division->getCodeEpci());
                                    break;
                                case 'code_postal':
                                    // Trouver toutes les communes de ce code postal et les exclure
                                    $communesCodePostal = $entityManager->createQuery('
                                        SELECT d.codeInseeCommune FROM App\Entity\DivisionAdministrative d 
                                        WHERE d.codePostal = :codePostal 
                                        AND d.codeInseeCommune IS NOT NULL
                                    ')
                                    ->setParameter('codePostal', $exclusion->getValeurExclusion())
                                    ->getResult();
                                    foreach ($communesCodePostal as $result) {
                                        $communesExclues[] = $result['codeInseeCommune'];
                                    }
                                    error_log("🚫 Exclusion code postal " . $exclusion->getValeurExclusion() . " de l'EPCI " . $division->getCodeEpci() . " (" . count($communesCodePostal) . " communes)");
                                    break;
                            }
                        }
                        
                        // Filtrer les communes pour exclure celles qui sont dans des secteurs plus spécifiques
                        $communes = array_filter($communes, function($commune) use ($communesExclues) {
                            return !in_array($commune->getCodeInseeCommune(), $communesExclues);
                        });

                        // Préparer les données des communes pour le service de cache
                        $communesInput = [];
                        foreach ($communes as $commune) {
                            $communesInput[] = [
                                'codeInseeCommune' => $commune->getCodeInseeCommune(),
                                'nomCommune' => $commune->getNomCommune()
                            ];
                        }

                        // Utiliser le service de cache pour récupérer TOUTES les géométries (plus de limite !)
                        error_log("🚀 Utilisation du service de cache pour " . count($communesInput) . " communes");
                        $communesData = $cacheService->getMultipleCommunesGeometry($communesInput);

                        // Mettre à jour les bounds avec toutes les communes récupérées
                        foreach ($communesData as $commune) {
                            if (isset($commune['coordinates']) && is_array($commune['coordinates'])) {
                                // Calculer les limites pour l'ensemble
                                foreach ($commune['coordinates'] as $coord) {
                                    if (isset($coord['lat']) && isset($coord['lng'])) {
                                        $minLat = min($minLat, $coord['lat']);
                                        $maxLat = max($maxLat, $coord['lat']);
                                        $minLng = min($minLng, $coord['lng']);
                                        $maxLng = max($maxLng, $coord['lng']);
                                        $hasCoordinates = true;
                                    }
                                }
                                error_log("✅ Géométrie récupérée pour " . $commune['nom'] . " (" . count($commune['coordinates']) . " points) - Source: " . ($commune['source'] ?? 'unknown'));
                            }
                        }

                        if (!empty($communesData)) {
                            $attributionData['communes'] = $communesData; // Inclure les communes dans les données
                            $attributionData['boundary_type'] = 'communes_reelles'; // Nouveau type pour différencier
                            $attributionData['center'] = [
                                'lat' => ($minLat + $maxLat) / 2,
                                'lng' => ($minLng + $maxLng) / 2
                            ];
                            error_log("🎯 EPCI " . $division->getNomEpci() . " - " . count($communesData) . " communes avec vraies géométries");
                        } else {
                            // Fallback sur l'ancienne méthode si pas de frontières en cache
                            $communesEpci = $entityManager->getRepository(DivisionAdministrative::class)
                                ->createQueryBuilder('d')
                                ->where('d.codeEpci = :codeEpci')
                                ->andWhere('d.actif = true')
                                ->andWhere('d.latitude IS NOT NULL')
                                ->andWhere('d.longitude IS NOT NULL')
                                ->setParameter('codeEpci', $division->getCodeEpci())
                                ->getQuery()
                                ->getResult();

                            foreach ($communesEpci as $commune) {
                                $lat = (float) $commune->getLatitude();
                                $lng = (float) $commune->getLongitude();
                                
                                $attributionData['coordinates'][] = [
                                    'lat' => $lat,
                                    'lng' => $lng
                                ];

                                $minLat = min($minLat, $lat);
                                $maxLat = max($maxLat, $lat);
                                $minLng = min($minLng, $lng);
                                $maxLng = max($maxLng, $lng);
                                $hasCoordinates = true;
                            }
                            $attributionData['boundary_type'] = 'convex_hull'; // Indiquer qu'on utilise l'enveloppe convexe
                        }
                    } else {
                        // Pour les autres types, appliquer les exclusions avant de récupérer les frontières
                        $attributionData['boundary_type'] = 'real';
                        
                        // Récupérer les exclusions pour cette attribution
                        $exclusions = $entityManager->getRepository(ExclusionSecteur::class)
                            ->findBy(['attributionSecteur' => $attribution]);
                        
                        // Appliquer les exclusions selon le type d'attribution
                        $exclusionData = $this->appliquerExclusionsAffichage($attribution, $exclusions, $entityManager);
                        if (!empty($exclusionData)) {
                            $attributionData['exclusions'] = $exclusionData;
                            error_log("🚫 Attribution " . $attribution->getTypeCritere() . " '" . $attribution->getValeurCritere() . "' a " . count($exclusionData) . " exclusions");
                        }
                        
                        // Déterminer le code et le type pour l'API
                        $code = null;
                        $apiType = null;
                        
                        switch ($attribution->getTypeCritere()) {
                            case 'code_postal':
                                $code = $attribution->getValeurCritere();
                                $apiType = 'code_postal';
                                break;
                            case 'canton':
                                $code = $attribution->getValeurCritere();
                                $apiType = 'canton';
                                break;
                            case 'departement':
                                $code = $division->getCodeDepartement();
                                $apiType = 'departement';
                                break;
                            case 'region':
                                $code = $division->getCodeRegion();
                                $apiType = 'region';
                                break;
                            case 'commune':
                                $code = $division->getCodeInseeCommune();
                                $apiType = 'commune';
                                break;
                        }
                        
                        if ($code && $apiType) {
                            $attributionData['api_type'] = $apiType;
                            $attributionData['api_code'] = $code;
                            error_log("🗺️ CORRECT: Marquage pour frontières réelles: {$apiType} {$code}");
                        } else {
                            error_log("❌ CORRECT: Pas de code/apiType pour " . $attribution->getTypeCritere() . " = " . $attribution->getValeurCritere());
                        }
                        
                        // Fallback vers point unique pour le centrage
                        if ($division->getLatitude() && $division->getLongitude()) {
                            $lat = (float) $division->getLatitude();
                            $lng = (float) $division->getLongitude();
                            
                            $attributionData['coordinates'][] = [
                                'lat' => $lat,
                                'lng' => $lng
                            ];

                            $minLat = min($minLat, $lat);
                            $maxLat = max($maxLat, $lat);
                            $minLng = min($minLng, $lng);
                            $maxLng = max($maxLng, $lng);
                            $hasCoordinates = true;
                        }
                    }

                    $secteurInfo['attributions'][] = $attributionData;
                }

                // Calculer les bounds et centre pour ce secteur
                error_log("🔍 Secteur {$secteur->getNomSecteur()}: hasCoordinates = " . ($hasCoordinates ? 'true' : 'false') . ", attributions = " . count($secteur->getAttributions()));
                if ($hasCoordinates) {
                    $latMargin = ($maxLat - $minLat) * 0.1;
                    $lngMargin = ($maxLng - $minLng) * 0.1;
                    
                    $secteurInfo['bounds'] = [
                        'southwest' => [
                            'lat' => $minLat - $latMargin,
                            'lng' => $minLng - $lngMargin
                        ],
                        'northeast' => [
                            'lat' => $maxLat + $latMargin,
                            'lng' => $maxLng + $lngMargin
                        ]
                    ];

                    $secteurInfo['center'] = [
                        'lat' => ($minLat + $maxLat) / 2,
                        'lng' => ($minLng + $maxLng) / 2
                    ];

                    $secteurInfo['hasCoordinates'] = true;
                }

                $secteursData[] = $secteurInfo;
            }

            error_log("🎯 FINAL: Retour de " . count($secteursData) . " secteurs");
            return $this->json([
                'success' => true,
                'secteurs' => $secteursData,
                'total' => count($secteursData),
                'debug' => [
                    'found_sectors' => count($secteurs),
                    'processed_sectors' => count($secteursData),
                    'first_sector_name' => count($secteurs) > 0 ? $secteurs[0]->getNomSecteur() : 'none'
                ]
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getAllSecteursGeoData: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de la récupération des secteurs'], 500);
        }
    }

    #[Route('/commune/{codeInsee}/geometry', name: 'app_admin_commune_geometry', methods: ['GET'])]
    public function getCommuneGeometry(string $codeInsee, CommuneGeometryService $communeGeometryService, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Récupérer les infos de la commune depuis la base
            $commune = $entityManager->getRepository(DivisionAdministrative::class)
                ->findOneBy(['codeInseeCommune' => $codeInsee]);
            
            if (!$commune) {
                return $this->json(['error' => 'Commune non trouvée'], 404);
            }

            // Récupérer la géométrie réelle
            $geometry = $communeGeometryService->getCommuneGeometry($codeInsee, $commune->getNomCommune());
            
            if (!$geometry) {
                return $this->json(['error' => 'Géométrie non disponible pour cette commune'], 404);
            }

            return $this->json([
                'success' => true,
                'commune' => [
                    'codeInsee' => $codeInsee,
                    'nom' => $commune->getNomCommune(),
                    'codePostal' => $commune->getCodePostal(),
                    'geometry' => $geometry
                ]
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getCommuneGeometry: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de la récupération de la géométrie'], 500);
        }
    }

    #[Route('/debug/secteurs', name: 'app_admin_debug_secteurs', methods: ['GET'])]
    public function debugSecteurs(): Response
    {
        return $this->render('admin/debug_secteurs.html.twig');
    }

    /**
     * Debug endpoint to check attribution data
     */
    #[Route('/debug/attributions', name: 'app_admin_debug_attributions', methods: ['GET'])]
    public function debugAttributions(EntityManagerInterface $entityManager): JsonResponse
    {
        $secteurs = $entityManager->getRepository(Secteur::class)->findAll();
        $debug = [];
        
        foreach ($secteurs as $secteur) {
            $secteurData = [
                'id' => $secteur->getId(),
                'nom' => $secteur->getNomSecteur(),
                'attributions' => []
            ];
            
            foreach ($secteur->getAttributions() as $attribution) {
                $division = $attribution->getDivisionAdministrative();
                if (!$division) continue;
                
                $attributionData = [
                    'type' => $attribution->getTypeCritere(),
                    'valeur' => $attribution->getValeurCritere(),
                    'nom' => (string) $attribution,
                ];
                
                // Simuler la logique de marquage
                if ($attribution->getTypeCritere() !== 'epci') {
                    $attributionData['boundary_type'] = 'real';
                    
                    $code = null;
                    $apiType = null;
                    
                    switch ($attribution->getTypeCritere()) {
                        case 'code_postal':
                            $code = $attribution->getValeurCritere();
                            $apiType = 'code_postal';
                            break;
                        case 'canton':
                            $code = $attribution->getValeurCritere();
                            $apiType = 'canton';
                            break;
                        case 'departement':
                            $code = $division->getCodeDepartement();
                            $apiType = 'departement';
                            break;
                        case 'commune':
                            $code = $division->getCodeInseeCommune();
                            $apiType = 'commune';
                            break;
                    }
                    
                    if ($code && $apiType) {
                        $attributionData['api_type'] = $apiType;
                        $attributionData['api_code'] = $code;
                        $attributionData['debug'] = "SHOULD_CALL_API: {$apiType}/{$code}";
                    }
                }
                
                $secteurData['attributions'][] = $attributionData;
            }
            
            $debug[] = $secteurData;
        }
        
        return $this->json(['debug_data' => $debug]);
    }

    /**
     * API pour récupérer les frontières géographiques selon le type de zone
     */
    #[Route('/boundaries/{type}/{code}', name: 'app_admin_boundaries', methods: ['GET'])]
    public function getBoundaries(
        string $type, 
        string $code, 
        GeographicBoundariesService $boundariesService
    ): JsonResponse {
        try {
            $boundaries = $boundariesService->getBoundariesByType($type, $code);
            
            if (!$boundaries) {
                return $this->json(['error' => "Frontières non disponibles pour {$type} {$code}"], 404);
            }

            return $this->json([
                'success' => true,
                'boundaries' => $boundaries
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getBoundaries {$type}/{$code}: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de la récupération des frontières'], 500);
        }
    }

    /**
     * API pour récupérer les frontières d'un code postal
     */
    #[Route('/code-postal/{codePostal}/boundaries', name: 'app_admin_code_postal_boundaries', methods: ['GET'])]
    public function getCodePostalBoundaries(
        string $codePostal, 
        GeographicBoundariesService $boundariesService
    ): JsonResponse {
        try {
            $boundaries = $boundariesService->getCodePostalBoundaries($codePostal);
            
            if (!$boundaries) {
                return $this->json(['error' => "Frontières non disponibles pour le code postal {$codePostal}"], 404);
            }

            return $this->json([
                'success' => true,
                'code_postal' => $codePostal,
                'boundaries' => $boundaries
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getCodePostalBoundaries {$codePostal}: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de la récupération des frontières'], 500);
        }
    }

    /**
     * API pour récupérer les frontières d'un canton
     */
    #[Route('/canton/{codeCanton}/boundaries', name: 'app_admin_canton_boundaries', methods: ['GET'])]
    public function getCantonBoundaries(
        string $codeCanton, 
        GeographicBoundariesService $boundariesService
    ): JsonResponse {
        try {
            $boundaries = $boundariesService->getCantonBoundaries($codeCanton);
            
            if (!$boundaries) {
                return $this->json(['error' => "Frontières non disponibles pour le canton {$codeCanton}"], 404);
            }

            return $this->json([
                'success' => true,
                'canton' => $codeCanton,
                'boundaries' => $boundaries
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getCantonBoundaries {$codeCanton}: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de la récupération des frontières'], 500);
        }
    }

    /**
     * API pour récupérer les frontières d'un département
     */
    #[Route('/departement/{codeDepartement}/boundaries', name: 'app_admin_departement_boundaries', methods: ['GET'])]
    public function getDepartementBoundaries(
        string $codeDepartement, 
        GeographicBoundariesService $boundariesService
    ): JsonResponse {
        try {
            $boundaries = $boundariesService->getDepartementBoundaries($codeDepartement);
            
            if (!$boundaries) {
                return $this->json(['error' => "Frontières non disponibles pour le département {$codeDepartement}"], 404);
            }

            return $this->json([
                'success' => true,
                'departement' => $codeDepartement,
                'boundaries' => $boundaries
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getDepartementBoundaries {$codeDepartement}: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de la récupération des frontières'], 500);
        }
    }

    /**
     * API pour récupérer les frontières d'une région
     */
    #[Route('/region/{codeRegion}/boundaries', name: 'app_admin_region_boundaries', methods: ['GET'])]
    public function getRegionBoundaries(
        string $codeRegion, 
        GeographicBoundariesService $boundariesService
    ): JsonResponse {
        try {
            $boundaries = $boundariesService->getRegionBoundaries($codeRegion);
            
            if (!$boundaries) {
                return $this->json(['error' => "Frontières non disponibles pour la région {$codeRegion}"], 404);
            }

            return $this->json([
                'success' => true,
                'region' => $codeRegion,
                'boundaries' => $boundaries
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getRegionBoundaries {$codeRegion}: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de la récupération des frontières'], 500);
        }
    }

    /**
     * Récupère la géométrie d'une commune directement depuis l'API officielle
     */
    private function fetchCommuneGeometryDirect(string $codeInsee, string $nomCommune): ?array
    {
        try {
            $url = "https://geo.api.gouv.fr/communes/{$codeInsee}?geometry=contour&format=geojson";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5, // Réduire timeout pour éviter blocage
                    'method' => 'GET'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                error_log("❌ Erreur API pour commune $nomCommune ($codeInsee)");
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['geometry'])) {
                error_log("❌ Pas de géométrie pour commune $nomCommune ($codeInsee)");
                return null;
            }
            
            // Convertir la géométrie GeoJSON en format compatible
            $boundaries = $this->extractBoundariesFromGeoJSON($data['geometry']);
            
            if (empty($boundaries)) {
                error_log("❌ Conversion géométrie échouée pour $nomCommune ($codeInsee)");
                return null;
            }
            
            error_log("✅ Géométrie récupérée pour $nomCommune: " . count($boundaries) . " points");
            
            return [
                'geometry' => [
                    'boundaries' => $boundaries,
                    'source' => 'api_officielle_directe'
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("❌ Exception géométrie $nomCommune ($codeInsee): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrait les coordonnées d'une géométrie GeoJSON
     */
    private function extractBoundariesFromGeoJSON(array $geometry): array
    {
        $boundaries = [];
        
        if ($geometry['type'] === 'Polygon') {
            // Polygon simple - prendre le contour extérieur
            foreach ($geometry['coordinates'][0] as $coord) {
                $boundaries[] = [
                    'lat' => $coord[1],
                    'lng' => $coord[0]
                ];
            }
        } elseif ($geometry['type'] === 'MultiPolygon') {
            // MultiPolygon - prendre le plus grand polygone
            $largestPolygon = [];
            $maxPoints = 0;
            
            foreach ($geometry['coordinates'] as $polygon) {
                $pointCount = count($polygon[0]);
                if ($pointCount > $maxPoints) {
                    $maxPoints = $pointCount;
                    $largestPolygon = $polygon[0];
                }
            }
            
            foreach ($largestPolygon as $coord) {
                $boundaries[] = [
                    'lat' => $coord[1],
                    'lng' => $coord[0]
                ];
            }
        }
        
        return $boundaries;
    }

    /**
     * Gère les exclusions avant la suppression d'une attribution
     */
    private function gererExclusionsAvantSuppression(AttributionSecteur $attribution, EntityManagerInterface $entityManager): void
    {
        // 1. Supprimer toutes les exclusions liées à cette attribution (si c'est une attribution parente comme EPCI)
        $exclusionsParentes = $entityManager->getRepository(ExclusionSecteur::class)
            ->findBy(['attributionSecteur' => $attribution]);
        
        foreach ($exclusionsParentes as $exclusion) {
            error_log("🗑️ Suppression exclusion ID " . $exclusion->getId() . " liée à l'attribution supprimée");
            $entityManager->remove($exclusion);
        }
        
        // 2. Si c'est une attribution spécifique (commune), supprimer les exclusions qu'elle a créées dans d'autres attributions
        if ($attribution->getTypeCritere() === 'commune') {
            $exclusionsCrees = $entityManager->getRepository(ExclusionSecteur::class)
                ->findBy([
                    'divisionAdministrative' => $attribution->getDivisionAdministrative(),
                    'typeExclusion' => 'commune',
                    'valeurExclusion' => $attribution->getValeurCritere()
                ]);
            
            foreach ($exclusionsCrees as $exclusion) {
                $secteurParent = $exclusion->getAttributionSecteur()->getSecteur()->getNomSecteur();
                error_log("🔄 Suppression exclusion de la commune {$attribution->getValeurCritere()} dans le secteur '$secteurParent'");
                $entityManager->remove($exclusion);
            }
        }
        
        $entityManager->flush();
    }

    /**
     * Applique les règles d'exclusion géographique selon la hiérarchie :
     * commune < code postal < EPCI < département < région
     * 
     * Quand une zone plus petite est incluse dans un secteur, 
     * alors cette même zone est exclue des ensembles plus grands dans les autres secteurs.
     */
    private function appliquerReglesExclusionGeographique(AttributionSecteur $nouvelleAttribution, EntityManagerInterface $entityManager): void
    {
        try {
            $division = $nouvelleAttribution->getDivisionAdministrative();
            $secteurCible = $nouvelleAttribution->getSecteur();
            $typeCritere = $nouvelleAttribution->getTypeCritere();
            
            error_log("🔄 Application règles exclusion pour $typeCritere dans secteur " . $secteurCible->getNomSecteur());
        
        // Traitement spécial pour les codes postaux
        if ($typeCritere === 'code_postal') {
            $this->appliquerExclusionsCodePostal($nouvelleAttribution, $entityManager);
            return;
        }
        
        // Définir la hiérarchie géographique (ordre croissant de spécificité)
        $hierarchie = [
            'region' => 1,
            'departement' => 2, 
            'epci' => 3,
            'code_postal' => 4,
            'commune' => 5
        ];
        
        if (!isset($hierarchie[$typeCritere])) {
            return; // Type non géré par les règles d'exclusion
        }
        
        $prioriteNouvelle = $hierarchie[$typeCritere];
        
        // Récupérer toutes les attributions qui pourraient inclure cette division administrative
        // selon la hiérarchie géographique
        $attributionsExistantes = $this->rechercherAttributionsInclusives($division, $secteurCible, $entityManager);
        
        foreach ($attributionsExistantes as $attributionExistante) {
            $typeExistant = $attributionExistante->getTypeCritere();
            
            if (!isset($hierarchie[$typeExistant])) {
                continue; // Type non géré
            }
            
            $prioriteExistante = $hierarchie[$typeExistant];
            
            // Si la nouvelle attribution est plus spécifique (priorité plus élevée)
            // alors créer une exclusion dans l'attribution moins spécifique
            if ($prioriteNouvelle > $prioriteExistante) {
                // Vérifier si la nouvelle zone est incluse dans la zone existante
                if ($this->estZoneIncluse($nouvelleAttribution, $attributionExistante, $division)) {
                    
                    // Vérifier si l'exclusion n'existe pas déjà
                    $exclusionExistante = $entityManager->getRepository(ExclusionSecteur::class)
                        ->findOneBy([
                            'attributionSecteur' => $attributionExistante,
                            'divisionAdministrative' => $division
                        ]);
                    
                    if (!$exclusionExistante) {
                        // Créer une exclusion au lieu de supprimer l'attribution
                        $exclusion = new ExclusionSecteur();
                        $exclusion->setAttributionSecteur($attributionExistante);
                        $exclusion->setDivisionAdministrative($division);
                        $exclusion->setTypeExclusion($typeCritere);
                        $exclusion->setValeurExclusion($nouvelleAttribution->getValeurCritere());
                        $exclusion->setMotif("Zone plus spécifique assignée au secteur '{$secteurCible->getNomSecteur()}'");
                        
                        $entityManager->persist($exclusion);
                        
                        $secteurAffecte = $attributionExistante->getSecteur()->getNomSecteur();
                        $typeExistant = $attributionExistante->getTypeCritere();
                        $valeurExistante = $attributionExistante->getValeurCritere();
                        
                        error_log("🎯 Exclusion géographique : Création exclusion $typeCritere '{$nouvelleAttribution->getValeurCritere()}' dans $typeExistant '$valeurExistante' du secteur '$secteurAffecte'");
                    }
                }
            }
        }
        
        // CAS INVERSE : Si on ajoute un EPCI/département/région, vérifier s'il y a des communes 
        // déjà attribuées spécifiquement à d'autres secteurs
        if (in_array($typeCritere, ['epci', 'departement', 'region', 'code_postal'])) {
            $this->appliquerExclusionsInverses($nouvelleAttribution, $entityManager);
        }
        
        } catch (\Exception $e) {
            error_log("❌ Erreur dans appliquerReglesExclusionGeographique: " . $e->getMessage());
            error_log("❌ Stack trace exclusion: " . $e->getTraceAsString());
            throw $e; // Re-lancer l'exception pour qu'elle soit capturée par le contrôleur principal
        }
    }
    
    /**
     * Recherche toutes les attributions existantes qui pourraient inclure géographiquement 
     * la division administrative donnée selon la hiérarchie française.
     * 
     * Cette fonction identifie les "zones parentes" qui contiennent la zone à ajouter,
     * afin de créer les exclusions nécessaires.
     * 
     * EXEMPLE :
     * - Pour la commune "Boutx" (31085), trouve :
     *   * Le code postal 31160 (si attribué à un autre secteur)
     *   * L'EPCI "Pyrénées Haut Garonnaises" (si attribué à un autre secteur) 
     *   * Le département "Haute-Garonne" (si attribué à un autre secteur)
     *   * La région "Occitanie" (si attribuée à un autre secteur)
     * 
     * @param DivisionAdministrative $division La division à analyser
     * @param Secteur $secteurCible Le secteur auquel on ajoute l'attribution (à exclure des résultats)
     * @param EntityManagerInterface $entityManager Manager Doctrine
     * @return array Liste des AttributionSecteur qui incluent géographiquement cette division
     */
    private function rechercherAttributionsInclusives(DivisionAdministrative $division, Secteur $secteurCible, EntityManagerInterface $entityManager): array
    {
        // Construire les conditions selon les données de la division
        $conditions = [];
        $parameters = ['secteur' => $secteurCible];
        
        // Code postal : peut être inclus dans EPCI, département, région
        if ($division->getCodePostal()) {
            $conditions[] = '(a.typeCritere = :code_postal AND a.valeurCritere = :codePostalValue)';
            $parameters['codePostalValue'] = $division->getCodePostal();
            
            // IMPORTANT: Un code postal peut être dans plusieurs EPCIs !
            // Rechercher tous les EPCIs qui contiennent au moins une commune de ce code postal
            $conditions[] = '(a.typeCritere = :epci_cp AND d.codeEpci IN (
                SELECT DISTINCT d2.codeEpci 
                FROM App\Entity\DivisionAdministrative d2 
                WHERE d2.codePostal = :codePostalEpci 
                AND d2.codeEpci IS NOT NULL
            ))';
            $parameters['epci_cp'] = 'epci';
            $parameters['codePostalEpci'] = $division->getCodePostal();
        }
        
        // Commune : peut être incluse dans code postal, EPCI, département, région
        if ($division->getCodeInseeCommune()) {
            $conditions[] = '(a.typeCritere = :commune AND a.valeurCritere = :communeValue)';
            $parameters['communeValue'] = $division->getCodeInseeCommune();
            
            // Aussi incluse dans les zones plus larges
            if ($division->getCodePostal()) {
                $conditions[] = '(a.typeCritere = :code_postal_parent AND d.codePostal = :codePostalParent)';
                $parameters['code_postal_parent'] = 'code_postal';
                $parameters['codePostalParent'] = $division->getCodePostal();
            }
        }
        
        // EPCI : peut être inclus dans département, région
        if ($division->getCodeEpci()) {
            $conditions[] = '(a.typeCritere = :epci AND a.valeurCritere = :epciValue)';
            $conditions[] = '(a.typeCritere = :epci_parent AND d.codeEpci = :epciParent)';
            $parameters['epciValue'] = $division->getCodeEpci();
            $parameters['epci_parent'] = 'epci';
            $parameters['epciParent'] = $division->getCodeEpci();
        }
        
        // Département : peut être inclus dans région
        if ($division->getCodeDepartement()) {
            $conditions[] = '(a.typeCritere = :departement AND a.valeurCritere = :departementValue)';
            $conditions[] = '(a.typeCritere = :departement_parent AND d.codeDepartement = :departementParent)';
            $parameters['departementValue'] = $division->getCodeDepartement();
            $parameters['departement_parent'] = 'departement';
            $parameters['departementParent'] = $division->getCodeDepartement();
        }
        
        // Région
        if ($division->getCodeRegion()) {
            $conditions[] = '(a.typeCritere = :region AND a.valeurCritere = :regionValue)';
            $conditions[] = '(a.typeCritere = :region_parent AND d.codeRegion = :regionParent)';
            $parameters['regionValue'] = $division->getCodeRegion();
            $parameters['region_parent'] = 'region';
            $parameters['regionParent'] = $division->getCodeRegion();
        }
        
        if (empty($conditions)) {
            return [];
        }
        
        $qb = $entityManager->getRepository(AttributionSecteur::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.divisionAdministrative', 'd')
            ->where('a.secteur != :secteur')
            ->andWhere('(' . implode(' OR ', $conditions) . ')');
            
        foreach ($parameters as $key => $value) {
            $qb->setParameter($key, $value);
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si une zone plus spécifique est incluse dans une zone plus large
     */
    private function estZoneIncluse(AttributionSecteur $zoneSpecifique, AttributionSecteur $zoneLarge, DivisionAdministrative $division): bool
    {
        $typeSpecifique = $zoneSpecifique->getTypeCritere();
        $typeLarge = $zoneLarge->getTypeCritere();
        $valeurSpecifique = $zoneSpecifique->getValeurCritere();
        $valeurLarge = $zoneLarge->getValeurCritere();
        
        // Logique d'inclusion basée sur les données de la division administrative
        switch ($typeSpecifique) {
            case 'commune':
                // Une commune est incluse dans un code postal, EPCI, département ou région
                switch ($typeLarge) {
                    case 'code_postal':
                        return $division->getCodePostal() === $valeurLarge;
                    case 'epci':
                        return $division->getCodeEpci() === $valeurLarge;
                    case 'departement':
                        return $division->getCodeDepartement() === $valeurLarge;
                    case 'region':
                        return $division->getCodeRegion() === $valeurLarge;
                }
                break;
                
            case 'code_postal':
                // Un code postal est inclus dans un EPCI, département ou région
                switch ($typeLarge) {
                    case 'epci':
                        return $division->getCodeEpci() === $valeurLarge;
                    case 'departement':
                        return $division->getCodeDepartement() === $valeurLarge;
                    case 'region':
                        return $division->getCodeRegion() === $valeurLarge;
                }
                break;
                
            case 'epci':
                // Un EPCI est inclus dans un département ou région
                switch ($typeLarge) {
                    case 'departement':
                        return $division->getCodeDepartement() === $valeurLarge;
                    case 'region':
                        return $division->getCodeRegion() === $valeurLarge;
                }
                break;
                
            case 'departement':
                // Un département est inclus dans une région
                if ($typeLarge === 'region') {
                    return $division->getCodeRegion() === $valeurLarge;
                }
                break;
        }
        
        return false;
    }

    /**
     * Applique les exclusions inverses : quand on ajoute un EPCI/département/région,
     * vérifie s'il y a des communes déjà attribuées spécifiquement à d'autres secteurs
     */
    private function appliquerExclusionsInverses(AttributionSecteur $nouvelleAttribution, EntityManagerInterface $entityManager): void
    {
        $typeCritere = $nouvelleAttribution->getTypeCritere();
        $secteurCible = $nouvelleAttribution->getSecteur();
        
        error_log("🔄 Application exclusions inverses pour $typeCritere dans secteur " . $secteurCible->getNomSecteur());
        
        // Chercher toutes les entités plus spécifiques déjà attribuées à d'autres secteurs
        $entitesAExclure = $this->rechercherEntitesSpecifiquesExistantes($nouvelleAttribution, $secteurCible, $entityManager);
        
        error_log("🔍 Trouvé " . count($entitesAExclure) . " entités déjà attribuées spécifiquement");
        
        // Pour chaque entité déjà attribuée spécifiquement, créer une exclusion dans la nouvelle attribution
        foreach ($entitesAExclure as $entiteAttribution) {
            if (!($entiteAttribution instanceof AttributionSecteur)) {
                continue; // Skip les divisions administratives du JOIN
            }
            
            $divisionEntite = $entiteAttribution->getDivisionAdministrative();
            $secteurEntite = $entiteAttribution->getSecteur();
            
            // Vérifier si l'exclusion n'existe pas déjà
            $exclusionExistante = $entityManager->getRepository(ExclusionSecteur::class)
                ->findOneBy([
                    'attributionSecteur' => $nouvelleAttribution,
                    'divisionAdministrative' => $divisionEntite
                ]);
            
            if (!$exclusionExistante) {
                // Créer une exclusion dans la nouvelle attribution pour cette entité
                $exclusion = new ExclusionSecteur();
                $exclusion->setAttributionSecteur($nouvelleAttribution);
                $exclusion->setDivisionAdministrative($divisionEntite);
                $exclusion->setTypeExclusion($entiteAttribution->getTypeCritere());
                $exclusion->setValeurExclusion($entiteAttribution->getValeurCritere());
                $exclusion->setMotif("Zone {$entiteAttribution->getTypeCritere()} déjà attribuée spécifiquement au secteur '{$secteurEntite->getNomSecteur()}'");
                
                $entityManager->persist($exclusion);
                
                $nomEntite = $this->getNomEntiteAdministrative($divisionEntite, $entiteAttribution->getTypeCritere());
                error_log("🚫 Exclusion inverse : {$entiteAttribution->getTypeCritere()} $nomEntite ({$entiteAttribution->getValeurCritere()}) exclu du $typeCritere car déjà dans secteur '{$secteurEntite->getNomSecteur()}'");
            }
        }
    }

    /**
     * Recherche toutes les entités plus spécifiques que la nouvelle attribution,
     * qui sont déjà attribuées à d'autres secteurs.
     * 
     * Cette fonction identifie les "zones enfants" qui doivent être exclues
     * de la nouvelle zone large que l'on ajoute.
     * 
     * EXEMPLE :
     * - Pour un nouvel EPCI "Pyrénées Haut Garonnaises", trouve :
     *   * Les communes déjà attribuées spécifiquement (ex: "Boutx" → secteur "31160")
     *   * Les codes postaux déjà attribués spécifiquement (ex: "31160" → secteur "MonCP")
     * 
     * HIÉRARCHIE DE RECHERCHE :
     * - Région → trouve départements, EPCIs, codes postaux, communes
     * - Département → trouve EPCIs, codes postaux, communes  
     * - EPCI → trouve codes postaux, communes
     * - Code postal → trouve communes
     * - Commune → aucune recherche (niveau le plus spécifique)
     * 
     * @param AttributionSecteur $nouvelleAttribution L'attribution large à analyser
     * @param Secteur $secteurCible Le secteur de la nouvelle attribution (à exclure des résultats)
     * @param EntityManagerInterface $entityManager Manager Doctrine
     * @return array Liste des AttributionSecteur plus spécifiques déjà existantes
     */
    private function rechercherEntitesSpecifiquesExistantes(AttributionSecteur $nouvelleAttribution, Secteur $secteurCible, EntityManagerInterface $entityManager): array
    {
        $typeCritere = $nouvelleAttribution->getTypeCritere();
        $valeurCritere = $nouvelleAttribution->getValeurCritere();
        
        // Définir la hiérarchie pour chercher les entités plus spécifiques
        $hierarchie = [
            'region' => ['departement', 'epci', 'code_postal', 'commune'],
            'departement' => ['epci', 'code_postal', 'commune'],
            'epci' => ['code_postal', 'commune'],
            'code_postal' => ['commune'],
            'commune' => []
        ];
        
        if (!isset($hierarchie[$typeCritere])) {
            return [];
        }
        
        $typesSpecifiques = $hierarchie[$typeCritere];
        if (empty($typesSpecifiques)) {
            return [];
        }
        
        // Construire la requête selon le type
        $conditions = [];
        $parameters = ['secteur' => $secteurCible];
        
        foreach ($typesSpecifiques as $typeSpecifique) {
            switch ($typeCritere) {
                case 'region':
                    switch ($typeSpecifique) {
                        case 'departement':
                            $conditions[] = '(a.typeCritere = :dept AND d.codeRegion = :regionValue)';
                            $parameters['dept'] = 'departement';
                            break;
                        case 'epci':
                            $conditions[] = '(a.typeCritere = :epci AND d.codeRegion = :regionValue)';
                            $parameters['epci'] = 'epci';
                            break;
                        case 'code_postal':
                            $conditions[] = '(a.typeCritere = :cp AND d.codeRegion = :regionValue)';
                            $parameters['cp'] = 'code_postal';
                            break;
                        case 'commune':
                            $conditions[] = '(a.typeCritere = :comm AND d.codeRegion = :regionValue)';
                            $parameters['comm'] = 'commune';
                            break;
                    }
                    $parameters['regionValue'] = $valeurCritere;
                    break;
                    
                case 'departement':
                    switch ($typeSpecifique) {
                        case 'epci':
                            $conditions[] = '(a.typeCritere = :epci AND d.codeDepartement = :deptValue)';
                            $parameters['epci'] = 'epci';
                            break;
                        case 'code_postal':
                            $conditions[] = '(a.typeCritere = :cp AND d.codeDepartement = :deptValue)';
                            $parameters['cp'] = 'code_postal';
                            break;
                        case 'commune':
                            $conditions[] = '(a.typeCritere = :comm AND d.codeDepartement = :deptValue)';
                            $parameters['comm'] = 'commune';
                            break;
                    }
                    $parameters['deptValue'] = $valeurCritere;
                    break;
                    
                case 'epci':
                    switch ($typeSpecifique) {
                        case 'code_postal':
                            $conditions[] = '(a.typeCritere = :cp AND d.codeEpci = :epciValue)';
                            $parameters['cp'] = 'code_postal';
                            break;
                        case 'commune':
                            $conditions[] = '(a.typeCritere = :comm AND d.codeEpci = :epciValue)';
                            $parameters['comm'] = 'commune';
                            break;
                    }
                    $parameters['epciValue'] = $valeurCritere;
                    break;
                    
                case 'code_postal':
                    if ($typeSpecifique === 'commune') {
                        $conditions[] = '(a.typeCritere = :comm AND d.codePostal = :cpValue)';
                        $parameters['comm'] = 'commune';
                        $parameters['cpValue'] = $valeurCritere;
                    }
                    break;
            }
        }
        
        if (empty($conditions)) {
            return [];
        }
        
        $qb = $entityManager->getRepository(AttributionSecteur::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.divisionAdministrative', 'd')
            ->where('a.secteur != :secteur')
            ->andWhere('(' . implode(' OR ', $conditions) . ')');
            
        foreach ($parameters as $key => $value) {
            $qb->setParameter($key, $value);
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère le nom d'une entité administrative selon son type
     */
    private function getNomEntiteAdministrative(DivisionAdministrative $division, string $type): string
    {
        switch ($type) {
            case 'commune':
                return $division->getNomCommune() ?: 'Commune inconnue';
            case 'code_postal':
                return $division->getCodePostal() ?: 'Code postal inconnu';
            case 'epci':
                return $division->getNomEpci() ?: 'EPCI inconnu';
            case 'departement':
                return $division->getNomDepartement() ?: 'Département inconnu';
            case 'region':
                return $division->getNomRegion() ?: 'Région inconnue';
            default:
                return 'Entité inconnue';
        }
    }

    /**
     * Applique les exclusions pour l'affichage géographique selon le type d'attribution
     */
    private function appliquerExclusionsAffichage(AttributionSecteur $attribution, array $exclusions, EntityManagerInterface $entityManager): array
    {
        if (empty($exclusions)) {
            return [];
        }
        
        $exclusionData = [];
        $typeCritere = $attribution->getTypeCritere();
        $valeurCritere = $attribution->getValeurCritere();
        
        error_log("🔍 Traitement exclusions pour $typeCritere '$valeurCritere' - " . count($exclusions) . " exclusions trouvées");
        
        foreach ($exclusions as $exclusion) {
            $typeExclusion = $exclusion->getTypeExclusion();
            $valeurExclusion = $exclusion->getValeurExclusion();
            
            // Créer les données d'exclusion pour l'affichage
            $exclusionInfo = [
                'type' => $typeExclusion,
                'valeur' => $valeurExclusion,
                'motif' => $exclusion->getMotif(),
                'division_administrative' => null
            ];
            
            // Récupérer les informations de la division administrative exclue
            $divisionExclue = $exclusion->getDivisionAdministrative();
            if ($divisionExclue) {
                $exclusionInfo['division_administrative'] = [
                    'id' => $divisionExclue->getId(),
                    'nom' => $this->getNomEntiteAdministrative($divisionExclue, $typeExclusion),
                    'code_insee' => $divisionExclue->getCodeInseeCommune(),
                    'code_postal' => $divisionExclue->getCodePostal(),
                    'latitude' => $divisionExclue->getLatitude(),
                    'longitude' => $divisionExclue->getLongitude()
                ];
            }
            
            $exclusionData[] = $exclusionInfo;
            error_log("🚫 Exclusion $typeExclusion '$valeurExclusion' dans $typeCritere '$valeurCritere'");
        }
        
        return $exclusionData;
    }

    /**
     * Applique les exclusions spécifiques aux codes postaux.
     * 
     * PARTICULARITÉ DES CODES POSTAUX :
     * Un code postal peut chevaucher plusieurs EPCIs, contrairement aux autres entités.
     * Il faut donc créer des exclusions dans TOUS les EPCIs qui contiennent des communes
     * de ce code postal.
     * 
     * ALGORITHME :
     * 1. Trouver toutes les communes du code postal
     * 2. Identifier tous les EPCIs contenant au moins une commune de ce code postal
     * 3. Pour chaque EPCI d'un autre secteur : créer une exclusion pour chaque commune du code postal
     * 
     * EXEMPLE avec code postal 31160 :
     * - Communes : Boutx, Juzet-d'Izaut, etc. (27 communes)
     * - EPCIs concernés : "Pyrénées Haut Garonnaises", "Cagire Garonne Salat", "Coeur Comminges"
     * - Résultat : 27 × 3 = 81 exclusions créées
     * 
     * @param AttributionSecteur $attributionCodePostal L'attribution du code postal
     * @param EntityManagerInterface $entityManager Manager Doctrine
     */
    private function appliquerExclusionsCodePostal(AttributionSecteur $attributionCodePostal, EntityManagerInterface $entityManager): void
    {
        $codePostal = $attributionCodePostal->getValeurCritere();
        $secteurCodePostal = $attributionCodePostal->getSecteur();
        
        error_log("🏠 Application exclusions code postal $codePostal pour secteur " . $secteurCodePostal->getNomSecteur());
        
        // 1. Trouver toutes les communes de ce code postal
        $communesCodePostal = $entityManager->createQuery('
            SELECT d FROM App\Entity\DivisionAdministrative d 
            WHERE d.codePostal = :codePostal 
            AND d.codeInseeCommune IS NOT NULL
        ')
        ->setParameter('codePostal', $codePostal)
        ->getResult();
        
        error_log("🔍 Trouvé " . count($communesCodePostal) . " communes pour le code postal $codePostal");
        
        // 2. Trouver tous les EPCIs qui contiennent des communes de ce code postal  
        $attributionsEPCI = $entityManager->createQuery('
            SELECT DISTINCT a FROM App\Entity\AttributionSecteur a
            JOIN a.divisionAdministrative d
            JOIN a.secteur s
            WHERE a.typeCritere = :epci
            AND d.codeEpci IN (
                SELECT DISTINCT d2.codeEpci 
                FROM App\Entity\DivisionAdministrative d2 
                WHERE d2.codePostal = :codePostal 
                AND d2.codeEpci IS NOT NULL
            )
            AND s.nomSecteur != :secteurCodePostal
        ')
        ->setParameter('epci', 'epci')
        ->setParameter('codePostal', $codePostal)
        ->setParameter('secteurCodePostal', $secteurCodePostal->getNomSecteur())
        ->getResult();
        
        error_log("🔍 Trouvé " . count($attributionsEPCI) . " attributions EPCI concernées");
        
        // 3. Pour chaque EPCI, créer des exclusions pour toutes les communes du code postal
        foreach ($attributionsEPCI as $attributionEPCI) {
            $secteurEPCI = $attributionEPCI->getSecteur();
            
            foreach ($communesCodePostal as $commune) {
                // Vérifier si l'exclusion n'existe pas déjà
                $exclusionExistante = $entityManager->getRepository(ExclusionSecteur::class)
                    ->findOneBy([
                        'attributionSecteur' => $attributionEPCI,
                        'divisionAdministrative' => $commune
                    ]);
                
                if (!$exclusionExistante) {
                    // Créer une exclusion de cette commune dans cet EPCI
                    $exclusion = new ExclusionSecteur();
                    $exclusion->setAttributionSecteur($attributionEPCI);
                    $exclusion->setDivisionAdministrative($commune);
                    $exclusion->setTypeExclusion('commune');
                    $exclusion->setValeurExclusion($commune->getCodeInseeCommune());
                    $exclusion->setMotif("Commune du code postal $codePostal déjà attribuée spécifiquement au secteur " . $secteurCodePostal->getNomSecteur());
                    
                    $entityManager->persist($exclusion);
                    
                    error_log("🚫 Exclusion: commune " . $commune->getNomCommune() . " (" . $commune->getCodeInseeCommune() . ") exclue de l'EPCI du secteur " . $secteurEPCI->getNomSecteur());
                }
            }
        }
        
        error_log("✅ Exclusions code postal $codePostal appliquées");
    }


}
