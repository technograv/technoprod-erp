# ✅ Checklist de Conformité Comptable - TechnoProd

## 🚀 Checklist Pré-Déploiement (OBLIGATOIRE)

### ⚡ Tests Rapides (5 minutes)
- [ ] `php bin/console app:test-compliance` → Score = 100% ✅
- [ ] Vérification clés RSA disponibles
- [ ] Test intégrité d'un document
- [ ] Vérification chaîne d'audit intègre

### 🧮 Tests Comptables Complets (10 minutes)
- [ ] `php bin/console app:test-comptabilite` → Cœur système conforme
- [ ] Plan comptable : 77 comptes actifs
- [ ] Journaux : 6 journaux obligatoires opérationnels
- [ ] Balance générale équilibrée
- [ ] Export FEC fonctionnel

### 📋 Vérifications Manuelles
- [ ] Backup des clés RSA (`/var/crypto/`)
- [ ] Logs d'erreur vides (`/var/log/`)
- [ ] Base de données accessible
- [ ] Services comptables démarrés

## 🔄 Checklist Post-Modification Comptable

### Après modification des entités comptables
- [ ] `php bin/console doctrine:migrations:migrate`
- [ ] `php bin/console app:pcg:initialiser` (si nécessaire)
- [ ] `php bin/console app:test-comptabilite`
- [ ] Vérification équilibre débit/crédit

### Après modification services comptables
- [ ] Tests unitaires spécifiques
- [ ] `php bin/console app:test-compliance`
- [ ] Vérification audit trail
- [ ] Test génération FEC

## 🛡️ Checklist Sécurité NF203

### Intégrité cryptographique
- [ ] Clés RSA-2048 présentes et valides
- [ ] Signatures numériques fonctionnelles
- [ ] Hachage SHA-256 opérationnel
- [ ] Pas de rupture chaîne audit

### En cas de problème chaîne audit
- [ ] `php bin/console app:rebuild-audit-chain`
- [ ] Vérification intégrité post-reconstruction
- [ ] Documentation de l'incident
- [ ] Re-test complet

## 📊 Checklist Mensuelle

### Contrôles de routine
- [ ] Exécution `app:test-compliance`
- [ ] Vérification espace disque logs
- [ ] Contrôle performances requêtes comptables
- [ ] Vérification totaux comptables cohérents

### Maintenance préventive
- [ ] Nettoyage logs anciens (>3 mois)
- [ ] Sauvegarde clés cryptographiques
- [ ] Mise à jour documentation conformité
- [ ] Formation équipe sur nouveautés

## 🎯 Checklist Préparation 2026

### Facture électronique obligatoire
- [ ] Service FacturXService testé
- [ ] Génération PDF/A-3 + XML CII validée
- [ ] Tests avec tous profils Factur-X
- [ ] Formation équipe prévue Q4 2025

### Mise en conformité finale
- [ ] Validation avec expert-comptable
- [ ] Tests avec logiciels tiers (EDI)
- [ ] Documentation utilisateur finalisée
- [ ] Procédures de support définies

## 🚨 Actions en Cas d'Échec de Conformité

### Score < 100% aux tests
1. **STOP** - Ne pas déployer
2. Analyser logs détaillés : `--verbose-errors`
3. Identifier la cause exacte
4. Appliquer correction spécifique
5. Re-tester jusqu'à 100%
6. Documenter la correction

### Rupture chaîne audit détectée
1. **URGENCE** - Conformité compromise
2. Exécuter `app:rebuild-audit-chain`
3. Vérifier intégrité post-reconstruction
4. Identifier cause racine
5. Corriger pour éviter récurrence

### Erreurs FEC ou comptables
1. Vérifier cohérence écritures
2. Contrôler équilibre débit/crédit
3. Valider plan comptable
4. Re-générer états comptables
5. Tester avec données réelles

## 📞 Contacts d'Urgence

### En cas de problème critique
- **Expert-comptable :** [À définir]
- **Support technique :** [À définir]
- **Responsable conformité :** [À définir]

### Ressources techniques
- **Documentation :** `ARCHITECTURE_CONFORMITE_COMPTABLE.md`
- **Suivi :** `CONFORMITE_COMPTABLE_SUIVI.md`
- **Code source :** `src/Command/TestComplianceCommand.php`

---

## 📝 Historique des Vérifications

| Date | Version | Tests | Score | Commentaires |
|------|---------|-------|-------|--------------|
| 24/07/2025 | v1.0 | Complets | 100% | Conformité initiale atteinte |
| _À compléter_ | | | | |

---

**⚠️ RAPPEL IMPORTANT :** Cette checklist DOIT être suivie rigoureusement. La conformité comptable est une obligation légale. Aucun déploiement ne doit être effectué sans validation 100% des tests de conformité.

*Checklist créée le 24/07/2025 - À utiliser avant chaque release*