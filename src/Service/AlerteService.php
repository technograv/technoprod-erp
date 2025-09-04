<?php

namespace App\Service;

use App\Entity\Alerte;
use App\Entity\AlerteUtilisateur;
use App\Entity\User;
use App\Repository\AlerteRepository;
use App\Repository\AlerteUtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;

class AlerteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlerteRepository $alerteRepository,
        private AlerteUtilisateurRepository $alerteUtilisateurRepository
    ) {}

    public function getActiveAlertsForUser(User $user): array
    {
        return $this->alerteRepository->findActiveAlertsForUser($user);
    }

    public function canUserSeeAlert(Alerte $alerte, User $user): bool
    {
        $userRoles = $user->getRoles();
        
        if (empty($alerte->getCibles())) {
            return true;
        }
        
        foreach ($alerte->getCibles() as $requiredRole) {
            if (in_array($requiredRole, $userRoles)) {
                return true;
            }
        }
        
        return false;
    }

    public function isAlerteDismissedByUser(Alerte $alerte, User $user): bool
    {
        $dismissed = $this->alerteUtilisateurRepository->findOneBy([
            'alerte' => $alerte,
            'user' => $user
        ]);

        return $dismissed !== null;
    }

    public function dismissAlertForUser(Alerte $alerte, User $user): bool
    {
        if (!$alerte->isDismissible()) {
            return false;
        }

        if ($this->isAlerteDismissedByUser($alerte, $user)) {
            return false;
        }

        $alerteUtilisateur = new AlerteUtilisateur();
        $alerteUtilisateur->setAlerte($alerte);
        $alerteUtilisateur->setUser($user);

        $this->entityManager->persist($alerteUtilisateur);
        $this->entityManager->flush();

        return true;
    }

    public function createAlerte(array $data): Alerte
    {
        $alerte = new Alerte();
        $alerte->setTitre($data['titre']);
        $alerte->setMessage($data['message']);
        $alerte->setType($data['type_alerte'] ?? 'info');
        
        if (!empty($data['date_expiration'])) {
            $alerte->setDateExpiration(new \DateTimeImmutable($data['date_expiration']));
        }
        
        $alerte->setCibles($data['cibles_roles'] ?? []);
        $alerte->setDismissible($data['dismissible'] ?? true);
        $alerte->setIsActive($data['active'] ?? true);
        $alerte->setOrdre($this->getNextOrdre());

        $this->entityManager->persist($alerte);
        $this->entityManager->flush();

        return $alerte;
    }

    public function updateAlerte(Alerte $alerte, array $data): Alerte
    {
        $alerte->setTitre($data['titre']);
        $alerte->setMessage($data['message']);
        $alerte->setType($data['type_alerte'] ?? 'info');
        
        if (!empty($data['date_expiration'])) {
            $alerte->setDateExpiration(new \DateTimeImmutable($data['date_expiration']));
        } else {
            $alerte->setDateExpiration(null);
        }
        
        $alerte->setCibles($data['cibles_roles'] ?? []);
        $alerte->setDismissible($data['dismissible'] ?? true);
        $alerte->setIsActive($data['active'] ?? true);

        if (isset($data['ordre']) && $data['ordre'] !== $alerte->getOrdre()) {
            $this->alerteRepository->reorganizeOrdres((int)$data['ordre']);
        }

        $this->entityManager->flush();

        return $alerte;
    }

    public function deleteAlerte(Alerte $alerte): bool
    {
        try {
            $this->entityManager->remove($alerte);
            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getNextOrdre(): int
    {
        $maxOrdre = $this->alerteRepository->findMaxOrdre();
        return ($maxOrdre ?? 0) + 1;
    }

    public function getAlerteStats(): array
    {
        $total = $this->alerteRepository->count([]);
        $active = $this->alerteRepository->count(['isActive' => true]);
        
        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active
        ];
    }

    public function getVisibleAlertsForUser(User $user): array
    {
        $alertes = $this->getActiveAlertsForUser($user);
        $visibleAlertes = [];

        foreach ($alertes as $alerte) {
            if ($this->canUserSeeAlert($alerte, $user) && !$this->isAlerteDismissedByUser($alerte, $user)) {
                $visibleAlertes[] = $alerte;
            }
        }

        return $visibleAlertes;
    }
}