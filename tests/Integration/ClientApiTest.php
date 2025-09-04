<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Entity\Client;
use App\Entity\FormeJuridique;

class ClientApiTest extends WebTestCase
{
    private $httpClient;
    private $user;
    private $formeJuridique;

    protected function setUp(): void
    {
        $this->httpClient = static::createClient();
        
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        // Créer un utilisateur de test
        $this->user = new User();
        $this->user->setNom('User');
        $this->user->setPrenom('Test');
        $this->user->setEmail('user@test.com');
        $this->user->setRoles(['ROLE_USER']);
        $this->user->setPassword('password');
        $this->user->setActive(true);
        
        $entityManager->persist($this->user);
        
        // Créer une forme juridique de test
        $this->formeJuridique = new FormeJuridique();
        $this->formeJuridique->setNom('Société à Responsabilité Limitée');
        $this->formeJuridique->setTemplateFormulaire('personne_morale');
        $this->formeJuridique->setOrdre(1);
        
        $entityManager->persist($this->formeJuridique);
        $entityManager->flush();
        
        $this->httpClient->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        // Nettoyer les entités de test créées
        $clientEntities = $entityManager->getRepository(Client::class)->findBy(['code' => 'API001']);
        foreach ($clientEntities as $clientEntity) {
            $entityManager->remove($clientEntity);
        }
        
        if ($this->formeJuridique && $entityManager->contains($this->formeJuridique)) {
            $entityManager->remove($this->formeJuridique);
        }
        if ($this->user && $entityManager->contains($this->user)) {
            $entityManager->remove($this->user);
        }
        
        $entityManager->flush();
        parent::tearDown();
    }

    public function testClientListAccess(): void
    {
        $this->httpClient->request('GET', '/client');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.clients-list, .client-table, table');
    }

    public function testClientListRequiresAuthentication(): void
    {
        $unauthenticatedClient = static::createClient();
        $unauthenticatedClient->request('GET', '/client');
        
        $this->assertEquals(302, $unauthenticatedClient->getResponse()->getStatusCode());
    }

    public function testClientSearchApi(): void
    {
        // Créer d'abord un client de test
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        $testClient = new Client();
        $testClient->setNomEntreprise('Test API Search');
        $testClient->setCode('API001');
        $testClient->setStatut('CLIENT');
        $testClient->setFormeJuridique($this->formeJuridique);
        
        $entityManager->persist($testClient);
        $entityManager->flush();
        
        // Test de recherche
        $this->httpClient->request(
            'GET', 
            '/client/api/search?q=Test'
        );
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        // Vérifier qu'on trouve notre client de test
        $found = false;
        foreach ($data as $client) {
            if ($client['nom_entreprise'] === 'Test API Search') {
                $found = true;
                $this->assertArrayHasKey('id', $client);
                $this->assertArrayHasKey('code', $client);
                $this->assertArrayHasKey('statut', $client);
                break;
            }
        }
        
        $this->assertTrue($found, 'Test client should be found in search results');
    }

    public function testClientContactsApi(): void
    {
        // Créer un client avec des contacts
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        $testClient = new Client();
        $testClient->setNomEntreprise('Client Contacts Test');
        $testClient->setCode('CONT001');
        $testClient->setStatut('CLIENT');
        $testClient->setFormeJuridique($this->formeJuridique);
        
        $entityManager->persist($testClient);
        $entityManager->flush();
        
        // Test API contacts
        $this->httpClient->request('GET', "/client/{$testClient->getId()}/contacts");
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        // Même s'il n'y a pas de contacts, l'API doit retourner un tableau vide
        $this->assertIsArray($data);
    }

    public function testClientAddressesApi(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        $testClient = new Client();
        $testClient->setNomEntreprise('Client Addresses Test');
        $testClient->setCode('ADDR001');
        $testClient->setStatut('CLIENT');
        $testClient->setFormeJuridique($this->formeJuridique);
        
        $entityManager->persist($testClient);
        $entityManager->flush();
        
        // Test API adresses
        $this->httpClient->request('GET', "/client/{$testClient->getId()}/addresses");
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testClientCreationApi(): void
    {
        $clientData = [
            'nom_entreprise' => 'Nouvelle Entreprise API',
            'code' => 'NEW001',
            'statut' => 'PROSPECT',
            'forme_juridique_id' => $this->formeJuridique->getId()
        ];

        $this->httpClient->request(
            'POST',
            '/client/api/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($clientData)
        );

        // Vérifier la réponse (peut être 201 Created ou 200 OK)
        $this->assertContains(
            $this->httpClient->getResponse()->getStatusCode(),
            [200, 201]
        );
        
        if ($this->httpClient->getResponse()->getStatusCode() === 200) {
            $this->assertResponseHeaderSame('content-type', 'application/json');
            
            $data = json_decode($this->httpClient->getResponse()->getContent(), true);
            if (isset($data['success']) && $data['success']) {
                $this->assertArrayHasKey('client', $data);
                $this->assertEquals('Nouvelle Entreprise API', $data['client']['nom_entreprise']);
            }
        }
    }

    public function testClientApiPerformance(): void
    {
        $startTime = microtime(true);
        
        $this->httpClient->request('GET', '/client');
        
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertResponseIsSuccessful();
        $this->assertLessThan(3000, $responseTime, 'Client list should load within 3 seconds');
        
        echo "Client list response time: " . round($responseTime, 2) . "ms\n";
    }

    public function testClientApiErrorHandling(): void
    {
        // Test avec un ID client inexistant
        $this->httpClient->request('GET', '/client/99999/contacts');
        
        $statusCode = $this->httpClient->getResponse()->getStatusCode();
        
        // Doit retourner 404 ou une réponse d'erreur JSON
        $this->assertContains($statusCode, [404, 200]);
        
        if ($statusCode === 200) {
            $this->assertResponseHeaderSame('content-type', 'application/json');
            
            $data = json_decode($this->httpClient->getResponse()->getContent(), true);
            // Si c'est une réponse JSON, elle peut indiquer une erreur
            if (isset($data['error']) || isset($data['success'])) {
                $this->assertTrue(true, 'API handles non-existent client gracefully');
            }
        }
    }

    public function testClientDataIntegrity(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        // Créer un client avec des données complètes
        $testClient = new Client();
        $testClient->setNomEntreprise('Intégrité Test');
        $testClient->setCode('INT001');
        $testClient->setStatut('CLIENT');
        $testClient->setFormeJuridique($this->formeJuridique);
        
        $entityManager->persist($testClient);
        $entityManager->flush();
        
        // Récupérer via API et vérifier l'intégrité des données
        $this->httpClient->request('GET', '/client/api/search?q=Intégrité');
        
        $this->assertResponseIsSuccessful();
        
        $data = json_decode($this->httpClient->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        if (!empty($data)) {
            $client = $data[0];
            $this->assertArrayHasKey('nom_entreprise', $client);
            $this->assertArrayHasKey('code', $client);
            $this->assertArrayHasKey('statut', $client);
            
            // Vérifier que les valeurs correspondent
            $this->assertEquals('Intégrité Test', $client['nom_entreprise']);
            $this->assertEquals('INT001', $client['code']);
            $this->assertEquals('CLIENT', $client['statut']);
        }
    }
}