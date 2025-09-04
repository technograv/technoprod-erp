<?php

namespace App\DTO\Alerte;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DTO pour la création d'une nouvelle alerte système
 * Valide les données avant traitement par AlerteService
 */
class AlerteCreateDto
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $titre;

    #[Assert\NotBlank(message: 'Le message est obligatoire')]
    #[Assert\Length(min: 10, minMessage: 'Le message doit contenir au moins {{ limit }} caractères')]
    public string $message;

    #[Assert\NotBlank(message: 'Le type est obligatoire')]
    #[Assert\Choice(
        choices: ['info', 'success', 'warning', 'danger'],
        message: 'Le type doit être info, success, warning ou danger'
    )]
    public string $type;

    #[Assert\Type(
        type: 'array',
        message: 'Les cibles doivent être un tableau'
    )]
    public ?array $cibles = [];

    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'L\'ordre doit être entre {{ min }} et {{ max }}'
    )]
    public int $ordre = 0;

    #[Assert\Type(
        type: 'bool',
        message: 'Le statut actif doit être un booléen'
    )]
    public bool $isActive = true;

    #[Assert\Type(
        type: 'bool',
        message: 'Le statut dismissible doit être un booléen'
    )]
    public bool $dismissible = true;

    public ?string $dateExpiration = null;

    #[Assert\Callback]
    public function validateDateExpiration(ExecutionContextInterface $context): void
    {
        if ($this->dateExpiration && !strtotime($this->dateExpiration)) {
            $context->buildViolation('La date d\'expiration n\'est pas valide')
                ->atPath('dateExpiration')
                ->addViolation();
        }
    }

    #[Assert\Callback] 
    public function validateCibles(ExecutionContextInterface $context): void
    {
        if ($this->cibles) {
            $validRoles = ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_COMMERCIAL', 'ROLE_USER'];
            foreach ($this->cibles as $role) {
                if (!in_array($role, $validRoles)) {
                    $context->buildViolation('Rôle cible invalide : {{ role }}')
                        ->setParameter('{{ role }}', $role)
                        ->atPath('cibles')
                        ->addViolation();
                }
            }
        }
    }
}