# 📋 RÈGLES MÉTIER DÉNOMINATION CLIENT

## 🎯 **NOUVELLES RÈGLES IMPLÉMENTÉES**

### **Personne Physique (Particulier) :**
- ✅ **Dénomination** : `NULL` (pas de nom d'entreprise)
- ✅ **Famille** : Automatiquement définie à `"Particulier"`
- ✅ **Identification** : Via `prénom` + `nom` séparés
- ✅ **Validation** : Dénomination doit rester vide

### **Personne Morale (Entreprise) :**
- ✅ **Dénomination** : **OBLIGATOIRE** (nom entreprise)
- ✅ **Famille** : Libre choix (TPE, PME, ETI, etc.)
- ✅ **Identification** : Via la dénomination sociale
- ✅ **Validation** : Dénomination ne peut pas être vide

## 🔧 **IMPLÉMENTATION TECHNIQUE**

### **1. Contrôleur (ClientController.php) :**
```php
if ($formData['typePersonne'] === 'physique') {
    // Personne physique : pas de dénomination + famille forcée
    $formData['nom'] = null;                    // NULL pour dénomination
    $formData['famille'] = 'Particulier';       // Famille automatique
} 
// Personne morale : dénomination obligatoire (validation FormType)
```

### **2. Validation (ClientType.php) :**
```php
// Personne morale : dénomination obligatoire
if ($typePersonne === 'morale' && empty($value)) {
    $context->buildViolation('La dénomination est obligatoire pour une personne morale.');
}

// Personne physique : dénomination doit être NULL
if ($typePersonne === 'physique' && !empty($value)) {
    $context->buildViolation('Les personnes physiques ne doivent pas avoir de dénomination.');
}
```

### **3. Sauvegarde (handleCustomFormSubmission) :**
```php
if ($client->getTypePersonne() === 'physique') {
    $client->setNom(null);                      // Pas de dénomination
    $client->setPrenom($request->get('personne_prenom'));
    $client->setFamille('Particulier');         // Famille forcée
}
```

## 📊 **STRUCTURE BASE DE DONNÉES**

### **Table `client` :**
```sql
CREATE TABLE client (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,           -- P001, P002, CLI001...
    type_personne VARCHAR(20) NOT NULL,         -- 'physique' ou 'morale'
    nom VARCHAR(200) NULL,                      -- NULL pour physique, obligatoire pour morale
    prenom VARCHAR(100) NULL,                   -- Rempli pour physique uniquement
    civilite VARCHAR(10) NULL,                  -- M., Mme, Mlle pour physique
    famille VARCHAR(100) NULL,                  -- 'Particulier' pour physique, libre pour morale
    statut VARCHAR(20) DEFAULT 'prospect'       -- 'prospect' ou 'client'
);
```

## 🧪 **EXEMPLES CONCRETS**

### **Exemple 1 - Personne Physique :**
```
Code: P003
Type: physique
Nom (dénomination): NULL
Prénom: Jean
Civilité: M.
Famille: Particulier
Contact: Jean DUPONT (même personne)
```

### **Exemple 2 - Personne Morale :**
```
Code: P004  
Type: morale
Nom (dénomination): "TechnoSoft SARL"
Prénom: NULL
Civilité: NULL
Famille: PME
Contact: Marie MARTIN (contact dans l'entreprise)
```

## ✅ **AVANTAGES MÉTIER**

1. **🎯 Clarté conceptuelle** : Distinction nette personne physique vs entreprise
2. **📊 Reporting précis** : Statistiques par type de client
3. **🔍 Recherche facilitée** : Filtrage par famille "Particulier" vs entreprises
4. **⚖️ Conformité légale** : Respect distinction juridique personne physique/morale
5. **📈 Analyse commerciale** : Segmentation automatique du portefeuille

## 🧪 **TESTS DE VALIDATION**

### **Test 1 - Particulier valide :**
- Sélectionner "Personne physique"
- Remplir prénom/nom/email/téléphone
- ✅ **Attendu** : Dénomination=NULL, Famille=Particulier

### **Test 2 - Entreprise valide :**
- Sélectionner "Personne morale"  
- Remplir dénomination + contact
- ✅ **Attendu** : Dénomination remplie, Famille libre

### **Test 3 - Entreprise sans dénomination :**
- Sélectionner "Personne morale"
- Laisser dénomination vide
- ❌ **Attendu** : Erreur "Dénomination obligatoire"

---

**📝 Note** : Ces règles respectent les bonnes pratiques comptables et juridiques françaises pour la distinction personne physique/personne morale.