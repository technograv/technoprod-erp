<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-user',
    description: 'Create a test user with password for development',
)]
class CreateTestUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Créer ou mettre à jour l'utilisateur test
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);

        if (!$user) {
            $user = new User();
            $user->setEmail('test@test.com');
            $user->setNom('Test');
            $user->setPrenom('User');
            $user->setRoles(['ROLE_ADMIN', 'ROLE_COMMERCIAL']);
        }

        // Définir le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'test123');
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        $io->success('Test user created successfully!');
        $io->info('Email: test@test.com');
        $io->info('Password: test123');

        return Command::SUCCESS;
    }
}
