/**
 * TechnoProd Admin Tab Manager
 * Handles AJAX loading and CSS forcing for admin tabs
 */

class AdminTabManager {
    constructor() {
        this.loadedTabs = new Set();
        this.initializeRoutes();
    }

    initializeRoutes() {
        // Will be populated from Twig routes
        this.routes = window.adminRoutes || {};
    }

    loadTabContent(tabId) {
        console.log('🔍 DEBUG: loadTabContent called for:', tabId);
        console.log('🔍 DEBUG: Current URL:', window.location.href);
        console.log('🔍 DEBUG: Timestamp:', new Date().toISOString());
        
        const tabContent = document.querySelector(tabId + ' .admin-section');
        console.log('🔍 DEBUG: Selector used:', tabId + ' .admin-section');
        console.log('🔍 DEBUG: tabContent element:', tabContent);
        console.log('🔍 DEBUG: tabContent found:', !!tabContent);
        console.log('🔍 DEBUG: tabContent.dataset.loaded:', tabContent?.dataset?.loaded);
        
        if (!tabContent) {
            console.log('🔍 DEBUG: No tabContent found for selector:', tabId + ' .admin-section');
            return;
        }
        
        if (this.loadedTabs.has(tabId)) {
            console.log('🔍 DEBUG: Tab already loaded, reactivating:', tabId);
            this.activateTab(tabId);
            
            // Pour les onglets spéciaux, réinitialiser les sous-onglets même si déjà chargés
            if (tabId === '#gestion-societes') {
                console.log('🔍 DEBUG: Re-initializing Société sub-tabs');
                if (typeof setupSocietesSubTabsFixed === 'function') {
                    setTimeout(() => setupSocietesSubTabsFixed(), 200);
                } else {
                    console.error('❌ setupSocietesSubTabsFixed not available for reactivation!');
                }
            }
            
            return;
        }
        
        // Route specific tab handlers
        switch(tabId) {
            case '#juridique-comptable':
                this.setupJuridiqueComptableTab();
                break;
            case '#gestion-societes':
                this.setupGestionSocietesTab();
                break;
            case '#produits':
                this.setupProduitsTab();
                break;
            case '#unites':
                this.setupUnitesTab();
                break;
            case '#tags-clients':
                this.setupTagsClientsTab();
                break;
            case '#parametres-enseigne':
                this.setupParametresEnseigneTab();
                break;
            case '#utilisateurs':
                this.setupUtilisateursTab();
                break;
            case '#transporteurs':
                this.setupTransporteursTab();
                break;
            case '#frais-port':
                this.setupFraisPortTab();
                break;
            case '#parametres':
                this.setupParametresTab();
                break;
            case '#tiers':
                this.setupTiersTab();
                break;
            case '#produits-services':
                this.setupProduitsServicesTab();
                break;
            case '#templates-content':
                this.setupTemplatesTab();
                break;
            default:
                this.setupGenericTab(tabId);
        }
        
        this.loadedTabs.add(tabId);
        if (tabContent) tabContent.dataset.loaded = 'true';
    }

    activateTab(tabId) {
        const tabPane = document.querySelector(tabId);
        if (tabPane) {
            // Désactiver tous les autres onglets
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
                pane.style.display = 'none';
                pane.style.visibility = 'hidden';
                pane.style.opacity = '0';
            });
            
            // Activer l'onglet courant
            tabPane.classList.add('show', 'active');
            tabPane.style.display = 'block';
            tabPane.style.visibility = 'visible';
            tabPane.style.opacity = '1';
            
            // Scroller vers le haut
            setTimeout(() => {
                window.scrollTo(0, 0);
                tabPane.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
            
            console.log('🔍 DEBUG: Tab activated successfully:', tabId);
        }
    }

    setupJuridiqueComptableTab() {
        console.log('🔍 DEBUG: Setting up Juridique Comptable tab');
        
        if (typeof setupJuridiqueComptableTabFixed === 'function') {
            console.log('✅ setupJuridiqueComptableTabFixed found, calling it');
            setupJuridiqueComptableTabFixed();
        } else {
            console.error('❌ setupJuridiqueComptableTabFixed function not available!');
        }
    }

