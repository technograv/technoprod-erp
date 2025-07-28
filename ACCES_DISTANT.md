# 🌐 CONFIGURATION ACCÈS DISTANT TECHNOPROD

## 📡 **SERVEUR CONFIGURÉ**

✅ **Serveur accessible** : `172.17.4.210:8001`  
✅ **URL complète** : `https://172.17.4.210:8001`  
✅ **Écoute sur toutes les interfaces** : `0.0.0.0:8001`

## 🖥️ **CONFIGURATION CLIENT (Autre ordinateur)**

### **Windows :**
1. **Ouvrir le fichier hosts** (en tant qu'administrateur) :
   ```
   C:\Windows\System32\drivers\etc\hosts
   ```

2. **Ajouter cette ligne** :
   ```
   172.17.4.210  technoprod.local
   ```

3. **Accéder via** : `https://technoprod.local:8001`

### **macOS :**
1. **Ouvrir le terminal** et exécuter :
   ```bash
   sudo nano /etc/hosts
   ```

2. **Ajouter cette ligne** :
   ```
   172.17.4.210  technoprod.local
   ```

3. **Sauvegarder** : `Ctrl+X`, puis `Y`, puis `Entrée`

4. **Accéder via** : `https://technoprod.local:8001`

### **Linux :**
1. **Ouvrir le terminal** et exécuter :
   ```bash
   sudo nano /etc/hosts
   ```

2. **Ajouter cette ligne** :
   ```
   172.17.4.210  technoprod.local
   ```

3. **Sauvegarder** et **accéder via** : `https://technoprod.local:8001`

## 🔐 **SÉCURITÉ**

⚠️ **IMPORTANT** : Cette configuration est pour le **développement local uniquement**.

Pour la **production**, configurez :
- **Nginx/Apache** avec domaine réel
- **Certificat SSL** valide
- **Firewall** approprié
- **Base de données** sécurisée

## 🧪 **TEST DE CONNEXION**

Depuis l'ordinateur client, testez :

1. **Ping du serveur** :
   ```bash
   ping 172.17.4.210
   ```

2. **Test du port** :
   ```bash
   telnet 172.17.4.210 8001
   ```

3. **Accès navigateur** : `https://technoprod.local:8001`

## 🔄 **REDÉMARRAGE DU SERVEUR**

Si besoin de redémarrer :
```bash
symfony server:stop
symfony server:start --allow-all-ip --port=8001 -d
```

## 📋 **ALTERNATIVE SANS HOSTS**

Accès direct via IP (sans modifier hosts) :
- **URL** : `https://172.17.4.210:8001`
- **Avantage** : Pas de modification système
- **Inconvénient** : URL moins pratique

---

**Résultat** : Tous les ordinateurs du réseau local peuvent maintenant accéder à TechnoProd !