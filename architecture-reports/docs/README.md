# 📊 Documentation Architecture TechnoProd

Générée le: lun. 22 sept. 2025 10:42:00 CEST

## 📁 Fichiers disponibles

- **technoprod-architecture.json** : Données structurées de l'architecture
- **technoprod-architecture-report.md** : Rapport d'analyse détaillé
- **technoprod-architecture.dot** : Fichier source GraphViz
- **technoprod-architecture.png/.svg** : Diagrammes visuels (si GraphViz installé)
- **dependencies-graph.png/.svg** : Graphiques des dépendances Deptrac
- **deptrac_*.log** : Logs d'analyse des violations architecturales

## 🚀 Usage

```bash
# Analyse complète
./analyze-architecture.sh all

# Analyse spécifique
./analyze-architecture.sh arch    # Architecture uniquement
./analyze-architecture.sh deps    # Dépendances uniquement
```

## 📊 Résumé TechnoProd

Votre application TechnoProd contient :
- 61 entités Doctrine
- 26 controllers web + 15 controllers admin
- 44 services métier
- 61 repositories
- 14 formulaires Symfony
- 302 routes configurées

## 🏗️ Architecture

L'application suit une architecture classique Symfony avec :
- **Entités** : Modèle de données (Domain Layer)
- **Repositories** : Accès aux données (Data Layer) 
- **Services** : Logique métier (Business Layer)
- **Controllers** : Interface utilisateur (Presentation Layer)
- **Forms** : Gestion des formulaires
