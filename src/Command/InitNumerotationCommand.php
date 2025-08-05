<?php

namespace App\Command;

use App\Service\DocumentNumerotationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-numerotation',
    description: 'Initialise les numérotations de documents par défaut',
)]
class InitNumerotationCommand extends Command
{
    private DocumentNumerotationService $numerotationService;

    public function __construct(DocumentNumerotationService $numerotationService)
    {
        $this->numerotationService = $numerotationService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Initialisation des numérotations de documents');

        try {
            $this->numerotationService->initialiserDocumentsParDefaut();
            
            $io->success('Numérotations initialisées avec succès !');
            
            // Afficher les numérotations créées
            $numerotations = $this->numerotationService->getToutesLesNumerotations();
            
            $io->table(
                ['Préfixe', 'Libellé', 'Prochain numéro'],
                array_map(function($numerotation) {
                    return [
                        $numerotation->getPrefixe(),
                        $numerotation->getLibelle(),
                        $numerotation->getProchainNumero()
                    ];
                }, $numerotations)
            );
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'initialisation : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}