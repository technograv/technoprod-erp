<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Client;
use App\Entity\Adresse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact/modal/new/{clientId}', name: 'app_contact_modal_new', methods: ['GET', 'POST'])]
    public function modalNew(int $clientId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = $entityManager->getRepository(Client::class)->find($clientId);
        if (!$client) {
            throw $this->createNotFoundException('Client non trouvé');
        }

        $contact = new Contact();
        $contact->setClient($client);

        if ($request->isMethod('POST')) {
            // Traitement des données du formulaire POST
            $prenom = $request->request->get('prenom');
            $nom = $request->request->get('nom');
            $email = $request->request->get('email');
            $telephone = $request->request->get('telephone');
            $fonction = $request->request->get('fonction');
            $mobile = $request->request->get('mobile');
            $adresseId = $request->request->get('adresse');
            
            // Checkboxes
            $isFacturationDefault = $request->request->get('isFacturationDefault') === 'on';
            $isLivraisonDefault = $request->request->get('isLivraisonDefault') === 'on';

            if ($nom) {
                $contact->setPrenom($prenom);
                $contact->setNom($nom);
                $contact->setEmail($email);
                $contact->setTelephone($telephone);
                $contact->setFonction($fonction);
                $contact->setTelephoneMobile($mobile);
                $contact->setIsFacturationDefault($isFacturationDefault);
                $contact->setIsLivraisonDefault($isLivraisonDefault);
                
                // Gérer l'adresse si sélectionnée
                if ($adresseId) {
                    $adresse = $entityManager->getRepository(Adresse::class)->find($adresseId);
                    if ($adresse && $adresse->getClient() === $client) {
                        $contact->setAdresse($adresse);
                    }
                } else {
                    $contact->setAdresse(null);
                }

                // Si c'est le contact de facturation par défaut, désactiver les autres
                if ($isFacturationDefault) {
                    foreach ($client->getContacts() as $existingContact) {
                        if ($existingContact !== $contact) {
                            $existingContact->setIsFacturationDefault(false);
                        }
                    }
                }

                // Si c'est le contact de livraison par défaut, désactiver les autres
                if ($isLivraisonDefault) {
                    foreach ($client->getContacts() as $existingContact) {
                        if ($existingContact !== $contact) {
                            $existingContact->setIsLivraisonDefault(false);
                        }
                    }
                }

                $entityManager->persist($contact);
                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Contact créé avec succès',
                        'contact' => [
                            'id' => $contact->getId(),
                            'clientId' => $contact->getClient()->getId(), // ✅ AJOUT du clientId manquant
                            'label' => ($contact->getPrenom() ? $contact->getPrenom() . ' ' : '') . $contact->getNom(),
                            'prenom' => $contact->getPrenom(),
                            'nom' => $contact->getNom(),
                            'email' => $contact->getEmail(),
                            'telephone' => $contact->getTelephone(),
                            'mobile' => $contact->getTelephoneMobile(),
                            'fonction' => $contact->getFonction(),
                            'isFacturationDefault' => $contact->isFacturationDefault(),
                            'isLivraisonDefault' => $contact->isLivraisonDefault(),
                            'adresse_id' => $contact->getAdresse() ? $contact->getAdresse()->getId() : null
                        ]
                    ]);
                }

                return $this->redirectToRoute('app_client_edit', ['id' => $clientId]);
            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le nom est obligatoire'
                ], 400);
            }
        }

        // Récupérer les adresses du client pour le menu déroulant
        $adresses = $client->getAdresses();

        return $this->render('contact/modal_new.html.twig', [
            'client' => $client,
            'contact' => $contact,
            'adresses' => $adresses
        ]);
    }

    #[Route('/contact/modal/edit/{id}', name: 'app_contact_modal_edit', methods: ['GET', 'POST'])]
    public function modalEdit(Contact $contact, Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = $contact->getClient();

        if ($request->isMethod('POST')) {
            // Traitement des données du formulaire POST
            $prenom = $request->request->get('prenom');
            $nom = $request->request->get('nom');
            $email = $request->request->get('email');
            $telephone = $request->request->get('telephone');
            $fonction = $request->request->get('fonction');
            $mobile = $request->request->get('mobile');
            $adresseId = $request->request->get('adresse');
            
            // Checkboxes
            $isFacturationDefault = $request->request->get('isFacturationDefault') === 'on';
            $isLivraisonDefault = $request->request->get('isLivraisonDefault') === 'on';

            if ($nom) {
                $contact->setPrenom($prenom);
                $contact->setNom($nom);
                $contact->setEmail($email);
                $contact->setTelephone($telephone);
                $contact->setFonction($fonction);
                $contact->setTelephoneMobile($mobile);
                $contact->setIsFacturationDefault($isFacturationDefault);
                $contact->setIsLivraisonDefault($isLivraisonDefault);
                
                // Gérer l'adresse si sélectionnée
                if ($adresseId) {
                    $adresse = $entityManager->getRepository(Adresse::class)->find($adresseId);
                    if ($adresse && $adresse->getClient() === $client) {
                        $contact->setAdresse($adresse);
                    }
                } else {
                    $contact->setAdresse(null);
                }

                // Si c'est le contact de facturation par défaut, désactiver les autres
                if ($isFacturationDefault) {
                    foreach ($client->getContacts() as $existingContact) {
                        if ($existingContact !== $contact && $existingContact->getId() !== $contact->getId()) {
                            $existingContact->setIsFacturationDefault(false);
                        }
                    }
                }

                // Si c'est le contact de livraison par défaut, désactiver les autres
                if ($isLivraisonDefault) {
                    foreach ($client->getContacts() as $existingContact) {
                        if ($existingContact !== $contact && $existingContact->getId() !== $contact->getId()) {
                            $existingContact->setIsLivraisonDefault(false);
                        }
                    }
                }

                $entityManager->flush();

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Contact modifié avec succès',
                        'contact' => [
                            'id' => $contact->getId(),
                            'clientId' => $contact->getClient()->getId(), // ✅ AJOUT du clientId manquant
                            'label' => ($contact->getPrenom() ? $contact->getPrenom() . ' ' : '') . $contact->getNom(),
                            'prenom' => $contact->getPrenom(),
                            'nom' => $contact->getNom(),
                            'email' => $contact->getEmail(),
                            'telephone' => $contact->getTelephone(),
                            'mobile' => $contact->getTelephoneMobile(),
                            'fonction' => $contact->getFonction(),
                            'isFacturationDefault' => $contact->isFacturationDefault(),
                            'isLivraisonDefault' => $contact->isLivraisonDefault(),
                            'adresse_id' => $contact->getAdresse() ? $contact->getAdresse()->getId() : null
                        ]
                    ]);
                }

                return $this->redirectToRoute('app_client_edit', ['id' => $client->getId()]);
            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le nom est obligatoire'
                ], 400);
            }
        }

        // Récupérer les adresses du client pour le menu déroulant
        $adresses = $client->getAdresses();

        return $this->render('contact/modal_edit.html.twig', [
            'contact' => $contact,
            'client' => $client,
            'adresses' => $adresses
        ]);
    }

    #[Route('/contact/{id}/default-address', name: 'app_contact_default_address', methods: ['GET'])]
    public function getDefaultAddress(Contact $contact): Response
    {
        $address = $contact->getAdresse();
        
        if ($address) {
            // Créer un label pour l'adresse
            $label = sprintf('%s - %s %s',
                $address->getLigne1() ?? '',
                $address->getCodePostal() ?? '',
                $address->getVille() ?? ''
            );
            
            return $this->json([
                'success' => true,
                'address' => [
                    'id' => $address->getId(),
                    'label' => trim($label),
                    'ligne1' => $address->getLigne1(),
                    'codePostal' => $address->getCodePostal(),
                    'ville' => $address->getVille()
                ]
            ]);
        }
        
        return $this->json([
            'success' => false,
            'message' => 'Aucune adresse associée à ce contact'
        ]);
    }
}