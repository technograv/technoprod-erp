# Solution Ngrok pour Google OAuth

## 📥 Installation rapide de ngrok

```bash
# Télécharger ngrok
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz
tar xvzf ngrok-v3-stable-linux-amd64.tgz

# Créer un tunnel vers votre serveur
./ngrok http 172.17.4.210:8080
```

## 🎯 Ngrok vous donnera une URL comme :
```
https://abc123def.ngrok.io
```

## 🔧 Configuration Google OAuth
Dans Google Cloud Console, utilisez :
```
https://abc123def.ngrok.io/connect/google/check
```

## ✅ Avantages
- ✅ URL HTTPS gratuite
- ✅ Accessible par toute l'équipe
- ✅ Pas de configuration DNS
- ✅ Fonctionne immédiatement

## 📋 Étapes complètes

1. **Installez ngrok** (commandes ci-dessus)
2. **Lancez le tunnel** : `./ngrok http 172.17.4.210:8080`
3. **Copiez l'URL** fournie par ngrok
4. **Configurez Google OAuth** avec cette URL + `/connect/google/check`
5. **Testez** avec la nouvelle URL

Cette solution contourne tous les problèmes DNS !