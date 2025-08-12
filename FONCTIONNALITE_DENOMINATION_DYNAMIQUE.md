# 🔄 FONCTIONNALITÉ DÉNOMINATION DYNAMIQUE

## ✅ **NOUVELLE FONCTIONNALITÉ IMPLÉMENTÉE**

**Objectif :** Permettre la modification dynamique du champ dénomination lors du changement de type de personne dans l'édition d'un client.

### **Comportement dynamique :**

#### **🔄 Personne Physique → Personne Morale :**
1. **État initial** : Champ dénomination grisé et vide
2. **Changement de type** : Sélection "Personne morale" dans la liste
3. **Résultat automatique** :
   - ✅ Champ dénomination **activé** et **modifiable**
   - ✅ Attribut **`required`** ajouté (validation obligatoire)
   - ✅ Label mis à jour : "Dénomination sociale *" (avec astérisque rouge)
   - ✅ Placeholder : "Nom de l'entreprise, raison sociale..."
   - ✅ Message d'aide supprimé

#### **🔄 Personne Morale → Personne Physique :**
Cette fonctionnalité n'est **pas disponible** car le changement personne morale → personne physique est **interdit** par les règles métier (liste déroulante grisée).

## 🛠️ **IMPLÉMENTATION TECHNIQUE**

### **JavaScript dynamique (edit.html.twig) :**
```javascript
function setupTypePersonneChange() {
    const typePersonneSelect = document.getElementById('type_personne');
    const denominationField = document.querySelector('input[name="nom"]');
    
    function updateDenominationField() {
        const selectedType = typePersonneSelect.value;
        
        if (selectedType === 'physique') {
            // Personne physique : champ grisé et non obligatoire
            denominationField.disabled = true;
            denominationField.removeAttribute('required');
            denominationField.placeholder = 'Pas de dénomination pour les particuliers';
            denominationField.value = '';
            
        } else if (selectedType === 'morale') {
            // Personne morale : champ actif et obligatoire
            denominationField.disabled = false;
            denominationField.setAttribute('required', 'required');
            denominationField.placeholder = 'Nom de l\'entreprise, raison sociale...';
            denominationField.value = '';
        }
    }
    
    // Événement sur changement de type
    typePersonneSelect.addEventListener('change', updateDenominationField);
}
```

### **Initialisation automatique :**
- Fonction appelée au chargement de la page (`DOMContentLoaded`)
- Application de l'état initial selon le type de personne existant
- Événement déclenché seulement si la liste déroulante n'est pas `disabled`

## 📋 **SCÉNARIOS D'UTILISATION**

### **Scénario 1 - Conversion Particulier → Entreprise :**
1. **Client initial** : Jean DUPONT (personne physique)
2. **Besoin** : Jean crée son entreprise "DUPONT Consulting SARL"
3. **Action** : Édition client → Changer type vers "Personne morale"
4. **Résultat** :
   - ✅ Champ dénomination activé automatiquement
   - ✅ Saisie "DUPONT Consulting SARL"
   - ✅ Validation réussie avec dénomination obligatoire
   - ✅ Client transformé en personne morale

### **Scénario 2 - Protection Entreprise :**
1. **Client initial** : TechnoSoft SARL (personne morale)
2. **Tentative** : Changement vers "Personne physique"
3. **Protection** : Liste déroulante grisée
4. **Résultat** : ❌ Changement impossible (règle métier respectée)

## ✅ **AVANTAGES**

1. **🔄 Flexibilité** : Évolution naturelle particulier → entreprise
2. **⚡ Réactivité** : Interface qui s'adapte en temps réel
3. **🛡️ Sécurité** : Validation automatique selon le contexte
4. **👤 UX fluide** : Pas besoin de rechargement de page
5. **📊 Cohérence** : Respect des règles métier en temps réel

## 🧪 **TEST DE VALIDATION**

### **Test complet :**
1. **Éditer une personne physique** existante
2. **Vérifier** : Dénomination grisée initialement
3. **Changer** type vers "Personne morale"
4. **Vérifier** : Dénomination activée et obligatoire
5. **Saisir** nom d'entreprise
6. **Sauvegarder** : Validation réussie
7. **Résultat** : Client transformé avec dénomination

---

**🎯 INTERFACE DYNAMIQUE ET INTUITIVE**

Cette fonctionnalité offre une expérience utilisateur moderne où l'interface s'adapte intelligemment aux choix de l'utilisateur, tout en respectant les règles métier établies.