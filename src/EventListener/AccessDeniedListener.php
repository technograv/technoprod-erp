<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class AccessDeniedListener
{
    public function __construct(
        private RouterInterface $router,
        private Security $security,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        // Vérifier si c'est une exception d'accès refusé
        if (!$exception instanceof AccessDeniedException) {
            return;
        }
        
        $request = $event->getRequest();
        
        // Ne PAS intervenir si l'utilisateur n'est pas connecté du tout
        // Laisser Symfony gérer la redirection vers login
        if (!$this->security->getUser()) {
            return;
        }
        
        // Ne PAS intervenir pour les requêtes AJAX
        if ($request->isXmlHttpRequest()) {
            return;
        }
        
        $session = $request->getSession();
        
        // Pour les routes admin quand l'utilisateur est connecté mais n'a pas ROLE_ADMIN
        if (str_starts_with($request->getPathInfo(), '/admin')) {
            if ($session) {
                $session->getFlashBag()->add('warning', 
                    'Accès refusé : Cette section nécessite des privilèges administrateur. Vous avez été redirigé vers le tableau de bord.');
            }
            
            // Rediriger vers le dashboard workflow
            $response = new RedirectResponse($this->router->generate('workflow_dashboard'));
            $event->setResponse($response);
            return;
        }
        
        // Pour toutes les autres erreurs d'accès refusé, rediriger vers le dashboard avec message
        if ($session) {
            $session->getFlashBag()->add('warning', 
                'Accès refusé : Vous n\'avez pas les permissions nécessaires pour accéder à cette ressource. Vous avez été redirigé vers le tableau de bord.');
        }
        
        // Rediriger vers le dashboard workflow
        $response = new RedirectResponse($this->router->generate('workflow_dashboard'));
        $event->setResponse($response);
    }
}