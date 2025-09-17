/**
 * Service unifié pour la gestion des interactions Client/Prospect
 * Utilisable sur les pages de création et d'édition de devis
 */
class ClientProspectService {
    constructor(config = {}) {
        this.config = {
            debug: config.debug || false,
            mode: config.mode || 'create', // 'create' ou 'edit'
            selectors: {
                clientSelect: config.selectors?.clientSelect || '#prospect',
                clientField: config.selectors?.clientField || '#client_field',
                contactLivraisonSelect: config.selectors?.contactLivraisonSelect || '#contact_defaut',
                contactFacturationSelect: config.selectors?.contactFacturationSelect || '#contact_facturation',
                adresseLivraisonSelect: config.selectors?.adresseLivraisonSelect || '#adresse_livraison_select',
                adresseFacturationSelect: config.selectors?.adresseFacturationSelect || '#adresse_facturation_select',
                contactSelection: config.selectors?.contactSelection || '#contact-selection',
                // Boutons
                editClientBtn: config.selectors?.editClientBtn || '#edit-client-btn',
                editContactLivraisonBtn: config.selectors?.editContactLivraisonBtn || '#edit-contact-livraison-btn',
                editContactFacturationBtn: config.selectors?.editContactFacturationBtn || '#edit-contact-facturation-btn',
                editAddressLivraisonBtn: config.selectors?.editAddressLivraisonBtn || '#edit-address-livraison-btn',
                editAddressFacturationBtn: config.selectors?.editAddressFacturationBtn || '#edit-address-facturation-btn'
            },
            ...config
        };
        
        this.currentClient = null;
        this.currentContacts = [];
        this.currentAddresses = [];
        
        this.log('🚀 ClientProspectService initialisé', this.config);
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[ClientProspectService] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[ClientProspectService] ${message}`, error || '');
    }
    
    /**
     * Initialise le service et attache les événements
     */
    init() {
        this.log('🔧 Initialisation du service');
        
        // Vérifier les éléments requis
        if (!this.checkRequiredElements()) {
            this.error('⚠️ Éléments requis manquants, service non initialisé');
            return false;
        }
        
        // Attacher les événements
        this.attachEvents();
        
        // Initialiser si un client est déjà sélectionné
        const clientSelect = document.querySelector(this.config.selectors.clientSelect);
        if (clientSelect?.value) {
            this.handleClientChange(clientSelect.value);
        }
        
        this.log('✅ Service initialisé avec succès');
        return true;
    }
    
    /**
     * Vérifie que les éléments DOM requis existent
     */
    checkRequiredElements() {
        const required = [
            this.config.selectors.clientSelect,
            this.config.selectors.contactLivraisonSelect,
            this.config.selectors.contactFacturationSelect
        ];
        
        const missing = required.filter(selector => !document.querySelector(selector));
        
        if (missing.length > 0) {
            this.error('Éléments manquants:', missing);
            return false;
        }
        
        return true;
    }
    
    /**
     * Attache tous les événements nécessaires
     */
    attachEvents() {
        const clientSelect = document.querySelector(this.config.selectors.clientSelect);
        
        // Support pour les deux types de sélecteurs (création et édition)
        let contactLivraisonSelect = document.querySelector(this.config.selectors.contactLivraisonSelect);
        let contactFacturationSelect = document.querySelector(this.config.selectors.contactFacturationSelect);
        
        // Si on ne trouve pas les sélecteurs par défaut, essayer les sélecteurs Symfony
        if (!contactLivraisonSelect && this.config.mode === 'edit') {
            contactLivraisonSelect = document.querySelector('#devis_contactLivraison');
            this.log('🔧 Utilisation sélecteur Symfony pour contact livraison');
        }
        if (!contactFacturationSelect && this.config.mode === 'edit') {
            contactFacturationSelect = document.querySelector('#devis_contactFacturation');
            this.log('🔧 Utilisation sélecteur Symfony pour contact facturation');
        }
        
        const adresseLivraisonSelect = document.querySelector(this.config.selectors.adresseLivraisonSelect);
        const adresseFacturationSelect = document.querySelector(this.config.selectors.adresseFacturationSelect);
        
        // Changement de client
        clientSelect?.addEventListener('change', (e) => {
            this.handleClientChange(e.target.value);
        });
        
        // Changement de contacts
        contactLivraisonSelect?.addEventListener('change', (e) => {
            this.handleContactChange('livraison', e.target.value);
        });
        
        contactFacturationSelect?.addEventListener('change', (e) => {
            this.handleContactChange('facturation', e.target.value);
        });
        
        // Changement d'adresses
        adresseLivraisonSelect?.addEventListener('change', (e) => {
            this.handleAddressChange('livraison', e.target.value);
        });
        
        adresseFacturationSelect?.addEventListener('change', (e) => {
            this.handleAddressChange('facturation', e.target.value);
        });
        
        // Boutons d'édition
        this.attachEditButtons();
    }
    
    /**
     * Attache les événements des boutons d'édition
     */
    attachEditButtons() {
        const editClientBtn = document.querySelector(this.config.selectors.editClientBtn);
        const editContactLivraisonBtn = document.querySelector(this.config.selectors.editContactLivraisonBtn);
        const editContactFacturationBtn = document.querySelector(this.config.selectors.editContactFacturationBtn);
        const editAddressLivraisonBtn = document.querySelector(this.config.selectors.editAddressLivraisonBtn);
        const editAddressFacturationBtn = document.querySelector(this.config.selectors.editAddressFacturationBtn);
        
        editClientBtn?.addEventListener('click', () => {
            if (this.currentClient) {
                this.openClientModal(this.currentClient.id);
            }
        });
        
        editContactLivraisonBtn?.addEventListener('click', () => {
            let contactSelect = document.querySelector(this.config.selectors.contactLivraisonSelect);
            // Support pour les sélecteurs Symfony en mode édition
            if (!contactSelect && this.config.mode === 'edit') {
                contactSelect = document.querySelector('#devis_contactLivraison');
            }
            if (contactSelect?.value) {
                this.openContactModal(contactSelect.value);
            }
        });
        
        editContactFacturationBtn?.addEventListener('click', () => {
            let contactSelect = document.querySelector(this.config.selectors.contactFacturationSelect);
            // Support pour les sélecteurs Symfony en mode édition
            if (!contactSelect && this.config.mode === 'edit') {
                contactSelect = document.querySelector('#devis_contactFacturation');
            }
            if (contactSelect?.value) {
                this.openContactModal(contactSelect.value);
            }
        });
        
        editAddressLivraisonBtn?.addEventListener('click', () => {
            const addressSelect = document.querySelector(this.config.selectors.adresseLivraisonSelect);
            if (addressSelect?.value) {
                this.openAddressModal(addressSelect.value);
            }
        });
        
        editAddressFacturationBtn?.addEventListener('click', () => {
            const addressSelect = document.querySelector(this.config.selectors.adresseFacturationSelect);
            if (addressSelect?.value) {
                this.openAddressModal(addressSelect.value);
            }
        });
    }
    
    /**
     * Gère le changement de client
     */
    async handleClientChange(clientId) {
        this.log('👤 Changement de client:', clientId);
        
        if (!clientId) {
            this.currentClient = null;
            this.currentContacts = [];
            this.currentAddresses = [];
            this.hideContactSection();
            this.updateEditButtons();
            return;
        }
        
        try {
            // Mettre à jour le client actuel
            this.currentClient = { id: clientId };
            
            // Synchroniser avec le champ caché si en mode création
            if (this.config.mode === 'create') {
                const clientField = document.querySelector(this.config.selectors.clientField);
                if (clientField) {
                    clientField.value = clientId;
                }
            }
            
            // Charger les données du client
            await Promise.all([
                this.loadClientContacts(clientId),
                this.loadClientAddresses(clientId)
            ]);
            
            // Populer les sélecteurs
            this.populateContacts();
            this.populateAddresses();
            
            // Afficher la section contacts
            this.showContactSection();
            
            // Mettre à jour les boutons
            this.updateEditButtons();
            
            this.log('✅ Client changé avec succès');
        } catch (error) {
            this.error('Erreur lors du changement de client:', error);
        }
    }
    
    /**
     * Charge les contacts du client via API
     */
    async loadClientContacts(clientId) {
        this.log('📞 Chargement contacts pour client:', clientId);
        
        try {
            const response = await fetch(`/client/${clientId}/contacts`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            this.currentContacts = await response.json();
            this.log('📞 Contacts chargés:', this.currentContacts.length);
        } catch (error) {
            this.error('Erreur chargement contacts:', error);
            this.currentContacts = [];
        }
    }
    
    /**
     * Charge les adresses du client via API
     */
    async loadClientAddresses(clientId) {
        this.log('📍 Chargement adresses pour client:', clientId);
        
        try {
            const response = await fetch(`/client/${clientId}/addresses`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            this.currentAddresses = await response.json();
            this.log('📍 Adresses chargées:', this.currentAddresses.length);
        } catch (error) {
            this.error('Erreur chargement adresses:', error);
            this.currentAddresses = [];
        }
    }
    
    /**
     * Popule les sélecteurs de contacts
     */
    populateContacts() {
        // Support pour les deux types de sélecteurs (création et édition)
        let contactLivraisonSelect = document.querySelector(this.config.selectors.contactLivraisonSelect);
        let contactFacturationSelect = document.querySelector(this.config.selectors.contactFacturationSelect);
        
        // Si on ne trouve pas les sélecteurs par défaut, essayer les sélecteurs Symfony
        if (!contactLivraisonSelect && this.config.mode === 'edit') {
            contactLivraisonSelect = document.querySelector('#devis_contactLivraison');
        }
        if (!contactFacturationSelect && this.config.mode === 'edit') {
            contactFacturationSelect = document.querySelector('#devis_contactFacturation');
        }
        
        if (!contactLivraisonSelect || !contactFacturationSelect) {
            this.log('⚠️ Sélecteurs de contacts non trouvés');
            return;
        }
        
        // Vider les sélecteurs
        contactLivraisonSelect.innerHTML = '<option value="">Choisir un contact...</option>';
        contactFacturationSelect.innerHTML = '<option value="">Choisir un contact...</option>';
        
        // Ajouter les contacts
        this.currentContacts.forEach(contact => {
            const label = this.formatContactLabel(contact);
            
            const optionLivraison = new Option(label, contact.id);
            const optionFacturation = new Option(label, contact.id);
            
            // Sélectionner les contacts par défaut
            if (contact.is_livraison_default) {
                optionLivraison.selected = true;
            }
            if (contact.is_facturation_default) {
                optionFacturation.selected = true;
            }
            
            contactLivraisonSelect.appendChild(optionLivraison);
            contactFacturationSelect.appendChild(optionFacturation);
        });
        
        // Déclencher les événements de changement pour les contacts par défaut
        setTimeout(() => {
            if (contactLivraisonSelect.value) {
                contactLivraisonSelect.dispatchEvent(new Event('change'));
            }
            if (contactFacturationSelect.value) {
                contactFacturationSelect.dispatchEvent(new Event('change'));
            }
        }, 100);
    }
    
    /**
     * Popule les sélecteurs d'adresses
     */
    populateAddresses() {
        const adresseLivraisonSelect = document.querySelector(this.config.selectors.adresseLivraisonSelect);
        const adresseFacturationSelect = document.querySelector(this.config.selectors.adresseFacturationSelect);
        
        if (!adresseLivraisonSelect || !adresseFacturationSelect) return;
        
        // Vider les sélecteurs
        adresseLivraisonSelect.innerHTML = '<option value="">Choisir une adresse...</option>';
        adresseFacturationSelect.innerHTML = '<option value="">Choisir une adresse...</option>';
        
        // Ajouter les adresses
        this.currentAddresses.forEach(address => {
            const label = this.formatAddressLabel(address);
            
            const optionLivraison = new Option(label, address.id);
            const optionFacturation = new Option(label, address.id);
            
            adresseLivraisonSelect.appendChild(optionLivraison);
            adresseFacturationSelect.appendChild(optionFacturation);
        });
    }
    
    /**
     * Gère le changement de contact
     */
    handleContactChange(type, contactId) {
        this.log(`📞 Changement contact ${type}:`, contactId);
        
        // Trouver le contact et sélectionner son adresse automatiquement
        if (contactId) {
            const contact = this.currentContacts.find(c => c.id == contactId);
            if (contact && contact.adresse_id) {
                const addressSelect = document.querySelector(
                    type === 'livraison' 
                        ? this.config.selectors.adresseLivraisonSelect 
                        : this.config.selectors.adresseFacturationSelect
                );
                
                if (addressSelect) {
                    addressSelect.value = contact.adresse_id;
                    addressSelect.dispatchEvent(new Event('change'));
                    this.log(`📍 Adresse ${type} sélectionnée automatiquement:`, contact.adresse_id);
                }
            }
        }
        
        this.updateEditButtons();
    }
    
    /**
     * Gère le changement d'adresse
     */
    handleAddressChange(type, addressId) {
        this.log(`📍 Changement adresse ${type}:`, addressId);
        this.updateEditButtons();
    }
    
    /**
     * Met à jour la visibilité des boutons d'édition
     */
    updateEditButtons() {
        const editClientBtn = document.querySelector(this.config.selectors.editClientBtn);
        const editContactLivraisonBtn = document.querySelector(this.config.selectors.editContactLivraisonBtn);
        const editContactFacturationBtn = document.querySelector(this.config.selectors.editContactFacturationBtn);
        const editAddressLivraisonBtn = document.querySelector(this.config.selectors.editAddressLivraisonBtn);
        const editAddressFacturationBtn = document.querySelector(this.config.selectors.editAddressFacturationBtn);
        
        // Support pour les deux types de sélecteurs (création et édition)
        let contactLivraisonSelect = document.querySelector(this.config.selectors.contactLivraisonSelect);
        let contactFacturationSelect = document.querySelector(this.config.selectors.contactFacturationSelect);
        
        // Si on ne trouve pas les sélecteurs par défaut, essayer les sélecteurs Symfony
        if (!contactLivraisonSelect && this.config.mode === 'edit') {
            contactLivraisonSelect = document.querySelector('#devis_contactLivraison');
        }
        if (!contactFacturationSelect && this.config.mode === 'edit') {
            contactFacturationSelect = document.querySelector('#devis_contactFacturation');
        }
        
        const adresseLivraisonSelect = document.querySelector(this.config.selectors.adresseLivraisonSelect);
        const adresseFacturationSelect = document.querySelector(this.config.selectors.adresseFacturationSelect);
        
        // Bouton client
        if (editClientBtn) {
            editClientBtn.style.display = this.currentClient ? 'inline-block' : 'none';
        }
        
        // Boutons contacts
        if (editContactLivraisonBtn) {
            editContactLivraisonBtn.style.display = contactLivraisonSelect?.value ? 'inline-block' : 'none';
        }
        
        if (editContactFacturationBtn) {
            editContactFacturationBtn.style.display = contactFacturationSelect?.value ? 'inline-block' : 'none';
        }
        
        // Boutons adresses
        if (editAddressLivraisonBtn) {
            editAddressLivraisonBtn.style.display = adresseLivraisonSelect?.value ? 'inline-block' : 'none';
        }
        
        if (editAddressFacturationBtn) {
            editAddressFacturationBtn.style.display = adresseFacturationSelect?.value ? 'inline-block' : 'none';
        }
    }
    
    /**
     * Affiche la section contacts
     */
    showContactSection() {
        const contactSection = document.querySelector(this.config.selectors.contactSelection);
        if (contactSection) {
            contactSection.style.display = 'block';
        }
    }
    
    /**
     * Masque la section contacts
     */
    hideContactSection() {
        const contactSection = document.querySelector(this.config.selectors.contactSelection);
        if (contactSection) {
            contactSection.style.display = 'none';
        }
    }
    
    /**
     * Formate le libellé d'un contact
     */
    formatContactLabel(contact) {
        const parts = [];
        if (contact.prenom) parts.push(contact.prenom);
        if (contact.nom) parts.push(contact.nom);
        if (contact.fonction) parts.push(`(${contact.fonction})`);
        
        return parts.join(' ') || 'Contact sans nom';
    }
    
    /**
     * Formate le libellé d'une adresse
     */
    formatAddressLabel(address) {
        return address.label || `${address.nom || 'Adresse'} - ${address.ligne1} - ${address.codePostal} ${address.ville}`;
    }
    
    /**
     * Ouvre la modale de client
     */
    openClientModal(clientId) {
        this.log('🔧 Ouverture modale client:', clientId);
        
        // Pour l'instant, fallback vers nouvelle fenêtre
        // TODO: Implémenter les modales
        window.open(`/client/${clientId}/edit`, '_blank');
    }
    
    /**
     * Ouvre la modale de contact
     */
    openContactModal(contactId) {
        this.log('🔧 Ouverture modale contact:', contactId);
        
        // Pour l'instant, fallback vers nouvelle fenêtre
        // TODO: Implémenter les modales
        if (this.currentClient) {
            window.open(`/client/${this.currentClient.id}/edit#contact-${contactId}`, '_blank');
        }
    }
    
    /**
     * Ouvre la modale d'adresse
     */
    openAddressModal(addressId) {
        this.log('🔧 Ouverture modale adresse:', addressId);
        
        // Pour l'instant, fallback vers nouvelle fenêtre
        // TODO: Implémenter les modales
        if (this.currentClient) {
            window.open(`/client/${this.currentClient.id}/edit#address-${addressId}`, '_blank');
        }
    }
    
    /**
     * Rafraîchit les données du client actuel
     */
    async refresh() {
        if (this.currentClient) {
            await this.handleClientChange(this.currentClient.id);
        }
    }
    
    /**
     * Nettoie le service
     */
    destroy() {
        this.currentClient = null;
        this.currentContacts = [];
        this.currentAddresses = [];
        this.log('🗑️ Service nettoyé');
    }
}

// Rendre disponible globalement
window.ClientProspectService = ClientProspectService;