<?php

namespace App\Controller;

use App\Entity\Adresse;
use App\Entity\Client;
use App\Form\AdresseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdresseController extends AbstractController
{
    #[Route('/adresse/modal/new/{clientId}', name: 'app_adresse_modal_new', methods: ['GET', 'POST'])]
    public function modalNew(int $clientId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = $entityManager->getRepository(Client::class)->find($clientId);
        if (!$client) {
            throw $this->createNotFoundException('Client non trouvé');
        }

        $adresse = new Adresse();
        $adresse->setClient($client);
        $adresse->setPays('France'); // Valeur par défaut

        if ($request->isMethod('POST')) {
            // Traitement des données du formulaire POST
            $nom = $request->request->get('nom');
            $ligne1 = $request->request->get('ligne1');
            $codePostal = $request->request->get('codePostal');
            $ville = $request->request->get('ville');
            $pays = $request->request->get('pays', 'France');

            if ($nom && $ligne1 && $codePostal && $ville) {
                $adresse->setNom($nom);
                $adresse->setLigne1($ligne1);
                $adresse->setCodePostal($codePostal);
                $adresse->setVille($ville);
                $adresse->setPays($pays);

                $entityManager->persist($adresse);
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Adresse créée avec succès',
                        'adresse' => [
                            'id' => $adresse->getId(),
                            'label' => $adresse->getDisplayLabel(),
                            'nom' => $adresse->getNom(),
                            'ligne1' => $adresse->getLigne1(),
                            'codePostal' => $adresse->getCodePostal(),
                            'ville' => $adresse->getVille(),
                            'pays' => $adresse->getPays()
                        ]
                    ]);
                }

                return $this->redirectToRoute('app_client_edit', ['id' => $clientId]);
            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Veuillez remplir tous les champs obligatoires'
                ], 400);
            }
        }

        return $this->render('adresse/modal_new.html.twig', [
            'client' => $client,
            'adresse' => $adresse
        ]);
    }

    #[Route('/adresse/modal/edit/{id}', name: 'app_adresse_modal_edit', methods: ['GET', 'POST'])]
    public function modalEdit(Adresse $adresse, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            // Traitement des données du formulaire POST
            $nom = $request->request->get('nom');
            $ligne1 = $request->request->get('ligne1');
            $codePostal = $request->request->get('codePostal');
            $ville = $request->request->get('ville');
            $pays = $request->request->get('pays', 'France');

            if ($nom && $ligne1 && $codePostal && $ville) {
                $adresse->setNom($nom);
                $adresse->setLigne1($ligne1);
                $adresse->setCodePostal($codePostal);
                $adresse->setVille($ville);
                $adresse->setPays($pays);

                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Adresse modifiée avec succès',
                        'adresse' => [
                            'id' => $adresse->getId(),
                            'label' => $adresse->getDisplayLabel(),
                            'nom' => $adresse->getNom(),
                            'ligne1' => $adresse->getLigne1(),
                            'codePostal' => $adresse->getCodePostal(),
                            'ville' => $adresse->getVille(),
                            'pays' => $adresse->getPays()
                        ]
                    ]);
                }

                return $this->redirectToRoute('app_client_edit', ['id' => $adresse->getClient()->getId()]);
            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Veuillez remplir tous les champs obligatoires'
                ], 400);
            }
        }

        return $this->render('adresse/modal_edit.html.twig', [
            'adresse' => $adresse
        ]);
    }
    
    #[Route('/api/create', name: 'app_adresse_api_create', methods: ['POST'])]
    public function apiCreate(Request $request, EntityManagerInterface $entityManager): Response
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Récupérer le client
            $clientId = $data['client'] ?? null;
            if (!$clientId) {
                return $this->json(['success' => false, 'message' => 'Client requis'], 400);
            }
            
            $client = $entityManager->getRepository(Client::class)->find($clientId);
            if (!$client) {
                return $this->json(['success' => false, 'message' => 'Client non trouvé'], 404);
            }
            
            // Créer l'adresse
            $adresse = new Adresse();
            $adresse->setClient($client);
            $adresse->setType($data['type'] ?? 'autre');
            $adresse->setLigne1($data['ligne1'] ?? '');
            $adresse->setLigne2($data['ligne2'] ?? '');
            $adresse->setCodePostal($data['codePostal'] ?? '');
            $adresse->setVille($data['ville'] ?? '');
            $adresse->setPays($data['pays'] ?? 'France');
            
            // Générer le label d'affichage
            $displayLabel = $adresse->getLigne1();
            if ($adresse->getLigne2()) {
                $displayLabel .= ', ' . $adresse->getLigne2();
            }
            $displayLabel .= ', ' . $adresse->getCodePostal() . ' ' . $adresse->getVille();
            $adresse->setDisplayLabel($displayLabel);
            
            $entityManager->persist($adresse);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'address' => [
                    'id' => $adresse->getId(),
                    'label' => $adresse->getDisplayLabel(),
                    'ligne1' => $adresse->getLigne1(),
                    'codePostal' => $adresse->getCodePostal(),
                    'ville' => $adresse->getVille()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'adresse: ' . $e->getMessage()
            ], 500);
        }
    }
}