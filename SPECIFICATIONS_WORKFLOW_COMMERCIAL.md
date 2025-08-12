# 📋 SPÉCIFICATIONS DÉTAILLÉES - Workflow Commercial Complet
## TechnoProd ERP - Extension Commande/Facture/Avoir

**Version :** 1.0  
**Date :** 22 Juillet 2025  
**Auteur :** Équipe TechnoProd  

---

## 🎯 1. VISION ET OBJECTIFS

### **1.1 Vision Générale**
Transformer TechnoProd d'un système de gestion de devis en **ERP commercial complet** avec workflow bout-en-bout : Prospect → Devis → Commande → Facture → Avoir.

### **1.2 Objectifs Fonctionnels**
- ✅ **Continuité workflow** : Liaison automatique entre tous les documents
- ✅ **Gestion des avoirs** : Retours, ristournes, corrections
- ✅ **Suivi paiements** : Encaissements et remboursements  
- ✅ **Pilotage commercial** : Dashboard temps réel et KPI
- ✅ **Conformité comptable** : Respect des règles françaises

### **1.3 Objectifs Techniques**
- ✅ **Architecture extensible** : Prêt pour modules futurs
- ✅ **Performance optimisée** : Requêtes et indexation
- ✅ **Sécurité renforcée** : Validation des transitions d'état
- ✅ **Tests automatisés** : Couverture complète des workflows

---

## 🏗️ 2. ARCHITECTURE DES DONNÉES

### **2.1 Entités Existantes (Conservées)**
```php
// EXISTANT - AUCUNE MODIFICATION
- Prospect (clients/prospects unifiés)
- Devis (avec signature électronique)  
- DevisItem (lignes de devis)
- Produit (catalogue)
- User (utilisateurs/commerciaux)
- Secteur/Zone (territoires commerciaux)
- Contact/Adresse (Facturation/Livraison)
```

### **2.2 Nouvelles Entités à Créer**

#### **2.2.1 Entité COMMANDE**
```php
#[ORM\Entity]
class Commande
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $numeroCommande = null; // CMD-2025-0001

    // RELATIONS
    #[ORM\ManyToOne(targetEntity: Devis::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Devis $devisOrigine;

    #[ORM\ManyToOne(targetEntity: Prospect::class)]
    #[ORM\JoinColumn(nullable: false)]  
    private Prospect $prospect;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $commercial;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeItem::class, cascade: ['persist', 'remove'])]
    private Collection $commandeItems;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: Facture::class)]
    private Collection $factures;

    // WORKFLOW ET DATES
    #[ORM\Column(length: 20)]
    private string $statut = 'confirmee';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $dateCommande;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateProductionPrevue = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateExpedition = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]  
    private ?DateTimeInterface $dateLivraisonPrevue = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateLivraisonReelle = null;

    // MONTANTS (reprise du devis)
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalTtc = '0.00';

    // INFORMATIONS LOGISTIQUES
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructionsLivraison = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transporteur = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroSuivi = null;

    // AUDIT
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]  
    private DateTimeInterface $updatedAt;
}
```

#### **2.2.2 Entité COMMANDE_ITEM**
```php
#[ORM\Entity]
class CommandeItem
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'commandeItems')]
    private Commande $commande;

    #[ORM\ManyToOne(targetEntity: DevisItem::class)]
    private DevisItem $devisItemOrigine;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    private ?Produit $produit = null;

    // DONNÉES PRODUIT (figées à la commande)
    #[ORM\Column(length: 255)]
    private string $designation;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $quantite;

    #[ORM\Column(length: 10)]
    private string $unite = 'U';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $prixUnitaireHt;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $tvaPercent;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalLigneHt;

    // STATUT PRODUCTION PAR LIGNE
    #[ORM\Column(length: 20)]
    private string $statutProduction = 'en_attente';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateProductionPrevue = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateProductionReelle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $quantiteLivree = null;
}
```

