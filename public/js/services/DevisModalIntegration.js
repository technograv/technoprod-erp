/**
 * Service d'intégration des modales pour les pages de devis
 * Fait le lien entre l'interface utilisateur et le NestedModalService
 */
class DevisModalIntegration {
    constructor(config = {}) {
        this.config = {
            debug: config.debug || false,
            selectors: {
                // Sélecteurs principaux
                clientSelect: 'devis_client',
                addClientBtn: 'add-client-btn',
                editClientBtn: 'edit-client-btn',
                
                // Contacts livraison
                contactLivraisonSelect: 'devis_contactLivraison',
                addContactLivraisonBtn: 'add-contact-btn',
                editContactLivraisonBtn: 'edit-contact-livraison-btn',
                
                // Contacts facturation
                contactFacturationSelect: 'devis_contactFacturation',
                addContactFacturationBtn: 'add-contact-facturation-btn',
                editContactFacturationBtn: 'edit-contact-facturation-btn',
                
                // Adresses
                adresseLivraisonDisplay: 'livraison-address-display',
                adresseFacturationDisplay: 'facturation-address-display',
                addAdresseLivraisonBtn: 'add-address-livraison-btn',
                editAdresseLivraisonBtn: 'edit-address-livraison-btn',
                addAdresseFacturationBtn: 'add-address-facturation-btn',
                editAdresseFacturationBtn: 'edit-address-facturation-btn'
            },
            ...config
        };
        
        this.nestedModalService = null;
        this.currentClientId = null;
        
        this.log('🚀 DevisModalIntegration initialisé');
        this.init();
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[DevisModalIntegration] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[DevisModalIntegration] ${message}`, error || '');
    }
    
    /**
     * Initialisation du service
     */
    init() {
        // Vérifier les dépendances
        if (!window.NestedModalService) {
            this.error('NestedModalService non disponible');
            return false;
        }
        
        // Initialiser le service de modales imbriquées
        this.nestedModalService = new NestedModalService({
            debug: this.config.debug,
            clientSelect: this.config.selectors.clientSelect,
            contactLivraisonSelect: this.config.selectors.contactLivraisonSelect,
            contactFacturationSelect: this.config.selectors.contactFacturationSelect,
            adresseLivraisonDisplay: this.config.selectors.adresseLivraisonDisplay,
            adresseFacturationDisplay: this.config.selectors.adresseFacturationDisplay
        });
        
        // Attacher les événements
        this.attachEvents();
        
        this.log('✅ Service initialisé avec succès');
        return true;
    }
    
    /**
     * Attache tous les événements aux boutons de l'interface
     */
    attachEvents() {
        this.log('🔗 Attachement des événements...');
        
        // Événements client
        this.attachClientEvents();
        
        // Événements contacts
        this.attachContactEvents();
        
        // Événements adresses
        this.attachAddressEvents();
        
        // Événement de changement de client
        this.attachClientChangeEvent();
        
        this.log('✅ Tous les événements attachés');
    }
    
    /**
     * Attache les événements liés aux clients
     */
    attachClientEvents() {
        // Bouton créer client
        const addClientBtn = document.getElementById(this.config.selectors.addClientBtn);
        if (addClientBtn) {
            addClientBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.nestedModalService.openClientCreationModal();
            });
            this.log('✅ Bouton créer client attaché');
        } else {
            this.log('⚠️ Bouton créer client non trouvé');
        }
        
        // Bouton éditer client
        const editClientBtn = document.getElementById(this.config.selectors.editClientBtn);
        if (editClientBtn) {
            editClientBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentClientId) {
                    this.nestedModalService.openClientEditModal(this.currentClientId);
                }
            });
            this.log('✅ Bouton éditer client attaché');
        } else {
            this.log('⚠️ Bouton éditer client non trouvé');
        }
    }
    
    /**
     * Attache les événements liés aux contacts
     */
    attachContactEvents() {
        // Contacts de livraison
        const addContactLivraisonBtn = document.getElementById(this.config.selectors.addContactLivraisonBtn);
        if (addContactLivraisonBtn) {
            addContactLivraisonBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentClientId) {
                    this.nestedModalService.openContactCreationModal(this.currentClientId, 'livraison');
                }
            });
            this.log('✅ Bouton créer contact livraison attaché');
        }
        
        const editContactLivraisonBtn = document.getElementById(this.config.selectors.editContactLivraisonBtn);
        if (editContactLivraisonBtn) {
            editContactLivraisonBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const contactSelect = document.getElementById(this.config.selectors.contactLivraisonSelect);
                if (contactSelect && contactSelect.value) {
                    this.nestedModalService.openContactEditModal(contactSelect.value, 'livraison');
                }
            });
            this.log('✅ Bouton éditer contact livraison attaché');
        }
        
        // Contacts de facturation
        const addContactFacturationBtn = document.getElementById(this.config.selectors.addContactFacturationBtn);
        if (addContactFacturationBtn) {
            addContactFacturationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentClientId) {
                    this.nestedModalService.openContactCreationModal(this.currentClientId, 'facturation');
                }
            });
            this.log('✅ Bouton créer contact facturation attaché');
        }
        
        const editContactFacturationBtn = document.getElementById(this.config.selectors.editContactFacturationBtn);
        if (editContactFacturationBtn) {
            editContactFacturationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const contactSelect = document.getElementById(this.config.selectors.contactFacturationSelect);
                if (contactSelect && contactSelect.value) {
                    this.nestedModalService.openContactEditModal(contactSelect.value, 'facturation');
                }
            });
            this.log('✅ Bouton éditer contact facturation attaché');
        }
    }
    
    /**
     * Attache les événements liés aux adresses
     */
    attachAddressEvents() {
        // Adresses de livraison
        const addAdresseLivraisonBtn = document.getElementById(this.config.selectors.addAdresseLivraisonBtn);
        if (addAdresseLivraisonBtn) {
            addAdresseLivraisonBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentClientId) {
                    this.nestedModalService.openAddressCreationModal(this.currentClientId, 'livraison');
                }
            });
            this.log('✅ Bouton créer adresse livraison attaché');
        }
        
        const editAdresseLivraisonBtn = document.getElementById(this.config.selectors.editAdresseLivraisonBtn);
        if (editAdresseLivraisonBtn) {
            editAdresseLivraisonBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // Récupérer l'ID de l'adresse sélectionnée (à implémenter selon la structure)
                const addressId = this.getCurrentLivraisonAddressId();
                if (addressId) {
                    this.nestedModalService.openAddressEditModal(addressId, 'livraison');
                }
            });
            this.log('✅ Bouton éditer adresse livraison attaché');
        }
        
        // Adresses de facturation
        const addAdresseFacturationBtn = document.getElementById(this.config.selectors.addAdresseFacturationBtn);
        if (addAdresseFacturationBtn) {
            addAdresseFacturationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentClientId) {
                    this.nestedModalService.openAddressCreationModal(this.currentClientId, 'facturation');
                }
            });
            this.log('✅ Bouton créer adresse facturation attaché');
        }
        
        const editAdresseFacturationBtn = document.getElementById(this.config.selectors.editAdresseFacturationBtn);
        if (editAdresseFacturationBtn) {
            editAdresseFacturationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // Récupérer l'ID de l'adresse sélectionnée (à implémenter selon la structure)
                const addressId = this.getCurrentFacturationAddressId();
                if (addressId) {
                    this.nestedModalService.openAddressEditModal(addressId, 'facturation');
                }
            });
            this.log('✅ Bouton éditer adresse facturation attaché');
        }
    }
    
    /**
     * Attache l'événement de changement de client
     */
    attachClientChangeEvent() {
        const clientSelect = document.getElementById(this.config.selectors.clientSelect);
        if (clientSelect) {
            // Utiliser jQuery si disponible pour Select2
            if (window.$ && typeof $.fn.select2 !== 'undefined') {
                $(clientSelect).on('change', (e) => {
                    const clientId = e.target.value;
                    this.onClientChanged(clientId);
                });
            } else {
                clientSelect.addEventListener('change', (e) => {
                    const clientId = e.target.value;
                    this.onClientChanged(clientId);
                });
            }
            this.log('✅ Événement changement client attaché');
        }
    }
    
    /**
     * Gère le changement de client sélectionné
     */
    onClientChanged(clientId) {
        this.log('🔄 Client changé:', clientId);
        
        this.currentClientId = clientId;
        
        // Activer/désactiver les boutons selon le client sélectionné
        this.toggleButtons();
        
        // Charger les données du client (contacts, adresses)
        if (clientId) {
            this.loadClientData(clientId);
        } else {
            this.clearClientData();
        }
    }
    
    /**
     * Active/désactive les boutons selon l'état actuel
     */
    toggleButtons() {
        const hasClient = !!this.currentClientId;
        
        // Boutons qui nécessitent un client sélectionné
        const clientDependentButtons = [
            this.config.selectors.editClientBtn,
            this.config.selectors.addContactLivraisonBtn,
            this.config.selectors.addContactFacturationBtn,
            this.config.selectors.addAdresseLivraisonBtn,
            this.config.selectors.addAdresseFacturationBtn
        ];
        
        clientDependentButtons.forEach(btnId => {
            const btn = document.getElementById(btnId);
            if (btn) {
                btn.disabled = !hasClient;
                if (hasClient) {
                    btn.classList.remove('disabled');
                } else {
                    btn.classList.add('disabled');
                }
            }
        });
        
        // Boutons d'édition qui nécessitent une sélection
        this.toggleEditButtons();
        
        this.log('🔘 Boutons mis à jour, client:', hasClient);
    }
    
    /**
     * Active/désactive les boutons d'édition selon les sélections
     */
    toggleEditButtons() {
        // Contact livraison
        const contactLivraisonSelect = document.getElementById(this.config.selectors.contactLivraisonSelect);
        const editContactLivraisonBtn = document.getElementById(this.config.selectors.editContactLivraisonBtn);
        if (contactLivraisonSelect && editContactLivraisonBtn) {
            const hasContact = !!contactLivraisonSelect.value;
            editContactLivraisonBtn.disabled = !hasContact;
        }
        
        // Contact facturation
        const contactFacturationSelect = document.getElementById(this.config.selectors.contactFacturationSelect);
        const editContactFacturationBtn = document.getElementById(this.config.selectors.editContactFacturationBtn);
        if (contactFacturationSelect && editContactFacturationBtn) {
            const hasContact = !!contactFacturationSelect.value;
            editContactFacturationBtn.disabled = !hasContact;
        }
        
        // Adresses (à implémenter selon la structure de l'UI)
        this.toggleAddressEditButtons();
    }
    
    /**
     * Active/désactive les boutons d'édition d'adresse
     */
    toggleAddressEditButtons() {
        // À implémenter selon la structure de l'interface
        const editAdresseLivraisonBtn = document.getElementById(this.config.selectors.editAdresseLivraisonBtn);
        const editAdresseFacturationBtn = document.getElementById(this.config.selectors.editAdresseFacturationBtn);
        
        if (editAdresseLivraisonBtn) {
            const hasAddress = !!this.getCurrentLivraisonAddressId();
            editAdresseLivraisonBtn.disabled = !hasAddress;
        }
        
        if (editAdresseFacturationBtn) {
            const hasAddress = !!this.getCurrentFacturationAddressId();
            editAdresseFacturationBtn.disabled = !hasAddress;
        }
    }
    
    /**
     * Charge les données d'un client (contacts, adresses)
     */
    async loadClientData(clientId) {
        this.log('📥 Chargement données client:', clientId);
        
        try {
            // Faire un appel AJAX pour récupérer les données du client
            const response = await fetch(`/api/client/${clientId}/data`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                this.populateClientData(data);
            } else {
                this.error('Erreur chargement données client:', response.status);
            }
        } catch (error) {
            this.error('Erreur chargement données client:', error);
        }
    }
    
    /**
     * Remplit l'interface avec les données du client
     */
    populateClientData(data) {
        this.log('📋 Population des données client:', data);
        
        // Remplir les contacts de livraison
        if (data.contacts && data.contacts.length > 0) {
            this.populateContactSelect(this.config.selectors.contactLivraisonSelect, data.contacts, data.defaultContactLivraison);
            this.populateContactSelect(this.config.selectors.contactFacturationSelect, data.contacts, data.defaultContactFacturation);
        }
        
        // Remplir les adresses
        if (data.addresses && data.addresses.length > 0) {
            this.populateAddressDisplays(data.addresses, data.defaultAddressLivraison, data.defaultAddressFacturation);
        }
        
        // Mettre à jour l'état des boutons
        this.toggleButtons();
    }
    
    /**
     * Remplit un sélecteur de contact
     */
    populateContactSelect(selectId, contacts, defaultContactId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Vider les options existantes (sauf la première)
        const firstOption = select.firstElementChild;
        select.innerHTML = '';
        if (firstOption) {
            select.appendChild(firstOption);
        }
        
        // Ajouter les contacts
        contacts.forEach(contact => {
            const option = document.createElement('option');
            option.value = contact.id;
            option.textContent = `${contact.prenom} ${contact.nom}`;
            option.selected = contact.id == defaultContactId;
            select.appendChild(option);
        });
        
        // Déclencher l'événement change si Select2
        if (window.$ && typeof $.fn.select2 !== 'undefined') {
            $(select).trigger('change');
        }
    }
    
    /**
     * Remplit les affichages d'adresse
     */
    populateAddressDisplays(addresses, defaultLivraisonId, defaultFacturationId) {
        // Adresse de livraison
        const defaultLivraison = addresses.find(addr => addr.id == defaultLivraisonId);
        if (defaultLivraison) {
            this.updateAddressDisplay(this.config.selectors.adresseLivraisonDisplay, defaultLivraison);
        }
        
        // Adresse de facturation
        const defaultFacturation = addresses.find(addr => addr.id == defaultFacturationId);
        if (defaultFacturation) {
            this.updateAddressDisplay(this.config.selectors.adresseFacturationDisplay, defaultFacturation);
        }
    }
    
    /**
     * Met à jour l'affichage d'une adresse
     */
    updateAddressDisplay(displayId, address) {
        const display = document.getElementById(displayId);
        if (!display) return;
        
        display.innerHTML = `
            <div class="current-address">
                <strong>${address.ligne1}</strong><br>
                ${address.ligne2 ? address.ligne2 + '<br>' : ''}
                ${address.codePostal} ${address.ville}
            </div>
        `;
        
        // Stocker l'ID de l'adresse pour l'édition
        display.dataset.addressId = address.id;
    }
    
    /**
     * Vide les données client de l'interface
     */
    clearClientData() {
        this.log('🗑️ Nettoyage des données client');
        
        // Vider les sélecteurs de contact
        this.clearContactSelect(this.config.selectors.contactLivraisonSelect);
        this.clearContactSelect(this.config.selectors.contactFacturationSelect);
        
        // Vider les affichages d'adresse
        this.clearAddressDisplay(this.config.selectors.adresseLivraisonDisplay);
        this.clearAddressDisplay(this.config.selectors.adresseFacturationDisplay);
    }
    
    /**
     * Vide un sélecteur de contact
     */
    clearContactSelect(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Garder seulement la première option
        const firstOption = select.firstElementChild;
        select.innerHTML = '';
        if (firstOption) {
            select.appendChild(firstOption);
        }
        
        // Déclencher l'événement change si Select2
        if (window.$ && typeof $.fn.select2 !== 'undefined') {
            $(select).val('').trigger('change');
        }
    }
    
    /**
     * Vide l'affichage d'une adresse
     */
    clearAddressDisplay(displayId) {
        const display = document.getElementById(displayId);
        if (!display) return;
        
        display.innerHTML = '<div class="text-muted">Aucune adresse sélectionnée</div>';
        delete display.dataset.addressId;
    }
    
    /**
     * Récupère l'ID de l'adresse de livraison actuelle
     */
    getCurrentLivraisonAddressId() {
        const display = document.getElementById(this.config.selectors.adresseLivraisonDisplay);
        return display ? display.dataset.addressId : null;
    }
    
    /**
     * Récupère l'ID de l'adresse de facturation actuelle
     */
    getCurrentFacturationAddressId() {
        const display = document.getElementById(this.config.selectors.adresseFacturationDisplay);
        return display ? display.dataset.addressId : null;
    }
    
    /**
     * Retourne l'état actuel du service
     */
    getState() {
        return {
            currentClientId: this.currentClientId,
            nestedModalState: this.nestedModalService ? this.nestedModalService.getState() : null
        };
    }
    
    /**
     * Nettoie le service
     */
    destroy() {
        this.log('🗑️ Destruction du service');
        
        if (this.nestedModalService) {
            this.nestedModalService.closeAllModals();
        }
        
        this.currentClientId = null;
    }
}

// Rendre disponible globalement
window.DevisModalIntegration = DevisModalIntegration;