<?php

namespace App\Command;

use App\Entity\AttributionSecteur;
use App\Entity\ExclusionSecteur;
use App\Entity\DivisionAdministrative;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-exclusions',
    description: 'Debug geographic exclusion system for a specific attribution',
)]
class DebugExclusionsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('attribution-id', InputArgument::REQUIRED, 'ID of the attribution to test')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $attributionId = $input->getArgument('attribution-id');

        $attribution = $this->entityManager->getRepository(AttributionSecteur::class)->find($attributionId);
        
        if (!$attribution) {
            $io->error("Attribution $attributionId non trouvée");
            return Command::FAILURE;
        }

        $io->title("Debug des exclusions pour l'attribution $attributionId");
        $io->section("Informations attribution");
        $io->writeln("Secteur: " . $attribution->getSecteur()->getNomSecteur());
        $io->writeln("Type: " . $attribution->getTypeCritere());
        $io->writeln("Valeur: " . $attribution->getValeurCritere());
        
        $division = $attribution->getDivisionAdministrative();
        $io->writeln("Division: " . $division->getNomCommune());
        $io->writeln("Code EPCI: " . ($division->getCodeEpci() ?? 'N/A'));
        $io->writeln("EPCI: " . ($division->getNomEpci() ?? 'N/A'));

        $io->section("Recherche des attributions inclusives");
        
        // Test the inclusive attributions search
        $inclusiveAttributions = $this->rechercherAttributionsInclusives($division, $attribution->getSecteur());
        
        $io->writeln("Nombre d'attributions inclusives trouvées: " . count($inclusiveAttributions));
        
        foreach ($inclusiveAttributions as $inclusive) {
            $io->writeln("- Attribution " . $inclusive->getId() . " (" . $inclusive->getTypeCritere() . " " . $inclusive->getValeurCritere() . ") du secteur " . $inclusive->getSecteur()->getNomSecteur());
        }
        
        $io->section("Vérification des exclusions existantes");
        
        $exclusions = $this->entityManager->getRepository(ExclusionSecteur::class)->findBy([
            'divisionAdministrative' => $division
        ]);
        
        $io->writeln("Exclusions existantes: " . count($exclusions));
        foreach ($exclusions as $exclusion) {
            $io->writeln("- Exclusion dans attribution " . $exclusion->getAttributionSecteur()->getId() . ": " . $exclusion->getTypeExclusion() . " " . $exclusion->getValeurExclusion());
        }

        // Manually trigger exclusion creation
        $io->section("Application manuelle des règles d'exclusion");
        
        try {
            $this->appliquerReglesExclusionGeographique($attribution);
            $this->entityManager->flush();
            $io->success("Exclusions appliquées avec succès");
        } catch (\Exception $e) {
            $io->error("Erreur lors de l'application des exclusions: " . $e->getMessage());
            $io->writeln("Stack trace: " . $e->getTraceAsString());
        }

        return Command::SUCCESS;
    }
    
    /**
     * Copy of the rechercherAttributionsInclusives function for testing
     */
    private function rechercherAttributionsInclusives(DivisionAdministrative $division, $secteurCible): array
    {
        // Simple approach: find EPCI attributions that match this commune's EPCI
        if (!$division->getCodeEpci()) {
            return [];
        }
        
        return $this->entityManager->getRepository(AttributionSecteur::class)
            ->createQueryBuilder('a')
            ->where('a.secteur != :secteur')
            ->andWhere('a.typeCritere = :epci')
            ->andWhere('a.valeurCritere = :epciValue')
            ->setParameter('secteur', $secteurCible)
            ->setParameter('epci', 'epci')
            ->setParameter('epciValue', $division->getCodeEpci())
            ->getQuery()
            ->getResult();
    }

    /**
     * Copy of the exclusion function for testing  
     */
    private function appliquerReglesExclusionGeographique(AttributionSecteur $nouvelleAttribution): void
    {
        $division = $nouvelleAttribution->getDivisionAdministrative();
        $secteurCible = $nouvelleAttribution->getSecteur();
        $typeCritere = $nouvelleAttribution->getTypeCritere();
        
        echo "🔄 Application règles exclusion pour $typeCritere dans secteur " . $secteurCible->getNomSecteur() . "\n";
        
        // Définir la hiérarchie géographique (ordre croissant de spécificité)
        $hierarchie = [
            'region' => 1,
            'departement' => 2, 
            'epci' => 3,
            'code_postal' => 4,
            'commune' => 5
        ];
        
        if (!isset($hierarchie[$typeCritere])) {
            echo "Type $typeCritere non géré par les règles d'exclusion\n";
            return; // Type non géré par les règles d'exclusion
        }
        
        $prioriteNouvelle = $hierarchie[$typeCritere];
        echo "Priorité nouvelle attribution: $prioriteNouvelle\n";
        
        // Récupérer toutes les attributions qui pourraient inclure cette division administrative
        $attributionsExistantes = $this->rechercherAttributionsInclusives($division, $secteurCible);
        
        echo "Attributions existantes trouvées: " . count($attributionsExistantes) . "\n";
        
        foreach ($attributionsExistantes as $attributionExistante) {
            $typeExistant = $attributionExistante->getTypeCritere();
            echo "Analyse attribution existante: $typeExistant " . $attributionExistante->getValeurCritere() . "\n";
            
            if (!isset($hierarchie[$typeExistant])) {
                echo "Type existant $typeExistant non géré\n";
                continue; // Type non géré
            }
            
            $prioriteExistante = $hierarchie[$typeExistant];
            echo "Priorité existante: $prioriteExistante vs nouvelle: $prioriteNouvelle\n";
            
            // Si la nouvelle attribution est plus spécifique (priorité plus élevée)
            if ($prioriteNouvelle > $prioriteExistante) {
                echo "Nouvelle attribution plus spécifique, création exclusion\n";
                
                // Vérifier si l'exclusion n'existe pas déjà
                $exclusionExistante = $this->entityManager->getRepository(ExclusionSecteur::class)
                    ->findOneBy([
                        'attributionSecteur' => $attributionExistante,
                        'divisionAdministrative' => $division
                    ]);
                
                if (!$exclusionExistante) {
                    // Créer une exclusion
                    $exclusion = new ExclusionSecteur();
                    $exclusion->setAttributionSecteur($attributionExistante);
                    $exclusion->setDivisionAdministrative($division);
                    $exclusion->setTypeExclusion($typeCritere);
                    $exclusion->setValeurExclusion($nouvelleAttribution->getValeurCritere());
                    $exclusion->setMotif("Zone plus spécifique assignée au secteur '{$secteurCible->getNomSecteur()}'");
                    
                    $this->entityManager->persist($exclusion);
                    
                    echo "✅ Exclusion créée pour attribution " . $attributionExistante->getId() . "\n";
                } else {
                    echo "Exclusion déjà existante\n";
                }
            } else {
                echo "Attribution existante plus spécifique, pas d'exclusion\n";
            }
        }
    }
}