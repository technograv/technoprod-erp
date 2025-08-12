# 🎯 Guide MCP TechnoProd - Référence Rapide

## 🚀 Démarrage rapide MCP

### Prérequis
```bash
# 1. Symfony doit tourner
symfony server:status

# 2. Se positionner dans le bon dossier
cd /home/decorpub/TechnoProd/technoprod
```

### Commandes essentielles
```bash
# Démarrer MCP
./mcp.sh start

# Vérifier que tout fonctionne
./mcp.sh status

# Arrêter MCP
./mcp.sh stop
```

## 🌐 URLs MCP

| Interface | URL | Usage |
|-----------|-----|-------|
| **Panneau Admin MCP** | http://localhost:3001/admin | Onglet Secteurs pour InfoWindows |
| **Accueil MCP** | http://localhost:3001/ | Test général |
| **Symfony original** | https://127.0.0.1:8080/ | Comparaison |

## 🎨 Workflow Recette Esthétique

### Étapes pour optimiser les InfoWindows secteurs :

1. **Démarrer l'environnement**
   ```bash
   cd /home/decorpub/TechnoProd/technoprod
   ./mcp.sh start
   ```

2. **Accéder à l'interface MCP**
   - Naviguer vers : http://localhost:3001/admin
   - Se connecter si nécessaire (OAuth préservé)
   - Cliquer sur l'onglet "Secteurs"

3. **Inspection visuelle**
   - Cliquer sur des marqueurs secteurs pour voir les InfoWindows
   - Ouvrir DevTools (F12) pour inspecter le CSS
   - Identifier les problèmes de centrage/scroll

4. **Modification en temps réel**
   - Ouvrir : `templates/admin/secteurs.html.twig`
   - Modifier le CSS des InfoWindows (lignes ~465-480)
   - Sauvegarder le fichier

5. **Validation instantanée**
   - Rafraîchir la page MCP (ou rechargement automatique)
   - Tester les InfoWindows modifiées
   - Itérer jusqu'à résultat parfait

## 🔧 Diagnostic MCP

### Vérifications de bon fonctionnement :

```bash
# État du processus
./mcp.sh status

# Test de connectivité
curl -s -o /dev/null -w "%{http_code}" http://localhost:3001
# Doit retourner 200 ou 302

# Processus en cours
ps aux | grep "node.*mcp-simple" | grep -v grep
```

### Résolution des problèmes courants :

| Problème | Solution |
|----------|----------|
| **Code 000** | MCP non démarré → `./mcp.sh start` |
| **Erreur 500** | Symfony non accessible → `symfony server:status` |
| **Page blanche** | Problème proxy → `./mcp.sh restart` |
| **OAuth ne fonctionne pas** | Utiliser HTTPS → https://127.0.0.1:8080 direct |

## 📝 Fichiers MCP clés

| Fichier | Rôle |
|---------|------|
| `mcp-simple.js` | Serveur proxy principal |
| `mcp.sh` | Script de gestion |
| `templates/admin/secteurs.html.twig` | Template InfoWindows (lignes ~465-480) |

## 🎯 Zone d'édition InfoWindows

**Fichier :** `templates/admin/secteurs.html.twig`  
**Lignes :** ~465-480  
**Section :** `const infoContent = \`<div style="width: 260px...`

### CSS actuel à optimiser :
- Centrage vertical de l'en-tête coloré
- Gestion du scroll du contenu
- Positionnement du bouton de fermeture
- Largeur responsive

## ✅ Indicateurs de fonctionnement

### Signes que MCP fonctionne :
- ✅ Badge vert "🎯 MCP Active" visible sur les pages
- ✅ Console navigateur : "🎯 MCP Debug Mode actif"
- ✅ Toutes les fonctionnalités Symfony préservées
- ✅ Authentification Google OAuth opérationnelle

## 🚨 Arrêt d'urgence

```bash
# Arrêt propre
./mcp.sh stop

# Arrêt forcé si besoin
pkill -f "node.*mcp-simple"
```

---
**📅 Dernière mise à jour :** 04/08/2025  
**🎯 Statut :** MCP opérationnel et testé pour recette esthétique