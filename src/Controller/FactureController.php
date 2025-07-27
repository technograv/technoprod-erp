<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Form\FactureType;
use App\Repository\FactureRepository;
use App\Service\WorkflowService;
use App\Service\FacturXService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/facture')]
class FactureController extends AbstractController
{
    public function __construct(
        private WorkflowService $workflowService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_facture_index', methods: ['GET'])]
    public function index(FactureRepository $factureRepository): Response
    {
        return $this->render('facture/index.html.twig', [
            'factures' => $factureRepository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_facture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $facture = new Facture();
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($facture);
            $entityManager->flush();

            $this->addFlash('success', 'Facture créée avec succès !');

            return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('facture/new.html.twig', [
            'facture' => $facture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_facture_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        $actions = $this->workflowService->getFactureActions($facture);
        
        return $this->render('facture/show.html.twig', [
            'facture' => $facture,
            'workflow_actions' => $actions,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_facture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $facture->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Facture modifiée avec succès !');

            return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('facture/edit.html.twig', [
            'facture' => $facture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_facture_delete', methods: ['POST'])]
    public function delete(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$facture->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($facture);
            $entityManager->flush();
            
            $this->addFlash('success', 'Facture supprimée avec succès !');
        }

        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/factur-x', name: 'app_facture_facturx', methods: ['GET'])]
    public function generateFacturX(
        Facture $facture, 
        FacturXService $facturXService,
        Request $request
    ): Response {
        try {
            // Récupération du profil depuis les paramètres (défaut: BASIC)
            $profile = $request->query->get('profile', 'BASIC');
            $signDocument = $request->query->getBoolean('sign', true);
            
            // Validation du profil
            $allowedProfiles = ['MINIMUM', 'BASIC_WL', 'BASIC', 'EN16931'];
            if (!in_array($profile, $allowedProfiles)) {
                throw new \InvalidArgumentException("Profil invalide: $profile");
            }
            
            // Génération du Factur-X
            return $facturXService->exportFacturXFile($facture, $profile);
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération Factur-X: ' . $e->getMessage());
            return $this->redirectToRoute('app_facture_show', ['id' => $facture->getId()]);
        }
    }

    #[Route('/{id}/xml-cii', name: 'app_facture_xml_cii', methods: ['GET'])]
    public function generateXMLCII(
        Facture $facture, 
        FacturXService $facturXService,
        Request $request
    ): Response {
        try {
            $profile = $request->query->get('profile', 'BASIC');
            
            // Génération du XML CII seul
            $xmlContent = $facturXService->generateXMLCII($facture, $profile);
            
            // Validation
            $facturXService->validateFacturX($xmlContent, $profile);
            
            // Retour en tant que fichier XML
            $response = new Response($xmlContent);
            $response->headers->set('Content-Type', 'application/xml; charset=utf-8');
            $response->headers->set('Content-Disposition', 
                'attachment; filename="facture-' . $facture->getNumeroFacture() . '-cii.xml"'
            );
            
            return $response;
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération XML CII: ' . $e->getMessage());
            return $this->redirectToRoute('app_facture_show', ['id' => $facture->getId()]);
        }
    }
}