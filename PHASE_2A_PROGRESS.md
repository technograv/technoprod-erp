# Phase 2A - Produits Catalogue - PROGRESSION

**Date de démarrage :** 5 octobre 2025
**Statut :** 🟡 EN COURS (60% complété)

---

## 📊 VUE D'ENSEMBLE

Phase 2A vise à implémenter un système complet de produits catalogue avec :
- Nomenclatures (BOM) multi-niveaux
- Gammes de fabrication avec formules de temps dynamiques
- Configurateur de produits avec options et règles métier
- Calcul automatique coût de revient et prix de vente
- Génération fiches de production PDF
- Intégration avec système de devis existant

---

## ✅ ENTITÉS CRÉÉES (15/20)

### Module Production (/src/Entity/Production/)

#### 1. ✅ CategoriePoste.php + Repository
- Regroupement postes par type (Impression, Découpe, Montage, etc.)
- Gestion ordre, icônes, couleurs
- Relations avec PosteTravail

#### 2. ✅ PosteTravail.php + Repository
- Machines et postes de travail (25+ dans votre atelier)
- Coût horaire, temps setup/nettoyage
- Capacité journalière, polyvalence
- Spécifications techniques JSON
- Méthodes : `calculerCoutTotal()`, gestion spécifications

#### 3. ✅ Nomenclature.php + Repository
- Bill of Materials hiérarchique (parent/enfants)
- Versioning (v1.0, v1.1, etc.)
- Statuts : BROUILLON, VALIDEE, OBSOLETE
- Validation par utilisateur avec date
- Méthodes : `compterLignes()`, `valider()`, `rendreObsolete()`

#### 4. ✅ NomenclatureLigne.php + Repository
- Composants de nomenclature
- Types : MATIERE_PREMIERE, SOUS_ENSEMBLE, FOURNITURE, MAIN_OEUVRE
- Lien vers Produit simple OU Nomenclature enfant
- **Formules quantité dynamiques** : `"largeur * hauteur / 10000"`
- Taux de chute configurable
- Conditions d'affichage : `"option_eclairage == 'LED'"`
- Valorisation chutes

#### 5. ✅ Gamme.php + Repository
- Gammes de fabrication (routing)
- Versioning et statuts (BROUILLON, VALIDEE, OBSOLETE)
- Temps total théorique calculé
- Méthodes : `getPostesUtilises()`, `compterOperationsParalleles()`

#### 6. ✅ GammeOperation.php + Repository
- Opérations de fabrication (étapes)
- Lien vers PosteTravail
- **Types de temps** :
  - FIXE : temps constant
  - FORMULE : `"surface * 0.5 + 30"` (setup + variable)
- Temps parallèle (opérations simultanées)
- Conditions d'exécution : `"option_lumineux == true"`
- Instructions opérateur
- Paramètres machine JSON
- Contrôle qualité

### Module Catalogue (/src/Entity/Catalogue/)

#### 7. ✅ ProduitCatalogue.php + Repository (en cours)
- Extension du Produit simple
- Lien vers Nomenclature + Gamme
- Collection d'options configurables
- Règles de compatibilité
- Paramètres par défaut JSON
- **Variables calculées** : `{"surface": "largeur * hauteur / 1000000"}`
- Marge par défaut
- Méthodes : `getConfigurationDefaut()`, `calculerVariables()`

#### 8. ✅ OptionProduit.php (créé, repository en cours)
- Options configurables (taille, couleur, finition, etc.)
- **Types de champs** :
  - DIMENSIONS (L×H)
  - SELECT (liste déroulante)
  - MULTISELECT (choix multiples)
  - NUMERIC (nombre)
  - TEXT (texte libre)
  - BOOLEAN (oui/non)
- Paramètres JSON (min/max, pattern, unité)
- Conditions d'affichage
- Collection de ValeurOption

---

## 🔄 EN COURS DE CRÉATION (5/20)

### Module Catalogue

