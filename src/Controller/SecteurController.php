<?php

namespace App\Controller;

use App\Entity\Secteur;
use App\Entity\CommuneFrancaise;
use App\Entity\ExclusionSecteur;
use App\Form\SecteurType;
use App\Form\SecteurModerneType;
use App\Repository\SecteurRepository;
use App\Repository\CommuneFrancaiseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/secteur')]
#[IsGranted('ROLE_ADMIN')]
final class SecteurController extends AbstractController
{
    #[Route(name: 'app_secteur_index', methods: ['GET'])]
    public function index(SecteurRepository $secteurRepository): Response
    {
        return $this->render('secteur/index.html.twig', [
            'secteurs' => $secteurRepository->findAll(),
        ]);
    }

    #[Route('/moderne', name: 'app_secteur_index_moderne', methods: ['GET'])]
    public function indexModerne(SecteurRepository $secteurRepository, ParameterBagInterface $params): Response
    {
        $secteurs = $secteurRepository->createQueryBuilder('s')
            ->leftJoin('s.commercial', 'c')
            ->addSelect('c')
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('secteur/index_moderne.html.twig', [
            'secteurs' => $secteurs,
            'google_maps_api_key' => $params->get('google.maps.api.key'),
        ]);
    }

    #[Route('/new', name: 'app_secteur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $secteur = new Secteur();
        $form = $this->createForm(SecteurModerneType::class, $secteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($secteur);
            $entityManager->flush();
            
            $this->addFlash('success', 'Secteur créé avec succès ! Vous pouvez maintenant définir sa couverture géographique.');
            return $this->redirectToRoute('app_secteur_edit', ['id' => $secteur->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('secteur/new_moderne.html.twig', [
            'secteur' => $secteur,
            'form' => $form,
        ]);
    }


    #[Route('/ajax/new-form', name: 'app_secteur_new_ajax', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function newAjax(): JsonResponse
    {
        try {
            $secteur = new Secteur();
            $form = $this->createForm(SecteurModerneType::class, $secteur, [
                'action' => $this->generateUrl('app_secteur_create_ajax'),
                'method' => 'POST'
            ]);

            $formHtml = $this->renderView('secteur/_form_modal_with_attributions.html.twig', [
                'form' => $form,
                'secteur' => $secteur,
                'is_edit' => false
            ]);

            return new JsonResponse([
                'success' => true,
                'html' => $formHtml,
                'title' => 'Nouveau secteur'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/ajax/{id}', name: 'app_secteur_show_ajax', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function showAjax(Secteur $secteur, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Forcer le chargement des relations
            $entityManager->refresh($secteur);
            
            // Récupérer les attributions avec leurs détails
            $attributions = [];
            foreach ($secteur->getAttributions() as $attribution) {
                $division = $attribution->getDivisionAdministrative();
                
                // Obtenir le nom selon le type
                $nomDivision = match($attribution->getTypeCritere()) {
                    'commune' => $division->getNomCommune(),
                    'canton' => $division->getNomCanton(), 
                    'epci' => $division->getNomEpci(),
                    'departement' => $division->getNomDepartement(),
                    'region' => $division->getNomRegion(),
                    default => $attribution->getValeurCritere() // Fallback sur la valeur
                };
                
                $attributions[] = [
                    'id' => $attribution->getId(),
                    'typeCritere' => $attribution->getTypeCritere(),
                    'valeurCritere' => $attribution->getValeurCritere(),
                    'notes' => $attribution->getNotes(),
                    'division' => [
                        'nom' => $nomDivision ?: $attribution->getValeurCritere(),
                        'typeCritere' => $attribution->getTypeCritere() // Utiliser le type du critère à la place
                    ]
                ];
            }

            $data = [
                'id' => $secteur->getId(),
                'nomSecteur' => $secteur->getNomSecteur(),
                'description' => $secteur->getDescription(),
                'couleurHex' => $secteur->getCouleurHex(),
                'isActive' => $secteur->isActive(),
                'createdAt' => $secteur->getCreatedAt()?->format('d/m/Y H:i'),
                'updatedAt' => $secteur->getUpdatedAt()?->format('d/m/Y H:i'),
                'commercial' => $secteur->getCommercial() ? [
                    'id' => $secteur->getCommercial()->getId(),
                    'nom' => $secteur->getCommercial()->getNom(),
                    'email' => $secteur->getCommercial()->getEmail()
                ] : null,
                'clients_count' => count($secteur->getClients()),
                'attributions_count' => count($secteur->getAttributions()),
                'attributions' => $attributions
            ];
            
            return new JsonResponse(['success' => true, 'secteur' => $data]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/ajax/create', name: 'app_secteur_create_ajax', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createAjax(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $secteur = new Secteur();
        $form = $this->createForm(SecteurModerneType::class, $secteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($secteur);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Secteur créé avec succès !',
                'secteur' => [
                    'id' => $secteur->getId(),
                    'nomSecteur' => $secteur->getNomSecteur()
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse([
            'success' => false,
            'errors' => $errors
        ], 400);
    }

    #[Route('/ajax/{id}/edit-form', name: 'app_secteur_edit_ajax', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function editAjax(Secteur $secteur): JsonResponse
    {
        try {
            $form = $this->createForm(SecteurModerneType::class, $secteur, [
                'action' => $this->generateUrl('app_secteur_update_ajax', ['id' => $secteur->getId()]),
                'method' => 'POST'
            ]);

            $formHtml = $this->renderView('secteur/_form_modal_with_attributions.html.twig', [
                'form' => $form,
                'secteur' => $secteur,
                'is_edit' => true
            ]);

            return new JsonResponse([
                'success' => true,
                'html' => $formHtml,
                'title' => 'Modifier le secteur : ' . $secteur->getNomSecteur()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/ajax/{id}/update', name: 'app_secteur_update_ajax', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function updateAjax(Request $request, Secteur $secteur, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $form = $this->createForm(SecteurModerneType::class, $secteur);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $secteur->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Secteur modifié avec succès !',
                    'secteur' => [
                        'id' => $secteur->getId(),
                        'nomSecteur' => $secteur->getNomSecteur()
                    ]
                ]);
            }

            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'errors' => $errors
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/ajax/{id}/delete', name: 'app_secteur_delete_ajax', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function deleteAjax(Secteur $secteur, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Avant de supprimer le secteur, nettoyer toutes les exclusions créées par ses attributions
            $this->nettoyerExclusionsAvantSuppressionSecteur($secteur, $entityManager);
            
            $entityManager->remove($secteur);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Secteur supprimé avec succès !'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/ajax/{id}/attributions', name: 'app_secteur_attributions_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function getAttributionsList(Secteur $secteur, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Forcer le chargement des relations
            $entityManager->refresh($secteur);
            
            $attributions = [];
            foreach ($secteur->getAttributions() as $attribution) {
                $division = $attribution->getDivisionAdministrative();
                
                // Obtenir le nom selon le type
                $nomDivision = match($attribution->getTypeCritere()) {
                    'commune' => $division->getNomCommune(),
                    'canton' => $division->getNomCanton(), 
                    'epci' => $division->getNomEpci(),
                    'departement' => $division->getNomDepartement(),
                    'region' => $division->getNomRegion(),
                    default => $attribution->getValeurCritere()
                };
                
                $attributions[] = [
                    'id' => $attribution->getId(),
                    'typeCritere' => $attribution->getTypeCritere(),
                    'valeurCritere' => $attribution->getValeurCritere(),
                    'notes' => $attribution->getNotes(),
                    'division' => [
                        'nom' => $nomDivision ?: $attribution->getValeurCritere()
                    ]
                ];
            }

            return new JsonResponse([
                'success' => true,
                'attributions' => $attributions
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/edit', name: 'app_secteur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Secteur $secteur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SecteurModerneType::class, $secteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mettre à jour le timestamp
            $secteur->setUpdatedAt(new \DateTimeImmutable());
            
            // Sauvegarder les modifications
            $entityManager->flush();
            
            $this->addFlash('success', 'Secteur modifié avec succès !');
            return $this->redirectToRoute('app_secteur_show', ['id' => $secteur->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('secteur/edit_moderne.html.twig', [
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


    #[Route('/{id}', name: 'app_secteur_delete', methods: ['POST'])]
    public function delete(Request $request, Secteur $secteur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$secteur->getId(), $request->getPayload()->getString('_token'))) {
            // Avant de supprimer le secteur, nettoyer toutes les exclusions créées par ses attributions
            $this->nettoyerExclusionsAvantSuppressionSecteur($secteur, $entityManager);
            
            $entityManager->remove($secteur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_secteur_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Nettoie toutes les exclusions créées par les attributions d'un secteur avant sa suppression
     */
    private function nettoyerExclusionsAvantSuppressionSecteur(Secteur $secteur, EntityManagerInterface $entityManager): void
    {
        error_log("🧹 Nettoyage exclusions avant suppression du secteur '{$secteur->getNomSecteur()}'");
        
        // Pour chaque attribution du secteur, supprimer les exclusions qu'elle a créées dans d'autres secteurs
        foreach ($secteur->getAttributions() as $attribution) {
            $typeCritere = $attribution->getTypeCritere();
            $valeurCritere = $attribution->getValeurCritere();
            
            // Logique différente selon le type d'attribution
            if ($typeCritere === 'code_postal') {
                // Pour un code postal, supprimer toutes les exclusions de communes de ce code postal
                error_log("🔍 Nettoyage exclusions pour code postal $valeurCritere");
                
                // Trouver toutes les communes de ce code postal
                $communesCodePostal = $entityManager->createQuery('
                    SELECT d FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codePostal = :codePostal 
                    AND d.codeInseeCommune IS NOT NULL
                ')
                ->setParameter('codePostal', $valeurCritere)
                ->getResult();
                
                // Pour chaque commune, supprimer ses exclusions
                foreach ($communesCodePostal as $commune) {
                    $exclusionsCommuneCodePostal = $entityManager->getRepository(ExclusionSecteur::class)
                        ->findBy([
                            'divisionAdministrative' => $commune,
                            'typeExclusion' => 'commune',
                            'valeurExclusion' => $commune->getCodeInseeCommune()
                        ]);
                    
                    foreach ($exclusionsCommuneCodePostal as $exclusion) {
                        $secteurAffecte = $exclusion->getAttributionSecteur()->getSecteur()->getNomSecteur();
                        error_log("🗑️ Suppression exclusion commune '{$commune->getCodeInseeCommune()}' ({$commune->getNomCommune()}) du secteur '$secteurAffecte'");
                        $entityManager->remove($exclusion);
                    }
                }
            } else {
                // Pour les autres types (commune, epci, etc.), logique existante
                $exclusionsCrees = $entityManager->getRepository(ExclusionSecteur::class)
                    ->findBy([
                        'divisionAdministrative' => $attribution->getDivisionAdministrative(),
                        'valeurExclusion' => $valeurCritere
                    ]);
                
                foreach ($exclusionsCrees as $exclusion) {
                    $secteurAffecte = $exclusion->getAttributionSecteur()->getSecteur()->getNomSecteur();
                    error_log("🗑️ Suppression exclusion {$typeCritere} '{$valeurCritere}' du secteur '$secteurAffecte'");
                    $entityManager->remove($exclusion);
                }
            }
            
            // Supprimer aussi les exclusions parentes (si cette attribution en avait)
            $exclusionsParentes = $entityManager->getRepository(ExclusionSecteur::class)
                ->findBy(['attributionSecteur' => $attribution]);
            
            foreach ($exclusionsParentes as $exclusion) {
                error_log("🗑️ Suppression exclusion parente ID {$exclusion->getId()}");
                $entityManager->remove($exclusion);
            }
        }
        
        // Ne pas faire de flush ici - laisser le contrôleur principal le faire
        error_log("✅ Nettoyage exclusions terminé");
    }
}
