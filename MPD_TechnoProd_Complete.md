# MPD TechnoProd - Modèle Physique de Données Complet
**Date de génération:** 2025-09-19  
**Objectif:** Identification des redondances et optimisation de l'architecture

---

## 📋 TABLES PRINCIPALES

### 🏢 CLIENT (Table centrale)
```sql
id                             integer         NOT NULL    [PK]
commercial_id                  integer         NULL        [FK → user]
secteur_id                     integer         NULL        [FK → secteur] 
code                          varchar(255)     NOT NULL    
famille                       varchar(255)     NULL        
civilite                      varchar(255)     NULL        ⚠️ REDONDANT
nom                           varchar(255)     NULL        ⚠️ REDONDANT
prenom                        varchar(255)     NULL        ⚠️ REDONDANT
statut                        varchar(255)     NOT NULL    
regime_comptable              varchar(255)     NULL        
mode_paiement                 varchar(255)     NULL        
delai_paiement                integer         NULL        
taux_tva                      numeric         NULL        
assujetti_tva                 boolean         NULL        
conditions_tarifs             varchar(255)     NULL        
notes                         text            NULL        
is_active                     boolean         NOT NULL    
created_at                    timestamp       NOT NULL    
updated_at                    timestamp       NOT NULL    
date_conversion_client        timestamp       NULL        
email                         varchar(255)     NULL        ⚠️ REDONDANT
telephone                     varchar(255)     NULL        ⚠️ REDONDANT
nom_entreprise                varchar(255)     NULL        ✅ CORRECT
contact_facturation_default_id integer        NULL        [FK → contact]
contact_livraison_default_id  integer         NULL        [FK → contact]
forme_juridique_id            integer         NULL        [FK → forme_juridique]
actif                         boolean         NOT NULL    
derniere_visite               date            NULL        
chiffre_affaire_annuel        numeric         NULL        
```

### 👤 CONTACT (Informations personnelles)
```sql
id                           integer         NOT NULL    [PK]
client_id                    integer         NOT NULL    [FK → client]
nom                          varchar(255)     NULL        ✅ CORRECT ICI
prenom                       varchar(255)     NULL        ✅ CORRECT ICI
fonction                     varchar(255)     NULL        
email                        varchar(255)     NULL        ✅ CORRECT ICI
telephone                    varchar(255)     NULL        ✅ CORRECT ICI
telephone_mobile             varchar(255)     NULL        
created_at                   timestamp       NOT NULL    
updated_at                   timestamp       NOT NULL    
civilite                     varchar(255)     NULL        ✅ CORRECT ICI
fax                          varchar(255)     NULL        
is_facturation_default       boolean         NOT NULL    
is_livraison_default         boolean         NOT NULL    
adresse_id                   integer         NULL        [FK → adresse]
```

### 🏠 ADRESSE (Informations géographiques)
```sql
id                           integer         NOT NULL    [PK]
client_id                    integer         NOT NULL    [FK → client]
ligne1                       varchar(255)     NOT NULL    ✅ CORRECT ICI
ligne2                       varchar(255)     NULL        
ligne3                       varchar(255)     NULL        
code_postal                  varchar(255)     NOT NULL    ✅ CORRECT ICI
ville                        varchar(255)     NOT NULL    ✅ CORRECT ICI
pays                         varchar(255)     NULL        
created_at                   timestamp       NOT NULL    
updated_at                   timestamp       NOT NULL    
nom                          varchar(255)     NOT NULL    
deleted_at                   timestamp       NULL        
```

### ⚖️ FORME_JURIDIQUE (Référentiel)
```sql
id                           integer         NOT NULL    [PK]
nom                          varchar(255)     NOT NULL    ✅ CORRECT (SAS, SARL, etc.)
template_formulaire          varchar(255)     NOT NULL    
actif                        boolean         NOT NULL    
created_at                   timestamp       NOT NULL    
updated_at                   timestamp       NOT NULL    
ordre                        integer         NOT NULL    
forme_par_defaut            boolean         NOT NULL    
```

---

## 📊 TABLES MÉTIER (Ventes)

### 💰 DEVIS
```sql
id                           integer         NOT NULL    [PK]
client_id                    integer         NOT NULL    [FK → client]
contact_facturation_id       integer         NULL        [FK → contact]
adresse_facturation_id       integer         NULL        [FK → adresse]
adresse_livraison_id         integer         NULL        [FK → adresse]
commercial_id                integer         NOT NULL    [FK → user]
numero_devis                 varchar(255)     NOT NULL    
date_creation                date            NOT NULL    
date_validite                date            NOT NULL    
statut                       varchar(255)     NOT NULL    
total_ht                     numeric         NOT NULL    
total_tva                    numeric         NOT NULL    
total_ttc                    numeric         NOT NULL    
contact_livraison_id         integer         NULL        [FK → contact]
-- Champs signature électronique
date_signature               date            NULL        
signature_nom                varchar(255)     NULL        
signature_email              varchar(255)     NULL        
signature_data               text            NULL        
-- Champs métier
acompte_percent              numeric         NULL        
remise_globale_percent       numeric         NULL        
notes_internes               text            NULL        
notes_client                 text            NULL        
nom_projet                   varchar(255)     NULL        
```