#### **2.2.3 Entité FACTURE**
```php
#[ORM\Entity]  
class Facture
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $numeroFacture = null; // FACT-2025-0001

    // RELATIONS ORIGINES
    #[ORM\ManyToOne(targetEntity: Devis::class)]
    private ?Devis $devisOrigine = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'factures')]
    private ?Commande $commandeOrigine = null;

    // RELATIONS STANDARD  
    #[ORM\ManyToOne(targetEntity: Prospect::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Prospect $prospect;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $commercial;

    #[ORM\OneToMany(mappedBy: 'facture', targetEntity: FactureItem::class, cascade: ['persist', 'remove'])]
    private Collection $factureItems;

    #[ORM\OneToMany(mappedBy: 'facture', targetEntity: Paiement::class)]
    private Collection $paiements;

    #[ORM\OneToMany(mappedBy: 'factureOriginale', targetEntity: Avoir::class)]
    private Collection $avoirs;

    // WORKFLOW ET DATES
    #[ORM\Column(length: 20)]
    private string $statut = 'brouillon';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $dateFacture;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $dateEcheance;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $datePaiementComplet = null;

    // MONTANTS
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalTtc = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalPaye = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAvoir = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $soldeRestant = '0.00';

    // INFORMATIONS PAIEMENT
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionsPaiement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mentionsLegales = null;

    // AUDIT
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $updatedAt;
}
```

#### **2.2.4 Entité FACTURE_ITEM**
```php
#[ORM\Entity]
class FactureItem  
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'factureItems')]
    private Facture $facture;

    #[ORM\ManyToOne(targetEntity: CommandeItem::class)]
    private ?CommandeItem $commandeItemOrigine = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    private ?Produit $produit = null;

    // DONNÉES FIGÉES À LA FACTURE
    #[ORM\Column(length: 255)]
    private string $designation;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $quantite;

    #[ORM\Column(length: 10)]
    private string $unite = 'U';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $prixUnitaireHt;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $tvaPercent;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalLigneHt;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalLigneTva;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalLigneTtc;

    // SUIVI AVOIRS
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $quantiteAvoir = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $montantAvoir = '0.00';
}
```

#### **2.2.5 Entité AVOIR (Entité Clé)**
```php
#[ORM\Entity]
class Avoir
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $numeroAvoir = null; // AVOIR-2025-0001

    // RELATIONS
    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'avoirs')]
    #[ORM\JoinColumn(nullable: false)]
    private Facture $factureOriginale;

    #[ORM\ManyToOne(targetEntity: Prospect::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Prospect $prospect;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $commercial;

    #[ORM\OneToMany(mappedBy: 'avoir', targetEntity: AvoirItem::class, cascade: ['persist', 'remove'])]
    private Collection $avoirItems;

    #[ORM\OneToMany(mappedBy: 'avoir', targetEntity: Paiement::class)]
    private Collection $remboursements;

    // MOTIF ET WORKFLOW
    #[ORM\Column(length: 30)]
    private string $motif; // 'retour_marchandise', 'ristourne_commerciale', 'erreur_facturation', 'geste_commercial'

    #[ORM\Column(length: 20)]
    private string $statut = 'brouillon'; // 'brouillon', 'valide', 'rembourse', 'annule'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifDetaille = null;

    // DATES
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $dateAvoir;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateValidation = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateRemboursement = null;

    // MONTANTS
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalTtc = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $montantRembourse = '0.00';

    // VALIDATION
    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $validateurAvoir = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaireValidation = null;

    // AUDIT
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $updatedAt;
}
```

#### **2.2.6 Entité AVOIR_ITEM**
```php  
#[ORM\Entity]
class AvoirItem
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Avoir::class, inversedBy: 'avoirItems')]
    private Avoir $avoir;

    #[ORM\ManyToOne(targetEntity: FactureItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    private FactureItem $factureItemOriginale;

    // QUANTITÉ ET MONTANT À CRÉDITER
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $quantiteAvoir;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $prixUnitaireHt;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $tvaPercent;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalLigneHt;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalLigneTva;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalLigneTtc;

    // MOTIF LIGNE
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifLigne = null;
}
```