    setupGestionSocietesTab() {
        console.log('🔍 DEBUG: Setting up Gestion Societes tab');
        const societesPane = document.querySelector('#gestion-societes');
        if (societesPane) {
            this.activateTab('#gestion-societes');
            
            if (typeof setupSocietesSubTabsFixed === 'function') {
                console.log('✅ setupSocietesSubTabsFixed found, calling it');
                setupSocietesSubTabsFixed();
            } else {
                console.error('❌ setupSocietesSubTabsFixed function not available!');
            }
        } else {
            console.error('❌ ERROR: #gestion-societes not found!');
        }
    }

    setupGenericTab(tabId) {
        console.log('🔍 DEBUG: Setting up generic tab:', tabId);
        this.activateTab(tabId);
    }

    setupProduitsTab() {
        console.log('🔍 DEBUG: Setting up Produits tab');
        this.activateTab('#produits');
        
        // Remplacer le spinner par le contenu AJAX
        const produitsSection = document.querySelector('#produits .admin-section');
        if (produitsSection && window.adminRoutes && window.adminRoutes.produits) {
            window.adminAjaxLoader.loadContentIntoElementFixed(
                window.adminRoutes.produits, 
                produitsSection, 
                '#produits'
            );
        } else {
            console.log('⚠️ Route produits non configurée, affichage du contenu par défaut');
            this.showTabMessage('#produits', 'Gestion des Produits', 'Interface de gestion des produits en développement.');
        }
    }

    setupUnitesTab() {
        console.log('🔍 DEBUG: Setting up Unités tab');
        this.activateTab('#unites');
        
        // Charger le contenu AJAX si la route existe
        const unitesSection = document.querySelector('#unites .admin-section');
        if (unitesSection && window.adminRoutes && window.adminRoutes.unites) {
            window.adminAjaxLoader.loadContentIntoElementFixed(
                window.adminRoutes.unites, 
                unitesSection, 
                '#unites'
            );
        } else {
            console.log('⚠️ Route unités non configurée, affichage du contenu par défaut');
            this.showTabMessage('#unites', 'Gestion des Unités', 'Interface de gestion des unités en développement.');
        }
    }

    setupTagsClientsTab() {
        console.log('🔍 DEBUG: Setting up Tags Clients tab');
        this.activateTab('#tags-clients');
        
        // Charger le contenu AJAX si la route existe
        const tagsSection = document.querySelector('#tags-clients .admin-section');
        if (tagsSection && window.adminRoutes && window.adminRoutes.tags) {
            window.adminAjaxLoader.loadContentIntoElementFixed(
                window.adminRoutes.tags, 
                tagsSection, 
                '#tags-clients'
            );
        } else {
            console.log('⚠️ Route tags non configurée, affichage du contenu par défaut');
            this.showTabMessage('#tags-clients', 'Gestion des Tags Clients', 'Interface de gestion des tags clients en développement.');
        }
    }

    setupParametresEnseigneTab() {
        console.log('🔍 DEBUG: Setting up Paramètres Enseigne tab');
        this.activateTab('#parametres-enseigne');
        
        // Pour les paramètres enseigne, on affiche un message informatif
        this.showTabMessage('#parametres-enseigne', 'Paramètres de l\'Enseigne', 'Configuration de l\'identité visuelle et de la communication spécifiques à cette enseigne. Fonctionnalité en cours de développement.');
    }

    setupUtilisateursTab() {
        console.log('🔍 DEBUG: Setting up Utilisateurs tab');
        this.activateTab('#utilisateurs');
        
        // Charger le contenu AJAX des utilisateurs si la route existe
        const utilisateursDiv = document.querySelector('#utilisateurs-management');
        if (utilisateursDiv && window.adminRoutes && window.adminRoutes.users) {
            window.adminAjaxLoader.loadContentIntoElementFixed(
                window.adminRoutes.users, 
                utilisateursDiv, 
                '#utilisateurs'
            );
        } else {
            console.log('⚠️ Route utilisateurs non configurée, redirection vers gestion-societes');
            // Rediriger vers l'onglet gestion-societes qui a la gestion utilisateurs
            setTimeout(() => {
                const usersTab = document.querySelector('a[href="#gestion-societes"]');
                if (usersTab) {
                    usersTab.click();
                }
            }, 100);
        }
    }

