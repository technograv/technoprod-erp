<?php

namespace App\Controller;

use App\Entity\Secteur;
use App\Entity\SecteurZone;
use App\Entity\Zone;
use App\Entity\CommuneFrancaise;
use App\Form\SecteurType;
use App\Repository\SecteurRepository;
use App\Repository\CommuneFrancaiseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/secteur')]
final class SecteurController extends AbstractController
{
    #[Route(name: 'app_secteur_index', methods: ['GET'])]
    public function index(SecteurRepository $secteurRepository): Response
    {
        return $this->render('secteur/index.html.twig', [
            'secteurs' => $secteurRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_secteur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $secteur = new Secteur();
        $form = $this->createForm(SecteurType::class, $secteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Debug: Compter les zones sélectionnées
            $zonesCount = count($secteur->getZones());
            $this->addFlash('info', "Zones sélectionnées: $zonesCount");
            
            $entityManager->persist($secteur);
            $entityManager->flush();
            
            $this->addFlash('success', 'Secteur créé avec succès !');
            return $this->redirectToRoute('app_secteur_show', ['id' => $secteur->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('secteur/new.html.twig', [
            'secteur' => $secteur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_secteur_show', methods: ['GET'])]
    public function show(Secteur $secteur, EntityManagerInterface $entityManager): Response
    {
        // Forcer le chargement des relations
        $entityManager->refresh($secteur);
        
        // Debug: compter les zones
        $zonesCount = count($secteur->getZones());
        $this->addFlash('info', "Ce secteur contient $zonesCount zones");
        
        return $this->render('secteur/show.html.twig', [
            'secteur' => $secteur,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_secteur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Secteur $secteur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SecteurType::class, $secteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Debug: Compter les zones avant sauvegarde
            $zonesCount = count($secteur->getZones());
            $this->addFlash('info', "Zones sélectionnées avant sauvegarde: $zonesCount");
            
            // Mettre à jour le timestamp
            $secteur->setUpdatedAt(new \DateTimeImmutable());
            
            // Forcer la persistence de l'entité et ses relations
            $entityManager->persist($secteur);
            $entityManager->flush();
            
            // Debug: Vérifier après sauvegarde
            $entityManager->refresh($secteur);
            $zonesCountAfter = count($secteur->getZones());
            $this->addFlash('info', "Zones après sauvegarde: $zonesCountAfter");
            
            $this->addFlash('success', 'Secteur modifié avec succès !');
            return $this->redirectToRoute('app_secteur_show', ['id' => $secteur->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('secteur/edit.html.twig', [
            'secteur' => $secteur,
            'form' => $form,
        ]);
    }

    #[Route('/api/communes/search', name: 'app_secteur_commune_search', methods: ['GET'])]
    public function searchCommunes(Request $request, CommuneFrancaiseRepository $communeRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }
        
        $communes = $communeRepository->searchForAutocomplete($query, 20);
        
        $results = [];
        foreach ($communes as $commune) {
            $results[] = [
                'id' => $commune->getId(),
                'text' => $commune->getCodePostal() . ' - ' . $commune->getNomCommune() . 
                         ($commune->getNomDepartement() ? ' (' . $commune->getNomDepartement() . ')' : ''),
                'codePostal' => $commune->getCodePostal(),
                'nomCommune' => $commune->getNomCommune(),
                'departement' => $commune->getNomDepartement(),
                'region' => $commune->getNomRegion(),
                'latitude' => $commune->getLatitude(),
                'longitude' => $commune->getLongitude()
            ];
        }
        
        return new JsonResponse($results);
    }

    #[Route('/api/zones', name: 'app_secteur_zones_list', methods: ['GET'])]
    public function listZones(EntityManagerInterface $entityManager): JsonResponse
    {
        $zones = $entityManager->getRepository(Zone::class)->findBy([], ['codePostal' => 'ASC']);
        
        $results = [];
        foreach ($zones as $zone) {
            $results[] = [
                'id' => $zone->getId(),
                'codePostal' => $zone->getCodePostal(),
                'ville' => $zone->getVille(),
                'departement' => $zone->getDepartement(),
                'region' => $zone->getRegion(),
                'latitude' => $zone->getLatitude(),
                'longitude' => $zone->getLongitude()
            ];
        }
        
        return new JsonResponse($results);
    }

    #[Route('/api/zone/create-from-commune', name: 'app_secteur_create_zone_from_commune', methods: ['POST'])]
    public function createZoneFromCommune(Request $request, EntityManagerInterface $entityManager, CommuneFrancaiseRepository $communeRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $communeId = $data['commune_id'] ?? null;
        
        if (!$communeId) {
            return new JsonResponse(['error' => 'ID commune manquant'], 400);
        }
        
        $commune = $communeRepository->find($communeId);
        if (!$commune) {
            return new JsonResponse(['error' => 'Commune introuvable'], 404);
        }
        
        // Vérifier si une zone existe déjà pour cette commune
        $existingZone = $entityManager->getRepository(Zone::class)->findOneBy(['commune' => $commune]);
        if ($existingZone) {
            return new JsonResponse([
                'id' => $existingZone->getId(),
                'text' => $existingZone->__toString(),
                'already_exists' => true
            ]);
        }
        
        // Créer une nouvelle zone basée sur la commune
        $zone = new Zone();
        $zone->setCommune($commune);
        
        $entityManager->persist($zone);
        $entityManager->flush();
        
        return new JsonResponse([
            'id' => $zone->getId(),
            'text' => $zone->__toString(),
            'created' => true
        ]);
    }

    #[Route('/{id}', name: 'app_secteur_delete', methods: ['POST'])]
    public function delete(Request $request, Secteur $secteur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$secteur->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($secteur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_secteur_index', [], Response::HTTP_SEE_OTHER);
    }
}
