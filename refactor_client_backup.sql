-- =====================================================
-- REFACTORING CLIENT - SCRIPT DE SAUVEGARDE ET ANALYSE
-- Date: 2025-09-19
-- Objectif: Éliminer les redondances dans la table client
-- =====================================================

-- 1. SAUVEGARDE DES DONNÉES ACTUELLES
-- ===================================

-- Sauvegarde complète de la table client avant modifications
CREATE TABLE client_backup_20250919 AS
SELECT * FROM client;

-- Vérification du nombre de lignes sauvegardées
SELECT 'BACKUP CREATED' as status, COUNT(*) as total_clients FROM client_backup_20250919;

-- 2. ANALYSE DES DONNÉES EXISTANTES
-- ================================

-- Clients sans contact principal
SELECT 'CLIENTS_SANS_CONTACT' as type, COUNT(*) as count
FROM client c
LEFT JOIN contact ct ON c.id = ct.client_id
WHERE ct.id IS NULL;

-- Clients avec données personnelles renseignées
SELECT 'CLIENTS_AVEC_DONNEES_PERSO' as type, COUNT(*) as count
FROM client
WHERE nom IS NOT NULL 
   OR prenom IS NOT NULL 
   OR email IS NOT NULL 
   OR telephone IS NOT NULL 
   OR civilite IS NOT NULL;

-- Détail des clients avec données personnelles
SELECT 
    id,
    code,
    nom,
    prenom,
    email,
    telephone,
    civilite,
    nom_entreprise,
    forme_juridique_id,
    CASE 
        WHEN nom IS NOT NULL OR prenom IS NOT NULL THEN 'HAS_PERSONAL_DATA'
        ELSE 'NO_PERSONAL_DATA'
    END as data_status
FROM client
WHERE nom IS NOT NULL 
   OR prenom IS NOT NULL 
   OR email IS NOT NULL 
   OR telephone IS NOT NULL 
   OR civilite IS NOT NULL
ORDER BY id;

-- Clients avec plusieurs contacts
SELECT 
    c.id,
    c.code,
    COUNT(ct.id) as nb_contacts,
    STRING_AGG(CONCAT(ct.nom, ' ', ct.prenom), '; ') as contacts_list
FROM client c
LEFT JOIN contact ct ON c.id = ct.client_id
GROUP BY c.id, c.code
HAVING COUNT(ct.id) > 1
ORDER BY nb_contacts DESC;

-- Vérification cohérence actif vs is_active
SELECT 'INCOHERENCE_ACTIF' as type, COUNT(*) as count
FROM client
WHERE actif != is_active;

-- =====================================================
-- RAPPORT DE STATUT
-- =====================================================

SELECT 
    'TOTAL_CLIENTS' as metric,
    COUNT(*) as value
FROM client

UNION ALL

SELECT 
    'CLIENTS_AVEC_DONNEES_REDONDANTES' as metric,
    COUNT(*) as value
FROM client
WHERE nom IS NOT NULL 
   OR prenom IS NOT NULL 
   OR email IS NOT NULL 
   OR telephone IS NOT NULL 
   OR civilite IS NOT NULL

UNION ALL

SELECT 
    'TOTAL_CONTACTS' as metric,
    COUNT(*) as value
FROM contact

UNION ALL

SELECT 
    'CLIENTS_SANS_CONTACT' as metric,
    COUNT(*) as value
FROM client c
LEFT JOIN contact ct ON c.id = ct.client_id
WHERE ct.id IS NULL;