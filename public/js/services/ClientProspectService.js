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
        this.skipAddressAutoSelection = false; // Flag pour empêcher la sélection automatique d'adresse
        
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
        this.log('🔍 Vérification des éléments requis...');
        
        // Seul le sélecteur client est vraiment requis au démarrage
        // Les autres éléments peuvent être cachés ou non encore visibles
        const required = [
            this.config.selectors.clientSelect
        ];
        
        this.log('🔍 Sélecteur client recherché:', this.config.selectors.clientSelect);
        const clientElement = document.querySelector(this.config.selectors.clientSelect);
        this.log('🔍 Élément client trouvé:', !!clientElement, clientElement?.id, clientElement?.tagName);
        
        const missing = required.filter(selector => !document.querySelector(selector));
        
        if (missing.length > 0) {
            this.error('Éléments manquants:', missing);
            return false;
        }
        
        // Vérifier les éléments optionnels et les signaler si manquants (sans échouer)
        const optional = [
            this.config.selectors.contactLivraisonSelect,
            this.config.selectors.contactFacturationSelect
        ];
        
        const missingOptional = optional.filter(selector => !document.querySelector(selector));
        if (missingOptional.length > 0) {
            this.log('⚠️ Éléments optionnels non trouvés (normal si pas encore visibles):', missingOptional);
        }
        
        this.log('✅ Vérification éléments requis réussie');
        return true;
    }
    
    /**
     * Attache tous les événements nécessaires
     */
    attachEvents() {
        this.log('🔗 Début attachement des événements...');
        
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
        
        // Changement de client - Support Select2
        if (clientSelect) {
            this.log('🔧 Client select trouvé, ID:', clientSelect.id, 'Classes:', clientSelect.className);
            
            // Événement DOM standard
            clientSelect.addEventListener('change', (e) => {
                this.log('👤 Événement change DOM client:', e.target.value);
                this.handleClientChange(e.target.value);
            });
            
            // Support Select2 avec événements globaux
            this.setupSelect2GlobalEvents(clientSelect);
        } else {
            this.log('❌ Client select non trouvé avec sélecteur:', this.config.selectors.clientSelect);
        }
        
        // Changement de contacts - Support Select2 (peut être différé si éléments non visibles)
        this.attachContactEvents(contactLivraisonSelect, contactFacturationSelect);
        
        // Changement d'adresses
        adresseLivraisonSelect?.addEventListener('change', (e) => {
            this.handleAddressChange('livraison', e.target.value);
        });
        
        adresseFacturationSelect?.addEventListener('change', (e) => {
            this.handleAddressChange('facturation', e.target.value);
        });
        
        // Boutons d'édition
        this.attachEditButtons();
        
        // Boutons d'ajout
        this.attachAddButtons();
        
        // Événements de modales globaux
        this.attachModalEvents();
    }
    
    /**
     * Attache les événements globaux des modales
     */
    attachModalEvents() {
        // Écouter la création de clients
        window.addEventListener('clientCreated', (event) => {
            this.log('✅ Client créé:', event.detail.client);
            
            // Recharger la liste des clients
            if (window.$ && typeof $.fn.select2 !== 'undefined') {
                const clientSelect = $(this.config.selectors.clientSelect);
                
                // Ajouter le nouveau client à la liste
                const newOption = new Option(event.detail.client.label, event.detail.client.id, true, true);
                clientSelect.append(newOption).trigger('change');
                
                // Déclencher le changement pour charger les contacts/adresses
                this.handleClientChange(event.detail.client.id);
            }
        });
        
        // Écouter la mise à jour de clients
        window.addEventListener('clientUpdated', (event) => {
            this.log('✅ Client modifié:', event.detail.client);
            
            // Mettre à jour le label du client dans la liste
            if (window.$ && typeof $.fn.select2 !== 'undefined') {
                const clientSelect = $(this.config.selectors.clientSelect);
                const currentValue = clientSelect.val();
                
                this.log('🔄 Mise à jour client select:', {
                    clientId: event.detail.client.id,
                    newLabel: event.detail.client.label,
                    currentValue: currentValue,
                    sameClient: currentValue == event.detail.client.id,
                    fullClientData: event.detail.client,
                    hasSelect2: clientSelect.hasClass('select2-hidden-accessible')
                });
                
                if (currentValue == event.detail.client.id) {
                    // Mettre à jour l'option existante
                    const option = clientSelect.find(`option[value="${event.detail.client.id}"]`);
                    const oldText = option.text();
                    
                    this.log('🔍 Avant mise à jour:', {
                        optionFound: option.length > 0,
                        oldText: oldText,
                        newText: event.detail.client.label
                    });
                    
                    if (option.length > 0) {
                        option.text(event.detail.client.label);
                        
                        // Déclencher les événements Select2 pour forcer la mise à jour de l'affichage
                        clientSelect.trigger('change.select2');
                        clientSelect.trigger('select2:select');
                        
                        // Forcer le rafraîchissement de l'affichage Select2
                        if (clientSelect.hasClass('select2-hidden-accessible')) {
                            clientSelect.select2('destroy').select2();
                        }
                        
                        this.log('✅ Label client mis à jour:', {
                            from: oldText,
                            to: event.detail.client.label,
                            optionTextAfter: option.text(),
                            currentDisplayedValue: clientSelect.siblings('.select2-container').find('.select2-selection__rendered').text()
                        });
                    } else {
                        this.log('❌ Option non trouvée pour ID:', event.detail.client.id);
                    }
                }
            }
        });
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
            this.log('🖱️ Clic sur bouton édition client', {
                currentClient: this.currentClient,
                hasCurrentClient: !!this.currentClient
            });
            
            if (this.currentClient) {
                this.openClientModal(this.currentClient.id);
            } else {
                console.warn('⚠️ Aucun client sélectionné pour l\'édition');
            }
        });
        
        // IMPORTANT: Les boutons d'édition Contact et Adresse sont maintenant gérés par
        // ContactModalService et AddressModalService pour éviter les doubles modales
        // Ces services utilisent les nouvelles routes modales (/contact/modal/edit et /adresse/modal/edit)
        // au lieu des anciennes routes (/client/{id}/edit#contact-{id})
        
        // Les event listeners ci-dessous sont DÉSACTIVÉS pour éviter les conflits
        /*
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
        */
    }
    
    /**
     * Attache les événements des boutons d'ajout
     */
    attachAddButtons() {
        // Sélecteurs des boutons d'ajout
        const addClientBtn = document.getElementById('add-client-btn');
        const addContactBtn = document.getElementById('add-contact-btn');
        const addContactFacturationBtn = document.getElementById('add-contact-facturation-btn');
        const addAddressLivraisonBtn = document.getElementById('add-address-livraison-btn');
        const addAddressFacturationBtn = document.getElementById('add-address-facturation-btn');
        
        // Bouton ajouter client
        addClientBtn?.addEventListener('click', () => {
            if (this.getModalService()) {
                this.getModalService().openModal('/client/modal/new', 'Nouveau client');
            }
        });
        
        // Boutons contact et adresse - gérés par les services modales dédiés
        // addContactBtn?.addEventListener('click', () => {
        //     if (this.currentClient && this.getModalService()) {
        //         this.getModalService().openModal(`/client/${this.currentClient.id}/edit?modal=1#add-contact`, 'Ajouter un contact');
        //     }
        // });
        
        // addContactFacturationBtn?.addEventListener('click', () => {
        //     if (this.currentClient && this.getModalService()) {
        //         this.getModalService().openModal(`/client/${this.currentClient.id}/edit?modal=1#add-contact`, 'Ajouter un contact de facturation');
        //     }
        // });
        
        // addAddressLivraisonBtn?.addEventListener('click', () => {
        //     if (this.currentClient && this.getModalService()) {
        //         this.getModalService().openModal(`/client/${this.currentClient.id}/edit?modal=1#add-address`, 'Ajouter une adresse de livraison');
        //     }
        // });
        
        // addAddressFacturationBtn?.addEventListener('click', () => {
        //     if (this.currentClient && this.getModalService()) {
        //         this.getModalService().openModal(`/client/${this.currentClient.id}/edit?modal=1#add-address`, 'Ajouter une adresse de facturation');
        //     }
        // });
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
            const url = `/client/${clientId}/contacts`;
            this.log('📞 URL API contacts:', url);
            const response = await fetch(url);
            this.log('📞 Réponse API contacts - Status:', response.status);
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            this.currentContacts = await response.json();
            this.log('📞 Contacts chargés:', this.currentContacts.length, this.currentContacts);
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
            const url = `/client/${clientId}/addresses`;
            this.log('📍 URL API adresses:', url);
            const response = await fetch(url);
            this.log('📍 Réponse API adresses - Status:', response.status);
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            this.currentAddresses = await response.json();
            this.log('📍 Adresses chargées:', this.currentAddresses.length, this.currentAddresses);
        } catch (error) {
            this.error('Erreur chargement adresses:', error);
            this.currentAddresses = [];
        }
    }
    
    /**
     * Popule les sélecteurs de contacts
     */
    populateContacts() {
        this.log('📝 PopulateContacts appelée avec', this.currentContacts.length, 'contacts');
        
        // Support pour les deux types de sélecteurs (création et édition)
        let contactLivraisonSelect = document.querySelector(this.config.selectors.contactLivraisonSelect);
        let contactFacturationSelect = document.querySelector(this.config.selectors.contactFacturationSelect);
        
        this.log('📝 Sélecteurs trouvés:', {
            livraison: !!contactLivraisonSelect,
            facturation: !!contactFacturationSelect,
            mode: this.config.mode
        });
        
        // Si on ne trouve pas les sélecteurs par défaut, essayer les sélecteurs Symfony
        if (!contactLivraisonSelect && this.config.mode === 'edit') {
            contactLivraisonSelect = document.querySelector('#devis_contactLivraison');
            this.log('📝 Utilisation sélecteur Symfony livraison');
        }
        if (!contactFacturationSelect && this.config.mode === 'edit') {
            contactFacturationSelect = document.querySelector('#devis_contactFacturation');
            this.log('📝 Utilisation sélecteur Symfony facturation');
        }
        
        if (!contactLivraisonSelect || !contactFacturationSelect) {
            this.log('⚠️ Sélecteurs de contacts non trouvés');
            this.log('⚠️ Éléments disponibles:', {
                contactLivraison: !!contactLivraisonSelect,
                contactFacturation: !!contactFacturationSelect,
                selectorLivraison: this.config.selectors.contactLivraisonSelect,
                selectorFacturation: this.config.selectors.contactFacturationSelect
            });
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
     * Popule les sélecteurs de contacts SANS sélectionner les défauts automatiquement
     */
    populateContactsWithoutDefaults() {
        this.log('📝 PopulateContactsWithoutDefaults appelée avec', this.currentContacts.length, 'contacts');
        
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
            this.log('⚠️ Sélecteurs de contacts non trouvés pour rechargement');
            return;
        }
        
        // Sauvegarder les sélections actuelles AVANT de vider
        const currentLivraisonValue = contactLivraisonSelect.value;
        const currentFacturationValue = contactFacturationSelect.value;
        
        this.log('📝 Sauvegarde sélections actuelles:', {
            livraison: currentLivraisonValue,
            facturation: currentFacturationValue
        });
        
        // Vider les sélecteurs
        contactLivraisonSelect.innerHTML = '<option value="">Choisir un contact...</option>';
        contactFacturationSelect.innerHTML = '<option value="">Choisir un contact...</option>';
        
        // Ajouter les contacts SANS sélection automatique par défaut
        this.currentContacts.forEach(contact => {
            const label = this.formatContactLabel(contact);
            
            const optionLivraison = new Option(label, contact.id);
            const optionFacturation = new Option(label, contact.id);
            
            // IMPORTANT: Ne PAS sélectionner automatiquement les contacts par défaut
            // Garder seulement les sélections qui étaient déjà faites
            if (contact.id == currentLivraisonValue) {
                optionLivraison.selected = true;
            }
            if (contact.id == currentFacturationValue) {
                optionFacturation.selected = true;
            }
            
            contactLivraisonSelect.appendChild(optionLivraison);
            contactFacturationSelect.appendChild(optionFacturation);
        });
        
        this.log('📝 Contacts rechargés avec préservation des sélections');
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
            
            // Créer les options avec HTML
            const optionLivraison = document.createElement('option');
            optionLivraison.value = address.id;
            optionLivraison.innerHTML = label;
            
            const optionFacturation = document.createElement('option');
            optionFacturation.value = address.id;
            optionFacturation.innerHTML = label;
            
            adresseLivraisonSelect.appendChild(optionLivraison);
            adresseFacturationSelect.appendChild(optionFacturation);
        });
    }
    
    /**
     * Gère le changement de contact
     */
    handleContactChange(type, contactId) {
        this.log(`📞 Changement contact ${type}:`, contactId);
        
        // Trouver le contact et sélectionner son adresse automatiquement (sauf si désactivé)
        if (contactId && !this.skipAddressAutoSelection) {
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
        } else if (this.skipAddressAutoSelection) {
            this.log(`📍 Sélection automatique d'adresse désactivée temporairement`);
        }
        
        // Synchroniser l'email d'envoi automatique si c'est le contact de livraison (par défaut)
        if (contactId && type === 'livraison') {
            this.syncContactEmail(contactId);
        }
        
        this.updateEditButtons();
    }
    
    /**
     * Synchronise l'email d'envoi automatique avec le contact sélectionné
     */
    syncContactEmail(contactId) {
        this.log(`📧 Synchronisation email pour contact:`, contactId);
        
        // Appeler la fonction globale si elle existe
        if (typeof window.syncContactEmail === 'function') {
            window.syncContactEmail(contactId);
        } else {
            this.log(`⚠️ Fonction syncContactEmail non disponible`);
        }
    }
    
    /**
     * Gère le changement d'adresse
     */
    handleAddressChange(type, addressId) {
        this.log(`📍 Changement adresse ${type}:`, addressId);
        this.updateEditButtons();
    }
    
    /**
     * Recharge uniquement les adresses sans affecter les contacts sélectionnés
     */
    async reloadAddressesOnly(clientId) {
        this.log('📍 Rechargement adresses uniquement pour client:', clientId);
        
        if (!clientId) {
            this.currentAddresses = [];
            this.populateAddresses();
            return;
        }
        
        try {
            // Recharger seulement les adresses
            await this.loadClientAddresses(clientId);
            
            // Repeupler seulement les adresses (sans toucher aux contacts)
            this.populateAddresses();
            
            this.log('✅ Adresses rechargées sans affecter les contacts');
        } catch (error) {
            this.error('Erreur lors du rechargement des adresses:', error);
        }
    }
    
    /**
     * Recharge uniquement les contacts sans affecter les adresses sélectionnées
     */
    async reloadContactsOnly(clientId) {
        this.log('📞 Rechargement contacts uniquement pour client:', clientId);
        
        if (!clientId) {
            this.currentContacts = [];
            this.populateContactsWithoutDefaults();
            return;
        }
        
        try {
            // IMPORTANT: Désactiver temporairement la sélection automatique d'adresse
            this.skipAddressAutoSelection = true;
            
            // Recharger seulement les contacts
            await this.loadClientContacts(clientId);
            
            // Mettre à jour window.clientContacts pour la synchronisation email
            window.clientContacts = this.currentContacts;
            this.log('📧 window.clientContacts mis à jour:', window.clientContacts.length, 'contacts');
            
            // Repeupler seulement les contacts (sans sélection automatique par défaut)
            this.populateContactsWithoutDefaults();
            
            // Réactiver la sélection automatique d'adresse après un délai
            setTimeout(() => {
                this.skipAddressAutoSelection = false;
                this.log('📍 Sélection automatique d\'adresse réactivée');
            }, 1000);
            
            this.log('✅ Contacts rechargés sans affecter les adresses');
        } catch (error) {
            this.error('Erreur lors du rechargement des contacts:', error);
            // Réactiver en cas d'erreur aussi
            this.skipAddressAutoSelection = false;
        }
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
            
            // Attacher les événements aux contacts maintenant qu'ils sont visibles
            setTimeout(() => {
                this.attachContactEventsWhenVisible();
            }, 100);
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
        if (address.label) {
            return address.label;
        }
        
        const nom = address.nom || 'Adresse';
        const ligne1 = address.ligne1 || '';
        const codePostal = address.codePostal || '';
        const ville = address.ville || '';
        
        // Format: "Nom : Adresse - code postal Ville"
        return `${nom} : ${ligne1} - ${codePostal} ${ville}`;
    }
    
    /**
     * Ouvre la modale de client
     */
    openClientModal(clientId) {
        this.log('🔧 Ouverture modale client:', clientId);
        
        if (this.getModalService()) {
            this.getModalService().openModal(`/client/modal/edit/${clientId}`, 'Modifier le client');
        } else {
            // Fallback si ModalService non disponible
            window.open(`/client/${clientId}/edit`, '_blank');
        }
    }
    
    /**
     * Ouvre la modale de contact
     * DEPRECATED: Utiliser ContactModalService à la place
     * Cette méthode est conservée pour compatibilité mais redirige vers les nouvelles routes
     */
    openContactModal(contactId) {
        this.log('🔧 [DEPRECATED] openContactModal - Utiliser ContactModalService');
        
        // Utiliser les nouvelles routes modales
        if (contactId && this.getModalService()) {
            this.getModalService().openModal(`/contact/modal/edit/${contactId}`, 'Modifier le contact');
        }
    }
    
    /**
     * Ouvre la modale d'adresse
     * DEPRECATED: Utiliser AddressModalService à la place
     * Cette méthode est conservée pour compatibilité mais redirige vers les nouvelles routes
     */
    openAddressModal(addressId) {
        this.log('🔧 [DEPRECATED] openAddressModal - Utiliser AddressModalService');
        
        // Utiliser les nouvelles routes modales
        if (addressId && this.getModalService()) {
            this.getModalService().openModal(`/adresse/modal/edit/${addressId}`, 'Modifier l\'adresse');
        }
    }
    
    /**
     * Obtient l'instance du ModalService
     */
    getModalService() {
        if (!this.modalService && window.ModalService) {
            this.modalService = new ModalService({
                debug: this.config.debug,
                refreshOnSuccess: true // Rafraîchir après succès pour mettre à jour les données
            });
        }
        return this.modalService;
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
     * Attache les événements aux contacts (gère les éléments non encore visibles)
     */
    attachContactEvents(contactLivraisonSelect, contactFacturationSelect) {
        if (contactLivraisonSelect) {
            contactLivraisonSelect.addEventListener('change', (e) => {
                this.handleContactChange('livraison', e.target.value);
            });
            this.setupSelect2Support(contactLivraisonSelect, 'contact-livraison');
        }
        
        if (contactFacturationSelect) {
            contactFacturationSelect.addEventListener('change', (e) => {
                this.handleContactChange('facturation', e.target.value);
            });
            this.setupSelect2Support(contactFacturationSelect, 'contact-facturation');
        }
        
        // Si les éléments n'existent pas encore (page création), on les attachera plus tard
        if (!contactLivraisonSelect || !contactFacturationSelect) {
            this.log('⚠️ Éléments contacts non trouvés, seront attachés après affichage de la section');
        }
    }

    /**
     * Attache les événements aux contacts quand la section devient visible
     */
    attachContactEventsWhenVisible() {
        const contactLivraisonSelect = document.querySelector(this.config.selectors.contactLivraisonSelect);
        const contactFacturationSelect = document.querySelector(this.config.selectors.contactFacturationSelect);
        
        this.log('🔧 Attachement différé des événements contacts');
        this.attachContactEvents(contactLivraisonSelect, contactFacturationSelect);
    }

    /**
     * Configure le support Select2 avec événements globaux
     */
    setupSelect2GlobalEvents(clientSelect) {
        this.log('🔧 setupSelect2GlobalEvents appelée pour:', clientSelect.id);
        this.log('🔧 jQuery disponible:', !!window.$);
        
        if (window.$) {
            this.log('🌍 Configuration événements Select2 globaux pour client');
            
            // Utiliser les événements globaux Select2
            $(document).on('select2:select', this.config.selectors.clientSelect, (e) => {
                if (e.params && e.params.data) {
                    this.log('👤 Événement Select2 global client:', e.params.data.id);
                    this.handleClientChange(e.params.data.id);
                } else {
                    this.log('👤 Événement Select2 global client (manuel):', $(e.target).val());
                }
            });
            
            // Aussi écouter les événements sur l'élément directement avec délai
            setTimeout(() => {
                this.log('🔍 Vérification Select2 après 2s, element:', clientSelect.id);
                this.log('🔍 Element classes:', clientSelect.className);
                this.log('🔍 Has Select2 class:', $(clientSelect).hasClass('select2-hidden-accessible'));
                
                if ($(clientSelect).hasClass('select2-hidden-accessible')) {
                    this.log('🔧 Select2 détecté directement pour client');
                    $(clientSelect).on('select2:select', (e) => {
                        if (e.params && e.params.data) {
                            this.log('👤 Événement Select2 direct client:', e.params.data.id);
                            this.handleClientChange(e.params.data.id);
                        } else {
                            this.log('👤 Événement Select2 direct client (manuel):', $(clientSelect).val());
                            // Ne pas re-déclencher handleClientChange si c'est un événement manuel
                        }
                    });
                }
            }, 2000);
        } else {
            this.log('❌ jQuery non disponible pour Select2');
        }
    }

    /**
     * Configure le support Select2 avec retry automatique
     */
    setupSelect2Support(element, type) {
        let retryCount = 0;
        const maxRetries = 20; // Plus de tentatives
        
        const checkSelect2 = () => {
            this.log(`🔍 Vérification Select2 pour ${type} (tentative ${retryCount + 1}/${maxRetries})`);
            this.log(`🔍 Element classes:`, element.className);
            this.log(`🔍 jQuery disponible:`, !!window.$);
            this.log(`🔍 Element Select2:`, window.$ ? $(element).hasClass('select2-hidden-accessible') : 'jQuery non disponible');
            
            if (window.$ && $(element).hasClass('select2-hidden-accessible')) {
                this.log(`🔧 Select2 détecté pour ${type}, ajout événement Select2`);
                
                if (type === 'client') {
                    $(element).on('select2:select', (e) => {
                        if (e.params && e.params.data) {
                            this.log('👤 Événement Select2 client:', e.params.data.id);
                            this.handleClientChange(e.params.data.id);
                        } else {
                            this.log('👤 Événement Select2 client (manuel):', $(element).val());
                        }
                    });
                } else if (type === 'contact-livraison') {
                    $(element).on('select2:select', (e) => {
                        if (e.params && e.params.data) {
                            this.handleContactChange('livraison', e.params.data.id);
                        }
                    });
                } else if (type === 'contact-facturation') {
                    $(element).on('select2:select', (e) => {
                        if (e.params && e.params.data) {
                            this.handleContactChange('facturation', e.params.data.id);
                        }
                    });
                }
                return true;
            }
            
            retryCount++;
            if (retryCount < maxRetries) {
                setTimeout(checkSelect2, 1000); // Délai plus long
            } else {
                this.log(`⚠️ Select2 non trouvé pour ${type} après ${maxRetries} tentatives`);
            }
        };
        
        // Première vérification immédiate, puis avec délai
        setTimeout(checkSelect2, 500); // Délai initial plus long
    }

    /**
     * Nettoie le service
     */
    destroy() {
        if (this.modalService) {
            this.modalService.cleanup();
            this.modalService = null;
        }
        
        this.currentClient = null;
        this.currentContacts = [];
        this.currentAddresses = [];
        this.log('🗑️ Service nettoyé');
    }
}

// Rendre disponible globalement
window.ClientProspectService = ClientProspectService;