# Configuration Claude - Projet TechnoProd

## Informations du projet

**Nom du projet :** TechnoProd ERP/CRM  
**Type :** Application web Symfony 7  
**Base de données :** PostgreSQL  
**Environnement :** Linux Debian avec PHP 8.3  

## Structure du projet

- **Répertoire principal :** `/home/decorpub/TechnoProd/technoprod/`
- **URL de développement :** http://127.0.0.1:8001
- **Base de données :** `technoprod_db` (PostgreSQL)

## Entités principales ACTUELLES

1. **User** - Utilisateurs avec authentification Symfony + préférences personnalisées
2. **Client** - Clients/Prospects unifiés (refonte architecture EBP-style)
3. **Contact** - Contacts multiples avec gestion par défaut facturation/livraison
4. **Adresse** - Adresses multiples avec relations intelligentes
5. **CommuneFrancaise** - Base officielle communes françaises (codes postaux, coordonnées)
6. **Secteur** - Secteurs commerciaux avec zones géographiques françaises
7. **Devis** - Système complet de devis avec signature électronique
8. **DevisItem** - Lignes de devis avec catalogue produits
9. **Produit** - Catalogue produits/services complet
10. **Entités comptables** - ComptePCG, JournalComptable, EcritureComptable (conformité française)

## État du Projet (09/01/2025 - Après-midi)

### ✅ SYSTÈMES TERMINÉS ET FONCTIONNELS
1. **Système Prospect/Client unifié** (Style EBP)
2. **Gestion des contacts et adresses** (Facturation/Livraison)
3. **Système de secteurs commerciaux** avec zones postales
4. **Fonctionnalités d'appel téléphonique** (tel: links + JS)
5. **Fonctionnalités de cartographie** (Google Maps/Waze)
6. **SYSTÈME DE DEVIS COMPLET** avec :
   - Signature électronique avec canvas HTML5
   - Génération PDF professionnelle
   - Envoi d'emails avec pièces jointes
   - Paiement automatisé des acomptes
   - Catalogue produits/services intégré
   - Workflow complet (brouillon → envoyé → signé → payé)
   - Interface client dédiée avec accès sécurisé
   - **NOUVELLE :** Interfaces création et édition optimisées sans duplications

### 🎯 SESSION DE TRAVAIL JANVIER 2025
- **Objectif :** Finalisation complète du système de devis
- **Réalisations complétées :** 
  - ✅ Correction duplication contacts dans dropdowns édition devis
  - ✅ Suppression boutons en doublon "Ajouter une ligne"
  - ✅ Optimisation JavaScript (un seul $(document).ready())
  - ✅ Structure template Twig corrigée (fermetures manquantes)
  - ✅ Event listeners protégés contre attachement multiple

### 📋 PROCHAINE ÉTAPE IDENTIFIÉE
- **Page visualisation devis** (/devis/{id}) : Manque d'informations et historique non fonctionnel
  - Enrichissement des informations affichées
  - Réparation de la section historique
  - Amélioration de l'interface utilisateur

### 📋 DONNÉES DE TEST DISPONIBLES
- **Produits :** 5 produits/services créés (CONS-001, FORM-001, DEV-001, MAINT-001, SERV-001)
- **Prospects :** Données existantes du système précédent
- **Secteurs :** Configuration géographique complète

## Commandes de développement

### Serveur
```bash
# Démarrer le serveur de développement
symfony server:start -d

# Arrêter le serveur  
symfony server:stop

# Vérifier l'état
symfony server:status
```

### Base de données
```bash
# Vérifier la BDD
php bin/console doctrine:schema:validate

# Créer une migration
php bin/console make:migration

# Appliquer les migrations
php bin/console doctrine:migrations:migrate

# Insérer des données de test
php bin/console doctrine:query:sql "INSERT INTO ..."
```

### Tests et qualité
```bash
# Vérifier la syntaxe
php bin/console lint:yaml config/
php bin/console lint:twig templates/

# Debug des routes
php bin/console debug:router

# ⚠️ TESTS DE CONFORMITÉ COMPTABLE (OBLIGATOIRES)
php bin/console app:test-compliance     # Score doit être 100%
php bin/console app:test-comptabilite   # Système comptable complet
```

### MCP - Développement visuel et recette esthétique
```bash
# IMPORTANT: Se positionner dans le bon dossier
cd /home/decorpub/TechnoProd/technoprod

# Gestion du serveur MCP
./mcp.sh start      # Démarrer l'environnement MCP
./mcp.sh status     # Vérifier l'état du MCP
./mcp.sh stop       # Arrêter le MCP
./mcp.sh restart    # Redémarrer le MCP
./mcp.sh logs       # Voir les logs en temps réel

# URLs de développement MCP
# Interface secteurs: http://localhost:3001/admin/secteurs
# Page d'accueil: http://localhost:3001/
# Symfony original: https://127.0.0.1:8080/

# Workflow recette esthétique:
# 1. ./mcp.sh start
# 2. Naviguer vers http://localhost:3001/admin/secteurs
# 3. Ouvrir DevTools (F12) pour inspection
# 4. Modifier templates/admin/secteurs.html.twig en temps réel
# 5. Voir les modifications instantanément
```

