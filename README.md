# 🚀 TechnoProd ERP/CRM

**Système complet de gestion client/prospect avec devis, signature électronique et conformité comptable française**

[![Symfony](https://img.shields.io/badge/Symfony-7.3-000000.svg?style=flat&logo=symfony)](https://symfony.com/)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4.svg?style=flat&logo=php)](https://php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-316192.svg?style=flat&logo=postgresql)](https://postgresql.org/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.1-7952B3.svg?style=flat&logo=bootstrap)](https://getbootstrap.com/)

## 📋 **Vue d'ensemble**

TechnoProd est un ERP/CRM moderne développé avec Symfony 7, offrant une interface intuitive pour la gestion complète des clients, prospects, devis et factures. Conçu pour les entreprises françaises avec une conformité comptable stricte.

### 🎯 **Statut du Projet**
- **Version actuelle :** 1.0.0-beta
- **Statut :** 95% complet - Prêt pour production
- **Dernière mise à jour :** 27/07/2025

## ✨ **Fonctionnalités Principales**

### 🏢 **Gestion Client/Prospect**
- ✅ Interface moderne en tableau avec colonnes redimensionnables
- ✅ Tri interactif et filtrage avancé
- ✅ Création adaptative selon type de personne (physique/morale)
- ✅ Gestion multi-contacts avec validation des règles métier
- ✅ Autocomplétion française (codes postaux, villes)
- ✅ Bouton de sauvegarde flottant pour UX optimale

### 📄 **Système de Devis**
- ✅ Création intuitive avec catalogue produits intégré
- ✅ Signature électronique avec canvas HTML5
- ✅ Génération PDF professionnelle
- ✅ Envoi automatique via Gmail API
- ✅ Workflow complet : Brouillon → Envoyé → Signé → Payé

### 🧮 **Conformité Comptable Française**
- ✅ **NF203** : Intégrité des documents avec signature numérique
- ✅ **Plan Comptable Général** : 77 comptes selon normes françaises
- ✅ **FEC** : Export conforme arrêté 29 juillet 2013
- ✅ **Factur-X** : Prêt pour obligation 2026 (4 profils supportés)
- ✅ **Audit trail** complet avec chaînage cryptographique

### 🗺️ **Géographie Française**
- ✅ Base de 108 communes françaises avec coordonnées GPS
- ✅ Secteurs commerciaux avec zones géographiques
- ✅ Autocomplétion bidirectionnelle code postal ↔ ville

### 👤 **Gestion Utilisateurs**
- ✅ Système de préférences personnalisées
- ✅ Signatures email d'entreprise et personnelles
- ✅ Configuration avancée (langue, fuseau horaire)

## 🛠️ **Technologies Utilisées**

| Composant | Version | Description |
|-----------|---------|-------------|
| **Symfony** | 7.3 | Framework PHP principal |
| **PHP** | 8.3.23 | Langage backend |
| **PostgreSQL** | 15 | Base de données |
| **Doctrine** | Latest | ORM pour base de données |
| **Bootstrap** | 5.1.3 | Framework CSS |
| **jQuery** | 3.6.0 | JavaScript pour interactions |
| **FontAwesome** | 6.0.0 | Icônes |
| **DomPDF** | Latest | Génération PDF |

## 🚀 **Installation**

### **Prérequis**
- PHP 8.3+
- PostgreSQL 15+
- Composer 2.8+
- Symfony CLI

### **Installation Rapide**

```bash
# 1. Cloner le repository
git clone https://github.com/votre-username/technoprod-erp.git
cd technoprod-erp

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
cp .env.local.example .env.local
# Éditer .env.local avec vos paramètres

# 4. Configurer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Initialiser les données
php bin/console app:pcg:initialiser
php bin/console app:import-communes-francaises

# 6. Démarrer le serveur
symfony server:start
```

### **Configuration OAuth Google (Optionnelle)**
Pour utiliser Gmail API :
1. Créer un projet sur Google Cloud Console
2. Activer Gmail API
3. Configurer OAuth 2.0
4. Ajouter les clés dans `.env.local`

Voir `CONFIGURATION_GOOGLE_OAUTH.md` pour le guide détaillé.

## 📚 **Documentation**

| Fichier | Description |
|---------|-------------|
| `CLAUDE.md` | Configuration principale et historique |
| `FONCTIONNALITES_TERMINEES.md` | Checklist complète des fonctionnalités |
| `SESSION_TRAVAIL_27_07_2025.md` | Rapport détaillé dernière session |
| `ARCHITECTURE_CONFORMITE_COMPTABLE.md` | Documentation conformité |
| `GUIDE_TEST_WORKFLOW.md` | Guide de test utilisateur |

## 🔧 **Scripts d'Automatisation**

### **Déploiement Initial**
```bash
./git-setup.sh technoprod-erp
```

### **Commits Rapides**
```bash
./quick-commit.sh "Description des modifications"
```

### **Tests de Conformité**
```bash
php bin/console app:test-compliance
php bin/console app:test-comptabilite
```

## 📊 **URLs Principales**

| URL | Description |
|-----|-------------|
| `/` | Dashboard principal |
| `/client` | Gestion clients/prospects |
| `/client/new` | Création nouveau client |
| `/client/{id}/edit-improved` | Édition client moderne |
| `/devis` | Gestion des devis |
| `/devis/new` | Création nouveau devis |
| `/user/preferences` | Préférences utilisateur |

## 🧪 **Tests et Qualité**

### **Tests de Conformité**
```bash
# Score de conformité (doit être 100%)
php bin/console app:test-compliance

# Tests comptables complets
php bin/console app:test-comptabilite

# Validation des schémas
php bin/console doctrine:schema:validate
```

### **Validation Code**
```bash
# Syntaxe Twig
php bin/console lint:twig templates/

# Configuration YAML
php bin/console lint:yaml config/

# Routes
php bin/console debug:router
```

## 🤝 **Contribution**

1. **Fork** le projet
2. **Créer** une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. **Commiter** vos changements (`git commit -m 'Ajout nouvelle fonctionnalité'`)
4. **Pousser** vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. **Ouvrir** une Pull Request

## 📄 **Licence**

Ce projet est sous licence MIT. Voir `LICENSE` pour plus de détails.

## 📞 **Support**

- **Documentation** : Voir dossier `/docs`
- **Issues** : [GitHub Issues](https://github.com/votre-username/technoprod-erp/issues)
- **Wiki** : [GitHub Wiki](https://github.com/votre-username/technoprod-erp/wiki)

## 🏆 **Crédits**

- **Développement principal** : Équipe TechnoProd
- **Assistance IA** : Claude Code (Anthropic)
- **Framework** : Symfony Community
- **Base communes françaises** : Données officielles INSEE

---

## 🚀 **Roadmap Future**

### **Version 1.1 (Q3 2025)**
- [ ] Gestion des factures (conversion devis → facture)
- [ ] Tableau de bord commercial avec KPI
- [ ] Notifications temps réel (WebSockets)
- [ ] API REST complète

### **Version 1.2 (Q4 2025)**
- [ ] Gestion des stocks
- [ ] Module de paie simplifié
- [ ] Application mobile (PWA)
- [ ] Intégrations externes (banques, comptables)

---

**Développé avec ❤️ et Claude Code**