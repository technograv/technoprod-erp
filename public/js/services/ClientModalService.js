/**
 * Service de gestion des modales de client
 * Fournit une interface simplifiée pour créer et modifier des clients
 */
class ClientModalService {
    constructor(config = {}) {
        this.config = {
            debug: config.debug || false,
            onClientCreated: config.onClientCreated || null,
            onClientUpdated: config.onClientUpdated || null,
            ...config
        };
        
        this.modalService = null;
        this.initialized = false;
        
        this.log('🚀 ClientModalService initialisé');
        this.init();
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[ClientModalService] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[ClientModalService] ${message}`, error || '');
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
            modalId: 'client-modal',
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
        // Écouter les événements de création/modification de client
        window.addEventListener('clientCreated', (event) => {
            this.log('✅ Client créé:', event.detail.client);
            this.showSuccessMessage(event.detail.message);
            
            // Callback personnalisé
            if (this.config.onClientCreated) {
                this.config.onClientCreated(event.detail);
            }
        });
        
        window.addEventListener('clientUpdated', (event) => {
            this.log('✅ Client modifié:', event.detail.client);
            this.showSuccessMessage(event.detail.message);
            
            // Callback personnalisé
            if (this.config.onClientUpdated) {
                this.config.onClientUpdated(event.detail);
            }
        });
    }
    
    /**
     * Ouvre la modale de création de client
     */
    createClient(type = 'client') {
        if (!this.initialized) {
            this.error('Service non initialisé');
            return;
        }
        
        const url = `/client/modal/new`;
        this.log('🔧 Ouverture modale création client de type:', type);
        this.modalService.openModal(url, 'Ajouter un ' + (type === 'prospect' ? 'prospect' : 'client'));
    }
    
    /**
     * Ouvre la modale d'édition de client
     */
    editClient(clientId) {
        if (!this.initialized) {
            this.error('Service non initialisé');
            return;
        }
        
        if (!clientId) {
            alert('Veuillez d\'abord sélectionner un client');
            return;
        }
        
        const url = `/client/modal/edit/${clientId}`;
        this.log('🔧 Ouverture modale édition client:', clientId);
        this.modalService.openModal(url, 'Modifier le client');
    }
    
    /**
     * Attache les gestionnaires d'événements aux boutons de client
     */
    attachToButtons(config) {
        const {
            addClientBtn,
            editClientBtn,
            clientSelector
        } = config;
        
        // Bouton d'ajout de client
        if (addClientBtn) {
            const btn = typeof addClientBtn === 'string' 
                ? document.getElementById(addClientBtn)
                : addClientBtn;
                
            btn?.addEventListener('click', () => {
                this.createClient();
            });
        }
        
        // Bouton d'édition de client
        if (editClientBtn) {
            const btn = typeof editClientBtn === 'string' 
                ? document.getElementById(editClientBtn)
                : editClientBtn;
                
            btn?.addEventListener('click', () => {
                const clientSelect = typeof clientSelector === 'string'
                    ? document.getElementById(clientSelector)
                    : clientSelector;
                let clientId = clientSelect?.value;
                
                // Si le clientId n'est pas trouvé dans le select principal, 
                // chercher dans le champ caché client_field (pour les pages de devis)
                if (!clientId && document.getElementById('client_field')) {
                    clientId = document.getElementById('client_field').value;
                }
                
                this.editClient(clientId);
            });
        }
        
        this.log('✅ Gestionnaires d\'événements attachés aux boutons');
    }
    
    /**
     * Configuration simple pour les pages de devis
     */
    attachToDevisPage() {
        this.attachToButtons({
            addClientBtn: 'add-client-btn',
            editClientBtn: 'edit-client-btn',
            clientSelector: 'prospect'
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
window.ClientModalService = ClientModalService;