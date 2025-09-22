/**
 * DevisContactService - Gestion unifiée des contacts et adresses pour les devis
 * 
 * Architecture propre et modulaire pour /devis/new et /devis/{id}/edit
 * Utilise une approche data-* pour une configuration simple et maintenable
 * 
 * @version 2.0.0
 * @author Claude Code Assistant
 */
class DevisContactService {
    /**
     * @param {Object} config - Configuration du service
     * @param {boolean} config.debug - Activer les logs de debug
     */
    constructor(config = {}) {
        this.config = {
            debug: config.debug || true, // FORCE DEBUG ON
            ...config
        };
        
        // Initialiser le logger
        this.logger = new DebugLogger('DevisContactService', true);
        this.logger.critical('🔴 DÉMARRAGE SERVICE - DEBUG FORCÉ ACTIVÉ');
        
        // État du service
        this.currentContext = null; // 'livraison' ou 'facturation'
        this.isInitialized = false;
        
        // Registre des event listeners pour le nettoyage
        this.eventListeners = new Map();
        
        this.log('🚀 DevisContactService v2.0 - Initialisation');
        this.logger.info('Configuration', this.config);
        this.init();
    }
    
    // =====================================
    // MÉTHODES UTILITAIRES
    // =====================================
    
    /**
     * Log de debug
     */
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[DevisContactService] ${message}`, data || '');
        }
        // Logger aussi dans le système centralisé
        if (this.logger) {
            this.logger.debug(message, data);
        }
    }
    
    /**
     * Log d'erreur
     */
    error(message, error = null) {
        console.error(`[DevisContactService] ${message}`, error || '');
        // Logger aussi dans le système centralisé
        if (this.logger) {
            this.logger.error(message, error);
        }
    }
    
    /**
     * Vérifie si nous sommes en mode création ou édition
     */
    getMode() {
        return document.getElementById('devis_client') ? 'edit' : 'create';
    }
    
    // =====================================
    // INITIALISATION
    // =====================================
    
    /**
     * Initialise le service
     */
    init() {
        this.logger.group('INITIALISATION');
        try {
            // Marquer que le service est actif (désactive les anciens systèmes)
            window.devisContactServiceActive = true;
            this.logger.info('Service marqué comme actif');
            
            this.logger.info('Setup event listeners...');
            this.setupEventListeners();
            
            this.logger.info('Attach action buttons...');
            this.attachActionButtons();
            
            this.logger.info('Setup visibility handlers...');
            this.setupVisibilityHandlers();
            
            this.isInitialized = true;
            this.logger.success('✅ Service initialisé avec succès', { mode: this.getMode() });
            
        } catch (error) {
            this.logger.critical('❌ ERREUR FATALE lors de l\'initialisation', error);
            this.error('❌ Erreur lors de l\'initialisation', error);
        } finally {
            this.logger.groupEnd();
        }
    }
    
    /**
     * Configure les écouteurs d'événements globaux
     */
    setupEventListeners() {
        // Intercepter les événements de création/modification AVANT les autres services
        this.addEventListener(window, 'contactCreated', (event) => {
            if (this.currentContext) {
                this.log('🎯 Événement contactCreated intercepté');
                event.stopImmediatePropagation();
                this.handleContactCreated(event.detail);
            }
        }, true); // Capture phase
        
        this.addEventListener(window, 'contactUpdated', (event) => {
            this.handleContactUpdated(event.detail);
        });
        
        this.addEventListener(window, 'addressCreated', (event) => {
            this.handleAddressCreated(event.detail);
        });
        
        this.addEventListener(window, 'addressUpdated', (event) => {
            this.handleAddressUpdated(event.detail);
        });
    }
    
    /**
     * Attache les gestionnaires aux boutons avec data-action
     */
    attachActionButtons() {
        const actionButtons = document.querySelectorAll('[data-action]');
        
        this.log(`📎 Attachement à ${actionButtons.length} boutons avec data-action`);
        
        actionButtons.forEach(button => {
            const action = button.dataset.action;
            
            switch (action) {
                case 'create-contact':
                    this.addEventListener(button, 'click', (e) => this.handleCreateContact(e));
                    break;
                case 'edit-contact':
                    this.addEventListener(button, 'click', (e) => this.handleEditContact(e));
                    break;
                case 'create-address':
                    this.addEventListener(button, 'click', (e) => this.handleCreateAddress(e));
                    break;
                case 'edit-address':
                    this.addEventListener(button, 'click', (e) => this.handleEditAddress(e));
                    break;
            }
        });
    }
    
    /**
     * Configure la gestion de la visibilité des boutons d'édition
     */
    setupVisibilityHandlers() {
        // Sélecteurs de contacts (selects simples - uniformisés sur les 2 pages)
        const contactSelectors = this.getContactSelectors();
        contactSelectors.forEach(({element, target}) => {
            if (element) {
                // Event listener natif (contacts uniformisés en selects simples)
                this.addEventListener(element, 'change', () => {
                    this.logger.warn(`🔥 CHANGE EVENT CONTACT ${target.toUpperCase()}: ${element.value}`);
                    this.updateEditButtonVisibility('contact', target, element.value);
                    // Nouvelle logique : synchroniser contact → adresse/email
                    this.handleContactChange(target, element.value);
                });
                
                // Initialiser
                this.updateEditButtonVisibility('contact', target, element.value);
            }
        });
        
        // Sélecteurs d'adresses
        const addressSelectors = this.getAddressSelectors();
        addressSelectors.forEach(({element, target}) => {
            if (element) {
                this.addEventListener(element, 'change', () => {
                    this.updateEditButtonVisibility('address', target, element.value);
                });
                // Initialiser
                this.updateEditButtonVisibility('address', target, element.value);
            }
        });
        
        // AJOUT IMPORTANT : Gestion du changement de client
        this.setupClientChangeHandler();
    }
    
    /**
     * Configure la gestion du changement de client
     */
    setupClientChangeHandler() {
        this.logger.group('SETUP CLIENT CHANGE HANDLER');
        const clientSelector = this.getClientSelector();
        this.logger.info('Sélecteur client trouvé?', { 
            selector: clientSelector ? clientSelector.id : 'NON',
            value: clientSelector?.value 
        });
        
        if (clientSelector) {
            // Event listener natif
            this.addEventListener(clientSelector, 'change', () => {
                this.logger.warn(`⚡ EVENT: Changement client déclenché (natif): ${clientSelector.value}`);
                this.handleClientChange(clientSelector.value);
            });
            
            // Event listener Select2 (si présent)
            this.setupSelect2Listener(clientSelector);
            
            // Initialiser si un client est déjà sélectionné
            if (clientSelector.value) {
                this.logger.info(`Client déjà sélectionné au chargement: ${clientSelector.value}`);
                this.handleClientChange(clientSelector.value);
            } else {
                this.logger.warn('Aucun client sélectionné au chargement');
            }
            
            this.log('📞 Event listener changement client configuré');
        } else {
            this.logger.critical('❌ AUCUN SÉLECTEUR CLIENT TROUVÉ!');
        }
        this.logger.groupEnd();
    }
    
    /**
     * Configure l'event listener Select2 avec retry
     */
    setupSelect2Listener(clientSelector, retries = 10) {
        if (window.$ && $(clientSelector).data('select2')) {
            this.logger.info('✅ Select2 détecté, ajout listener Select2...');
            
            // Event listener pour changement de sélection
            $(clientSelector).on('select2:select', (e) => {
                const clientId = e.params.data.id;
                this.logger.warn(`⚡ EVENT: Changement client déclenché (Select2): ${clientId}`);
                this.handleClientChange(clientId);
            });
            
            // 🎯 UX IMPROVEMENT: Focus automatique sur le champ de recherche à l'ouverture
            $(clientSelector).on('select2:open', () => {
                this.logger.info('🔍 Select2 ouvert - focus automatique sur recherche');
                // Petit délai pour laisser Select2 se rendre complètement
                setTimeout(() => {
                    const searchField = document.querySelector('.select2-search__field');
                    if (searchField) {
                        searchField.focus();
                        this.logger.success('✅ Focus appliqué sur le champ de recherche');
                    } else {
                        this.logger.warn('⚠️ Champ de recherche Select2 non trouvé');
                    }
                }, 50);
            });
            
            this.logger.success('Select2 listener attaché avec succès');
        } else if (retries > 0) {
            this.logger.warn(`Select2 pas encore prêt, retry dans 500ms (${retries} tentatives restantes)`);
            setTimeout(() => {
                this.setupSelect2Listener(clientSelector, retries - 1);
            }, 500);
        } else {
            this.logger.error('❌ Select2 non disponible après 10 tentatives');
        }
    }
    
    
    // =====================================
    // GESTION DU CLIENT
    // =====================================
    
    /**
     * Gère le changement de client
     */
    async handleClientChange(clientId) {
        this.logger.group(`CHANGEMENT CLIENT: ${clientId}`);
        this.logger.time('handleClientChange');
        this.log(`👤 Changement de client: ${clientId}`);
        
        if (!clientId) {
            this.logger.warn('Client ID vide - reset de l\'interface');
            this.clearContactsAndAddresses();
            this.hideContactsSection();
            this.logger.groupEnd();
            return;
        }
        
        try {
            // Synchroniser le champ caché pour /devis/new
            this.logger.info('1. Sync champ client caché...');
            this.syncClientField(clientId);
            
            // Afficher la section contacts
            this.logger.info('2. Affichage section contacts...');
            this.showContactsSection();
            
            // Charger les données du client en parallèle
            this.logger.info('3. Chargement données client...');
            this.logger.time('loadData');
            const [contacts, addresses] = await Promise.all([
                this.loadClientContacts(clientId),
                this.loadClientAddresses(clientId)
            ]);
            this.logger.timeEnd('loadData');
            this.logger.success(`Données chargées: ${contacts.length} contacts, ${addresses.length} adresses`);
            
            // IMPORTANT: Stocker les contacts pour la synchronisation contact → adresse/email
            this.currentContacts = contacts;
            this.logger.info('✅ Contacts stockés pour synchronisation future', { count: contacts.length });
            
            // Populer les sélecteurs
            this.logger.info('4. Population des sélecteurs...');
            this.populateContactSelectors(contacts);
            this.populateAddressSelectors(addresses);
            
            // Sélectionner automatiquement les éléments par défaut
            this.logger.info('5. Sélection des éléments par défaut...');
            this.selectDefaultItems(contacts, addresses);
            
            // Mettre à jour les data-client-id des boutons
            this.logger.info('6. Mise à jour des boutons...');
            this.updateButtonsClientId(clientId);
            
            this.logger.success(`✅ Client ${clientId} complètement chargé`);
            this.log(`✅ Client ${clientId} chargé: ${contacts.length} contacts, ${addresses.length} adresses`);
            
        } catch (error) {
            this.logger.critical('❌ ERREUR lors du changement de client', {
                clientId,
                error: error.message,
                stack: error.stack
            });
            this.error('Erreur lors du changement de client', error);
        } finally {
            this.logger.timeEnd('handleClientChange');
            this.logger.groupEnd();
        }
    }
    
    /**
     * Charge les contacts d'un client
     */
    async loadClientContacts(clientId) {
        const url = `/client/${clientId}/contacts`;
        this.logger.info(`📡 API Call: GET ${url}`);
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            this.logger.debug('Response status:', response.status);
            
            if (!response.ok) {
                this.logger.error(`HTTP Error ${response.status} pour ${url}`);
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            this.logger.success(`Contacts reçus:`, data);
            return data;
        } catch (error) {
            this.logger.error(`Erreur chargement contacts:`, error);
            throw error;
        }
    }
    
    /**
     * Charge les adresses d'un client
     */
    async loadClientAddresses(clientId) {
        const url = `/client/${clientId}/addresses`;
        this.logger.info(`📡 API Call: GET ${url}`);
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            this.logger.debug('Response status:', response.status);
            
            if (!response.ok) {
                this.logger.error(`HTTP Error ${response.status} pour ${url}`);
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            this.logger.success(`Adresses reçues:`, data);
            return data;
        } catch (error) {
            this.logger.error(`Erreur chargement adresses:`, error);
            throw error;
        }
    }
    
    // =====================================
    // GESTION DES CONTACTS
    // =====================================
    
    /**
     * Gère le changement de contact pour synchroniser adresse et email
     */
    handleContactChange(target, contactId) {
        this.logger.group(`🔄 CHANGEMENT CONTACT ${target.toUpperCase()}`);
        this.logger.info(`Contact ${target} sélectionné: ${contactId}`);
        this.logger.debug('Données contacts disponibles:', this.currentContacts);
        
        if (!contactId) {
            this.logger.warn('❌ Pas de contact sélectionné');
            this.logger.groupEnd();
            return;
        }
        
        if (!this.currentContacts) {
            this.logger.error('❌ AUCUNE donnée de contact chargée!');
            this.logger.groupEnd();
            return;
        }
        
        // Trouver le contact dans les données actuelles
        const contact = this.currentContacts.find(c => c.id == contactId);
        
        if (!contact) {
            this.logger.error(`❌ Contact ${contactId} non trouvé dans les données`);
            this.logger.error('Contacts disponibles:', this.currentContacts.map(c => ({ id: c.id, label: c.label })));
            this.logger.groupEnd();
            return;
        }
        
        this.logger.success(`✅ Contact trouvé:`, {
            id: contact.id,
            label: contact.label,
            email: contact.email,
            adresse_id: contact.adresse_id
        });
        
        // Synchroniser l'email UNIQUEMENT pour le contact de livraison
        if (target === 'livraison') {
            this.logger.info('📧 Synchronisation email (contact livraison)...');
            this.syncContactEmail(contact);
        } else {
            this.logger.info('📧 Email non synchronisé (contact facturation - règle métier)');
        }
        
        // Synchroniser l'adresse associée au contact
        if (contact.adresse_id) {
            this.logger.info(`📍 Synchronisation adresse ${contact.adresse_id} pour ${target}...`);
            this.syncContactAddress(target, contact.adresse_id);
        } else {
            this.logger.warn(`⚠️ Contact ${contactId} n'a pas d'adresse associée`);
        }
        
        this.logger.groupEnd();
    }
    
    /**
     * Synchronise l'email du contact avec le champ email du devis
     */
    syncContactEmail(contact) {
        this.logger.group('📧 SYNCHRONISATION EMAIL');
        
        if (!contact.email) {
            this.logger.warn(`❌ Contact ${contact.id} n'a pas d'email`);
            this.logger.groupEnd();
            return;
        }
        
        const emailField = document.getElementById('devis_emailEnvoiAutomatique') || 
                          document.getElementById('email_envoi_automatique');
        
        this.logger.debug('Champs email recherchés:', {
            devis_emailEnvoiAutomatique: document.getElementById('devis_emailEnvoiAutomatique'),
            email_envoi_automatique: document.getElementById('email_envoi_automatique'),
            found: emailField
        });
                          
        if (emailField) {
            const oldValue = emailField.value;
            emailField.value = contact.email;
            this.logger.success(`✅ Email synchronisé: ${oldValue} → ${contact.email}`);
            
            // Déclencher l'événement change pour les éventuels listeners
            emailField.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            this.logger.error('❌ AUCUN champ email trouvé!');
        }
        
        this.logger.groupEnd();
    }
    
    /**
     * Synchronise l'adresse du contact avec le sélecteur d'adresse
     */
    syncContactAddress(target, addressId) {
        this.logger.group(`📍 SYNCHRONISATION ADRESSE ${target.toUpperCase()}`);
        
        const addressSelector = this.getAddressSelector(target);
        
        this.logger.debug(`Sélecteur adresse ${target}:`, {
            element: addressSelector,
            id: addressSelector?.id,
            optionsCount: addressSelector?.options?.length
        });
        
        if (!addressSelector) {
            this.logger.error(`❌ Sélecteur d'adresse ${target} non trouvé`);
            this.logger.groupEnd();
            return;
        }
        
        // Vérifier si l'adresse existe dans les options
        const options = Array.from(addressSelector.options);
        const option = options.find(opt => opt.value == addressId);
        
        this.logger.debug('Options disponibles:', options.map(opt => ({ value: opt.value, text: opt.textContent })));
        this.logger.debug(`Recherche adresse ${addressId}:`, { found: option });
        
        if (option) {
            const oldValue = addressSelector.value;
            addressSelector.value = addressId;
            this.logger.success(`✅ Adresse ${target} synchronisée: ${oldValue} → ${addressId} (${option.textContent})`);
            
            // Déclencher l'événement change
            addressSelector.dispatchEvent(new Event('change', { bubbles: true }));
            
            // Mettre à jour la visibilité du bouton d'édition
            this.updateEditButtonVisibility('address', target, addressId);
        } else {
            this.logger.error(`❌ Adresse ${addressId} non trouvée dans les options du sélecteur ${target}`);
            this.logger.error('Options disponibles:', options.map(opt => ({ value: opt.value, text: opt.textContent })));
        }
        
        this.logger.groupEnd();
    }
    
    /**
     * Sélectionne automatiquement les contacts et adresses par défaut
     */
    selectDefaultItems(contacts, addresses) {
        // Sélectionner les contacts par défaut
        contacts.forEach(contact => {
            if (contact.is_livraison_default) {
                const selector = this.getContactSelector('livraison');
                if (selector) {
                    selector.value = contact.id;
                    selector.dispatchEvent(new Event('change'));
                    
                    // Synchroniser l'email UNIQUEMENT pour le contact de livraison
                    if (contact.email) {
                        this.logger.info('📧 Synchronisation email automatique (contact livraison par défaut)');
                        this.syncEmail(contact.email);
                    }
                }
            }
            
            if (contact.is_facturation_default) {
                const selector = this.getContactSelector('facturation');
                if (selector) {
                    selector.value = contact.id;
                    selector.dispatchEvent(new Event('change'));
                    this.logger.info('📧 Email non synchronisé (contact facturation par défaut - règle métier)');
                }
            }
        });
        
        // Sélectionner les adresses par défaut
        this.logger.info('Sélection des adresses par défaut...');
        
        // Livraison : utiliser l'adresse du contact par défaut ou la première adresse
        const defaultLivraisonContact = contacts.find(c => c.is_livraison_default);
        const livraisonAddressSelector = this.getAddressSelector('livraison');
        if (livraisonAddressSelector && addresses.length > 0) {
            let selectedAddressId = null;
            
            // Priorité 1: Adresse du contact de livraison par défaut
            if (defaultLivraisonContact && defaultLivraisonContact.adresse_id) {
                selectedAddressId = defaultLivraisonContact.adresse_id;
                this.logger.debug('Adresse livraison du contact par défaut:', selectedAddressId);
            } 
            // Priorité 2: Première adresse disponible
            else if (addresses.length > 0) {
                selectedAddressId = addresses[0].id;
                this.logger.debug('Première adresse disponible pour livraison:', selectedAddressId);
            }
            
            if (selectedAddressId) {
                livraisonAddressSelector.value = selectedAddressId;
                livraisonAddressSelector.dispatchEvent(new Event('change'));
                this.logger.success('Adresse livraison sélectionnée:', selectedAddressId);
            }
        }
        
        // Facturation : utiliser l'adresse du contact par défaut ou la première adresse
        const defaultFacturationContact = contacts.find(c => c.is_facturation_default);
        const facturationAddressSelector = this.getAddressSelector('facturation');
        if (facturationAddressSelector && addresses.length > 0) {
            let selectedAddressId = null;
            
            // Priorité 1: Adresse du contact de facturation par défaut
            if (defaultFacturationContact && defaultFacturationContact.adresse_id) {
                selectedAddressId = defaultFacturationContact.adresse_id;
                this.logger.debug('Adresse facturation du contact par défaut:', selectedAddressId);
            } 
            // Priorité 2: Première adresse disponible
            else if (addresses.length > 0) {
                selectedAddressId = addresses[0].id;
                this.logger.debug('Première adresse disponible pour facturation:', selectedAddressId);
            }
            
            if (selectedAddressId) {
                facturationAddressSelector.value = selectedAddressId;
                facturationAddressSelector.dispatchEvent(new Event('change'));
                this.logger.success('Adresse facturation sélectionnée:', selectedAddressId);
            }
        }
    }
    
    /**
     * Synchronise le champ caché client_field pour /devis/new
     */
    syncClientField(clientId) {
        const clientField = document.getElementById('client_field');
        if (clientField) {
            clientField.value = clientId;
            this.log(`🔄 Champ client_field synchronisé: ${clientId}`);
        }
    }
    
    /**
     * Met à jour les data-client-id de tous les boutons
     */
    updateButtonsClientId(clientId) {
        document.querySelectorAll('[data-client-id]').forEach(button => {
            button.dataset.clientId = clientId;
        });
    }
    
    /**
     * Affiche la section contacts
     */
    showContactsSection() {
        const section = document.getElementById('contacts-addresses-section') || 
                       document.getElementById('contact-selection');
        if (section) {
            section.style.display = '';
        }
        
        // Afficher le bouton d'édition client
        const editClientBtn = document.getElementById('edit-client-btn');
        if (editClientBtn) {
            editClientBtn.style.display = 'inline-block';
        }
    }
    
    /**
     * Masque la section contacts
     */
    hideContactsSection() {
        const section = document.getElementById('contacts-addresses-section') || 
                       document.getElementById('contact-selection');
        if (section) {
            section.style.display = 'none';
        }
        
        // Masquer le bouton d'édition client
        const editClientBtn = document.getElementById('edit-client-btn');
        if (editClientBtn) {
            editClientBtn.style.display = 'none';
        }
    }
    
    /**
     * Vide tous les sélecteurs de contacts et adresses
     */
    clearContactsAndAddresses() {
        // Vider les contacts
        const contactSelectors = this.getContactSelectors();
        contactSelectors.forEach(({element}) => {
            if (element) {
                element.innerHTML = '<option value="">Sélectionner...</option>';
                element.dispatchEvent(new Event('change'));
            }
        });
        
        // Vider les adresses
        const addressSelectors = this.getAddressSelectors();
        addressSelectors.forEach(({element}) => {
            if (element) {
                element.innerHTML = '<option value="">Choisir une adresse...</option>';
                element.dispatchEvent(new Event('change'));
            }
        });
        
        // Vider l'email
        const emailField = this.getEmailField();
        if (emailField) {
            emailField.value = '';
        }
    }
    
    // =====================================
    // GESTIONNAIRES D'ACTIONS
    // =====================================
    
    /**
     * Gère la création d'un contact
     */
    handleCreateContact(event) {
        const button = this.findActionButton(event.target);
        if (!button) return;
        
        const target = button.dataset.target;
        const clientId = this.getClientId();
        
        this.log(`🔹 Création contact ${target}`, { clientId });
        
        if (!this.validateClientSelected(clientId)) return;
        if (!this.validateTarget(target)) return;
        
        // Stocker le contexte pour la sélection après création
        this.currentContext = target;
        
        this.openContactModal('create', clientId, target);
    }
    
    /**
     * Gère l'édition d'un contact
     */
    handleEditContact(event) {
        const button = this.findActionButton(event.target);
        if (!button) return;
        
        const target = button.dataset.target;
        const contactId = this.getContactId(target);
        
        this.log(`✏️ Édition contact ${target}`, { contactId });
        
        if (!this.validateTarget(target)) return;
        if (!contactId) {
            alert(`Veuillez d'abord sélectionner un contact de ${target}`);
            return;
        }
        
        this.openContactModal('edit', contactId, target);
    }
    
    /**
     * Gère la création d'une adresse
     */
    handleCreateAddress(event) {
        const button = this.findActionButton(event.target);
        if (!button) return;
        
        const target = button.dataset.target;
        const clientId = this.getClientId();
        
        this.log(`🔹 Création adresse ${target}`, { clientId });
        
        if (!this.validateClientSelected(clientId)) return;
        if (!this.validateTarget(target)) return;
        
        this.openAddressModal('create', clientId, target);
    }
    
    /**
     * Gère l'édition d'une adresse
     */
    handleEditAddress(event) {
        const button = this.findActionButton(event.target);
        if (!button) return;
        
        const target = button.dataset.target;
        const addressId = this.getAddressId(target);
        
        this.log(`✏️ Édition adresse ${target}`, { addressId });
        
        if (!this.validateTarget(target)) return;
        if (!addressId) {
            alert(`Veuillez d'abord sélectionner une adresse de ${target}`);
            return;
        }
        
        this.openAddressModal('edit', addressId, target);
    }
    
    // =====================================
    // GESTIONNAIRES D'ÉVÉNEMENTS
    // =====================================
    
    /**
     * Gère le retour de création de contact
     */
    async handleContactCreated(detail) {
        const contact = detail.contact;
        this.log('✅ Contact créé', contact);
        
        try {
            // Fermer la modale
            this.closeModal('contact-modal');
            
            // Bloquer temporairement les autres services
            this.blockConflictingServices();
            
            // Sélection chirurgicale dans le bon champ uniquement
            if (this.currentContext) {
                await this.selectContactInField(this.currentContext, contact.id);
                
                // Synchroniser l'email si contact de livraison
                if (this.currentContext === 'livraison' && contact.email) {
                    this.syncEmail(contact.email);
                }
            }
            
        } catch (error) {
            this.error('Erreur lors du traitement de création contact', error);
        } finally {
            // Nettoyer le contexte et restaurer les services
            this.currentContext = null;
            setTimeout(() => this.unblockConflictingServices(), 2000);
        }
    }
    
    /**
     * Gère le retour de modification de contact
     */
    handleContactUpdated(detail) {
        const contact = detail.contact;
        this.log('✅ Contact modifié', contact);
        
        // Fermer la modale et recharger les données
        this.closeModal('contact-modal');
        this.reloadContactsForClient(this.getClientId());
    }
    
    /**
     * Gère le retour de création d'adresse
     */
    handleAddressCreated(detail) {
        const address = detail.adresse;
        this.log('✅ Adresse créée', address);
        
        // Fermer la modale et recharger les données
        this.closeModal('address-modal');
        this.reloadAddressesForClient(this.getClientId());
    }
    
    /**
     * Gère le retour de modification d'adresse
     */
    handleAddressUpdated(detail) {
        const address = detail.adresse;
        this.log('✅ Adresse modifiée', address);
        
        // Fermer la modale et recharger les données
        this.closeModal('address-modal');
        this.reloadAddressesForClient(this.getClientId());
    }
    
    // =====================================
    // SÉLECTION ET SYNCHRONISATION
    // =====================================
    
    /**
     * Sélectionne un contact uniquement dans le champ spécifié
     */
    async selectContactInField(target, contactId) {
        this.log(`🎯 Sélection chirurgicale: ${target} = ${contactId}`);
        
        const selector = this.getContactSelector(target);
        if (!selector) {
            this.log('❌ Sélecteur non trouvé pour', target);
            return;
        }
        
        // Sélectionner le contact
        selector.value = contactId;
        selector.dispatchEvent(new Event('change'));
        
        // Charger l'adresse par défaut du contact
        await this.loadContactDefaultAddress(contactId, target);
        
        this.log(`✅ Contact ${contactId} sélectionné dans ${target}`);
    }
    
    /**
     * Synchronise l'email d'envoi automatique
     */
    syncEmail(email) {
        const emailField = this.getEmailField();
        if (emailField && email) {
            emailField.value = email;
            this.log(`📧 Email synchronisé: ${email}`);
        }
    }
    
    /**
     * Charge l'adresse par défaut d'un contact
     */
    async loadContactDefaultAddress(contactId, target) {
        try {
            const response = await fetch(`/contact/${contactId}/default-address`);
            const data = await response.json();
            
            if (data.success && data.address) {
                const addressSelector = this.getAddressSelector(target);
                if (addressSelector) {
                    addressSelector.value = data.address.id;
                    addressSelector.dispatchEvent(new Event('change'));
                    this.log(`📍 Adresse par défaut chargée pour ${target}`);
                }
            }
        } catch (error) {
            this.log('⚠️ Erreur chargement adresse par défaut', error);
        }
    }
    
    // =====================================
    // RECHARGEMENT DES DONNÉES
    // =====================================
    
    /**
     * Recharge les contacts pour un client
     */
    async reloadContactsForClient(clientId) {
        if (!clientId) return;
        
        try {
            const response = await fetch(`/client/${clientId}/contacts`);
            const contacts = await response.json();
            
            // Stocker les contacts pour la synchronisation
            this.currentContacts = contacts;
            
            this.populateContactSelectors(contacts);
            this.log(`🔄 Contacts rechargés: ${contacts.length}`);
        } catch (error) {
            this.error('Erreur rechargement contacts', error);
        }
    }
    
    /**
     * Recharge les adresses pour un client
     */
    async reloadAddressesForClient(clientId) {
        if (!clientId) return;
        
        try {
            const response = await fetch(`/client/${clientId}/addresses`);
            const addresses = await response.json();
            this.populateAddressSelectors(addresses);
            this.log(`🔄 Adresses rechargées: ${addresses.length}`);
        } catch (error) {
            this.error('Erreur rechargement adresses', error);
        }
    }
    
    /**
     * Peuple les sélecteurs de contacts
     */
    populateContactSelectors(contacts) {
        const selectors = this.getContactSelectors();
        
        selectors.forEach(({element, target}) => {
            if (element) {
                const currentValue = element.value;
                
                // Vider et repeupler
                element.innerHTML = '<option value="">Sélectionner...</option>';
                contacts.forEach(contact => {
                    const option = document.createElement('option');
                    option.value = contact.id;
                    option.textContent = this.formatContactLabel(contact);
                    element.appendChild(option);
                });
                
                // Restaurer la valeur si elle existe encore
                if (currentValue && contacts.find(c => c.id == currentValue)) {
                    element.value = currentValue;
                }
                
                // Mettre à jour la visibilité des boutons
                this.updateEditButtonVisibility('contact', target, element.value);
            }
        });
    }
    
    /**
     * Peuple les sélecteurs d'adresses
     */
    populateAddressSelectors(addresses) {
        const selectors = this.getAddressSelectors();
        
        selectors.forEach(({element, target}) => {
            if (element) {
                const currentValue = element.value;
                
                // Vider et repeupler
                element.innerHTML = '<option value="">Choisir une adresse...</option>';
                addresses.forEach(address => {
                    const option = document.createElement('option');
                    option.value = address.id;
                    option.textContent = this.formatAddressLabel(address);
                    element.appendChild(option);
                });
                
                // Restaurer la valeur si elle existe encore
                if (currentValue && addresses.find(a => a.id == currentValue)) {
                    element.value = currentValue;
                }
                
                // Mettre à jour la visibilité des boutons
                this.updateEditButtonVisibility('address', target, element.value);
            }
        });
    }
    
    // =====================================
    // UTILITAIRES DOM
    // =====================================
    
    /**
     * Trouve le bouton avec data-action en remontant l'arbre DOM
     */
    findActionButton(element) {
        let current = element;
        while (current && !current.dataset.action) {
            current = current.parentElement;
        }
        return current;
    }
    
    /**
     * Retourne le sélecteur de client selon le mode
     */
    getClientSelector() {
        // Mode EDIT : #devis_client
        const editSelect = document.getElementById('devis_client');
        if (editSelect) {
            this.logger.debug('Sélecteur client EDIT trouvé', { id: 'devis_client', value: editSelect.value });
            return editSelect;
        }
        
        // Mode CREATE : #client_selector
        const newSelect = document.getElementById('client_selector');
        if (newSelect) {
            this.logger.debug('Sélecteur client CREATE trouvé', { id: 'client_selector', value: newSelect.value });
            return newSelect;
        }
        
        this.logger.error('AUCUN sélecteur client trouvé!');
        this.logger.error('IDs recherchés: devis_client, client_selector');
        this.logger.error('Éléments dans le DOM:', {
            devis_client: document.getElementById('devis_client'),
            client_selector: document.getElementById('client_selector')
        });
        return null;
    }
    
    /**
     * Retourne les sélecteurs de contacts avec leur target
     */
    getContactSelectors() {
        const mode = this.getMode();
        
        if (mode === 'edit') {
            return [
                { element: document.getElementById('devis_contactLivraison'), target: 'livraison' },
                { element: document.getElementById('devis_contactFacturation'), target: 'facturation' }
            ];
        } else {
            return [
                { element: document.getElementById('contact_defaut'), target: 'livraison' },
                { element: document.getElementById('contact_facturation'), target: 'facturation' }
            ];
        }
    }
    
    /**
     * Retourne les sélecteurs d'adresses avec leur target
     */
    getAddressSelectors() {
        return [
            { element: document.getElementById('adresse_livraison_select'), target: 'livraison' },
            { element: document.getElementById('adresse_facturation_select'), target: 'facturation' }
        ];
    }
    
    /**
     * Retourne le sélecteur de contact pour un target donné
     */
    getContactSelector(target) {
        const selectors = this.getContactSelectors();
        const found = selectors.find(s => s.target === target);
        return found?.element || null;
    }
    
    /**
     * Retourne le sélecteur d'adresse pour un target donné
     */
    getAddressSelector(target) {
        const selectors = this.getAddressSelectors();
        const found = selectors.find(s => s.target === target);
        return found?.element || null;
    }
    
    /**
     * Retourne le champ email selon le mode
     */
    getEmailField() {
        return document.getElementById('devis_emailEnvoiAutomatique') ||
               document.getElementById('email_envoi_automatique');
    }
    
    /**
     * Retourne l'ID du client actuel
     */
    getClientId() {
        const editSelect = document.getElementById('devis_client');
        if (editSelect) return editSelect.value;
        
        const newSelect = document.getElementById('client_selector');
        if (newSelect) return newSelect.value;
        
        const hiddenField = document.getElementById('client_field');
        if (hiddenField) return hiddenField.value;
        
        return null;
    }
    
    /**
     * Retourne l'ID du contact pour un target donné
     */
    getContactId(target) {
        const selector = this.getContactSelector(target);
        return selector?.value || null;
    }
    
    /**
     * Retourne l'ID de l'adresse pour un target donné
     */
    getAddressId(target) {
        const selector = this.getAddressSelector(target);
        return selector?.value || null;
    }
    
    // =====================================
    // FORMATAGE
    // =====================================
    
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
        return address.label || 
               `${address.ligne1 || ''} - ${address.codePostal || ''} ${address.ville || ''}`.trim();
    }
    
    // =====================================
    // VALIDATION
    // =====================================
    
    /**
     * Valide qu'un client est sélectionné
     */
    validateClientSelected(clientId) {
        if (!clientId) {
            alert('Veuillez d\'abord sélectionner un client');
            return false;
        }
        return true;
    }
    
    /**
     * Valide le target (livraison/facturation)
     */
    validateTarget(target) {
        if (!target || !['livraison', 'facturation'].includes(target)) {
            this.error('Target invalide', target);
            return false;
        }
        return true;
    }
    
    // =====================================
    // GESTION DES MODALES
    // =====================================
    
    /**
     * Ouvre une modale de contact
     */
    openContactModal(mode, id, target) {
        const url = mode === 'create' 
            ? `/contact/modal/new/${id}?type=${target}`
            : `/contact/modal/edit/${id}`;
        
        const title = mode === 'create' 
            ? 'Ajouter un contact' 
            : 'Modifier le contact';
        
        this.openModal(url, title, 'contact-modal');
    }
    
    /**
     * Ouvre une modale d'adresse
     */
    openAddressModal(mode, id, target) {
        const url = mode === 'create' 
            ? `/adresse/modal/new/${id}`
            : `/adresse/modal/edit/${id}`;
        
        const title = mode === 'create' 
            ? 'Ajouter une adresse' 
            : 'Modifier l\'adresse';
        
        this.openModal(url, title, 'address-modal');
    }
    
    /**
     * Ouvre une modale générique
     */
    openModal(url, title, modalId) {
        if (window.ModalService) {
            const modalService = new ModalService({
                debug: this.config.debug,
                modalId: modalId
            });
            modalService.openModal(url, title);
        } else {
            this.error('ModalService non disponible');
        }
    }
    
    /**
     * Ferme une modale de manière robuste
     */
    closeModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) return;
        
        // Essayer Bootstrap
        if (window.bootstrap?.Modal) {
            const instance = bootstrap.Modal.getInstance(modalElement);
            if (instance) {
                instance.hide();
                return;
            }
        }
        
        // Fallback manuel
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        document.querySelector('.modal-backdrop')?.remove();
        document.body.classList.remove('modal-open');
        
        this.log(`🔒 Modale ${modalId} fermée`);
    }
    
    // =====================================
    // GESTION DE LA VISIBILITÉ
    // =====================================
    
    /**
     * Met à jour la visibilité d'un bouton d'édition
     */
    updateEditButtonVisibility(type, target, value) {
        const button = document.querySelector(`[data-action="edit-${type}"][data-target="${target}"]`);
        if (button) {
            button.style.display = value ? 'inline-block' : 'none';
        }
    }
    
    // =====================================
    // GESTION DES CONFLITS
    // =====================================
    
    /**
     * Bloque temporairement les services conflictuels
     */
    blockConflictingServices() {
        if (window.clientProspectService?.reloadContactsOnly) {
            this.originalReloadContactsOnly = window.clientProspectService.reloadContactsOnly;
            window.clientProspectService.reloadContactsOnly = () => Promise.resolve();
            this.log('🛡️ Services conflictuels bloqués');
        }
    }
    
    /**
     * Restaure les services conflictuels
     */
    unblockConflictingServices() {
        if (this.originalReloadContactsOnly) {
            window.clientProspectService.reloadContactsOnly = this.originalReloadContactsOnly;
            this.originalReloadContactsOnly = null;
            this.log('🔄 Services conflictuels restaurés');
        }
    }
    
    // =====================================
    // GESTION DES EVENT LISTENERS
    // =====================================
    
    /**
     * Ajoute un event listener avec tracking pour le nettoyage
     */
    addEventListener(element, event, handler, useCapture = false) {
        element.addEventListener(event, handler, useCapture);
        
        // Stocker pour le nettoyage
        if (!this.eventListeners.has(element)) {
            this.eventListeners.set(element, []);
        }
        this.eventListeners.get(element).push({ event, handler, useCapture });
    }
    
    /**
     * Nettoie tous les event listeners
     */
    cleanupEventListeners() {
        for (const [element, listeners] of this.eventListeners) {
            listeners.forEach(({ event, handler, useCapture }) => {
                element.removeEventListener(event, handler, useCapture);
            });
        }
        this.eventListeners.clear();
        this.log('🧹 Event listeners nettoyés');
    }
    
    // =====================================
    // DESTRUCTION
    // =====================================
    
    /**
     * Détruit le service et nettoie les ressources
     */
    destroy() {
        this.unblockConflictingServices();
        this.cleanupEventListeners();
        this.currentContext = null;
        this.isInitialized = false;
        window.devisContactServiceActive = false;
        this.log('🗑️ Service détruit');
    }
}

// Rendre disponible globalement
window.DevisContactService = DevisContactService;