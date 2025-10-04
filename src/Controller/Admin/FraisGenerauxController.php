<?php

namespace App\Controller\Admin;

use App\Entity\FraisGeneraux;
use App\Form\FraisGenerauxType;
use App\Repository\FraisGenerauxRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/frais-generaux')]
class FraisGenerauxController extends AbstractController
{
    #[Route('/', name: 'admin_frais_generaux_index', methods: ['GET'])]
    public function index(FraisGenerauxRepository $repository, Request $request): Response
    {
        $periode = $request->query->get('periode', (new \DateTimeImmutable())->format('Y-m'));
        $fraisGeneraux = $repository->findActifsPourPeriode($periode);

        // Récupérer toutes les périodes disponibles
        $periodes = $repository->createQueryBuilder('f')
            ->select('DISTINCT f.periode')
            ->orderBy('f.periode', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return $this->render('admin/frais_generaux/index.html.twig', [
            'frais_generaux' => $fraisGeneraux,
            'periode_courante' => $periode,
            'periodes' => array_column($periodes, 'periode'),
        ]);
    }

    #[Route('/new', name: 'admin_frais_generaux_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $frais = new FraisGeneraux();
        $form = $this->createForm(FraisGenerauxType::class, $frais);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($frais);
            $em->flush();

            $this->addFlash('success', 'Frais généraux créés avec succès.');
            return $this->redirectToRoute('admin_frais_generaux_index');
        }

        return $this->render('admin/frais_generaux/new.html.twig', [
            'frais' => $frais,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_frais_generaux_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FraisGeneraux $frais, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FraisGenerauxType::class, $frais);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Frais généraux modifiés avec succès.');
            return $this->redirectToRoute('admin_frais_generaux_index');
        }

        return $this->render('admin/frais_generaux/edit.html.twig', [
            'frais' => $frais,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_frais_generaux_delete', methods: ['POST'])]
    public function delete(Request $request, FraisGeneraux $frais, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$frais->getId(), $request->request->get('_token'))) {
            $em->remove($frais);
            $em->flush();

            $this->addFlash('success', 'Frais généraux supprimés avec succès.');
        }

        return $this->redirectToRoute('admin_frais_generaux_index');
    }

    #[Route('/{id}/toggle', name: 'admin_frais_generaux_toggle', methods: ['POST'])]
    public function toggle(FraisGeneraux $frais, EntityManagerInterface $em): Response
    {
        $frais->setActif(!$frais->isActif());
        $em->flush();

        return $this->json([
            'success' => true,
            'actif' => $frais->isActif()
        ]);
    }

    #[Route('/{id}/duplicate', name: 'admin_frais_generaux_duplicate', methods: ['POST'])]
    public function duplicate(FraisGeneraux $fraisOriginal, EntityManagerInterface $em, Request $request): Response
    {
        $nouvellePeriode = $request->request->get('nouvelle_periode');

        if (!$nouvellePeriode || !preg_match('/^\d{4}-\d{2}$/', $nouvellePeriode)) {
            $this->addFlash('error', 'Période invalide (format attendu: YYYY-MM).');
            return $this->redirectToRoute('admin_frais_generaux_index');
        }

        $fraisDuplique = new FraisGeneraux();
        $fraisDuplique->setLibelle($fraisOriginal->getLibelle());
        $fraisDuplique->setMontantMensuel($fraisOriginal->getMontantMensuel());
        $fraisDuplique->setTypeRepartition($fraisOriginal->getTypeRepartition());
        $fraisDuplique->setVolumeDevisMensuelEstime($fraisOriginal->getVolumeDevisMensuelEstime());
        $fraisDuplique->setHeuresMOMensuelles($fraisOriginal->getHeuresMOMensuelles());
        $fraisDuplique->setCoefficientMajoration($fraisOriginal->getCoefficientMajoration());
        $fraisDuplique->setDescription($fraisOriginal->getDescription());
        $fraisDuplique->setOrdre($fraisOriginal->getOrdre());
        $fraisDuplique->setPeriode($nouvellePeriode);
        $fraisDuplique->setActif(true);

        $em->persist($fraisDuplique);
        $em->flush();

        $this->addFlash('success', 'Frais généraux dupliqués pour la période ' . $nouvellePeriode . '.');
        return $this->redirectToRoute('admin_frais_generaux_index', ['periode' => $nouvellePeriode]);
    }
}
