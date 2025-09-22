// Debug simple pour voir l'état du service
console.clear();
console.log('%c🔍 DEBUG SIMPLE', 'color: purple; font-size: 16px; font-weight: bold');

// 1. État des classes et instances
console.log('1️⃣ CLASSES ET INSTANCES:');
console.log('  window.DevisContactService:', typeof window.DevisContactService);
console.log('  window.devisContactService:', typeof window.devisContactService);
console.log('  window.clientProspectService:', typeof window.clientProspectService);

// 2. Essayer de créer une instance manuellement
console.log('\n2️⃣ CRÉATION MANUELLE:');
if (typeof window.DevisContactService === 'function') {
    try {
        console.log('  🔧 Tentative création instance...');
        const testService = new DevisContactService({ debug: true });
        console.log('  ✅ Instance créée avec succès!');
        console.log('  Service actif:', testService.active);
        
        // L'assigner globalement
        window.devisContactService = testService;
        console.log('  ✅ Instance assignée à window.devisContactService');
        
    } catch (error) {
        console.error('  ❌ Erreur création:', error);
    }
} else {
    console.error('  ❌ Classe DevisContactService non disponible');
}

// 3. Tester l'event listener manuellement
console.log('\n3️⃣ TEST EVENT LISTENER:');
const clientSelect = document.getElementById('devis_client');
if (clientSelect) {
    console.log('  ✅ Sélecteur client trouvé');
    console.log('  Valeur actuelle:', clientSelect.value);
    console.log('  Nombre d\'options:', clientSelect.options.length);
    
    // Ajouter un listener de test
    const testListener = (e) => {
        console.log('%c  🎯 CHANGEMENT DÉTECTÉ!', 'color: green; font-weight: bold');
        console.log('  Nouveau client:', e.target.value);
        
        // Appeler manuellement le service si disponible
        if (window.devisContactService && window.devisContactService.handleClientChange) {
            console.log('  🔄 Appel manuel handleClientChange...');
            window.devisContactService.handleClientChange(e.target.value);
        }
    };
    
    clientSelect.addEventListener('change', testListener);
    console.log('  ✅ Listener de test ajouté');
    
    // Test de changement si possible
    if (clientSelect.options.length > 2) {
        const nouvelleValeur = clientSelect.options[2].value;
        console.log(`  🔄 Test changement vers client ${nouvelleValeur}...`);
        
        setTimeout(() => {
            const valeurOriginale = clientSelect.value;
            clientSelect.value = nouvelleValeur;
            clientSelect.dispatchEvent(new Event('change', { bubbles: true }));
            
            // Remettre l'original après 3 secondes
            setTimeout(() => {
                clientSelect.value = valeurOriginale;
                clientSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }, 3000);
        }, 1000);
    }
} else {
    console.error('  ❌ Sélecteur client non trouvé');
}

console.log('\n='.repeat(50));

// Export
window.debugSimple = () => {
    const script = document.createElement('script');
    script.src = '/debug-simple.js?t=' + Date.now();
    document.head.appendChild(script);
};