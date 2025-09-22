# Documentation Complète - Application TechnoProd ERP

> Application Symfony de gestion commerciale et ERP développée pour TechnoProd  
> **Date de génération :** 22 septembre 2025  
> **Version :** 2.2  

---

## Table des Matières

1. [Vue d'ensemble](#vue-densemble)
2. [Architecture générale](#architecture-générale)
3. [Entités et base de données](#entités-et-base-de-données)
4. [Controllers et routes](#controllers-et-routes)
5. [Services métier](#services-métier)
6. [Templates et vues](#templates-et-vues)
7. [Formulaires](#formulaires)
8. [Configuration](#configuration)

---

## Vue d'ensemble

TechnoProd ERP est une application Symfony 6.x de gestion commerciale complète développée pour les besoins spécifiques du groupe TechnoProd. L'application gère l'ensemble du cycle commercial : prospection, devis, commandes, facturation, avec un système de gestion de secteurs géographiques avancé.

### Fonctionnalités principales

- **Gestion multi-sociétés** : Support de plusieurs sociétés (mère/filles)
- **CRM intégré** : Gestion complète des prospects et clients
- **Workflow commercial** : Devis → Commandes → Factures
- **Secteurs géographiques** : Attribution automatique par divisions administratives
- **Comptabilité** : Module comptable avec PCG intégré
- **Multi-tenant** : Gestion des permissions par société
- **Logging avancé** : Traçabilité complète des actions

---

## Architecture générale

### Structure des dossiers

```
src/
├── Command/           # Commandes CLI Symfony
├── Controller/        # Controllers web
│   ├── Admin/        # Administration système
│   └── Api/          # API REST
├── Entity/           # Entités Doctrine
├── Form/             # Types de formulaires
├── Repository/       # Repositories Doctrine
├── Security/         # Voters et sécurité
├── Service/          # Services métier
├── Trait/            # Traits réutilisables
└── Twig/             # Extensions Twig

templates/
├── admin/            # Templates administration
├── client/           # Gestion clients
├── devis/            # Gestion devis
├── workflow/         # Dashboard workflow
└── base.html.twig    # Layout principal
```

### Technologies utilisées

- **Framework :** Symfony 6.x
- **Base de données :** PostgreSQL
- **ORM :** Doctrine
- **Frontend :** Bootstrap 5, JavaScript, Select2
- **PDF :** DomPDF
- **Email :** Gmail API / SMTP
- **Authentification :** Google OAuth2
- **Cache :** Symfony Cache

---

## Entités et base de données

### Entités principales

#### User (Utilisateur)
**Fichier :** `/src/Entity/User.php`

**Attributs principaux :**
- `id` (int) - Clé primaire
- `email` (string, 180) - Unique, identifiant
- `roles` (array) - Rôles Symfony
- `password` (string, nullable) - Mot de passe hashé
- `nom` (string, 100) - Nom utilisateur
- `prenom` (string, 100) - Prénom utilisateur
- `isActive` (bool) - Statut actif
- `googleId` (string, nullable) - ID Google OAuth
- `societePrincipale` (Societe, nullable) - Société principale
- `objectifMensuel` (decimal, nullable) - Objectif commercial mensuel

**Relations :**
- `OneToMany` vers `Client` (commercial)
- `OneToMany` vers `Secteur` (commercial)
- `OneToMany` vers `Devis` (commercial)
- `OneToMany` vers `UserSocieteRole`
- `ManyToMany` vers `GroupeUtilisateur`
- `OneToMany` vers `UserPermission`

#### Societe (Société)
**Fichier :** `/src/Entity/Societe.php`

**Attributs principaux :**
- `id` (int) - Clé primaire
- `nom` (string, 255) - Nom société
- `type` (string, 20) - "mere" ou "fille"
- `siret` (string, 100, nullable) - SIRET
- `adresse` (string, 255, nullable) - Adresse
- `couleurPrimaire` (string, 7, nullable) - Couleur thème
- `delaiRelanceDevis` (int, default: 14) - Délai relance devis
- `acompteDefautPercent` (decimal, default: 30.00) - % acompte par défaut

**Relations :**
- `ManyToOne` vers `Societe` (societeParent)
- `OneToMany` vers `Societe` (societesFilles)
- `OneToMany` vers `UserSocieteRole`

#### Client (Client/Prospect)
**Fichier :** `/src/Entity/Client.php`

**Attributs principaux :**
- `id` (int) - Clé primaire
- `code` (string, 20, unique) - Code client
- `statut` (string, 20) - "prospect" ou "client"
- `nomEntreprise` (string, 200, nullable) - Dénomination sociale
- `famille` (string, 100, nullable) - Famille client
- `delaiPaiement` (int, nullable, default: 30) - Délai paiement
- `tauxTva` (decimal, default: 20.00) - Taux TVA
- `notes` (text, nullable) - Notes internes

**Relations :**
- `ManyToOne` vers `User` (commercial)
- `ManyToOne` vers `Secteur`
- `ManyToOne` vers `FormeJuridique`
- `OneToMany` vers `Contact`
- `OneToMany` vers `Adresse`
- `OneToMany` vers `Devis`
- `ManyToMany` vers `Tag`
- `ManyToOne` vers `Contact` (contactFacturationDefault)
- `ManyToOne` vers `Contact` (contactLivraisonDefault)

#### Contact
**Fichier :** `/src/Entity/Contact.php`

**Attributs principaux :**
- `id` (int) - Clé primaire
- `nom` (string, 100, nullable) - Nom contact
- `prenom` (string, 100, nullable) - Prénom contact
- `fonction` (string, 150, nullable) - Fonction
- `email` (string, 180, nullable) - Email
- `telephone` (string, 25, nullable) - Téléphone fixe
- `telephoneMobile` (string, 25, nullable) - Mobile
- `civilite` (string, 10, nullable) - Civilité
- `isFacturationDefault` (bool) - Contact facturation par défaut
- `isLivraisonDefault` (bool) - Contact livraison par défaut

**Relations :**
- `ManyToOne` vers `Client`
- `ManyToOne` vers `Adresse`

#### Adresse
**Fichier :** `/src/Entity/Adresse.php`

**Attributs principaux :**
- `id` (int) - Clé primaire
- `nom` (string, 100) - Nom adresse
- `ligne1` (string, 200) - Adresse ligne 1
- `ligne2` (string, 200, nullable) - Adresse ligne 2
- `ligne3` (string, 200, nullable) - Adresse ligne 3
- `codePostal` (string, 10) - Code postal
- `ville` (string, 100) - Ville
- `pays` (string, 100, default: "France") - Pays
- `deletedAt` (datetime, nullable) - Soft delete

**Relations :**
- `ManyToOne` vers `Client`

#### Devis
**Fichier :** `/src/Entity/Devis.php`

**Attributs principaux :**
- `id` (int) - Clé primaire
- `numeroDevis` (string, 20, unique) - Numéro devis
- `statut` (string, 20, default: "brouillon") - Statut devis
- `dateCreation` (date) - Date création
- `dateValidite` (date) - Date validité
- `totalHt` (decimal) - Total HT
- `totalTva` (decimal) - Total TVA
- `totalTtc` (decimal) - Total TTC
- `acomptePercent` (decimal, nullable) - % acompte
- `notesInternes` (text, nullable) - Notes internes
- `notesClient` (text, nullable) - Notes client
- `nomProjet` (string, 255, nullable) - Nom projet

**Relations :**
- `ManyToOne` vers `Client`
- `ManyToOne` vers `User` (commercial)
- `ManyToOne` vers `Contact` (contactFacturation)
- `ManyToOne` vers `Contact` (contactLivraison)
- `ManyToOne` vers `Adresse` (adresseFacturation)
- `ManyToOne` vers `Adresse` (adresseLivraison)
- `OneToMany` vers `DevisElement`
- `OneToMany` vers `DevisVersion`
- `OneToMany` vers `DevisLog`

#### Produit
**Fichier :** `/src/Entity/Produit.php`

**Attributs principaux :**
- `id` (int) - Clé primaire
- `designation` (string, 255) - Désignation
- `reference` (string, 50, unique) - Référence
- `type` (string, 20, default: "produit") - Type : produit/service/forfait
- `prixAchatHt` (decimal) - Prix achat HT
- `prixVenteHt` (decimal) - Prix vente HT
- `tvaPercent` (decimal, default: 20.00) - Taux TVA
- `unite` (string, 50, default: "unité") - Unité
- `stockQuantite` (int, nullable) - Stock
- `actif` (bool, default: true) - Statut actif

**Relations :**
- `OneToMany` vers `DevisItem`
- `OneToMany` vers `ProductImage`

#### Secteur
**Fichier :** `/src/Entity/Secteur.php`

**Attributs principaux :**
- `id` (int) - Clé primaire
- `nomSecteur` (string, 150) - Nom secteur
- `couleurHex` (string, 7, nullable) - Couleur hexadécimale
- `isActive` (bool, default: true) - Statut actif
- `description` (text, nullable) - Description

**Relations :**
- `ManyToOne` vers `User` (commercial)
- `ManyToOne` vers `TypeSecteur`
- `OneToMany` vers `AttributionSecteur`
- `OneToMany` vers `Client`

### Schéma relationnel

```
User (1) ←→ (N) Client : commercial
User (1) ←→ (N) Secteur : commercial  
User (1) ←→ (N) Devis : commercial
User (N) ←→ (N) GroupeUtilisateur
User (1) ←→ (N) UserSocieteRole
User (1) ←→ (N) UserPermission

Societe (1) ←→ (N) Societe : parent/enfants

Client (1) ←→ (N) Contact
Client (1) ←→ (N) Adresse
Client (1) ←→ (N) Devis
Client (N) ←→ (N) Tag
Client (N) ←→ (1) FormeJuridique
Client (N) ←→ (1) Secteur
Client (N) ←→ (1) Contact : facturationDefault
Client (N) ←→ (1) Contact : livraisonDefault

Contact (N) ←→ (1) Adresse

Devis (1) ←→ (N) DevisElement
Devis (1) ←→ (N) DevisVersion
Devis (1) ←→ (N) DevisLog

Produit (1) ←→ (N) DevisItem
Produit (1) ←→ (N) ProductImage

Secteur (1) ←→ (N) AttributionSecteur
TypeSecteur (1) ←→ (N) Secteur
```

---

## Controllers et routes

### Controllers principaux

#### HomeController
**Fichier :** `/src/Controller/HomeController.php`
- **Route principale :** `/` → `app_home`
- **Action :** Redirection vers `workflow_dashboard`

#### ClientController
**Fichier :** `/src/Controller/ClientController.php`
**Préfixe :** `/client`

**Routes principales :**
- `GET /client/` → `app_client_index` - Liste des clients
- `GET|POST /client/new` → `app_client_new` - Nouveau client
- `GET /client/{id}` → `app_client_show` - Affichage client
- `GET|POST /client/{id}/edit` → `app_client_edit` - Édition client
- `POST /client/{id}/convert` → `app_client_convert_to_client` - Conversion prospect→client
- `POST /client/{id}` → `app_client_delete` - Suppression client
- `GET /client/api/clients/search` → `app_api_clients_search` - API recherche
- `GET /client/modal/new` → `app_client_modal_new` - Modal nouveau client

**Templates utilisés :**
- `client/index.html.twig` - Liste clients
- `client/show.html.twig` - Détail client
- `client/new.html.twig` - Nouveau client
- `client/edit.html.twig` - Édition client
- `client/modal_new_working.html.twig` - Modal création
- `client/modal_edit.html.twig` - Modal édition

#### DevisController
**Fichier :** `/src/Controller/DevisController.php`
**Préfixe :** `/devis`

**Routes principales :**
- `GET /devis/` → `app_devis_index` - Liste devis
- `GET|POST /devis/new` → `app_devis_new` - Nouveau devis
- `GET /devis/{id}` → `app_devis_show` - Affichage devis
- `GET|POST /devis/{id}/edit` → `app_devis_edit` - Édition devis
- `GET /devis/{id}/pdf` → `app_devis_pdf` - PDF devis
- `POST /devis/{id}/send` → `app_devis_send` - Envoi devis

**Templates utilisés :**
- `devis/index.html.twig` - Liste devis
- `devis/show.html.twig` - Détail devis
- `devis/new.html.twig` - Nouveau devis
- `devis/edit.html.twig` - Édition devis
- `devis/pdf.html.twig` - Template PDF

#### AdminController
**Fichier :** `/src/Controller/AdminController.php`
**Préfixe :** `/admin`

**Routes d'administration :**
- Administration des utilisateurs
- Configuration système
- Gestion des sociétés
- Paramètres globaux

#### WorkflowController
**Fichier :** `/src/Controller/WorkflowController.php`

**Routes principales :**
- `GET /workflow/dashboard` → `workflow_dashboard` - Dashboard principal
- API endpoints pour le workflow commercial

**Templates utilisés :**
- `workflow/dashboard.html.twig` - Dashboard principal

### Controllers d'administration

#### Admin/DashboardController
**Fichier :** `/src/Controller/Admin/DashboardController.php`
- Dashboard d'administration
- Statistiques globales

#### Admin/ConfigurationController
**Fichier :** `/src/Controller/Admin/ConfigurationController.php`
- Configuration des paramètres métier
- Gestion des données de référence

#### Admin/UserManagementController
**Fichier :** `/src/Controller/Admin/UserManagementController.php`
- Gestion des utilisateurs
- Attribution des permissions

### API Controllers

#### ApiController
**Fichier :** `/src/Controller/ApiController.php`
- API publique
- Endpoints de recherche

#### Api/PublicApiController
**Fichier :** `/src/Controller/Api/PublicApiController.php`
- API publique externe
- Intégrations tiers

---

## Services métier

### Services de logging

#### ClientLoggerService
**Fichier :** `/src/Service/ClientLoggerService.php`
**Usage :** Traçabilité des actions sur les clients

**Méthodes principales :**
- `log(Client $client, string $action, ?string $details = null)` - Log générique
- `logCreated(Client $client)` - Log création
- `logUpdated(Client $client, array $changes)` - Log modification
- `logDeleted(Client $client)` - Log suppression

**Utilisé dans :**
- `ClientController` - Toutes les actions CRUD

#### DevisLoggerService
**Fichier :** `/src/Service/DevisLoggerService.php`
**Usage :** Traçabilité des actions sur les devis

**Méthodes principales :**
- `logCreated(Devis $devis)` - Log création devis
- `logStatusChanged(Devis $devis, string $oldStatus, string $newStatus)` - Log changement statut
- `logSent(Devis $devis, string $recipient)` - Log envoi

### Services fonctionnels

#### DashboardService
**Fichier :** `/src/Service/DashboardService.php`
**Usage :** Calcul des métriques et statistiques

**Méthodes principales :**
- `getAdminDashboardStats()` - Stats administration
- `getCommercialStats(User $user)` - Stats commerciales
- `getSecteurStats(Secteur $secteur)` - Stats par secteur

**Utilisé dans :**
- `WorkflowController` - Dashboard principal
- `Admin/DashboardController` - Dashboard admin

#### DocumentNumerotationService
**Fichier :** `/src/Service/DocumentNumerotationService.php`
**Usage :** Génération des numéros de documents

**Méthodes principales :**
- `previewProchainNumero(string $type)` - Aperçu prochain numéro
- `genererNumero(string $type)` - Génération effective
- `resetNumerotation(string $type)` - Reset compteur

#### TenantService
**Fichier :** `/src/Service/TenantService.php`
**Usage :** Gestion multi-tenant

**Méthodes principales :**
- `getCurrentSociete()` - Société courante
- `hasAccess(User $user, Societe $societe)` - Vérification accès
- `switchSociete(User $user, Societe $societe)` - Changement société

#### SecteurService
**Fichier :** `/src/Service/SecteurService.php`
**Usage :** Gestion des secteurs géographiques

**Méthodes principales :**
- `findSecteurByCommune(string $codePostal)` - Recherche par commune
- `assignClientToSecteur(Client $client)` - Attribution automatique
- `getSecteurStatistiques(Secteur $secteur)` - Statistiques secteur

### Services techniques

#### CacheService
**Fichier :** `/src/Service/CacheService.php`
**Usage :** Gestion du cache applicatif

#### AuditService
**Fichier :** `/src/Service/AuditService.php`
**Usage :** Audit et conformité

#### TemplateService
**Fichier :** `/src/Service/TemplateService.php`
**Usage :** Gestion des templates personnalisés

---

## Templates et vues

### Layouts de base

#### base.html.twig
**Fichier :** `/templates/base.html.twig`
**Usage :** Layout principal de l'application

**Blocs Twig :**
- `title` - Titre de la page
- `stylesheets` - CSS spécifiques
- `javascripts` - JS spécifiques
- `body` - Contenu principal

**Fonctionnalités :**
- Navigation responsive Bootstrap 5
- Intégration Font Awesome
- CSS dynamique société
- Menu dropdown contextuel

### Templates métier

#### client/
**Répertoire :** `/templates/client/`

**Templates principaux :**
- `index.html.twig` - Liste clients (étend `base.html.twig`)
- `show.html.twig` - Détail client (étend `base.html.twig`)
- `new.html.twig` - Formulaire nouveau client (étend `base.html.twig`)
- `edit.html.twig` - Formulaire édition client (étend `base.html.twig`)
- `modal_new_working.html.twig` - Modal création client
- `modal_edit.html.twig` - Modal édition client

**Includes utilisés :**
- `_form.html.twig` - Formulaire client
- `_delete_form.html.twig` - Formulaire suppression

#### devis/
**Répertoire :** `/templates/devis/`

**Templates principaux :**
- `index.html.twig` - Liste devis (étend `base.html.twig`)
- `show.html.twig` - Détail devis (étend `base.html.twig`)
- `new.html.twig` - Nouveau devis (étend `base.html.twig`)
- `edit.html.twig` - Édition devis (étend `base.html.twig`)
- `pdf.html.twig` - Template PDF devis

**Partials :**
- `partials/client_selector.html.twig` - Sélecteur client
- `partials/contacts_addresses_section.html.twig` - Section contacts/adresses

#### workflow/
**Répertoire :** `/templates/workflow/`

**Templates principaux :**
- `dashboard.html.twig` - Dashboard principal (étend `base.html.twig`)

**Partials :**
- `partials/calendar_week.html.twig` - Vue calendrier semaine

#### admin/
**Répertoire :** `/templates/admin/`

**Templates d'administration :**
- `dashboard.html.twig` - Dashboard admin
- `users.html.twig` - Gestion utilisateurs
- `societes.html.twig` - Gestion sociétés
- `parametres.html.twig` - Paramètres système

### Hiérarchie des templates

```
base.html.twig (layout principal)
├── client/
│   ├── index.html.twig (extends base)
│   ├── show.html.twig (extends base)
│   ├── new.html.twig (extends base)
│   ├── edit.html.twig (extends base)
│   └── partials/
├── devis/
│   ├── index.html.twig (extends base)
│   ├── show.html.twig (extends base)
│   └── partials/
├── workflow/
│   ├── dashboard.html.twig (extends base)
│   └── partials/
└── admin/
    ├── dashboard.html.twig (extends base)
    └── [templates admin] (extends base)
```

---

## Formulaires

### FormTypes principaux

#### ClientType
**Fichier :** `/src/Form/ClientType.php`
**Usage :** Formulaire client/prospect

**Champs principaux :**
- Informations entreprise (nomEntreprise, formeJuridique)
- Conditions commerciales (delaiPaiement, modePaiement)
- Notes et informations complémentaires

#### DevisType
**Fichier :** `/src/Form/DevisType.php`
**Usage :** Formulaire devis

**Champs principaux :**
- Client (EntityType vers Client)
- Contacts facturation/livraison
- Dates validité
- Conditions commerciales

#### ContactType
**Fichier :** `/src/Form/ContactType.php`
**Usage :** Formulaire contact

**Champs principaux :**
- Informations personnelles (civilité, nom, prénom)
- Coordonnées (email, téléphone, mobile)
- Fonction et notes

#### AdresseType
**Fichier :** `/src/Form/AdresseType.php`
**Usage :** Formulaire adresse

**Champs principaux :**
- Nom adresse
- Lignes d'adresse (1-3)
- Code postal, ville, pays

#### ProduitType
**Fichier :** `/src/Form/ProduitType.php`
**Usage :** Formulaire produit

**Champs principaux :**
- Désignation, référence
- Tarification (prix achat/vente, TVA)
- Stock et caractéristiques

### FormTypes spécialisés

#### DevisItemType
**Fichier :** `/src/Form/DevisItemType.php`
**Usage :** Ligne de devis

#### SecteurModerneType
**Fichier :** `/src/Form/SecteurModerneType.php`
**Usage :** Gestion secteurs géographiques

---

## Configuration

### Configuration Symfony

#### services.yaml
**Fichier :** `/config/services.yaml`
- Configuration des services
- Injection de dépendances
- Paramètres globaux

#### routes.yaml
**Fichier :** `/config/routes.yaml`
- Configuration des routes
- Préfixes et contraintes

#### security.yaml
**Fichier :** `/config/routes/security.yaml`
- Firewall Symfony
- Providers d'authentification
- Contrôle d'accès

### Base de données

#### Migrations
**Répertoire :** `/migrations/`
- Migrations Doctrine pour l'évolution de la BDD
- Plus de 80 migrations pour traçabilité complète

#### Fixtures
**Fichier :** `/src/DataFixtures/AppFixtures.php`
- Données de test et démonstration

### Configuration applicative

#### Bundles
**Fichier :** `/config/bundles.php`
- Bundles Symfony activés
- Configuration des composants

#### Paramètres
- Configuration multi-tenant
- Paramètres par société
- Variables d'environnement

---

## Informations techniques

### Versions et dépendances

- **Symfony :** 6.x
- **Doctrine ORM :** 2.x
- **PHP :** 8.1+
- **PostgreSQL :** 13+
- **Bootstrap :** 5.1.3
- **Font Awesome :** 6.0.0

### Performance et cache

- Cache Symfony intégré
- Cache dashboard (5 minutes)
- Optimisations requêtes Doctrine
- Lazy loading des relations

### Sécurité

- Authentification Google OAuth2
- Gestion des rôles et permissions
- Contrôle d'accès par société
- Validation CSRF
- Audit trail complet

### APIs et intégrations

- API REST interne
- Intégration Gmail
- Google Calendar
- Export PDF (DomPDF)
- Factur-X (factures électroniques)

---

## Conclusion

Cette documentation présente l'architecture complète de l'application TechnoProd ERP. L'application offre une solution complète de gestion commerciale avec des fonctionnalités avancées de CRM, workflow commercial, gestion multi-tenant et secteurs géographiques.

L'architecture modulaire basée sur Symfony permet une maintenabilité élevée et des évolutions futures facilitées. Le système de logging et d'audit garantit une traçabilité complète des opérations.

Pour toute question technique ou évolution, se référer au code source et aux commentaires dans les fichiers mentionnés.

---

*Documentation générée automatiquement le 22 septembre 2025*