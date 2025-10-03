# 🏗️ CHANTIER PRODUITS/SERVICES - TechnoProd

**Date de démarrage :** 03/10/2025
**Statut :** 🔴 EN ANALYSE
**Complexité :** ⚠️ TRÈS ÉLEVÉE - Refonte majeure

---

## 📋 CONTEXTE

### État actuel
- ✅ Système de devis fonctionnel avec **lignes libres** (saisie manuelle)
- ✅ Champs basiques : code, désignation, description, Qté, PU HT, remise %, TVA
- ❌ Pas de fiche produit structurée
- ❌ Pas de gestion prix d'achat/marge/marque
- ❌ Pas de gestion stocks
- ❌ Pas de ventilation comptable automatique
- ❌ Pas de gestion fournisseurs liés

### Objectifs du chantier
1. **Créer système de fiches produits complet** (remplace lignes libres)
2. **Intégrer gestion commerciale** (marge, marque, prix de revient)
3. **Préparer gestion stocks** (future fonctionnalité)
4. **Automatiser ventilation comptable** (conformité PCG)
5. **Gérer relations fournisseurs** (achats optimisés)
6. **Préparer e-commerce** (extensibilité future)

---

## 🎯 ÉTAPE 1 : FICHE PRODUIT STANDARD

### 📦 Onglet "Informations Générales"

#### Champs obligatoires
- **Code article** (string, unique, indexé) - Référence interne
- **Type** (enum: BIEN / SERVICE) - Distinction marchandise/prestation
- **Libellé** (string, 255 chars) - Nom commercial
- **Prix de vente HT** (decimal 10,4) - Tarif public
- **Taux de TVA** (relation → TauxTVA) - TVA applicable
- **Statut** (boolean: actif/inactif) - Produit vendable ou non

#### Champs optionnels
- **Famille** (relation → FamilleProduit, nullable) - Catégorie niveau 1
- **Sous-famille** (relation → SousFamilleProduit, nullable) - Catégorie niveau 2
- **Description courte** (text) - Résumé commercial
- **Description détaillée** (text) - Fiche technique complète
- **Quantité par défaut** (decimal 10,4, default: 1) - Qté suggérée devis
- **Unité de vente** (relation → Unite, nullable) - Ex: pièce, mètre, kg
- **Nombre de décimales prix** (integer, default: 2) - Précision affichage

#### Calculs automatiques (Gestion commerciale)
- **Prix d'achat** (decimal 10,4, nullable) - Coût fournisseur
- **Frais (%)** (decimal 5,2, default: 0) - Frais annexes transport/logistique
- **Prix de revient** (calculé: `prix_achat * (1 + frais/100)`) - Coût réel
- **Taux de marge (%)** (calculé: `(PV_HT - prix_revient) / prix_revient * 100`)
- **Taux de marque (%)** (calculé: `(PV_HT - prix_revient) / PV_HT * 100`)

#### Relation fournisseur (simplifié, détails dans onglet dédié)
- **Fournisseur principal** (relation → Fournisseur, nullable)

---

### 📊 Onglet "Gestion des Stocks" (PRÉPARATION FUTURE)

⚠️ **Fonctionnalité non implémentée immédiatement** - Structure BDD anticipée

#### Champs prévus
- **Gestion stock** (boolean, default: false) - Activation suivi
- **Stock actuel** (decimal 10,4, default: 0)
- **Stock minimum** (decimal 10,4, nullable) - Seuil alerte
- **Stock maximum** (decimal 10,4, nullable) - Seuil réapprovisionnement
- **Emplacement** (string, nullable) - Localisation entrepôt
- **Numéro de lot** (string, nullable)
- **Date péremption** (date, nullable)

#### Relations futures
- **MouvementStock** (1:N) - Historique entrées/sorties
- **Inventaire** (N:M) - Rattachement inventaires physiques

---

### 💰 Onglet "Comptabilité"

#### Ventilation automatique (conformité PCG)
- **Compte vente** (relation → ComptePCG, nullable) - Compte 7xxxxx
- **Compte achat** (relation → ComptePCG, nullable) - Compte 6xxxxx

#### Gestion avancée (selon destination)
- **Type destination** (enum: MARCHANDISE, PRODUIT_FINI, MATIERE_PREMIERE, EMBALLAGE, PRODUIT_INTERMEDIAIRE, AUTRE)
- **Compte variation stock** (relation → ComptePCG, nullable) - Comptes 603x, 713x
- **Compte stock** (relation → ComptePCG, nullable) - Comptes 31x, 32x, 35x, 37x

#### Règles métier comptables
```
MARCHANDISE:
- Achat: 607xxx (Achats de marchandises)
- Vente: 707xxx (Ventes de marchandises)
- Variation: 6037 (Variation stocks marchandises)
- Stock: 37 (Stocks de marchandises)

PRODUIT_FINI:
- Achat: N/A (production interne)
- Vente: 701xxx (Ventes de produits finis)
- Variation: 7135 (Variation stocks produits)
- Stock: 355 (Produits finis)

MATIERE_PREMIERE:
- Achat: 601xxx (Achats matières premières)
- Variation: 6031 (Variation stocks MP)
- Stock: 31 (Matières premières)

SERVICE:
- Achat: 604xxx / 606xxx
- Vente: 706xxx
- Pas de stock
```

---

### 🖼️ Onglet "Images"

#### Gestion bibliothèque photos
- **Image principale** (string, path) - Photo mise en avant
- **Images secondaires** (Collection → ProduitImage, 1:N) - Galerie
  - `produit_id` (FK)
  - `image_path` (string)
  - `ordre` (integer) - Tri drag & drop
  - `legende` (string, nullable)
  - `alt_text` (string, nullable) - SEO

#### Fonctionnalités prévues
- Upload multiple avec drag & drop
- Réorganisation par glisser-déposer (SortableJS)
- Compression automatique images (Intervention/Image ou similaire)
- Formats supportés : JPG, PNG, WebP

---

### 🔗 Onglet "Articles Liés"

#### Produits optionnels/complémentaires
- **ArticlesLies** (N:M → Produit) - Auto-relation
  - `produit_principal_id` (FK)
  - `produit_lie_id` (FK)
  - `type_relation` (enum: OPTIONNEL, COMPLEMENTAIRE, ALTERNATIF, PACK)
  - `ordre` (integer) - Ordre suggestion

#### Cas d'usage
- **OPTIONNEL** : "Avec ce produit, ajoutez..."
- **COMPLEMENTAIRE** : "Les clients ont aussi acheté..."
- **ALTERNATIF** : "Produit similaire moins cher/plus cher"
- **PACK** : "Composition bundle commercial"

---

### 🏭 Onglet "Fournisseurs"

#### Informations fournisseur détaillées
**Relation :** `ProduitFournisseur` (N:M entre Produit et Fournisseur)

Champs par association :
- **Fournisseur** (relation → Fournisseur)
- **Code fournisseur** (string) - Code société chez fournisseur
- **Nom fournisseur** (string) - Dénomination sociale
- **Référence produit fournisseur** (string) - SKU fournisseur
- **Prix vente conseillé (PVC)** (decimal 10,4, nullable)
- **Remise sur PVC (%)** (decimal 5,2, default: 0)
- **Prix achat public** (decimal 10,4)
- **Remise achat (%)** (decimal 5,2, default: 0)
- **Prix achat net HT** (calculé: `prix_achat_public * (1 - remise/100)`)
- **Unité d'achat** (relation → Unite, nullable)
- **Multiple de commande** (integer, default: 1) - Qté minimum commande
- **Délai livraison (jours)** (integer, nullable)
- **Code éco-contribution** (string, nullable) - DEEE, etc.
- **Priorité** (integer, default: 0) - Fournisseur préférentiel si plusieurs

#### Règles métier
- Un produit peut avoir **plusieurs fournisseurs**
- Le fournisseur avec `priorité` la plus élevée = fournisseur principal
- Si `prix_achat_net_HT` renseigné → utilisé pour calcul marge/marque

---

### 📝 Onglet "Notes"

