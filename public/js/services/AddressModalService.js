/**
 * Service de gestion des modales d'adresse
 * Fournit une interface simplifiée pour créer et modifier des adresses
 */
class AddressModalService {
    constructor(config = {}) {
        this.config = {
            debug: config.debug || false,
            onAddressCreated: config.onAddressCreated || null,
            onAddressUpdated: config.onAddressUpdated || null,
            ...config
        };
        
        this.modalService = null;
        this.initialized = false;
        
        this.log('🚀 AddressModalService initialisé');
        this.init();
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[AddressModalService] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[AddressModalService] ${message}`, error || '');
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
            modalId: 'address-modal',
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
        // Écouter les événements de création/modification d'adresse
        window.addEventListener('addressCreated', (event) => {
            this.log('✅ Adresse créée:', event.detail.adresse);
            this.showSuccessMessage(event.detail.message);
            
            // Callback personnalisé
            if (this.config.onAddressCreated) {
                this.config.onAddressCreated(event.detail);
            }
        });
        
        window.addEventListener('addressUpdated', (event) => {
            this.log('✅ Adresse modifiée:', event.detail.adresse);
            this.showSuccessMessage(event.detail.message);
            
            // Callback personnalisé
            if (this.config.onAddressUpdated) {
                this.config.onAddressUpdated(event.detail);
            }
        });
    }
    
    /**
     * Ouvre la modale de création d'adresse
     */
    createAddress(clientId) {
        if (!this.initialized) {
            this.error('Service non initialisé');
            return;
        }
        
        if (!clientId) {
            alert('Veuillez d\'abord sélectionner un client');
            return;
        }
        
        const url = `/adresse/modal/new/${clientId}`;
        this.log('🔧 Ouverture modale création adresse pour client:', clientId);
        this.modalService.openModal(url, 'Ajouter une adresse');
    }
    
    /**
     * Ouvre la modale d'édition d'adresse
     */
    editAddress(addressId) {
        if (!this.initialized) {
            this.error('Service non initialisé');
            return;
        }
        
        if (!addressId) {
            alert('Veuillez d\'abord sélectionner une adresse');
            return;
        }
        
        const url = `/adresse/modal/edit/${addressId}`;
        this.log('🔧 Ouverture modale édition adresse:', addressId);
        this.modalService.openModal(url, 'Modifier l\'adresse');
    }
    
    /**
     * Attache les gestionnaires d'événements aux boutons d'adresse
     */
    attachToButtons(config) {
        const {
            addLivraisonBtn,
            addFacturationBtn,
            editLivraisonBtn,
            editFacturationBtn,
            clientSelector,
            addressLivraisonSelector,
            addressFacturationSelector
        } = config;
        
        // Bouton d'ajout d'adresse de livraison
        if (addLivraisonBtn) {
            const btn = typeof addLivraisonBtn === 'string' 
                ? document.getElementById(addLivraisonBtn)
                : addLivraisonBtn;
                
            btn?.addEventListener('click', () => {
                const clientSelect = typeof clientSelector === 'string'
                    ? document.getElementById(clientSelector)
                    : clientSelector;
                const clientId = clientSelect?.value;
                this.createAddress(clientId);
            });
        }
        
        // Bouton d'ajout d'adresse de facturation
        if (addFacturationBtn) {
            const btn = typeof addFacturationBtn === 'string' 
                ? document.getElementById(addFacturationBtn)
                : addFacturationBtn;
                
            btn?.addEventListener('click', () => {
                const clientSelect = typeof clientSelector === 'string'
                    ? document.getElementById(clientSelector)
                    : clientSelector;
                const clientId = clientSelect?.value;
                this.createAddress(clientId);
            });
        }
        
        // Bouton d'édition d'adresse de livraison
        if (editLivraisonBtn) {
            const btn = typeof editLivraisonBtn === 'string' 
                ? document.getElementById(editLivraisonBtn)
                : editLivraisonBtn;
                
            btn?.addEventListener('click', () => {
                const adresseSelect = typeof addressLivraisonSelector === 'string'
                    ? document.getElementById(addressLivraisonSelector)
                    : addressLivraisonSelector;
                const adresseId = adresseSelect?.value;
                this.editAddress(adresseId);
            });
        }
        
        // Bouton d'édition d'adresse de facturation
        if (editFacturationBtn) {
            const btn = typeof editFacturationBtn === 'string' 
                ? document.getElementById(editFacturationBtn)
                : editFacturationBtn;
                
            btn?.addEventListener('click', () => {
                const adresseSelect = typeof addressFacturationSelector === 'string'
                    ? document.getElementById(addressFacturationSelector)
                    : addressFacturationSelector;
                const adresseId = adresseSelect?.value;
                this.editAddress(adresseId);
            });
        }
        
        this.log('✅ Gestionnaires d\'événements attachés aux boutons');
    }
    
    /**
     * Configuration simple pour les pages de devis
     */
    attachToDevisPage() {
        this.attachToButtons({
            addLivraisonBtn: 'add-address-livraison-btn',
            addFacturationBtn: 'add-address-facturation-btn',
            editLivraisonBtn: 'edit-address-livraison-btn',
            editFacturationBtn: 'edit-address-facturation-btn',
            clientSelector: 'devis_client',
            addressLivraisonSelector: 'adresse_livraison_select',
            addressFacturationSelector: 'adresse_facturation_select'
        });
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
window.AddressModalService = AddressModalService;