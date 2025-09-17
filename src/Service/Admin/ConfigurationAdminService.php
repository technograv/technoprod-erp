<?php

namespace App\Service\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;
use Psr\Log\LoggerInterface;

class ConfigurationAdminService
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function updateDelaisWorkflow(Request $request): JsonResponse
    {
        $this->logger->info("Mise à jour des délais workflow");
        
        try {
            /** @var User $user */
            $user = $this->security->getUser();
            $societe = $user->getSocietePrincipale();
            
            if (!$societe) {
                return new JsonResponse(['error' => 'Aucune société associée'], 400);
            }

            $data = json_decode($request->getContent(), true);
            
            // Mettre à jour les délais (null = héritage pour sociétés filles)
            if (isset($data['delaiRelanceDevis'])) {
                $societe->setDelaiRelanceDevis($data['delaiRelanceDevis']);
            }
            if (isset($data['delaiFacturation'])) {
                $societe->setDelaiFacturation($data['delaiFacturation']);
            }
            if (isset($data['frequenceVisiteClients'])) {
                $societe->setFrequenceVisiteClients($data['frequenceVisiteClients']);
            }
            if (isset($data['acompteDefautPercent'])) {
                $societe->setAcompteDefautPercent($data['acompteDefautPercent']);
            }
            
            $this->entityManager->flush();
            $this->logger->info("Paramètres workflow de la société {$societe->getNom()} mis à jour");

            return new JsonResponse([
                'success' => true,
                'message' => 'Paramètres workflow mis à jour avec succès'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la mise à jour des délais workflow: {$e->getMessage()}");
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}