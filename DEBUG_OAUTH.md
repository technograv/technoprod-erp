# Debug OAuth - Solutions pour invalid_grant

## 🔍 Étapes de debug

### 1. Vérifier les logs
Après avoir tenté une connexion, consultez les logs :
```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log  
# ou les logs PHP selon votre configuration
```

### 2. Solutions courantes pour `invalid_grant`

#### Solution A : Recréer les clés OAuth
1. Allez dans Google Cloud Console
2. **APIs & Services** → **Credentials**
3. Supprimez l'ancien client OAuth
4. Recréez un nouveau client OAuth avec :
   - Type : Web application
   - Authorized redirect URIs : `http://test.decorpub.fr:8080/connect/google/check`

#### Solution B : Vérifier la configuration
Vérifiez que dans Google Cloud Console :
- L'écran de consentement OAuth est configuré
- Les APIs "Google+ API" et "People API" sont activées
- L'URL de redirection est **exactement** : `http://test.decorpub.fr:8080/connect/google/check`

#### Solution C : Problème de domaine
Si le sous-domaine pose problème, testez avec l'IP + hosts :
1. Ajoutez dans `/etc/hosts` : `172.17.4.210 oauth-test.local`
2. Configurez Google avec : `http://oauth-test.local:8080/connect/google/check`

#### Solution D : Configuration Symfony
Vérifiez le fichier de config OAuth :

```yaml
# config/packages/knpu_oauth2_client.yaml
knpu_oauth2_client:
    clients:
        google:
            type: google
            client_id: '%env(GOOGLE_OAUTH_CLIENT_ID)%'
            client_secret: '%env(GOOGLE_OAUTH_CLIENT_SECRET)%'
            redirect_route: connect_google_check
            redirect_params: {}
            use_state: true
```

## 🔧 Test manual

### URL de test direct
Testez cette URL dans votre navigateur (remplacez CLIENT_ID) :

```
https://accounts.google.com/o/oauth2/auth?client_id=249270781829-1eorf05uiu4n1qr83m3n18n3naai54s1.apps.googleusercontent.com&redirect_uri=http://test.decorpub.fr:8080/connect/google/check&scope=openid+email+profile&response_type=code&state=random_state_string
```

### Code d'autorisation
Si l'URL ci-dessus fonctionne et vous redirige vers test.decorpub.fr avec un paramètre `code`, alors le problème est dans notre code PHP.

## 🚨 Solutions immédiates

### Option 1 : Reconfiguration rapide
```bash
# 1. Supprimez les variables OAuth
# 2. Recréez un client OAuth dans Google Cloud Console
# 3. Mettez les nouvelles clés dans .env.local
```

### Option 2 : Localhost temporaire
Utilisez `localhost` temporairement :
1. Modifiez dans Google : `http://localhost:8080/connect/google/check`
2. Testez depuis votre machine locale

### Option 3 : Mode debug avancé
Activez le mode debug Symfony pour voir les erreurs détaillées :
```bash
# Dans .env.local
APP_DEBUG=true
```

## ✅ Checklist de vérification

- [ ] URLs de redirection identiques (Google Cloud ↔ .env.local)
- [ ] APIs Google activées (Google+ et People API)
- [ ] Écran de consentement OAuth configuré
- [ ] Domaine test.decorpub.fr accessible
- [ ] Logs consultés pour erreurs détaillées
- [ ] Test avec une URL manuelle

---
**Si le problème persiste, recréez complètement les clés OAuth dans Google Cloud Console.**