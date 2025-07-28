# 🔒 RÈGLES BASCULEMENT TYPE DE PERSONNE

## 🎯 **NOUVELLES RÈGLES MÉTIER IMPLÉMENTÉES**

### **Création :**
- ✅ **Personne physique** → Famille automatiquement définie à `"Particulier"`
- ✅ **Personne morale** → Famille libre au choix de l'utilisateur

### **Modification après création :**
- ✅ **Personne physique → Personne morale** : **AUTORISÉ** (évolution possible)
- ❌ **Personne morale → Personne physique** : **INTERDIT** (protection données)

## 🛡️ **PROTECTION PERSONNE MORALE**

### **Pourquoi cette restriction ?**
1. **Intégrité juridique** : Une entreprise ne peut pas devenir une personne physique
2. **Protection des données** : Éviter la perte de dénomination sociale
3. **Cohérence comptable** : Préserver l'historique commercial
4. **Conformité légale** : Respecter la nature juridique établie

### **Interface utilisateur :**
- **Liste déroulante grisée** pour personne morale
- **Message explicatif** : "Une personne morale ne peut pas être convertie en personne physique"
- **Champ caché** pour maintenir la valeur lors de la soumission

## 🔧 **IMPLÉMENTATION TECHNIQUE**

### **Template d'édition (edit.html.twig) :**
```twig
<select class="form-select" name="type_personne" 
        {{ client.typePersonne == 'morale' ? 'disabled' : '' }}>
    <option value="morale" {{ client.typePersonne == 'morale' ? 'selected' : '' }}>
        Personne morale
    </option>
    <option value="physique" {{ client.typePersonne == 'physique' ? 'selected' : '' }} 
            {{ client.typePersonne == 'morale' ? 'disabled' : '' }}>
        Personne physique
    </option>
</select>

{% if client.typePersonne == 'morale' %}
    <small class="text-muted">
        <i class="fas fa-info-circle"></i> 
        Une personne morale ne peut pas être convertie en personne physique
    </small>
    <input type="hidden" name="type_personne" value="morale">
{% endif %}
```

### **Contrôleur - Famille automatique :**
```php
if ($formData['typePersonne'] === 'physique') {
    $formData['nom'] = null;                    // Pas de dénomination
    $formData['famille'] = 'Particulier';       // Famille automatique
}
```

## 📊 **MATRICE DES TRANSITIONS**

| État actuel | → Personne physique | → Personne morale |
|-------------|--------------------|--------------------|
| **Personne physique** | ✅ Maintien | ✅ **AUTORISÉ** |
| **Personne morale** | ❌ **INTERDIT** | ✅ Maintien |

## 🧪 **SCÉNARIOS DE TEST**

### **Test 1 - Création personne physique :**
1. Nouveau client → Personne physique
2. Remplir prénom/nom
3. ✅ **Attendu** : Famille = "Particulier" (automatique)

### **Test 2 - Basculement physique → morale :**
1. Client personne physique existant
2. Édition → Changer vers "Personne morale"
3. ✅ **Attendu** : Changement autorisé

### **Test 3 - Protection personne morale :**
1. Client personne morale existant
2. Édition → Liste déroulante grisée
3. ✅ **Attendu** : Impossible de sélectionner "Personne physique"

## ✅ **AVANTAGES**

1. **🛡️ Protection juridique** : Évite les erreurs de classification
2. **📊 Cohérence des données** : Préserve l'intégrité du système
3. **⚖️ Conformité** : Respecte les distinctions légales françaises
4. **🎯 UX claire** : Interface explicite sur les règles métier
5. **🔒 Sécurité** : Empêche les modifications accidentelles

---

**📝 Note** : Ces règles suivent les bonnes pratiques de gestion de données clients B2B et respectent la logique juridique française.