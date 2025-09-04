<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;

class CommuneApiTest extends WebTestCase
{
    private $client;
    private $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        $this->user = new User();
        $this->user->setNom('User');
        $this->user->setPrenom('Test');
        $this->user->setEmail('commune@test.com');
        $this->user->setRoles(['ROLE_USER']);
        $this->user->setPassword('password');
        $this->user->setActive(true);
        
        $entityManager->persist($this->user);
        $entityManager->flush();
        
        $this->client->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        
        if ($this->user && $entityManager->contains($this->user)) {
            $entityManager->remove($this->user);
            $entityManager->flush();
        }
        
        parent::tearDown();
    }

    public function testCommuneSearchApi(): void
    {
        $this->client->request(
            'GET', 
            '/client/api/communes/search?q=Paris'
        );
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        // Vérifier qu'on trouve des résultats pour Paris
        $this->assertNotEmpty($data, 'Should find communes matching "Paris"');
        
        // Vérifier la structure des résultats
        if (!empty($data)) {
            $commune = $data[0];
            $this->assertArrayHasKey('nom', $commune);
            $this->assertArrayHasKey('codePostal', $commune);
            $this->assertArrayHasKey('details', $commune);
        }
    }

    public function testCommuneSearchWithCodePostal(): void
    {
        $this->client->request(
            'GET', 
            '/client/api/communes/search?q=75001'
        );
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        // Vérifier qu'on trouve des résultats pour le code postal 75001
        if (!empty($data)) {
            $commune = $data[0];
            $this->assertStringContains('75001', $commune['codePostal']);
        }
    }

    public function testCommuneSearchEmptyQuery(): void
    {
        $this->client->request(
            'GET', 
            '/client/api/communes/search?q='
        );
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        // Avec une requête vide, doit retourner un tableau vide ou limité
        $this->assertLessThanOrEqual(20, count($data), 'Empty query should return limited results');
    }

    public function testCommuneSearchPerformance(): void
    {
        $startTime = microtime(true);
        
        $this->client->request(
            'GET', 
            '/client/api/communes/search?q=Toulouse'
        );
        
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertResponseIsSuccessful();
        $this->assertLessThan(1000, $responseTime, 'Commune search should be under 1 second');
        
        echo "Commune search response time: " . round($responseTime, 2) . "ms\n";
    }

    public function testCommuneSearchRequiresAuthentication(): void
    {
        $unauthenticatedClient = static::createClient();
        $unauthenticatedClient->request(
            'GET', 
            '/client/api/communes/search?q=Paris'
        );
        
        $this->assertEquals(302, $unauthenticatedClient->getResponse()->getStatusCode());
    }

    public function testCommuneSearchCaseInsensitive(): void
    {
        // Test avec différentes casses
        $queries = ['paris', 'PARIS', 'Paris', 'pArIs'];
        
        foreach ($queries as $query) {
            $this->client->request(
                'GET', 
                "/client/api/communes/search?q={$query}"
            );
            
            $this->assertResponseIsSuccessful();
            
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertIsArray($data);
            
            // Tous les formats doivent retourner des résultats similaires
            if (!empty($data)) {
                $this->assertStringContainsIgnoringCase('paris', $data[0]['nom']);
            }
        }
    }

    public function testCommuneSearchSpecialCharacters(): void
    {
        // Test avec des caractères spéciaux (accents)
        $this->client->request(
            'GET', 
            '/client/api/communes/search?q=Mérignac'
        );
        
        $this->assertResponseIsSuccessful();
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        // L'API doit gérer les accents correctement
        if (!empty($data)) {
            $found = false;
            foreach ($data as $commune) {
                if (stripos($commune['nom'], 'merignac') !== false || 
                    stripos($commune['nom'], 'mérignac') !== false) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Should find communes with special characters');
        }
    }

    public function testCommuneSearchResultLimit(): void
    {
        // Test avec une requête très générique qui peut retourner beaucoup de résultats
        $this->client->request(
            'GET', 
            '/client/api/communes/search?q=Saint'
        );
        
        $this->assertResponseIsSuccessful();
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        // L'API doit limiter le nombre de résultats pour éviter la surcharge
        $this->assertLessThanOrEqual(50, count($data), 'Results should be limited to reasonable number');
    }

    public function testCommuneSearchDataStructure(): void
    {
        $this->client->request(
            'GET', 
            '/client/api/communes/search?q=Lyon'
        );
        
        $this->assertResponseIsSuccessful();
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        if (!empty($data)) {
            $commune = $data[0];
            
            // Vérifier la structure de données attendue
            $requiredFields = ['nom', 'codePostal', 'details'];
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $commune, "Commune should have field: {$field}");
            }
            
            // Vérifier les types de données
            $this->assertIsString($commune['nom']);
            $this->assertIsString($commune['codePostal']);
            $this->assertIsString($commune['details']);
            
            // Vérifier que le code postal a le bon format (5 chiffres)
            $this->assertMatchesRegularExpression('/^\d{5}$/', $commune['codePostal']);
        }
    }
}