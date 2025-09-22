// Debug de l'event listener sur le changement de client
console.clear();
console.log('%c🔍 DEBUG EVENT LISTENER CLIENT', 'color: orange; font-size: 16px; font-weight: bold');
console.log('='.repeat(60));

// 1. Vérifier l'état du service
console.group('1️⃣ ÉTAT DU SERVICE');
console.log('window.DevisContactService:', typeof window.DevisContactService);
console.log('window.devisContactService:', typeof window.devisContactService);

if (window.devisContactService) {
    console.log('Service actif:', window.devisContactService.active);
    console.log('Mode:', window.devisContactService.mode);
    console.log('Debug:', window.devisContactService.debug);
} else {
    console.error('❌ Aucune instance devisContactService trouvée');
}
console.groupEnd();

// 2. Vérifier les sélecteurs
console.group('2️⃣ SÉLECTEURS CLIENT');
const selecteurs = ['devis_client', 'prospect', 'client_selector'];
let clientElement = null;

selecteurs.forEach(id => {
    const element = document.getElementById(id);
    console.log(`#${id}:`, element ? '✅ Présent' : '❌ Absent');
    if (element && !clientElement) {
        clientElement = element;
        console.log(`  → Utilisation de #${id} pour les tests`);
    }
});
console.groupEnd();

// 3. Vérifier les event listeners
console.group('3️⃣ EVENT LISTENERS');
if (clientElement) {
    // Récupérer les event listeners (méthode non-standard mais utile pour debug)
    const listeners = getEventListeners ? getEventListeners(clientElement) : null;
    
    if (listeners) {
        console.log('Event listeners détectés:', Object.keys(listeners));
        if (listeners.change) {
            console.log(`Listeners 'change': ${listeners.change.length}`);
            listeners.change.forEach((listener, i) => {
                console.log(`  ${i + 1}:`, listener.listener.toString().substring(0, 100) + '...');
            });
        } else {
            console.warn('❌ Aucun listener "change" détecté');
        }
    } else {
        console.warn('⚠️ getEventListeners non disponible (normal dans certains navigateurs)');
    }
    
    // Test manuel d'attachement
    console.log('📝 Test manuel d\'event listener...');
    
    // Sauvegarder la valeur actuelle
    const valeurInitiale = clientElement.value;
    console.log('Valeur client actuelle:', valeurInitiale);
    
    // Attacher un listener de test
    const testListener = function(e) {
        console.log('%c🎯 TEST LISTENER DÉCLENCHÉ !', 'color: green; font-weight: bold');
        console.log('Nouveau client ID:', e.target.value);
        console.log('Event:', e);
        
        // Vérifier si DevisContactService réagit
        setTimeout(() => {
            if (window.devisContactService && window.devisContactService.logger) {
                const logs = window.devisContactService.logger.export();
                console.log('📋 Logs récents du service:');
                logs.slice(-5).forEach(log => console.log(`  ${log.level}: ${log.message}`));
            }
        }, 1000);
    };
    
    clientElement.addEventListener('change', testListener);
    console.log('✅ Listener de test attaché');
    
    // Simuler un changement
    if (clientElement.options.length > 2) {
        const nouvelleValeur = clientElement.options[2].value;
        console.log(`🔄 Simulation changement vers: ${nouvelleValeur}`);
        
        clientElement.value = nouvelleValeur;
        const event = new Event('change', { bubbles: true });
        clientElement.dispatchEvent(event);
        
        // Remettre la valeur d'origine après le test
        setTimeout(() => {
            clientElement.value = valeurInitiale;
            clientElement.dispatchEvent(new Event('change', { bubbles: true }));
            console.log('🔄 Valeur restaurée');
        }, 2000);
    }
    
} else {
    console.error('❌ Aucun sélecteur client trouvé');
}
console.groupEnd();

// 4. Vérifier les APIs
console.group('4️⃣ TEST API');
if (clientElement && clientElement.value) {
    const clientId = clientElement.value;
    console.log(`🌐 Test API pour client ${clientId}...`);
    
    // Test contacts
    fetch(`/client/${clientId}/contacts`)
        .then(response => {
            console.log(`API contacts - Status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log(`✅ Contacts API: ${data.length} résultats`);
        })
        .catch(err => {
            console.error('❌ Erreur API contacts:', err);
        });
    
    // Test adresses
    fetch(`/client/${clientId}/addresses`)
        .then(response => {
            console.log(`API adresses - Status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log(`✅ Adresses API: ${data.length} résultats`);
        })
        .catch(err => {
            console.error('❌ Erreur API adresses:', err);
        });
}
console.groupEnd();

console.log('='.repeat(60));
console.log('%c🏁 DEBUG TERMINÉ', 'color: orange; font-weight: bold');

// Export pour usage manuel
window.debugEventListener = () => {
    const script = document.createElement('script');
    script.src = '/debug-event-listener.js?t=' + Date.now();
    document.head.appendChild(script);
};