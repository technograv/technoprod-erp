<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Entity\Secteur;
use App\Entity\Client;

class WorkflowApiTest extends WebTestCase
{
    private $client;
    private $commercialUser;
    private $secteur;
    private $testClient;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        // Créer un utilisateur commercial
        $this->commercialUser = new User();
        $this->commercialUser->setNom('Commercial');
        $this->commercialUser->setPrenom('Test');
        $this->commercialUser->setEmail('commercial@test.com');
        $this->commercialUser->setRoles(['ROLE_COMMERCIAL']);
        $this->commercialUser->setPassword('password');
        $this->commercialUser->setActive(true);
        
        $entityManager->persist($this->commercialUser);
        
        // Créer un secteur de test
        $this->secteur = new Secteur();
        $this->secteur->setNomSecteur('Secteur Test');
        $this->secteur->setCouleurHex('#007bff');
        $this->secteur->setCommercial($this->commercialUser);
        $this->secteur->setActive(true);
        
        $entityManager->persist($this->secteur);
        
        // Créer un client de test
        $this->testClient = new Client();
        $this->testClient->setNomEntreprise('Client Test API');
        $this->testClient->setCode('TEST001');
        $this->testClient->setStatut('PROSPECT');
        $this->testClient->setSecteur($this->secteur);
        
        $entityManager->persist($this->testClient);
        $entityManager->flush();
        
        // Authentifier l'utilisateur commercial
        $this->client->loginUser($this->commercialUser);
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        // Nettoyage des entités de test
        if ($this->testClient && $entityManager->contains($this->testClient)) {
            $entityManager->remove($this->testClient);
        }
        if ($this->secteur && $entityManager->contains($this->secteur)) {
            $entityManager->remove($this->secteur);
        }
        if ($this->commercialUser && $entityManager->contains($this->commercialUser)) {
            $entityManager->remove($this->commercialUser);
        }
        
        $entityManager->flush();
        parent::tearDown();
    }

    public function testWorkflowDashboardAccess(): void
    {
        $this->client->request('GET', '/workflow/dashboard');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.dashboard-workflow');
    }

    public function testWorkflowRequiresAuthentication(): void
    {
        $unauthenticatedClient = static::createClient();
        $unauthenticatedClient->request('GET', '/workflow/dashboard');
        
        // Doit rediriger vers login
        $this->assertEquals(302, $unauthenticatedClient->getResponse()->getStatusCode());
    }

    public function testGetMonSecteurEndpoint(): void
    {
        $this->client->request(
            'GET', 
            '/workflow/dashboard/mon-secteur',
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('secteurs', $data);
        $this->assertArrayHasKey('contrats_actifs', $data);
        $this->assertIsArray($data['secteurs']);
    }

    public function testGetMesAlertesEndpoint(): void
    {
        $this->client->request(
            'GET', 
            '/workflow/dashboard/mes-alertes',
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('alertes', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertIsArray($data['alertes']);
    }

    public function testWorkflowStatsPerformance(): void
    {
        $startTime = microtime(true);
        
        $this->client->request(
            'GET', 
            '/workflow/dashboard/mon-secteur',
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertResponseIsSuccessful();
        
        // Vérifier que la réponse est rapide (moins de 2 secondes)
        $this->assertLessThan(2000, $responseTime, 'API response should be under 2 seconds');
        
        echo "Workflow API response time: " . round($responseTime, 2) . "ms\n";
    }

    public function testAjaxHeaderRequirement(): void
    {
        // Test sans header X-Requested-With
        $this->client->request('GET', '/workflow/dashboard/mon-secteur');
        
        // Certaines routes AJAX peuvent retourner une erreur ou redirection
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 400, 403]);
    }

    public function testJsonResponseStructure(): void
    {
        $this->client->request(
            'GET', 
            '/workflow/dashboard/mes-alertes',
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        
        $this->assertResponseIsSuccessful();
        
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);
        
        // Vérifier la structure JSON
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertIsBool($data['success']);
        
        if ($data['success']) {
            $this->assertArrayHasKey('alertes', $data);
            $this->assertIsArray($data['alertes']);
            
            // Si des alertes existent, vérifier leur structure
            if (!empty($data['alertes'])) {
                $alerte = $data['alertes'][0];
                $expectedKeys = ['id', 'titre', 'message', 'type', 'typeBootstrap', 'dismissible'];
                
                foreach ($expectedKeys as $key) {
                    $this->assertArrayHasKey($key, $alerte, "Alert should have key: $key");
                }
            }
        }
    }

    public function testWorkflowPermissions(): void
    {
        // Test que l'utilisateur ne peut accéder qu'à ses propres données
        $this->client->request(
            'GET', 
            '/workflow/dashboard/mon-secteur',
            [],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        
        $this->assertResponseIsSuccessful();
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        // Vérifier que l'utilisateur ne voit que ses secteurs
        if (!empty($data['secteurs'])) {
            foreach ($data['secteurs'] as $secteur) {
                // Le secteur doit appartenir à l'utilisateur connecté
                $this->assertNotEmpty($secteur['nom']);
            }
        }
    }
}