#### Informations complémentaires
- **Notes internes** (text) - Visibles équipe uniquement
- **Notes techniques** (text) - Fiche technique détaillée
- **Observations commerciales** (text) - Argumentaire vente

---

### 🛒 Onglets futurs (E-commerce)

⚠️ **Non implémentés dans cette version** - Structure anticipée

Onglets prévus :
- **SEO** : meta_title, meta_description, slug URL
- **Web** : visible_en_ligne, mise_en_avant, nouveau, promo
- **Déclinaisons** : tailles, couleurs, variantes (ProductVariant)
- **Livraison** : poids, dimensions, frais port spécifiques
- **Stock web** : stock_web_reserve, stock_magasin_reserve

---

## ✅ DÉCISIONS VALIDÉES

### 1. Interface utilisateur
**✅ VALIDÉ : Onglets Bootstrap classiques**
- Navigation intuitive et familière
- S'intègre parfaitement avec l'existant
- Validation Symfony standard

### 2. Architecture entités
**✅ DÉCISIONS :**
- **Produit existant** : Enrichir l'entité actuelle (pas de nouvelle entité)
- **Fournisseur** : À créer (structure similaire à Client)
- **Unités** : Réutiliser entité Unite existante
- **TauxTVA** : Réutiliser entité TauxTVA existante
- **FamilleProduit / SousFamilleProduit** : À créer
- **ProduitFournisseur** : À créer (table pivot enrichie)
- **ArticleLie** : À créer (auto-relation)

### 3. Calculs prix/marge
**✅ COMPORTEMENT TYPE "REMISE GLOBALE DEVIS" :**
- Prix d'achat : **lecture seule** (non modifiable directement dans le calcul)
- Modification PV → recalcul automatique marge
- Modification marge → recalcul automatique PV
- Recalcul bidirectionnel en temps réel (JavaScript)

**Formules :**
```javascript
// Si user modifie PV :
marge = ((PV - PA) / PA) * 100

// Si user modifie marge :
PV = PA * (1 + marge/100)
```

### 4. Gestion stocks
**✅ IMPLÉMENTATION BASIQUE V1 :**
- Champs existants conservés : `stockQuantite`, `stockMinimum`, `gestionStock`
- Onglet "Stocks" simple avec champs basiques
- Module complet différé (mouvements, inventaires, etc.)

### 5. Compatibilité devis
**✅ REMPLACEMENT PROGRESSIF :**
- Les fiches produits **alimenteront** les lignes de devis
- L'entité `Produit` actuelle sera enrichie (pas de migration complexe)
- DevisItem continuera à fonctionner normalement
- Ajout autocomplete sur fiches produits dans création devis

---

## 📐 ARCHITECTURE TECHNIQUE PROPOSÉE

### Entité Produit (structure préliminaire)

```php
#[ORM\Entity]
class Produit
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $codeArticle;

    #[ORM\Column(length: 10)]
    private string $type; // BIEN | SERVICE

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionCourte = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionDetaillee = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $prixAchat = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $fraisPourcentage = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private string $prixVenteHT;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private string $quantiteDefaut = '1.0000';

    #[ORM\Column(type: 'integer')]
    private int $nombreDecimalesPrix = 2;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    // Relations
    #[ORM\ManyToOne(targetEntity: FamilleProduit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?FamilleProduit $famille = null;

    #[ORM\ManyToOne(targetEntity: SousFamilleProduit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SousFamilleProduit $sousFamille = null;

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Unite $uniteVente = null;

    #[ORM\ManyToOne(targetEntity: TauxTVA::class)]
    #[ORM\JoinColumn(nullable: false)]
    private TauxTVA $tauxTVA;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Fournisseur $fournisseurPrincipal = null;

    // Comptabilité
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeDestination = null;

    #[ORM\ManyToOne(targetEntity: ComptePCG::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ComptePCG $compteVente = null;

    #[ORM\ManyToOne(targetEntity: ComptePCG::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ComptePCG $compteAchat = null;

    // Collections
    #[ORM\OneToMany(targetEntity: ProduitImage::class, mappedBy: 'produit', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $images;

    #[ORM\OneToMany(targetEntity: ProduitFournisseur::class, mappedBy: 'produit', cascade: ['persist', 'remove'])]
    private Collection $fournisseurs;

    // Calculs (méthodes)
    public function getPrixRevient(): ?string
    {
        if ($this->prixAchat === null) return null;
        return bcmul($this->prixAchat, bcadd('1', bcdiv($this->fraisPourcentage, '100', 4), 4), 4);
    }

    public function getTauxMarge(): ?float
    {
        $pr = $this->getPrixRevient();
        if ($pr === null || $pr === '0') return null;
        return (float) bcmul(bcdiv(bcsub($this->prixVenteHT, $pr, 4), $pr, 4), '100', 2);
    }

    public function getTauxMarque(): ?float
    {
        $pr = $this->getPrixRevient();
        if ($pr === null || $this->prixVenteHT === '0') return null;
        return (float) bcmul(bcdiv(bcsub($this->prixVenteHT, $pr, 4), $this->prixVenteHT, 4), '100', 2);
    }
}
```

---

## 📅 PROCHAINES ÉTAPES

**Attente de tes réponses sur :**
1. ✅ Validation structure onglets (Option A recommandée)
2. ❓ Entité Fournisseur existe-t-elle ?
3. ✅ Validation calculs automatiques (Option A recommandée)
4. ❓ Niveau implémentation stocks (Plan B recommandé)
5. ❓ Compatibilité devis existants (Option A/C)

**Une fois validé, je pourrai :**
1. Créer toutes les entités Doctrine
2. Générer les migrations BDD
3. Créer les formulaires Symfony
4. Implémenter l'interface avec onglets
5. Développer les calculs automatiques
6. Créer les contrôleurs CRUD

---

## 🏭 ÉTAPE 2 : PRODUITS CATALOGUE (PRODUITS COMPLEXES)

### 🎯 CONTEXTE ET ENJEUX

**Définition :**
Les **produits catalogue** sont des produits complexes manufacturés, composés de :
- Plusieurs **matières premières** (produits simples)
- **Parcours machines** avec transformations
- **Règles de gestion** pour calculs automatiques
- **Nomenclatures** (BOM - Bill of Materials)

**Différence avec produits simples :**
```
PRODUIT SIMPLE (Étape 1)
→ Achat direct fournisseur
→ Revente avec marge
→ Pas de transformation

PRODUIT CATALOGUE (Étape 2)
→ Fabrication interne
→ Assemblage composants + transformation
→ Calcul coût de revient complexe
→ Planning de production
→ Fiches atelier
```

### 📋 BESOINS FONCTIONNELS IDENTIFIÉS

#### 1. Gestion des devis
✅ **ACQUIS :** Choix "ligne libre" vs "produit catalogue" lors ajout ligne devis

#### 2. Calcul automatique produits complexes
- **Composition** : Liste des matières premières nécessaires
- **Quantités dynamiques** : Selon dimensions/options choisies
- **Parcours machines** : Succession d'étapes de transformation
- **Temps machine** : Calcul temps par opération
- **Coûts** : Agrégation coûts matières + main d'œuvre + machine

#### 3. Production atelier
- **Fiches de production** : Documents pour opérateurs
- **Planning production** : Ordonnancement des commandes
- **Suivi avancement** : État de fabrication en temps réel
- **Gestion ressources** : Disponibilité machines/opérateurs

### 🤔 ANALYSE PRÉLIMINAIRE - COMPLEXITÉ

**⚠️ CETTE ÉTAPE EST UN SYSTÈME MES/MRP COMPLET**

Nous sommes face à un **système de gestion de production industrielle** qui comprend :

1. **PLM** (Product Lifecycle Management)
   - Nomenclatures (BOM)
   - Gammes de fabrication
   - Gestion versions produits

2. **MRP** (Material Requirements Planning)
   - Calcul besoins matières
   - Gestion stocks composants
   - Approvisionnements automatiques

