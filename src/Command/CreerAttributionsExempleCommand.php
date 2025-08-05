<?php

namespace App\Command;

use App\Entity\Secteur;
use App\Entity\DivisionAdministrative;
use App\Entity\AttributionSecteur;
use App\Entity\TypeSecteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:creer-attributions-exemple',
    description: 'Crée des attributions d\'exemple pour tester le système de secteurs',
)]
class CreerAttributionsExempleCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création d\'attributions d\'exemple');

        // Récupérer les secteurs et divisions
        $secteurs = $this->entityManager->getRepository(Secteur::class)->findAll();
        $divisions = $this->entityManager->getRepository(DivisionAdministrative::class)->findAll();

        if (empty($secteurs)) {
            $io->error('Aucun secteur trouvé');
            return Command::FAILURE;
        }

        if (empty($divisions)) {
            $io->error('Aucune division administrative trouvée');
            return Command::FAILURE;
        }

        $stats = ['created' => 0, 'errors' => 0];

        // Plan d'attribution par secteur
        $planAttributions = [
            'Centre-ville' => [
                // Centres-villes de grandes métropoles
                ['code_postal' => '75001', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '69001', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '13001', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '31000', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '67000', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
            ],
            'Zone industrielle' => [
                // Zones périphériques et industrielles
                ['code_postal' => '92100', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '77100', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '77200', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '06000', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
            ],
            'Secteur Tanguy' => [
                // Régions entières (ouest de la France)
                ['code_postal' => '35000', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '44000', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '76000', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '38000', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
                ['code_postal' => '34000', 'type' => TypeSecteur::TYPE_CODE_POSTAL],
            ]
        ];

        foreach ($secteurs as $secteur) {
            $nomSecteur = $secteur->getNomSecteur();
            
            if (!isset($planAttributions[$nomSecteur])) {
                $io->note("Pas de plan d'attribution pour le secteur: $nomSecteur");
                continue;
            }

            $io->section("Attribution pour le secteur: $nomSecteur");

            foreach ($planAttributions[$nomSecteur] as $attribution) {
                try {
                    // Trouver la division correspondante
                    $division = $this->entityManager->getRepository(DivisionAdministrative::class)
                        ->findOneBy(['codePostal' => $attribution['code_postal']]);

                    if (!$division) {
                        $io->warning("Division non trouvée pour le code postal: " . $attribution['code_postal']);
                        $stats['errors']++;
                        continue;
                    }

                    // Vérifier qu'elle n'est pas déjà attribuée
                    $existante = $this->entityManager->getRepository(AttributionSecteur::class)
                        ->estDejaCouvertePar($division, $attribution['type']);

                    if ($existante) {
                        $io->note("Division déjà attribuée: " . $division->getNomCommune() . " -> " . $existante->getSecteur()->getNomSecteur());
                        continue;
                    }

                    // Créer l'attribution
                    $nouvelleAttribution = AttributionSecteur::creerDepuisDivision(
                        $secteur, 
                        $division, 
                        $attribution['type']
                    );
                    $nouvelleAttribution->setNotes('Attribution d\'exemple créée automatiquement');

                    $this->entityManager->persist($nouvelleAttribution);
                    $stats['created']++;

                    $io->writeln("✓ " . $division->getCodePostal() . " " . $division->getNomCommune() . " → " . $secteur->getNomSecteur());

                } catch (\Exception $e) {
                    $io->error("Erreur pour " . $attribution['code_postal'] . ": " . $e->getMessage());
                    $stats['errors']++;
                }
            }
        }

        // Sauvegarder
        try {
            $this->entityManager->flush();
            $io->success("Attributions créées avec succès !");
        } catch (\Exception $e) {
            $io->error("Erreur lors de la sauvegarde: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Statistiques finales
        $io->section('Résultats');
        $io->table(
            ['Métrique', 'Nombre'],
            [
                ['Attributions créées', $stats['created']],
                ['Erreurs', $stats['errors']]
            ]
        );

        // Afficher le résumé par secteur
        $io->section('Résumé par secteur');
        foreach ($secteurs as $secteur) {
            $nbAttributions = $secteur->getNombreDivisionsCouvertes();
            $resume = $secteur->getResumeTerritoire();
            
            $io->writeln("<info>{$secteur->getNomSecteur()}</info> ({$secteur->getCommercial()->getNom()} {$secteur->getCommercial()->getPrenom()})");
            $io->writeln("  → $nbAttributions attributions: $resume");
            $io->newLine();
        }

        return Command::SUCCESS;
    }
}