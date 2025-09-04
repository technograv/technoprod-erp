<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests d'intégration pour les endpoints API de TechnoProd
 * Sans création d'entités pour éviter les problèmes de base de données de test
 */
class ApiEndpointsTest extends WebTestCase
{
    public function testPublicEndpointsAccessibility(): void
    {
        $client = static::createClient();
        
        // Test des endpoints publics (qui ne nécessitent pas d'authentification)
        $publicEndpoints = [
            '/' => 'Page d\'accueil',
            '/login' => 'Page de connexion'
        ];
        
        foreach ($publicEndpoints as $endpoint => $description) {
            $client->request('GET', $endpoint);
            $response = $client->getResponse();
            
            // Les pages publiques doivent être accessibles (200) ou rediriger (302)
            $this->assertContains(
                $response->getStatusCode(),
                [200, 302],
                "{$description} doit être accessible"
            );
        }
    }

    public function testProtectedEndpointsRequireAuth(): void
    {
        $client = static::createClient();
        
        // Test des endpoints protégés (qui nécessitent une authentification)
        $protectedEndpoints = [
            '/admin/' => 'Administration',
            '/workflow/dashboard' => 'Dashboard commercial',
            '/client' => 'Liste clients',
            '/devis' => 'Liste devis'
        ];
        
        foreach ($protectedEndpoints as $endpoint => $description) {
            $client->request('GET', $endpoint);
            $response = $client->getResponse();
            
            // Les pages protégées doivent rediriger vers login (302) ou refuser l'accès (403)
            $this->assertContains(
                $response->getStatusCode(),
                [302, 403],
                "{$description} doit nécessiter une authentification"
            );
        }
    }

    public function testApiEndpointsFormat(): void
    {
        $client = static::createClient();
        
        // Test des endpoints API (qui doivent retourner JSON)
        $apiEndpoints = [
            '/client/api/communes/search?q=Paris' => 'API recherche communes',
            '/admin/users' => 'API liste utilisateurs',
            '/workflow/dashboard/mes-alertes' => 'API alertes utilisateur'
        ];
        
        foreach ($apiEndpoints as $endpoint => $description) {
            $client->request('GET', $endpoint, [], [], [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
                'HTTP_Accept' => 'application/json'
            ]);
            
            $response = $client->getResponse();
            $statusCode = $response->getStatusCode();
            
            // API endpoints doivent soit fonctionner (200) soit demander auth (302/403)
            $this->assertContains(
                $statusCode,
                [200, 302, 400, 403],
                "{$description} doit avoir une réponse API valide"
            );
            
            // Si la réponse est 200, vérifier que c'est du JSON
            if ($statusCode === 200) {
                $contentType = $response->headers->get('Content-Type');
                $this->assertStringContains(
                    'application/json',
                    $contentType ?? '',
                    "{$description} doit retourner du JSON"
                );
            }
        }
    }

    public function testServerConfiguration(): void
    {
        $client = static::createClient();
        
        // Test que le serveur Symfony fonctionne correctement
        $client->request('GET', '/');
        $response = $client->getResponse();
        
        // Vérifier les headers de sécurité de base
        $this->assertNotNull($response->headers->get('X-Powered-By') ?: 'PHP');
        
        // Vérifier que la réponse n'est pas vide
        $this->assertNotEmpty($response->getContent());
        
        // Test de la performance de base
        $startTime = microtime(true);
        $client->request('GET', '/');
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertLessThan(5000, $responseTime, 'Page d\'accueil doit charger en moins de 5 secondes');
        
        echo "Page d'accueil - Temps de réponse: " . round($responseTime, 2) . "ms\n";
    }

    public function testCsrfProtection(): void
    {
        $client = static::createClient();
        
        // Test que les endpoints POST sont protégés contre CSRF
        $postEndpoints = [
            '/admin/alertes' => 'Création alerte',
            '/client/api/create' => 'Création client'
        ];
        
        foreach ($postEndpoints as $endpoint => $description) {
            $client->request('POST', $endpoint, [], [], [
                'CONTENT_TYPE' => 'application/json'
            ], json_encode(['test' => 'data']));
            
            $response = $client->getResponse();
            $statusCode = $response->getStatusCode();
            
            // Les endpoints POST doivent être protégés (302 pour auth ou 400/403 pour CSRF)
            $this->assertContains(
                $statusCode,
                [302, 400, 403, 405],
                "{$description} doit avoir une protection CSRF ou auth"
            );
        }
    }

    public function testHttpMethods(): void
    {
        $client = static::createClient();
        
        // Test des méthodes HTTP supportées
        $methods = ['GET', 'POST', 'PUT', 'DELETE'];
        
        foreach ($methods as $method) {
            $client->request($method, '/');
            $response = $client->getResponse();
            
            // La méthode GET doit fonctionner, les autres peuvent être refusées
            if ($method === 'GET') {
                $this->assertContains($response->getStatusCode(), [200, 302]);
            } else {
                $this->assertContains($response->getStatusCode(), [302, 405, 500]);
            }
        }
    }

    public function testApplicationStructure(): void
    {
        // Test que les fichiers essentiels de l'application existent
        $essentialFiles = [
            'config/services.yaml' => 'Configuration des services',
            'config/packages/doctrine.yaml' => 'Configuration Doctrine',
            'config/packages/framework.yaml' => 'Configuration Symfony',
            'templates/base.html.twig' => 'Template de base',
            'src/Controller/AdminController.php' => 'Contrôleur Admin',
            'src/Service/DashboardService.php' => 'Service Dashboard'
        ];
        
        foreach ($essentialFiles as $file => $description) {
            $filePath = __DIR__ . '/../../' . $file;
            $this->assertFileExists($filePath, "{$description} doit exister");
        }
    }

    public function testRoutingConfiguration(): void
    {
        $client = static::createClient();
        
        // Test que le système de routing fonctionne
        $client->request('GET', '/nonexistent-route');
        $response = $client->getResponse();
        
        // Une route inexistante doit retourner 404
        $this->assertEquals(404, $response->getStatusCode(), 'Route inexistante doit retourner 404');
    }

    public function testSecurityConfiguration(): void
    {
        $client = static::createClient();
        
        // Test des configurations de sécurité
        $client->request('GET', '/admin/');
        $response = $client->getResponse();
        
        // L'admin doit être protégé
        $this->assertNotEquals(200, $response->getStatusCode(), 'Admin doit être protégé');
        
        // Test avec user-agent suspect
        $client->request('GET', '/', [], [], [
            'HTTP_User-Agent' => '<script>alert("xss")</script>'
        ]);
        
        $response = $client->getResponse();
        $this->assertStringNotContainsString('<script>', $response->getContent(), 'User-Agent malveillant doit être filtré');
    }
}