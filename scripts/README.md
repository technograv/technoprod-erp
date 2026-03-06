# Scripts de Sécurité TechnoProd

## check-security.sh

Script de scan automatique des secrets avant commit Git.

### Installation

Le hook pre-commit est déjà installé automatiquement :
```bash
ln -sf ../../scripts/check-security.sh .git/hooks/pre-commit
```

### Utilisation

Le script s'exécute automatiquement à chaque `git commit`. Il :

1. **Scanne les fichiers staged** pour détecter :
   - APP_SECRET avec valeurs réelles
   - Mots de passe PostgreSQL/MySQL dans les DSN
   - Google API Keys
   - OAuth Secrets
   - JWT Secrets
   - Autres patterns de secrets

2. **Bloque le commit** si des secrets critiques sont détectés

3. **Avertit** si des patterns suspects sont trouvés (nécessite validation manuelle)

### Tests manuels

```bash
# Tester le script sans commit
./scripts/check-security.sh

# Désactiver temporairement (déconseillé)
git commit --no-verify
```

### Patterns détectés

**Critiques (bloquent le commit) :**
- `APP_SECRET=[a-f0-9]{64}` - APP_SECRET Symfony
- `postgresql://user:PASSWORD@host` - DSN PostgreSQL avec mot de passe
- `mysql://user:PASSWORD@host` - DSN MySQL avec mot de passe
- `GOOGLE.*API.*KEY=...` - Google API Keys
- `OAUTH.*SECRET=...` - OAuth Secrets
- Fichier `.env` staged

**Avertissements (demandent confirmation) :**
- `password="..."` - Mots de passe en clair
- `token="..."` - Tokens
- `api_key="..."` - API Keys génériques

### Bonnes pratiques

1. **Utilisez .env.local** pour les secrets réels (jamais versionné)
2. **Utilisez des placeholders** dans .env (versionné)
3. **Documentez les variables** dans .env.local.example
4. **Rotez les secrets** si exposition accidentelle

### Désinstallation

```bash
rm .git/hooks/pre-commit
```
