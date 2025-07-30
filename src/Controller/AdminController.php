<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\FormeJuridique;
use App\Entity\Secteur;
use App\Entity\Zone;
use App\Entity\Produit;
use App\Entity\ModeReglement;
use App\Entity\ModePaiement;
use App\Entity\Banque;
use App\Entity\MethodeExpedition;
use App\Entity\ModeleDocument;
use App\Service\DocumentNumerotationService;
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
            'produits' => $entityManager->getRepository(Produit::class)->count([]),
            'modes_reglement' => $entityManager->getRepository(ModeReglement::class)->count([]),
            'modes_paiement' => $entityManager->getRepository(ModePaiement::class)->count([]),
            'banques' => $entityManager->getRepository(Banque::class)->count([]),
            'methodes_expedition' => $entityManager->getRepository(MethodeExpedition::class)->count([]),
            'modeles_document' => $entityManager->getRepository(ModeleDocument::class)->count([])
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
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
            
            $prochainNumero = $numerotationService->getProchainNumero($prefixe);

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
}