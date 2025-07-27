# Configuration DNS OVH - test.decorpub.fr

## 🎯 Objectif
Configurer le sous-domaine `test.decorpub.fr` pour pointer vers le serveur TechnoProd (172.17.4.210)

## 📋 Instructions étape par étape

### 1. Connexion à l'espace client OVH

1. Rendez-vous sur : https://www.ovh.com/manager/
2. Connectez-vous avec vos identifiants OVH
3. Sélectionnez l'univers **"Web Cloud"**

### 2. Accès à la zone DNS

1. Dans le menu de gauche, cliquez sur **"Noms de domaine"**
2. Sélectionnez le domaine **"decorpub.fr"**
3. Cliquez sur l'onglet **"Zone DNS"**

### 3. Ajouter l'entrée DNS

1. Cliquez sur **"Ajouter une entrée"** (bouton en haut à droite)
2. Choisissez le type **"A"**
3. Remplissez les champs :

```
Sous-domaine : test
TTL : 3600 (par défaut)
Cible : 172.17.4.210
```

### 4. Validation

1. Cliquez sur **"Suivant"**
2. Vérifiez les informations :
   - **Nom complet** : `test.decorpub.fr`
   - **Type** : A
   - **Cible** : 172.17.4.210
3. Cliquez sur **"Confirmer"**

### 5. Activation des modifications

⚠️ **Important** : Les modifications DNS peuvent prendre jusqu'à 24h pour se propager, mais généralement c'est effectif en 15-30 minutes.

## ✅ Vérification de la configuration

### Depuis l'interface OVH
Dans la zone DNS, vous devriez voir cette nouvelle ligne :
```
test    A    172.17.4.210    3600
```

### Test depuis la ligne de commande
Après quelques minutes, testez la résolution :

```bash
# Test de résolution DNS
nslookup test.decorpub.fr

# Test avec dig (plus détaillé)
dig test.decorpub.fr

# Test de connectivité
ping test.decorpub.fr
```

### Test de l'application
Une fois le DNS propagé :
```bash
# Test HTTP
curl -I http://test.decorpub.fr:8080

# Ou directement dans le navigateur
http://test.decorpub.fr:8080
```

## 🔧 Configuration alternative (si besoin)

Si vous préférez une configuration avec Apache/Nginx en frontal (sans port :8080) :

### Option A : Redirection sur port 80
Ajoutez aussi cette entrée DNS si vous voulez configurer un proxy :
```
Sous-domaine : test
Type : A
Cible : 172.17.4.210
```

Puis configurez un VirtualHost Apache :
```apache
<VirtualHost *:80>
    ServerName test.decorpub.fr
    ProxyPreserveHost On
    ProxyPass / http://172.17.4.210:8080/
    ProxyPassReverse / http://172.17.4.210:8080/
</VirtualHost>
```

## 📞 Support OVH

Si vous rencontrez des difficultés :
- **Documentation OVH** : https://docs.ovh.com/fr/domains/editer-ma-zone-dns/
- **Support OVH** : Via l'espace client
- **Community OVH** : https://community.ovh.com/

## 🚨 Points d'attention

1. **Propagation DNS** : Peut prendre jusqu'à 24h
2. **Cache DNS local** : Videz le cache de votre navigateur/OS si nécessaire
3. **Port :8080** : N'oubliez pas le port dans l'URL pour les tests
4. **HTTPS** : Pour la production, pensez à configurer un certificat SSL

## ✅ Checklist post-configuration

- [ ] Entrée DNS ajoutée dans OVH
- [ ] Résolution DNS fonctionnelle (`nslookup test.decorpub.fr`)
- [ ] Application accessible via `http://test.decorpub.fr:8080`
- [ ] Google OAuth fonctionne avec la nouvelle URL
- [ ] Équipe informée de la nouvelle URL de test

---
**Une fois configuré, votre équipe pourra tester Google OAuth sur `http://test.decorpub.fr:8080` ! 🎉**