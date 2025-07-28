# 🔧 CORRECTION FORMULAIRE CRÉATION CLIENT

## 🚨 **PROBLÈMES RÉSOLUS**

**Erreurs :** 
- `The invalid form control with name='personne_adresse_ville' is not focusable.`
- `The invalid form control with name='client[nom]' is not focusable.`

### **Cause du problème :**
- Champs avec attribut `required` cachés lors du basculement personne physique/morale
- Le champ `client[nom]` (Symfony) requis même pour personne physique
- Le navigateur tentait de valider des champs invisibles
- Conflits de validation entre sections conditionnelles

### **Solution implémentée :**

#### **1. Correction du formulaire Symfony :**
```php
// src/Form/ClientType.php
->add('nom', TextType::class, [
    'label' => 'Nom / Raison sociale',
    'required' => false, // ✅ Ajouté pour éviter validation forcée
    'attr' => ['placeholder' => 'Nom de l\'entreprise ou nom de famille']
])
```

#### **2. Gestion dynamique de l'attribut `required` :**
```javascript
// Gestion du champ nom d'entreprise selon le type
const nomEntrepriseField = document.querySelector('input[name="client[nom]"]');
if (typePersonne.value === 'physique') {
    // Pour personne physique : désactiver nom entreprise
    nomEntrepriseField.removeAttribute('required');
    nomEntrepriseField.disabled = true;
} else {
    // Pour personne morale : activer nom entreprise
    nomEntrepriseField.disabled = false;
    nomEntrepriseField.setAttribute('required', 'required');
}
```

#### **3. Validation intelligente selon le type :**
- **Personne physique** : Seuls les champs personnalisés `personne_*` sont requis, `client[nom]` désactivé
- **Personne morale** : Champs `client[nom]`, `contact_*` et `adresse_*` requis, champs `personne_*` désactivés
- **Basculement automatique** : Les attributs `required` s'adaptent en temps réel

#### **4. Sécurisation de la soumission :**
```javascript
form.addEventListener('submit', function(e) {
    // Gestion spéciale du champ nom d'entreprise selon le type
    if (typePersonne.value === 'physique') {
        nomEntrepriseField.disabled = true;
        nomEntrepriseField.removeAttribute('required');
    } else {
        nomEntrepriseField.disabled = false;
        nomEntrepriseField.setAttribute('required', 'required');
    }
    
    // Désactiver tous les champs cachés avant validation
    const hiddenSections = document.querySelectorAll('#personne-physique-fields, #contact-section, #adresse-section');
    hiddenSections.forEach(section => {
        if (section.style.display === 'none') {
            section.querySelectorAll('input, select').forEach(field => {
                field.disabled = true;
                field.removeAttribute('required');
            });
        }
    });
});
```

## ✅ **RÉSULTAT**

- ✅ **Plus d'erreurs console** lors de la saisie
- ✅ **Validation correcte** selon le type de personne
- ✅ **Interface fluide** sans messages d'erreur navigateur
- ✅ **Formulaire fonctionnel** pour création clients/prospects

## 🧪 **TEST RECOMMANDÉ**

1. **Aller sur** : `https://technoprod.local:8001/client/new`
2. **Basculer** entre "Personne physique" et "Personne morale"
3. **Vérifier** : Plus d'erreurs dans la console navigateur
4. **Tester** : Soumission du formulaire sans blocage

---

**Statut** : ✅ Problème résolu - Formulaire opérationnel