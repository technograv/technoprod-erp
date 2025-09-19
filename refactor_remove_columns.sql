-- =====================================================
-- PHASE 3: SUPPRESSION DES COLONNES REDONDANTES
-- =====================================================

-- ATTENTION: Vérifications avant suppression
-- ==========================================

-- 1. Vérifier que tous les clients ont des contacts par défaut
SELECT 'VERIFICATION_CONTACTS_DEFAUT' as check_name,
       COUNT(*) as clients_sans_contact_defaut
FROM client 
WHERE contact_facturation_default_id IS NULL 
   OR contact_livraison_default_id IS NULL;

-- 2. Vérifier que les données sont bien dans les contacts
SELECT 'VERIFICATION_DONNEES_MIGREES' as check_name,
       COUNT(*) as clients_avec_donnees_dans_contact
FROM client c
INNER JOIN contact ct ON c.id = ct.client_id 
WHERE (ct.nom IS NOT NULL OR ct.prenom IS NOT NULL OR ct.email IS NOT NULL OR ct.telephone IS NOT NULL);

-- Si tout est OK, procéder à la suppression
-- =========================================

-- Supprimer les colonnes redondantes avec Contact
ALTER TABLE client DROP COLUMN IF EXISTS nom;
ALTER TABLE client DROP COLUMN IF EXISTS prenom; 
ALTER TABLE client DROP COLUMN IF EXISTS civilite;
ALTER TABLE client DROP COLUMN IF EXISTS email;
ALTER TABLE client DROP COLUMN IF EXISTS telephone;

-- Supprimer la colonne redondante interne
ALTER TABLE client DROP COLUMN IF EXISTS is_active;

-- Vérifications post-suppression
-- ==============================

-- Lister les colonnes restantes
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'client' 
ORDER BY ordinal_position;