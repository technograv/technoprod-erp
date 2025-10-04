<?php

namespace App\Controller\Admin;

use App\Entity\FamilleProduit;
use App\Form\FamilleProduitType;
use App\Repository\FamilleProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/famille-produit')]
class FamilleProduitController extends AbstractController
{
    #[Route('/', name: 'admin_famille_produit_index', methods: ['GET'])]
    public function index(FamilleProduitRepository $repository): Response
    {
        $racines = $repository->findRacines();

        return $this->render('admin/famille_produit/index.html.twig', [
            'racines' => $racines,
        ]);
    }

    #[Route('/new', name: 'admin_famille_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $famille = new FamilleProduit();
        $form = $this->createForm(FamilleProduitType::class, $famille);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($famille);
            $em->flush();

            $this->addFlash('success', 'Famille de produit créée avec succès.');
            return $this->redirectToRoute('admin_famille_produit_index');
        }

        return $this->render('admin/famille_produit/new.html.twig', [
            'famille' => $famille,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_famille_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FamilleProduit $famille, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FamilleProduitType::class, $famille);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Famille de produit modifiée avec succès.');
            return $this->redirectToRoute('admin_famille_produit_index');
        }

        return $this->render('admin/famille_produit/edit.html.twig', [
            'famille' => $famille,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_famille_produit_delete', methods: ['POST'])]
    public function delete(Request $request, FamilleProduit $famille, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$famille->getId(), $request->request->get('_token'))) {
            // Vérifier s'il y a des produits liés
            if ($famille->getProduits()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer cette famille : elle contient des produits.');
                return $this->redirectToRoute('admin_famille_produit_index');
            }

            // Vérifier s'il y a des enfants
            if ($famille->getEnfants()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer cette famille : elle contient des sous-familles.');
                return $this->redirectToRoute('admin_famille_produit_index');
            }

            $em->remove($famille);
            $em->flush();

            $this->addFlash('success', 'Famille de produit supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_famille_produit_index');
    }

    #[Route('/{id}/toggle', name: 'admin_famille_produit_toggle', methods: ['POST'])]
    public function toggle(FamilleProduit $famille, EntityManagerInterface $em): Response
    {
        $famille->setActif(!$famille->isActif());
        $em->flush();

        return $this->json([
            'success' => true,
            'actif' => $famille->isActif()
        ]);
    }
}
