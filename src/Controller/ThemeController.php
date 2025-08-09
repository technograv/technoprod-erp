<?php

namespace App\Controller;

use App\Service\ThemeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ThemeController extends AbstractController
{
    public function __construct(
        private ThemeService $themeService
    ) {
    }

    /**
     * Route pour servir le CSS dynamique de la société active
     */
    #[Route('/theme/css', name: 'theme_css', methods: ['GET'])]
    public function dynamicCSS(): Response
    {
        $css = $this->themeService->generateDynamicCSS();
        
        $response = new Response($css);
        $response->headers->set('Content-Type', 'text/css');
        
        // Cache pour 1 heure mais permettre revalidation
        $response->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');
        $response->headers->set('ETag', md5($css));
        
        return $response;
    }

    /**
     * Route pour récupérer les variables JS du thème actuel
     */
    #[Route('/theme/vars.js', name: 'theme_js_vars', methods: ['GET'])]
    public function themeVariables(): Response
    {
        $variables = $this->themeService->getJavaScriptVariables();
        
        $js = "window.THEME_VARIABLES = " . json_encode($variables, JSON_PRETTY_PRINT) . ";";
        
        $response = new Response($js);
        $response->headers->set('Content-Type', 'application/javascript');
        $response->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');
        
        return $response;
    }

    /**
     * Prévisualisation des thèmes pour l'administration
     */
    #[Route('/admin/theme/preview/{societeId}', name: 'admin_theme_preview', methods: ['GET'])]
    public function previewTheme(int $societeId, EntityManagerInterface $entityManager): Response
    {
        // TODO: Vérifier permissions admin
        
        $societe = $entityManager->getRepository(\App\Entity\Societe::class)->find($societeId);
        
        if (!$societe) {
            throw $this->createNotFoundException('Société non trouvée');
        }
        
        $css = $this->themeService->generateDynamicCSS($societe);
        
        $response = new Response($css);
        $response->headers->set('Content-Type', 'text/css');
        $response->headers->set('Cache-Control', 'no-cache');
        
        return $response;
    }
}