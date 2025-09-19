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
}