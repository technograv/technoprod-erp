/**
 * Service de gestion des modales imbriquées
 * Orchestre la création et l'édition de clients, contacts et adresses
 * avec navigation fluide entre les modales
 */
class NestedModalService {
    constructor(config = {}) {
        this.config = {
            debug: config.debug || false,
            autoSelectCreated: config.autoSelectCreated !== false,
            refreshOnComplete: config.refreshOnComplete || false,
            ...config
        };
        
        // Stack de modales actives
        this.modalStack = [];
        
        // Mémoire temporaire pour les entités en cours
        this.pendingData = {
            client: null,
            contact: null,
            address: null
        };
        
        // Contexte de sélection pour auto-remplissage
        this.selectionContext = {
            clientSelect: null,
            contactFacturationSelect: null,
            contactLivraisonSelect: null,
            addressFacturationSelect: null,
            addressLivraisonSelect: null,
            targetType: null // 'facturation' ou 'livraison'
        };
        
        // Services de modales
        this.modalService = null;
        this.clientModalService = null;
        this.contactModalService = null;
        this.addressModalService = null;
        
        // État actuel
        this.currentClientId = null;
        this.isNavigating = false;
        
        this.log('🚀 NestedModalService initialisé');
        this.init();
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[NestedModalService] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[NestedModalService] ${message}`, error || '');
    }
    
    /**
     * Initialise les services
     */
    init() {
        // Initialiser le service de base si disponible
        if (!window.ModalService) {
            this.error('ModalService non disponible');
            return false;
        }
        
        // Créer une instance du service de modales de base
        this.modalService = new ModalService({
            debug: this.config.debug,
            modalId: 'nested-modal',
            refreshOnSuccess: false
        });
        
        // Initialiser les services spécialisés s'ils existent
        if (window.ClientModalService) {
            this.clientModalService = new ClientModalService({
                debug: this.config.debug,
                onClientCreated: (detail) => this.handleClientCreated(detail),
                onClientUpdated: (detail) => this.handleClientUpdated(detail)
            });
        }
        
        if (window.ContactModalService) {
            this.contactModalService = new ContactModalService({
                debug: this.config.debug,
                onContactCreated: (detail) => this.handleContactCreated(detail),
                onContactUpdated: (detail) => this.handleContactUpdated(detail)
            });
        }
        
        if (window.AddressModalService) {
            this.addressModalService = new AddressModalService({
                debug: this.config.debug,
                onAddressCreated: (detail) => this.handleAddressCreated(detail),
                onAddressUpdated: (detail) => this.handleAddressUpdated(detail)
            });
        }
        
        this.setupEventListeners();
        this.log('✅ Services initialisés');
        return true;
    }
    
    /**
     * Configure les écouteurs d'événements globaux
     */
    setupEventListeners() {
        // Écouter les événements de navigation inter-modales
        window.addEventListener('navigateToContactModal', (event) => {
            this.log('📍 Navigation vers modale contact', event.detail);
            this.openContactModal(event.detail.mode, event.detail.entityId, event.detail.type, true);
        });
        
        window.addEventListener('navigateToAddressModal', (event) => {
            this.log('📍 Navigation vers modale adresse', event.detail);
            this.openAddressModal(event.detail.mode, event.detail.entityId, event.detail.type, true);
        });
        
        window.addEventListener('navigateBack', (event) => {
            this.log('⬅️ Navigation retour');
            this.navigateBack();
        });
        
        // Écouter les fermetures de modales
        window.addEventListener('modalClosed', () => {
            this.handleModalClosed();
        });
    }
    
    /**
     * Attache le service aux éléments de la page devis
     */
    attachToDevisPage(selectors = {}) {
        this.selectionContext = {
            clientSelect: selectors.clientSelect || 'devis_client',
            contactFacturationSelect: selectors.contactFacturationSelect || 'devis_contactFacturation',
            contactLivraisonSelect: selectors.contactLivraisonSelect || 'devis_contactLivraison',
            addressFacturationSelect: selectors.addressFacturationSelect || 'devis_addressFacturation',
            addressLivraisonSelect: selectors.addressLivraisonSelect || 'devis_addressLivraison'
        };
        
        // Attacher les événements aux boutons
        this.attachClientButtons();
        this.attachContactButtons();
        this.attachAddressButtons();
        
        this.log('✅ Service attaché à la page devis');
    }
    
    /**
     * Attache les événements aux boutons client
     */
    attachClientButtons() {
        // Bouton créer client
        const addClientBtn = document.getElementById('add-client-btn');
        if (addClientBtn) {
            addClientBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openClientModal('create');
            });
        }
        
        // Bouton éditer client
        const editClientBtn = document.getElementById('edit-client-btn');
        if (editClientBtn) {
            editClientBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const clientSelect = document.getElementById(this.selectionContext.clientSelect);
                if (clientSelect && clientSelect.value) {
                    this.openClientModal('edit', clientSelect.value);
                }
            });
        }
    }
    
    /**
     * Attache les événements aux boutons contact
     */
    attachContactButtons() {
        // Boutons créer contact (facturation et livraison)
        const addContactBtns = document.querySelectorAll('.add-contact-btn');
        addContactBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const type = btn.dataset.type || 'livraison';
                this.selectionContext.targetType = type;
                
                const clientSelect = document.getElementById(this.selectionContext.clientSelect);
                if (clientSelect && clientSelect.value) {
                    this.openContactModal('create', clientSelect.value, type);
                } else {
                    alert('Veuillez d\'abord sélectionner un client');
                }
            });
        });
        
        // Boutons éditer contact
        const editContactBtns = document.querySelectorAll('.edit-contact-btn');
        editContactBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const type = btn.dataset.type || 'livraison';
                const selectId = type === 'facturation' ? 
                    this.selectionContext.contactFacturationSelect : 
                    this.selectionContext.contactLivraisonSelect;
                
                const contactSelect = document.getElementById(selectId);
                if (contactSelect && contactSelect.value) {
                    this.openContactModal('edit', contactSelect.value, type);
                }
            });
        });
    }
    
    /**
     * Attache les événements aux boutons adresse
     */
    attachAddressButtons() {
        // Boutons créer adresse
        const addAddressBtns = document.querySelectorAll('.add-address-btn');
        addAddressBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const type = btn.dataset.type || 'livraison';
                const fromModal = btn.dataset.fromModal === 'true';
                
                const clientSelect = document.getElementById(this.selectionContext.clientSelect);
                if (clientSelect && clientSelect.value) {
                    this.openAddressModal('create', clientSelect.value, type, fromModal);
                } else {
                    alert('Veuillez d\'abord sélectionner un client');
                }
            });
        });
        
        // Boutons éditer adresse
        const editAddressBtns = document.querySelectorAll('.edit-address-btn');
        editAddressBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const type = btn.dataset.type || 'livraison';
                const fromModal = btn.dataset.fromModal === 'true';
                const selectId = type === 'facturation' ? 
                    this.selectionContext.addressFacturationSelect : 
                    this.selectionContext.addressLivraisonSelect;
                
                const addressSelect = document.getElementById(selectId);
                if (addressSelect && addressSelect.value) {
                    this.openAddressModal('edit', addressSelect.value, type, fromModal);
                }
            });
        });
    }
    
    /**
     * Ouvre la modale client
     */
    async openClientModal(mode = 'create', clientId = null) {
        this.log(`📂 Ouverture modale client en mode ${mode}`);
        
        try {
            // Réinitialiser si nouvelle session
            if (!this.isNavigating) {
                this.resetState();
            }
            
            // Ajouter à la pile
            this.pushModal('client', { mode, clientId });
            
            // Déterminer l'URL
            const url = mode === 'edit' ? 
                `/client/modal/edit/${clientId}` : 
                '/client/modal/new';
            
            // Ouvrir la modale
            await this.modalService.openModal(url, mode === 'edit' ? 'Modifier le client' : 'Nouveau client');
            
            // Attacher les événements spécifiques au formulaire
            this.attachClientFormEvents();
            
        } catch (error) {
            this.error('Erreur ouverture modale client:', error);
            this.popModal();
        }
    }
    
    /**
     * Ouvre la modale contact
     */
    async openContactModal(mode = 'create', entityId = null, type = 'livraison', fromClientModal = false) {
        this.log(`📞 Ouverture modale contact en mode ${mode} pour ${type}`);
        
        try {
            // Si on vient de la modale client, sauvegarder l'état
            if (fromClientModal) {
                this.savePendingClientData();
                this.isNavigating = true;
            }
            
            // Ajouter à la pile
            this.pushModal('contact', { mode, entityId, type, fromClientModal });
            
            // Déterminer l'URL et le client ID
            let url;
            if (mode === 'edit') {
                url = `/contact/modal/edit/${entityId}`;
            } else {
                // Pour la création, on a besoin du client ID
                const clientId = this.currentClientId || 
                    document.getElementById(this.selectionContext.clientSelect)?.value;
                
                if (!clientId) {
                    throw new Error('Client ID requis pour créer un contact');
                }
                url = `/contact/modal/new/${clientId}`;
            }
            
            // Fermer la modale actuelle si navigation
            if (fromClientModal && this.modalService.currentModal) {
                this.modalService.closeModal();
                // Attendre la fermeture
                await new Promise(resolve => setTimeout(resolve, 300));
            }
            
            // Ouvrir la nouvelle modale
            await this.modalService.openModal(url, mode === 'edit' ? 'Modifier le contact' : 'Nouveau contact');
            
            // Attacher les événements
            this.attachContactFormEvents(type);
            
        } catch (error) {
            this.error('Erreur ouverture modale contact:', error);
            this.popModal();
        }
    }
    
    /**
     * Ouvre la modale adresse
     */
    async openAddressModal(mode = 'create', entityId = null, type = 'livraison', fromContactModal = false) {
        this.log(`📍 Ouverture modale adresse en mode ${mode} pour ${type}`);
        
        try {
            // Si on vient de la modale contact, sauvegarder l'état
            if (fromContactModal) {
                this.savePendingContactData();
                this.isNavigating = true;
            }
            
            // Ajouter à la pile
            this.pushModal('address', { mode, entityId, type, fromContactModal });
            
            // Déterminer l'URL
            let url;
            if (mode === 'edit') {
                url = `/adresse/modal/edit/${entityId}`;
            } else {
                const clientId = this.currentClientId || 
                    document.getElementById(this.selectionContext.clientSelect)?.value;
                
                if (!clientId) {
                    throw new Error('Client ID requis pour créer une adresse');
                }
                url = `/adresse/modal/new/${clientId}`;
            }
            
            // Fermer la modale actuelle si navigation
            if (fromContactModal && this.modalService.currentModal) {
                this.modalService.closeModal();
                await new Promise(resolve => setTimeout(resolve, 300));
            }
            
            // Ouvrir la nouvelle modale
            await this.modalService.openModal(url, mode === 'edit' ? 'Modifier l\'adresse' : 'Nouvelle adresse');
            
            // Attacher les événements
            this.attachAddressFormEvents(type);
            
        } catch (error) {
            this.error('Erreur ouverture modale adresse:', error);
            this.popModal();
        }
    }
    
    /**
     * Sauvegarde les données du client en cours
     */
    savePendingClientData() {
        const form = document.querySelector('#nested-modal form');
        if (!form) return;
        
        const formData = new FormData(form);
        this.pendingData.client = {
            formeJuridique: formData.get('formeJuridique'),
            nom: formData.get('nom'),
            prenom: formData.get('prenom'),
            civilite: formData.get('civilite'),
            denominationSociale: formData.get('denominationSociale'),
            email: formData.get('email'),
            telephone: formData.get('telephone'),
            mobile: formData.get('mobile'),
            modeReglement: formData.get('modeReglement')
        };
        
        this.log('💾 Données client sauvegardées', this.pendingData.client);
    }
    
    /**
     * Sauvegarde les données du contact en cours
     */
    savePendingContactData() {
        const form = document.querySelector('#nested-modal form');
        if (!form) return;
        
        const formData = new FormData(form);
        this.pendingData.contact = {
            civilite: formData.get('civilite'),
            prenom: formData.get('prenom'),
            nom: formData.get('nom'),
            email: formData.get('email'),
            fonction: formData.get('fonction'),
            telephoneFixe: formData.get('telephoneFixe'),
            mobile: formData.get('mobile'),
            adresseId: formData.get('adresse'),
            defautFacturation: formData.get('defautFacturation') === 'on',
            defautLivraison: formData.get('defautLivraison') === 'on'
        };
        
        this.log('💾 Données contact sauvegardées', this.pendingData.contact);
    }
    
    /**
     * Attache les événements au formulaire client
     */
    attachClientFormEvents() {
        const form = document.querySelector('#nested-modal form');
        if (!form) return;
        
        // Bouton pour ajouter un contact depuis le formulaire client
        const addContactBtn = form.querySelector('.btn-add-contact-from-client');
        if (addContactBtn) {
            addContactBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openContactModal('create', null, 'livraison', true);
            });
        }
        
        // Intercepter la soumission pour gérer les cas spéciaux
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleClientFormSubmit(form);
        });
    }
    
    /**
     * Attache les événements au formulaire contact
     */
    attachContactFormEvents(type) {
        const form = document.querySelector('#nested-modal form');
        if (!form) return;
        
        // Boutons pour gérer les adresses depuis le formulaire contact
        const addAddressBtn = form.querySelector('.btn-add-address-from-contact');
        if (addAddressBtn) {
            addAddressBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openAddressModal('create', null, type, true);
            });
        }
        
        const editAddressBtn = form.querySelector('.btn-edit-address-from-contact');
        if (editAddressBtn) {
            editAddressBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const addressSelect = form.querySelector('select[name="adresse"]');
                if (addressSelect && addressSelect.value) {
                    this.openAddressModal('edit', addressSelect.value, type, true);
                }
            });
        }
        
        // Intercepter la soumission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleContactFormSubmit(form);
        });
    }
    
    /**
     * Attache les événements au formulaire adresse
     */
    attachAddressFormEvents(type) {
        const form = document.querySelector('#nested-modal form');
        if (!form) return;
        
        // Intercepter la soumission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleAddressFormSubmit(form);
        });
    }
    
    /**
     * Gère la soumission du formulaire client
     */
    async handleClientFormSubmit(form) {
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                
                // Si on a des données en attente (contact/adresse), créer les entités liées
                if (this.pendingData.contact || this.pendingData.address) {
                    this.currentClientId = result.client.id;
                    await this.createPendingEntities();
                } else {
                    // Sinon, sélectionner directement le client
                    this.selectCreatedClient(result.client);
                    this.modalService.closeModal();
                    this.resetState();
                }
            } else {
                const error = await response.text();
                this.showFormErrors(error);
            }
        } catch (error) {
            this.error('Erreur soumission formulaire client:', error);
        }
    }
    
    /**
     * Gère la soumission du formulaire contact
     */
    async handleContactFormSubmit(form) {
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                
                // Si on naviguait depuis client, retourner et finaliser
                const lastModal = this.getLastModal();
                if (lastModal && lastModal.fromClientModal) {
                    this.pendingData.contact = result.contact;
                    await this.navigateBack();
                    
                    // Si on était en train de créer un client, le finaliser maintenant
                    if (this.pendingData.client) {
                        const clientForm = document.querySelector('#nested-modal form');
                        if (clientForm) {
                            await this.handleClientFormSubmit(clientForm);
                        }
                    }
                } else {
                    // Sinon, sélectionner directement le contact
                    this.selectCreatedContact(result.contact);
                    this.modalService.closeModal();
                }
            } else {
                const error = await response.text();
                this.showFormErrors(error);
            }
        } catch (error) {
            this.error('Erreur soumission formulaire contact:', error);
        }
    }
    
    /**
     * Gère la soumission du formulaire adresse
     */
    async handleAddressFormSubmit(form) {
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                
                // Si on naviguait depuis contact, retourner
                const lastModal = this.getLastModal();
                if (lastModal && lastModal.fromContactModal) {
                    this.pendingData.address = result.address;
                    await this.navigateBack();
                    
                    // Sélectionner l'adresse dans le formulaire contact
                    const addressSelect = document.querySelector('#nested-modal select[name="adresse"]');
                    if (addressSelect) {
                        // Ajouter l'option si elle n'existe pas
                        const option = new Option(result.address.label, result.address.id, true, true);
                        addressSelect.add(option);
                        addressSelect.value = result.address.id;
                    }
                } else {
                    // Sinon, sélectionner directement l'adresse
                    this.selectCreatedAddress(result.address);
                    this.modalService.closeModal();
                }
            } else {
                const error = await response.text();
                this.showFormErrors(error);
            }
        } catch (error) {
            this.error('Erreur soumission formulaire adresse:', error);
        }
    }
    
    /**
     * Navigue vers la modale précédente
     */
    async navigateBack() {
        if (this.modalStack.length <= 1) return;
        
        this.log('⬅️ Navigation retour');
        
        // Retirer la modale actuelle de la pile
        this.popModal();
        
        // Fermer la modale actuelle
        if (this.modalService.currentModal) {
            this.modalService.closeModal();
            await new Promise(resolve => setTimeout(resolve, 300));
        }
        
        // Récupérer la modale précédente
        const previousModal = this.getLastModal();
        if (!previousModal) return;
        
        // Rouvrir la modale précédente
        switch (previousModal.type) {
            case 'client':
                await this.openClientModal(previousModal.data.mode, previousModal.data.clientId);
                // Restaurer les données du formulaire
                if (this.pendingData.client) {
                    this.restoreClientFormData();
                }
                break;
                
            case 'contact':
                await this.openContactModal(
                    previousModal.data.mode, 
                    previousModal.data.entityId,
                    previousModal.data.type,
                    previousModal.data.fromClientModal
                );
                // Restaurer les données du formulaire
                if (this.pendingData.contact) {
                    this.restoreContactFormData();
                }
                break;
                
            case 'address':
                await this.openAddressModal(
                    previousModal.data.mode,
                    previousModal.data.entityId,
                    previousModal.data.type,
                    previousModal.data.fromContactModal
                );
                break;
        }
    }
    
    /**
     * Restaure les données du formulaire client
     */
    restoreClientFormData() {
        if (!this.pendingData.client) return;
        
        const form = document.querySelector('#nested-modal form');
        if (!form) return;
        
        Object.entries(this.pendingData.client).forEach(([key, value]) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field && value !== null && value !== undefined) {
                field.value = value;
            }
        });
        
        this.log('♻️ Données client restaurées');
    }
    
    /**
     * Restaure les données du formulaire contact
     */
    restoreContactFormData() {
        if (!this.pendingData.contact) return;
        
        const form = document.querySelector('#nested-modal form');
        if (!form) return;
        
        Object.entries(this.pendingData.contact).forEach(([key, value]) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = value === true;
                } else if (value !== null && value !== undefined) {
                    field.value = value;
                }
            }
        });
        
        this.log('♻️ Données contact restaurées');
    }
    
    /**
     * Crée les entités en attente après la création du client
     */
    async createPendingEntities() {
        try {
            // Créer le contact si nécessaire
            if (this.pendingData.contact) {
                const contactData = {
                    ...this.pendingData.contact,
                    client: this.currentClientId
                };
                
                const response = await fetch(`/contact/api/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(contactData)
                });
                
                if (response.ok) {
                    const result = await response.json();
                    this.selectCreatedContact(result.contact);
                }
            }
            
            // Créer l'adresse si nécessaire
            if (this.pendingData.address) {
                const addressData = {
                    ...this.pendingData.address,
                    client: this.currentClientId
                };
                
                const response = await fetch(`/adresse/api/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(addressData)
                });
                
                if (response.ok) {
                    const result = await response.json();
                    this.selectCreatedAddress(result.address);
                }
            }
            
            // Fermer la modale et réinitialiser
            this.modalService.closeModal();
            this.resetState();
            
        } catch (error) {
            this.error('Erreur création entités liées:', error);
        }
    }
    
    /**
     * Sélectionne automatiquement le client créé
     */
    selectCreatedClient(client) {
        if (!this.config.autoSelectCreated) return;
        
        const clientSelect = document.getElementById(this.selectionContext.clientSelect);
        if (clientSelect && window.$ && $.fn.select2) {
            // Ajouter l'option si elle n'existe pas
            const option = new Option(client.label || client.nom, client.id, true, true);
            $(clientSelect).append(option).trigger('change');
            
            this.log('✅ Client sélectionné:', client);
            
            // Déclencher le chargement des contacts
            clientSelect.dispatchEvent(new Event('change'));
        }
    }
    
    /**
     * Sélectionne automatiquement le contact créé
     */
    selectCreatedContact(contact) {
        if (!this.config.autoSelectCreated) return;
        
        const type = this.selectionContext.targetType || 'livraison';
        const selectId = type === 'facturation' ? 
            this.selectionContext.contactFacturationSelect : 
            this.selectionContext.contactLivraisonSelect;
        
        const contactSelect = document.getElementById(selectId);
        if (contactSelect && window.$ && $.fn.select2) {
            const option = new Option(contact.label || `${contact.prenom} ${contact.nom}`, contact.id, true, true);
            $(contactSelect).append(option).trigger('change');
            
            this.log(`✅ Contact ${type} sélectionné:`, contact);
            
            // Déclencher le chargement des adresses
            contactSelect.dispatchEvent(new Event('change'));
        }
    }
    
    /**
     * Sélectionne automatiquement l'adresse créée
     */
    selectCreatedAddress(address) {
        if (!this.config.autoSelectCreated) return;
        
        const type = this.selectionContext.targetType || 'livraison';
        const selectId = type === 'facturation' ? 
            this.selectionContext.addressFacturationSelect : 
            this.selectionContext.addressLivraisonSelect;
        
        const addressSelect = document.getElementById(selectId);
        if (addressSelect && window.$ && $.fn.select2) {
            const option = new Option(address.label || address.ligne1, address.id, true, true);
            $(addressSelect).append(option).trigger('change');
            
            this.log(`✅ Adresse ${type} sélectionnée:`, address);
        }
    }
    
    /**
     * Gère la création d'un client
     */
    handleClientCreated(detail) {
        this.log('✅ Client créé via event:', detail);
        this.selectCreatedClient(detail.client);
        
        // Callback personnalisé
        if (this.config.onClientCreated) {
            this.config.onClientCreated(detail);
        }
    }
    
    /**
     * Gère la mise à jour d'un client
     */
    handleClientUpdated(detail) {
        this.log('✅ Client mis à jour via event:', detail);
        
        // Rafraîchir les données du client si nécessaire
        const clientSelect = document.getElementById(this.selectionContext.clientSelect);
        if (clientSelect && clientSelect.value === String(detail.client.id)) {
            // Mettre à jour le label si nécessaire
            if (window.$ && $.fn.select2) {
                const option = clientSelect.querySelector(`option[value="${detail.client.id}"]`);
                if (option) {
                    option.textContent = detail.client.label || detail.client.nom;
                    $(clientSelect).trigger('change.select2');
                }
            }
        }
        
        // Callback personnalisé
        if (this.config.onClientUpdated) {
            this.config.onClientUpdated(detail);
        }
    }
    
    /**
     * Gère la création d'un contact
     */
    handleContactCreated(detail) {
        this.log('✅ Contact créé via event:', detail);
        this.selectCreatedContact(detail.contact);
        
        if (this.config.onContactCreated) {
            this.config.onContactCreated(detail);
        }
    }
    
    /**
     * Gère la mise à jour d'un contact
     */
    handleContactUpdated(detail) {
        this.log('✅ Contact mis à jour via event:', detail);
        
        if (this.config.onContactUpdated) {
            this.config.onContactUpdated(detail);
        }
    }
    
    /**
     * Gère la création d'une adresse
     */
    handleAddressCreated(detail) {
        this.log('✅ Adresse créée via event:', detail);
        this.selectCreatedAddress(detail.address);
        
        if (this.config.onAddressCreated) {
            this.config.onAddressCreated(detail);
        }
    }
    
    /**
     * Gère la mise à jour d'une adresse
     */
    handleAddressUpdated(detail) {
        this.log('✅ Adresse mise à jour via event:', detail);
        
        if (this.config.onAddressUpdated) {
            this.config.onAddressUpdated(detail);
        }
    }
    
    /**
     * Gère la fermeture d'une modale
     */
    handleModalClosed() {
        // Si on n'est pas en navigation, réinitialiser
        if (!this.isNavigating) {
            this.resetState();
        }
        this.isNavigating = false;
    }
    
    /**
     * Affiche les erreurs de formulaire
     */
    showFormErrors(errorHtml) {
        const modalBody = document.querySelector('#nested-modal .modal-body');
        if (modalBody) {
            // Injecter les erreurs en haut du formulaire
            const errorDiv = document.createElement('div');
            errorDiv.innerHTML = errorHtml;
            
            // Extraire les messages d'erreur si c'est du HTML
            const alerts = errorDiv.querySelectorAll('.alert-danger');
            if (alerts.length > 0) {
                const firstElement = modalBody.firstElementChild;
                alerts.forEach(alert => {
                    modalBody.insertBefore(alert, firstElement);
                });
            } else {
                // Sinon créer une alerte générique
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger';
                alert.textContent = 'Une erreur est survenue lors de la validation du formulaire.';
                modalBody.insertBefore(alert, modalBody.firstElementChild);
            }
        }
    }
    
    /**
     * Ajoute une modale à la pile
     */
    pushModal(type, data = {}) {
        this.modalStack.push({ type, data, timestamp: Date.now() });
        this.log('📚 Pile modales:', this.modalStack);
    }
    
    /**
     * Retire la dernière modale de la pile
     */
    popModal() {
        const removed = this.modalStack.pop();
        this.log('📤 Modale retirée:', removed);
        return removed;
    }
    
    /**
     * Récupère la dernière modale de la pile
     */
    getLastModal() {
        return this.modalStack[this.modalStack.length - 1];
    }
    
    /**
     * Réinitialise l'état complet
     */
    resetState() {
        this.modalStack = [];
        this.pendingData = {
            client: null,
            contact: null,
            address: null
        };
        this.currentClientId = null;
        this.isNavigating = false;
        this.selectionContext.targetType = null;
        
        this.log('🔄 État réinitialisé');
    }
}

// Rendre disponible globalement
window.NestedModalService = NestedModalService;