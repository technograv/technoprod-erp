/**
 * TechnoProd Admin Legacy Functions
 * Functions that are still needed for backward compatibility
 * These will be gradually refactored or removed
 */

// Wait for dashboard to be initialized, then set up legacy functions
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 DEBUG: Legacy functions DOM Content Loaded');
    
    // Make legacy functions available immediately (they don't depend on dashboard modules)
    window.setupJuridiqueComptableTabFixed = setupJuridiqueComptableTabFixed;
    window.setupSocietesSubTabsFixed = setupSocietesSubTabsFixed;
    console.log('✅ Legacy functions globally available');
    
    // Wait for dashboard initialization to complete for wrapper functions
    function waitForDashboard() {
        if (window.adminAjaxLoader && window.adminTabManager) {
            console.log('✅ Dashboard modules found, setting up legacy wrapper functions');
            setupLegacyFunctions();
        } else {
            console.log('⏳ Waiting for dashboard modules...');
            setTimeout(waitForDashboard, 100);
        }
    }
    
    // Start waiting after a short delay to let dashboard.js initialize first
    setTimeout(waitForDashboard, 200);
});

function setupLegacyFunctions() {
    // Replace legacy loadTabContent function
    window.loadTabContent = function(tabId) {
        console.log('🔄 Legacy loadTabContent called for:', tabId);
        return window.adminTabManager.loadTabContent(tabId);
    };
    
    // Replace legacy functions
    window.loadContentIntoElementFixed = function(url, targetElement, tabId) {
        return window.adminAjaxLoader.loadContentIntoElementFixed(url, targetElement, tabId);
    };
    
    window.activateSubTabContent = function(targetElement, tabId) {
        return window.adminAjaxLoader.activateSubTabContent(targetElement, tabId);
    };
    
    window.showNotification = function(type, message) {
        return window.adminAjaxLoader.showNotification(type, message);
    };
    
    console.log('✅ Legacy functions setup completed');
}

// Legacy functions that still need to be preserved
// These are the complex functions from the original dashboard

// Setup functions for specialized tabs
function setupJuridiqueComptableTabFixed() {
    console.log('🔧 setupJuridiqueComptableTabFixed: Starting setup');
    
    const juridiquePane = document.querySelector('#juridique-comptable');
    if (juridiquePane) {
        // Masquer tous les autres onglets principaux
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
            pane.style.display = 'none';
            pane.style.visibility = 'hidden';
            pane.style.opacity = '0';
        });
        
        // Activer l'onglet juridique-comptable
        juridiquePane.classList.add('show', 'active');
        juridiquePane.style.display = 'block';
        juridiquePane.style.visibility = 'visible';
        juridiquePane.style.opacity = '1';
        
        console.log('✅ Juridique-comptable tab activated, setting up sub-tabs');
        
        setTimeout(() => {
            setupJuridiqueComptableSubTabs();
        }, 100);
    } else {
        console.error('❌ ERROR: #juridique-comptable not found!');
    }
}

function setupJuridiqueComptableSubTabs() {
    // Configure sub-tab event listeners
    const juridiqueSubTabs = document.querySelectorAll('#juridique-sub-tabs .nav-link');
    console.log('🔧 Found', juridiqueSubTabs.length, 'juridique sub-tabs to configure');
    
    juridiqueSubTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const targetSubTab = e.target.getAttribute('href');
            console.log('🔧 Juridique sub-tab clicked:', targetSubTab);
            loadJuridiqueComptableSubTabFixed(targetSubTab);
        });
    });
    
    // Load default sub-tab
    console.log('🔧 Loading default juridique sub-tab: #formes-juridiques-content');
    loadJuridiqueComptableSubTabFixed('#formes-juridiques-content');
}

