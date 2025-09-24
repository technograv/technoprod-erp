/**
 * Service unifié pour la gestion du drag & drop dans TechnoProd
 * Basé sur le système performant utilisé dans /devis/id/edit
 * 
 * @author TechnoProd
 * @version 1.0.0
 */
class DragDropService {
    constructor() {
        this.instances = new Map();
        this.defaultConfig = {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            preventOnFilter: false
        };
    }

    /**
     * Initialise le drag & drop sur un conteneur
     * @param {string|HTMLElement} container - Sélecteur CSS ou élément DOM
     * @param {Object} options - Configuration du drag & drop
     * @returns {Object} Instance du service
     */
    init(container, options = {}) {
        const element = typeof container === 'string' 
            ? document.querySelector(container)
            : container;

        if (!element) {
            console.error(`DragDropService: Conteneur introuvable: ${container}`);
            return null;
        }

        // Vérifier que l'élément est dans le DOM et visible
        if (!document.contains(element)) {
            console.error(`DragDropService: Élément non attaché au DOM: ${container}`);
            return null;
        }

        // Détruire instance existante si nécessaire
        this.destroy(element);

        const config = this.buildConfig(options);
        
        // Vérifier la disponibilité de SortableJS
        if (typeof Sortable === 'undefined') {
            console.warn('DragDropService: SortableJS non chargé, tentative de chargement...');
            this.loadSortableJS(() => this.init(container, options));
            return null;
        }

        const sortableInstance = new Sortable(element, config);
        
        const instance = {
            element,
            sortable: sortableInstance,
            config,
            options
        };

        this.instances.set(element, instance);
        
        console.log('DragDropService: Initialisé sur', element);
        return instance;
    }

    /**
     * Construit la configuration SortableJS
     * @param {Object} options - Options utilisateur
     * @returns {Object} Configuration SortableJS
     */
    buildConfig(options) {
        const config = { ...this.defaultConfig };

        // Fusionner les options utilisateur
        Object.assign(config, options);

        // Variables pour l'indicateur de drop
        let dragIndicator = null;
        let targetPosition = null;

        // Callbacks personnalisés
        const originalOnStart = config.onStart;
        config.onStart = (evt) => {
            this.handleDragStart(evt, options);
            if (originalOnStart) originalOnStart(evt);
        };

        const originalOnEnd = config.onEnd;
        config.onEnd = (evt) => {
            this.handleDragEnd(evt, options, () => {
                this.removeDragIndicator(evt.from, dragIndicator);
                dragIndicator = null;
                targetPosition = null;
            });
            if (originalOnEnd) originalOnEnd(evt);
        };

        const originalOnMove = config.onMove;
        config.onMove = (evt) => {
            // Gérer l'indicateur de drop avec protection contre les erreurs
            try {
                const indicatorResult = this.handleDragMove(evt, options, dragIndicator, targetPosition);
                if (indicatorResult) {
                    dragIndicator = indicatorResult.indicator;
                    targetPosition = indicatorResult.position;
                }
            } catch (error) {
                // Ignorer silencieusement les erreurs d'indicateur pour ne pas casser le drag & drop
                console.warn('DragDropService: Erreur indicateur ignorée:', error.message);
            }

            const result = this.handleMove(evt, options);
            if (originalOnMove) {
                const customResult = originalOnMove(evt);
                return result && customResult;
            }
            return result;
        };

        return config;
    }

