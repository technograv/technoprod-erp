<?php

namespace App\Controller\Admin;

use App\Trait\AdminControllerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur de base pour toutes les pages d'administration
 * Garantit la sécurité et fournit des méthodes communes
 */
#[IsGranted('ROLE_ADMIN')]
abstract class AbstractAdminController extends AbstractController
{
    use AdminControllerTrait;

    /**
     * Données communes à tous les templates d'administration
     */
    protected function getBaseTemplateData(): array
    {
        return [
            'admin_section' => true,
            'current_user' => $this->getUser(),
            'breadcrumb' => $this->getBreadcrumb()
        ];
    }

    /**
     * Génère le fil d'Ariane pour la page courante
     * À surcharger dans les contrôleurs enfants si nécessaire
     */
    protected function getBreadcrumb(): array
    {
        return [
            ['label' => 'Administration', 'url' => '/admin'],
        ];
    }
}