#### **2.2.7 Entité PAIEMENT (Support)**
```php
#[ORM\Entity]
class Paiement
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $numeroPaiement = null; // PAIE-2025-0001

    // RELATIONS (EXCLUSIVES)
    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'paiements')]
    private ?Facture $facture = null;

    #[ORM\ManyToOne(targetEntity: Avoir::class, inversedBy: 'remboursements')]
    private ?Avoir $avoir = null;

    // TYPE ET MODE
    #[ORM\Column(length: 20)]
    private string $type; // 'encaissement', 'remboursement'

    #[ORM\Column(length: 20)]
    private string $mode; // 'cheque', 'virement', 'carte_bancaire', 'especes', 'prelevement'

    // MONTANT ET DATES
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $montant;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $datePaiement;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateEncaissement = null;

    // RÉFÉRENCES
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenceBancaire = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroTransaction = null;

    // STATUT
    #[ORM\Column(length: 20)]
    private string $statut = 'en_attente'; // 'en_attente', 'encaisse', 'rejete', 'annule'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    // AUDIT
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $updatedAt;
}
```

---

## 🔄 3. WORKFLOWS DÉTAILLÉS

### **3.1 Workflow DEVIS (Existant - Inchangé)**
```
STATES: brouillon → envoye → {accepte|refuse|expire}
TRANSITIONS:
- brouillon → envoye (envoi email client)
- envoye → accepte (signature électronique)  
- envoye → refuse (refus client)
- envoye → expire (délai validité dépassé)
- accepte → commande_generee (conversion automatique)
```

### **3.2 Workflow COMMANDE (Nouveau)**
```
STATES: confirmee → preparation → production → expediee → livree → facturee
BRANCH STATES: annulee
RETOUR POSSIBLE: production → preparation (modifications)

TRANSITIONS:
- confirmee → preparation (mise en production)
- preparation → production (début fabrication/service)
- production → expediee (envoi/installation)
- expediee → livree (réception client)
- livree → facturee (génération facture)
- [ANY] → annulee (annulation client/interne)

AUTOMATISATIONS:
- Auto-transition si tous CommandeItems au même statut
- Email notifications sur changements d'état
- Mise à jour dates prévisionnelles
```

### **3.3 Workflow FACTURE (Nouveau)**
```
STATES: brouillon → envoyee → {payee|en_relance|en_litige} → archivee
BRANCH STATES: annulee, avoir_emis

TRANSITIONS:
- brouillon → envoyee (envoi client)
- envoyee → payee (paiement complet)
- envoyee → en_relance (échéance dépassée)  
- en_relance → en_litige (relances inefficaces)
- payee → archivee (clôture administrative)
- [envoyee|payee] → avoir_emis (création avoir)

AUTOMATISATIONS:
- Passage auto en_relance J+30 après échéance
- Calcul automatique soldes restants
- Génération PDF facture
```

### **3.4 Workflow AVOIR (Nouveau - Critique)**
```
STATES: brouillon → valide → {rembourse|utilise} → cloture
BRANCH STATES: annule, refuse

TRANSITIONS:
- brouillon → valide (validation manager)
- valide → rembourse (remboursement effectué)
- valide → utilise (compensation sur nouvelle facture)
- valide → refuse (rejet demande client)
- [ANY] → annule (annulation administrative)
- [rembourse|utilise] → cloture (finalisation)

VALIDATIONS:
- Montant avoir ≤ montant facture restant
- Motif obligatoire avec détails
- Approbation managériale requise
- Impact automatique sur soldes facture
```

---

## 🎮 4. RÈGLES MÉTIER CRITIQUES

### **4.1 Règles de Conversion**

#### **Devis → Commande**
```php
DÉCLENCHEURS:
- Devis.statut = 'accepte' 
- Signature électronique présente
- Acompte payé (si requis)

PROCESS:
1. Création Commande avec numéro auto
2. Copie de tous les DevisItems vers CommandeItems
3. Conservation références originales
4. Statut initial = 'confirmee'
5. Dates prévisionnelles calculées
6. Devis.statut = 'commande_generee'

VALIDATION:
- Pas de modification des prix lors copie
- Quantités > 0 obligatoires
- Prospect actif requis
```

#### **Commande → Facture**
```php
DÉCLENCHEURS:
- Commande.statut = 'livree'
- OU Facturation partielle autorisée
- Action manuelle utilisateur

PROCESS:
1. Sélection CommandeItems à facturer
2. Création Facture avec numéro auto
3. Génération FactureItems correspondants
4. Calcul montants et TVA
5. Statut initial = 'brouillon'
6. Date échéance = date facture + délai paiement

VALIDATION:  
- CommandeItems.statutProduction = 'livre' 
- Quantité à facturer ≤ quantité commandée
- Pas de double facturation
```