function loadJuridiqueComptableSubTabFixed(subTabId) {
    console.log('🔍 Loading juridique-comptable sub-tab (Fixed version):', subTabId);
    
    // Désactiver tous les autres sous-onglets
    document.querySelectorAll('#juridique-comptable .tab-content .tab-pane').forEach(pane => {
        pane.classList.remove('show', 'active');
        pane.style.display = 'none';
    });
    
    // Activer l'onglet de navigation
    document.querySelectorAll('#juridique-sub-tabs .nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    const navLink = document.querySelector('#juridique-sub-tabs .nav-link[href="' + subTabId + '"]');
    if (navLink) {
        navLink.classList.add('active');
    }
    
    let url = '';
    let targetContentId = '';
    
    switch(subTabId) {
        case '#formes-juridiques-content':
            url = window.adminRoutes?.formes_juridiques || '/admin/formes-juridiques';
            targetContentId = '#formes-juridiques-content';
            break;
        case '#moyens-paiement-content':
            url = window.adminRoutes?.modes_paiement || '/admin/modes-paiement';
            targetContentId = '#moyens-paiement-content';
            break;
        case '#modes-reglement-content':
            url = window.adminRoutes?.modes_reglement || '/admin/modes-reglement';
            targetContentId = '#modes-reglement-content';
            break;
        case '#banques-content':
            url = window.adminRoutes?.banques || '/admin/banques';
            targetContentId = '#banques-content';
            break;
        case '#taux-tva-content':
            url = window.adminRoutes?.taux_tva || '/admin/taux-tva';
            targetContentId = '#taux-tva-content';
            break;
    }
    
    // Activer l'élément cible avec CSS forcing
    const targetElement = document.querySelector(targetContentId);
    if (targetElement) {
        if (url && !targetElement.dataset.loaded) {
            console.log('🔍 DEBUG: Loading content into', targetContentId, 'from URL:', url);
            window.adminAjaxLoader.loadContentIntoElementFixed(url, targetElement, subTabId);
            targetElement.dataset.loaded = 'true';
        } else if (targetElement && targetElement.dataset.loaded) {
            console.log('🔍 DEBUG: Content already loaded for', subTabId, '- activating display');
            window.adminAjaxLoader.activateSubTabContent(targetElement, subTabId);
        }
    }
}

function setupSocietesSubTabsFixed() {
    console.log('🔧 setupSocietesSubTabsFixed: Starting setup');
    
    const gestionSocietesTabs = document.querySelectorAll('#societes-sub-tabs .nav-link');
    console.log('🔧 Found', gestionSocietesTabs.length, 'sub-tabs to configure');
    
    gestionSocietesTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const targetSubTab = e.target.getAttribute('href');
            console.log('🔧 Sub-tab clicked:', targetSubTab);
            loadGestionSocietesSubTabFixed(targetSubTab);
        });
    });
    
    console.log('🔧 Loading default sub-tab: #societes-content');
    loadGestionSocietesSubTabFixed('#societes-content');
}

function loadGestionSocietesSubTabFixed(subTabId) {
    console.log('🔍 Loading gestion-societes sub-tab with CSS forcing:', subTabId);
    
    // Désactiver tous les autres sous-onglets
    document.querySelectorAll('#gestion-societes .tab-content .tab-pane').forEach(pane => {
        pane.classList.remove('show', 'active');
        pane.style.display = 'none';
    });
    
    // Activer l'onglet de navigation
    document.querySelectorAll('#societes-sub-tabs .nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    const navLink = document.querySelector('#societes-sub-tabs .nav-link[href="' + subTabId + '"]');
    if (navLink) {
        navLink.classList.add('active');
    }
    
    let url = '';
    let targetContentId = '';
    
    switch(subTabId) {
        case '#societes-content':
            url = window.adminRoutes?.societes || '/admin/societes';
            targetContentId = '#societes-content';
            break;
        case '#users-content':
            url = window.adminRoutes?.users || '/admin/users';
            targetContentId = '#users-content';
            break;
        case '#groupes-users-content':
            url = window.adminRoutes?.groupes_utilisateurs || '/admin/groupes-utilisateurs';
            targetContentId = '#groupes-users-content';
            break;
        case '#themes-couleurs-content':
            targetContentId = '#themes-couleurs-content';
            console.log('🎨 Initializing themes-couleurs-content tab');
            const themesElement = document.querySelector(targetContentId);
            if (themesElement && !themesElement.dataset.loaded) {
                console.log('🎨 Calling initEnvironmentTab');
                if (typeof initEnvironmentTab === 'function') {
                    initEnvironmentTab();
                }
                themesElement.dataset.loaded = 'true';
            }
            break;
        case '#templates-documents-content':
            targetContentId = '#templates-documents-content';
            console.log('📄 Initializing templates-documents-content tab');
            const templatesElement = document.querySelector(targetContentId);
            if (templatesElement && !templatesElement.dataset.loaded) {
                console.log('📄 Calling initTemplatesTab');
                if (typeof loadTemplatesManagement === 'function') {
                    loadTemplatesManagement();
                }
                templatesElement.dataset.loaded = 'true';
            }
            break;
    }
    
    const targetElement = document.querySelector(targetContentId);
    if (targetElement) {
        if (url) {
            console.log('🔍 DEBUG: Loading content into', targetContentId, 'from URL:', url);
            console.log('🔍 DEBUG: Element loaded flag:', targetElement.dataset.loaded);
            window.adminAjaxLoader.loadContentIntoElementFixed(url, targetElement, subTabId);
            targetElement.dataset.loaded = 'true';
        } else {
            console.log('🔍 DEBUG: No URL provided, activating display for', subTabId);
            window.adminAjaxLoader.activateSubTabContent(targetElement, subTabId);
        }
    } else {
        console.error('❌ ERROR: Target element not found for:', targetContentId);
    }
}