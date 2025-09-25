# 🚨 SAUVEGARDE DES MODIFICATIONS - AVANT COMPACTAGE CONTEXTE

**Date :** $(date)
**Problème initial :** Page /devis/new ne créait pas les devis (message "Veuillez sélectionner un client/prospect")
**Problème actuel :** Adresses affichent "-" au lieu du contenu

## 📋 MODIFICATIONS APPLIQUÉES (à annuler si nécessaire)

### 1. Controller DevisController.php - Ligne 140
**AVANT (état initial fonctionnel) :**
```php
$prospectId = $request->request->get('prospect');
```

**APRÈS (première tentative) :**
```php
// Créer le formulaire Symfony
$form = $this->createForm(DevisType::class, $devis);
$form->handleRequest($request);
// + code complexe avec formulaire Symfony
```

**APRÈS (solution simplifiée actuelle) :**
```php
$prospectId = $request->request->get('client_selector'); // Correction ici !
```

### 2. Template new.html.twig - Lignes 183 et 421
**AVANT (état initial fonctionnel) :**
```html
<form method="post" id="devis-form">
...
</form>
```

**APRÈS (tentative Symfony) :**
```twig
{{ form_start(form, {'attr': {'id': 'devis-form'}}) }}
...
{# Masquer tous les champs restants du formulaire #}
<div style="display: none;" id="symfony-hidden-fields">
    {{ form_rest(form) }}
</div>
{{ form_end(form) }}
```

**APRÈS (retour simplifié actuel) :**
```html
<form method="post" id="devis-form">
...
</form>
```

### 3. DevisContactService.js - Ligne 1354
**MODIFIÉ :**
```javascript
// AVANT
formatAddressLabel(address) {
    return address.label ||
           `${address.ligne1 || ''} - ${address.codePostal || ''} ${address.ville || ''}`.trim();
}

// APRÈS
formatAddressLabel(address) {
    return address.nom ||
           `${address.ligne1 || ''} - ${address.code_postal || ''} ${address.ville || ''}`.trim();
}
```

## 🎯 ÉTAT CIBLE (à restaurer si nécessaire)

L'état fonctionnel était :
1. **Controller :** `$prospectId = $request->request->get('prospect');` (pas 'client_selector')
2. **Template :** Formulaire HTML simple (sans Symfony)
3. **Service :** formatAddressLabel avec `address.label` et `codePostal`

## 🐛 PROBLÈME ACTUEL

Les adresses affichent "-" car :
- Le service appelle `formatAddressLabel(address)`
- Mais les propriétés attendues ne correspondent pas à celles renvoyées par l'API
- L'API renvoie : `nom`, `ligne1`, `code_postal`, `ville`
- Le service attend : `label` OU `ligne1`, `codePostal`, `ville`

## 🔍 VÉRIFICATIONS À FAIRE

1. Vérifier ce que renvoie exactement `/client/{id}/addresses`
2. Adapter `formatAddressLabel` selon les vrais noms des propriétés
3. OU corriger l'API pour renvoyer les bons noms

## 📝 COMMANDES DE RESTAURATION RAPIDE

Si besoin de revenir à l'état initial :
```bash
# Controller
git checkout HEAD~X -- src/Controller/DevisController.php

# Template
git checkout HEAD~X -- templates/devis/new.html.twig

# Service (si nécessaire)
git checkout HEAD~X -- public/js/services/DevisContactService.js
```

**⚠️ ATTENTION :** Ne pas utiliser git car pas de sauvegarde au moment du problème initial !

## 🎯 ACTION SUIVANTE

1. Identifier exactement quelles propriétés sont renvoyées par l'API `/client/{id}/addresses`
2. Corriger `formatAddressLabel` pour utiliser les bonnes propriétés
3. Tester la création de devis
4. Si ça ne marche pas → revenir exactement à l'état initial et chercher la vraie cause