#### **Facture → Avoir**
```php
DÉCLENCHEURS:
- Facture.statut IN ('envoyee', 'payee')
- Demande client ou décision interne
- Action manuelle avec validation

PROCESS:
1. Sélection FactureItems à créditer
2. Saisie motif et quantités
3. Création Avoir avec numéro auto
4. Statut initial = 'brouillon'
5. Validation managériale obligatoire

VALIDATION:
- Quantité avoir ≤ quantité facturée restante
- Motif dans liste prédéfinie
- Approbation utilisateur ROLE_MANAGER
- Impact immédiat sur Facture.soldeRestant
```

### **4.2 Règles de Calcul**

#### **Montants et Totaux**
```php
// FORMULES STANDARD
totalLigneHt = quantite × prixUnitaireHt
totalLigneTva = totalLigneHt × (tvaPercent / 100)  
totalLigneTtc = totalLigneHt + totalLigneTva

// TOTAUX DOCUMENT
totalHt = SUM(items.totalLigneHt)
totalTva = SUM(items.totalLigneTva)  
totalTtc = SUM(items.totalLigneTtc)

// SOLDES FACTURE
soldeRestant = totalTtc - totalPaye - totalAvoir
```

#### **Impact Avoirs sur Factures**
```php
// APRÈS VALIDATION AVOIR
Facture.totalAvoir += Avoir.totalTtc
Facture.soldeRestant = Facture.totalTtc - Facture.totalPaye - Facture.totalAvoir

// STATUT AUTO SI SOLDÉ
if (Facture.soldeRestant <= 0.01) {
    Facture.statut = 'payee'
    Facture.datePaiementComplet = now()
}
```

### **4.3 Règles de Sécurité**

#### **Contrôles de Cohérence**
```php
INTERDICTIONS:
- Supprimer document avec suite workflow
- Modifier montants si statut > 'brouillon'  
- Avoir > montant facture restant dû
- Paiement > solde restant facture

VALIDATIONS:
- Dates cohérentes (commande < livraison < facture)
- Quantités positives sur toutes lignes
- TVA dans fourchettes légales (0-25%)
- Utilisateur avec droits suffisants
```

#### **Droits et Approbations**
```php
ROLES REQUIS:
- ROLE_USER: Devis, consultation
- ROLE_COMMERCIAL: Commandes, factures brouillon  
- ROLE_MANAGER: Validation avoirs, annulations
- ROLE_ADMIN: Configuration, exports

APPROBATIONS:
- Avoir > 500€: ROLE_MANAGER obligatoire
- Annulation commande: ROLE_MANAGER
- Modification facture envoyée: ROLE_ADMIN
```

---

## 🎨 5. INTERFACES UTILISATEUR

### **5.1 Dashboard Commercial (Extension)**
```php
WIDGETS EXISTANTS: (conservés)
- Statistiques devis par statut
- Pipeline commercial mensuel
- Top prospects et secteurs

NOUVEAUX WIDGETS:
- Commandes en cours par statut
- Factures impayées avec alertes  
- Avoirs du mois (montant et causes)
- CA facturation vs encaissements
- Délais moyens par étape workflow
```

### **5.2 Nouvelles Pages à Créer**

#### **Commandes**
```php
/commande                    # Index avec filtres statuts
/commande/new               # Création (depuis devis accepté)
/commande/{id}              # Vue détaillée avec suivi
/commande/{id}/edit         # Modification (si statut permet)
/commande/{id}/production   # Gestion production par ligne
/commande/{id}/livraison    # Saisie infos livraison
/commande/{id}/facture/new  # Génération facture
```

#### **Factures**  
```php
/facture                    # Index avec filtres et alertes
/facture/new               # Création (depuis commande/directe)
/facture/{id}              # Vue détaillée avec paiements
/facture/{id}/edit         # Modification (si brouillon)
/facture/{id}/pdf          # Génération PDF
/facture/{id}/envoyer      # Envoi email client
/facture/{id}/paiement/new # Saisie paiement
/facture/{id}/avoir/new    # Création avoir
```

