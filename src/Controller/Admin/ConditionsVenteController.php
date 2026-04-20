<?php

namespace App\Controller\Admin;

use App\Entity\ConditionsVente;
use App\Repository\ConditionsVenteRepository;
use App\Repository\SocieteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/conditions-vente')]
class ConditionsVenteController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ConditionsVenteRepository $conditionsVenteRepository;
    private SocieteRepository $societeRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ConditionsVenteRepository $conditionsVenteRepository,
        SocieteRepository $societeRepository
    ) {
        $this->entityManager = $entityManager;
        $this->conditionsVenteRepository = $conditionsVenteRepository;
        $this->societeRepository = $societeRepository;
    }

    #[Route('', name: 'app_admin_conditions_vente_index', methods: ['GET'])]
    public function index(): Response
    {
        $conditionsVente = $this->conditionsVenteRepository->findAllOrdered();
        $societes = $this->societeRepository->findAll();

        return $this->render('admin/conditions_vente.html.twig', [
            'conditionsVente' => $conditionsVente,
            'societes' => $societes,
        ]);
    }

    #[Route('/create', name: 'app_admin_conditions_vente_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $cgv = new ConditionsVente();
            $cgv->setCode($data['code']);
            $cgv->setNom($data['nom']);
            $cgv->setContenu($data['contenu'] ?? null);
            $cgv->setNotes($data['notes'] ?? null);
            $cgv->setActif($data['actif'] ?? true);

            if (!empty($data['societeId'])) {
                $societe = $this->societeRepository->find($data['societeId']);
                if ($societe) {
                    $cgv->setSociete($societe);
                }
            }

            // Ordre à la fin
            $maxOrdre = $this->conditionsVenteRepository->createQueryBuilder('c')
                ->select('MAX(c.ordre)')
                ->getQuery()
                ->getSingleScalarResult();
            $cgv->setOrdre(($maxOrdre ?? 0) + 1);

            $this->entityManager->persist($cgv);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'CGV créées avec succès',
                'id' => $cgv->getId()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'app_admin_conditions_vente_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $cgv = $this->conditionsVenteRepository->find($id);

        if (!$cgv) {
            return $this->json(['success' => false, 'message' => 'CGV non trouvées'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        try {
            if (isset($data['code'])) {
                $cgv->setCode($data['code']);
            }
            if (isset($data['nom'])) {
                $cgv->setNom($data['nom']);
            }
            if (isset($data['contenu'])) {
                $cgv->setContenu($data['contenu']);
            }
            if (isset($data['notes'])) {
                $cgv->setNotes($data['notes']);
            }
            if (isset($data['actif'])) {
                $cgv->setActif($data['actif']);
            }
            if (isset($data['societeId'])) {
                if (!empty($data['societeId'])) {
                    $societe = $this->societeRepository->find($data['societeId']);
                    if ($societe) {
                        $cgv->setSociete($societe);
                    }
                } else {
                    $cgv->setSociete(null);
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'CGV modifiées avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'app_admin_conditions_vente_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $cgv = $this->conditionsVenteRepository->find($id);

        if (!$cgv) {
            return $this->json(['success' => false, 'message' => 'CGV non trouvées'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->entityManager->remove($cgv);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'CGV supprimées avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/reorder', name: 'app_admin_conditions_vente_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $order = $data['order'] ?? [];

        try {
            foreach ($order as $index => $id) {
                $cgv = $this->conditionsVenteRepository->find($id);
                if ($cgv) {
                    $cgv->setOrdre($index + 1);
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Ordre modifié avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de l\'ordre: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
