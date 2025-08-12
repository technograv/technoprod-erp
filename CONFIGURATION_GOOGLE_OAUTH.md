# Configuration Google OAuth - TechnoProd

## 🎯 Objectif
Permettre l'authentification via Google Workspace pour les domaines autorisés : decorpub.fr, technograv.fr, pimpanelo.fr, technoburo.fr, pimpanelo.com

## 📋 Étapes de configuration

### 1. Créer un projet Google Cloud

1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créez un nouveau projet ou sélectionnez un projet existant
3. Nommez le projet (ex: "TechnoProd-OAuth")

### 2. Activer l'API Google+

1. Dans le menu de navigation → **APIs & Services** → **Library**
2. Recherchez "Google+ API" et activez-la
3. Recherchez "People API" et activez-la également

### 3. Configurer l'écran de consentement OAuth

1. **APIs & Services** → **OAuth consent screen**
2. Choisissez **External** (sauf si vous avez Google Workspace)
3. Remplissez les informations :
   - **App name**: TechnoProd ERP
   - **User support email**: nicolas.michel@decorpub.fr
   - **Developer contact email**: nicolas.michel@decorpub.fr
   - **Authorized domains**: decorpub.fr, technograv.fr, pimpanelo.fr, technoburo.fr, pimpanelo.com

### 4. Créer les identifiants OAuth

1. **APIs & Services** → **Credentials**
2. Cliquez sur **+ CREATE CREDENTIALS** → **OAuth 2.0 Client IDs**
3. Type d'application : **Web application**
4. Nom : "TechnoProd OAuth Client"
5. **Authorized redirect URIs** :
   - `http://172.17.4.210:8080/connect/google/check` (pour les tests)
   - `https://votre-domaine.com/connect/google/check` (pour la production)

### 5. Récupérer les clés

Après création, vous obtiendrez :
- **Client ID** : `xxxxx.apps.googleusercontent.com`
- **Client Secret** : `xxxxxx`

### 6. Configurer l'application

Modifiez le fichier `.env.local` :

```bash
# Google OAuth
GOOGLE_OAUTH_CLIENT_ID=votre_client_id_ici
GOOGLE_OAUTH_CLIENT_SECRET=votre_client_secret_ici
```

## ✅ Test de fonctionnement

### 1. Connexion Google
- Rendez-vous sur : http://172.17.4.210:8080/login
- Cliquez sur "Se connecter avec Google"
- Connectez-vous avec un compte des domaines autorisés

### 2. Gestion des rôles
- **Super Admin automatique** : nicolas.michel@decorpub.fr
- **Menu de test** : En mode dev, switch entre les rôles via le menu déroulant
- **Autres utilisateurs** : ROLE_USER par défaut

### 3. Vérifications
- Seuls les domaines autorisés peuvent se connecter
- L'avatar Google s'affiche dans la navbar
- Le switch de rôles fonctionne en mode dev

## 🔧 Fonctionnalités implémentées

### Authentification
- ✅ Connexion via Google OAuth
- ✅ Création automatique des comptes
- ✅ Restriction par domaines autorisés
- ✅ Super admin automatique (nicolas.michel@decorpub.fr)

### Interface
- ✅ Bouton "Se connecter avec Google" sur la page de login
- ✅ Avatar Google dans la navbar
- ✅ Indication "Compte Google" dans le menu utilisateur

### Mode développement
- ✅ Menu de switch de rôles (visible uniquement en dev)
- ✅ Switch entre ROLE_USER, ROLE_COMMERCIAL, ROLE_ADMIN
- ✅ Rechargement automatique après changement

## 🚀 Prochaines étapes

1. **Tests avec votre équipe** :
   - Testez la connexion avec différents comptes de vos domaines
   - Vérifiez que les rôles fonctionnent correctement
   - Testez le switch de rôles en mode dev

2. **Intégrations futures** :
   - Gmail pour l'envoi des devis
   - Google Drive pour le stockage des PDF
   - Google Calendar pour les échéances

## 🔒 Sécurité

- Les comptes non autorisés sont rejetés
- Les tokens Google sont stockés de manière sécurisée
- Le switch de rôles est désactivé en production
- Le super admin ne peut pas perdre ses droits

## 📞 Support

Si vous rencontrez des problèmes :
1. Vérifiez que les domaines sont bien configurés dans Google Cloud
2. Vérifiez que les URLs de redirection sont correctes
3. Vérifiez que les APIs sont activées
4. Consultez les logs de l'application pour plus de détails

---
*Configuration OAuth terminée - Prêt pour les tests !*