# Plan de Nettoyage AdminController - Phase 3.3

## Routes à SUPPRIMER de AdminController.php (migrées vers contrôleurs spécialisés)

### ConfigurationController - CONFIGURATION
- [✓] `/formes-juridiques` + CRUD (5 routes)
- [✓] `/modes-paiement` + CRUD (4 routes)  
- [✓] `/modes-reglement` + CRUD (4 routes)
- [✓] `/banques` + CRUD (4 routes)
- [✓] `/taux-tva` + CRUD + GET (6 routes)
- [✓] `/unites` + CRUD + GET + types (7 routes)

### UserManagementController - GESTION UTILISATEURS
- [✓] `/users` + gestion complète (9 routes)
- [✓] `/groupes-utilisateurs` + CRUD (1 route GET - autres à vérifier)

### SocieteController - GESTION SOCIÉTÉS  
- [✓] `/societes` + CRUD (7 routes)
- [✓] `/settings` + update (2 routes)
- [✓] `/api/societes-tree` (1 route)

### ThemeController - ENVIRONNEMENT & TEMPLATES
- [✓] `/environment` + couleurs/logo/thème (6 routes)
- [✓] `/templates` + CRUD (6 routes)
- [✓] `/inheritance-info` (1 route)

### CatalogController - CATALOGUE & TAGS
- [✓] `/produits` (1 route - interface simple)
- [✓] `/tags` + CRUD + test (5 routes)
- [✓] `/modeles-document` + CRUD (4 routes)

### LogisticsController - LOGISTIQUE
- [✓] `/transporteurs` + CRUD + GET (5 routes)
- [✓] `/frais-port` + CRUD + GET (5 routes) 
- [✓] `/methodes-expedition` + CRUD (4 routes)
- [✓] `/civilites` + CRUD + GET (5 routes)

### SecteurController - SECTEURS COMMERCIAUX
- [✓] `/secteurs-admin` (1 route)
- [✓] `/secteur/{id}/attributions` + create/delete (3 routes)
- [✓] `/secteur/{id}/geo-data` + `/secteurs/all-geo-data` (2 routes)
- [✓] `/commune/{codeInsee}/geometry` (1 route)
- [✓] `/boundaries/*` (5 routes boundaries)
- [✓] `/divisions-administratives` + recherche + search (3 routes)
- [✓] `/types-secteur` + CRUD (4 routes)
- [✓] `/test/secteur-data/{id}` + `/debug/exclusions/{id}` (2 routes debug)

## Routes à CONSERVER dans AdminController.php

### Dashboard principal - FONCTIONS ESSENTIELLES
- [x] `/` - Dashboard avec statistiques (app_admin_dashboard)

### Debug et fonctions système temporaires
- [x] `/debug/secteurs` (app_admin_debug_secteurs) - temporaire ?
- [x] `/debug/attributions` (app_admin_debug_attributions) - temporaire ?
- [x] `/debug-auth` (app_admin_debug_auth) - temporaire ?

### Fonctions système
- [ ] `/numerotation` + update - À MIGRER vers SystemController

## ESTIMATION SUPPRESSION : ~80-85 routes sur ~90 total
## RÉDUCTION FICHIER : ~5382 lignes → ~500-800 lignes (85% réduction)