#### **Avoirs**
```php  
/avoir                     # Index avec validations en attente
/avoir/new                 # Création (depuis facture)
/avoir/{id}                # Vue détaillée
/avoir/{id}/edit          # Modification (si brouillon)
/avoir/{id}/valider       # Validation managériale
/avoir/{id}/pdf           # Génération PDF
/avoir/{id}/remboursement # Saisie remboursement
```

### **5.3 Templates Design**

#### **Cohérence Visuelle**
- **Même charte** que devis existants
- **Couleurs par statut** : vert/orange/rouge
- **Icons FontAwesome** : fas fa-shopping-cart (commande), fas fa-file-invoice (facture), fas fa-undo (avoir)
- **Bootstrap 5** avec composants modernes

#### **Éléments Spécifiques**
```php  
STATUT BADGES:
- Commande: badge-primary (confirmée), badge-warning (production), badge-success (livrée)
- Facture: badge-secondary (brouillon), badge-info (envoyée), badge-success (payée)
- Avoir: badge-warning (brouillon), badge-success (validé), badge-danger (refusé)

TIMELINE WORKFLOW:
- Visualisation étapes avec points de passage
- Dates réelles vs prévisionnelles  
- Indicateurs de retard en rouge

TOTAUX ET SOLDES:
- Mise en évidence soldes restants
- Alertes visuelles impayés
- Graphiques simples CA/encaissements
```

---

## ⚙️ 6. DÉVELOPPEMENT TECHNIQUE

### **6.1 Structure des Contrôleurs**
```php
// NOUVEAUX CONTRÔLEURS
CommandeController::class
├── index()           # Liste avec filtres
├── show($id)         # Vue détaillée  
├── new()             # Création depuis devis
├── edit($id)         # Modification
├── production($id)   # Gestion production
├── livraison($id)    # Saisie livraison
└── genererFacture($id) # Création facture

FactureController::class  
├── index()           # Liste avec alertes impayés
├── show($id)         # Vue avec paiements/avoirs
├── new()             # Création
├── edit($id)         # Modification
├── pdf($id)          # Génération PDF
├── envoyer($id)      # Envoi email
├── paiement($id)     # Saisie paiement
└── genererAvoir($id) # Création avoir

AvoirController::class
├── index()           # Liste avec validations
├── show($id)         # Vue détaillée
├── new()             # Création depuis facture  
├── edit($id)         # Modification
├── valider($id)      # Approbation manager
├── pdf($id)          # Génération PDF
└── rembourser($id)   # Saisie remboursement
```

### **6.2 Services Métier**
```php
// NOUVEAUX SERVICES
CommandeService::class
├── creerDepuisDevis(Devis $devis): Commande
├── changerStatut(Commande $commande, string $statut): void
├── calculerDelaisPrevisionnels(Commande $commande): array
└── verifierCoherenceStatuts(Commande $commande): bool

FactureService::class
├── creerDepuisCommande(Commande $commande, array $items): Facture  
├── calculerSoldeRestant(Facture $facture): string
├── genererNumeroFacture(): string
├── marquerPayee(Facture $facture): void
└── verifierEcheance(Facture $facture): bool

AvoirService::class
├── creerDepuisFacture(Facture $facture, array $items, string $motif): Avoir
├── valider(Avoir $avoir, User $validateur): void  
├── impacterFacture(Avoir $avoir): void
├── calculerMontants(Avoir $avoir): void
└── verifierQuantitesDisponibles(Facture $facture, array $items): bool

WorkflowService::class (extension)
├── executerTransition(object $entity, string $from, string $to): void
├── getTransitionsPossibles(object $entity): array  
├── verifierDroitsTransition(object $entity, string $transition, User $user): bool
└── notifierChangementStatut(object $entity, string $ancienStatut): void
```

