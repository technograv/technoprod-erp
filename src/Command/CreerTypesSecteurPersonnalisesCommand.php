<?php

namespace App\Command;

use App\Entity\TypeSecteur;
use App\Entity\Secteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:creer-types-secteur-personnalises',
    description: 'Crée des types de secteur personnalisés pour tester différents scénarios',
)]
class CreerTypesSecteurPersonnalisesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création de types de secteur personnalisés');

        $typesPersonnalises = [
            [
                'code' => 'METRO',
                'nom' => 'Métropoles françaises',
                'type' => TypeSecteur::TYPE_COMMUNE,
                'description' => 'Secteur basé sur les grandes métropoles françaises',
                'ordre' => 10
            ],
            [
                'code' => 'REG_OUEST',
                'nom' => 'Régions Ouest',
                'type' => TypeSecteur::TYPE_REGION,
                'description' => 'Secteur couvrant les régions de l\'ouest de la France',
                'ordre' => 11
            ],
            [
                'code' => 'DEPT_IDF',
                'nom' => 'Départements Île-de-France',
                'type' => TypeSecteur::TYPE_DEPARTEMENT,
                'description' => 'Secteur par département en Île-de-France',
                'ordre' => 12
            ],
            [
                'code' => 'TECH_PARK',
                'nom' => 'Parcs technologiques',
                'type' => TypeSecteur::TYPE_EPCI,
                'description' => 'Secteur spécialisé dans les zones technologiques',
                'ordre' => 13
            ]
        ];

        $stats = ['created' => 0, 'skipped' => 0];

        foreach ($typesPersonnalises as $typeData) {
            // Vérifier si existe déjà
            $existant = $this->entityManager->getRepository(TypeSecteur::class)
                ->findOneBy(['code' => $typeData['code']]);

            if ($existant) {
                $io->note("Type '{$typeData['code']}' déjà existant, ignoré");
                $stats['skipped']++;
                continue;
            }

            $typeSecteur = new TypeSecteur();
            $typeSecteur->setCode($typeData['code'])
                       ->setNom($typeData['nom'])
                       ->setType($typeData['type'])
                       ->setDescription($typeData['description'])
                       ->setOrdre($typeData['ordre'])
                       ->setActif(true);

            $this->entityManager->persist($typeSecteur);
            $stats['created']++;

            $io->writeln("✓ {$typeData['code']} - {$typeData['nom']}");
        }

        try {
            $this->entityManager->flush();
            $io->success('Types de secteur personnalisés créés !');
        } catch (\Exception $e) {
            $io->error('Erreur lors de la sauvegarde: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->table(
            ['Métrique', 'Nombre'],
            [
                ['Types créés', $stats['created']],
                ['Types ignorés (existants)', $stats['skipped']]
            ]
        );

        // Maintenant assignons ces types aux secteurs existants
        $io->section('Assignment des types aux secteurs existants');
        
        $secteurs = $this->entityManager->getRepository(Secteur::class)->findAll();
        $typesDisponibles = $this->entityManager->getRepository(TypeSecteur::class)->findAllOrdered();

        $assignmentPlan = [
            'Centre-ville' => 'METRO',
            'Zone industrielle' => 'DEPT_IDF', 
            'Secteur Tanguy' => 'REG_OUEST'
        ];

        foreach ($secteurs as $secteur) {
            $nomSecteur = $secteur->getNomSecteur();
            
            if (isset($assignmentPlan[$nomSecteur])) {
                $codeType = $assignmentPlan[$nomSecteur];
                $typeSecteur = $this->entityManager->getRepository(TypeSecteur::class)
                    ->findOneBy(['code' => $codeType]);

                if ($typeSecteur) {
                    $secteur->setTypeSecteur($typeSecteur);
                    $io->writeln("✓ $nomSecteur → {$typeSecteur->getNom()}");
                }
            }
        }

        try {
            $this->entityManager->flush();
            $io->success('Types assignés aux secteurs !');
        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'assignment: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Affichage final des types disponibles
        $io->section('Types de secteur disponibles');
        $tousLesTypes = $this->entityManager->getRepository(TypeSecteur::class)->findAllActifs();
        
        foreach ($tousLesTypes as $type) {
            $nbSecteurs = $this->entityManager->getRepository(TypeSecteur::class)->countSecteursUtilisant($type);
            $io->writeln("<info>{$type->getCode()}</info> - {$type->getNom()} ({$type->getTypeLibelle()}) - <comment>$nbSecteurs secteur(s)</comment>");
        }

        return Command::SUCCESS;
    }
}