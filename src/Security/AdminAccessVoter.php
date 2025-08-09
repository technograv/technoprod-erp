<?php

namespace App\Security;

use App\Entity\Societe;
use App\Entity\User;
use App\Service\TenantService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AdminAccessVoter extends Voter
{
    public const ADMIN_ACCESS = 'ADMIN_ACCESS';

    public function __construct(
        private Security $security,
        private TenantService $tenantService
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ADMIN_ACCESS;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Super admin a toujours accès
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Pour l'accès admin, vérifier les permissions pour la société courante
        if ($attribute === self::ADMIN_ACCESS) {
            $currentSociete = $this->tenantService->getCurrentSociete();
            
            if (!$currentSociete) {
                return false;
            }

            return $user->canAccessAdminForSociete($currentSociete);
        }

        return false;
    }
}