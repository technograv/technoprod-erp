# 🏗️ Plan de Séparation des Services - TechnoProd V2.2

## 🎯 OBJECTIF
Séparer chaque service développé pour éviter les régressions futures et créer une architecture modulaire robuste.

---

## 📊 ANALYSE ARCHITECTURE ACTUELLE

### **Services Existants :**
1. **WorkflowService** - Logique métier devis/commandes/factures
2. **CommuneGeometryCacheService** - Cache géométries communes françaises  
3. **TenantService** - Gestion multi-société et permissions
4. **ThemeService** - Gestion thèmes et couleurs interface
5. **ComptabilisationService** - Conformité comptable française

### **Problèmes Identifiés :**
- **Controllers surchargés** : Logique métier dans controllers (AdminController 2000+ lignes)
- **Responsabilités mixées** : Business logic + rendering + data access
- **Couplage fort** : Modifications cassent autres fonctionnalités
- **Code dupliqué** : Logiques similaires dans différents controllers

---

## 🏭 PLAN DE SÉPARATION - ARCHITECTURE MODULAIRE

### 🔔 **SERVICE 1 : AlerteService**
*Extraction logique alertes de AdminController et WorkflowController*

```php
// src/Service/AlerteService.php
namespace App\Service;

use App\Entity\{Alerte, AlerteUtilisateur, User};
use App\DTO\AlerteCreateDto;
use App\Repository\{AlerteRepository, AlerteUtilisateurRepository};

/**
 * Service de gestion des alertes système
 * Responsabilités : CRUD alertes, gestion ciblage rôles, dismissal utilisateur
 */
class AlerteService
{
    public function __construct(
        private AlerteRepository $alerteRepository,
        private AlerteUtilisateurRepository $alerteUtilisateurRepository,
        private EntityManagerInterface $entityManager
    ) {}

    // Méthodes à extraire des controllers
    public function createAlerte(AlerteCreateDto $dto): Alerte { }
    public function updateAlerte(Alerte $alerte, AlerteUpdateDto $dto): Alerte { }
    public function deleteAlerte(Alerte $alerte): bool { }
    public function getVisibleAlertsForUser(User $user): array { }
    public function dismissAlerte(User $user, Alerte $alerte): bool { }
    public function canUserSeeAlerte(User $user, Alerte $alerte): bool { }
    public function reorganizeOrdres(): void { }
}
```

**Extraction de :**
- `AdminController::createAlerte()` (lignes 1456-1520)
- `AdminController::updateAlerte()` (lignes 1522-1580) 
- `WorkflowController::getMesAlertes()` (lignes 372-424)
- `WorkflowController::dismissAlerte()` (lignes 430-482)

---

### 🗺️ **SERVICE 2 : SecteurService** 
*Extraction logique secteurs commerciaux de AdminController*

```php
// src/Service/SecteurService.php
namespace App\Service;

/**
 * Service de gestion des secteurs commerciaux
 * Responsabilités : CRUD secteurs, assignation zones, calculs géographiques
 */
class SecteurService
{
    public function __construct(
        private SecteurRepository $secteurRepository,
        private CommuneGeometryCacheService $cacheService,
        private GeographicBoundariesService $boundariesService
    ) {}

    // Logique métier secteurs
    public function createSecteur(SecteurCreateDto $dto): Secteur { }
    public function assignZoneToSecteur(Secteur $secteur, AttributionCreateDto $dto): Attribution { }
    public function calculerPositionSecteur(Secteur $secteur): ?array { }
    public function getSecteurDataForCommercial(User $commercial): array { }
    public function generateExclusionsGeographiques(Secteur $secteur): array { }
    
    // Méthodes géographiques
    public function getAllSecteursGeoData(): array { }
    public function getCommunesPourType(string $type, DivisionAdministrative $division): array { }
}
```

**Extraction de :**
- `AdminController::getAllSecteursGeoData()` (lignes 800-950)
- `AdminController::calculerPositionHierarchique()` (lignes 876-1100)
- `WorkflowController::getMonSecteur()` (lignes 206-266)
- Toute la logique géographique secteurs

---

