# 🚨 Diagnostic Final - OAuth invalid_grant

## ❌ Problème confirmé
L'erreur `invalid_grant` persiste même avec :
- ✅ Nouvelles clés OAuth créées
- ✅ APIs activées
- ✅ URLs correctes
- ✅ Test direct avec curl → Même erreur

## 🎯 **Cause probable : Configuration OAuth Consent Screen**

### Action IMMÉDIATE à vérifier dans Google Cloud Console :

#### 1. OAuth Consent Screen
- **Publishing status** : DOIT être "In production"
- **User type** : External
- Si en "Testing" → Ajouter `nicolas.michel@decorpub.fr` dans Test users

#### 2. Scopes autorisés
Vérifier que ces scopes sont dans la liste :
- `openid`
- `email`
- `profile`

#### 3. Domaine vérifié
- Dans **Domain verification**, ajouter et vérifier `decorpub.fr`

### Solution alternative : Mode Testing
Si vous ne pouvez pas passer en "Production" :

1. **OAuth consent screen** → Mode "Testing"
2. **Test users** → Ajouter EXPLICITEMENT :
   - `nicolas.michel@decorpub.fr`
   - Tous les emails des domaines autorisés

## 🔧 **Si ça ne marche toujours pas**

### Dernière solution : Recreer le PROJET Google Cloud
1. Créer un nouveau projet Google Cloud
2. Configurer OAuth depuis zéro
3. Activer toutes les APIs

## 📊 **Logs de debug**
Le test direct avec curl confirme que Google rejette nos tokens :
```
HTTP Code: 400
Response: {"error": "invalid_grant", "error_description": "Bad Request"}
```

Cela indique un problème de configuration côté Google Cloud Console, pas dans notre code.