// Test spécifique pour Select2 et event listeners
console.clear();
console.log('%c🔍 TEST SELECT2 EVENT LISTENER', 'color: red; font-size: 16px; font-weight: bold');
console.log('='.repeat(60));

// 1. Vérifier l'état actuel
console.group('1️⃣ ÉTAT ACTUEL');
console.log('window.devisContactService:', typeof window.devisContactService);
if (window.devisContactService) {
    console.log('Service actif:', window.devisContactService.active);
    console.log('Instance OK ✅');
} else {
    console.error('Service non disponible ❌');
}

const clientSelect = document.getElementById('devis_client');
console.log('Select client:', clientSelect ? '✅' : '❌');
console.log('Select2 actif:', clientSelect && clientSelect.classList.contains('select2-hidden-accessible') ? '✅' : '❌');
console.log('Valeur actuelle:', clientSelect?.value);

// Compter les options
if (clientSelect) {
    console.log('Nombre d\'options:', clientSelect.options.length);
    console.log('Option 1:', clientSelect.options[1]?.text || 'N/A');
    console.log('Option 2:', clientSelect.options[2]?.text || 'N/A');
}
console.groupEnd();

// 2. Test avec event listener direct (sans Select2)
console.group('2️⃣ TEST EVENT LISTENER DIRECT');
if (clientSelect) {
    let changeDetected = false;
    
    // Listener de test
    const testListener = function(e) {
        changeDetected = true;
        console.log('%c🎯 CHANGEMENT DÉTECTÉ (direct)!', 'color: green; font-weight: bold');
        console.log('Ancien:', e.detail?.oldValue || 'inconnu');
        console.log('Nouveau:', e.target.value);
        
        // Appeler manuellement le service
        if (window.devisContactService && window.devisContactService.handleClientChange) {
            console.log('🔄 Appel manuel du service...');
            window.devisContactService.handleClientChange(e.target.value);
        }
    };
    
    clientSelect.addEventListener('change', testListener);
    
    // Test direct sans Select2
    console.log('📝 Test changement direct (bypass Select2)...');
    const valeurOriginale = clientSelect.value;
    
    if (clientSelect.options.length > 2) {
        const nouvelleValeur = clientSelect.options[2].value;
        console.log(`Changement: ${valeurOriginale} → ${nouvelleValeur}`);
        
        clientSelect.value = nouvelleValeur;
        const event = new Event('change', { bubbles: true });
        clientSelect.dispatchEvent(event);
        
        setTimeout(() => {
            if (changeDetected) {
                console.log('✅ Event listener direct fonctionne!');
            } else {
                console.error('❌ Event listener direct ne fonctionne pas');
            }
            
            // Remettre la valeur d'origine
            clientSelect.value = valeurOriginale;
            clientSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }, 1000);
    }
} else {
    console.error('Sélecteur non trouvé');
}
console.groupEnd();

// 3. Test avec Select2
console.group('3️⃣ TEST SELECT2');
if (clientSelect && window.$ && $(clientSelect).data('select2')) {
    console.log('Select2 détecté ✅');
    
    // Listener Select2
    const select2Listener = function(e) {
        console.log('%c🎯 CHANGEMENT DÉTECTÉ (Select2)!', 'color: blue; font-weight: bold');
        console.log('Nouveau client:', e.params?.data?.id || e.target.value);
        
        // Appeler le service
        if (window.devisContactService && window.devisContactService.handleClientChange) {
            console.log('🔄 Appel service via Select2...');
            const clientId = e.params?.data?.id || e.target.value;
            window.devisContactService.handleClientChange(clientId);
        }
    };
    
    // Attacher à Select2
    $(clientSelect).on('select2:select', select2Listener);
    console.log('✅ Listener Select2 attaché');
    
    // Test Select2
    console.log('📝 Test avec Select2...');
    if (clientSelect.options.length > 2) {
        const nouvelleValeur = clientSelect.options[2].value;
        console.log(`Test Select2 avec valeur: ${nouvelleValeur}`);
        
        // Utiliser l'API Select2
        $(clientSelect).val(nouvelleValeur).trigger('change');
        
        setTimeout(() => {
            console.log('Select2 test terminé');
        }, 2000);
    }
} else {
    console.warn('Select2 non détecté ou jQuery non disponible');
}
console.groupEnd();

// 4. Diagnostic complet
console.group('4️⃣ DIAGNOSTIC');
console.log('Tous les event listeners sur le select:');
if (window.getEventListeners) {
    const listeners = getEventListeners(clientSelect);
    console.log(listeners);
} else {
    console.log('getEventListeners non disponible (normal dans certains navigateurs)');
}

// Vérifier si le service a bien attaché son listener
if (window.devisContactService && window.devisContactService.eventListeners) {
    console.log('Event listeners du service:', window.devisContactService.eventListeners);
}
console.groupEnd();

console.log('='.repeat(60));
console.log('%c🏁 TEST TERMINÉ', 'color: red; font-weight: bold');
console.log('💡 Maintenant teste manuellement le changement de client dans l\'interface');

// Export
window.testSelect2Event = () => {
    const script = document.createElement('script');
    script.src = '/test-select2-event.js?t=' + Date.now();
    document.head.appendChild(script);
};