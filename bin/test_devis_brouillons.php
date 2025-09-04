#!/usr/bin/env php
<?php

use App\Entity\User;
use App\Entity\Devis;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new \App\Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $kernel->boot();
    
    $container = $kernel->getContainer();
    $entityManager = $container->get(EntityManagerInterface::class);
    
    echo "=== Test récupération devis brouillons ===\n\n";
    
    // Récupérer l'utilisateur Nicolas Michel (ID 16)
    $user = $entityManager->getRepository(User::class)->find(16);
    
    if (!$user) {
        echo "❌ Utilisateur ID 16 non trouvé\n";
        return;
    }
    
    echo "👤 Utilisateur trouvé : {$user->getPrenom()} {$user->getNom()}\n";
    echo "📧 Email : {$user->getEmail()}\n\n";
    
    // Requête pour récupérer les devis brouillons
    $devisBrouillons = $entityManager->getRepository(Devis::class)
        ->createQueryBuilder('d')
        ->leftJoin('d.client', 'c')
        ->where('d.statut = :statut')
        ->andWhere('d.commercial = :commercial')
        ->setParameter('statut', 'brouillon')
        ->setParameter('commercial', $user)
        ->orderBy('d.updatedAt', 'DESC')
        ->getQuery()
        ->getResult();
    
    echo "📋 Nombre de devis brouillons trouvés : " . count($devisBrouillons) . "\n\n";
    
    if (count($devisBrouillons) > 0) {
        echo "Liste des devis brouillons :\n";
        echo "─────────────────────────────────────────────────────────────\n";
        
        foreach ($devisBrouillons as $devis) {
            $clientNom = $devis->getClient() ? $devis->getClient()->getNomEntreprise() : 'N/A';
            $dateCreation = $devis->getDateCreation() ? $devis->getDateCreation()->format('d/m/Y') : 'N/A';
            $totalTtc = $devis->getTotalTtc() ?: '0.00';
            
            echo "📄 {$devis->getNumeroDevis()}\n";
            echo "   Client: {$clientNom}\n";
            echo "   Date création: {$dateCreation}\n";
            echo "   Total TTC: {$totalTtc} €\n";
            echo "   Statut: {$devis->getStatut()}\n\n";
        }
        
        echo "✅ Test réussi ! La requête fonctionne correctement.\n";
    } else {
        echo "❌ Aucun devis brouillon trouvé pour cet utilisateur.\n";
        
        // Vérification supplémentaire
        echo "\nVérification supplémentaire...\n";
        $allBrouillons = $entityManager->getRepository(Devis::class)
            ->findBy(['statut' => 'brouillon']);
            
        echo "Total devis brouillons dans la base : " . count($allBrouillons) . "\n";
        
        if (count($allBrouillons) > 0) {
            echo "IDs des commerciaux pour ces devis :\n";
            foreach ($allBrouillons as $devis) {
                $commercialId = $devis->getCommercial() ? $devis->getCommercial()->getId() : 'NULL';
                echo "- Devis {$devis->getNumeroDevis()} : Commercial ID {$commercialId}\n";
            }
        }
    }
    
    return Command::SUCCESS;
};