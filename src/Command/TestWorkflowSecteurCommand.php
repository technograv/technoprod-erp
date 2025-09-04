<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Secteur;
use App\Controller\WorkflowController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCommand(
    name: 'app:test-workflow-secteur',
    description: 'Test de l\'endpoint workflow mon-secteur'
)]
class TestWorkflowSecteurCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkflowController $workflowController,
        private RequestStack $requestStack
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("🧪 Test de l'endpoint workflow mon-secteur");

        try {
            // Récupérer l'utilisateur Nicolas Michel
            $user = $this->entityManager->getRepository(User::class)->find(16);
            
            if (!$user) {
                $io->error('Utilisateur ID 16 (Nicolas Michel) non trouvé');
                return Command::FAILURE;
            }
            
            $io->info("👤 Utilisateur trouvé: {$user->getNom()} {$user->getPrenom()}");
            
            // Récupérer ses secteurs
            $secteurs = $this->entityManager->getRepository(Secteur::class)
                ->findBy(['commercial' => $user, 'isActive' => true]);
            
            $io->info("🎯 Secteurs trouvés: " . count($secteurs));
            foreach ($secteurs as $secteur) {
                $io->text("   - {$secteur->getNomSecteur()} (ID: {$secteur->getId()})");
                $io->text("     Attributions: " . count($secteur->getAttributions()));
                
                // Détail des attributions
                foreach ($secteur->getAttributions() as $attribution) {
                    $division = $attribution->getDivisionAdministrative();
                    if ($division) {
                        $io->text("       → {$attribution->getTypeCritere()}: {$attribution->getValeurCritere()} ({$division->getNomCommune()})");
                    }
                }
            }
            
            // Simuler une requête HTTP avec authentication simulée
            $request = Request::create('/workflow/dashboard/mon-secteur', 'GET');
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            $this->requestStack->push($request);
            
            $io->info("🚀 Tentative d'appel direct de la méthode getMonSecteur()...");
            
            // Créer un token simulé et l'injecter
            $tokenStorage = $this->workflowController->getContainer()->get('security.token_storage');
            $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
                $user, 
                'main', 
                $user->getRoles()
            );
            $tokenStorage->setToken($token);
            
            // Appeler la méthode
            $response = $this->workflowController->getMonSecteur();
            $data = json_decode($response->getContent(), true);
            
            $io->info("📊 Response Status: " . $response->getStatusCode());
            
            if ($response->getStatusCode() === 200) {
                $io->success("✅ Endpoint accessible avec succès !");
                $io->text("Données retournées:");
                $io->text("- Success: " . ($data['success'] ? 'true' : 'false'));
                $io->text("- Secteurs count: " . count($data['secteurs'] ?? []));
                $io->text("- Message: " . ($data['message'] ?? 'N/A'));
                
                if (isset($data['secteurs']) && count($data['secteurs']) > 0) {
                    foreach ($data['secteurs'] as $secteur) {
                        $io->text("  → {$secteur['nom']} (lat: {$secteur['latitude']}, lng: {$secteur['longitude']})");
                    }
                }
            } else {
                $io->error("❌ Erreur HTTP " . $response->getStatusCode());
                $io->text("Response: " . $response->getContent());
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
            $io->text("Stack trace:");
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}