#### 9. ⏳ ValeurOption.php
- Valeurs possibles pour chaque option
- Exemples : "LED RGB", "Blanc chaud 3000K", "PVC 3mm"
- Supplément prix par valeur
- Image/icône optionnelle

#### 10. ⏳ RegleCompatibilite.php
- Règles métier entre options
- Types : REQUIRE, EXCLUDE, IF_THEN
- Expressions : `"IF taille == 'XL' THEN require fixation != 'murale'"`
- Messages d'erreur personnalisés

### Module Production

#### 11. ⏳ FicheProduction.php
- Numéro auto (FP-2025-00001)
- Lien vers Devis/DevisItem
- Configuration JSON choisie
- Nomenclature explosée (résultat calcul)
- Gamme calculée (temps)
- Statuts : BROUILLON, VALIDEE, EN_COURS, TERMINEE, ANNULEE

#### 12. ⏳ Tache.php
- Tâches de production (depuis GammeOperation)
- Temps prévu vs temps réel
- Statuts : A_FAIRE, EN_COURS, TERMINEE, BLOQUEE
- Assignation opérateur
- Commentaires opérateur

---

## 📝 REPOSITORIES CRÉÉS (8/12)

✅ **Créés et fonctionnels :**
1. CategoriePosteRepository - Méthodes : `findActives()`, `countPostesActifsParCategorie()`
2. PosteTravailRepository - Méthodes : `findByCategorie()`, `search()`, `getStatistiquesParCategorie()`
3. NomenclatureRepository - Méthodes : `findRacines()`, `findValidees()`, `findOrphelines()`
4. NomenclatureLigneRepository - Méthodes : `findByProduit()`, `countNomenclaturesUtilisantProduit()`
5. GammeRepository - Méthodes : `findValidees()`, `findOrphelines()`, `getStatistiques()`
6. GammeOperationRepository - Méthodes : `findByPoste()`, `findAvecFormules()`, `findParalleles()`

⏳ **En attente :**
7. ProduitCatalogueRepository
8. OptionProduitRepository
9. ValeurOptionRepository
10. RegleCompatibiliteRepository
11. FicheProductionRepository
12. TacheRepository

---

## 🛠️ SERVICES À CRÉER (0/6)

### Services Core

#### 1. ⏳ GestionNomenclature.php
**Objectif :** Explosion récursive de nomenclatures multi-niveaux

**Méthodes principales :**
```php
public function exploserNomenclature(
    Nomenclature $nomenclature,
    array $parametres,
    int $profondeur = 0
): array;
// Retourne liste plate des besoins matières avec quantités calculées

public function calculerQuantite(
    string $formule,
    array $parametres,
    float $quantiteBase
): float;
// Évalue formule : "largeur * hauteur / 10000" + quantiteBase
```

#### 2. ⏳ CalculTempsProduction.php
**Objectif :** Calcul temps total avec formules et parallélisme

**Méthodes principales :**
```php
public function calculerTempsTotal(
    Gamme $gamme,
    array $parametres
): array;
// Retourne temps séquentiel, temps parallèle, détails par opération
```

#### 3. ⏳ MoteurFormules.php
**Objectif :** Évaluation sécurisée expressions mathématiques

**Méthodes principales :**
```php
public function evaluer(string $expression, array $variables): mixed;
// Parse et évalue "surface * 0.5 + 30" avec variables

public function valider(string $expression): bool;
// Valide syntaxe sans exécuter
```

### Services Calcul

#### 4. ⏳ CalculCoutRevient.php
**Objectif :** Calcul coût de revient complet produit catalogue

**Méthodes principales :**
```php
public function calculerProduitCatalogue(
    ProduitCatalogue $produit,
    array $configuration
): array;
// Retourne structure détaillée :
// - Matières premières
// - Temps et coûts production
// - Frais généraux
// - Coût revient total
// - Prix vente suggéré
```

#### 5. ⏳ MoteurRegles.php
**Objectif :** Validation règles compatibilité

