/**
 * TechnoProd Admin Dashboard - JavaScript Module
 * Version: 2.1
 * Extracted from templates for better maintenance and performance
 */

class AdminDashboard {
    constructor() {
        this.tabManager = null;
        this.ajaxLoader = null;
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🔍 DEBUG: DOM Content Loaded - Admin Dashboard Initialized');
            console.log('🔍 DEBUG: Bootstrap available:', typeof bootstrap !== 'undefined');
            console.log('🔍 DEBUG: Main admin tabs found:', document.querySelectorAll('#main-admin-tabs [data-bs-toggle="tab"]').length);
            console.log('🔍 DEBUG: Pill tabs found:', document.querySelectorAll('[data-bs-toggle="pill"]').length);
            console.log('🔍 DEBUG: All tab elements:', document.querySelectorAll('[data-bs-toggle]').length);
            
            // Wait for all modules to be loaded
            this.waitForModules().then(() => {
                this.initializeModules();
                this.initializeTabSystem();
                this.setupDebugLogging();
                this.cleanupParasiteContent();
                
                console.log('✅ TechnoProd Admin Dashboard fully initialized');
            });
        });
    }

    async waitForModules() {
        // Wait for modules to be available
        let attempts = 0;
        while (attempts < 50) { // Max 5 seconds wait
            if (window.AdminTabManager && window.AdminAjaxLoader && 
                typeof setupJuridiqueComptableTabFixed === 'function' && 
                typeof setupSocietesSubTabsFixed === 'function') {
                break;
            }
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
        
        if (attempts >= 50) {
            console.error('❌ Admin modules failed to load in time');
            console.error('❌ AdminTabManager available:', !!window.AdminTabManager);
            console.error('❌ AdminAjaxLoader available:', !!window.AdminAjaxLoader);
            console.error('❌ setupJuridiqueComptableTabFixed available:', typeof setupJuridiqueComptableTabFixed === 'function');
            console.error('❌ setupSocietesSubTabsFixed available:', typeof setupSocietesSubTabsFixed === 'function');
        } else {
            console.log('✅ All modules loaded successfully after', attempts, 'attempts');
        }
    }

    initializeModules() {
        // Initialize the modules
        this.ajaxLoader = new window.AdminAjaxLoader();
        this.tabManager = new window.AdminTabManager();
        
        // Make globally accessible for legacy code
        window.adminAjaxLoader = this.ajaxLoader;
        window.adminTabManager = this.tabManager;
        
        // Bind the loadTabContent function to use our tab manager
        this.tabManager.loadTabContent = this.tabManager.loadTabContent.bind(this.tabManager);
        
        console.log('🔧 Admin modules initialized successfully');
    }

    cleanupParasiteContent() {
        // CORRECTION: Masquer immédiatement tout le contenu parasite au chargement, sauf premier onglet actif
        setTimeout(() => {
            document.querySelectorAll('.tab-pane:not(#gestion-societes)').forEach(pane => {
                if (!pane.id.includes('-content')) { // Garder les sous-onglets
                    pane.classList.remove('show', 'active');
                    pane.style.display = 'none';
                    pane.style.visibility = 'hidden';
                    pane.style.opacity = '0';
                    console.log('🔍 DEBUG: Masqué onglet parasite:', pane.id);
                }
            });
        }, 500);
    }

    setupDebugLogging() {
        // Log tous les clics sur les onglets pour debug
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-bs-toggle="tab"], [data-bs-toggle="pill"]')) {
                console.log('🔍 DEBUG: Tab clicked:', e.target.getAttribute('href'), 'Type:', e.target.getAttribute('data-bs-toggle'));
            }
        });
    }

    initializeTabSystem() {
        // Gérer le chargement AJAX des onglets PRINCIPAUX seulement
        document.querySelectorAll('#main-admin-tabs [data-bs-toggle="tab"]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const targetTab = e.target.getAttribute('href');
                console.log('🔄 Main tab switched to:', targetTab);
                if (this.tabManager) {
                    this.tabManager.loadTabContent(targetTab);
                }
            });
        });

        // Gérer les sous-onglets avec chargement conditionnel
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach((pill) => {
            pill.addEventListener('shown.bs.tab', (e) => {
                const targetTab = e.target.getAttribute('href');
                console.log('🔄 Sub-tab switched to:', targetTab);

                // Certains sous-onglets nécessitent un chargement spécial
                if (targetTab === '#templates-documents-content' ||
                    targetTab === '#themes-couleurs-content') {
                    if (this.tabManager) {
                        this.tabManager.loadTabContent(targetTab);
                    }
                }
            });
        });

        console.log('🔧 Tab system initialized with', document.querySelectorAll('#main-admin-tabs [data-bs-toggle="tab"]').length, 'main tabs');

        // Charger automatiquement le premier onglet actif au démarrage
        setTimeout(() => {
            const activeTab = document.querySelector('#main-admin-tabs .nav-link.active');
            if (activeTab && this.tabManager) {
                const targetTab = activeTab.getAttribute('href');
                console.log('🔄 Loading initial active tab:', targetTab);
                this.tabManager.loadTabContent(targetTab);
            }
        }, 100);
    }
}

// Initialize Dashboard when DOM is ready
const adminDashboard = new AdminDashboard();