### 📊 **SERVICE 3 : DashboardService**
*Extraction logique dashboard de WorkflowController*

```php
// src/Service/DashboardService.php
namespace App\Service;

/**
 * Service de gestion des dashboards utilisateur
 * Responsabilités : Stats commerciales, événements calendrier, KPIs
 */
class DashboardService
{
    public function generateCommercialStats(User $user): array { }
    public function getWeeklyCalendarEvents(User $user, int $weekOffset): array { }
    public function getCommercialActionBlocks(User $user): array { }
    public function getPerformanceMetrics(User $user): array { }
    
    // Actions commerciales
    public function getDevisBrouillons(User $user): array { }
    public function getDevisRelances(User $user): array { }
    public function getCommandesSansLivraison(User $user): array { }
    public function getLivraisonsFacturer(User $user): array { }
}
```

**Extraction de :**
- `WorkflowController::dashboard()` (lignes 172-204)
- Toutes les routes placeholder (lignes 278-366)
- Logique des 4 blocs d'actions commerciales

---

### 👥 **SERVICE 4 : UserManagementService**
*Extraction gestion utilisateurs de AdminController*

```php
// src/Service/UserManagementService.php
namespace App\Service;

/**
 * Service de gestion avancée des utilisateurs
 * Responsabilités : Permissions, groupes, sociétés, profils
 */
class UserManagementService
{
    public function updateUserPermissions(User $user, array $permissions): User { }
    public function assignUserToGroups(User $user, array $groupes): User { }
    public function switchSocietePrincipale(User $user, Societe $societe): User { }
    public function resetUserPassword(User $user): string { }
    public function canUserAccessSociete(User $user, Societe $societe): bool { }
    
    // Stats et analytics
    public function getUserStatistics(): array { }
    public function getGroupStatistics(): array { }
}
```

**Extraction de :**
- `AdminController` routes utilisateurs (lignes 200-400)
- Toute la logique permissions et groupes
- Interface switch utilisateur

---

### 🏢 **SERVICE 5 : ConfigurationService**
*Centralisation configuration système*

```php
// src/Service/ConfigurationService.php
namespace App\Service;

/**
 * Service de configuration système centralisée  
 * Responsabilités : Thèmes, paramètres, environnement
 */
class ConfigurationService
{
    public function updateSystemTheme(ThemeUpdateDto $dto): bool { }
    public function getEnvironmentSettings(): array { }
    public function updateEmailSignature(string $signature): bool { }
    public function getMaintenanceMode(): bool { }
    public function setMaintenanceMode(bool $enabled): bool { }
    
    // Gestion bankues et taux TVA
    public function getBanqueConfiguration(): array { }
    public function getTauxTVAConfiguration(): array { }
}
```

---

## 🔧 IMPLÉMENTATION - ÉTAPES CONCRÈTES

### **ÉTAPE 1 : Création Structure Services (Jour 1)**

```bash
# Créer dossiers architecture
mkdir -p src/Service/Commercial
mkdir -p src/Service/Admin  
mkdir -p src/DTO/Alerte
mkdir -p src/DTO/Secteur
mkdir -p src/DTO/Dashboard
mkdir -p src/DTO/User

# Créer interfaces services
touch src/Service/AlerteService.php
touch src/Service/SecteurService.php  
touch src/Service/DashboardService.php
touch src/Service/UserManagementService.php
touch src/Service/ConfigurationService.php
```

### **ÉTAPE 2 : DTOs de Validation (Jour 1-2)**

```php
// src/DTO/Alerte/AlerteCreateDto.php
use Symfony\Component\Validator\Constraints as Assert;

class AlerteCreateDto
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(max: 255)]
    public string $titre;

    #[Assert\NotBlank(message: 'Le message est obligatoire')]
    public string $message;

    #[Assert\Choice(choices: ['info', 'success', 'warning', 'danger'])]
    public string $type;

    #[Assert\Type('array')]
    public ?array $cibles = [];

    #[Assert\Range(min: 0, max: 100)]
    public int $ordre = 0;
}
```

### **ÉTAPE 3 : Migration Controllers → Services (Jour 2-3)**

