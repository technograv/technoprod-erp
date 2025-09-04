<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Entity\Alerte;
use App\Entity\Secteur;

class WorkflowControllerTest extends WebTestCase
{
    /**
     * Test endpoint mon-secteur avec utilisateur authentifié
     */
    public function testGetMonSecteurRequiresAuthentication(): void
    {
        $client = static::createClient();
        
        // Test sans authentification
        $client->request('GET', '/workflow/dashboard/mon-secteur', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    /**
     * Test endpoint mes-alertes avec utilisateur authentifié
     */
    public function testGetMesAlertesRequiresAuthentication(): void
    {
        $client = static::createClient();
        
        // Test sans authentification
        $client->request('GET', '/workflow/dashboard/mes-alertes', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }

    /**
     * Test structure JSON de la réponse mon-secteur
     */
    public function testMonSecteurJsonStructure(): void
    {
        $client = static::createClient();
        
        // Créer un utilisateur test
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setNom('Test');
        $user->setPrenom('User');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('password');
        $user->setActive(true);
        
        $entityManager->persist($user);
        $entityManager->flush();
        
        // Simuler connexion (pour test d'intégration réel, utiliser loginUser)
        $client->loginUser($user);
        
        $client->request('GET', '/workflow/dashboard/mon-secteur', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $data = json_decode($client->getResponse()->getContent(), true);
        
        // Vérifier structure JSON
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('secteurs', $data);
        $this->assertArrayHasKey('contrats_actifs', $data);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['secteurs']);
        $this->assertIsArray($data['contrats_actifs']);
        
        // Nettoyage
        $entityManager->remove($user);
        $entityManager->flush();
    }

    /**
     * Test structure JSON de la réponse mes-alertes
     */
    public function testMesAlertesJsonStructure(): void
    {
        $client = static::createClient();
        
        // Créer un utilisateur test
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        
        $user = new User();
        $user->setEmail('test2@example.com');
        $user->setNom('Test2');
        $user->setPrenom('User2');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('password');
        $user->setActive(true);
        
        $entityManager->persist($user);
        
        // Créer une alerte test
        $alerte = new Alerte();
        $alerte->setTitre('Alerte Test');
        $alerte->setMessage('Message test');
        $alerte->setType('info');
        $alerte->setCibles(['ROLE_USER']);
        $alerte->setIsActive(true);
        $alerte->setDismissible(true);
        $alerte->setOrdre(1);
        
        $entityManager->persist($alerte);
        $entityManager->flush();
        
        // Simuler connexion
        $client->loginUser($user);
        
        $client->request('GET', '/workflow/dashboard/mes-alertes', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        $data = json_decode($client->getResponse()->getContent(), true);
        
        // Vérifier structure JSON
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('alertes', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['alertes']);
        
        // Vérifier structure d'une alerte
        if (!empty($data['alertes'])) {
            $alerte = $data['alertes'][0];
            $this->assertArrayHasKey('id', $alerte);
            $this->assertArrayHasKey('titre', $alerte);
            $this->assertArrayHasKey('message', $alerte);
            $this->assertArrayHasKey('type', $alerte);
            $this->assertArrayHasKey('typeBootstrap', $alerte);
            $this->assertArrayHasKey('dismissible', $alerte);
        }
        
        // Nettoyage
        $entityManager->remove($alerte);
        $entityManager->remove($user);
        $entityManager->flush();
    }
}