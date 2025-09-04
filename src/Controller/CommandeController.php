<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
use App\Service\WorkflowService;
use App\Service\AutoEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commande')]
class CommandeController extends AbstractController
{
    public function __construct(
        private WorkflowService $workflowService,
        private AutoEventService $autoEventService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_commande_index', methods: ['GET'])]
    public function index(CommandeRepository $commandeRepository): Response
    {
        return $this->render('commande/index.html.twig', [
            'commandes' => $commandeRepository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_commande_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $commande = new Commande();
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($commande);
            $entityManager->flush();

            $this->addFlash('success', 'Commande créée avec succès !');

            return $this->redirectToRoute('app_commande_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commande/new.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_commande_show', methods: ['GET'])]
    public function show(Commande $commande): Response
    {
        $actions = $this->workflowService->getCommandeActions($commande);
        
        return $this->render('commande/show.html.twig', [
            'commande' => $commande,
            'workflow_actions' => $actions,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_commande_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Commande $commande, EntityManagerInterface $entityManager): Response
    {
        // Sauvegarder l'ancienne date de livraison pour détecter les changements
        $oldDateLivraison = $commande->getDateLivraisonPrevue();
        
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $commande->setUpdatedAt(new \DateTimeImmutable());
            
            // Créer automatiquement un événement de livraison si une nouvelle date est définie
            $newDateLivraison = $commande->getDateLivraisonPrevue();
            if ($newDateLivraison && (!$oldDateLivraison || $oldDateLivraison->format('Y-m-d') !== $newDateLivraison->format('Y-m-d'))) {
                $this->autoEventService->createLivraisonEvent($commande);
            }
            
            // Créer également un événement si la date de livraison réelle est définie/modifiée
            $newDateLivraisonReelle = $commande->getDateLivraisonReelle();
            $oldDateLivraisonReelle = $entityManager->getUnitOfWork()->getOriginalEntityData($commande)['dateLivraisonReelle'] ?? null;
            if ($newDateLivraisonReelle && (!$oldDateLivraisonReelle || $oldDateLivraisonReelle->format('Y-m-d') !== $newDateLivraisonReelle->format('Y-m-d'))) {
                $this->autoEventService->createLivraisonEvent($commande);
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Commande modifiée avec succès !');

            return $this->redirectToRoute('app_commande_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commande/edit.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_commande_delete', methods: ['POST'])]
    public function delete(Request $request, Commande $commande, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$commande->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($commande);
            $entityManager->flush();
            
            $this->addFlash('success', 'Commande supprimée avec succès !');
        }

        return $this->redirectToRoute('app_commande_index', [], Response::HTTP_SEE_OTHER);
    }
}