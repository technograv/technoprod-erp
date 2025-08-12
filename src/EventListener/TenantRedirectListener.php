<?php

namespace App\EventListener;

use App\Service\TenantService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class TenantRedirectListener
{
    private const EXCLUDED_ROUTES = [
        'app_tenant_select',
        'app_tenant_switch',
        'app_tenant_context',
        'app_tenant_refresh',
        'app_tenant_theme',
        'app_logout',
        'app_login',
        'app_oauth_google_connect',
        'app_oauth_google_callback',
        '_profiler',
        '_wdt',
    ];

    private const EXCLUDED_PATHS = [
        '/tenant/',
        '/login',
        '/logout',
        '/oauth/',
        '/_profiler/',
        '/_wdt/',
    ];

    public function __construct(
        private Security $security,
        private TenantService $tenantService,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Ignorer les requêtes non-maîtresses
        if (!$event->isMainRequest()) {
            return;
        }

        // Ignorer les routes exclues
        $route = $request->attributes->get('_route');
        if ($route && in_array($route, self::EXCLUDED_ROUTES)) {
            return;
        }

        // Ignorer les chemins exclus
        $pathInfo = $request->getPathInfo();
        foreach (self::EXCLUDED_PATHS as $excludedPath) {
            if (str_starts_with($pathInfo, $excludedPath)) {
                return;
            }
        }

        // Ignorer les requêtes AJAX
        if ($request->isXmlHttpRequest()) {
            return;
        }

        // Vérifier que l'utilisateur est connecté
        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        try {
            // Vérifier s'il y a une société sélectionnée
            $currentSociete = $this->tenantService->getCurrentSociete();
            
            if (!$currentSociete) {
                // Vérifier s'il y a des sociétés disponibles
                $availableSocietes = $this->tenantService->getAvailableSocietes();
                
                if (empty($availableSocietes)) {
                    // Aucune société disponible - ne pas rediriger, laisser l'application gérer l'erreur
                    return;
                }

                if (count($availableSocietes) === 1) {
                    // Une seule société disponible, la sélectionner automatiquement
                    $this->tenantService->setCurrentSociete($availableSocietes[0]);
                    return;
                }

                // Plusieurs sociétés disponibles, rediriger vers la page de sélection
                $redirectUrl = $this->urlGenerator->generate('app_tenant_select');
                $response = new RedirectResponse($redirectUrl);
                $event->setResponse($response);
                return;
            }

            // Une société est sélectionnée, vérifier que l'utilisateur y a encore accès
            if (!$this->tenantService->hasAccessToSociete($currentSociete)) {
                // Plus d'accès, nettoyer et rediriger
                $this->tenantService->clearCache();
                $redirectUrl = $this->urlGenerator->generate('app_tenant_select');
                $response = new RedirectResponse($redirectUrl);
                $event->setResponse($response);
                return;
            }

        } catch (\Exception $e) {
            // En cas d'erreur, ne pas bloquer l'application
            // On pourrait logger l'erreur ici si nécessaire
            return;
        }
    }
}