<?php

namespace App\Command;

use App\Entity\Societe;
use App\Entity\User;
use App\Entity\UserSocieteRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-multi-tenant',
    description: 'Initialise le système multi-tenant avec la société mère DecorPub et les sociétés filles de test'
)]
class InitMultiTenantCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🏢 Initialisation du système multi-tenant TechnoProd');

        try {
            // 1. Créer la société mère "Groupe DecorPub"
            $io->section('1. Création de la société mère');
            $societeMere = $this->createSocieteMere();
            $io->success("✅ Société mère '{$societeMere->getNom()}' créée avec succès");

            // 2. Créer les sociétés filles de test
            $io->section('2. Création des sociétés filles de test');
            $societesFilles = $this->createSocietesFilles($societeMere);
            foreach ($societesFilles as $fille) {
                $io->writeln("✅ Société fille '{$fille->getNom()}' créée");
            }

            // 3. Configurer le super-admin
            $io->section('3. Configuration du super-admin');
            $superAdmin = $this->configureSuperAdmin($societeMere);
            if ($superAdmin) {
                $io->success("✅ Super-admin '{$superAdmin->getEmail()}' configuré avec accès à toutes les sociétés");
            } else {
                $io->warning("⚠️ Utilisateur nicolas.michel@decorpub.fr non trouvé en base");
            }

            // 4. Statistiques finales
            $io->section('4. Récapitulatif');
            $this->displayStatistics($io);

            $io->success('🎉 Initialisation multi-tenant terminée avec succès !');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("❌ Erreur lors de l'initialisation : " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createSocieteMere(): Societe
    {
        // Vérifier si la société existe déjà
        $existingSociete = $this->entityManager->getRepository(Societe::class)
            ->findByNomIgnoreCase('Groupe DecorPub');

        if ($existingSociete) {
            return $existingSociete;
        }

        $societe = new Societe();
        $societe->setNom('Groupe DecorPub')
                ->setType('mere')
                ->setSiret('12345678901234')
                ->setNumeroTva('FR12345678901')
                ->setAdresse('123 Avenue de la République')
                ->setCodePostal('31000')
                ->setVille('Toulouse')
                ->setPays('France')
                ->setTelephone('05.61.00.00.00')
                ->setEmail('contact@decorpub.fr')
                ->setSiteWeb('https://www.decorpub.fr')
                ->setCouleurPrimaire('#dc3545')
                ->setCouleurSecondaire('#6c757d')
                ->setParametreCustom('template_theme', 'default')
                ->setParametreCustom('invoice_prefix', 'FACT-DP-')
                ->setActive(true);

        $this->entityManager->persist($societe);
        $this->entityManager->flush();

        return $societe;
    }

    private function createSocietesFilles(Societe $societeMere): array
    {
        $fillesToCreate = [
            [
                'nom' => 'TechnoGrav',
                'couleur_primaire' => '#007bff',
                'couleur_secondaire' => '#28a745',
                'template_theme' => 'blue',
                'invoice_prefix' => 'FACT-TG-'
            ],
            [
                'nom' => 'TechnoPrint',
                'couleur_primaire' => '#28a745',
                'couleur_secondaire' => '#17a2b8',
                'template_theme' => 'green',
                'invoice_prefix' => 'FACT-TP-'
            ],
            [
                'nom' => 'TechnoBuro',
                'couleur_primaire' => '#ffc107',
                'couleur_secondaire' => '#fd7e14',
                'template_theme' => 'yellow',
                'invoice_prefix' => 'FACT-TB-'
            ]
        ];

        $societesFilles = [];

        foreach ($fillesToCreate as $filleData) {
            // Vérifier si existe déjà
            $existing = $this->entityManager->getRepository(Societe::class)
                ->findByNomIgnoreCase($filleData['nom']);

            if ($existing) {
                $societesFilles[] = $existing;
                continue;
            }

            $fille = new Societe();
            $fille->setNom($filleData['nom'])
                  ->setType('fille')
                  ->setSocieteParent($societeMere)
                  ->setAdresse($societeMere->getAdresse()) // Hérite de la mère
                  ->setCodePostal($societeMere->getCodePostal())
                  ->setVille($societeMere->getVille())
                  ->setPays($societeMere->getPays())
                  ->setTelephone($societeMere->getTelephone())
                  ->setEmail(strtolower($filleData['nom']) . '@decorpub.fr')
                  ->setSiteWeb('https://' . strtolower($filleData['nom']) . '.decorpub.fr')
                  ->setCouleurPrimaire($filleData['couleur_primaire'])
                  ->setCouleurSecondaire($filleData['couleur_secondaire'])
                  ->setParametreCustom('template_theme', $filleData['template_theme'])
                  ->setParametreCustom('invoice_prefix', $filleData['invoice_prefix'])
                  ->setActive(true);

            $this->entityManager->persist($fille);
            $societesFilles[] = $fille;
        }

        $this->entityManager->flush();
        return $societesFilles;
    }

    private function configureSuperAdmin(Societe $societeMere): ?User
    {
        $superAdmin = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'nicolas.michel@decorpub.fr']);

        if (!$superAdmin) {
            return null;
        }

        // Récupérer toutes les sociétés (mère + filles)
        $toutes_societes = $this->entityManager->getRepository(Societe::class)->findAll();

        foreach ($toutes_societes as $societe) {
            // Vérifier si le rôle existe déjà
            $existingRole = $this->entityManager->getRepository(UserSocieteRole::class)
                ->findUserRoleInSociete($superAdmin, $societe);

            if (!$existingRole) {
                $role = new UserSocieteRole();
                $role->setUser($superAdmin)
                     ->setSociete($societe)
                     ->setRole(UserSocieteRole::ROLE_ADMIN)
                     ->setNotes('Super-admin - Accès total configuré automatiquement')
                     ->setActive(true);

                $this->entityManager->persist($role);
            }
        }

        $this->entityManager->flush();
        return $superAdmin;
    }

    private function displayStatistics(SymfonyStyle $io): void
    {
        // Compter les sociétés
        $societeRepo = $this->entityManager->getRepository(Societe::class);
        $counts = $societeRepo->countByType();

        $io->table(
            ['Type', 'Nombre'],
            [
                ['Sociétés mères', $counts['mere']],
                ['Sociétés filles', $counts['fille']],
                ['TOTAL', $counts['mere'] + $counts['fille']]
            ]
        );

        // Lister toutes les sociétés
        $societes = $societeRepo->findAll();
        $rows = [];
        foreach ($societes as $societe) {
            $rows[] = [
                $societe->getId(),
                $societe->getNom(),
                $societe->getType(),
                $societe->getSocieteParent() ? $societe->getSocieteParent()->getNom() : '-',
                $societe->isActive() ? '✅' : '❌'
            ];
        }

        $io->table(
            ['ID', 'Nom', 'Type', 'Société parent', 'Actif'],
            $rows
        );

        // Statistiques des rôles
        $roleStats = $this->entityManager->getRepository(UserSocieteRole::class)
            ->getRoleStatistics();

        if (!empty($roleStats)) {
            $io->writeln('');
            $io->writeln('<info>📊 Statistiques des rôles :</info>');
            foreach ($roleStats as $stat) {
                $io->writeln("  • {$stat['role']}: {$stat['user_count']} utilisateur(s) dans {$stat['societe_count']} société(s)");
            }
        }
    }
}