    /**
     * Gère le déplacement des éléments (onMove)
     * @param {Event} evt - Événement SortableJS
     * @param {Object} options - Options utilisateur
     * @returns {boolean} Autoriser/interdire le déplacement
     */
    handleMove(evt, options) {
        // Filtres par défaut
        const related = evt.related;
        if (related && options.forbiddenClasses) {
            for (const className of options.forbiddenClasses) {
                if (related.classList.contains(className)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Gère le début du drag & drop (onStart)
     * @param {Event} evt - Événement SortableJS
     * @param {Object} options - Options utilisateur
     */
    handleDragStart(evt, options) {
        // Ajouter une classe au conteneur pour indiquer le mode drag
        evt.from.classList.add('drag-active');
    }

    /**
     * Gère le mouvement pendant le drag & drop (onMove)
     * @param {Event} evt - Événement SortableJS
     * @param {Object} options - Options utilisateur
     * @param {HTMLElement} currentIndicator - Indicateur actuel
     * @param {Object} currentPosition - Position actuelle
     * @returns {Object|null} Nouvel indicateur et position
     */
    handleDragMove(evt, options, currentIndicator, currentPosition) {
        if (options.showDropIndicator === false) return null;

        const container = evt.from;
        const related = evt.related;
        
        if (!related || !container || !container.contains(related)) return null;
        
        // Vérifier que les éléments sont toujours dans le DOM
        if (!document.contains(related) || !document.contains(container)) return null;

        // Calculer la position de drop
        const rect = related.getBoundingClientRect();
        const mouseY = evt.originalEvent ? evt.originalEvent.clientY : 0;
        const relativeY = mouseY - rect.top;
        const isAfter = relativeY > rect.height / 2;

        const newPosition = {
            element: related,
            isAfter: isAfter
        };

        // Vérifier si la position a changé
        if (currentPosition && 
            currentPosition.element === newPosition.element && 
            currentPosition.isAfter === newPosition.isAfter) {
            return { indicator: currentIndicator, position: currentPosition };
        }

        // Supprimer l'ancien indicateur
        this.removeDragIndicator(container, currentIndicator);

        // Créer et positionner le nouvel indicateur
        const indicator = this.createDragIndicator(related);
        
        // Vérifier que l'élément related est toujours valide avant insertion
        if (!related.parentNode || !document.contains(related)) {
            console.warn('DragDropService: Élément related non valide pour insertion indicateur');
            return null;
        }
        
        if (isAfter) {
            related.insertAdjacentElement('afterend', indicator);
        } else {
            related.insertAdjacentElement('beforebegin', indicator);
        }

        return { indicator: indicator, position: newPosition };
    }

    /**
     * Gère la fin du drag & drop (onEnd)
     * @param {Event} evt - Événement SortableJS
     * @param {Object} options - Options utilisateur
     * @param {Function} cleanup - Fonction de nettoyage
     */
    handleDragEnd(evt, options, cleanup) {
        // Supprimer la classe du conteneur
        evt.from.classList.remove('drag-active');
        
        // Nettoyer l'indicateur
        if (cleanup) cleanup();

        // Gérer la réorganisation
        this.handleReorder(evt, options);
    }

    /**
     * Gère la réorganisation des éléments
     * @param {Event} evt - Événement SortableJS
     * @param {Object} options - Options utilisateur
     */
    handleReorder(evt, options) {
        if (!options.onReorder || !options.itemSelector) return;

        const container = evt.from;
        const items = Array.from(container.querySelectorAll(options.itemSelector));
        
        // Extraire les IDs selon la stratégie définie
        let itemIds;
        if (options.idAttribute) {
            itemIds = items
                .map(item => item.getAttribute(options.idAttribute))
                .filter(id => id !== null && id !== undefined);
        } else if (options.datasetKey) {
            itemIds = items
                .map(item => item.dataset[options.datasetKey])
                .filter(id => id !== null && id !== undefined);
        } else {
            console.warn('DragDropService: itemSelector défini mais aucune stratégie d\'extraction d\'ID');
            return;
        }

        // Convertir en nombres si nécessaire
        if (options.numericIds) {
            itemIds = itemIds.map(id => parseInt(id)).filter(id => !isNaN(id));
        }

        if (itemIds.length > 0) {
            options.onReorder(itemIds, evt);
        }
    }

    /**
     * Crée un indicateur de drop
     * @param {HTMLElement} relatedElement - Élément de référence pour le contexte
     * @returns {HTMLElement} Élément indicateur
     */
    createDragIndicator(relatedElement) {
        // Détecter si on est dans un contexte de tableau
        const isTableContext = relatedElement && (
            relatedElement.tagName === 'TR' || 
            relatedElement.closest('tbody') || 
            relatedElement.closest('table')
        );

        if (isTableContext) {
            // Créer une ligne de tableau pour l'indicateur
            const indicator = document.createElement('tr');
            indicator.className = 'drag-drop-indicator drag-drop-table-row';
            
            // Compter le nombre de colonnes de la ligne de référence
            const columnsCount = relatedElement.tagName === 'TR' 
                ? relatedElement.children.length 
                : relatedElement.closest('tr')?.children.length || 1;
            
            // Créer une cellule qui s'étend sur toutes les colonnes
            const cell = document.createElement('td');
            cell.setAttribute('colspan', columnsCount);
            cell.className = 'drag-drop-indicator-cell';
            cell.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Déposer ici';
            
            indicator.appendChild(cell);
            return indicator;
        } else {
            // Contexte normal (div, list, etc.)
            const indicator = document.createElement('div');
            indicator.className = 'drag-drop-indicator';
            indicator.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Déposer ici';
            return indicator;
        }
    }

    /**
     * Supprime l'indicateur de drop
     * @param {HTMLElement} container - Conteneur
     * @param {HTMLElement} indicator - Indicateur à supprimer
     */
    removeDragIndicator(container, indicator) {
        if (indicator && indicator.parentNode) {
            indicator.remove();
        }
        // Supprimer tous les indicateurs orphelins (contexte normal et tableau)
        const orphanIndicators = container.querySelectorAll('.drag-drop-indicator, .drag-drop-table-row');
        orphanIndicators.forEach(orphan => orphan.remove());
    }

    /**
     * Charge SortableJS dynamiquement si nécessaire
     * @param {Function} callback - Fonction à appeler après chargement
     */
    loadSortableJS(callback) {
        if (document.querySelector('script[src*="sortable"]')) {
            setTimeout(callback, 100);
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
        script.onload = () => {
            console.log('DragDropService: SortableJS chargé avec succès');
            if (callback) callback();
        };
        script.onerror = () => {
            console.error('DragDropService: Erreur lors du chargement de SortableJS');
        };
        document.head.appendChild(script);
    }

    /**
     * Détruit une instance drag & drop
     * @param {HTMLElement} element - Élément conteneur
     */
    destroy(element) {
        const instance = this.instances.get(element);
        if (instance && instance.sortable) {
            instance.sortable.destroy();
            this.instances.delete(element);
            console.log('DragDropService: Instance détruite pour', element);
        }
    }

    /**
     * Détruit toutes les instances
     */
    destroyAll() {
        for (const [element, instance] of this.instances) {
            if (instance.sortable) {
                instance.sortable.destroy();
            }
        }
        this.instances.clear();
        console.log('DragDropService: Toutes les instances détruites');
    }

    /**
     * Met à jour l'affichage des numéros d'ordre après réorganisation
     * @param {HTMLElement} container - Conteneur
     * @param {string} orderSelector - Sélecteur pour les éléments affichant l'ordre
     */
    updateOrderDisplay(container, orderSelector) {
        const orderElements = container.querySelectorAll(orderSelector);
        orderElements.forEach((element, index) => {
            element.textContent = index + 1;
        });
    }

    /**
     * Affiche une notification de succès
     * @param {string} message - Message à afficher
     */
    showSuccess(message) {
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 m-3 alert alert-success alert-dismissible';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <i class="fas fa-check me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 3000);
    }

    /**
     * Injecte les styles CSS nécessaires
     */
    injectStyles() {
        if (document.getElementById('drag-drop-service-styles')) return;

        const style = document.createElement('style');
        style.id = 'drag-drop-service-styles';
        style.textContent = `
            /* Styles DragDropService */
            .sortable-ghost {
                opacity: 0.4;
            }

            .sortable-chosen {
                background-color: #e3f2fd !important;
            }

            .drag-handle {
                cursor: move;
                user-select: none;
                width: 20px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .sortable-item {
                transition: all 0.2s ease;
            }

            .sortable-item:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .drag-handle:hover {
                color: #007bff;
            }

            /* Indicateur de zone de drop - contexte normal */
            .drag-drop-indicator {
                padding: 8px 16px;
                margin: 4px 0;
                background: linear-gradient(135deg, #4caf50, #45a049);
                color: white;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0.9;
                box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
                animation: pulse-green 1.5s infinite;
                z-index: 1000;
            }

            /* Indicateur de zone de drop - contexte tableau */
            .drag-drop-table-row {
                background: transparent !important;
            }

            .drag-drop-indicator-cell {
                padding: 8px 16px;
                background: linear-gradient(135deg, #4caf50, #45a049);
                color: white;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                text-align: center;
                opacity: 0.9;
                box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
                animation: pulse-green 1.5s infinite;
                z-index: 1000;
                border: none !important;
            }

            @keyframes pulse-green {
                0% { transform: scale(1); }
                50% { transform: scale(1.02); }
                100% { transform: scale(1); }
            }

            /* Mode drag actif */
            .drag-active .sortable-item {
                transition: all 0.3s ease;
            }

            .drag-active .sortable-item:not(.sortable-chosen):not(.sortable-ghost) {
                opacity: 0.7;
            }
        `;
        document.head.appendChild(style);
    }
}

// Initialisation différée pour éviter les conflits
(function() {
    // Créer l'instance globale si elle n'existe pas
    if (!window.dragDropService) {
        window.dragDropService = new DragDropService();
        
        // Auto-injection des styles
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                window.dragDropService.injectStyles();
            });
        } else {
            window.dragDropService.injectStyles();
        }
    }
})();