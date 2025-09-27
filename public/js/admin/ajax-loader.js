/**
 * TechnoProd Admin AJAX Loader
 * Handles AJAX content loading with CSS forcing and error handling
 */

class AdminAjaxLoader {
    constructor() {
        this.timeoutDuration = 10000; // 10 seconds
    }

    loadContentIntoElementFixed(url, targetElement, tabId) {
        console.log('🔄 loadContentIntoElementFixed called with:', {url, targetElementId: targetElement.id, tabId});
        console.log('🔄 Starting fetch request to:', url);
        
        // Créer un AbortController pour gérer le timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => {
            console.log('⏰ Request timeout after 10 seconds');
            controller.abort();
        }, this.timeoutDuration);
        
        return fetch(url, {
                signal: controller.signal,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                clearTimeout(timeoutId);
                console.log('✅ Fetch response received:', response.status, response.statusText);
                console.log('🔄 Response headers:', Object.fromEntries(response.headers.entries()));
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return response.text();
            })
            .then(html => {
                console.log('✅ Received HTML length:', html.length, 'characters');
                console.log('🔄 HTML preview (first 500 chars):', html.substring(0, 500));
                
                if (html.length === 0) {
                    throw new Error('Empty response received');
                }
                
                targetElement.innerHTML = html;
                
                // Activer l'affichage avec forçage CSS
                this.activateSubTabContent(targetElement, tabId);
                
                // Exécuter les scripts dans le contenu chargé
                const scripts = targetElement.querySelectorAll('script');
                console.log('📜 Found', scripts.length, 'scripts to execute');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    if (script.src) {
                        newScript.src = script.src;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.head.appendChild(newScript);
                });

                // Appeler les fonctions d'initialisation spécifiques après chargement des scripts
                setTimeout(() => {
                    this.callInitFunctionIfExists(tabId, url);
                }, 100);

                console.log('✅ Content loaded successfully for:', tabId);
                return html;
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('❌ Error loading tab content:', error);
                console.error('❌ Error details:', {
                    message: error.message,
                    name: error.name,
                    url: url,
                    tabId: tabId
                });
                
                let errorMessage = 'Erreur lors du chargement du contenu';
                if (error.name === 'AbortError') {
                    errorMessage = 'Timeout: Le contenu met trop de temps à charger';
                } else if (error.message.includes('HTTP error')) {
                    errorMessage = `Erreur serveur: ${error.message}`;
                }
                
                targetElement.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${errorMessage}</div>`;
                
                // Activer l'affichage même en cas d'erreur
                this.activateSubTabContent(targetElement, tabId);
                
                throw error;
            });
    }

    activateSubTabContent(targetElement, tabId) {
        console.log('🔍 DEBUG: Activating sub-tab content for:', tabId, 'element:', targetElement?.id);
        
        // Déterminer le conteneur parent des sous-onglets
        let parentContainer = null;
        if (tabId.includes('juridique') || tabId.includes('formes-') || tabId.includes('moyens-') || 
            tabId.includes('modes-') || tabId.includes('banques-') || tabId.includes('taux-')) {
            parentContainer = '#juridique-comptable';
        } else if (tabId.includes('societes-') || tabId.includes('users-') || tabId.includes('groupes-') || 
                   tabId.includes('themes-') || tabId.includes('templates-')) {
            parentContainer = '#gestion-societes';
        }
        
        if (parentContainer) {
            // Désactiver tous les autres contenus de sous-onglets du même parent
            document.querySelectorAll(`${parentContainer} .tab-content .tab-pane`).forEach(pane => {
                pane.classList.remove('show', 'active');
                pane.style.display = 'none';
                pane.style.visibility = 'hidden';
                pane.style.opacity = '0';
            });
        }
        
        // Activer le contenu ciblé avec forçage CSS
        if (targetElement) {
            targetElement.classList.add('show', 'active');
            targetElement.style.display = 'block';
            targetElement.style.visibility = 'visible';
            targetElement.style.opacity = '1';
            console.log('🔍 DEBUG: Sub-tab content activated with forced CSS:', tabId);
        } else {
            console.error('🔍 ERROR: targetElement is null for tabId:', tabId);
        }
    }

    showNotification(type, message) {
        // Créer une notification toast simple
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas ${icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    callInitFunctionIfExists(tabId, url) {
        console.log('🔧 Checking for init function for:', tabId, url);

        // Mapping des onglets vers leurs fonctions d'initialisation
        const initFunctions = {
            '#societes-content': 'initSocietes',
            '/admin/societes': 'initSocietes'
        };

        // Chercher la fonction d'initialisation par tabId ou par URL
        let functionName = initFunctions[tabId] || initFunctions[url];

        if (functionName && typeof window[functionName] === 'function') {
            console.log('🚀 Calling init function:', functionName);
            try {
                window[functionName]();
                console.log('✅ Init function called successfully:', functionName);
            } catch (error) {
                console.error('❌ Error calling init function:', functionName, error);
            }
        } else {
            console.log('ℹ️ No init function found for:', tabId, url);
        }
    }
}

// Export for global use
window.AdminAjaxLoader = AdminAjaxLoader;