3. **MES** (Manufacturing Execution System)
   - Ordonnancement production
   - Fiches de travail atelier
   - Suivi temps réel
   - Gestion ressources (machines, opérateurs)

4. **Costing** (Calcul coûts industriels)
   - Coût matières
   - Coût main d'œuvre
   - Coût machine (amortissement)
   - Coûts indirects

### 🎯 QUESTIONS STRATÉGIQUES AVANT DE CONTINUER

#### Question 1 : Type de production

**Quel est votre mode de fabrication principal ?**

**Option A : Production à la commande (Make-to-Order)**
```
Client commande → Fabrication déclenchée → Livraison
Exemple : Meubles sur-mesure, machines spéciales
```

**Option B : Production sur stock (Make-to-Stock)**
```
Fabrication en continu → Stock → Vente stock existant
Exemple : Produits standardisés, série
```

**Option C : Assemblage à la commande (Assemble-to-Order)**
```
Composants en stock → Commande → Assemblage final → Livraison
Exemple : Ordinateurs Dell, voitures options
```

**🔹 VOTRE CAS ?**

---

#### Question 2 : Complexité des nomenclatures

**Vos produits ont combien de niveaux de composition ?**

**Exemple simple (1 niveau) :**
```
PRODUIT FINI : Table
├─ Plateau bois (1x)
├─ Pieds métal (4x)
└─ Vis (16x)
```

**Exemple complexe (multi-niveaux) :**
```
PRODUIT FINI : Machine industrielle
├─ Module A (1x)
│   ├─ Sous-ensemble A1 (2x)
│   │   ├─ Pièce X (4x)
│   │   └─ Pièce Y (2x)
│   └─ Sous-ensemble A2 (1x)
├─ Module B (1x)
└─ Module C (2x)
```

**🔹 VOS PRODUITS :** Simple (1-2 niveaux) ou Complexe (3+ niveaux) ?

---

#### Question 3 : Variabilité des produits

**Vos produits catalogue sont-ils :**

**Option A : Standardisés**
```
Produit X = toujours même composition/fabrication
Pas de personnalisation
```

**Option B : Paramétrables**
```
Produit X avec options : taille, couleur, finition
Nomenclature change selon choix client
```

**Option C : Configurables complexes**
```
Configurateur produit avancé
Règles métier complexes
Incompatibilités entre options
Calculs dynamiques
```

**🔹 VOTRE CAS ?**

---

#### Question 4 : Machines et ressources

**Combien de types de machines différentes ?**
- Moins de 5 machines
- 5 à 20 machines
- Plus de 20 machines

**Les machines sont-elles :**
- Dédiées (1 machine = 1 opération spécifique)
- Polyvalentes (1 machine = plusieurs opérations possibles)

**Contraintes de planification :**
- Machines en parallèle (plusieurs produits simultanés) ?
- Temps de setup entre produits ?
- Maintenance préventive planifiée ?
- Compétences opérateurs spécifiques ?

**🔹 DÉCRIVEZ VOTRE ATELIER ?**

---

#### Question 5 : Données existantes

**Avez-vous déjà formalisé :**

✅ **Liste des matières premières utilisées ?**
- Combien de références différentes environ ?

✅ **Liste des machines/postes de travail ?**
- Noms et types ?

✅ **Gammes de fabrication types ?**
- Existe-t-il des documents papier/Excel décrivant les étapes ?

✅ **Temps standards par opération ?**
- Temps machine/main d'œuvre connus ?

✅ **Coûts horaires machines ?**
- Tarif horaire par machine ?

**🔹 QU'AVEZ-VOUS DÉJÀ ?**

---

### 💡 PROPOSITION DE STRATÉGIE

Vu la complexité, je propose une **approche itérative en 3 phases** :

#### 📦 PHASE 2A : NOMENCLATURES & CALCUL COÛTS (1-2 mois)
**Objectif :** Permettre création produits catalogue avec calcul prix automatique

**Livrables :**
- Création nomenclatures (BOM) multi-niveaux
- Gestion gammes de fabrication (routings)
- Calcul coût de revient automatique
- Intégration dans devis (sélection produit catalogue)
- Calcul prix vente avec marge

**Entités :**
- `ProduitCatalogue` (extends Produit)
- `Nomenclature` (BOM)
- `NomenclatureLigne` (composants)
- `Gamme` (routing)
- `GammeOperation` (étapes fabrication)
- `PosteTravail` (machines/centres de charge)

---

#### 🏭 PHASE 2B : FICHES DE PRODUCTION (1 mois)
**Objectif :** Générer documents pour l'atelier

**Livrables :**
- Génération fiches de production PDF
- Liste matières à préparer (picking list)
- Instructions opératoires par poste
- Suivi simple (fait/pas fait)

**Entités :**
- `OrdreFabrication` (OF)
- `OFOperation` (suivi étapes)

---

#### 📅 PHASE 2C : PLANNING PRODUCTION (1-2 mois)
**Objectif :** Ordonnancement et pilotage atelier

**Livrables :**
- Planification ordres de fabrication
- Gantt de production
- Gestion capacité machines
- Alertes retards/conflits ressources

**Entités :**
- `Planning`
- `Reservation` (machines/opérateurs)
- `Jalonnement` (calcul dates)

---

### 🎯 PROCHAINE ÉTAPE RECOMMANDÉE

**AVANT DE CODER QUOI QUE CE SOIT**, j'ai besoin de :

1. **Réponses aux 5 questions stratégiques** ci-dessus
2. **Un exemple concret** de produit catalogue que vous fabriquez :
   - Nom du produit
   - Liste des matières premières
   - Étapes de fabrication
   - Temps estimés par étape
   - Coûts connus

3. **Vos priorités métier** :
   - Calcul prix automatique urgent ?
   - Fiches atelier prioritaire ?
   - Planning production peut attendre ?

**🔹 PEUX-TU ME DONNER CES INFOS ?**

Cela me permettra de dimensionner correctement l'architecture et de ne pas partir sur des hypothèses fausses.

---

## ✅ RÉPONSES UTILISATEUR - CONTEXTE MÉTIER

### 🏭 Secteur d'activité : **Multi-métiers - Communication visuelle + IT/Bureautique**

**⚠️ CORRECTION PÉRIMÈTRE :** Beaucoup plus large que prévu initialement !

#### Branches d'activité
1. **Signalétique** (enseignes, panneaux, PLV)
2. **Imprimerie** (offset, numérique grand format)
3. **Gravure** (laser, mécanique, chimique - pierre, métal, verre, plastique...)
4. **Covering/Adhésifs** (véhicules, bâtiments, vitrines)
5. **Textile** (vente + personnalisation/marquage)
6. **IT/Bureautique** :
   - Vente machines (PC, serveurs, imprimantes MFP)
   - Contrats de maintenance
   - Infogérance
   - Cybersécurité

**→ ERP COMPLET multi-activités avec production industrielle + négoce + services**

#### Type de production
**✅ Make-to-Order (Production à la commande)**
- Chaque projet est unique
- Fabrication déclenchée après validation devis client
- Personnalisation systématique (dimensions, visuels, matériaux)

#### Complexité nomenclatures
**✅ Multi-niveaux (3-4 niveaux)**
- Produits finis composés de sous-ensembles
- Sous-ensembles fabriqués à partir de matières premières transformées
- Assemblages complexes avec composants achetés (LEDs, transformateurs, etc.)

#### Variabilité produits
**✅ Paramétrables → Configurables complexes**
- Dimensions variables (calepinage sur mesure)
- Choix matériaux (PVC, dibond, alu, etc.)
- Options techniques (avec/sans éclairage, fixation, finitions)
- Règles métier : compatibilités matériaux/machines/finitions

#### Machines atelier
**✅ ~15 machines avec polyvalence limitée**

**Exemples identifiés :**
- Imprimante hybride LIYU Q2 (impression grand format)
- Table lamination à plat (application adhésifs)
- Fraiseuse numérique verso 4x2 (usinage, repérage caméra)
- Plotter de découpe (gabarits, adhésifs)