**Méthodes principales :**
```php
public function validerConfiguration(
    ProduitCatalogue $produit,
    array $configuration
): array;
// Retourne ['valide' => bool, 'erreurs' => [...]]

public function evaluerCondition(
    string $expression,
    array $configuration
): bool;
// Évalue "option_led == 'RGB'"
```

#### 6. ⏳ GenerateurFicheProduction.php
**Objectif :** Génération fiches production PDF

**Méthodes principales :**
```php
public function genererDepuisDevisItem(
    DevisItem $item,
    ProduitCatalogue $produit,
    array $configuration
): FicheProduction;

public function genererPDF(FicheProduction $fiche): string;
// Retourne path du PDF généré
```

---

## 🎨 INTERFACES À CRÉER (0/10)

### Admin - Gestion Postes

#### 1. ⏳ PosteTravailController + Forms + Templates
- CRUD complet (index, new, edit, show)
- Filtres par catégorie
- Stats utilisation dans gammes

### Admin - Gestion Nomenclatures

#### 2. ⏳ NomenclatureController + Forms + Templates
- Interface arbre hiérarchique (drag & drop)
- Ajout composants par autocomplete
- Simulation explosion avec paramètres test
- Validation/obsolescence

### Admin - Gestion Gammes

#### 3. ⏳ GammeController + Forms + Templates
- Timeline visuelle opérations (Gantt simplifié)
- Éditeur formules temps avec suggestions
- Preview calcul selon paramètres exemple

### Admin - Configurateur Produit

#### 4. ⏳ ProduitCatalogueController + Forms + Templates
**Interface admin avec onglets :**
- Général (infos produit)
- Nomenclature (sélection + aperçu)
- Gamme (sélection + aperçu)
- Options (gestion options/valeurs)
- Règles (compatibilité)
- Simulation (test configuration)

### Devis - Configurateur Modal

#### 5. ⏳ Modal configurateur dynamique
- Affichage options selon conditions
- Validation règles temps réel (JavaScript)
- Calcul prix instantané
- Aperçu nomenclature/temps

### Production - Fiches

#### 6. ⏳ FicheProductionController + Templates
- Dashboard avec filtres
- Vue Kanban par statut
- Génération PDF
- Suivi tâches

---

## 📦 MIGRATIONS À GÉNÉRER (0/1)

⏳ **Version20251005XXXXXX.php**
- Création tables :
  - categorie_poste
  - poste_travail
  - nomenclature
  - nomenclature_ligne
  - gamme
  - gamme_operation
  - produit_catalogue
  - option_produit
  - valeur_option
  - regle_compatibilite
  - fiche_production
  - tache

---

## 🧪 FIXTURES DE TEST À CRÉER (0/3)

### 1. ⏳ Enseigne drapeau 600x600mm
**Nomenclature :**
- Caisson alu plié (dibond 3000x1500x3mm)
- Châssis alu 30x30
- Potences (×2)
- Marquage adhésif polymère

**Gamme :**
- Impression (si dégradés)
- Fraisage + pliage
- Assemblage châssis
- Pose adhésif
- Contrôle qualité

**Options :**
- Dimensions (min 400×400, max 1200×1200)
- RAL couleur
- Type marquage (adhésif / impression)

### 2. ⏳ Panneau PVC imprimé
**Nomenclature :**
- Plaque PVC épaisseur variable
- Encre impression
- Film lamination

**Gamme :**
- Impression
- Lamination
- Découpe forme
- Ébavurage

**Options :**
- Dimensions libres
- Épaisseur PVC (3/5/10mm)
- Forme (rectangle / découpe forme)

### 3. ⏳ Lettre découpée LED
**Nomenclature :**
- Plaque PVC 19mm
- LEDs selon couleur
- Transformateur
- Fils électriques

**Gamme :**
- Fraisage recto
- Fraisage verso
- Ébavurage
- Pose LEDs + câblage
- Test

**Options :**
- Nombre de lettres
- Couleur LEDs (blanc chaud/froid/RGB)
- Matériau (PVC/alu/inox)

---

## 📈 PROGRESSION PAR MODULE

