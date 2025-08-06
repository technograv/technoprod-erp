# 🗺️ SYSTÈME D'ADMINISTRATION DES SECTEURS COMMERCIAUX

## Vue d'ensemble

Le système d'administration des secteurs commerciaux de TechnoProd ERP permet la gestion complète des territoires commerciaux avec une interface cartographique avancée et un système d'exclusions géographiques automatiques.

## 🎯 Fonctionnalités principales

### 1. Gestion des secteurs commerciaux
- **Création/modification** : Interface modale complète avec validation
- **Attribution géographique** : Assign zones par hiérarchie française (Région > Département > EPCI > Code postal > Commune)
- **Visualisation cartographique** : Frontières réelles via API officielle française
- **Contrôles avancés** : Afficher/masquer, centrer, zoom intelligent

### 2. Système d'exclusions géographiques automatiques
- **Règles hiérarchiques** : Gestion automatique des conflits entre zones
- **Exclusions bidirectionnelles** : Directes et inverses selon priorités
- **Cas spéciaux codes postaux** : Gestion chevauchement multi-EPCIs
- **Base de données** : 81+ exclusions automatiques fonctionnelles

### 3. Interface cartographique optimisée
- **Frontières réelles** : API officielle française pour tous types d'entités
- **Anti-doublons** : Évite superposition de communes
- **InfoWindows complètes** : Détails complets au clic
- **Performance** : Cache intelligent des géométries

## 🔧 Architecture technique

### Backend (Symfony 7 + PostgreSQL)
- **AdminController.php** : 13 routes REST + 8 fonctions d'exclusion géographique
- **SecteurController.php** : CRUD secteurs + nettoyage exclusions
- **Services géographiques** : API frontières, cache géométries, services EPCI
- **Entités** : AttributionSecteur, ExclusionSecteur, DivisionAdministrative

### Frontend (JavaScript + Google Maps API)
- **6 fonctions d'affichage** spécialisées par type géographique
- **Système anti-doublons** automatique
- **Interface carte** : Contrôles avancés + InfoWindows dynamiques
- **Performance** : Cache client + optimisations rendu

### Base de données
- **12 migrations** : Structure complète avec contraintes et relations
- **Données géographiques** : Base officielle française complète
- **Exclusions** : Gestion automatique des conflits géographiques

## 📊 APIs disponibles

### Routes principales
- `GET /admin/secteurs/all-geo-data` : Données géographiques tous secteurs
- `POST /secteur/attribution/create` : Création nouvelle attribution
- `DELETE /secteur/attribution/{id}` : Suppression attribution
- `GET /admin/code-postal/{code}/boundaries` : Frontières code postal
- `GET /admin/commune/{codeInsee}/geometry` : Géométrie commune

### Services
- **GeographicBoundariesService** : API frontières tous types
- **CommuneGeometryCacheService** : Cache local intelligent
- **EpciBoundariesService** : Service frontières EPCIs

## 🚀 Utilisation

### Interface d'administration
1. Accéder à `/admin/` > Onglet "Secteurs"
2. Créer/modifier secteurs via modales
3. Ajouter zones géographiques avec autocomplétion
4. Visualiser sur carte avec contrôles avancés

### Gestion des exclusions
Les exclusions sont **automatiques** selon la hiérarchie :
- Commune ajoutée → Exclue des EPCIs/départements/régions des autres secteurs
- Code postal ajouté → Toutes ses communes exclues des EPCIs concernés
- EPCI ajouté → Communes déjà attribuées ailleurs automatiquement exclues

### Robustesse
- **Attribution garantie** : Créée même si exclusions échouent
- **Gestion d'erreurs** : Messages informatifs et fallbacks
- **Performance** : Cache et optimisations automatiques

## 🔍 Données de test

Le système inclut des données de test complètes :
- **8 secteurs** configurés avec couverture géographique
- **Code postal 31160** : Exemple avec 27 communes sur 3 EPCIs
- **81 exclusions** automatiques fonctionnelles
- **Base géographique** : Données officielles françaises

## 📝 Maintenance

### Logs et debug
- Logs détaillés dans `/var/log/` pour exclusions
- Mode debug via console navigateur
- API de diagnostic : `/admin/debug/attributions`

### Extensions futures
- Interface préparée pour nouveaux types géographiques
- Architecture extensible pour autres pays
- Cache optimisé pour montée en charge

---

*Système développé avec Claude Code - Production ready*