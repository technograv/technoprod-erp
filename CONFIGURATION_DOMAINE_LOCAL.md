# Configuration Domaine Local - TechnoProd

## 🎯 Objectif
Configurer `technoprod.local` pour contourner la restriction IP de Google OAuth

## 📋 Étapes à exécuter

### 1. Configurer le domaine local (À faire sur le serveur)
```bash
# Ajouter le domaine local au fichier hosts
sudo echo "172.17.4.210    technoprod.local" >> /etc/hosts

# Vérifier que c'est bien ajouté
cat /etc/hosts | grep technoprod
```

### 2. Configurer Google OAuth
Dans Google Cloud Console → Credentials → OAuth 2.0 Client → Authorized redirect URIs :
```
http://technoprod.local:8080/connect/google/check
```

### 3. Configurer l'équipe
Chaque membre de l'équipe doit ajouter cette ligne dans son fichier hosts local :

**Sur Windows** (fichier `C:\Windows\System32\drivers\etc\hosts`) :
```
172.17.4.210    technoprod.local
```

**Sur Mac/Linux** (fichier `/etc/hosts`) :
```bash
sudo echo "172.17.4.210    technoprod.local" >> /etc/hosts
```

### 4. Accès à l'application
- **Nouvelle URL** : http://technoprod.local:8080
- **Page de login** : http://technoprod.local:8080/login
- **OAuth fonctionne** avec cette URL

## ✅ Tests à effectuer

1. **Test de résolution DNS** :
```bash
ping technoprod.local
# Doit répondre avec 172.17.4.210
```

2. **Test de l'application** :
```bash
curl -I http://technoprod.local:8080
# Doit retourner un code HTTP 302 (redirection login)
```

3. **Test OAuth** :
- Aller sur http://technoprod.local:8080/login
- Cliquer sur "Se connecter avec Google"
- Doit rediriger vers Google sans erreur

## 🔧 Alternative si problèmes

Si le fichier hosts ne fonctionne pas, utilisez un proxy Apache/Nginx :

```bash
# Installer Apache (si pas déjà fait)
sudo apt update && sudo apt install apache2

# Configurer un VirtualHost
sudo nano /etc/apache2/sites-available/technoprod-local.conf
```

Contenu du VirtualHost :
```apache
<VirtualHost *:80>
    ServerName technoprod.local
    ProxyPreserveHost On
    ProxyPass / http://172.17.4.210:8080/
    ProxyPassReverse / http://172.17.4.210:8080/
</VirtualHost>
```

Puis :
```bash
sudo a2enmod proxy proxy_http
sudo a2ensite technoprod-local
sudo systemctl reload apache2
```

## 📞 Support
Si vous rencontrez des problèmes, vérifiez :
1. Le fichier hosts est bien modifié
2. Pas de cache DNS (redémarrer le navigateur)
3. Google OAuth a la bonne URL de redirection