### **6.3 Formulaires Symfony**
```php
// NOUVEAUX FORMULAIRES
CommandeType::class
├── CommandeItemType (embedded collection)
├── Champs dates prévisionnelles  
├── Instructions livraison
└── Sélection transporteur

FactureType::class
├── FactureItemType (embedded collection)
├── Conditions paiement
├── Date échéance  
└── Mentions légales

AvoirType::class
├── AvoirItemType (embedded collection)
├── Sélection motif (choix)
├── Saisie motif détaillé
└── Quantités à créditer

PaiementType::class  
├── Montant avec validation
├── Mode paiement (choix)
├── Références bancaires
└── Date encaissement
```

### **6.4 Migrations Base de Données**
```php
// ORDRE D'EXÉCUTION DES MIGRATIONS
1. Migration001_CreateCommande.php
2. Migration002_CreateCommandeItem.php  
3. Migration003_CreateFacture.php
4. Migration004_CreateFactureItem.php
5. Migration005_CreateAvoir.php
6. Migration006_CreateAvoirItem.php
7. Migration007_CreatePaiement.php
8. Migration008_AddForeignKeys.php
9. Migration009_CreateIndexes.php
10. Migration010_UpdateExistingData.php

// CONTRAINTES IMPORTANTES
- Clés étrangères avec CASCADE appropriés
- Index sur numéros documents (uniques)
- Index sur statuts et dates (performances)
- Contraintes CHECK sur montants positifs
```

### **6.5 Tests Automatisés**
```php
// COUVERTURE TESTS REQUISE
tests/Entity/           # Tests unitaires entités
├── CommandeTest.php    # Relations et calculs
├── FactureTest.php     # Soldes et statuts
├── AvoirTest.php       # Validations métier
└── PaiementTest.php    # Contraintes

tests/Service/          # Tests services métier
├── CommandeServiceTest.php
├── FactureServiceTest.php  
├── AvoirServiceTest.php
└── WorkflowServiceTest.php

tests/Controller/       # Tests fonctionnels
├── CommandeControllerTest.php
├── FactureControllerTest.php
└── AvoirControllerTest.php

tests/Integration/      # Tests d'intégration  
├── WorkflowCompletTest.php  # Devis → Commande → Facture → Avoir
├── CalculMontantsTest.php   # Cohérence montants/soldes
└── SecurityTest.php         # Contrôles droits accès
```

---

## 📊 7. DONNÉES ET FIXTURES

### **7.1 Fixtures de Développement**
```php
// NOUVELLES FIXTURES À CRÉER
CommandeFixtures::class
├── 10 commandes différents statuts
├── Liens vers devis existants
├── Dates cohérentes avec workflow
└── CommandeItems avec productions variées

FactureFixtures::class
├── 15 factures (brouillon/envoyée/payée)
├── Liens commandes ou création directe
├── Échéances variées (courantes/dépassées)
└── FactureItems cohérents

AvoirFixtures::class  
├── 5 avoirs différents motifs
├── Statuts brouillon/validé/remboursé
├── Liens vers factures existantes
└── Montants partiels et totaux

PaiementFixtures::class
├── Paiements factures (partiels/complets)
├── Remboursements avoirs  
├── Modes paiement variés
└── Dates encaissement réalistes
```

### **7.2 Données de Configuration**
```php
// PARAMÈTRES SYSTÈME À AJOUTER
DELAIS_DEFAUT:
- delai_preparation: 3 jours
- delai_production: 7 jours  
- delai_expedition: 2 jours
- delai_paiement: 30 jours

MOTIFS_AVOIR:
- retour_marchandise: "Retour de marchandise"
- ristourne_commerciale: "Ristourne commerciale"
- erreur_facturation: "Erreur de facturation"  
- geste_commercial: "Geste commercial"
- annulation_partielle: "Annulation partielle"

MODES_PAIEMENT:
- cheque: "Chèque"
- virement: "Virement bancaire"
- carte_bancaire: "Carte bancaire"
- especes: "Espèces"
- prelevement: "Prélèvement automatique"

SEUILS_VALIDATION:
- montant_avoir_validation: 500.00 (euros)
- delai_relance_facture: 30 (jours)
- delai_contentieux: 90 (jours)
```

---

## 🚀 8. PLAN D'IMPLÉMENTATION

