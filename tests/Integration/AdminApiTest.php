<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;

class AdminApiTest extends WebTestCase
{
    private $client;
    private $adminUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        
        // Créer un utilisateur admin pour les tests
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        $this->adminUser = new User();
        $this->adminUser->setNom('Test');
        $this->adminUser->setPrenom('Admin');
        $this->adminUser->setEmail('admin@test.com');
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->adminUser->setPassword('password');
        $this->adminUser->setActive(true);
        
        $entityManager->persist($this->adminUser);
        $entityManager->flush();
        
        // Authentifier l'utilisateur admin
        $this->client->loginUser($this->adminUser);
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        if ($this->adminUser && $entityManager->contains($this->adminUser)) {
            $entityManager->remove($this->adminUser);
            $entityManager->flush();
        }
        
        parent::tearDown();
    }

    public function testAdminDashboardEndpoint(): void
    {
        $this->client->request('GET', '/admin/');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('title', 'Administration');
    }

    public function testAdminDashboardRequiresAuthentication(): void
    {
        // Test sans authentification
        $unauthenticatedClient = static::createClient();
        $unauthenticatedClient->request('GET', '/admin/');
        
        // Doit rediriger vers la page de login
        $this->assertEquals(302, $unauthenticatedClient->getResponse()->getStatusCode());
    }

    public function testGetUsersEndpoint(): void
    {
        $this->client->request('GET', '/admin/users');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('users', $data);
        $this->assertIsArray($data['users']);
    }

    public function testGetUserDetailsEndpoint(): void
    {
        $userId = $this->adminUser->getId();
        
        $this->client->request('GET', "/admin/users/{$userId}");
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('Test', $data['user']['nom']);
        $this->assertEquals('Admin', $data['user']['prenom']);
    }

    public function testToggleUserStatusEndpoint(): void
    {
        $userId = $this->adminUser->getId();
        
        $this->client->request('POST', "/admin/users/{$userId}/toggle");
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('is_active', $data);
    }

    public function testCreateFormeJuridiqueEndpoint(): void
    {
        $formeData = [
            'code' => 'TEST',
            'nom' => 'Test Forme Juridique',
            'template_personne' => 'personne_morale',
            'ordre' => 99
        ];

        $this->client->request(
            'POST',
            '/admin/formes-juridiques',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($formeData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('forme_juridique', $data);
    }

    public function testGetSecteursGeoDataEndpoint(): void
    {
        $this->client->request('GET', '/admin/secteurs/all-geo-data');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('secteurs', $data);
        $this->assertIsArray($data['secteurs']);
    }

    public function testCsrfProtectionOnCriticalEndpoints(): void
    {
        // Test sans token CSRF - doit échouer
        $this->client->request(
            'POST',
            '/admin/alertes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['titre' => 'Test Alert'])
        );

        // Doit retourner une erreur CSRF (403 ou 400)
        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [400, 403]
        );
    }

    public function testInvalidEndpoint(): void
    {
        $this->client->request('GET', '/admin/invalid-endpoint');
        
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testApiResponseFormat(): void
    {
        $this->client->request('GET', '/admin/users');
        
        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        
        // Vérifier que la réponse est du JSON valide
        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data, 'Response should be valid JSON');
        
        // Vérifier les headers appropriés
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        // Vérifier la structure de base
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
    }
}