```php
// AVANT (AdminController.php)
#[Route('/admin/alertes', methods: ['POST'])]
public function createAlerte(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $alerte = new Alerte();
    $alerte->setTitre($data['titre']);
    // ... 50+ lignes logique métier
}

// APRÈS (AdminController.php)  
#[Route('/admin/alertes', methods: ['POST'])]
public function createAlerte(#[RequestBody] AlerteCreateDto $dto): JsonResponse
{
    try {
        $alerte = $this->alerteService->createAlerte($dto);
        return $this->json(['success' => true, 'alerte' => $alerte]);
    } catch (ValidationException $e) {
        return $this->json(['success' => false, 'errors' => $e->getErrors()], 400);
    }
}
```

### **ÉTAPE 4 : Tests Unitaires Services (Jour 3-4)**

```php
// tests/Service/AlerteServiceTest.php
class AlerteServiceTest extends KernelTestCase
{
    public function testCreateAlerteValid(): void
    {
        $dto = new AlerteCreateDto();
        $dto->titre = 'Test Alerte';
        $dto->message = 'Message test';
        $dto->type = 'info';
        
        $alerte = $this->alerteService->createAlerte($dto);
        
        $this->assertInstanceOf(Alerte::class, $alerte);
        $this->assertEquals('Test Alerte', $alerte->getTitre());
    }
}
```

---

## 📋 ORDRE DE MIGRATION RECOMMANDÉ

### **PRIORITÉ 1 - Services Critiques (Semaine 1)**
1. **AlerteService** : Fonctionnalité récente, bien isolée
2. **DashboardService** : Dashboard commercial complexe à sécuriser
3. **UserManagementService** : Gestion permissions critiques

### **PRIORITÉ 2 - Services Support (Semaine 2)**  
1. **SecteurService** : Logique géographique complexe
2. **ConfigurationService** : Centralisation paramètres système

### **PRIORITÉ 3 - Frontend (Semaine 3)**
1. **Extraction JavaScript** : Modules commercialDashboard, adminInterface
2. **Asset optimization** : Bundling, minification
3. **Component library** : Composants réutilisables

---

## ⚡ COMMANDES D'AIDE MIGRATION

### **Validation architecture :**
```bash
# Vérifier services après création
php bin/console debug:container AlerteService
php bin/console debug:container SecteurService

# Tests des nouveaux services  
./vendor/bin/phpunit tests/Service/

# Validation complete système
php bin/console app:test-compliance
```

### **Monitoring régressions :**
```bash
# Tests fonctionnels après chaque migration
# 1. Login admin et test interface alertes
# 2. Login commercial et test dashboard secteurs
# 3. Test création/modification secteurs
# 4. Test toutes les fonctionnalités critiques
```

---

## 🎯 CRITÈRES DE SUCCÈS SÉPARATION

### **Mesures techniques :**
- **Controllers** : <200 lignes par controller (actuellement 400-2000+)
- **Services** : Responsabilité unique vérifiable
- **Tests** : 80% couverture services métier
- **Couplage** : Dépendances clairement définies dans constructeurs

### **Mesures fonctionnelles :**
- **Zéro régression** : Toutes fonctionnalités préservées
- **Performance** : Temps réponse identiques ou améliorés
- **Maintenabilité** : Nouvelles fonctionnalités plus rapides à développer
- **Debugging** : Isolation des erreurs facilitée

---

## 🚨 VALIDATION FINALE

### **Checklist avant validation :**
- [ ] Tous services créés et injectés
- [ ] Controllers allégés <200 lignes
- [ ] DTOs validation implémentés
- [ ] Tests unitaires services 80%+
- [ ] Zéro régression fonctionnelle
- [ ] Performance maintenue/améliorée
- [ ] Documentation services complète

### **Tests de non-régression obligatoires :**
1. **Dashboard commercial** : Google Maps, alertes, actions
2. **Interface admin** : CRUD alertes, secteurs, utilisateurs  
3. **Système permissions** : Accès rôles et groupes
4. **Performance** : Temps réponse dashboard <2s

---

*Plan créé le 04/09/2025 - Ready pour implémentation Phase 2*