**Contraintes :**
- Ordre des opérations imposé (impression → lamination → découpe)
- Capacités machines limitées (dimensions max, matériaux compatibles)
- Temps setup entre produits différents
- Compétences opérateurs (soudure, câblage électrique, etc.)

#### Données existantes
**✅ Fichiers Excel + Catalogues fournisseurs + Logiciels CAO**
- Prix d'achat matières : Excel
- Calepinage/dimensions : Logiciel dessin (AutoCAD/Illustrator ?)
- Temps estimés : Expérience terrain (non formalisé ?)
- Gammes de fabrication : Connaissance métier (à formaliser)

---

### 📦 EXEMPLE 1 : PANNEAU SIMPLE 2x1,5m

**Produit fini :** Panneau publicitaire imprimé laminé 2x1,5m

#### Nomenclature (BOM)
```
PANNEAU_PUB_2x1.5m
├─ Support impression (matière première)
│   └─ Bâche frontlight 510g/m² : 3 m² (2x1,5m)
├─ Encres (consommable)
│   └─ Encres CMJN UV : calculé selon surface
├─ Film de lamination (matière première)
│   └─ Film polymère anti-UV : 3 m² (2x1,5m)
└─ Fixations (composant acheté)
    └─ Œillets laiton Ø15mm : 8 pièces (bords)
```

#### Gamme de fabrication (Routing)
```
1. IMPRESSION
   - Machine : LIYU Q2
   - Temps : 45 min (vitesse impression ~4 m²/h)
   - Opérateur : Imprimeur qualifié

2. SÉCHAGE
   - Temps : 30 min (UV ou air libre)

3. LAMINATION
   - Machine : Table lamination à plat
   - Temps : 20 min (application film + maroufle)
   - Opérateur : Finisseur

4. FINITION
   - Opération : Pose œillets
   - Temps : 15 min
   - Opérateur : Finisseur

5. CONTRÔLE QUALITÉ
   - Temps : 5 min
   - Opérateur : Chef atelier
```

#### Calcul coût de revient (exemple)
```
MATIÈRES PREMIÈRES
- Bâche 3m² × 8€/m²        = 24€
- Encres (forfait surface)  = 5€
- Film lamination 3m² × 6€  = 18€
- Œillets 8pcs × 0,50€      = 4€
TOTAL MATIÈRES              = 51€

MAIN D'ŒUVRE
- Impression 45min × 30€/h  = 22,50€
- Lamination 20min × 25€/h  = 8,33€
- Finition 15min × 25€/h    = 6,25€
- Contrôle 5min × 30€/h     = 2,50€
TOTAL MO                    = 39,58€

COÛTS MACHINE
- LIYU Q2 45min × 15€/h     = 11,25€
- Table lam. 20min × 5€/h   = 1,67€
TOTAL MACHINE               = 12,92€

COÛT DE REVIENT TOTAL       = 103,50€
MARGE 40%                   = 41,40€
PRIX DE VENTE HT            = 144,90€
```

---

### 🔆 EXEMPLE 2 : ENSEIGNE LUMINEUSE LETTRES DÉCOUPÉES

**Produit fini :** Enseigne lumineuse LED sur lettres PVC 19mm découpées

#### Nomenclature (BOM) - Plus complexe
```
ENSEIGNE_LUMINEUSE_LETTRES
├─ MODULE_LETTRE (quantité variable selon texte)
│   ├─ Plaque PVC 19mm (matière première)
│   │   └─ PVC expansé blanc 19mm : surface calculée (calepinage)
│   ├─ Encres impression (consommable)
│   ├─ Module LED (composant acheté)
│   │   └─ Bande LED RGB 12V : longueur calculée (contour lettre)
│   └─ Entretoises fixation (composant acheté)
│       └─ Entretoises inox Ø10mm H30mm : 4 pièces/lettre
├─ SYSTÈME_ELECTRIQUE
│   ├─ Transformateur 12V ou 24V (composant acheté)
│   │   └─ Choix selon puissance totale LEDs
│   ├─ Câblage (matière première)
│   │   └─ Câble électrique 2x0,75mm² : longueur calculée
│   └─ Connecteurs (composant acheté)
│       └─ Dominos, cosses, etc.
└─ GABARIT_POSE (sous-produit)
    └─ Film vinyle de repérage : surface calculée
```

#### Gamme de fabrication (Routing)
```
1. PRÉPARATION FICHIERS
   - Opération : CAO/DAO (vectorisation, calepinage)
   - Temps : Variable (45-120 min selon complexité)
   - Opérateur : Infographiste

2. IMPRESSION FACE AVANT
   - Machine : LIYU Q2
   - Support : Plaque PVC 19mm (face avant)
   - Temps : Calculé selon surface
   - Opérateur : Imprimeur

3. SÉCHAGE
   - Temps : 30 min

4. USINAGE VERSO
   - Machine : Fraiseuse numérique verso 4x2
   - Opération 1 : Repérage caméra (alignement)
   - Opération 2 : Évidement profondeur 15mm (logement LEDs)
   - Opération 3 : Découpe contour lettres
   - Temps : Variable (30-90 min selon complexité)
   - Opérateur : Usineur

5. NETTOYAGE/ÉBAVURAGE
   - Temps : 15 min
   - Opérateur : Finisseur

6. INSTALLATION LEDs
   - Opération : Collage bandes LED dans évidements
   - Temps : 20 min/lettre
   - Opérateur : Électricien qualifié

7. CÂBLAGE/PRÉCÂBLAGE
   - Opération : Raccordement LEDs, transformateur, dominos
   - Temps : 45-90 min selon nombre lettres
   - Opérateur : Électricien qualifié

8. TEST ÉLECTRIQUE
   - Opération : Vérification fonctionnement, intensité, uniformité
   - Temps : 10 min
   - Opérateur : Électricien

9. GABARIT DE POSE
   - Machine : Plotter de découpe
   - Support : Film vinyle de repérage
   - Temps : 20 min
   - Opérateur : Imprimeur

10. CONTRÔLE QUALITÉ FINAL
    - Temps : 15 min
    - Opérateur : Chef atelier
```

#### Calcul coût de revient (exemple - 5 lettres)
```
MATIÈRES PREMIÈRES
- PVC 19mm 2m² × 35€/m²        = 70€
- Encres impression            = 8€
- Bande LED RGB 10m × 12€/m    = 120€
- Film vinyle gabarit 1m²      = 8€
- Câble électrique 15m × 2€    = 30€
TOTAL MATIÈRES                 = 236€

COMPOSANTS ACHETÉS
- Entretoises 20pcs × 1,50€    = 30€
- Transformateur 12V 150W      = 45€
- Connecteurs/dominos          = 15€
TOTAL COMPOSANTS               = 90€

MAIN D'ŒUVRE
- Infographie 90min × 35€/h    = 52,50€
- Impression 40min × 30€/h     = 20€
- Usinage 60min × 30€/h        = 30€
- Installation LEDs 100min × 35€ = 58,33€
- Câblage 60min × 35€/h        = 35€
- Gabarit 20min × 30€/h        = 10€
- Contrôles 25min × 30€/h      = 12,50€
TOTAL MO                       = 218,33€

COÛTS MACHINE
- LIYU 40min × 15€/h           = 10€
- Fraiseuse 60min × 25€/h      = 25€
- Plotter 20min × 10€/h        = 3,33€
TOTAL MACHINE                  = 38,33€

COÛT DE REVIENT TOTAL          = 582,66€
MARGE 50%                      = 291,33€
PRIX DE VENTE HT               = 873,99€
```

---

### 🎯 ANALYSE MÉTIER - BESOINS PRIORITAIRES

#### 🔴 URGENCE HAUTE : Calcul automatique devis
**Problème actuel :** Excel + calepinage manuel = temps de chiffrage élevé + risque erreurs

**Besoin :**
1. Configurateur produit avec paramètres (dimensions, options, matériaux)
2. Calcul automatique quantités matières (avec chutes/pertes)
3. Calcul temps machine par opération (selon dimensions/complexité)
4. Agrégation automatique coût de revient
5. Application marge → Prix vente HT dans devis

