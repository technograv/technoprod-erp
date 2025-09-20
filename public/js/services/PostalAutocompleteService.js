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
            
            input.parentNode.insertBefore(suggestionsList, input.nextSibling);
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
                    
                    suggestionsList.style.display = 'block';
                } else {
                    suggestionsList.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('❌ Erreur autocomplétion:', error);
            });
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
        
        if (inputType === 'postal') {
            // Mettre à jour le code postal
            inputField.value = commune.codePostal;
            
            // Remplir automatiquement la ville
            const villeField = container.querySelector('[data-type="city"]');
            if (villeField) {
                villeField.value = commune.nomCommune;
                // Masquer ses suggestions aussi
                const villeSuggestions = villeField.parentNode.querySelector('.commune-suggestions');
                if (villeSuggestions) {
                    villeSuggestions.style.display = 'none';
                }
            }
        } else if (inputType === 'city') {
            // Mettre à jour la ville
            inputField.value = commune.nomCommune;
            
            // Remplir automatiquement le code postal
            const postalField = container.querySelector('[data-type="postal"]');
            if (postalField) {
                postalField.value = commune.codePostal;
                // Masquer ses suggestions aussi
                const postalSuggestions = postalField.parentNode.querySelector('.commune-suggestions');
                if (postalSuggestions) {
                    postalSuggestions.style.display = 'none';
                }
            }
        }
    }
}

// Instance globale pour réutilisation
window.PostalAutocompleteService = PostalAutocompleteService;