/**
 * Service centralisé de navigation multi-modale
 * Gère les transitions Contact ↔ Adresse de manière fluide et réutilisable
 */
class MultiModalNavigationService {
    constructor(config = {}) {
        this.config = {
            debug: config.debug || false,
            defaultDelay: 300, // Délai standard pour transitions
            selectionDelay: 500, // Délai pour présélections
            cleanupDelay: 100, // Délai pour nettoyage DOM
            eventMaxAge: 60000, // Age max des événements (60s)
            ...config
        };
        
        this.navigationStack = [];
        this.initialized = false;
        
        this.log('🚀 MultiModalNavigationService initialisé');
        this.init();
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[MultiModalNavigationService] ${message}`, data || '');
        }
    }
    
    error(message, error = null) {
        console.error(`[MultiModalNavigationService] ${message}`, error || '');
    }
    
    /**
     * Initialise le service
     */
    init() {
        // Écouter les événements globaux pour gérer les retours automatiques
        this.setupEventListeners();
        this.initialized = true;
        this.log('✅ Service de navigation multi-modale initialisé');
    }
    
    /**
     * Configure les écouteurs d'événements globaux
     */
    setupEventListeners() {
        // Retour automatique après création d'adresse
        window.addEventListener('addressCreated', (event) => {
            this.log('📍 Adresse créée - vérification retour automatique', event.detail);
            this.handleAddressActionComplete(event.detail, 'created');
        });
        
        // Retour automatique après modification d'adresse  
        window.addEventListener('addressUpdated', (event) => {
            this.log('📍 Adresse modifiée - vérification retour automatique', event.detail);
            this.handleAddressActionComplete(event.detail, 'updated');
        });
        
        // Retour automatique après création de contact
        window.addEventListener('contactCreated', (event) => {
            this.log('👤 Contact créé - vérification retour automatique', event.detail);
            this.handleContactActionComplete(event.detail, 'created');
        });
        
        // Retour automatique après modification de contact
        window.addEventListener('contactUpdated', (event) => {
            this.log('👤 Contact modifié - vérification retour automatique', event.detail);
            this.handleContactActionComplete(event.detail, 'updated');
        });
    }
    
    /**
     * Navigue de Contact vers Adresse (création ou édition)
     */
    navigateFromContactToAddress(contactInfo, addressAction, addressId = null) {
        this.log('🔄 Navigation Contact → Adresse', {
            contactInfo,
            addressAction,
            addressId
        });
        
        // Stocker les informations de navigation
        const navigationEvent = {
            mode: contactInfo.mode || 'edit',
            contactId: contactInfo.contactId,
            clientId: contactInfo.clientId,
            fromModal: true,
            noReturn: contactInfo.noReturn || false,
            timestamp: Date.now(),
            sourceModal: 'contact',
            targetModal: 'address',
            targetAction: addressAction,
            targetId: addressId
        };
        
        // Stocker dans la variable globale (compatibilité avec système existant)
        window.lastContactEditEvent = navigationEvent;
        this.log('💾 Navigation stockée:', navigationEvent);
        
        // Fermer la modale contact actuelle
        const visibleModal = document.querySelector('.modal.show');
        if (visibleModal) {
            this.log('🔍 Modale visible trouvée:', {
                id: visibleModal.id,
                classes: Array.from(visibleModal.classList),
                title: visibleModal.querySelector('.modal-title')?.textContent
            });
            
            const currentModal = bootstrap.Modal.getInstance(visibleModal);
            if (currentModal) {
                this.log('🔒 Fermeture modale contact en cours...');
                currentModal.hide();
            } else {
                this.log('❌ Instance Bootstrap Modal non trouvée, tentative de fermeture manuelle');
                // Fermeture manuelle si l'instance Bootstrap n'est pas trouvée
                visibleModal.classList.remove('show');
                visibleModal.style.display = 'none';
                visibleModal.setAttribute('aria-hidden', 'true');
                
                // Supprimer les backdrops
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                
                // Restaurer le body
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
            }
        } else {
            this.log('❌ Aucune modale visible trouvée pour fermeture');
        }
        
        // Attendre fermeture complète puis ouvrir modale adresse
        setTimeout(() => {
            this.log('⏰ Ouverture modale adresse après délai...');
            this.openAddressModal(addressAction, addressId || contactInfo.clientId);
        }, this.config.defaultDelay);
    }
    
    /**
     * Ouvre une modale d'adresse (création ou édition)
     */
    openAddressModal(action, id) {
        try {
            if (!window.AddressModalService) {
                this.error('AddressModalService non disponible');
                return;
            }
            
            const addressService = new AddressModalService({ debug: this.config.debug });
            
            if (action === 'create') {
                this.log('✅ Ouverture modale création adresse pour client:', id);
                addressService.createAddress(id);
            } else if (action === 'edit') {
                this.log('✅ Ouverture modale édition adresse:', id);
                addressService.editAddress(id);
            }
        } catch (error) {
            this.error('Erreur ouverture modale adresse:', error);
        }
    }
    
    /**
     * Gère la completion d'une action sur adresse (créée/modifiée)
     */
    handleAddressActionComplete(detail, action) {
        const lastEvent = window.lastContactEditEvent;
        
        this.log('🔍 handleAddressActionComplete - État complet:', {
            action: action,
            lastEvent: lastEvent,
            hasNoReturn: lastEvent?.noReturn,
            mode: lastEvent?.mode,
            contactId: lastEvent?.contactId,
            clientId: lastEvent?.clientId
        });
        
        // Vérifier si on doit retourner vers une modale contact
        if (!this.shouldReturnToContact(lastEvent)) {
            this.log('❌ Pas de retour vers contact nécessaire');
            return;
        }
        
        this.log(`🔄 Retour automatique vers contact après ${action} adresse`);
        
        // Marquer que la fermeture de la modale adresse est due à une validation
        window.addressEditSuccess = true;
        
        // Attendre fermeture complète de la modale adresse
        setTimeout(() => {
            this.returnToContactModal(lastEvent, detail, action);
        }, this.config.defaultDelay);
    }
    
    /**
     * Gère la completion d'une action sur contact (créé/modifié) 
     */
    handleContactActionComplete(detail, action) {
        // Pour l'instant pas de logique spéciale, mais extensible
        this.log(`✅ Contact ${action} - pas de navigation automatique requise`);
    }
    
    /**
     * Détermine s'il faut retourner vers la modale contact
     */
    shouldReturnToContact(lastEvent) {
        if (!lastEvent) {
            this.log('❌ Aucun événement de navigation stocké');
            return false;
        }
        
        if (!lastEvent.fromModal) {
            this.log('❌ Pas venu d\'une modale contact');
            return false;
        }
        
        // Vérifier le flag noReturn (ne pas rouvrir)
        if (lastEvent.noReturn) {
            this.log('❌ Flag noReturn activé - pas de retour automatique');
            this.cleanupNavigation();
            return false;
        }
        
        // Vérifier l'âge de l'événement
        const eventAge = Date.now() - lastEvent.timestamp;
        if (eventAge > this.config.eventMaxAge) {
            this.log('❌ Événement trop ancien:', eventAge + 'ms');
            this.cleanupNavigation();
            return false;
        }
        
        this.log('✅ Retour vers contact validé');
        return true;
    }
    
    /**
     * Retourne vers la modale contact d'origine
     */
    returnToContactModal(lastEvent, detail, action) {
        try {
            if (!window.ContactModalService) {
                this.error('ContactModalService non disponible');
                return;
            }
            
            this.log('🔄 Réouverture modale contact - DÉTAILS COMPLETS:', {
                mode: lastEvent.mode,
                contactId: lastEvent.contactId,
                clientId: lastEvent.clientId,
                noReturn: lastEvent.noReturn
            });
            
            const contactService = new ContactModalService({ debug: this.config.debug });
            
            if (lastEvent.mode === 'edit') {
                this.log('📝 Mode EDIT - Appel editContact avec ID:', lastEvent.contactId);
                contactService.editContact(lastEvent.contactId);
            } else if (lastEvent.mode === 'new') {
                this.log('➕ Mode NEW - Appel createContact avec clientId:', lastEvent.clientId);
                contactService.createContact(lastEvent.clientId);
            }
            
            // Stocker l'ID de l'adresse à présélectionner pour la modale contact
            if (detail && detail.adresse) {
                window.lastContactEditEvent.preselectAddressId = detail.adresse.id;
                this.log('💾 ID adresse stocké pour présélection:', detail.adresse.id);
                
                // Aussi lancer la présélection après un délai
                setTimeout(() => {
                    this.preselectAddress(detail.adresse.id);
                }, this.config.selectionDelay + 1000); // Délai plus long pour être sûr
            }
            
        } catch (error) {
            this.error('Erreur retour vers modale contact:', error);
        } finally {
            // Nettoyer l'événement de navigation
            this.cleanupNavigation();
        }
    }
    
    /**
     * Présélectionne une adresse dans la modale contact réouverte
     */
    preselectAddress(addressId) {
        this.log('🎯 Présélection adresse:', addressId);
        
        const addressSelect = document.getElementById('contact-adresse');
        if (addressSelect && addressId) {
            this.log('🔍 Element addressSelect trouvé, recherche option:', addressId);
            
            // Attendre un délai pour que la modale soit complètement chargée
            setTimeout(() => {
                // Vérifier que l'option existe
                const option = Array.from(addressSelect.options).find(opt => opt.value == addressId);
                this.log('🔍 Options disponibles:', Array.from(addressSelect.options).map(o => ({value: o.value, text: o.text})));
                
                if (option) {
                    this.log('✅ Option trouvée, sélection...');
                    addressSelect.value = addressId;
                    addressSelect.dispatchEvent(new Event('change'));
                    
                    // Si Select2 est présent, l'utiliser aussi
                    if (window.$ && $(addressSelect).hasClass('select2-hidden-accessible')) {
                        this.log('🔧 Mise à jour Select2...');
                        $(addressSelect).val(addressId).trigger('change');
                    }
                    
                    this.log('✅ Adresse présélectionnée:', addressId);
                } else {
                    this.log('❌ Option adresse non trouvée dans le select');
                    this.log('❌ AddressId recherché:', addressId);
                    this.log('❌ Options disponibles:', Array.from(addressSelect.options).map(opt => ({value: opt.value, text: opt.text})));
                }
            }, 1000); // Délai plus long pour s'assurer que tout est chargé
        } else {
            this.log('❌ AddressSelect non trouvé ou addressId manquant', {
                addressSelect: !!addressSelect,
                addressId: addressId
            });
        }
    }
    
    /**
     * Nettoie les événements de navigation
     */
    cleanupNavigation() {
        window.lastContactEditEvent = null;
        window.addressEditSuccess = false;
        this.navigationStack = [];
        this.log('🧹 Navigation nettoyée');
    }
    
    /**
     * Nettoie agressivement les backdrops orphelins
     */
    cleanupOrphanedBackdrops() {
        setTimeout(() => {
            const openModals = document.querySelectorAll('.modal.show');
            const allBackdrops = document.querySelectorAll('.modal-backdrop');
            
            this.log('🔍 Vérification backdrops:', {
                openModals: openModals.length,
                backdrops: allBackdrops.length
            });
            
            // Nettoyage si aucune modale ouverte
            if (openModals.length === 0 && allBackdrops.length > 0) {
                this.log('🧹 Nettoyage backdrops orphelins:', allBackdrops.length);
                allBackdrops.forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
            }
        }, this.config.cleanupDelay);
    }
    
    /**
     * Méthode publique pour initier navigation Contact → Adresse
     */
    static navigateToAddress(contactInfo, action, addressId = null) {
        if (!window.multiModalNavigationService) {
            window.multiModalNavigationService = new MultiModalNavigationService({ debug: false });
        }
        
        window.multiModalNavigationService.navigateFromContactToAddress(contactInfo, action, addressId);
    }
    
    /**
     * Méthode publique pour nettoyer
     */
    static cleanup() {
        if (window.multiModalNavigationService) {
            window.multiModalNavigationService.cleanupNavigation();
            window.multiModalNavigationService.cleanupOrphanedBackdrops();
        }
    }
}

// Rendre disponible globalement
window.MultiModalNavigationService = MultiModalNavigationService;

// Initialiser automatiquement une instance globale
window.multiModalNavigationService = new MultiModalNavigationService({ debug: false });

// Nettoyer les backdrops orphelins à l'initialisation
window.multiModalNavigationService.cleanupOrphanedBackdrops();