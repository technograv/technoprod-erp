<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests d'intégration basiques pour TechnoProd
 * Tests des fonctionnalités de base sans création d'entités
 */
class BasicIntegrationTest extends WebTestCase
{
    public function testApplicationIsWorking(): void
    {
        $client = static::createClient();
        
        // Test page d'accueil
        $client->request('GET', '/');
        $response = $client->getResponse();
        
        // Page d'accueil doit soit être accessible (200) soit rediriger vers login (302)
        $this->assertContains(
            $response->getStatusCode(),
            [200, 302],
            'Page d\'accueil doit être accessible ou rediriger vers login'
        );
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        
        $this->assertResponseIsSuccessful();
        
        // Page de login doit contenir des éléments de connexion
        $response = $client->getResponse();
        $content = $response->getContent();
        
        // Vérifier qu'on a du contenu de connexion (formulaire ou boutons OAuth)
        $this->assertStringContainsString('login', strtolower($content), 'Page doit contenir des éléments de connexion');
    }

    public function testProtectedRoutesRequireAuth(): void
    {
        $client = static::createClient();
        
        // Test routes protégées qui doivent rediriger vers login
        $protectedRoutes = [
            '/admin/',
            '/client',
            '/workflow/dashboard',
        ];
        
        foreach ($protectedRoutes as $route) {
            $client->request('GET', $route);
            $response = $client->getResponse();
            
            // Routes protégées doivent rediriger (302) ou refuser l'accès (403)
            $this->assertContains(
                $response->getStatusCode(),
                [302, 403],
                "Route $route doit être protégée"
            );
        }
    }

    public function testAdminPanelIsProtected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/');
        
        // Admin doit être protégé
        $this->assertNotEquals(200, $client->getResponse()->getStatusCode());
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
        ];
        
        foreach ($essentialFiles as $file => $description) {
            $filePath = __DIR__ . '/../../' . $file;
            $this->assertFileExists($filePath, "{$description} doit exister");
        }
    }

    public function testDatabaseConnection(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        
        // Test connexion à la base de données
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        
        $this->assertTrue($connection->isConnected() || $connection->connect());
    }

    public function testSecurityConfiguration(): void
    {
        $client = static::createClient();
        
        // Test requête avec en-têtes suspects
        $client->request('GET', '/', [], [], [
            'HTTP_User-Agent' => '<script>alert("test")</script>'
        ]);
        
        $response = $client->getResponse();
        
        // L'application doit traiter les en-têtes suspects de manière sécurisée
        $this->assertLessThan(500, $response->getStatusCode(), 'Application doit gérer les en-têtes suspects');
    }

    public function testRoutingConfiguration(): void
    {
        $client = static::createClient();
        
        // Test route inexistante
        $client->request('GET', '/this-route-does-not-exist');
        $response = $client->getResponse();
        
        // Route inexistante doit retourner 404
        $this->assertEquals(404, $response->getStatusCode(), 'Route inexistante doit retourner 404');
    }
}