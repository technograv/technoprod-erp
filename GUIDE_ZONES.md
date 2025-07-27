# Guide d'utilisation des Zones TechnoProd

## 🎉 Nouvelle Architecture des Secteurs

Votre idée d'avoir une base de données centralisée des codes postaux a été implémentée !

## 📊 Structure

### 1. **Entité Zone**
- Code postal (ex: 31000)
- Ville (ex: Toulouse)
- Département (ex: Haute-Garonne)
- Région (ex: Occitanie)
- Coordonnées GPS (latitude/longitude) pour la cartographie future

### 2. **Relation Secteur ↔ Zones**
- Relation Many-to-Many
- Un secteur peut contenir plusieurs zones
- Une zone peut appartenir à plusieurs secteurs

## 🚀 Utilisation

### **Étape 1 : Importer les codes postaux**
```bash
php bin/console import:zones zones_exemple.csv
```

Le fichier CSV doit avoir le format :
```csv
code_postal,ville,departement,region,latitude,longitude
31000,Toulouse,Haute-Garonne,Occitanie,43.6047,1.4442
```

### **Étape 2 : Créer des secteurs**
1. Aller sur `/secteur/new`
2. Remplir le nom du secteur
3. Choisir un commercial
4. Sélectionner une couleur
5. **Sélectionner les zones** dans la liste déroulante multiple

### **Étape 3 : Gérer les zones**
- Consulter : `/zone/`
- Ajouter manuellement : `/zone/new`
- Modifier : `/zone/{id}/edit`

## 🎨 Avantages de cette architecture

✅ **Base centralisée** : Tous les codes postaux en un endroit  
✅ **Import CSV** : Facile d'importer de gros volumes  
✅ **Sélection multiple** : Interface intuitive pour les secteurs  
✅ **Réutilisabilité** : Les zones peuvent être partagées entre secteurs  
✅ **Évolutif** : Prêt pour la cartographie avec les coordonnées GPS  
✅ **Données enrichies** : Département, région inclus  

## 🗂️ Fichiers créés/modifiés

- `src/Entity/Zone.php` - Nouvelle entité
- `src/Entity/Secteur.php` - Relation Many-to-Many ajoutée
- `src/Command/ImportZonesCommand.php` - Import CSV
- `src/Controller/ZoneController.php` - CRUD zones
- `src/Form/SecteurType.php` - Sélection multiple zones
- `zones_exemple.csv` - Données d'exemple

## 📍 Prochaines étapes possibles

1. **Cartographie** : Utiliser les coordonnées GPS pour afficher les secteurs sur une carte
2. **Import automatique** : Programmer l'import depuis l'API de La Poste
3. **Recherche avancée** : Filtrer par département, région
4. **Analytics** : Statistiques par zone/secteur

## 🔧 Commandes utiles

```bash
# Importer des zones
php bin/console import:zones fichier.csv --limit=100

# Effacer et réimporter
php bin/console import:zones fichier.csv --clear

# Voir les zones importées
php bin/console doctrine:query:sql "SELECT COUNT(*) FROM zone"
```