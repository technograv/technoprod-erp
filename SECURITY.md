# 🔐 SÉCURITÉ - TechnoProd ERP

## Configuration des clés API

### ⚠️ RÈGLES IMPORTANTES

1. **JAMAIS de clés API dans les fichiers versionnés** (`.env` public)
2. **Toujours utiliser `.env.local`** pour les clés sensibles
3. **Vérifier avant chaque commit** avec le script de sécurité

### 📁 Fichiers de configuration

- **`.env`** : Configuration par défaut (VERSIONNÉ) - Pas de secrets !
- **`.env.local`** : Configuration locale (NON VERSIONNÉ) - Vos vraies clés
- **`.env.prod`** : Production (NON VERSIONNÉ)

### 🔑 Clés API requises

Configurer dans `.env.local` :

```bash
# Google Maps API Key
GOOGLE_MAPS_API_KEY=your_google_maps_key

# Gmail API (optionnel)
MAILER_DSN=smtp://smtp.gmail.com:587?encryption=tls&auth_mode=login&username=your-email@gmail.com&password=your-app-password
```

## Scripts de sécurité

### Vérification manuelle
```bash
./scripts/check-security.sh
```

### Hook automatique
Un hook pre-commit vérifie automatiquement la sécurité avant chaque commit.

### Désactiver temporairement (DANGER)
```bash
git commit --no-verify -m "message"
```

## En cas d'exposition accidentelle

1. **Révoquer immédiatement** la clé exposée
2. **Générer une nouvelle clé**
3. **Nettoyer l'historique Git** si nécessaire
4. **Mettre à jour `.env.local`** avec la nouvelle clé

## URLs importantes

- **Google Cloud Console** : https://console.cloud.google.com/apis/credentials
- **GitGuardian** : Surveillance automatique des secrets exposés

---

*Généré automatiquement - TechnoProd ERP Security*