/**
 * Service de debugging pour les modales - Système de logs détaillés
 */
class ModalDebugService {
    constructor() {
        this.logs = [];
        this.startTime = Date.now();
        this.modalStack = [];
        this.eventHistory = [];
        
        this.log('🚀 ModalDebugService initialisé');
        this.setupModalMonitoring();
        this.setupEventMonitoring();
    }
    
    /**
     * Log avec timestamp précis
     */
    log(message, data = null) {
        const timestamp = Date.now() - this.startTime;
        const logEntry = {
            time: timestamp,
            message: message,
            data: data,
            stack: new Error().stack.split('\n')[2]?.trim()
        };
        
        this.logs.push(logEntry);
        console.log(`[${timestamp}ms] 🔍 ${message}`, data || '');
        
        // Garder seulement les 100 derniers logs
        if (this.logs.length > 100) {
            this.logs.shift();
        }
    }
    
    /**
     * Monitorer toutes les modales du DOM
     */
    setupModalMonitoring() {
        // Observer les changements dans le DOM pour détecter les modales
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1 && node.classList?.contains('modal')) {
                            this.onModalAdded(node);
                        }
                        if (node.nodeType === 1 && node.classList?.contains('modal-backdrop')) {
                            this.onBackdropAdded(node);
                        }
                    });
                    
                    mutation.removedNodes.forEach((node) => {
                        if (node.nodeType === 1 && node.classList?.contains('modal')) {
                            this.onModalRemoved(node);
                        }
                        if (node.nodeType === 1 && node.classList?.contains('modal-backdrop')) {
                            this.onBackdropRemoved(node);
                        }
                    });
                }
                
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const target = mutation.target;
                    if (target.classList?.contains('modal')) {
                        this.onModalClassChanged(target);
                    }
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
        
        this.log('✅ Modal monitoring configuré');
    }
    
    /**
     * Monitorer les événements clés
     */
    setupEventMonitoring() {
        // Monitorer les événements de navigation
        window.addEventListener('addressCreated', (e) => {
            this.logEvent('addressCreated', e.detail);
        });
        
        window.addEventListener('addressUpdated', (e) => {
            this.logEvent('addressUpdated', e.detail);
        });
        
        window.addEventListener('contactCreated', (e) => {
            this.logEvent('contactCreated', e.detail);
        });
        
        window.addEventListener('contactUpdated', (e) => {
            this.logEvent('contactUpdated', e.detail);
        });
        
        // Monitorer les clics sur boutons modaux
        document.addEventListener('click', (e) => {
            if (e.target.id === 'add-address-btn' || 
                e.target.id === 'edit-address-btn' ||
                e.target.closest('#add-address-btn') ||
                e.target.closest('#edit-address-btn')) {
                this.logEvent('CLICK_ADDRESS_BUTTON', {
                    buttonId: e.target.id || e.target.closest('[id]')?.id,
                    target: e.target.outerHTML
                });
            }
        });
        
        this.log('✅ Event monitoring configuré');
    }
    
    /**
     * Détecter ajout de modale
     */
    onModalAdded(modalElement) {
        const modalId = modalElement.id || 'modal-' + Date.now();
        const modalInfo = {
            id: modalId,
            classes: Array.from(modalElement.classList),
            zIndex: window.getComputedStyle(modalElement).zIndex,
            content: modalElement.querySelector('.modal-title')?.textContent || 'Unknown'
        };
        
        this.modalStack.push(modalInfo);
        this.log(`➕ MODALE AJOUTÉE`, modalInfo);
        this.logModalState();
    }
    
    /**
     * Détecter suppression de modale
     */
    onModalRemoved(modalElement) {
        const modalId = modalElement.id;
        this.modalStack = this.modalStack.filter(m => m.id !== modalId);
        this.log(`➖ MODALE SUPPRIMÉE`, { id: modalId });
        this.logModalState();
    }
    
    /**
     * Détecter changement de classe de modale
     */
    onModalClassChanged(modalElement) {
        const modalId = modalElement.id;
        const classes = Array.from(modalElement.classList);
        const isVisible = classes.includes('show');
        
        this.log(`🔄 MODALE CLASSE CHANGÉE`, {
            id: modalId,
            classes: classes,
            visible: isVisible,
            zIndex: window.getComputedStyle(modalElement).zIndex
        });
        
        this.logModalState();
    }
    
    /**
     * Détecter ajout de backdrop
     */
    onBackdropAdded(backdropElement) {
        this.log(`🎭 BACKDROP AJOUTÉ`, {
            classes: Array.from(backdropElement.classList),
            zIndex: window.getComputedStyle(backdropElement).zIndex
        });
    }
    
    /**
     * Détecter suppression de backdrop
     */
    onBackdropRemoved(backdropElement) {
        this.log(`🎭 BACKDROP SUPPRIMÉ`, {
            classes: Array.from(backdropElement.classList)
        });
    }
    
    /**
     * Logger un événement
     */
    logEvent(eventName, detail) {
        const eventInfo = {
            name: eventName,
            detail: detail,
            timestamp: Date.now()
        };
        
        this.eventHistory.push(eventInfo);
        this.log(`🎪 ÉVÉNEMENT: ${eventName}`, detail);
        
        // Garder seulement les 50 derniers événements
        if (this.eventHistory.length > 50) {
            this.eventHistory.shift();
        }
    }
    
    /**
     * Logger l'état actuel des modales
     */
    logModalState() {
        const allModals = document.querySelectorAll('.modal');
        const visibleModals = document.querySelectorAll('.modal.show');
        const backdrops = document.querySelectorAll('.modal-backdrop');
        
        this.log(`📊 ÉTAT MODALES`, {
            totalModals: allModals.length,
            visibleModals: visibleModals.length,
            backdrops: backdrops.length,
            modalStack: this.modalStack.length,
            lastContactEvent: window.lastContactEditEvent
        });
        
        // Détailler chaque modale visible
        visibleModals.forEach((modal, index) => {
            this.log(`  📋 Modale ${index + 1}`, {
                id: modal.id,
                title: modal.querySelector('.modal-title')?.textContent,
                zIndex: window.getComputedStyle(modal).zIndex,
                classes: Array.from(modal.classList)
            });
        });
    }
    
    /**
     * Générer un rapport complet
     */
    generateReport() {
        this.log('📈 GÉNÉRATION RAPPORT DEBUG');
        
        const report = {
            summary: {
                totalLogs: this.logs.length,
                totalEvents: this.eventHistory.length,
                currentModals: document.querySelectorAll('.modal').length,
                visibleModals: document.querySelectorAll('.modal.show').length,
                backdrops: document.querySelectorAll('.modal-backdrop').length
            },
            currentState: {
                modals: Array.from(document.querySelectorAll('.modal')).map(modal => ({
                    id: modal.id,
                    classes: Array.from(modal.classList),
                    visible: modal.classList.contains('show'),
                    title: modal.querySelector('.modal-title')?.textContent,
                    zIndex: window.getComputedStyle(modal).zIndex
                })),
                lastContactEvent: window.lastContactEditEvent,
                navigationStack: window.multiModalNavigationService?.navigationStack || []
            },
            recentLogs: this.logs.slice(-20),
            recentEvents: this.eventHistory.slice(-10)
        };
        
        console.group('🔍 RAPPORT DEBUG MODAL');
        console.log('📊 Résumé:', report.summary);
        console.log('🎯 État actuel:', report.currentState);
        console.log('📝 Logs récents:', report.recentLogs);
        console.log('🎪 Événements récents:', report.recentEvents);
        console.groupEnd();
        
        return report;
    }
    
    /**
     * Détecter les problèmes communs
     */
    detectIssues() {
        const issues = [];
        
        const visibleModals = document.querySelectorAll('.modal.show');
        const backdrops = document.querySelectorAll('.modal-backdrop');
        
        // Problème : Plusieurs modales visibles
        if (visibleModals.length > 1) {
            issues.push({
                type: 'MULTIPLE_MODALS',
                severity: 'HIGH',
                message: `${visibleModals.length} modales visibles simultanément`,
                modals: Array.from(visibleModals).map(m => ({
                    id: m.id,
                    title: m.querySelector('.modal-title')?.textContent
                }))
            });
        }
        
        // Problème : Trop de backdrops
        if (backdrops.length > visibleModals.length) {
            issues.push({
                type: 'ORPHANED_BACKDROPS',
                severity: 'MEDIUM',
                message: `${backdrops.length} backdrops pour ${visibleModals.length} modales`,
                backdrops: backdrops.length
            });
        }
        
        // Problème : Z-index incorrect
        const modalZIndexes = Array.from(visibleModals).map(m => 
            parseInt(window.getComputedStyle(m).zIndex)
        );
        if (modalZIndexes.some(z => z <= 1050)) {
            issues.push({
                type: 'LOW_ZINDEX',
                severity: 'MEDIUM',
                message: 'Modales avec z-index trop bas',
                zIndexes: modalZIndexes
            });
        }
        
        if (issues.length > 0) {
            this.log('❌ PROBLÈMES DÉTECTÉS', issues);
        } else {
            this.log('✅ Aucun problème détecté');
        }
        
        return issues;
    }
}

// Initialiser automatiquement
window.modalDebugService = new ModalDebugService();

// Rendre disponible globalement pour debug manuel
window.ModalDebugService = ModalDebugService;