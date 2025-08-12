<?php

namespace App\Controller;

use App\Entity\Prospect;
use App\Entity\AdresseFacturation;
use App\Entity\AdresseLivraison;
use App\Entity\ContactFacturation;
use App\Entity\ContactLivraison;
use App\Form\ProspectType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/prospect')]
final class ProspectController extends AbstractController
{
    #[Route('/', name: 'app_prospect_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $repository = $entityManager->getRepository(Prospect::class);
        
        // Récupérer les paramètres de filtrage
        $statut = $request->query->get('statut');
        $famille = $request->query->get('famille');
        $secteurId = $request->query->get('secteur');
        
        // Construire la requête avec filtres
        $queryBuilder = $repository->createQueryBuilder('p')
            ->leftJoin('p.adresseFacturation', 'af')
            ->leftJoin('p.contactFacturation', 'cf')
            ->leftJoin('p.secteur', 's')
            ->leftJoin('p.commercial', 'c');
        
        if ($statut) {
            $queryBuilder->andWhere('p.statut = :statut')
                         ->setParameter('statut', $statut);
        }
        
        if ($famille) {
            $queryBuilder->andWhere('p.famille LIKE :famille')
                         ->setParameter('famille', '%' . $famille . '%');
        }
        
        if ($secteurId) {
            $queryBuilder->andWhere('p.secteur = :secteur')
                         ->setParameter('secteur', $secteurId);
        }
        
        $prospects = $queryBuilder->orderBy('p.updatedAt', 'DESC')
                                 ->getQuery()
                                 ->getResult();
        
        // Calculer les statistiques
        $statsQb = $repository->createQueryBuilder('p');
        $totalProspects = $statsQb->select('COUNT(p.id)')
                                  ->where('p.statut = :statut')
                                  ->setParameter('statut', 'prospect')
                                  ->getQuery()
                                  ->getSingleScalarResult();
        
        $totalClients = $statsQb->select('COUNT(p.id)')
                               ->where('p.statut = :statut')
                               ->setParameter('statut', 'client')
                               ->getQuery()
                               ->getSingleScalarResult();
        
        $thisMonth = new \DateTimeImmutable('first day of this month');
        $conversionsThisMonth = $statsQb->select('COUNT(p.id)')
                                        ->where('p.statut = :statut')
                                        ->andWhere('p.dateConversionClient >= :thisMonth')
                                        ->setParameter('statut', 'client')
                                        ->setParameter('thisMonth', $thisMonth)
                                        ->getQuery()
                                        ->getSingleScalarResult();
        
        $stats = [
            'prospects' => $totalProspects,
            'clients' => $totalClients,
            'conversions' => $conversionsThisMonth
        ];
        
        // Récupérer la liste des secteurs pour le filtre
        $secteurs = $entityManager->getRepository(\App\Entity\Secteur::class)->findBy([], ['nomSecteur' => 'ASC']);

        return $this->render('prospect/index.html.twig', [
            'prospects' => $prospects,
            'stats' => $stats,
            'secteurs' => $secteurs,
        ]);
    }

    #[Route('/new', name: 'app_prospect_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $prospect = new Prospect();
        
        // Initialiser les sous-entités
        $prospect->setAdresseFacturation(new AdresseFacturation());
        $prospect->setAdresseLivraison(new AdresseLivraison());
        $prospect->setContactFacturation(new ContactFacturation());
        $prospect->setContactLivraison(new ContactLivraison());
        
        $form = $this->createForm(ProspectType::class, $prospect);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer le prochain code disponible
            $repository = $entityManager->getRepository(Prospect::class);
            $nextCode = $repository->getNextProspectCode();
            $prospect->setCode($nextCode);
            
            // Persister avec le code final
            $entityManager->persist($prospect);
            $entityManager->flush();

            $this->addFlash('success', 'Prospect créé avec succès !');

            return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getId()]);
        }

        return $this->render('prospect/new.html.twig', [
            'prospect' => $prospect,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_prospect_show', methods: ['GET'])]
    public function show(Prospect $prospect): Response
    {
        return $this->render('prospect/show.html.twig', [
            'prospect' => $prospect,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_prospect_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        // S'assurer que les entités liées existent pour éviter les problèmes de formulaire
        if ($prospect->getAdresseFacturation() === null) {
            $adresseFacturation = new AdresseFacturation();
            $prospect->setAdresseFacturation($adresseFacturation);
            $entityManager->persist($adresseFacturation);
        }
        
        if ($prospect->getAdresseLivraison() === null) {
            $adresseLivraison = new AdresseLivraison();
            $prospect->setAdresseLivraison($adresseLivraison);
            $entityManager->persist($adresseLivraison);
        }
        
        if ($prospect->getContactFacturation() === null) {
            $contactFacturation = new ContactFacturation();
            $prospect->setContactFacturation($contactFacturation);
            $entityManager->persist($contactFacturation);
        }
        
        if ($prospect->getContactLivraison() === null) {
            $contactLivraison = new ContactLivraison();
            $prospect->setContactLivraison($contactLivraison);
            $entityManager->persist($contactLivraison);
        }
        
        $form = $this->createForm(ProspectType::class, $prospect);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $prospect->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'Prospect modifié avec succès !');

                return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getId()]);
            } else {
                // Debug: afficher les erreurs du formulaire
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $fieldName = $error->getOrigin() ? $error->getOrigin()->getName() : 'général';
                    $errors[] = "$fieldName: " . $error->getMessage();
                }
                
                if (!empty($errors)) {
                    // Vérifier si c'est une erreur CSRF
                    $csrfError = false;
                    foreach ($errors as $error) {
                        if (strpos($error, 'CSRF token') !== false) {
                            $csrfError = true;
                            break;
                        }
                    }
                    
                    $this->addFlash('error', 'Erreurs de validation: ' . implode(' | ', $errors));
                } else {
                    $this->addFlash('error', 'Erreur de validation non spécifiée.');
                }
            }
        }

        return $this->render('prospect/edit.html.twig', [
            'prospect' => $prospect,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/convert', name: 'app_prospect_convert_to_client', methods: ['POST'])]
    public function convertToClient(Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        if ($prospect->isClient()) {
            $this->addFlash('error', 'Ce prospect est déjà un client !');
            return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getId()]);
        }

        $prospect->convertToClient();
        $entityManager->flush();

        $this->addFlash('success', 'Prospect converti en client avec succès !');

        return $this->redirectToRoute('app_prospect_show', ['id' => $prospect->getId()]);
    }

    #[Route('/{id}', name: 'app_prospect_delete', methods: ['POST'])]
    public function delete(Request $request, Prospect $prospect, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$prospect->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($prospect);
            $entityManager->flush();
            
            $this->addFlash('success', 'Prospect supprimé avec succès !');
        }

        return $this->redirectToRoute('app_prospect_index');
    }

    #[Route('/{id}/data', name: 'app_prospect_data', methods: ['GET'])]
    public function getData(Prospect $prospect): JsonResponse
    {
        $data = [
            'civilite' => $prospect->getCivilite(),
            'nom' => $prospect->getNom(),
            'prenom' => $prospect->getPrenom(),
            'modeReglement' => $prospect->getModePaiement(),
            'adresse' => null,
            'codePostal' => null,
            'ville' => null
        ];

        // Récupérer l'adresse de facturation si elle existe
        if ($prospect->getAdresseFacturation()) {
            $adresse = $prospect->getAdresseFacturation();
            $data['adresse'] = $adresse->getLigne1();
            $data['codePostal'] = $adresse->getCodePostal();
            $data['ville'] = $adresse->getVille();
        }

        return $this->json($data);
    }
}