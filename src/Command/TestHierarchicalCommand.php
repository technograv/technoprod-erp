<?php

namespace App\Command;

use App\Entity\Secteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-hierarchical',
    description: 'Test hierarchical sector assignment system'
)]
class TestHierarchicalCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔍 Testing hierarchical system...');
        
        // Get sectors
        $secteurs = $this->entityManager->getRepository(Secteur::class)
            ->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();

        $output->writeln('📊 Found ' . count($secteurs) . ' active sectors:');
        foreach ($secteurs as $secteur) {
            $output->writeln(sprintf('  - %s (ID: %d)', $secteur->getNomSecteur(), $secteur->getId()));
        }

        // Test commune assignment logic
        $communeVsSecteur = [];
        $attributionsParType = [];
        
        // Collect all attributions by type
        foreach ($secteurs as $secteur) {
            foreach ($secteur->getAttributions() as $attribution) {
                $type = $attribution->getTypeCritere();
                if (!isset($attributionsParType[$type])) {
                    $attributionsParType[$type] = [];
                }
                $attributionsParType[$type][] = [
                    'secteur' => $secteur,
                    'attribution' => $attribution
                ];
            }
        }
        
        $output->writeln('');
        $output->writeln('📋 Attributions by type:');
        foreach ($attributionsParType as $type => $attributions) {
            $output->writeln("  $type: " . count($attributions) . " attributions");
        }
        
        // Process hierarchically
        $ordreTraitement = ['commune', 'code_postal', 'epci', 'departement', 'region'];
        
        foreach ($ordreTraitement as $typeActuel) {
            if (!isset($attributionsParType[$typeActuel])) continue;
            
            $output->writeln('');
            $output->writeln("🔄 Processing $typeActuel (" . count($attributionsParType[$typeActuel]) . " attributions):");
            
            foreach ($attributionsParType[$typeActuel] as $data) {
                $secteur = $data['secteur'];
                $attribution = $data['attribution'];
                $division = $attribution->getDivisionAdministrative();
                
                if (!$division) continue;
                
                // Get communes for this type
                $communesDuType = $this->getCommunesPourType($typeActuel, $division);
                
                $nouvellesCommunes = 0;
                foreach ($communesDuType as $commune) {
                    $codeInsee = $commune['codeInseeCommune'];
                    if (!isset($communeVsSecteur[$codeInsee])) {
                        $communeVsSecteur[$codeInsee] = $secteur->getId();
                        $nouvellesCommunes++;
                    }
                }
                
                $output->writeln(sprintf('  📍 %s "%s" → %s: %d nouvelles communes', 
                    $typeActuel, 
                    $attribution->getValeurCritere(), 
                    $secteur->getNomSecteur(), 
                    $nouvellesCommunes
                ));
            }
        }
        
        // Check Boutx specifically
        $output->writeln('');
        $output->writeln('🎯 Boutx (31085) status:');
        if (isset($communeVsSecteur['31085'])) {
            $secteurId = $communeVsSecteur['31085'];
            $secteur = $this->entityManager->find(Secteur::class, $secteurId);
            $output->writeln("  ✅ Assigned to: " . $secteur->getNomSecteur() . " (ID: $secteurId)");
        } else {
            $output->writeln('  ❌ NOT ASSIGNED');
        }
        
        return Command::SUCCESS;
    }
    
    private function getCommunesPourType(string $type, $division): array
    {
        switch ($type) {
            case 'commune':
                return [[
                    'codeInseeCommune' => $division->getCodeInseeCommune(),
                    'nomCommune' => $division->getNomCommune()
                ]];
                
            case 'code_postal':
                $communes = $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codePostal = :codePostal 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codePostal', $division->getCodePostal())
                ->getResult();
                return $communes;
                
            default:
                return [];
        }
    }
}