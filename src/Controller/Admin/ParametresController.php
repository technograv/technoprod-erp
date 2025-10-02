<?php

namespace App\Controller\Admin;

use App\Entity\AlerteType;
use App\Entity\Societe;
use App\Entity\User;
use App\Service\Admin\ConfigurationAdminService;
use App\Service\AlerteManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/parametres')]
#[IsGranted('ROLE_ADMIN')]
class ParametresController extends AbstractController
{
    public function __construct(
        private ConfigurationAdminService $configurationService,
        private EntityManagerInterface $entityManager,
        private AlerteManager $alerteManager
    ) {
    }

    #[Route('/', name: 'app_admin_parametres', methods: ['GET'])]
    public function parametres(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $currentSociete = $user->getSocietePrincipale();
        $isSocieteMere = $currentSociete && $currentSociete->isMere();

        // Récupérer les données nécessaires pour le template types d'alertes
        $typesAlerte = $this->entityManager
            ->getRepository(AlerteType::class)
            ->findBy([], ['ordre' => 'ASC', 'nom' => 'ASC']);

        $societes = $this->entityManager
            ->getRepository(Societe::class)
            ->findBy(['active' => true], ['nom' => 'ASC']);

        $detecteurs = $this->alerteManager->getDetectors();

        return $this->render('admin/parametres.html.twig', [
            'current_societe' => $currentSociete,
            'is_societe_mere' => $isSocieteMere,
            'signature_entreprise' => $currentSociete ? "--\n{$currentSociete->getNom()}\n{$currentSociete->getTelephone()}\n{$currentSociete->getEmail()}" : '',
            'types_alerte' => $typesAlerte,
            'societes' => $societes,
            'detecteurs' => $detecteurs,
        ]);
    }

    #[Route('/delais-workflow', name: 'app_admin_delais_workflow', methods: ['POST'])]
    public function updateDelaisWorkflow(Request $request): JsonResponse
    {
        // TODO: Move logic from AdminController::updateDelaisWorkflow
        return $this->configurationService->updateDelaisWorkflow($request);
    }
}