<?php

namespace App\Controller;

use App\Entity\SecteurZone;
use App\Form\SecteurZoneType;
use App\Repository\SecteurZoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/secteur/zone')]
final class SecteurZoneController extends AbstractController
{
    #[Route(name: 'app_secteur_zone_index', methods: ['GET'])]
    public function index(SecteurZoneRepository $secteurZoneRepository): Response
    {
        return $this->render('secteur_zone/index.html.twig', [
            'secteur_zones' => $secteurZoneRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_secteur_zone_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $secteurZone = new SecteurZone();
        $form = $this->createForm(SecteurZoneType::class, $secteurZone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($secteurZone);
            $entityManager->flush();

            return $this->redirectToRoute('app_secteur_zone_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('secteur_zone/new.html.twig', [
            'secteur_zone' => $secteurZone,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_secteur_zone_show', methods: ['GET'])]
    public function show(SecteurZone $secteurZone): Response
    {
        return $this->render('secteur_zone/show.html.twig', [
            'secteur_zone' => $secteurZone,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_secteur_zone_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SecteurZone $secteurZone, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SecteurZoneType::class, $secteurZone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_secteur_zone_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('secteur_zone/edit.html.twig', [
            'secteur_zone' => $secteurZone,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_secteur_zone_delete', methods: ['POST'])]
    public function delete(Request $request, SecteurZone $secteurZone, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$secteurZone->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($secteurZone);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_secteur_zone_index', [], Response::HTTP_SEE_OTHER);
    }
}
