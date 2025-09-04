<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Tests de sécurité pour vérifier les annotations #[IsGranted]
 */
class SecurityAnnotationTest extends WebTestCase
{
    /**
     * Test que toutes les routes admin nécessitent ROLE_ADMIN
     */
    public function testAdminRoutesRequireAdminRole(): void
    {
        $client = static::createClient();
        $router = static::getContainer()->get(RouterInterface::class);
        
        $adminRoutes = [
            '/admin/',
            '/admin/users',
            '/admin/alertes',
            '/admin/banques',
            '/admin/taux-tva'
        ];
        
        foreach ($adminRoutes as $route) {
            $client->request('GET', $route);
            $response = $client->getResponse();
            
            // Doit rediriger vers login (302) ou retourner 403
            $this->assertContains($response->getStatusCode(), [302, 403], 
                "Route $route doit être protégée");
        }
    }

    /**
     * Test que toutes les routes workflow nécessitent ROLE_USER minimum
     */
    public function testWorkflowRoutesRequireUserRole(): void
    {
        $client = static::createClient();
        
        $workflowRoutes = [
            '/workflow/dashboard',
            '/workflow/dashboard/mon-secteur',
            '/workflow/dashboard/mes-alertes'
        ];
        
        foreach ($workflowRoutes as $route) {
            $client->request('GET', $route);
            $response = $client->getResponse();
            
            // Doit rediriger vers login (302) ou retourner 403
            $this->assertContains($response->getStatusCode(), [302, 403], 
                "Route $route doit être protégée");
        }
    }

    /**
     * Test protection CSRF sur endpoints critiques
     */
    public function testCSRFProtectionOnCriticalEndpoints(): void
    {
        $client = static::createClient();
        
        $csrfRoutes = [
            ['POST', '/admin/alertes'],
            ['PUT', '/admin/alertes/1'],
            ['DELETE', '/admin/alertes/1'],
            ['POST', '/workflow/dashboard/alerte/1/dismiss']
        ];
        
        foreach ($csrfRoutes as [$method, $route]) {
            $client->request($method, $route, [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode(['test' => 'data']));
            
            $response = $client->getResponse();
            
            // Doit être protégé (302 pour auth ou 403 pour CSRF)
            $this->assertContains($response->getStatusCode(), [302, 403, 400], 
                "Route $method $route doit avoir protection CSRF");
        }
    }

    /**
     * Test que les annotations IsGranted sont bien présentes sur les contrôleurs
     */
    public function testControllersHaveSecurityAnnotations(): void
    {
        $adminController = new \ReflectionClass(\App\Controller\AdminController::class);
        $workflowController = new \ReflectionClass(\App\Controller\WorkflowController::class);
        
        // Vérifier annotations au niveau classe
        $adminAttributes = $adminController->getAttributes();
        $workflowAttributes = $workflowController->getAttributes();
        
        $hasSecurityAttribute = function($attributes) {
            foreach ($attributes as $attribute) {
                $attributeName = $attribute->getName();
                if (str_contains($attributeName, 'IsGranted') || 
                    str_contains($attributeName, 'Security') ||
                    $attributeName === 'Symfony\Component\Security\Http\Attribute\IsGranted') {
                    return true;
                }
            }
            return false;
        };
        
        $this->assertTrue($hasSecurityAttribute($adminAttributes), 
            'AdminController doit avoir une annotation de sécurité');
        $this->assertTrue($hasSecurityAttribute($workflowAttributes), 
            'WorkflowController doit avoir une annotation de sécurité');
    }
}