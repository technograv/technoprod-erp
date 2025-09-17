<?php

namespace App\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Trait contenant les méthodes communes aux contrôleurs d'administration
 * Respecte le principe DRY (Don't Repeat Yourself)
 */
trait AdminControllerTrait
{
    /**
     * Valide les données JSON reçues dans une requête
     */
    protected function validateJsonRequest(Request $request, array $requiredFields = []): array
    {
        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON data');
        }
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        
        return $data;
    }

    /**
     * Retourne une réponse JSON standardisée pour les succès
     */
    protected function createSuccessResponse(string $message, array $data = []): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Retourne une réponse JSON standardisée pour les erreurs
     */
    protected function createErrorResponse(string $message, int $statusCode = 400, array $errors = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * Valide une entité et retourne les erreurs s'il y en a
     */
    protected function validateEntity(object $entity, ValidatorInterface $validator): array
    {
        $violations = $validator->validate($entity);
        $errors = [];
        
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
        
        return $errors;
    }
}