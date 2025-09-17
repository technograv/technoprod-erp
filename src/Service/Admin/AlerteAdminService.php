<?php

namespace App\Service\Admin;

use App\DTO\AlerteCreateDto;
use App\DTO\AlerteUpdateDto;
use App\Entity\Alerte;
use App\Service\AlerteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Psr\Log\LoggerInterface;

class AlerteAdminService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private ValidatorInterface $validator,
        private AlerteService $alerteService
    ) {
    }

    public function getAllAlertes(): JsonResponse
    {
        $this->logger->info("Récupération de toutes les alertes admin");
        
        $alertes = $this->entityManager->getRepository(Alerte::class)
            ->createQueryBuilder('a')
            ->orderBy('a.ordre', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $alertesData = [];
        foreach ($alertes as $alerte) {
            $alertesData[] = [
                'id' => $alerte->getId(),
                'titre' => $alerte->getTitre(),
                'message' => $alerte->getMessage(),
                'type' => $alerte->getType(),
                'isActive' => $alerte->isActive(),
                'dismissible' => $alerte->isDismissible(),
                'ordre' => $alerte->getOrdre(),
                'cibles' => $alerte->getCibles(),
                'dateExpiration' => $alerte->getDateExpiration() ? $alerte->getDateExpiration()->format('d/m/Y H:i') : null,
                'createdAt' => $alerte->getCreatedAt()->format('d/m/Y H:i'),
                'isExpired' => $alerte->isExpired()
            ];
        }

        return new JsonResponse(['alertes' => $alertesData]);
    }

    public function createAlerte(AlerteCreateDto $dto, Request $request): JsonResponse
    {
        $this->logger->info("Création d'une nouvelle alerte: {$dto->titre}");
        
        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }
            
            $errors = $this->validator->validate($dto);
            
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'errors' => $errorMessages], 400);
            }
            
            // Utiliser le service pour créer l'alerte
            $data = [
                'titre' => $dto->titre,
                'message' => $dto->message,
                'type_alerte' => $dto->type,
                'active' => $dto->isActive,
                'dismissible' => $dto->dismissible,
                'ordre' => $dto->ordre,
                'cibles_roles' => $dto->cibles,
                'date_expiration' => $dto->dateExpiration
            ];
            
            $alerte = $this->alerteService->createAlerte($data);
            $this->logger->info("Alerte {$alerte->getTitre()} créée avec succès");

            return new JsonResponse(['success' => true, 'message' => 'Alerte créée avec succès']);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la création de l'alerte: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    public function getAlerte(Alerte $alerte): JsonResponse
    {
        $this->logger->info("Récupération de l'alerte ID: {$alerte->getId()}");
        
        return new JsonResponse([
            'id' => $alerte->getId(),
            'titre' => $alerte->getTitre(),
            'message' => $alerte->getMessage(),
            'type' => $alerte->getType(),
            'isActive' => $alerte->isActive(),
            'dismissible' => $alerte->isDismissible(),
            'ordre' => $alerte->getOrdre(),
            'cibles' => $alerte->getCibles() ?? [],
            'dateExpiration' => $alerte->getDateExpiration() ? $alerte->getDateExpiration()->format('Y-m-d\TH:i') : null
        ]);
    }

    public function updateAlerte(Alerte $alerte, AlerteUpdateDto $dto, Request $request): JsonResponse
    {
        $this->logger->info("Mise à jour de l'alerte ID: {$alerte->getId()}");
        
        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }
            
            $errors = $this->validator->validate($dto);
            
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'errors' => $errorMessages], 400);
            }
            
            // Utiliser le service pour mettre à jour l'alerte
            $data = [
                'titre' => $dto->titre,
                'message' => $dto->message,
                'type_alerte' => $dto->type,
                'active' => $dto->isActive,
                'dismissible' => $dto->dismissible,
                'ordre' => $dto->ordre,
                'cibles_roles' => $dto->cibles,
                'date_expiration' => $dto->dateExpiration
            ];
            
            $this->alerteService->updateAlerte($alerte, $data);
            $this->logger->info("Alerte {$alerte->getTitre()} mise à jour avec succès");

            return new JsonResponse(['success' => true, 'message' => 'Alerte mise à jour avec succès']);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la mise à jour de l'alerte {$alerte->getId()}: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    public function deleteAlerte(Alerte $alerte, Request $request): JsonResponse
    {
        $this->logger->info("Suppression de l'alerte ID: {$alerte->getId()}");
        
        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }
            
            $alerteTitre = $alerte->getTitre();
            $success = $this->alerteService->deleteAlerte($alerte);
            
            if (!$success) {
                $this->logger->warning("Impossible de supprimer l'alerte {$alerteTitre}");
                return new JsonResponse(['success' => false, 'message' => 'Impossible de supprimer l\'alerte']);
            }

            $this->logger->info("Alerte {$alerteTitre} supprimée avec succès");
            return new JsonResponse(['success' => true, 'message' => 'Alerte supprimée avec succès']);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la suppression de l'alerte {$alerte->getId()}: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}