<?php

namespace App\Command;

use App\Entity\AttributionSecteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fix:lannemezan-attribution',
    description: 'Corriger l\'attribution erronée du secteur Plateau de Lannemezan'
)]
class CorrigerAttributionLannemezanCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('CORRECTION ATTRIBUTION ERRONÉE SECTEUR PLATEAU DE LANNEMEZAN');
        
        // 1. Identifier l'attribution erronée (ID 123, code postal 34150)
        $attributionErronee = $this->entityManager->getRepository(AttributionSecteur::class)->find(123);
        
        if (!$attributionErronee) {
            $io->error('Attribution erronée (ID 123) non trouvée.');
            return Command::FAILURE;
        }
        
        $division = $attributionErronee->getDivisionAdministrative();
        
        $io->section('Attribution erronée identifiée');
        $io->definitionList([
            'ID Attribution' => $attributionErronee->getId(),
            'Secteur' => $attributionErronee->getSecteur()->getNomSecteur(),
            'Type critère' => $attributionErronee->getTypeCritere(),
            'Valeur critère' => $attributionErronee->getValeurCritere(),
            'Division - Commune' => $division ? $division->getNomCommune() : 'N/A',
            'Division - Code postal' => $division ? $division->getCodePostal() : 'N/A',
            'Division - Département' => $division ? $division->getNomDepartement() : 'N/A',
            'Division - Coordonnées' => $division ? 
                $division->getLatitude() . ', ' . $division->getLongitude() : 'N/A'
        ]);
        
        // 2. Confirmer que c'est bien l'attribution erronée (34150 dans l'Hérault)
        if ($division && $division->getCodePostal() === '34150' && $division->getNomDepartement() === 'Hérault') {
            $io->error('ATTRIBUTION ERRONÉE CONFIRMÉE: Code postal 34150 dans l\'Hérault au lieu du Plateau de Lannemezan !');
            
            $io->info('Cette attribution cause le mauvais positionnement de la puce du secteur car:');
            $io->listing([
                'Le code postal 34150 correspond à la région de Montpellier (Hérault)',
                'Les coordonnées (43.6594, 3.6472) sont à ~400km de Lannemezan',
                'L\'algorithme de positionnement mélange ces coordonnées avec celles des Hautes-Pyrénées',
                'Résultat: la puce apparaît au milieu entre Lannemezan et Montpellier'
            ]);
            
            if ($io->confirm('Voulez-vous supprimer cette attribution erronée ?', true)) {
                
                // 3. Supprimer l'attribution erronée
                $this->entityManager->remove($attributionErronee);
                $this->entityManager->flush();
                
                $io->success('Attribution erronée supprimée avec succès !');
                
                // 4. Vérifier les attributions restantes
                $io->section('Attributions restantes du secteur');
                
                $secteur = $attributionErronee->getSecteur();
                $attributionsRestantes = $this->entityManager->getRepository(AttributionSecteur::class)
                    ->findBy(['secteur' => $secteur]);
                
                foreach ($attributionsRestantes as $attribution) {
                    $div = $attribution->getDivisionAdministrative();
                    $io->info(sprintf(
                        'Attribution #%d: %s %s → %s (%s) - Coords: %s, %s',
                        $attribution->getId(),
                        $attribution->getTypeCritere(),
                        $attribution->getValeurCritere(),
                        $div ? $div->getNomCommune() : 'N/A',
                        $div ? $div->getNomDepartement() : 'N/A',
                        $div ? $div->getLatitude() : 'N/A',
                        $div ? $div->getLongitude() : 'N/A'
                    ));
                }
                
                $io->note('Le secteur "Plateau de Lannemezan" devrait maintenant être correctement positionné dans les Hautes-Pyrénées/Haute-Garonne.');
                
            } else {
                $io->info('Suppression annulée.');
                return Command::SUCCESS;
            }
            
        } else {
            $io->warning('L\'attribution trouvée ne correspond pas à l\'erreur attendue.');
            return Command::FAILURE;
        }
        
        // 5. Analyser l'impact de la correction
        $io->section('Analyse de l\'impact de la correction');
        
        $io->info('Après suppression de l\'attribution erronée, le calcul de positionnement utilisera:');
        $io->listing([
            'Attribution commune: Labroquère (31510) - Haute-Garonne',
            'Attribution code_postal: 31160 - Haute-Garonne', 
            'Attribution EPCI: Plateau de Lannemezan (200070787) - Hautes-Pyrénées'
        ]);
        
        $io->success('La puce devrait maintenant être correctement positionnée dans la région du vrai Plateau de Lannemezan.');
        
        $io->info('Pour vérifier la correction:');
        $io->listing([
            'Recharger la carte des secteurs dans l\'interface admin',
            'Vérifier que la puce "Plateau de Lannemezan" est dans les Hautes-Pyrénées',
            'La puce ne devrait plus apparaître "au milieu" d\'un autre secteur'
        ]);
        
        return Command::SUCCESS;
    }
}