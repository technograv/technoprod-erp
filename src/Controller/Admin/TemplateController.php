<?php

namespace App\Controller\Admin;

use App\Entity\Template;
use App\Repository\TemplateRepository;
use App\Repository\SocieteRepository;
use App\Repository\ConditionsVenteRepository;
use App\Repository\BanqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/templates')]
class TemplateController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TemplateRepository $templateRepository;
    private SocieteRepository $societeRepository;
    private ConditionsVenteRepository $conditionsVenteRepository;
    private BanqueRepository $banqueRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        TemplateRepository $templateRepository,
        SocieteRepository $societeRepository,
        ConditionsVenteRepository $conditionsVenteRepository,
        BanqueRepository $banqueRepository
    ) {
        $this->entityManager = $entityManager;
        $this->templateRepository = $templateRepository;
        $this->societeRepository = $societeRepository;
        $this->conditionsVenteRepository = $conditionsVenteRepository;
        $this->banqueRepository = $banqueRepository;
    }

    #[Route('', name: 'app_admin_templates_index', methods: ['GET'])]
    public function index(): Response
    {
        $templates = $this->templateRepository->findBy([], ['ordre' => 'ASC']);
        $societes = $this->societeRepository->findAll();
        $conditionsVente = $this->conditionsVenteRepository->findAll();
        $banques = $this->banqueRepository->findBy(['actif' => true], ['ordre' => 'ASC']);

        return $this->render('admin/templates_ajax.html.twig', [
            'templates' => $templates,
            'societes' => $societes,
            'conditionsVente' => $conditionsVente,
            'banques' => $banques,
        ]);
    }

    #[Route('/create', name: 'app_admin_templates_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $template = new Template();
            $template->setNom($data['nom']);
            $template->setTypeDocument($data['typeDocument']);

            if (!empty($data['societeId'])) {
                $societe = $this->societeRepository->find($data['societeId']);
                if ($societe) {
                    $template->setSociete($societe);
                }
            }

            if (!empty($data['conditionsVenteId'])) {
                $cgv = $this->conditionsVenteRepository->find($data['conditionsVenteId']);
                if ($cgv) {
                    $template->setConditionsVente($cgv);
                }
            }

            if (!empty($data['banqueId'])) {
                $banque = $this->banqueRepository->find($data['banqueId']);
                if ($banque) {
                    $template->setBanque($banque);
                }
            }

            // Les couleurs et logo viennent de la société, pas du template
            $template->setOptionsMiseEnPage($data['optionsMiseEnPage'] ?? null);
            $template->setActif($data['actif'] ?? true);

            // Ordre à la fin
            $maxOrdre = $this->templateRepository->createQueryBuilder('t')
                ->select('MAX(t.ordre)')
                ->getQuery()
                ->getSingleScalarResult();
            $template->setOrdre(($maxOrdre ?? 0) + 1);

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Template créé avec succès',
                'id' => $template->getId()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du template: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/{id}', name: 'app_admin_templates_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->json(['success' => false, 'message' => 'Template non trouvé'], 404);
        }

        return $this->json([
            'id' => $template->getId(),
            'nom' => $template->getNom(),
            'typeDocument' => $template->getTypeDocument(),
            'societe' => $template->getSociete() ? [
                'id' => $template->getSociete()->getId(),
                'nom' => $template->getSociete()->getNom()
            ] : null,
            'conditionsVente' => $template->getConditionsVente() ? [
                'id' => $template->getConditionsVente()->getId(),
                'nom' => $template->getConditionsVente()->getNom()
            ] : null,
            'banque' => $template->getBanque() ? [
                'id' => $template->getBanque()->getId(),
                'nom' => $template->getBanque()->getNom()
            ] : null,
            'actif' => $template->isActif()
        ]);
    }

    #[Route('/{id}', name: 'app_admin_templates_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->json(['success' => false, 'message' => 'Template non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $template->setNom($data['nom']);
            $template->setTypeDocument($data['typeDocument']);

            if (!empty($data['societeId'])) {
                $societe = $this->societeRepository->find($data['societeId']);
                if ($societe) {
                    $template->setSociete($societe);
                }
            }

            if (isset($data['conditionsVenteId'])) {
                if (!empty($data['conditionsVenteId'])) {
                    $cgv = $this->conditionsVenteRepository->find($data['conditionsVenteId']);
                    $template->setConditionsVente($cgv);
                } else {
                    $template->setConditionsVente(null);
                }
            }

            if (isset($data['banqueId'])) {
                if (!empty($data['banqueId'])) {
                    $banque = $this->banqueRepository->find($data['banqueId']);
                    $template->setBanque($banque);
                } else {
                    $template->setBanque(null);
                }
            }

            // Les couleurs et logo viennent de la société, pas du template
            $template->setOptionsMiseEnPage($data['optionsMiseEnPage'] ?? null);
            $template->setActif($data['actif'] ?? true);

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Template modifié avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/{id}', name: 'app_admin_templates_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->json(['success' => false, 'message' => 'Template non trouvé'], 404);
        }

        try {
            $this->entityManager->remove($template);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Template supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/{id}/toggle', name: 'app_admin_templates_toggle', methods: ['POST'])]
    public function toggle(int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->json(['success' => false, 'message' => 'Template non trouvé'], 404);
        }

        try {
            $template->setActif(!$template->isActif());
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'actif' => $template->isActif()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 400);
        }
    }

    #[Route('/reorder', name: 'app_admin_templates_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ordre = $data['ordre'] ?? [];

        try {
            foreach ($ordre as $index => $id) {
                $template = $this->templateRepository->find($id);
                if ($template) {
                    $template->setOrdre($index + 1);
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Ordre mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'ordre: ' . $e->getMessage()
            ], 400);
        }
    }
}