### 🧾 FACTURE
```sql
id                           integer         NOT NULL    [PK]
commande_id                  integer         NOT NULL    [FK → commande]
client_id                    integer         NOT NULL    [FK → client]
contact_id                   integer         NULL        [FK → contact]
commercial_id                integer         NOT NULL    [FK → user]
numero_facture               varchar(255)     NOT NULL    
date_facture                 date            NOT NULL    
statut                       varchar(255)     NOT NULL    
total_ht                     numeric         NOT NULL    
total_tva                    numeric         NOT NULL    
total_ttc                    numeric         NOT NULL    
montant_paye                 numeric         NOT NULL    
montant_restant              numeric         NOT NULL    
```

### 📦 PRODUIT
```sql
id                           integer         NOT NULL    [PK]
designation                  varchar(255)     NOT NULL    
description                  text            NULL        
reference                    varchar(255)     NOT NULL    
prix_achat_ht                numeric         NOT NULL    
prix_vente_ht                numeric         NOT NULL    
tva_percent                  numeric         NOT NULL    
unite                        varchar(255)     NOT NULL    
type                         varchar(255)     NOT NULL    
actif                        boolean         NOT NULL    
gestion_stock                boolean         NOT NULL    
stock_quantite               integer         NULL        
```

---

## ⚠️ PROBLÈMES IDENTIFIÉS

### 🔴 REDONDANCES DANS CLIENT

**Champs redondants** (présents à la fois dans `client` ET `contact`) :
- ❌ `civilite` → existe dans `contact.civilite`
- ❌ `nom` → existe dans `contact.nom`  
- ❌ `prenom` → existe dans `contact.prenom`
- ❌ `email` → existe dans `contact.email`
- ❌ `telephone` → existe dans `contact.telephone`

**Impact:** 
- Incohérence de données possible
- Maintenance complexe
- Logique métier dispersée

---

## ✅ SOLUTION RECOMMANDÉE

### 🎯 ARCHITECTURE CIBLE

**Table CLIENT (épurée) :**
```sql
-- GARDER
id                             integer         NOT NULL    [PK]
code                          varchar(255)     NOT NULL    ✅
nom_entreprise                varchar(255)     NULL        ✅ (dénomination sociale)
forme_juridique_id            integer         NULL        [FK] ✅
statut                        varchar(255)     NOT NULL    ✅
notes                         text            NULL        ✅
conditions_tarifs             varchar(255)     NULL        ✅
commercial_id                 integer         NULL        [FK] ✅
secteur_id                    integer         NULL        [FK] ✅

-- RELATIONS PAR DÉFAUT
contact_facturation_default_id integer        NULL        [FK] ✅
contact_livraison_default_id   integer        NULL        [FK] ✅

-- MÉTADONNÉES
regime_comptable              varchar(255)     NULL        ✅
mode_paiement                 varchar(255)     NULL        ✅
delai_paiement                integer         NULL        ✅
taux_tva                      numeric         NULL        ✅
assujetti_tva                 boolean         NULL        ✅
actif                         boolean         NOT NULL    ✅
created_at                    timestamp       NOT NULL    ✅
updated_at                    timestamp       NOT NULL    ✅

-- SUPPRIMER (redondants avec CONTACT)
civilite                      ❌ → contact.civilite
nom                           ❌ → contact.nom
prenom                        ❌ → contact.prenom  
email                         ❌ → contact.email
telephone                     ❌ → contact.telephone
```

### 🔄 MIGRATION DES DONNÉES

**Étapes :**
1. **Créer contacts manquants** pour clients n'en ayant pas
2. **Migrer les données** `client.nom/prenom/email/telephone` → `contact`
3. **Définir les contacts par défaut** 
4. **Supprimer les colonnes** redondantes
5. **Adapter les entités Symfony**
6. **Mettre à jour tous les controllers/templates**

### 🏗️ MÉTHODES D'ACCÈS

**Dans l'entité Client :**
```php
// Accès via relations
public function getNomContact(): ?string {
    return $this->getContactFacturationDefault()?->getNom();
}

public function getEmailContact(): ?string {
    return $this->getContactFacturationDefault()?->getEmail();
}

public function getTelephoneContact(): ?string {
    return $this->getContactFacturationDefault()?->getTelephone();
}
```

---

## 🚀 PLAN D'ACTION

### Phase 1: Analyse et préparation
- [x] MPD complet généré
- [ ] Validation architecture cible
- [ ] Script de migration des données

### Phase 2: Migration base de données  
- [ ] Sauvegarde complète
- [ ] Migration données vers contacts
- [ ] Suppression colonnes redondantes
- [ ] Tests intégrité

### Phase 3: Adaptation code
- [ ] Modification entités Doctrine
- [ ] Adaptation controllers
- [ ] Mise à jour templates
- [ ] Tests fonctionnels

**Voulez-vous que je procède à la Phase 1 complète ?**