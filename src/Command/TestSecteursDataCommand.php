<?php

namespace App\Command;

use App\Entity\Secteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-secteurs-data',
    description: 'Test les données des secteurs avec exclusions',
)]
class TestSecteursDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('secteur-id', InputArgument::OPTIONAL, 'ID du secteur à tester (5 = Volvestre par défaut)', 5)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $secteurId = $input->getArgument('secteur-id');

        $secteur = $this->entityManager->getRepository(Secteur::class)->find($secteurId);
        
        if (!$secteur) {
            $io->error("Secteur $secteurId non trouvé");
            return Command::FAILURE;
        }

        $io->title("Test données secteur: " . $secteur->getNomSecteur());

        foreach ($secteur->getAttributions() as $attribution) {
            $io->section("Attribution {$attribution->getId()}: {$attribution->getTypeCritere()} {$attribution->getValeurCritere()}");
            
            // Récupérer les exclusions comme le fait le contrôleur
            $exclusions = $this->entityManager->getRepository(\App\Entity\ExclusionSecteur::class)
                ->createQueryBuilder('es')
                ->join('es.divisionAdministrative', 'd')
                ->where('es.attributionSecteur = :attribution')
                ->setParameter('attribution', $attribution)
                ->getQuery()
                ->getResult();
            
            $io->writeln("Nombre d'exclusions: " . count($exclusions));
            
            foreach ($exclusions as $exclusion) {
                $div = $exclusion->getDivisionAdministrative();
                $io->writeln("- Exclusion: {$exclusion->getTypeExclusion()} {$exclusion->getValeurExclusion()} - Commune: {$div->getNomCommune()} ({$div->getCodeInseeCommune()})");
            }
        }

        return Command::SUCCESS;
    }
}