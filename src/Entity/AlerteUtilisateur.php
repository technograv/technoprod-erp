<?php

namespace App\Entity;

use App\Repository\AlerteUtilisateurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité AlerteUtilisateur - Tracking des alertes fermées par utilisateur
 * 
 * Permet de suivre quelles alertes ont été fermées par chaque utilisateur
 * pour ne plus les afficher dans leur dashboard personnel.
 * 
 * @author TechnoProd System
 * @version 2.2
 */
#[ORM\Entity(repositoryClass: AlerteUtilisateurRepository::class)]
class AlerteUtilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Alerte::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Alerte $alerte = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dismissedAt = null;

    public function __construct()
    {
        $this->dismissedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAlerte(): ?Alerte
    {
        return $this->alerte;
    }

    public function setAlerte(?Alerte $alerte): static
    {
        $this->alerte = $alerte;
        return $this;
    }

    public function getDismissedAt(): ?\DateTimeInterface
    {
        return $this->dismissedAt;
    }

    public function setDismissedAt(\DateTimeInterface $dismissedAt): static
    {
        $this->dismissedAt = $dismissedAt;
        return $this;
    }
}