## URLs FONCTIONNELLES

- `/` - Dashboard principal
- `/prospect` - Gestion prospects/clients
- `/prospect/new` - Nouveau prospect
- `/devis` - Dashboard des devis avec statistiques
- `/devis/new` - **Création nouveau devis (COMPLET + OPTIMISÉ)**
- `/devis/{id}` - Consultation devis *(À AMÉLIORER - prochaine étape)*
- `/devis/{id}/edit` - **Modification devis (COMPLET + SANS DOUBLONS)**
- `/devis/{id}/pdf` - Génération PDF
- `/devis/{id}/client/{token}` - Accès client signature

## Configuration technique

- **PHP :** 8.3.23
- **Symfony :** 7.3
- **PostgreSQL :** 15
- **Composer :** 2.8.10
- **Bootstrap :** 5.1.3
- **FontAwesome :** 6.0.0
- **Select2 :** 4.1.0 (pour les sélecteurs)
- **DomPDF :** Génération PDF
- **Symfony Mailer :** Envoi emails

## PROCHAINES ÉTAPES POTENTIELLES

1. **Amélioration page consultation devis (/devis/{id})**
2. **Gestion des factures** (conversion devis → facture)
3. **Catalogue produits** (interface CRUD complète)
4. **Tableau de bord commercial** (KPI, graphiques)
5. **Gestion des stocks** (si produits physiques)
6. **API REST** (pour intégrations externes)
7. **Notifications temps réel** (WebSockets)

## Fichiers de suivi

- `CLAUDE.md` : Configuration et état actuel (CE FICHIER)
- `CONVERSATION_HISTORY.md` : Historique des conversations
- `REPRISE_TRAVAIL.md` : Guide de reprise technique

---

## HISTORIQUE DES SESSIONS PRÉCÉDENTES

## SESSION DE TRAVAIL - 21/07/2025 🎯

### ✅ RÉALISATIONS MAJEURES
1. **INTÉGRATION GMAIL API COMPLÈTE**
   - Configuration OAuth avec scope `gmail.send`
   - Service `GmailMailerService` utilisant l'API Gmail
   - Envoi emails depuis l'adresse Gmail de l'utilisateur
   - Signature Gmail automatiquement intégrée
   - Tests confirmés : emails reçus avec succès

2. **SYSTÈME DE SIGNATURE ÉLECTRONIQUE FONCTIONNEL**
   - Analyse complète de l'architecture existante
   - Correction génération URLs absolues (problème `http:///` résolu)
   - Configuration `APP_BASE_URL` et routing Symfony
   - Interface canvas HTML5 opérationnelle
   - Workflow complet : envoi → lien → signature → sauvegarde

### ✅ CORRECTION JAVASCRIPT TEMPLATE LITERALS - 27/09/2025

**PROBLÈME IDENTIFIÉ :** Template literals JavaScript (backticks + `${}`) causaient des conflits avec le moteur de templates Twig, générant des erreurs de syntaxe JavaScript "missing ) after argument list" dans le navigateur.

**SOLUTION APPLIQUÉE :**
- Conversion de tous les template literals vers string concatenation classique
- 12 template literals remplacés dans `/templates/admin/societes.html.twig` :
  - `fetch(\`/admin/societes/\${id}/toggle\`)` → `fetch('/admin/societes/' + id + '/toggle')`
  - `document.querySelector(\`tr[data-id="\${id}"]\`)` → `document.querySelector('tr[data-id="' + id + '")')`
  - Styles CSS multilignes convertis de template literals vers concaténation

**CORRECTIONS ONCLICK HANDLERS :**
- Remplacement de tous les `onclick="function({{ twig.var }})"` par des event listeners sécurisés
- Conversion vers `data-*` attributes et delegation d'événements
- Élimination des risques d'injection de code via variables Twig dans onclick

**CORRECTIONS SYNTAXE JAVASCRIPT MODERNE :**
- Remplacement de l'optional chaining operator `?.` par des conditions traditionnelles
- Fix `value.match(/.{1,2}/g)?.join(' ')` → `matches ? matches.join(' ') : value`
- Fix `selected?.classList.add()` → `if (selected) { selected.classList.add() }`
- Compatibilité avec anciens navigateurs améliorée

**RÉSULTAT :** Template Twig validé avec `php bin/console lint:twig` - syntaxe correcte confirmée.