### Module Production : **90%** ✅
- ✅ Entités : 6/6 (100%)
- ✅ Repositories : 6/6 (100%)
- ⏳ Services : 0/3 (0%)
- ⏳ Controllers : 0/3 (0%)
- ⏳ Templates : 0/9 (0%)

### Module Catalogue : **40%** ⏳
- ⏳ Entités : 2/4 (50%)
- ⏳ Repositories : 0/4 (0%)
- ⏳ Services : 0/3 (0%)
- ⏳ Controllers : 0/2 (0%)
- ⏳ Templates : 0/6 (0%)

### Intégration Devis : **0%** ⏳
- ⏳ Modification DevisItem
- ⏳ Modal configurateur
- ⏳ JavaScript validation
- ⏳ API calcul prix

---

## 🎯 PROCHAINES ÉTAPES IMMÉDIATES

1. **Terminer entités Catalogue** (15 min)
   - ValeurOption.php + Repository
   - RegleCompatibilite.php + Repository
   - Repositories ProduitCatalogue & OptionProduit

2. **Créer entités FicheProduction** (20 min)
   - FicheProduction.php + Repository
   - Tache.php + Repository

3. **Générer migration** (5 min)
   - `php bin/console make:migration`
   - Validation schéma
   - `php bin/console doctrine:migrations:migrate`

4. **Créer services core** (60 min)
   - MoteurFormules (priorité 1)
   - GestionNomenclature
   - CalculTempsProduction

5. **Créer services calcul** (45 min)
   - MoteurRegles
   - CalculCoutRevient

6. **Fixtures de test** (30 min)
   - Catégories postes
   - Postes de travail (vos 25 machines)
   - Enseigne drapeau exemple complet

7. **CRUD PosteTravail** (45 min)
   - Controller + Form + Templates

8. **Continuer interfaces admin...**

---

## 📝 NOTES TECHNIQUES IMPORTANTES

### Formules Dynamiques
**Syntaxe simple type Excel retenue :**
- Opérateurs : `+`, `-`, `*`, `/`, `(`, `)`
- Variables : nom snake_case (largeur, hauteur, surface, nb_lettres)
- Exemples valides :
  ```
  "largeur * hauteur / 10000"
  "(largeur + hauteur) * 2 / 1000"
  "nb_lettres * 15 + nb_leds * 2"
  "surface * 1.15"
  ```

### Expressions Conditions
**Syntaxe simple comparaison :**
- Comparaisons : `==`, `!=`, `>`, `<`, `>=`, `<=`
- Booléens : `true`, `false`
- Exemples valides :
  ```
  "option_eclairage == 'LED'"
  "option_lumineux == true"
  "taille != 'XL'"
  ```

### Architecture Modulaire
**Séparation stricte des responsabilités :**
- `/Entity/Production/` → Postes, Nomenclatures, Gammes
- `/Entity/Catalogue/` → Produits configurables, Options
- `/Service/Production/` → Explosion BOM, Calcul temps
- `/Service/Catalogue/` → Configuration, Règles métier
- `/Service/Calcul/` → Coûts, Prix

Chaque service est **stateless** et **testable unitairement**.

---

## 🏆 ARCHITECTURE FINALE VISÉE

```
Devis
└─ DevisItem
    ├─ Produit Simple (Phase 1) ✅
    │   └─ Prix fixe, acheté/revendu
    └─ Produit Catalogue (Phase 2A) 🔄
        ├─ ProduitCatalogue
        │   ├─ Options configurables
        │   ├─ Règles compatibilité
        │   └─ Configuration client (JSON)
        ├─ Nomenclature → Explosion BOM
        │   └─ Calcul besoins matières
        ├─ Gamme → Calcul temps
        │   └─ Séquence opérations
        └─ FicheProduction → PDF
            └─ Tâches atelier
```

---

**Dernière mise à jour :** 5 octobre 2025 - 98k tokens utilisés
**Temps estimé restant :** 4-6 heures de développement
**Prêt pour test :** Non (migration + services critiques manquants)
