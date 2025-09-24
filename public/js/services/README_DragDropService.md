# DragDropService - Service Unifié Drag & Drop

Service JavaScript réutilisable pour la gestion du drag & drop dans l'application TechnoProd.

## 🎯 Objectifs

- **Uniformiser** l'expérience utilisateur du drag & drop
- **Réutiliser** facilement sur différentes pages
- **Maintenir** la performance du système existant des devis
- **Simplifier** l'implémentation pour les nouvelles fonctionnalités

## 📚 Utilisation

### 1. Inclusion du service

```html
<script src="/js/services/DragDropService.js"></script>
```

### 2. Utilisation basique

```javascript
// Initialiser le drag & drop sur un tableau
window.dragDropService.init('#mon-tableau tbody', {
    itemSelector: '.ma-ligne-draggable',
    datasetKey: 'id',
    numericIds: true,
    onReorder: (itemIds) => {
        console.log('Nouvel ordre:', itemIds);
        // Appel API pour sauvegarder
    }
});
```

### 3. Configuration avancée

```javascript
window.dragDropService.init('#sortable-container', {
    // Sélecteur des éléments draggables
    itemSelector: '.draggable-item',
    
    // Extraction des IDs
    datasetKey: 'id',              // data-id
    // OU idAttribute: 'data-item-id', // attribut HTML direct
    numericIds: true,              // Convertir en nombres
    
    // Classes interdites au drop
    forbiddenClasses: ['no-drop', 'header'],
    
    // Indicateur de zone de drop (✨ NOUVEAU !)
    showDropIndicator: true,       // Afficher la zone verte de drop
    
    // Configuration SortableJS
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    
    // Callbacks
    onReorder: async (itemIds, evt) => {
        // Sauvegarder le nouvel ordre
        await sauvegarderOrdre(itemIds);
    },
    
    onMove: (evt) => {
        // Logique personnalisée pour autoriser/interdire le déplacement
        return true; // ou false
    }
});
```

## 📋 Exemples d'implémentation

### Modes de règlement (implémenté)
```javascript
window.dragDropService.init('#sortable-modes', {
    itemSelector: '.sortable-item',
    datasetKey: 'id',
    numericIds: true,
    onReorder: async (itemIds) => {
        const response = await fetch('/admin/modes-reglement/reorder', {
            method: 'POST',
            body: JSON.stringify({ order: itemIds })
        });
        // ... gestion de la réponse
    }
});
```

### Template HTML recommandé
```html
<tbody id="sortable-container">
    {% for item in items %}
    <tr data-id="{{ item.id }}" class="sortable-item">
        <td>
            <div class="drag-handle">
                <i class="fas fa-grip-vertical"></i>
            </div>
        </td>
        <td>{{ item.nom }}</td>
        <!-- ... autres colonnes -->
    </tr>
    {% endfor %}
</tbody>
```

## 🎨 Styles CSS automatiques

Le service injecte automatiquement les styles nécessaires :

```css
.sortable-ghost { opacity: 0.4; }
.sortable-chosen { background-color: #e3f2fd !important; }
.drag-handle { cursor: move; width: 20px; }
.sortable-item { transition: all 0.2s ease; }
```

## 📡 API du service

### Méthodes principales

- `init(container, options)` - Initialise le drag & drop
- `destroy(element)` - Détruit une instance
- `destroyAll()` - Détruit toutes les instances
- `updateOrderDisplay(container, selector)` - Met à jour les numéros d'ordre
- `showSuccess(message)` - Affiche une notification

### Options de configuration

| Option | Type | Description | Défaut |
|--------|------|-------------|--------|
| `itemSelector` | string | Sélecteur des éléments draggables | - |
| `datasetKey` | string | Clé dataset pour l'ID (data-*) | - |
| `idAttribute` | string | Attribut HTML pour l'ID | - |
| `numericIds` | boolean | Convertir les IDs en nombres | false |
| `forbiddenClasses` | array | Classes interdites au drop | [] |
| `showDropIndicator` | boolean | **Afficher l'indicateur de drop** | **true** |
| `onReorder` | function | Callback après réorganisation | - |
| `handle` | string | Sélecteur du handle de drag | '.drag-handle' |
| `animation` | number | Durée animation (ms) | 150 |

## ✅ Bonnes pratiques

1. **Structure HTML** : Utiliser `sortable-item` comme classe des éléments draggables
2. **Handle** : Utiliser `.drag-handle` pour la poignée de drag
3. **IDs uniques** : S'assurer que les data-id sont uniques
4. **Gestion d'erreurs** : Prévoir une logique de fallback dans `onReorder`
5. **Performance** : Détruire les instances inutiles avec `destroy()`

## ✨ Fonctionnalité : Indicateur de Zone de Drop

### Description
L'indicateur de zone de drop est une **barre verte animée** qui apparaît pendant le drag & drop pour indiquer précisément où l'élément sera déposé.

### Caractéristiques
- 🟢 **Couleur verte** avec dégradé élégant
- ✨ **Animation pulse** pour attirer l'attention
- 📍 **Positionnement précis** au-dessus ou en-dessous de l'élément cible
- 🎯 **Calcul automatique** basé sur la position de la souris
- 🧹 **Nettoyage automatique** à la fin du drag

### Configuration
```javascript
// Activer (défaut)
showDropIndicator: true

// Désactiver
showDropIndicator: false
```

### Styles CSS automatiques
```css
.drag-drop-indicator {
    background: linear-gradient(135deg, #4caf50, #45a049);
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    animation: pulse-green 1.5s infinite;
}
```

## 🔄 Compatibilité

- ✅ **Compatible** avec SortableJS existant (devis/edit)
- ✅ **Auto-chargement** de SortableJS si non présent
- ✅ **Bootstrap 5** pour les notifications
- ✅ **Responsive** et accessible
- ✅ **Indicateur de drop** inspiré du système des devis

## 🚀 Évolutions futures

- Support des groupes de drag & drop
- Animations personnalisées
- Support des éléments virtualisés
- Intégration avec les web components