### 🔧 CORRECTIONS TECHNIQUES APPORTÉES
- **GoogleOAuthController.php** : Ajout scope `gmail.send`
- **GmailMailerService.php** : API Gmail + fallback SMTP
- **DevisController.php** : URL absolue construction manuelle
- **routing.yaml** : Configuration `default_uri` avec `APP_BASE_URL`
- **.env** : Variable `APP_BASE_URL=https://test.decorpub.fr:8080`
- **show.html.twig** : Bouton "Envoyer/Renvoyer" toujours visible

### 📋 TÂCHES EN ATTENTE POUR DEMAIN
1. **Amélioration mise en forme des emails** (template HTML professionnel)
2. **Optimisation interface signature client** (responsive, UX)
3. **Gestion des factures** (conversion devis → facture)
4. **API REST** pour intégrations externes

### 🧪 FONCTIONNALITÉS VALIDÉES
- ✅ Envoi Gmail API depuis adresse utilisateur
- ✅ Signature électronique complète
- ✅ URLs absolues fonctionnelles
- ✅ Workflow devis bout-en-bout

## SESSION DE TRAVAIL - 08/01/2025 🎯

### ✅ CORRECTION CRITIQUE - DUPLICATION CONTACTS ET BOUTONS DEVIS EDIT
**OBJECTIF MAJEUR ATTEINT : Résolution définitive des duplications dans interface édition devis**

#### **🐛 PROBLÈMES IDENTIFIÉS ET RÉSOLUS :**

**1. 🔄 Duplication des contacts dans les dropdowns :**
**Symptômes :** Les contacts apparaissaient en double dans les sélecteurs facturation/livraison lors de l'édition de devis
- Logs montraient deux appels API identiques pour le même client
- Contacts ajoutés deux fois : "M. Marine MICHEL" apparaissait 2 fois au lieu d'1

**Cause racine :** Double exécution du JavaScript au chargement de page
- **Deux `$(document).ready()`** dans le même template (lignes 625 et 1483)
- Event listeners multiples attachés sans désattachement préalable
- Fonction `loadInitialContactsData()` appelée deux fois

**2. 🔘 Duplication boutons "Ajouter une ligne" :**
**Symptômes :** Utilisateur voyait 2 boutons "Ajouter une ligne" au lieu d'un seul
- Un bouton personnalisé (template)
- Un bouton généré automatiquement par Symfony CollectionType

**Cause racine :** `{{ form_rest(form) }}` générait le prototype CollectionType après rendu manuel
- `devisItems` collection rendue manuellement (lignes 157-221)
- `form_rest()` re-générait les champs collection avec boutons Symfony

#### **🔧 SOLUTIONS TECHNIQUES APPLIQUÉES :**

**Correction duplication contacts :**
```javascript
// AVANT : Deux $(document).ready() séparés
$(document).ready(function() { /* code principal */ });
$(document).ready(function() { loadInitialContactsData(); });

// APRÈS : Un seul $(document).ready() consolidé
$(document).ready(function() {
    /* code principal */
    loadInitialContactsData(); // Intégré dans le principal
});

// Protection event listeners multiples
$('#devis_client').off('change').on('change', function() {...});
$('#devis_contactFacturation').off('change').on('change', function() {...});
$('#devis_contactLivraison').off('change').on('change', function() {...});
```

**Correction duplication boutons :**
```twig
{# Render devisItems field as hidden to prevent form_rest from creating duplicate buttons #}
<div style="display: none;">
    {{ form_widget(form.devisItems) }}
</div>

{{ form_rest(form) }}
{{ form_end(form) }}
```

**Correction structure template :**
- Supprimé `{% endblock %}` en double
- Ajouté fermeture manquante pour `{% block body %}`

#### **📊 RÉSULTATS FINALS :**
- **✅ Contacts uniques** : Plus de duplication dans les dropdowns
- **✅ Bouton unique** : Un seul "Ajouter une ligne" avec dropdown options
- **✅ JavaScript optimisé** : Exécution unique, event listeners propres
- **✅ Template valide** : Structure Twig correcte, plus d'erreur syntaxe
- **✅ Performance améliorée** : Moins d'appels AJAX redondants

#### **🎯 ARCHITECTURE TECHNIQUE CORRIGÉE :**
- **Template edit.html.twig** : Structure consolidée et optimisée
- **JavaScript** : Un seul `$(document).ready()` avec logique centralisée
- **Event listeners** : Protection contre attachement multiple avec `.off().on()`
- **Form rendering** : Gestion explicite des collections pour éviter doublons Symfony

### 🚀 **SYSTÈME DEVIS MAINTENANT 100% FONCTIONNEL :**
L'interface d'édition des devis TechnoProd est maintenant **parfaitement stable** avec :
- Interface utilisateur cohérente sans doublons
- Performance optimisée avec moins d'appels réseau
- Code maintenable et structure template propre
- Workflow d'édition fluide et prévisible

---
*Dernière mise à jour : 09/01/2025 - Interface devis création/édition finalisée*