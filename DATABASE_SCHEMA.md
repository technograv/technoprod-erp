# Architecture de la base de données - Phase 1 MVP

## Entités principales

### 1. User (Utilisateurs)
- id (PK)
- email (unique)
- password (hashed)
- nom
- prenom
- roles (JSON) - ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_COMMERCIAL', 'ROLE_PRODUCTION', 'ROLE_COMPTABILITE']
- is_active
- created_at
- updated_at

### 2. Client (Entreprises)
- id (PK)
- nom_entreprise
- siret
- code_client (unique)
- secteur_id (FK)
- contact_defaut_id (FK, nullable)
- adresse_defaut_id (FK, nullable)
- commercial_id (FK) - User assigné
- is_active
- notes
- created_at
- updated_at

### 3. Contact (Personnes physiques)
- id (PK)
- client_id (FK)
- nom
- prenom
- fonction
- email
- telephone
- telephone_mobile
- is_defaut (boolean)
- created_at
- updated_at

### 4. Adresse (Adresses multiples par client)
- id (PK)
- client_id (FK)
- type_adresse (string) - 'facturation', 'livraison', 'siege_social', 'autre'
- nom_lieu (optionnel pour identifier l'adresse)
- adresse_ligne1
- adresse_ligne2
- code_postal
- ville
- pays (défaut: 'France')
- is_defaut (boolean)
- created_at
- updated_at

### 5. Secteur (Secteurs commerciaux)
- id (PK)
- nom_secteur
- commercial_id (FK) - User assigné
- couleur_hex (pour affichage carte/planning)
- is_active
- created_at
- updated_at

### 6. SecteurZone (Codes postaux par secteur)
- id (PK)
- secteur_id (FK)
- code_postal
- ville (optionnel)
- created_at

### 7. Devis (Devis simples)
- id (PK)
- numero_devis (unique, auto-généré)
- client_id (FK)
- contact_id (FK)
- adresse_facturation_id (FK)
- adresse_livraison_id (FK)
- commercial_id (FK)
- date_creation
- date_validite
- statut ('brouillon', 'envoye', 'valide', 'refuse', 'expire')
- total_ht
- total_tva
- total_ttc
- remise_globale_percent
- remise_globale_montant
- notes_internes
- notes_client
- created_at
- updated_at

### 8. DevisItem (Lignes de devis)
- id (PK)
- devis_id (FK)
- designation
- description
- quantite
- prix_unitaire_ht
- remise_percent
- remise_montant
- total_ligne_ht
- tva_percent (défaut: 20)
- ordre_affichage
- created_at
- updated_at

## Relations principales

- User 1:n Secteur (commercial assigné)
- User 1:n Client (commercial assigné)
- User 1:n Devis (commercial assigné)
- Client 1:n Contact
- Client 1:n Adresse
- Client 1:1 Contact (défaut)
- Client 1:1 Adresse (défaut)
- Client n:1 Secteur
- Secteur 1:n SecteurZone
- Client 1:n Devis
- Devis 1:n DevisItem
- Devis n:1 Contact
- Devis n:1 Adresse (facturation)
- Devis n:1 Adresse (livraison)

## Index recommandés

- Client: code_client, siret, secteur_id, commercial_id
- Contact: client_id, email, is_defaut
- Adresse: client_id, code_postal, is_defaut
- SecteurZone: secteur_id, code_postal
- Devis: client_id, commercial_id, numero_devis, statut
- DevisItem: devis_id, ordre_affichage

## Contraintes

- Un seul contact par défaut par client
- Une seule adresse par défaut par client
- Un seul secteur par code postal
- Numéro de devis unique avec format : YYYY-NNNN (ex: 2025-0001)