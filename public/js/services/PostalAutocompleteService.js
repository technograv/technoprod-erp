/**
 * Service d'autocomplétion pour les codes postaux et villes
 * Réutilisable sur toute l'application - basé sur la logique de /client/edit
 */
class PostalAutocompleteService {
    constructor() {
        this.apiUrl = '/client/api/communes/search';
    }

    /**
     * Initialise l'autocomplétion sur un champ code postal et ville
     * @param {string} postalInputId - ID du champ code postal
     * @param {string} villeInputId - ID du champ ville
     */
    init(postalInputId, villeInputId) {
        console.log('🔧 PostalAutocompleteService.init appelé:', { postalInputId, villeInputId });
        
        const postalInput = document.getElementById(postalInputId);
        const villeInput = document.getElementById(villeInputId);

        if (!postalInput || !villeInput) {
            console.error('❌ PostalAutocompleteService: Champs non trouvés', { postalInputId, villeInputId });
            return;
        }

        // Marquer les champs avec data-type pour la logique de sélection
        postalInput.setAttribute('data-type', 'postal');
        villeInput.setAttribute('data-type', 'city');

        // Configurer l'autocomplétion pour chaque champ
        this.setupAutocompleteForInput(postalInput);
        this.setupAutocompleteForInput(villeInput);

        console.log('✅ PostalAutocompleteService: Initialisation réussie pour', postalInputId, '↔', villeInputId);
    }

