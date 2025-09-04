<?php

namespace App\DTO\Dashboard;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO pour la mise à jour du statut de production d'un item de commande
 * Valide les données avant traitement par WorkflowService
 */
class ProductionStatusUpdateDto
{
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(
        choices: ['en_attente', 'en_cours', 'terminee', 'livree'],
        message: 'Le statut doit être en_attente, en_cours, terminee ou livree'
    )]
    public string $statut;

    #[Assert\Type(
        type: 'string',
        message: 'Les commentaires doivent être une chaîne de caractères'
    )]
    public ?string $commentaires = null;

    #[Assert\DateTime(
        message: 'La date de production prévue doit être une date valide'
    )]
    public ?string $dateProductionPrevue = null;
}