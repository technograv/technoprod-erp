<?php

namespace App\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestDebugController extends AbstractController
{
    #[Route('/test/debug/devis', name: 'app_test_debug_devis')]
    public function debugDevis(EntityManagerInterface $entityManager): Response
    {
        // Récupérer quelques clients pour les tests
        $clients = $entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        return $this->render('test/debug_devis.html.twig', [
            'clients' => $clients
        ]);
    }
}