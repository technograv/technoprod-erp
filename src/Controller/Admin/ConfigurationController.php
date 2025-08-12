<?php

namespace App\Controller\Admin;

use App\Entity\FormeJuridique;
use App\Entity\ModeReglement;
use App\Entity\ModePaiement;
use App\Entity\Banque;
use App\Entity\TauxTVA;
use App\Entity\Unite;
use App\Repository\FormeJuridiqueRepository;
use App\Repository\ModeReglementRepository;
use App\Repository\ModePaiementRepository;
use App\Repository\BanqueRepository;
use App\Repository\TauxTVARepository;
use App\Repository\UniteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ConfigurationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    // ================================
    // FORMES JURIDIQUES
    // ================================

    #[Route('/formes-juridiques', name: 'app_admin_formes_juridiques', methods: ['GET'])]
    public function formesJuridiques(): Response
    {
        $formes = $this->entityManager
            ->getRepository(FormeJuridique::class)
            ->findAllOrdered();
        
        return $this->render('admin/configuration/formes_juridiques.html.twig', [
            'formes_juridiques' => $formes
        ]);
    }

    #[Route('/formes-juridiques/create', name: 'app_admin_formes_juridiques_create', methods: ['POST'])]
    public function createFormeJuridique(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['denomination']) || !isset($data['abreviation'])) {
                return $this->json(['error' => 'Données manquantes'], 400);
            }

            $forme = new FormeJuridique();
            $forme->setDenomination($data['denomination']);
            $forme->setAbreviation($data['abreviation']);
            $forme->setTemplate($data['template'] ?? 'personne_morale');
            $forme->setActif($data['actif'] ?? true);
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $forme->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(FormeJuridique::class);
                $repository->reorganizeOrdres($forme, $newOrdre);
            }
            
            $this->entityManager->persist($forme);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Forme juridique créée avec succès',
                'forme' => [
                    'id' => $forme->getId(),
                    'denomination' => $forme->getDenomination(),
                    'abreviation' => $forme->getAbreviation(),
                    'template' => $forme->getTemplate(),
                    'ordre' => $forme->getOrdre(),
                    'actif' => $forme->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/formes-juridiques/{id}/update', name: 'app_admin_formes_juridiques_update', methods: ['PUT'])]
    public function updateFormeJuridique(Request $request, FormeJuridique $forme): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['denomination'])) {
                $forme->setDenomination($data['denomination']);
            }
            if (isset($data['abreviation'])) {
                $forme->setAbreviation($data['abreviation']);
            }
            if (isset($data['template'])) {
                $forme->setTemplate($data['template']);
            }
            if (isset($data['actif'])) {
                $forme->setActif($data['actif']);
            }
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $forme->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(FormeJuridique::class);
                $repository->reorganizeOrdres($forme, $newOrdre);
            }

            $this->entityManager->flush();

            return $this->json([
                'message' => 'Forme juridique mise à jour avec succès',
                'forme' => [
                    'id' => $forme->getId(),
                    'denomination' => $forme->getDenomination(),
                    'abreviation' => $forme->getAbreviation(),
                    'template' => $forme->getTemplate(),
                    'ordre' => $forme->getOrdre(),
                    'actif' => $forme->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/formes-juridiques/{id}/delete', name: 'app_admin_formes_juridiques_delete', methods: ['DELETE'])]
    public function deleteFormeJuridique(FormeJuridique $forme): JsonResponse
    {
        try {
            $this->entityManager->remove($forme);
            $this->entityManager->flush();

            return $this->json(['message' => 'Forme juridique supprimée avec succès']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // MODES PAIEMENT
    // ================================

    #[Route('/modes-paiement', name: 'app_admin_modes_paiement', methods: ['GET'])]
    public function modesPaiement(): Response
    {
        $modes = $this->entityManager
            ->getRepository(ModePaiement::class)
            ->findAllOrdered();
        
        $banques = $this->entityManager
            ->getRepository(Banque::class)
            ->findBy(['actif' => true], ['nom' => 'ASC']);
        
        return $this->render('admin/configuration/modes_paiement.html.twig', [
            'modes_paiement' => $modes,
            'banques' => $banques
        ]);
    }

    #[Route('/modes-paiement/create', name: 'app_admin_modes_paiement_create', methods: ['POST'])]
    public function createModePaiement(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom'])) {
                return $this->json(['error' => 'Le nom est obligatoire'], 400);
            }

            $mode = new ModePaiement();
            $mode->setNom($data['nom']);
            $mode->setDescription($data['description'] ?? '');
            $mode->setActif($data['actif'] ?? true);
            
            if (isset($data['banque_id']) && !empty($data['banque_id'])) {
                $banque = $this->entityManager->find(Banque::class, $data['banque_id']);
                if ($banque) {
                    $mode->setBanque($banque);
                }
            }
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $mode->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(ModePaiement::class);
                $repository->reorganizeOrdres($mode, $newOrdre);
            }
            
            $this->entityManager->persist($mode);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Mode de paiement créé avec succès',
                'mode' => [
                    'id' => $mode->getId(),
                    'nom' => $mode->getNom(),
                    'description' => $mode->getDescription(),
                    'ordre' => $mode->getOrdre(),
                    'actif' => $mode->isActif(),
                    'banque' => $mode->getBanque()?->getNom()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/modes-paiement/{id}/update', name: 'app_admin_modes_paiement_update', methods: ['PUT'])]
    public function updateModePaiement(Request $request, ModePaiement $mode): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $mode->setNom($data['nom']);
            }
            if (isset($data['description'])) {
                $mode->setDescription($data['description']);
            }
            if (isset($data['actif'])) {
                $mode->setActif($data['actif']);
            }
            
            if (isset($data['banque_id'])) {
                if (!empty($data['banque_id'])) {
                    $banque = $this->entityManager->find(Banque::class, $data['banque_id']);
                    $mode->setBanque($banque);
                } else {
                    $mode->setBanque(null);
                }
            }
            
            // Gestion de l'ordre AVANT le flush
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $mode->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(ModePaiement::class);
                $repository->reorganizeOrdres($mode, $newOrdre);
            }

            $this->entityManager->flush();

            return $this->json([
                'message' => 'Mode de paiement mis à jour avec succès',
                'mode' => [
                    'id' => $mode->getId(),
                    'nom' => $mode->getNom(),
                    'description' => $mode->getDescription(),
                    'ordre' => $mode->getOrdre(),
                    'actif' => $mode->isActif(),
                    'banque' => $mode->getBanque()?->getNom()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/modes-paiement/{id}/delete', name: 'app_admin_modes_paiement_delete', methods: ['DELETE'])]
    public function deleteModePaiement(ModePaiement $mode): JsonResponse
    {
        try {
            $this->entityManager->remove($mode);
            $this->entityManager->flush();

            return $this->json(['message' => 'Mode de paiement supprimé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // MODES REGLEMENT
    // ================================

    #[Route('/modes-reglement', name: 'app_admin_modes_reglement', methods: ['GET'])]
    public function modesReglement(): Response
    {
        $modes = $this->entityManager
            ->getRepository(ModeReglement::class)
            ->findAllOrdered();
            
        $modesPaiement = $this->entityManager
            ->getRepository(ModePaiement::class)
            ->findAllOrdered();
        
        return $this->render('admin/configuration/modes_reglement.html.twig', [
            'modes_reglement' => $modes,
            'modes_paiement' => $modesPaiement
        ]);
    }

    #[Route('/modes-reglement/create', name: 'app_admin_modes_reglement_create', methods: ['POST'])]
    public function createModeReglement(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom'])) {
                return $this->json(['error' => 'Le nom est obligatoire'], 400);
            }

            $mode = new ModeReglement();
            $mode->setNom($data['nom']);
            $mode->setDescription($data['description'] ?? '');
            $mode->setDelaiJours($data['delai_jours'] ?? 0);
            $mode->setActif($data['actif'] ?? true);
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $mode->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(ModeReglement::class);
                $repository->reorganizeOrdres($mode, $newOrdre);
            }
            
            $this->entityManager->persist($mode);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Mode de règlement créé avec succès',
                'mode' => [
                    'id' => $mode->getId(),
                    'nom' => $mode->getNom(),
                    'delai_jours' => $mode->getDelaiJours(),
                    'ordre' => $mode->getOrdre(),
                    'actif' => $mode->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/modes-reglement/{id}/update', name: 'app_admin_modes_reglement_update', methods: ['PUT'])]
    public function updateModeReglement(Request $request, ModeReglement $mode): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $mode->setNom($data['nom']);
            }
            if (isset($data['description'])) {
                $mode->setDescription($data['description']);
            }
            if (isset($data['delai_jours'])) {
                $mode->setDelaiJours(intval($data['delai_jours']));
            }
            if (isset($data['actif'])) {
                $mode->setActif($data['actif']);
            }
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $mode->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(ModeReglement::class);
                $repository->reorganizeOrdres($mode, $newOrdre);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Mode de règlement mis à jour avec succès',
                'mode' => [
                    'id' => $mode->getId(),
                    'nom' => $mode->getNom(),
                    'delai_jours' => $mode->getDelaiJours(),
                    'ordre' => $mode->getOrdre(),
                    'actif' => $mode->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/modes-reglement/{id}/delete', name: 'app_admin_modes_reglement_delete', methods: ['DELETE'])]
    public function deleteModeReglement(ModeReglement $mode): JsonResponse
    {
        try {
            $this->entityManager->remove($mode);
            $this->entityManager->flush();

            return $this->json(['message' => 'Mode de règlement supprimé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // BANQUES
    // ================================

    #[Route('/banques', name: 'app_admin_banques', methods: ['GET'])]
    public function banques(): Response
    {
        $banques = $this->entityManager
            ->getRepository(Banque::class)
            ->findAllOrdered();
        
        return $this->render('admin/configuration/banques.html.twig', [
            'banques' => $banques
        ]);
    }

    #[Route('/banques/create', name: 'app_admin_banques_create', methods: ['POST'])]
    public function createBanque(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom']) || !isset($data['code'])) {
                return $this->json(['error' => 'Nom et code obligatoires'], 400);
            }

            $banque = new Banque();
            $banque->setNom($data['nom']);
            $banque->setCode($data['code']);
            $banque->setAdresse($data['adresse'] ?? '');
            $banque->setCodePostal($data['code_postal'] ?? '');
            $banque->setVille($data['ville'] ?? '');
            $banque->setPays($data['pays'] ?? 'France');
            $banque->setTelephone($data['telephone'] ?? '');
            $banque->setEmail($data['email'] ?? '');
            $banque->setSiteWeb($data['site_web'] ?? '');
            $banque->setIban($data['iban'] ?? '');
            $banque->setBic($data['bic'] ?? '');
            $banque->setActif($data['actif'] ?? true);
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $banque->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(Banque::class);
                $repository->reorganizeOrdres($banque, $newOrdre);
            }
            
            $this->entityManager->persist($banque);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Banque créée avec succès',
                'banque' => [
                    'id' => $banque->getId(),
                    'nom' => $banque->getNom(),
                    'code' => $banque->getCode(),
                    'iban' => $banque->getIban(),
                    'bic' => $banque->getBic(),
                    'actif' => $banque->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/banques/{id}/update', name: 'app_admin_banques_update', methods: ['PUT'])]
    public function updateBanque(Request $request, Banque $banque): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $banque->setNom($data['nom']);
            }
            if (isset($data['code'])) {
                $banque->setCode($data['code']);
            }
            if (isset($data['adresse'])) {
                $banque->setAdresse($data['adresse']);
            }
            if (isset($data['code_postal'])) {
                $banque->setCodePostal($data['code_postal']);
            }
            if (isset($data['ville'])) {
                $banque->setVille($data['ville']);
            }
            if (isset($data['pays'])) {
                $banque->setPays($data['pays']);
            }
            if (isset($data['telephone'])) {
                $banque->setTelephone($data['telephone']);
            }
            if (isset($data['email'])) {
                $banque->setEmail($data['email']);
            }
            if (isset($data['site_web'])) {
                $banque->setSiteWeb($data['site_web']);
            }
            if (isset($data['iban'])) {
                $banque->setIban($data['iban']);
            }
            if (isset($data['bic'])) {
                $banque->setBic($data['bic']);
            }
            if (isset($data['actif'])) {
                $banque->setActif($data['actif']);
            }
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $banque->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(Banque::class);
                $repository->reorganizeOrdres($banque, $newOrdre);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Banque mise à jour avec succès',
                'banque' => [
                    'id' => $banque->getId(),
                    'nom' => $banque->getNom(),
                    'code' => $banque->getCode(),
                    'iban' => $banque->getIban(),
                    'bic' => $banque->getBic(),
                    'actif' => $banque->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/banques/{id}/delete', name: 'app_admin_banques_delete', methods: ['DELETE'])]
    public function deleteBanque(Banque $banque): JsonResponse
    {
        try {
            // Vérifier que la banque n'est pas utilisée par des modes de paiement
            $modesPaiementCount = $this->entityManager->getRepository(ModePaiement::class)
                ->count(['banque' => $banque]);
            
            if ($modesPaiementCount > 0) {
                return $this->json([
                    'error' => 'Cette banque ne peut pas être supprimée car elle est utilisée par ' . $modesPaiementCount . ' mode(s) de paiement'
                ], 400);
            }

            $this->entityManager->remove($banque);
            $this->entityManager->flush();

            return $this->json(['message' => 'Banque supprimée avec succès']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // TAUX TVA
    // ================================

    #[Route('/taux-tva', name: 'app_admin_taux_tva', methods: ['GET'])]
    public function tauxTva(): Response
    {
        $taux = $this->entityManager
            ->getRepository(TauxTVA::class)
            ->findAllOrdered();
        
        return $this->render('admin/configuration/taux_tva.html.twig', [
            'taux_tva' => $taux
        ]);
    }

    #[Route('/taux-tva/get', name: 'app_admin_taux_tva_get', methods: ['GET'])]
    public function getTauxTva(Request $request): JsonResponse
    {
        $id = $request->query->get('id');
        
        if ($id) {
            $taux = $this->entityManager->find(TauxTVA::class, $id);
            if (!$taux) {
                return $this->json(['error' => 'Taux TVA non trouvé'], 404);
            }
            
            return $this->json([
                'id' => $taux->getId(),
                'nom' => $taux->getNom(),
                'taux' => $taux->getTaux(),
                'actif' => $taux->isActif(),
                'par_defaut' => $taux->isParDefaut()
            ]);
        }
        
        // Retourner tous les taux
        $taux = $this->entityManager->getRepository(TauxTVA::class)->findAllOrdered();
        $result = [];
        
        foreach ($taux as $t) {
            $result[] = [
                'id' => $t->getId(),
                'nom' => $t->getNom(),
                'taux' => $t->getTaux(),
                'actif' => $t->isActif(),
                'par_defaut' => $t->isParDefaut()
            ];
        }
        
        return $this->json($result);
    }

    #[Route('/taux-tva/create', name: 'app_admin_taux_tva_create', methods: ['POST'])]
    public function createTauxTva(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom']) || !isset($data['taux'])) {
                return $this->json(['error' => 'Nom et taux obligatoires'], 400);
            }

            $taux = new TauxTVA();
            $taux->setNom($data['nom']);
            $taux->setTaux(floatval($data['taux']));
            $taux->setDescription($data['description'] ?? '');
            $taux->setActif($data['actif'] ?? true);
            $taux->setParDefaut($data['par_defaut'] ?? false);
            
            // Si défini comme par défaut, désactiver les autres
            if ($taux->isParDefaut()) {
                $this->entityManager->createQuery(
                    'UPDATE App\Entity\TauxTVA t SET t.parDefaut = false'
                )->execute();
            }
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $taux->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(TauxTVA::class);
                $repository->reorganizeOrdres($taux, $newOrdre);
            }
            
            $this->entityManager->persist($taux);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Taux TVA créé avec succès',
                'taux' => [
                    'id' => $taux->getId(),
                    'nom' => $taux->getNom(),
                    'taux' => $taux->getTaux(),
                    'actif' => $taux->isActif(),
                    'par_defaut' => $taux->isParDefaut()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/taux-tva/{id}/update', name: 'app_admin_taux_tva_update', methods: ['PUT'])]
    public function updateTauxTva(Request $request, TauxTVA $taux): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $taux->setNom($data['nom']);
            }
            if (isset($data['taux'])) {
                $taux->setTaux(floatval($data['taux']));
            }
            if (isset($data['description'])) {
                $taux->setDescription($data['description']);
            }
            if (isset($data['actif'])) {
                $taux->setActif($data['actif']);
            }
            
            // Gestion du par défaut
            if (isset($data['par_defaut']) && $data['par_defaut']) {
                $this->entityManager->createQuery(
                    'UPDATE App\Entity\TauxTVA t SET t.parDefaut = false WHERE t.id != :id'
                )->setParameter('id', $taux->getId())->execute();
                $taux->setParDefaut(true);
            } elseif (isset($data['par_defaut'])) {
                $taux->setParDefaut($data['par_defaut']);
            }
            
            // Gestion de l'ordre
            if (isset($data['ordre'])) {
                $newOrdre = intval($data['ordre']);
                $taux->setOrdre($newOrdre);
                $repository = $this->entityManager->getRepository(TauxTVA::class);
                $repository->reorganizeOrdres($taux, $newOrdre);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Taux TVA mis à jour avec succès',
                'taux' => [
                    'id' => $taux->getId(),
                    'nom' => $taux->getNom(),
                    'taux' => $taux->getTaux(),
                    'actif' => $taux->isActif(),
                    'par_defaut' => $taux->isParDefaut()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/taux-tva/{id}/delete', name: 'app_admin_taux_tva_delete', methods: ['DELETE'])]
    public function deleteTauxTva(TauxTVA $taux): JsonResponse
    {
        try {
            $this->entityManager->remove($taux);
            $this->entityManager->flush();

            return $this->json(['message' => 'Taux TVA supprimé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // UNITES
    // ================================

    #[Route('/unites', name: 'app_admin_unites', methods: ['GET'])]
    public function unites(): Response
    {
        $unites = $this->entityManager
            ->getRepository(Unite::class)
            ->findBy([], ['type' => 'ASC', 'nom' => 'ASC']);
        
        return $this->render('admin/configuration/unites.html.twig', [
            'unites' => $unites
        ]);
    }

    #[Route('/unites/get', name: 'app_admin_unites_get', methods: ['GET'])]
    public function getUnites(): JsonResponse
    {
        $unites = $this->entityManager
            ->getRepository(Unite::class)
            ->findBy([], ['type' => 'ASC', 'nom' => 'ASC']);
        
        $result = [];
        foreach ($unites as $unite) {
            $result[] = [
                'id' => $unite->getId(),
                'nom' => $unite->getNom(),
                'abreviation' => $unite->getAbreviation(),
                'type' => $unite->getType(),
                'facteur_conversion' => $unite->getFacteurConversion(),
                'actif' => $unite->isActif()
            ];
        }
        
        return $this->json($result);
    }

    #[Route('/unites/create', name: 'app_admin_unites_create', methods: ['POST'])]
    public function createUnite(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom']) || !isset($data['abreviation']) || !isset($data['type'])) {
                return $this->json(['error' => 'Nom, abréviation et type obligatoires'], 400);
            }

            $unite = new Unite();
            $unite->setNom($data['nom']);
            $unite->setAbreviation($data['abreviation']);
            $unite->setType($data['type']);
            $unite->setDescription($data['description'] ?? '');
            $unite->setFacteurConversion($data['facteur_conversion'] ?? 1.0);
            $unite->setActif($data['actif'] ?? true);
            
            $this->entityManager->persist($unite);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Unité créée avec succès',
                'unite' => [
                    'id' => $unite->getId(),
                    'nom' => $unite->getNom(),
                    'abreviation' => $unite->getAbreviation(),
                    'type' => $unite->getType(),
                    'actif' => $unite->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/unites/{id}/update', name: 'app_admin_unites_update', methods: ['PUT'])]
    public function updateUnite(Request $request, Unite $unite): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $unite->setNom($data['nom']);
            }
            if (isset($data['abreviation'])) {
                $unite->setAbreviation($data['abreviation']);
            }
            if (isset($data['type'])) {
                $unite->setType($data['type']);
            }
            if (isset($data['description'])) {
                $unite->setDescription($data['description']);
            }
            if (isset($data['facteur_conversion'])) {
                $unite->setFacteurConversion(floatval($data['facteur_conversion']));
            }
            if (isset($data['actif'])) {
                $unite->setActif($data['actif']);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Unité mise à jour avec succès',
                'unite' => [
                    'id' => $unite->getId(),
                    'nom' => $unite->getNom(),
                    'abreviation' => $unite->getAbreviation(),
                    'type' => $unite->getType(),
                    'actif' => $unite->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/unites/{id}/delete', name: 'app_admin_unites_delete', methods: ['DELETE'])]
    public function deleteUnite(Unite $unite): JsonResponse
    {
        try {
            $this->entityManager->remove($unite);
            $this->entityManager->flush();

            return $this->json(['message' => 'Unité supprimée avec succès']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/unites/types', name: 'app_admin_unites_types', methods: ['GET'])]
    public function getUnitesTypes(): JsonResponse
    {
        $types = [
            'longueur' => 'Longueur',
            'poids' => 'Poids',
            'volume' => 'Volume',
            'surface' => 'Surface',
            'temps' => 'Temps',
            'quantite' => 'Quantité',
            'autre' => 'Autre'
        ];
        
        return $this->json(['types' => $types]);
    }
}