    /**
     * Configure l'autocomplétion pour un champ donné
     * @param {HTMLElement} input - L'élément input à configurer
     */
    setupAutocompleteForInput(input) {
        let timeout;
        
        // Créer la liste de suggestions si elle n'existe pas
        let suggestionsList = input.parentNode.querySelector('.commune-suggestions');
        if (!suggestionsList) {
            suggestionsList = document.createElement('div');
            suggestionsList.className = 'commune-suggestions';
            
            // Déterminer si on est dans un modal
            const isInModal = input.closest('.modal') !== null;
            
            if (isInModal) {
                // Dans un modal : position absolue avec z-index élevé
                suggestionsList.style.cssText = `
                    position: absolute;
                    z-index: 1070;
                    background: white;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    max-height: 200px;
                    overflow-y: auto;
                    width: 100%;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                    display: none;
                    top: 100%;
                    left: 0;
                `;
                
                // S'assurer que le conteneur parent a position: relative
                const parentContainer = input.parentNode;
                if (parentContainer) {
                    const computedStyle = window.getComputedStyle(parentContainer);
                    if (computedStyle.position === 'static') {
                        parentContainer.style.position = 'relative';
                        console.log('🔧 Position relative ajoutée au parent pour modal autocomplétion');
                    }
                }
            } else {
                // Hors modal : positionnement normal
                suggestionsList.style.cssText = `
                    position: absolute;
                    z-index: 1060;
                    background: white;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    max-height: 200px;
                    overflow-y: auto;
                    width: ${input.offsetWidth}px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                    display: none;
                `;
            }
            
            // Attachement au bon conteneur selon le contexte
            if (isInModal) {
                // Dans une modal, garder l'attachement classique mais avec position relative forcée
                const parentContainer = input.parentNode;
                parentContainer.style.position = 'relative';
                parentContainer.appendChild(suggestionsList);
                console.log('🔗 Suggestions attachées au parent avec position relative forcée');
            } else {
                input.parentNode.insertBefore(suggestionsList, input.nextSibling);
            }
        }
        
        // Événement de saisie
        input.addEventListener('input', () => {
            const query = input.value;
            
            clearTimeout(timeout);
            
            if (query.length < 2) {
                suggestionsList.style.display = 'none';
                return;
            }
            
            timeout = setTimeout(() => {
                this.searchCommunes(query, suggestionsList, input);
            }, 300);
        });
        
        // Masquer les suggestions lors du clic à l'extérieur
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !suggestionsList.contains(e.target)) {
                suggestionsList.style.display = 'none';
            }
        });
    }

    /**
     * Recherche les communes et affiche les suggestions
     * @param {string} query - Terme de recherche
     * @param {HTMLElement} suggestionsList - Container des suggestions
     * @param {HTMLElement} inputField - Champ de saisie
     */
    searchCommunes(query, suggestionsList, inputField) {
        console.log('📡 Recherche commune:', query);
        
        fetch(`${this.apiUrl}?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                console.log('📊 Résultats communes:', data);
                suggestionsList.innerHTML = '';
                
                if (data.length > 0) {
                    data.forEach((commune) => {
                        const suggestion = document.createElement('div');
                        suggestion.className = 'commune-suggestion';
                        suggestion.style.cssText = `
                            padding: 8px 12px;
                            cursor: pointer;
                            border-bottom: 1px solid #eee;
                        `;
                        
                        suggestion.innerHTML = 
                            '<strong>' + commune.codePostal + ' - ' + commune.nomCommune + '</strong>' +
                            (commune.nomDepartement ? '<br><small class="text-muted">' + commune.nomDepartement + '</small>' : '');
                        
                        suggestion.addEventListener('mouseenter', function() {
                            this.style.backgroundColor = '#f8f9fa';
                        });
                        
                        suggestion.addEventListener('mouseleave', function() {
                            this.style.backgroundColor = 'white';
                        });
                        
                        suggestion.addEventListener('click', () => {
                            this.selectCommune(inputField, commune);
                            suggestionsList.style.display = 'none';
                        });
                        
                        suggestionsList.appendChild(suggestion);
                    });
                    
                    // Repositionner dans les modales pour éviter les problèmes
                    this.repositionSuggestions(suggestionsList, inputField);
                    suggestionsList.style.display = 'block';
                } else {
                    suggestionsList.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('❌ Erreur autocomplétion:', error);
            });
    }

    repositionSuggestions(suggestionsList, inputField) {
        const isInModal = inputField.closest('.modal') !== null;
        
        if (isInModal) {
            // Dans une modal, assurer que la largeur correspond au champ
            suggestionsList.style.width = inputField.offsetWidth + 'px';
            
            console.log('🎯 Repositionnement modal autocomplétion:', {
                width: inputField.offsetWidth,
                parent: inputField.parentNode.tagName
            });
        }
    }

    /**
     * Sélectionne une commune et remplit les champs correspondants
     * @param {HTMLElement} inputField - Champ qui a déclenché la sélection
     * @param {Object} commune - Commune sélectionnée
     */
    selectCommune(inputField, commune) {
        const inputType = inputField.getAttribute('data-type');
        const container = inputField.closest('tr') || inputField.closest('.modal-body') || inputField.closest('form');
        
        console.log('🎯 Sélection commune:', commune, 'pour type:', inputType);
        console.log('🔍 Container trouvé:', container ? container.tagName : 'AUCUN');
        console.log('🔍 Input field ID:', inputField.id, 'Name:', inputField.name);
        
        if (inputType === 'postal') {
            // Mettre à jour le code postal
            inputField.value = commune.codePostal;
            console.log('✅ Code postal mis à jour:', commune.codePostal);
            
            // Remplir automatiquement la ville - chercher dans le bon template
            let villeField = null;
            
            // D'abord essayer de trouver un champ dans le même template
            const inputTemplate = inputField.closest('[id*="template"]');
            if (inputTemplate) {
                villeField = inputTemplate.querySelector('[data-type="city"]');
                console.log('🔍 Champ ville trouvé dans même template:', !!villeField, villeField ? villeField.id : 'AUCUN');
            }
            
            // Si pas trouvé, essayer dans le container général (mais seulement si visible)
            if (!villeField) {
                const candidateFields = container.querySelectorAll('[data-type="city"]');
                villeField = Array.from(candidateFields).find(field => {
                    const fieldTemplate = field.closest('[id*="template"]');
                    return !fieldTemplate || fieldTemplate.offsetParent !== null;
                });
                console.log('🔍 Champ ville trouvé dans container (visible):', !!villeField, villeField ? villeField.id : 'AUCUN');
            }
            
            // Si pas trouvé dans le container, chercher dans le document entier
            if (!villeField) {
                const allCityFields = document.querySelectorAll('[data-type="city"]');
                console.log('🔍 Tous les champs ville disponibles:', Array.from(allCityFields).map(f => f.id || f.name));
                
                // Prendre le champ ville dans le bon template
                villeField = Array.from(allCityFields).find(field => {
                    // D'abord chercher un champ dans le même template que le champ code postal
                    const inputTemplate = inputField.closest('[id*="template"]');
                    const fieldTemplate = field.closest('[id*="template"]');
                    
                    if (inputTemplate && fieldTemplate && inputTemplate === fieldTemplate) {
                        console.log('✅ Champ ville trouvé dans le même template:', inputTemplate.id);
                        return true; // Même template
                    }
                    
                    // Si pas de template spécifique, chercher un champ dans un template visible
                    if (fieldTemplate) {
                        const isTemplateVisible = fieldTemplate.offsetParent !== null;
                        console.log(`🔍 Template ${fieldTemplate.id} visible:`, isTemplateVisible);
                        if (isTemplateVisible) {
                            return true;
                        }
                    }
                    
                    // En dernier recours, chercher un champ visible (hors template)
                    return field.offsetParent !== null;
                });
                console.log('🔍 Champ ville de secours trouvé:', !!villeField, villeField ? villeField.id : 'AUCUN');
            }
            
            if (villeField) {
                const isVisible = villeField.offsetParent !== null;
                const template = villeField.closest('[id*="template"]');
                console.log('🔍 Avant mise à jour ville:', {
                    fieldId: villeField.id,
                    isVisible: isVisible,
                    template: template ? template.id : 'AUCUN',
                    templateVisible: template ? template.offsetParent !== null : 'N/A'
                });
                
                villeField.value = commune.nomCommune;
                console.log('✅ Ville mise à jour:', commune.nomCommune);
                
                // Vérifier après mise à jour
                setTimeout(() => {
                    console.log('🔍 Après mise à jour ville:', {
                        currentValue: villeField.value,
                        isVisible: villeField.offsetParent !== null
                    });
                }, 100);
                
                // Masquer ses suggestions aussi
                const villeSuggestions = villeField.parentNode.querySelector('.commune-suggestions');
                if (villeSuggestions) {
                    villeSuggestions.style.display = 'none';
                }
            } else {
                console.error('❌ Aucun champ ville trouvé nulle part');
            }
        } else if (inputType === 'city') {
            // Mettre à jour la ville
            inputField.value = commune.nomCommune;
            console.log('✅ Ville mise à jour:', commune.nomCommune);
            
            // Remplir automatiquement le code postal
            let postalField = container.querySelector('[data-type="postal"]');
            console.log('🔍 Champ code postal trouvé dans container:', !!postalField, postalField ? postalField.id : 'AUCUN');
            
            // Si pas trouvé dans le container, chercher dans le document entier
            if (!postalField) {
                const allPostalFields = document.querySelectorAll('[data-type="postal"]');
                console.log('🔍 Tous les champs code postal disponibles:', Array.from(allPostalFields).map(f => f.id || f.name));
                
                // Prendre le premier champ code postal visible
                postalField = Array.from(allPostalFields).find(field => 
                    field.offsetParent !== null // Champ visible
                );
                console.log('🔍 Champ code postal de secours trouvé:', !!postalField, postalField ? postalField.id : 'AUCUN');
            }
            
            if (postalField) {
                postalField.value = commune.codePostal;
                console.log('✅ Code postal mis à jour:', commune.codePostal);
                
                // Masquer ses suggestions aussi
                const postalSuggestions = postalField.parentNode.querySelector('.commune-suggestions');
                if (postalSuggestions) {
                    postalSuggestions.style.display = 'none';
                }
            } else {
                console.error('❌ Aucun champ code postal trouvé nulle part');
            }
        }
    }
}

// Instance globale pour réutilisation
window.PostalAutocompleteService = PostalAutocompleteService;