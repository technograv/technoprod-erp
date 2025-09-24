/**
 * Service de gestion des modales pour l'édition des entités
 * Utilise Bootstrap 5 pour l'affichage et Ajax pour le chargement du contenu
 */
class ModalService {
    constructor(config = {}) {
        this.config = {
            debug: config.debug || false,
            modalContainer: config.modalContainer || 'body',
            modalId: config.modalId || 'dynamic-modal',
            ...config
        };
        
        this.currentModal = null;
        this.scrollPosition = 0;
        this.log('🚀 ModalService initialisé');
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[ModalService] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[ModalService] ${message}`, error || '');
    }
    
    /**
     * Sauvegarde la position actuelle et empêche le déplacement du contenu
     */
    saveScrollPosition() {
        this.scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        this.log('📍 Position sauvegardée:', this.scrollPosition);
    }
    
    /**
     * Restaure la position d'origine
     */
    restoreScrollPosition() {
        // Restaurer la position de scroll
        window.scrollTo(0, this.scrollPosition);
        this.log('📍 Position restaurée:', this.scrollPosition);
    }
    
    /**
     * Ajoute un style CSS global pour empêcher le déplacement des modales
     */
    addGlobalNoShiftStyle() {
        // Vérifier si le style existe déjà
        if (document.getElementById('modal-no-shift-global-style')) {
            return;
        }
        
        const styleElement = document.createElement('style');
        styleElement.id = 'modal-no-shift-global-style';
        styleElement.textContent = `
            /* SOLUTION ULTIME - Masquer complètement le contenu principal */
            body.modal-open > *:not(.modal):not(.modal-backdrop) {
                visibility: hidden !important;
            }
            
            /* Empêcher Bootstrap de modifier le padding du body */
            body.modal-open {
                padding-right: 0 !important;
                overflow: hidden !important;
                position: relative !important;
            }
            
            /* La modale reste visible */
            body.modal-open .modal,
            body.modal-open .modal-backdrop {
                visibility: visible !important;
            }
            
            /* Empêcher tout reflow en figeant la page */
            body.modal-open * {
                transition: none !important;
                animation: none !important;
                transform: none !important;
            }
        `;
        document.head.appendChild(styleElement);
        
        this.log('🔧 Style anti-déplacement ajouté');
    }
    
    
    
    
    
    /**
     * Ouvre une modale avec le contenu d'une URL
     */
    async openModal(url, title = 'Édition', options = {}) {
        this.log('🔧 Ouverture modale:', url);
        
        try {
            // Sauvegarder la position avant ouverture
            this.saveScrollPosition();
            
            // Créer la modale si elle n'existe pas
            this.createModalStructure();
            
            // Afficher le loader
            this.showLoader(title);
            
            // Charger le contenu
            const content = await this.loadContent(url);
            
            // Afficher le contenu dans la modale
            this.showContent(title, content, options);
            
            this.log('✅ Modale ouverte avec succès');
        } catch (error) {
            this.error('Erreur ouverture modale:', error);
            this.showError('Erreur lors du chargement de la page.');
        }
    }
    
    /**
     * Crée la structure HTML de la modale
     */
    createModalStructure() {
        // Supprimer la modale existante si elle existe
        const existingModal = document.getElementById(this.config.modalId);
        if (existingModal) {
            existingModal.remove();
        }
        
        const modalHtml = `
            <div class="modal fade" id="${this.config.modalId}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Chargement...</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer d-none">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.querySelector(this.config.modalContainer).insertAdjacentHTML('beforeend', modalHtml);
        
        // Ajouter un style CSS global pour empêcher le déplacement
        this.addGlobalNoShiftStyle();
        
        // Initialiser la modale Bootstrap
        const modalElement = document.getElementById(this.config.modalId);
        this.currentModal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true
        });
        
        // Écouter la fermeture de la modale
        modalElement.addEventListener('hidden.bs.modal', () => {
            this.cleanup();
        });
    }
    
    /**
     * Affiche le loader
     */
    showLoader(title) {
        const modal = document.getElementById(this.config.modalId);
        modal.querySelector('.modal-title').textContent = title;
        this.currentModal.show();
    }
    
    /**
     * Charge le contenu d'une URL
     */
    async loadContent(url) {
        this.log('📥 Chargement contenu:', url);
        
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin', // Inclure les cookies de session
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html,application/xhtml+xml'
            }
        });
        
        if (!response.ok) {
            // Essayer de lire le contenu de l'erreur pour le débogage
            let errorContent = '';
            try {
                errorContent = await response.text();
                console.error('Contenu erreur 500:', errorContent);
            } catch (e) {
                console.error('Impossible de lire le contenu de l\'erreur');
            }
            throw new Error(`HTTP ${response.status}`);
        }
        
        return await response.text();
    }
    
    /**
     * Affiche le contenu dans la modale
     */
    showContent(title, content, options = {}) {
        const modal = document.getElementById(this.config.modalId);
        const modalTitle = modal.querySelector('.modal-title');
        const modalBody = modal.querySelector('.modal-body');
        const modalFooter = modal.querySelector('.modal-footer');
        
        // Vérifier si le contenu inclut déjà un header et un body
        if (content.includes('modal-header') && content.includes('modal-body')) {
            // Le contenu est complet, remplacer toute la structure
            const modalContent = modal.querySelector('.modal-content');
            modalContent.innerHTML = content;
        } else {
            // Le contenu est juste le body, utiliser le header par défaut
            modalTitle.textContent = title;
            modalBody.innerHTML = content;
        }
        
        // Afficher/masquer le footer selon les options (uniquement si pas de contenu complet)
        if (!content.includes('modal-header')) {
            if (options.showFooter) {
                modalFooter.classList.remove('d-none');
            } else {
                modalFooter.classList.add('d-none');
            }
        }
        
        // Initialiser les scripts dans le contenu chargé
        const contentContainer = content.includes('modal-body') ? modal : modalBody;
        this.initializeModalContent(contentContainer);
    }
    
    /**
     * Affiche une erreur dans la modale
     */
    showError(message) {
        const modal = document.getElementById(this.config.modalId);
        const modalTitle = modal.querySelector('.modal-title');
        const modalBody = modal.querySelector('.modal-body');
        
        modalTitle.textContent = 'Erreur';
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            </div>
        `;
        
        this.currentModal.show();
    }
    
    /**
     * Initialise les scripts dans le contenu de la modale
     */
    initializeModalContent(container) {
        this.log('🔧 Initialisation contenu modale');
        
        // Réexécuter les scripts inline dans la modale avec protection contre les redéclarations
        const scripts = container.querySelectorAll('script');
        scripts.forEach(script => {
            if (script.src) {
                // Script externe - ne pas recharger
                return;
            }
            
            // Script inline - réexécuter avec protection
            try {
                const newScript = document.createElement('script');
                // Encapsuler le script pour éviter les conflits de variables
                newScript.textContent = `(function(){${script.textContent}})();`;
                script.parentNode.replaceChild(newScript, script);
            } catch (error) {
                console.warn('Erreur lors de l\'exécution du script:', error);
                // Supprimer le script problématique
                script.remove();
            }
        });
        
        // Initialiser Select2 UNIQUEMENT pour les sélecteurs de clients (pas les adresses/contacts)
        if (window.$ && typeof $.fn.select2 !== 'undefined') {
            // Ne transformer en Select2 QUE les selects de clients (beaucoup d'options + recherche nécessaire)
            $(container).find('select.form-select').each(function() {
                const selectId = this.id;
                const isClientSelector = selectId && (
                    selectId.includes('client') ||
                    selectId.includes('prospect') ||
                    selectId === 'devis_client' ||
                    selectId === 'client_selector'
                );

                if (isClientSelector && !$(this).hasClass('select2-hidden-accessible')) {
                    console.log('🔧 [ModalService] Transformation Select2 pour:', selectId);
                    // Configuration Select2 pour modales Bootstrap
                    $(this).select2({
                        dropdownParent: $(this).closest('.modal'),
                        width: '100%',
                        theme: 'bootstrap-5',
                        // Fixes spécifiques pour Firefox
                        dropdownAutoWidth: false,
                        escapeMarkup: function (markup) {
                            return markup;
                        }
                    });
                } else if (!isClientSelector) {
                    console.log('✅ [ModalService] Select normal conservé pour:', selectId || 'sans ID');
                }
            });
        }
        
        // Initialiser les autres composants si nécessaire
        this.initializeFormHandling(container);
    }
    
    /**
     * Gère la soumission des formulaires dans la modale
     */
    initializeFormHandling(container) {
        const forms = container.querySelectorAll('form');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(form);
            });
        });
    }
    
    /**
     * Gère la soumission d'un formulaire via Ajax
     */
    async handleFormSubmit(form) {
        this.log('📤 Soumission formulaire modale');
        
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.text();
            
            if (response.ok) {
                // Succès - fermer la modale et rafraîchir la page parent
                this.log('✅ Formulaire soumis avec succès');
                this.currentModal.hide();
                
                // Émettre un événement pour notifier le parent
                window.dispatchEvent(new CustomEvent('modalFormSuccess', {
                    detail: { form: form, response: result }
                }));
                
                // Optionnel : rafraîchir la page parent
                if (this.config.refreshOnSuccess) {
                    window.location.reload();
                }
            } else {
                // Erreur - afficher les erreurs dans la modale
                this.showContent('Erreur', result);
            }
        } catch (error) {
            this.error('Erreur soumission formulaire:', error);
            this.showError('Erreur lors de la sauvegarde.');
        }
    }
    
    /**
     * Ferme la modale
     */
    closeModal() {
        if (this.currentModal) {
            this.currentModal.hide();
        }
    }
    
    /**
     * Nettoie les ressources
     */
    cleanup() {
        this.log('🗑️ Nettoyage modale');
        
        if (this.currentModal) {
            this.currentModal.dispose();
            this.currentModal = null;
        }
        
        // Supprimer l'élément DOM
        const modalElement = document.getElementById(this.config.modalId);
        if (modalElement) {
            modalElement.remove();
        }
        
        // Restaurer la position de la page
        this.restoreScrollPosition();
    }
}

// Rendre disponible globalement
window.ModalService = ModalService;