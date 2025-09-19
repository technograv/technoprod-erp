-- =====================================================
-- PHASE 2: MIGRATION DES DONNÉES VERS CONTACT
-- =====================================================

-- 1. CRÉER DES CONTACTS POUR LES CLIENTS QUI N'EN ONT PAS
-- =======================================================

-- Pour les clients sans contact ET avec des données personnelles
INSERT INTO contact (
    client_id,
    nom,
    prenom,
    civilite,
    email,
    telephone,
    is_facturation_default,
    is_livraison_default,
    created_at,
    updated_at
)
SELECT 
    c.id as client_id,
    COALESCE(c.nom, 'Contact') as nom,
    c.prenom,
    c.civilite,
    c.email,
    c.telephone,
    true as is_facturation_default,  -- Premier contact = défaut
    true as is_livraison_default,    -- Premier contact = défaut
    NOW() as created_at,
    NOW() as updated_at
FROM client c
LEFT JOIN contact ct ON c.id = ct.client_id
WHERE ct.id IS NULL  -- Pas de contact existant
AND (
    c.nom IS NOT NULL 
    OR c.prenom IS NOT NULL 
    OR c.email IS NOT NULL 
    OR c.telephone IS NOT NULL 
    OR c.civilite IS NOT NULL
);

-- 2. METTRE À JOUR LES CONTACTS EXISTANTS AVEC LES DONNÉES DU CLIENT
-- ==================================================================

-- Mise à jour des contacts existants si le client a des données plus récentes
UPDATE contact 
SET 
    nom = COALESCE(NULLIF(contact.nom, ''), client.nom, contact.nom),
    prenom = COALESCE(NULLIF(contact.prenom, ''), client.prenom, contact.prenom), 
    civilite = COALESCE(NULLIF(contact.civilite, ''), client.civilite, contact.civilite),
    email = COALESCE(NULLIF(contact.email, ''), client.email, contact.email),
    telephone = COALESCE(NULLIF(contact.telephone, ''), client.telephone, contact.telephone),
    updated_at = NOW()
FROM client
WHERE contact.client_id = client.id
AND (
    client.nom IS NOT NULL 
    OR client.prenom IS NOT NULL 
    OR client.email IS NOT NULL 
    OR client.telephone IS NOT NULL 
    OR client.civilite IS NOT NULL
);

-- 3. DÉFINIR LES CONTACTS PAR DÉFAUT POUR LES CLIENTS
-- ===================================================

-- Mettre à jour contact_facturation_default_id
UPDATE client 
SET contact_facturation_default_id = (
    SELECT ct.id 
    FROM contact ct 
    WHERE ct.client_id = client.id 
    AND ct.is_facturation_default = true 
    LIMIT 1
)
WHERE contact_facturation_default_id IS NULL;

-- Mettre à jour contact_livraison_default_id  
UPDATE client 
SET contact_livraison_default_id = (
    SELECT ct.id 
    FROM contact ct 
    WHERE ct.client_id = client.id 
    AND ct.is_livraison_default = true 
    LIMIT 1
)
WHERE contact_livraison_default_id IS NULL;

-- 4. VÉRIFICATIONS POST-MIGRATION
-- ===============================

-- Compter les nouveaux contacts créés
SELECT 'CONTACTS_CREES' as action, COUNT(*) as count
FROM contact 
WHERE created_at >= CURRENT_DATE;

-- Vérifier que tous les clients ont maintenant un contact
SELECT 'CLIENTS_SANS_CONTACT_APRES' as status, COUNT(*) as count
FROM client c
LEFT JOIN contact ct ON c.id = ct.client_id
WHERE ct.id IS NULL;