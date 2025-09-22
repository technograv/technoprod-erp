// Diagnostic complet pour /devis/new
console.clear();
console.log('%c🔍 DIAGNOSTIC /devis/new', 'color: orange; font-size: 20px; font-weight: bold');
console.log('=====================================');

// 1. Analyser la structure DOM
console.group('1️⃣ STRUCTURE DOM');
const elements = {
    'client_selector': document.getElementById('client_selector'),
    'client-selection': document.getElementById('client-selection'),
    'contact-selection': document.getElementById('contact-selection'),
    'contact_defaut': document.getElementById('contact_defaut'),
    'contact_facturation': document.getElementById('contact_facturation'),
    'adresse_livraison_select': document.getElementById('adresse_livraison_select'),
    'adresse_facturation_select': document.getElementById('adresse_facturation_select'),
    'email_envoi_automatique': document.getElementById('email_envoi_automatique')
};

Object.entries(elements).forEach(([name, el]) => {
    if (el) {
        const isVisible = el.style.display !== 'none' && 
                         !el.closest('[style*="display: none"]') &&
                         el.offsetParent !== null;
        console.log(`✅ ${name}: TROUVÉ (${isVisible ? 'VISIBLE' : 'CACHÉ'})`);
        if (!isVisible) {
            console.log(`   Style: ${el.style.display || 'default'}`);
            console.log(`   Parent caché: ${el.closest('[style*="display: none"]') ? 'OUI' : 'NON'}`);
        }
    } else {
        console.error(`❌ ${name}: MANQUANT`);
    }
});
console.groupEnd();

// 2. Vérifier les services JavaScript
console.group('2️⃣ SERVICES JAVASCRIPT');
const services = {
    'DevisContactService (classe)': window.DevisContactService,
    'devisContactService (instance)': window.devisContactService,
    'jQuery': window.$,
    'DebugLogger': window.DebugLogger,
    'globalDebugLogger': window.globalDebugLogger
};

Object.entries(services).forEach(([name, service]) => {
    if (service) {
        console.log(`✅ ${name}: DISPONIBLE`);
        if (name === 'devisContactService (instance)' && service.isInitialized !== undefined) {
            console.log(`   Initialisé: ${service.isInitialized}`);
            console.log(`   Mode: ${service.getMode ? service.getMode() : 'N/A'}`);
        }
    } else {
        console.warn(`⚠️ ${name}: NON DISPONIBLE`);
    }
});
console.groupEnd();

// 3. Tester les event listeners
console.group('3️⃣ EVENT LISTENERS');
const clientSelector = document.getElementById('client_selector');
if (clientSelector) {
    // Vérifier jQuery events
    if (window.$ && window.$._data) {
        const jqEvents = $._data(clientSelector, 'events');
        if (jqEvents && jqEvents.change) {
            console.log(`✅ jQuery change handlers: ${jqEvents.change.length}`);
            jqEvents.change.forEach((handler, i) => {
                console.log(`   Handler ${i+1}: ${handler.namespace || 'no-namespace'}`);
            });
        } else {
            console.warn('⚠️ Aucun jQuery change handler trouvé');
        }
    }
    
    // Vérifier native events
    const nativeEvents = clientSelector.onclick || clientSelector.onchange;
    console.log(`Native events: ${nativeEvents ? 'PRÉSENTS' : 'ABSENTS'}`);
} else {
    console.error('❌ Impossible de vérifier les events - client_selector manquant');
}
console.groupEnd();

// 4. Test de sélection
console.group('4️⃣ TEST SÉLECTION');
if (clientSelector && clientSelector.options.length > 1) {
    // Trouver une option valide
    let testOption = null;
    for (let i = 1; i < clientSelector.options.length; i++) {
        if (clientSelector.options[i].value) {
            testOption = clientSelector.options[i];
            break;
        }
    }
    
    if (testOption) {
        console.log(`🔄 Test avec option: ${testOption.value} = ${testOption.text}`);
        
        // Logger les changements
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    console.log(`   DOM change: ${mutation.target.id} style="${mutation.target.style.cssText}"`);
                }
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    Array.from(mutation.addedNodes).forEach(node => {
                        if (node.nodeType === 1) { // Element
                            console.log(`   DOM add: ${node.tagName} ${node.id ? '#' + node.id : ''}`);
                        }
                    });
                }
            });
        });
        
        // Observer tous les éléments importants
        Object.values(elements).forEach(el => {
            if (el) observer.observe(el, { attributes: true, childList: true, subtree: true });
        });
        
        // Déclencher le changement
        clientSelector.value = testOption.value;
        console.log(`   Valeur définie: ${clientSelector.value}`);
        
        // Déclencher les événements
        clientSelector.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.$) {
            $(clientSelector).trigger('change');
        }
        
        // Arrêter l'observation après 3 secondes
        setTimeout(() => {
            observer.disconnect();
            console.log('📊 RÉSULTATS FINAUX:');
            
            // Vérifier l'état final
            const contactSection = document.getElementById('contact-selection');
            const contactDefaut = document.getElementById('contact_defaut');
            const emailField = document.getElementById('email_envoi_automatique');
            
            console.log(`   Section contacts visible: ${contactSection && contactSection.style.display !== 'none'}`);
            console.log(`   Options contact livraison: ${contactDefaut ? contactDefaut.options.length : 'N/A'}`);
            console.log(`   Email synchronisé: ${emailField && emailField.value ? emailField.value : 'VIDE'}`);
            
            // Debug DevisContactService
            if (window.devisContactService) {
                console.log(`   Service état: ${window.devisContactService.isInitialized ? 'INITIALISÉ' : 'NON INITIALISÉ'}`);
            }
            
            console.groupEnd();
        }, 3000);
        
    } else {
        console.warn('⚠️ Aucune option valide pour tester');
        console.groupEnd();
    }
} else {
    console.error('❌ Impossible de tester - sélecteur manquant ou vide');
    console.groupEnd();
}

console.log('⏱️ Observation en cours pendant 3 secondes...');
console.log('=====================================');