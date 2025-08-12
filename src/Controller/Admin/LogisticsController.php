<?php

namespace App\Controller\Admin;

use App\Entity\Transporteur;
use App\Entity\FraisPort;
use App\Entity\MethodeExpedition;
use App\Entity\Civilite;
use App\Entity\TauxTVA;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class LogisticsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    // ================================
    // TRANSPORTEURS
    // ================================

    #[Route('/transporteurs', name: 'app_admin_transporteurs', methods: ['GET'])]
    public function transporteurs(): Response
    {
        $transporteurs = $this->entityManager
            ->getRepository(Transporteur::class)
            ->findBy([], ['nom' => 'ASC']);
        
        return $this->render('admin/logistics/transporteurs.html.twig', [
            'transporteurs' => $transporteurs
        ]);
    }

    #[Route('/transporteurs/get', name: 'app_admin_transporteurs_get', methods: ['GET'])]
    public function getTransporteurs(): JsonResponse
    {
        $transporteurs = $this->entityManager
            ->getRepository(Transporteur::class)
            ->findBy([], ['nom' => 'ASC']);
        
        $result = [];
        foreach ($transporteurs as $transporteur) {
            $result[] = [
                'id' => $transporteur->getId(),
                'nom' => $transporteur->getNom(),
                'code' => $transporteur->getCode(),
                'description' => $transporteur->getDescription(),
                'actif' => $transporteur->isActif(),
                'telephone' => $transporteur->getTelephone(),
                'email' => $transporteur->getEmail(),
                'site_web' => $transporteur->getSiteWeb()
            ];
        }
        
        return $this->json($result);
    }

    #[Route('/transporteurs/create', name: 'app_admin_transporteurs_create', methods: ['POST'])]
    public function createTransporteur(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom']) || !isset($data['code'])) {
                return $this->json(['error' => 'Nom et code obligatoires'], 400);
            }

            $transporteur = new Transporteur();
            $transporteur->setNom($data['nom']);
            $transporteur->setCode($data['code']);
            $transporteur->setDescription($data['description'] ?? '');
            $transporteur->setActif($data['actif'] ?? true);
            $transporteur->setTelephone($data['telephone'] ?? '');
            $transporteur->setEmail($data['email'] ?? '');
            $transporteur->setSiteWeb($data['site_web'] ?? '');
            
            $this->entityManager->persist($transporteur);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Transporteur créé avec succès',
                'transporteur' => [
                    'id' => $transporteur->getId(),
                    'nom' => $transporteur->getNom(),
                    'code' => $transporteur->getCode(),
                    'actif' => $transporteur->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/transporteurs/{id}/update', name: 'app_admin_transporteurs_update', methods: ['PUT'])]
    public function updateTransporteur(Request $request, Transporteur $transporteur): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $transporteur->setNom($data['nom']);
            }
            if (isset($data['code'])) {
                $transporteur->setCode($data['code']);
            }
            if (isset($data['description'])) {
                $transporteur->setDescription($data['description']);
            }
            if (isset($data['actif'])) {
                $transporteur->setActif($data['actif']);
            }
            if (isset($data['telephone'])) {
                $transporteur->setTelephone($data['telephone']);
            }
            if (isset($data['email'])) {
                $transporteur->setEmail($data['email']);
            }
            if (isset($data['site_web'])) {
                $transporteur->setSiteWeb($data['site_web']);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Transporteur mis à jour avec succès',
                'transporteur' => [
                    'id' => $transporteur->getId(),
                    'nom' => $transporteur->getNom(),
                    'code' => $transporteur->getCode(),
                    'actif' => $transporteur->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/transporteurs/{id}/delete', name: 'app_admin_transporteurs_delete', methods: ['DELETE'])]
    public function deleteTransporteur(Transporteur $transporteur): JsonResponse
    {
        try {
            $this->entityManager->remove($transporteur);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Transporteur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // FRAIS DE PORT
    // ================================

    #[Route('/frais-port', name: 'app_admin_frais_port', methods: ['GET'])]
    public function fraisPort(): Response
    {
        $fraisPort = $this->entityManager
            ->getRepository(FraisPort::class)
            ->findBy([], ['nom' => 'ASC']);
        
        $transporteurs = $this->entityManager
            ->getRepository(Transporteur::class)
            ->findBy(['actif' => true], ['nom' => 'ASC']);
            
        $tauxTva = $this->entityManager
            ->getRepository(TauxTVA::class)
            ->findBy(['actif' => true], ['ordre' => 'ASC', 'taux' => 'ASC']);
        
        return $this->render('admin/logistics/frais_port.html.twig', [
            'frais_port' => $fraisPort,
            'transporteurs' => $transporteurs,
            'taux_tva' => $tauxTva
        ]);
    }

    #[Route('/frais-port/get', name: 'app_admin_frais_port_get', methods: ['GET'])]
    public function getFraisPort(): JsonResponse
    {
        $fraisPort = $this->entityManager
            ->getRepository(FraisPort::class)
            ->findBy([], ['nom' => 'ASC']);
        
        $result = [];
        foreach ($fraisPort as $frais) {
            $result[] = [
                'id' => $frais->getId(),
                'nom' => $frais->getNom(),
                'description' => $frais->getDescription(),
                'montant_fixe' => $frais->getMontantFixe(),
                'pourcentage' => $frais->getPourcentage(),
                'seuil_gratuite' => $frais->getSeuilGratuite(),
                'actif' => $frais->isActif(),
                'transporteur_nom' => $frais->getTransporteur()?->getNom()
            ];
        }
        
        return $this->json($result);
    }

    #[Route('/frais-port/create', name: 'app_admin_frais_port_create', methods: ['POST'])]
    public function createFraisPort(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom'])) {
                return $this->json(['error' => 'Le nom est obligatoire'], 400);
            }

            $fraisPort = new FraisPort();
            $fraisPort->setNom($data['nom']);
            $fraisPort->setDescription($data['description'] ?? '');
            $fraisPort->setMontantFixe($data['montant_fixe'] ?? 0);
            $fraisPort->setPourcentage($data['pourcentage'] ?? 0);
            $fraisPort->setSeuilGratuite($data['seuil_gratuite'] ?? null);
            $fraisPort->setActif($data['actif'] ?? true);
            
            if (isset($data['transporteur_id']) && !empty($data['transporteur_id'])) {
                $transporteur = $this->entityManager->find(Transporteur::class, $data['transporteur_id']);
                if ($transporteur) {
                    $fraisPort->setTransporteur($transporteur);
                }
            }
            
            $this->entityManager->persist($fraisPort);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Frais de port créés avec succès',
                'frais_port' => [
                    'id' => $fraisPort->getId(),
                    'nom' => $fraisPort->getNom(),
                    'montant_fixe' => $fraisPort->getMontantFixe(),
                    'actif' => $fraisPort->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/frais-port/{id}/update', name: 'app_admin_frais_port_update', methods: ['PUT'])]
    public function updateFraisPort(Request $request, FraisPort $fraisPort): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $fraisPort->setNom($data['nom']);
            }
            if (isset($data['description'])) {
                $fraisPort->setDescription($data['description']);
            }
            if (isset($data['montant_fixe'])) {
                $fraisPort->setMontantFixe($data['montant_fixe']);
            }
            if (isset($data['pourcentage'])) {
                $fraisPort->setPourcentage($data['pourcentage']);
            }
            if (isset($data['seuil_gratuite'])) {
                $fraisPort->setSeuilGratuite($data['seuil_gratuite']);
            }
            if (isset($data['actif'])) {
                $fraisPort->setActif($data['actif']);
            }
            
            if (isset($data['transporteur_id'])) {
                if (!empty($data['transporteur_id'])) {
                    $transporteur = $this->entityManager->find(Transporteur::class, $data['transporteur_id']);
                    $fraisPort->setTransporteur($transporteur);
                } else {
                    $fraisPort->setTransporteur(null);
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Frais de port mis à jour avec succès',
                'frais_port' => [
                    'id' => $fraisPort->getId(),
                    'nom' => $fraisPort->getNom(),
                    'montant_fixe' => $fraisPort->getMontantFixe(),
                    'actif' => $fraisPort->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/frais-port/{id}/delete', name: 'app_admin_frais_port_delete', methods: ['DELETE'])]
    public function deleteFraisPort(FraisPort $fraisPort): JsonResponse
    {
        try {
            $this->entityManager->remove($fraisPort);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Frais de port supprimés avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // METHODES EXPEDITION
    // ================================

    #[Route('/methodes-expedition', name: 'app_admin_methodes_expedition', methods: ['GET'])]
    public function methodesExpedition(): Response
    {
        $methodes = $this->entityManager
            ->getRepository(MethodeExpedition::class)
            ->findBy([], ['nom' => 'ASC']);
        
        return $this->render('admin/logistics/methodes_expedition.html.twig', [
            'methodes' => $methodes
        ]);
    }

    #[Route('/methodes-expedition/create', name: 'app_admin_methodes_expedition_create', methods: ['POST'])]
    public function createMethodeExpedition(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom'])) {
                return $this->json(['error' => 'Le nom est obligatoire'], 400);
            }

            $methode = new MethodeExpedition();
            $methode->setNom($data['nom']);
            $methode->setDescription($data['description'] ?? '');
            $methode->setActif($data['actif'] ?? true);
            
            $this->entityManager->persist($methode);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Méthode d\'expédition créée avec succès',
                'methode' => [
                    'id' => $methode->getId(),
                    'nom' => $methode->getNom(),
                    'actif' => $methode->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/methodes-expedition/{id}/update', name: 'app_admin_methodes_expedition_update', methods: ['PUT'])]
    public function updateMethodeExpedition(Request $request, MethodeExpedition $methode): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $methode->setNom($data['nom']);
            }
            if (isset($data['description'])) {
                $methode->setDescription($data['description']);
            }
            if (isset($data['actif'])) {
                $methode->setActif($data['actif']);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Méthode d\'expédition mise à jour avec succès',
                'methode' => [
                    'id' => $methode->getId(),
                    'nom' => $methode->getNom(),
                    'actif' => $methode->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/methodes-expedition/{id}/delete', name: 'app_admin_methodes_expedition_delete', methods: ['DELETE'])]
    public function deleteMethodeExpedition(MethodeExpedition $methode): JsonResponse
    {
        try {
            $this->entityManager->remove($methode);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Méthode d\'expédition supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // CIVILITES
    // ================================

    #[Route('/civilites', name: 'app_admin_civilites', methods: ['GET'])]
    public function civilites(): Response
    {
        $civilites = $this->entityManager
            ->getRepository(Civilite::class)
            ->findBy([], ['nom' => 'ASC']);
        
        return $this->render('admin/logistics/civilites.html.twig', [
            'civilites' => $civilites
        ]);
    }

    #[Route('/civilites/get', name: 'app_admin_civilites_get', methods: ['GET'])]
    public function getCivilites(): JsonResponse
    {
        $civilites = $this->entityManager
            ->getRepository(Civilite::class)
            ->findBy([], ['nom' => 'ASC']);
        
        $result = [];
        foreach ($civilites as $civilite) {
            $result[] = [
                'id' => $civilite->getId(),
                'nom' => $civilite->getNom(),
                'abreviation' => $civilite->getAbreviation(),
                'actif' => $civilite->isActif()
            ];
        }
        
        return $this->json($result);
    }

    #[Route('/civilites/create', name: 'app_admin_civilites_create', methods: ['POST'])]
    public function createCivilite(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom']) || !isset($data['abreviation'])) {
                return $this->json(['error' => 'Nom et abréviation obligatoires'], 400);
            }

            $civilite = new Civilite();
            $civilite->setNom($data['nom']);
            $civilite->setAbreviation($data['abreviation']);
            $civilite->setActif($data['actif'] ?? true);
            
            $this->entityManager->persist($civilite);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Civilité créée avec succès',
                'civilite' => [
                    'id' => $civilite->getId(),
                    'nom' => $civilite->getNom(),
                    'abreviation' => $civilite->getAbreviation(),
                    'actif' => $civilite->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/civilites/{id}/update', name: 'app_admin_civilites_update', methods: ['PUT'])]
    public function updateCivilite(Request $request, Civilite $civilite): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $civilite->setNom($data['nom']);
            }
            if (isset($data['abreviation'])) {
                $civilite->setAbreviation($data['abreviation']);
            }
            if (isset($data['actif'])) {
                $civilite->setActif($data['actif']);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Civilité mise à jour avec succès',
                'civilite' => [
                    'id' => $civilite->getId(),
                    'nom' => $civilite->getNom(),
                    'abreviation' => $civilite->getAbreviation(),
                    'actif' => $civilite->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/civilites/{id}/delete', name: 'app_admin_civilites_delete', methods: ['DELETE'])]
    public function deleteCivilite(Civilite $civilite): JsonResponse
    {
        try {
            $this->entityManager->remove($civilite);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Civilité supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }
}