### **8.1 Phase 1 - Fondations (5 jours)**
```php
JOUR 1-2: ENTITÉS ET MIGRATIONS
- Création toutes les entités avec annotations
- Relations et contraintes de base  
- Migrations avec contraintes
- Tests unitaires entités

JOUR 3-4: SERVICES MÉTIER
- CommandeService avec conversions
- FactureService avec calculs
- AvoirService avec validations
- WorkflowService étendu

JOUR 5: FIXTURES ET DONNÉES
- Fixtures complètes pour tests
- Configuration paramètres système
- Données référentiels (motifs, modes)
- Validation cohérence données
```

### **8.2 Phase 2 - Interfaces (4 jours)**
```php
JOUR 6-7: CONTRÔLEURS ET FORMULAIRES  
- Contrôleurs CRUD complets
- Formulaires avec validation
- Actions spécialisées (validation, envoi)
- Gestion erreurs et sécurité

JOUR 8-9: TEMPLATES ET DESIGN
- Templates cohérents avec existant
- Composants réutilisables (statut, timeline)
- Responsive design mobile
- Tests navigation et UX
```

### **8.3 Phase 3 - Intégration (3 jours)**
```php
JOUR 10-11: WORKFLOW COMPLET
- Intégration Devis → Commande
- Liaison Commande → Facture  
- Création Facture → Avoir
- Tests bout-en-bout

JOUR 12: FINALISATION
- Dashboard étendu avec nouveaux widgets
- Exports PDF tous documents
- Emails notifications
- Tests de performance
- Documentation utilisateur
```

### **8.4 Phase 4 - Tests et Déploiement (2 jours)**
```php
JOUR 13: TESTS COMPLETS
- Tests automatisés complets
- Tests utilisateurs sur workflows
- Validation règles métier
- Corrections bugs identifiés

JOUR 14: MISE EN PRODUCTION
- Migration données production  
- Formation utilisateurs
- Surveillance post-déploiement
- Ajustements configuration
```

---

## ✅ 9. CRITÈRES DE VALIDATION

### **9.1 Fonctionnels**
- ✅ Workflow complet Devis → Commande → Facture → Avoir opérationnel
- ✅ Calculs automatiques corrects sur tous documents  
- ✅ Validation des avoirs avec approbation managériale
- ✅ Suivi paiements et soldes en temps réel
- ✅ Génération PDF professionnels tous documents
- ✅ Emails notifications automatiques  
- ✅ Dashboard étendu avec KPI temps réel

### **9.2 Techniques**  
- ✅ Performance < 1s sur toutes pages principales
- ✅ Sécurité : contrôles droits et validations métier
- ✅ Tests automatisés > 80% couverture code
- ✅ Base données optimisée avec index appropriés
- ✅ Code maintenable avec documentation complète
- ✅ Compatibilité mobile responsive
- ✅ Sauvegarde/restauration données validée

### **9.3 Métier**
- ✅ Respect règles comptables françaises
- ✅ Traçabilité complète des opérations  
- ✅ Cohérence montants et statuts garantie
- ✅ Gestion erreurs et cas exceptionnels
- ✅ Évolutivité pour modules futurs
- ✅ Formation utilisateurs réalisée
- ✅ Documentation administrative complète

---

## 📞 10. SUPPORT ET MAINTENANCE

### **10.1 Documentation**
- **Manuel utilisateur** avec captures d'écran
- **Guide administrateur** paramétrage système  
- **Documentation technique** développeurs
- **Procédures de sauvegarde** et restauration

### **10.2 Formation**
- **Session formation** utilisateurs finaux (2h)
- **Formation administrateurs** système (1h)  
- **Support téléphonique** 3 mois post-déploiement
- **Webinaires** nouvelles fonctionnalités

### **10.3 Évolutions Futures**
- **Module comptabilité** (écritures automatiques)
- **Module stock** (gestion inventaires)  
- **API REST** (intégrations externes)
- **Application mobile** commerciaux
- **BI/Reporting** avancé avec graphiques

---

**FIN DU DOCUMENT DE SPÉCIFICATIONS**

*Ce document constitue la base contractuelle pour le développement du module Workflow Commercial Complet de TechnoProd. Toute modification doit faire l'objet d'un avenant validé par les parties.*

**Version :** 1.0  
**Date :** 22 Juillet 2025  
**Pages :** 23  
**Validation :** En attente retours client