    setupTransporteursTab() {
        console.log('🔍 DEBUG: Setting up Transporteurs tab');
        this.activateTab('#transporteurs');
        
        // Charger le contenu AJAX
        const transporteursSection = document.querySelector('#transporteurs .admin-section');
        if (transporteursSection && window.adminRoutes && window.adminRoutes.transporteurs) {
            window.adminAjaxLoader.loadContentIntoElementFixed(
                window.adminRoutes.transporteurs, 
                transporteursSection, 
                '#transporteurs'
            );
        } else {
            console.log('⚠️ Route transporteurs non configurée, affichage du contenu par défaut');
            this.showTabMessage('#transporteurs', 'Gestion des Transporteurs', 'Interface de gestion des transporteurs et méthodes d\'expédition.');
        }
    }

    setupFraisPortTab() {
        console.log('🔍 DEBUG: Setting up Frais de Port tab');
        this.activateTab('#frais-port');
        
        // Charger le contenu AJAX
        const fraisPortSection = document.querySelector('#frais-port .admin-section');
        if (fraisPortSection && window.adminRoutes && window.adminRoutes.frais_port) {
            window.adminAjaxLoader.loadContentIntoElementFixed(
                window.adminRoutes.frais_port, 
                fraisPortSection, 
                '#frais-port'
            );
        } else {
            console.log('⚠️ Route frais de port non configurée, affichage du contenu par défaut');
            this.showTabMessage('#frais-port', 'Gestion des Frais de Port', 'Configuration des frais de port et règles d\'expédition.');
        }
    }

    setupParametresTab() {
        console.log('🔍 DEBUG: Setting up Paramètres tab');
        this.activateTab('#parametres');

        if (typeof setupParametresSubTabsFixed === 'function') {
            console.log('✅ setupParametresSubTabsFixed found, calling it');
            setupParametresSubTabsFixed();
        } else {
            console.error('❌ setupParametresSubTabsFixed function not available!');
        }
    }

    setupTiersTab() {
        console.log('🔍 DEBUG: Setting up Tiers tab');
        this.activateTab('#tiers');

        if (typeof setupTiersSubTabsFixed === 'function') {
            console.log('✅ setupTiersSubTabsFixed found, calling it');
            setupTiersSubTabsFixed();
        } else {
            console.error('❌ setupTiersSubTabsFixed function not available!');
        }
    }

    setupProduitsServicesTab() {
        console.log('🔍 DEBUG: Setting up Produits & Services tab');
        this.activateTab('#produits-services');

        // Afficher le message de placeholder pour le moment
        this.showTabMessage('#produits-services', 'Produits & Services', 'Gestion des produits et services. Fonctionnalité en cours de développement.');
    }

    setupTemplatesTab() {
        console.log('🔍 DEBUG: Setting up Templates tab');
        this.activateTab('#templates-content');

        const templatesSection = document.querySelector('#templates-content .admin-section');
        if (templatesSection && window.adminRoutes && window.adminRoutes.templates) {
            window.adminAjaxLoader.loadContentIntoElementFixed(
                window.adminRoutes.templates,
                templatesSection,
                '#templates-content'
            );
        } else {
            console.log('⚠️ Route templates non configurée, affichage du contenu par défaut');
            this.showTabMessage('#templates-content', 'Gestion des Templates', 'Interface de gestion des templates en développement.');
        }
    }

    showTabMessage(tabId, title, message) {
        const tabPane = document.querySelector(tabId);
        if (tabPane) {
            const adminSection = tabPane.querySelector('.admin-section');
            if (adminSection) {
                adminSection.innerHTML = `
                    <h3 class="section-title">
                        <i class="fas fa-info-circle me-2"></i>${title}
                    </h3>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        ${message}
                    </div>
                `;
            }
        }
    }
}

// Export for global use
window.AdminTabManager = AdminTabManager;