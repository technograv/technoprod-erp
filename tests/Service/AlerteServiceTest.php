<?php

namespace App\Tests\Service;

use App\Entity\Alerte;
use App\Entity\User;
use App\Repository\AlerteRepository;
use App\Repository\AlerteUtilisateurRepository;
use App\Service\AlerteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AlerteServiceTest extends TestCase
{
    private AlerteService $alerteService;
    private MockObject|AlerteRepository $alerteRepository;
    private MockObject|AlerteUtilisateurRepository $alerteUtilisateurRepository;
    private MockObject|EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->alerteRepository = $this->createMock(AlerteRepository::class);
        $this->alerteUtilisateurRepository = $this->createMock(AlerteUtilisateurRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->alerteService = new AlerteService(
            $this->entityManager,
            $this->alerteRepository,
            $this->alerteUtilisateurRepository
        );
    }

    public function testGetAlerteStats(): void
    {
        // Arrange
        $expectedStats = [
            'total' => 10,
            'active' => 7,
            'inactive' => 3,
            'expired' => 2
        ];
        
        $this->alerteRepository
            ->expects($this->once())
            ->method('getGlobalStats')
            ->willReturn($expectedStats);

        // Act
        $result = $this->alerteService->getAlerteStats();

        // Assert
        $this->assertEquals($expectedStats, $result);
    }

    public function testCanUserSeeAlert_WithMatchingRole(): void
    {
        // Arrange
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        
        $alerte = new Alerte();
        $alerte->setCibles(['ROLE_ADMIN']);

        // Act
        $result = $this->alerteService->canUserSeeAlert($alerte, $user);

        // Assert
        $this->assertTrue($result);
    }

    public function testCanUserSeeAlert_WithoutMatchingRole(): void
    {
        // Arrange
        $user = new User();
        $user->setRoles(['ROLE_USER']);
        
        $alerte = new Alerte();
        $alerte->setCibles(['ROLE_ADMIN']);

        // Act
        $result = $this->alerteService->canUserSeeAlert($alerte, $user);

        // Assert
        $this->assertFalse($result);
    }

    public function testCanUserSeeAlert_WithNoCibles(): void
    {
        // Arrange
        $user = new User();
        $user->setRoles(['ROLE_USER']);
        
        $alerte = new Alerte();
        $alerte->setCibles([]);

        // Act
        $result = $this->alerteService->canUserSeeAlert($alerte, $user);

        // Assert
        $this->assertTrue($result);
    }

    public function testCreateAlerte(): void
    {
        // Arrange
        $data = [
            'titre' => 'Test Alerte',
            'message' => 'Message test',
            'type_alerte' => 'warning',
            'cibles_roles' => ['ROLE_USER'],
            'active' => true,
            'dismissible' => true
        ];

        $this->alerteRepository
            ->expects($this->once())
            ->method('findMaxOrdre')
            ->willReturn(5);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Alerte::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $result = $this->alerteService->createAlerte($data);

        // Assert
        $this->assertInstanceOf(Alerte::class, $result);
        $this->assertEquals('Test Alerte', $result->getTitre());
        $this->assertEquals('warning', $result->getType());
        $this->assertEquals(6, $result->getOrdre()); // Max + 1
    }
}