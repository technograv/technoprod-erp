/**
 * Service de gestion des modales de contact
 * Fournit une interface simplifiée pour créer et modifier des contacts
 */
class ContactModalService {
    constructor(config = {}) {
        this.config = {
            debug: config.debug || false,
            onContactCreated: config.onContactCreated || null,
            onContactUpdated: config.onContactUpdated || null,
            ...config
        };
        
        this.modalService = null;
        this.initialized = false;
        
        this.log('🚀 ContactModalService initialisé');
        this.init();
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[ContactModalService] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[ContactModalService] ${message}`, error || '');
    }
    
    /**
     * Initialise le service
     */
    init() {
        if (!window.ModalService) {
            this.error('ModalService non disponible');
            return false;
        }
        
        this.modalService = new ModalService({
            debug: this.config.debug,
            modalId: 'contact-modal',
            refreshOnSuccess: false
        });
        
        // Écouter les événements globaux
        this.setupEventListeners();
        
        this.initialized = true;
        this.log('✅ Service initialisé');
        return true;
    }
    
    /**
     * Configure les écouteurs d'événements
     */
    setupEventListeners() {
        // Écouter les événements de création/modification de contact
        window.addEventListener('contactCreated', (event) => {
            this.log('✅ Contact créé:', event.detail.contact);
            this.showSuccessMessage(event.detail.message);
            
            // Callback personnalisé
            if (this.config.onContactCreated) {
                this.config.onContactCreated(event.detail);
            }
        });
        
        window.addEventListener('contactUpdated', (event) => {
            this.log('✅ Contact modifié:', event.detail.contact);
            this.showSuccessMessage(event.detail.message);
            
            // Callback personnalisé
            if (this.config.onContactUpdated) {
                this.config.onContactUpdated(event.detail);
            }
        });
    }
    
    /**
     * Ouvre la modale de création de contact
     */
    createContact(clientId, type = null) {
        if (!this.initialized) {
            this.error('Service non initialisé');
            return;
        }
        
        if (!clientId) {
            alert('Veuillez d\'abord sélectionner un client');
            return;
        }
        
        let url = `/contact/modal/new/${clientId}`;
        if (type) {
            url += `?type=${type}`;
        }
        
        this.log('🔧 Ouverture modale création contact pour client:', clientId);
        this.modalService.openModal(url, 'Ajouter un contact');
    }
    
    /**
     * Ouvre la modale d'édition de contact
     */
    editContact(contactId) {
        if (!this.initialized) {
            this.error('Service non initialisé');
            return;
        }
        
        if (!contactId) {
            alert('Veuillez d\'abord sélectionner un contact');
            return;
        }
        
        const url = `/contact/modal/edit/${contactId}`;
        this.log('🔧 Ouverture modale édition contact:', contactId);
        this.modalService.openModal(url, 'Modifier le contact');
    }
    
    /**
     * Attache les gestionnaires d'événements aux boutons de contact
     */
    attachToButtons(config) {
        const {
            addContactBtn,
            addContactFacturationBtn,
            editContactLivraisonBtn,
            editContactFacturationBtn,
            clientSelector,
            contactLivraisonSelector,
            contactFacturationSelector
        } = config;
        
        // Bouton d'ajout de contact général
        if (addContactBtn) {
            const btn = typeof addContactBtn === 'string' 
                ? document.getElementById(addContactBtn)
                : addContactBtn;
                
            btn?.addEventListener('click', () => {
                const clientSelect = typeof clientSelector === 'string'
                    ? document.getElementById(clientSelector)
                    : clientSelector;
                const clientId = clientSelect?.value;
                this.createContact(clientId);
            });
        }
        
        // Bouton d'ajout de contact de facturation
        if (addContactFacturationBtn) {
            const btn = typeof addContactFacturationBtn === 'string' 
                ? document.getElementById(addContactFacturationBtn)
                : addContactFacturationBtn;
                
            btn?.addEventListener('click', () => {
                const clientSelect = typeof clientSelector === 'string'
                    ? document.getElementById(clientSelector)
                    : clientSelector;
                const clientId = clientSelect?.value;
                this.createContact(clientId, 'facturation');
            });
        }
        
        // Bouton d'édition de contact de livraison
        if (editContactLivraisonBtn) {
            const btn = typeof editContactLivraisonBtn === 'string' 
                ? document.getElementById(editContactLivraisonBtn)
                : editContactLivraisonBtn;
                
            btn?.addEventListener('click', () => {
                const contactSelect = typeof contactLivraisonSelector === 'string'
                    ? document.getElementById(contactLivraisonSelector)
                    : contactLivraisonSelector;
                const contactId = contactSelect?.value;
                this.editContact(contactId);
            });
        }
        
        // Bouton d'édition de contact de facturation
        if (editContactFacturationBtn) {
            const btn = typeof editContactFacturationBtn === 'string' 
                ? document.getElementById(editContactFacturationBtn)
                : editContactFacturationBtn;
                
            btn?.addEventListener('click', () => {
                const contactSelect = typeof contactFacturationSelector === 'string'
                    ? document.getElementById(contactFacturationSelector)
                    : contactFacturationSelector;
                const contactId = contactSelect?.value;
                this.editContact(contactId);
            });
        }
        
        this.log('✅ Gestionnaires d\'événements attachés aux boutons');
    }
    
    /**
     * Configuration simple pour les pages de devis
     */
    attachToDevisPage() {
        // Détecter automatiquement si on est sur la page NEW ou EDIT
        const isEditPage = !!document.getElementById('devis_client');
        const isNewPage = !!document.getElementById('prospect');
        
        let config = {
            addContactBtn: 'add-contact-btn',
            addContactFacturationBtn: 'add-contact-facturation-btn',
            editContactLivraisonBtn: 'edit-contact-livraison-btn',
            editContactFacturationBtn: 'edit-contact-facturation-btn'
        };
        
        if (isEditPage) {
            // Page d'édition - utiliser les IDs Symfony
            config = {
                ...config,
                clientSelector: 'devis_client',
                contactLivraisonSelector: 'devis_contactLivraison',
                contactFacturationSelector: 'devis_contactFacturation'
            };
            this.log('🔧 Configuration EDIT détectée');
        } else if (isNewPage) {
            // Page de création - utiliser les IDs custom
            config = {
                ...config,
                clientSelector: 'prospect',
                contactLivraisonSelector: 'contact_defaut',
                contactFacturationSelector: 'contact_facturation'
            };
            this.log('🔧 Configuration NEW détectée');
        } else {
            this.error('❌ Impossible de détecter le type de page (NEW vs EDIT)');
            return;
        }
        
        this.attachToButtons(config);
    }
    
    /**
     * Affiche un message de succès
     */
    showSuccessMessage(message) {
        // Créer un toast Bootstrap
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Supprimer automatiquement après 3 secondes
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 3000);
    }
    
    /**
     * Nettoie les ressources
     */
    destroy() {
        if (this.modalService) {
            this.modalService.cleanup();
            this.modalService = null;
        }
        this.initialized = false;
        this.log('🗑️ Service détruit');
    }
}

// Rendre disponible globalement
window.ContactModalService = ContactModalService;