**ROI attendu :**
- Réduction temps chiffrage de 60-70%
- Fiabilité prix (moins d'oublis)
- Cohérence commerciale

---

#### 🟡 URGENCE MOYENNE : Fiches de production atelier
**Problème actuel :** Communication orale/papier volant → erreurs, pertes info

**Besoin :**
1. Génération PDF fiche de production par commande
2. Liste matières à préparer (picking list)
3. Instructions opératoires par poste
4. Plan/visuel du produit fini
5. Suivi simple avancement (cases à cocher)

**ROI attendu :**
- Moins d'erreurs fabrication
- Traçabilité
- Autonomie opérateurs

---

#### 🟢 URGENCE BASSE : Planning production
**Problème actuel :** Planification mentale/tableau blanc → sous-optimal mais gérable

**Besoin :**
1. Ordonnancement automatique commandes
2. Visualisation charge machines
3. Détection conflits/retards
4. Gantt de production

**ROI attendu :**
- Optimisation capacité
- Réduction délais
- Anticipation problèmes

---

### 💡 PLAN D'ACTION RECOMMANDÉ

#### 🎯 PHASE 1 : PRODUITS SIMPLES (EN COURS)
Déjà décrite précédemment.

---

#### 🎯 PHASE 2A : PRODUITS CATALOGUE - CONFIGURATEUR & CALCUL (PRIORITÉ 1)
**Durée estimée :** 6-8 semaines
**Objectif :** Chiffrage automatique des produits signalétique

##### Semaine 1-2 : Modélisation données
**Entités à créer :**

1. **ProduitCatalogue** (extends Produit)
```php
- typeProduction: 'make_to_order'
- configurateur: JSON (paramètres configurables)
- complexite: 'simple' | 'moyen' | 'complexe'
```

2. **Nomenclature** (BOM)
```php
- produitCatalogue (FK)
- version: string
- dateValidation: DateTime
- statut: 'brouillon' | 'validé' | 'obsolete'
```

3. **NomenclatureLigne** (composants)
```php
- nomenclature (FK)
- produitComposant (FK → Produit simple)
- quantite: decimal (ou formule)
- typeQuantite: 'fixe' | 'calculée' | 'variable'
- formuleCalcul: string (pour quantités dynamiques)
- niveau: integer (pour arborescence)
- parent (FK → NomenclatureLigne, nullable)
```

4. **Gamme** (routing)
```php
- nomenclature (FK)
- tempsTotal: integer (minutes, calculé)
- coutTotal: decimal (calculé)
```

5. **GammeOperation** (étapes)
```php
- gamme (FK)
- ordre: integer
- libelle: string
- posteTravail (FK)
- typeTemps: 'fixe' | 'calculé'
- tempsStandard: integer (minutes)
- formuleTemps: string (si calculé)
- tauxHoraireMO: decimal
- tauxHoraireMachine: decimal
- instructions: text
```

6. **PosteTravail** (machines/centres)
```php
- code: string
- libelle: string
- typeMachine: string ('impression', 'usinage', 'finition', 'assemblage')
- tauxHoraire: decimal
- capacitesMax: JSON (dimensions, poids, matériaux)
- competencesRequises: JSON
```

##### Semaine 3-4 : Moteur de calcul
**Service à développer :** `ProduitCatalogueCalculator`

**Fonctionnalités :**
```php
class ProduitCatalogueCalculator
{
    // Calcul quantités matières avec formules
    public function calculerQuantitesMatières(
        ProduitCatalogue $produit,
        array $parametres // ['largeur' => 2000, 'hauteur' => 1500, ...]
    ): array;

    // Calcul temps par opération
    public function calculerTempsOperations(
        Gamme $gamme,
        array $parametres
    ): array;

    // Agrégation coût de revient complet
    public function calculerCoutRevient(
        ProduitCatalogue $produit,
        array $parametres
    ): CoutRevientDTO {
        // Retourne: coutMatières, coutMO, coutMachine, total
    };

    // Application marge → PV
    public function calculerPrixVente(
        float $coutRevient,
        float $margePourcent
    ): float;
}
```

**Gestion formules dynamiques :**
```php
// Exemple formule quantité bâche
"surface = (largeur / 1000) * (hauteur / 1000) * 1.05" // +5% chutes

// Exemple formule temps impression
"temps = (surface / vitesse_impression) + temps_setup"
```

##### Semaine 5-6 : Interface utilisateur
**Création fiche produit catalogue :**
- Formulaire onglets (comme produit simple + onglets spécifiques)
- **Onglet "Nomenclature"** : Gestion composants avec quantités/formules
- **Onglet "Gamme"** : Gestion opérations avec temps/coûts
- **Onglet "Configurateur"** : Définition paramètres (dimensions, options, matériaux)

**Intégration devis :**
- Ajout ligne devis : choix "Produit catalogue"
- Modal configurateur : saisie paramètres (dimensions, options)
- Calcul automatique → injection prix/description dans ligne devis

##### Semaine 7-8 : Tests & ajustements
- Tests calculs avec produits réels
- Calibrage formules
- Formation utilisateurs
- Documentation

---

#### 🎯 PHASE 2B : FICHES DE PRODUCTION (PRIORITÉ 2)
**Durée estimée :** 3-4 semaines
**Démarrage :** Après Phase 2A validée

**Entités :**
- `OrdreFabrication` (OF)
- `OFOperation` (suivi étapes)

**Fonctionnalités :**
- Génération PDF fiche production
- Picking list matières
- Instructions opératoires
- Suivi avancement simple

---

#### 🎯 PHASE 2C : PLANNING PRODUCTION (PRIORITÉ 3)
**Durée estimée :** 4-6 semaines
**Démarrage :** Après Phase 2B validée

**Entités :**
- `Planning`
- `Reservation` (machines/opérateurs)

**Fonctionnalités :**
- Ordonnancement automatique
- Gantt interactif
- Gestion conflits ressources

---

### 🚀 PROCHAINES ACTIONS IMMÉDIATES

#### ✅ VALIDATIONS REQUISES

**🔹 Confirmes-tu cette approche en 3 phases (2A → 2B → 2C) ?**

**🔹 Validation Phase 2A (Configurateur & Calcul) :**
- Architecture entités proposée OK ?
- Formules dynamiques pour quantités/temps OK ?
- Intégration dans devis comme décrit OK ?

**🔹 Questions complémentaires :**

1. **Formules de calcul :** Tu les as déjà formalisées ou on les construit ensemble au fur et à mesure ?

2. **Taux horaires :** Tu as les coûts horaires par machine/opérateur ?

3. **Chutes/pertes matières :** Pourcentages types par matériau (ex: bâche +5%, PVC +10%) ?

4. **Temps setup machines :** Fixes ou variables selon produit ?

5. **Priorité Phase 2A :** On commence maintenant ou tu veux d'abord finir les produits simples (Phase 1) ?

---

## ✅ VALIDATION UTILISATEUR - STRATÉGIE GLOBALE

### 🎯 Décisions validées

#### 1. Périmètre réel = Multi-métiers
- **Signalétique + Imprimerie + Gravure + Covering + Textile + IT/Bureautique**
- Exemples initiaux (panneaux, enseignes) = seulement une partie de l'activité
- Architecture doit supporter TOUS les métiers, pas juste signalétique

#### 2. Approche développement
✅ **"Prévoir architecture globale PUIS développer point par point"**
- Conception complète en amont
- Implémentation itérative pour validation progressive
- Éviter refonte future (coûteux, bloquant)

#### 3. Formules de calcul
✅ **Collaboration utilisateur + assistant**
- Base existante à améliorer
- Co-construction des formules optimales
- Évolution continue

#### 4. Taux horaires & paramètres machines
✅ **Entité Machine administrable**
- Tous paramètres modifiables via interface admin
- Taux horaires : MO + Machine
- Temps setup configurables
- Capacités/contraintes par machine
- **→ Flexibilité totale sans toucher au code**

#### 5. Gestion chutes matières
✅ **Intégration dans système (amélioration coefficients actuels)**
- Actuellement : coefficients de vente globaux
- Proposition : chutes calculées par matériau/opération
- Traçabilité et optimisation

#### 6. Ordonnancement développement
✅ **Finir Phase 1 (Produits simples) → Architecture complète Phase 2 → Implémentation progressive**

---

## 🏗️ ARCHITECTURE GLOBALE RÉVISÉE

### 📐 Vue d'ensemble système

```
┌─────────────────────────────────────────────────────────────┐
│                    PRODUITS & SERVICES                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────┐  ┌──────────────────┐                │
│  │ PRODUITS SIMPLES │  │ PRODUITS CATALOGUE│                │
│  │  (Phase 1)       │  │  (Phase 2A-2B-2C) │                │
│  └────────┬─────────┘  └────────┬──────────┘                │
│           │                     │                            │
│           │  Achat/Revente     │  Fabrication               │
│           │  Direct            │  Multi-niveaux             │
│           │                     │                            │
│  ┌────────▼─────────────────────▼──────────┐                │
│  │      CATALOGUE UNIFIÉ DEVIS             │                │
│  │  (Ligne libre OU produit catalogue)     │                │
│  └─────────────────────────────────────────┘                │
│                                                              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│              RÉFÉRENTIEL PRODUCTION (Phase 2)                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │  MACHINES   │  │ NOMENCLATURES│  │   GAMMES     │       │
│  │             │  │    (BOM)     │  │  (Routings)  │       │
│  │ • Paramètres│  │              │  │              │       │
│  │ • Capacités │  │ • Composants │  │ • Opérations │       │
│  │ • Taux €/h  │  │ • Quantités  │  │ • Temps      │       │
│  │ • Setup     │  │ • Formules   │  │ • Coûts      │       │
│  └─────────────┘  └──────────────┘  └──────────────┘       │
│                                                              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│           MOTEUR DE CALCUL (Phase 2A)                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Paramètres client (dimensions, options, matériaux)         │
│           ↓                                                  │
│  Calcul quantités matières (formules dynamiques)            │
│           ↓                                                  │
│  Calcul temps opérations (formules + machine)               │
│           ↓                                                  │
│  Agrégation coûts (matières + MO + machine + chutes)        │
│           ↓                                                  │
│  Prix de vente HT (coût revient × (1 + marge%))             │
│           ↓                                                  │
│  Injection ligne devis (automatique)                        │
│                                                              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│         PRODUCTION ATELIER (Phase 2B & 2C)                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Devis signé → Ordre Fabrication (OF)                       │
│           ↓                                                  │
│  Génération fiches production (PDF)                          │
│           ↓                                                  │
│  Picking list matières                                       │
│           ↓                                                  │
│  Planning machines (ordonnancement)                          │
│           ↓                                                  │
│  Suivi avancement (temps réel)                               │
│           ↓                                                  │
│  Contrôle qualité & livraison                                │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

### 🗂️ ENTITÉS PRINCIPALES - ARCHITECTURE COMPLÈTE

#### BLOC 1 : Produits & Services (Phase 1 + base Phase 2)

**Produit** (entité centrale existante, à enrichir)
```php
// Champs existants conservés
- id, reference, designation, description
- type: 'bien' | 'service' | 'forfait' | 'catalogue'  ← AJOUT 'catalogue'
- prixAchatHt, prixVenteHt, margePercent, tvaPercent
- unite, categorie, stockQuantite, stockMinimum
- actif, gestionStock, image, notesInternes
- createdAt, updatedAt

// NOUVEAUX CHAMPS (Phase 1)
- codeArticle: string (alias de 'reference')
- typeProduit: 'BIEN' | 'SERVICE'  ← standardisation
- famille (FK → FamilleProduit, nullable)
- sousFamille (FK → SousFamilleProduit, nullable)
- fournisseurPrincipal (FK → Fournisseur, nullable)
- fraisPourcentage: decimal (frais annexes)
- prixRevient: decimal (calculé = prixAchat × (1 + frais%))
- quantiteDefaut: decimal (défaut devis)
- nombreDecimalesPrix: integer
- uniteVente (FK → Unite, nullable)
- uniteAchat (FK → Unite, nullable)

// COMPTABILITÉ
- typeDestination: enum (MARCHANDISE, PRODUIT_FINI, etc.)
- compteVente (FK → ComptePCG, nullable)
- compteAchat (FK → ComptePCG, nullable)
- compteStock (FK → ComptePCG, nullable)
- compteVariationStock (FK → ComptePCG, nullable)

// PHASE 2 : Produits catalogue
- estCatalogue: boolean (false par défaut)
- typeProduction: enum ('make_to_order', 'make_to_stock', 'assemble_to_order')
- configurateur: JSON (paramètres configurables)
- complexite: enum ('simple', 'moyen', 'complexe')

// PRODUITS CONCURRENT (prospection)
- estConcurrent: boolean (false par défaut)
```

**FamilleProduit** (nouveau)
```php
- id
- code: string (unique)
- libelle: string
- description: text (nullable)
- ordre: integer (drag & drop)
- actif: boolean
- parent (FK → FamilleProduit, nullable) ← arborescence illimitée
```

**SousFamilleProduit** (supprimé - géré par parent dans FamilleProduit)

**Fournisseur** (nouveau - structure Client)
```php
// Structure identique à Client
- id, raisonSociale, formeJuridique
- siren, siret, tva, naf
- email, telephone, siteweb
- contacts (1:N → Contact)
- adresses (1:N → Adresse)
- contactFacturationDefault, contactLivraisonDefault
- statut: enum ('actif', 'inactif', 'bloqué')
- notesInternes, conditions_paiement
- createdAt, updatedAt
```

**ProduitFournisseur** (nouveau - table pivot enrichie)
```php
- id
- produit (FK)
- fournisseur (FK)
- referenceFournisseur: string
- prixVenteConseille: decimal (nullable)
- remiseSurPVC: decimal (nullable)
- prixAchatPublic: decimal
- remiseAchat: decimal
- prixAchatNetHT: decimal (calculé)
- uniteAchat (FK → Unite, nullable)
- multipleCommande: integer (qté mini)
- delaiLivraisonJours: integer (nullable)
- codeEcoContribution: string (nullable)
- priorite: integer (0 par défaut, plus haut = préférentiel)
- actif: boolean
```

**ProduitImage** (existe déjà)
```php
- id, produit (FK), imagePath
- legende, altText (SEO)
- ordre: integer
- isDefault: boolean
```

**ArticleLie** (nouveau - auto-relation)
```php
- id
- produitPrincipal (FK → Produit)
- produitLie (FK → Produit)
- typeRelation: enum ('optionnel', 'complementaire', 'alternatif', 'pack')
- ordre: integer
- quantiteDefaut: decimal (pour packs)
```

---

#### BLOC 2 : Référentiel Production (Phase 2A)

**Machine** (nouveau - entité centrale administrable)
```php
- id
- code: string (unique, ex: 'LIYU-Q2')
- libelle: string (ex: 'Imprimante hybride LIYU Q2')
- typeMachine: string (ex: 'impression', 'usinage', 'lamination', 'gravure', 'plotter')
- fabricant: string (nullable)
- modele: string (nullable)
- numeroSerie: string (nullable)

// COÛTS
- tauxHoraireMachine: decimal (€/h - amortissement + conso)
- tauxHoraireMO: decimal (€/h - opérateur qualifié)

// TEMPS
- tempsSetup: integer (minutes, par défaut)
- formuleTempsSetup: string (nullable, si variable selon produit)

// CAPACITÉS (JSON pour flexibilité)
- capacites: JSON {
    "largeur_max": 2000,    // mm
    "hauteur_max": 4000,    // mm
    "epaisseur_max": 50,    // mm
    "poids_max": 100,       // kg
    "materiaux": ["pvc", "dibond", "alu", "bois", "verre"],
    "vitesse_production": 4.5  // m²/h pour imprimante, ou m/min pour plotter
  }

// CONTRAINTES
- competencesRequises: JSON ['impression_numerique', 'reglage_couleurs']
- prerequisOperations: JSON ['impression', 'sechage'] (dépendances)
- maintenancePreventive: JSON {
    "periodicite_jours": 30,
    "duree_heures": 4,
    "prochaine_date": "2025-11-03"
  }

// ÉTAT
- actif: boolean
- emplacementAtelier: string (nullable)
- observations: text (nullable)
- dateAchat: date (nullable)
- dateMiseService: date (nullable)
```

**Nomenclature** (nouveau - BOM)
```php
- id
- produitCatalogue (FK → Produit where estCatalogue=true)
- version: string (ex: 'v1.0', 'v2.3')
- dateCreation: DateTime
- dateValidation: DateTime (nullable)
- validePar (FK → User, nullable)
- statut: enum ('brouillon', 'validé', 'obsolète')
- commentaire: text (nullable)
```

**NomenclatureLigne** (nouveau - composants BOM)
```php
- id
- nomenclature (FK)
- produitComposant (FK → Produit where estCatalogue=false)  ← Produit simple uniquement
- niveau: integer (1, 2, 3... pour arborescence)
- parent (FK → NomenclatureLigne, nullable) ← permet sous-ensembles

// QUANTITÉ
- typeQuantite: enum ('fixe', 'calculée', 'variable')
- quantiteFixe: decimal (nullable, si type='fixe')
- formuleCalcul: string (nullable, si type='calculée')
  // Ex: "(largeur/1000) * (hauteur/1000) * 1.05"
  //     "nb_lettres * 4" (entretoises)
  //     "perimetre_total / 5 * 1.1" (bande LED)
- variableNom: string (nullable, si type='variable') ← demandée à user
- variableDefaut: decimal (nullable)

// CHUTES & PERTES
- coefficientChute: decimal (ex: 1.05 pour +5%)
- commentaire: text (nullable)
- ordre: integer (affichage)
```

**Gamme** (nouveau - routing)
```php
- id
- nomenclature (FK) ← 1 gamme par nomenclature
- tempsTotal: integer (minutes, calculé automatiquement)
- coutTotalMO: decimal (calculé)
- coutTotalMachine: decimal (calculé)
- coutTotal: decimal (calculé = MO + Machine)
- statut: enum ('brouillon', 'validé')
- commentaire: text (nullable)
```

**GammeOperation** (nouveau - étapes routing)
```php
- id
- gamme (FK)
- ordre: integer (10, 20, 30... pour réordonner facilement)
- libelle: string (ex: 'Impression face avant')
- machine (FK → Machine, nullable) ← Peut être manuelle
- typeOperation: enum ('preparation', 'production', 'assemblage', 'controle', 'finition')

// TEMPS
- typeTemps: enum ('fixe', 'calculé', 'mixte')
- tempsFixe: integer (minutes, si type='fixe')
- formuleTemps: string (nullable, si type='calculé' ou 'mixte')
  // Ex: "(surface / {vitesse_machine}) + {temps_setup}"
  //     "nb_lettres * 20 + 30" (20min/lettre + 30min setup)
- tempsSetupInclus: boolean (true par défaut)

// COÛTS (peuvent override ceux de Machine)
- tauxHoraireMO: decimal (nullable, sinon hérite Machine)
- tauxHoraireMachine: decimal (nullable, sinon hérite Machine)

// INSTRUCTIONS
- instructions: text (nullable) ← affiché sur fiche production
- fichierJoint: string (nullable, path PDF/image)
- competencesRequises: JSON (nullable, sinon hérite Machine)

// CONTRÔLE QUALITÉ
- pointsControle: JSON (nullable) ← checklist contrôle qualité
```

---

#### BLOC 3 : Moteur de calcul (Phase 2A - Services)

**Service : ProduitCatalogueCalculator**
```php
class ProduitCatalogueCalculator
{
    /**
     * Calcule quantités matières pour un produit configuré
     * @param ProduitCatalogue $produit
     * @param array $parametres ['largeur' => 2000, 'hauteur' => 1500, 'nb_lettres' => 5, ...]
     * @return array ['produit_id' => quantite_calculee]
     */
    public function calculerQuantitesMatières(Produit $produit, array $parametres): array;

    /**
     * Calcule temps par opération de gamme
     * @return array ['operation_id' => temps_minutes]
     */
    public function calculerTempsOperations(Gamme $gamme, array $parametres): array;

    /**
     * Évalue formule dynamique (moteur d'expression)
     * @param string $formule "(largeur/1000) * (hauteur/1000) * 1.05"
     * @param array $variables ['largeur' => 2000, 'hauteur' => 1500]
     * @return float
     */
    public function evaluerFormule(string $formule, array $variables): float;

    /**
     * Calcule coût de revient complet
     * @return CoutRevientDTO {
     *   coutMatières: float,
     *   coutMO: float,
     *   coutMachine: float,
     *   coutChutes: float,
     *   coutTotal: float,
     *   detailMatières: array,
     *   detailOperations: array
     * }
     */
    public function calculerCoutRevient(Produit $produit, array $parametres): CoutRevientDTO;

    /**
     * Applique marge et retourne PV HT
     */
    public function calculerPrixVente(float $coutRevient, float $margePourcent): float;
}
```

**DTO : CoutRevientDTO**
```php
class CoutRevientDTO
{
    public float $coutMatières;
    public float $coutMO;
    public float $coutMachine;
    public float $coutChutes;
    public float $coutTotal;
    public array $detailMatières; // ['produit_id' => ['qte' => ..., 'pu' => ..., 'total' => ...]]
    public array $detailOperations; // ['operation_id' => ['temps' => ..., 'cout_mo' => ..., 'cout_machine' => ...]]
    public int $tempsProductionTotal; // minutes
}
```

---

#### BLOC 4 : Production atelier (Phase 2B)

**OrdreFabrication** (OF)
```php
- id
- numero: string (auto OF-2025-0001)
- devisItem (FK → DevisItem) ← lien vers ligne devis
- devis (FK → Devis) ← lien devis parent
- produitCatalogue (FK → Produit)
- nomenclature (FK → Nomenclature) ← version figée
- gamme (FK → Gamme) ← version figée
- parametresClient: JSON ← dimensions, options choisies par client
- quantite: integer (qté à fabriquer)

// DATES & PLANNING
- dateCreation: DateTime
- dateLancement: DateTime (nullable)
- dateFinPrevue: DateTime (calculée)
- dateFinReelle: DateTime (nullable)
- priorite: integer (1-5)

// ÉTAT
- statut: enum ('brouillon', 'planifié', 'en_cours', 'suspendu', 'terminé', 'annulé')
- avancement: integer (0-100%)

// RESPONSABLES
- responsable (FK → User)
- validePar (FK → User, nullable)

// COÛTS RÉELS (suivi)
- coutMatieresReel: decimal (nullable)
- coutMOReel: decimal (nullable)
- coutMachineReel: decimal (nullable)
- ecartBudget: decimal (calculé)

// DOCUMENTS
- ficheProduit ionPDF: string (path PDF généré)
- observations: text
```

**OFOperation** (suivi étapes OF)
```php
- id
- ordreFabrication (FK)
- gammeOperation (FK) ← référence opération planifiée
- ordre: integer
- libelle: string
- machine (FK → Machine, nullable)

// PLANIFICATION
- datePrevueDebut: DateTime
- datePrevueFin: DateTime
- dureePreveMinutes: integer

// RÉALISATION
- dateReelleDebut: DateTime (nullable)
- dateReelleFin: DateTime (nullable)
- dureeReelleMinutes: integer (nullable)
- operateur (FK → User, nullable)

// ÉTAT
- statut: enum ('attente', 'en_cours', 'terminé', 'bloqué', 'annulé')
- avancement: integer (0-100%)
- observations: text (nullable)

// CONTRÔLE QUALITÉ
- controleEffectue: boolean
- controleOK: boolean
- nonConformites: text (nullable)
```

---

#### BLOC 5 : Planning production (Phase 2C)

**Planning** (vue globale)
```php
- id
- semaine: string ('2025-W45')
- chargeGlobale: integer (minutes totales planifiées)
- capaciteGlobale: integer (minutes disponibles)
- tauxCharge: float (%)
- statut: enum ('prévisionnel', 'validé', 'en_cours', 'terminé')
```

**ReservationMachine** (slots machines)
```php
- id
- machine (FK)
- ordreFabrication (FK)
- ofOperation (FK → OFOperation)
- dateDebut: DateTime
- dateFin: DateTime
- dureeMinutes: integer
- statut: enum ('planifié', 'confirmé', 'en_cours', 'terminé', 'annulé')
- operateur (FK → User, nullable)
```

**ConflitPlanning** (détection automatique)
```php
- id
- type: enum ('surcharge_machine', 'operateur_indisponible', 'matiere_manquante', 'delai_depassé')
- gravite: enum ('info', 'warning', 'critique')
- machine (FK, nullable)
- of1 (FK → OrdreFabrication, nullable)
- of2 (FK → OrdreFabrication, nullable) ← si conflit entre 2 OF
- dateDetection: DateTime
- resolu: boolean
- resolution: text (nullable)
```

---

### 🔧 SERVICES MÉTIER ADDITIONNELS

**Service : FormuleEvaluator**
```php
// Évalue formules mathématiques de manière sécurisée
// Utilise symfony/expression-language
class FormuleEvaluator
{
    public function evaluer(string $formule, array $variables): float;
    public function valider(string $formule): bool; // vérifie syntaxe
    public function extraireVariables(string $formule): array; // liste variables utilisées
}
```

**Service : GammeService**
```php
class GammeService
{
    public function calculerTempsTotal(Gamme $gamme, array $parametres): int;
    public function calculerCoutTotal(Gamme $gamme, array $parametres): float;
    public function genererFicheProduction(OrdreFabrication $of): string; // retourne path PDF
    public function genererPickingList(OrdreFabrication $of): array; // liste matières
}
```

**Service : PlanningService** (Phase 2C)
```php
class PlanningService
{
    public function ordonnancer(array $ordresFabrication): void; // planif auto
    public function detecterConflits(Planning $planning): array; // retourne ConflitPlanning[]
    public function calculerChargesMachines(\DateTimeInterface $debut, \DateTimeInterface $fin): array;
    public function proposerDateFinRealiste(OrdreFabrication $of): \DateTime;
}
```

---

### 📊 INTÉGRATION AVEC SYSTÈME EXISTANT

#### Modification DevisItem (existant)
```php
// AJOUT champs pour produits catalogue
- produitCatalogue (FK → Produit where estCatalogue=true, nullable)
- parametresConfiguration: JSON (nullable) ← dimensions, options saisies
- coutRevientCalcule: decimal (nullable) ← traçabilité calcul
- detailCalcul: JSON (nullable) ← détail complet pour affichage
```

#### Formulaire ajout ligne devis
```twig
{# Choix type ligne #}
<select id="type-ligne">
    <option value="libre">Ligne libre</option>
    <option value="produit-simple">Produit simple</option>
    <option value="produit-catalogue">Produit catalogue</option>
</select>

{# Si produit-catalogue sélectionné → modal configurateur #}
<div id="modal-configurateur">
    {# Champs dynamiques selon produit.configurateur JSON #}
    <input name="largeur" type="number" placeholder="Largeur (mm)">
    <input name="hauteur" type="number" placeholder="Hauteur (mm)">
    <select name="materiau">
        <option>PVC 3mm</option>
        <option>Dibond 3mm</option>
        <option>Alu 2mm</option>
    </select>
    <button onclick="calculerPrix()">Calculer prix</button>
</div>

{# Résultat calcul affiché dans modale avant ajout ligne #}
<div id="resultat-calcul">
    <h5>Détail calcul</h5>
    <ul>
        <li>Matières : 236€</li>
        <li>Main d'œuvre : 218€</li>
        <li>Machines : 38€</li>
        <li>Chutes (5%) : 12€</li>
    </ul>
    <p><strong>Coût revient : 504€</strong></p>
    <p>Marge 50% : 252€</p>
    <p><strong class="text-success">Prix vente HT : 756€</strong></p>
    <button onclick="ajouterLigneDevis()">Ajouter au devis</button>
</div>
```

---

### 🎯 BÉNÉFICES ARCHITECTURE PROPOSÉE

#### ✅ Flexibilité maximale
- **Machines administrables** : Tous paramètres modifiables sans code
- **Formules dynamiques** : Évolution calculs sans développement
- **Multi-métiers natif** : Support signalétique + imprimerie + gravure + covering + textile + IT

#### ✅ Évolutivité garantie
- Nomenclatures multi-niveaux illimités
- Ajout nouveaux types machines sans refonte
- Extension gammes sans limite
- Intégration e-commerce préparée

#### ✅ Traçabilité complète
- Versions nomenclatures/gammes figées dans OF
- Détail calculs conservé dans devis
- Suivi coûts réels vs prévisionnels
- Historique modifications

#### ✅ Performance métier
- Calcul devis automatisé (-60% temps)
- Fiches production générées automatiquement
- Planning optimisé (Phase 2C)
- Moins d'erreurs

---

### 📅 PLAN DE DÉVELOPPEMENT FINAL

#### PHASE 0 : Finalisation architecture (EN COURS)
**Durée : 1 semaine**
- Validation complète utilisateur
- Schéma BDD complet
- Diagrammes UML
- Documentation technique

#### PHASE 1 : Produits simples (6-8 semaines)
**Déjà définie précédemment**
- Enrichissement entité Produit
- Création Fournisseur, FamilleProduit, ProduitFournisseur
- Interface CRUD complète
- Intégration devis
- **LIVRABLE : Gestion catalogue produits simples opérationnelle**

#### PHASE 2A : Produits catalogue - Configurateur (6-8 semaines)
**Création référentiel production + moteur calcul**
- Entités : Machine, Nomenclature, NomenclatureLigne, Gamme, GammeOperation
- Service : ProduitCatalogueCalculator, FormuleEvaluator
- Interface admin : machines, nomenclatures, gammes
- Interface devis : configurateur produit + calcul temps réel
- **LIVRABLE : Chiffrage automatique produits complexes**

#### PHASE 2B : Fiches de production (3-4 semaines)
**Après validation Phase 2A**
- Entités : OrdreFabrication, OFOperation
- Service : GammeService
- Génération PDF fiches + picking lists
- Interface suivi simple atelier
- **LIVRABLE : Documentation atelier automatisée**

#### PHASE 2C : Planning production (4-6 semaines)
**Après validation Phase 2B**
- Entités : Planning, ReservationMachine, ConflitPlanning
- Service : PlanningService
- Interface planning Gantt
- Ordonnancement automatique
- **LIVRABLE : Pilotage atelier optimisé**

---

## ✅ VALIDATION FINALE REQUISE

### 🔹 Questions critiques

1. **Cette architecture globale répond-elle à TOUS tes métiers ?**
   - Signalétique : ✅
   - Imprimerie : ✅
   - Gravure : ✅
   - Covering : ✅
   - Textile : ✅
   - IT/Bureautique : ❓ (nécessite ajout contrats maintenance/infogérance ?)

2. **Entité Machine administrable te convient ?**
   - Tous paramètres modifiables en BDD
   - Pas besoin toucher au code pour évoluer
   - Formules flexibles

3. **Formules dynamiques : tu valides le système expression-language Symfony ?**
   - Syntaxe : `(largeur / 1000) * (hauteur / 1000) * {coeff_chute}`
   - Variables entre `{}` récupérées de Machine/Produit
   - Variables user saisies sans `{}`

4. **Intégration devis comme décrit OK ?**
   - Modal configurateur
   - Calcul temps réel
   - Affichage détail avant ajout ligne

5. **On valide architecture complète PUIS on commence Phase 1 ?**

---

*Document mis à jour le 03/10/2025 - Architecture globale complète définie*
