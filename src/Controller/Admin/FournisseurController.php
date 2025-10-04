<?php

namespace App\Controller\Admin;

use App\Entity\Fournisseur;
use App\Form\FournisseurType;
use App\Repository\FournisseurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/fournisseur')]
class FournisseurController extends AbstractController
{
    #[Route('/', name: 'admin_fournisseur_index', methods: ['GET'])]
    public function index(FournisseurRepository $repository): Response
    {
        $fournisseurs = $repository->findBy([], ['raisonSociale' => 'ASC']);

        return $this->render('admin/fournisseur/index.html.twig', [
            'fournisseurs' => $fournisseurs,
        ]);
    }

    #[Route('/new', name: 'admin_fournisseur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $fournisseur = new Fournisseur();
        $form = $this->createForm(FournisseurType::class, $fournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($fournisseur);
            $em->flush();

            $this->addFlash('success', 'Fournisseur créé avec succès.');
            return $this->redirectToRoute('admin_fournisseur_index');
        }

        return $this->render('admin/fournisseur/new.html.twig', [
            'fournisseur' => $fournisseur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_fournisseur_show', methods: ['GET'])]
    public function show(Fournisseur $fournisseur): Response
    {
        return $this->render('admin/fournisseur/show.html.twig', [
            'fournisseur' => $fournisseur,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_fournisseur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Fournisseur $fournisseur, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FournisseurType::class, $fournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Fournisseur modifié avec succès.');
            return $this->redirectToRoute('admin_fournisseur_index');
        }

        return $this->render('admin/fournisseur/edit.html.twig', [
            'fournisseur' => $fournisseur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_fournisseur_delete', methods: ['POST'])]
    public function delete(Request $request, Fournisseur $fournisseur, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fournisseur->getId(), $request->request->get('_token'))) {
            // Vérifier s'il y a des produits liés
            if ($fournisseur->getProduitsCommeDefaut()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer ce fournisseur : il est fournisseur principal de produits.');
                return $this->redirectToRoute('admin_fournisseur_index');
            }

            $em->remove($fournisseur);
            $em->flush();

            $this->addFlash('success', 'Fournisseur supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_fournisseur_index');
    }

    #[Route('/{id}/toggle', name: 'admin_fournisseur_toggle', methods: ['POST'])]
    public function toggle(Fournisseur $fournisseur, EntityManagerInterface $em): Response
    {
        // Toggle entre actif/inactif (pas bloqué)
        $nouveauStatut = $fournisseur->getStatut() === 'actif' ? 'inactif' : 'actif';
        $fournisseur->setStatut($nouveauStatut);
        $em->flush();

        return $this->json([
            'success' => true,
            'statut' => $fournisseur->getStatut()
        ]);
    }

    #[Route('/search', name: 'admin_fournisseur_search', methods: ['GET'])]
    public function search(Request $request, FournisseurRepository $repository): Response
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $fournisseurs = $repository->searchActifs($query);

        $results = array_map(function(Fournisseur $f) {
            return [
                'id' => $f->getId(),
                'text' => $f->getRaisonSociale() . ' (' . $f->getCode() . ')',
                'code' => $f->getCode(),
                'raison_sociale' => $f->getRaisonSociale()
            ];
        }, $fournisseurs);

        return $this->json(['results' => $results]);
    }
}
