/**
 * Exemple de migration du système drag & drop des devis vers le service unifié
 * Ce fichier montre comment adapter le code existant si souhaité dans le futur
 * IMPORTANT : Ce fichier est UNIQUEMENT un exemple - le système actuel fonctionne parfaitement
 */

// AVANT (système actuel dans devis/edit.html.twig) - NE PAS MODIFIER
/*
function initSortable() {
    const container = document.getElementById('elements-container');
    
    if (devisSortable) {
        devisSortable.destroy();
    }

    devisSortable = new Sortable(container, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        filter: '.devis-headers, .text-center.text-muted, .btn-group, .mt-3, .add-item-btn',
        preventOnFilter: false,
        onMove: function(evt, originalEvent) {
            // ... logique complexe existante
            return true;
        },
        onEnd: function(evt) {
            if (evt.item && evt.item.dataset.elementId) {
                const elementIds = Array.from(container.children)
                    .filter(child => child.dataset.elementId && !isNaN(parseInt(child.dataset.elementId)))
                    .map(child => parseInt(child.dataset.elementId));
                    
                if (elementIds.length > 0) {
                    reorderElements(elementIds);
                }
            }
        }
    });
}
*/

// APRÈS (si migration souhaitée dans le futur) - EXEMPLE SEULEMENT
/*
function initSortable() {
    // Détruire instance existante si nécessaire
    if (devisSortable) {
        window.dragDropService.destroy(document.getElementById('elements-container'));
        devisSortable = null;
    }

    devisSortable = window.dragDropService.init('#elements-container', {
        // Configuration personnalisée pour les devis
        filter: '.devis-headers, .text-center.text-muted, .btn-group, .mt-3, .add-item-btn',
        forbiddenClasses: ['devis-headers', 'text-center', 'btn-group', 'add-item-btn'],
        
        // Extraction des IDs d'éléments
        itemSelector: '[data-element-id]',
        datasetKey: 'elementId',
        numericIds: true,
        
        // Callback de réorganisation
        onReorder: async (elementIds) => {
            if (elementIds.length > 0) {
                await reorderElements(elementIds);
            }
        },
        
        // Callback onMove personnalisé pour la logique complexe des devis
        onMove: function(evt) {
            // Empêcher le déplacement vers les en-têtes ou éléments non-draggables
            const related = evt.related;
            if (related && (related.classList.contains('devis-headers') || 
                          related.classList.contains('text-center') || 
                          related.classList.contains('btn-group') ||
                          related.classList.contains('add-item-btn'))) {
                return false; // Empêcher le déplacement
            }
            return true; // Autoriser le déplacement
        }
    });
}
*/

// NOTES IMPORTANTES :
// 1. Le système actuel des devis fonctionne parfaitement - NE PAS LE MODIFIER
// 2. Ce fichier est uniquement un exemple de migration potentielle
// 3. Le service DragDropService est conçu pour être compatible avec SortableJS existant
// 4. Les deux systèmes peuvent coexister sans conflit