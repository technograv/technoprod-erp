// Test final : Vérifier que DevisContactService fonctionne seul
console.clear();
console.log('%c🎯 TEST FINAL - DEVIS CONTACT SERVICE SEUL', 'color: green; font-size: 18px; font-weight: bold');
console.log('='.repeat(60));

// 1. Vérifier qu'aucun service concurrent n'existe
console.group('1️⃣ VÉRIFICATION ABSENCE DE SERVICES CONCURRENTS');
console.log('ClientProspectService existe:', typeof window.ClientProspectService !== 'undefined');
console.log('window.clientProspectService existe:', typeof window.clientProspectService !== 'undefined');
console.log('DevisContactService existe:', typeof window.DevisContactService !== 'undefined');
console.log('window.devisContactService existe:', typeof window.devisContactService !== 'undefined');

if (typeof window.ClientProspectService !== 'undefined' || typeof window.clientProspectService !== 'undefined') {
    console.error('❌ PROBLÈME: ClientProspectService encore présent!');
} else {
    console.log('✅ ClientProspectService bien supprimé');
}
console.groupEnd();

// 2. Test du service DevisContactService
console.group('2️⃣ TEST DEVISCONTACTSERVICE');
if (typeof window.DevisContactService !== 'undefined') {
    console.log('✅ DevisContactService disponible');
    
    // Vérifier l'instance
    if (window.devisContactService) {
        console.log('✅ Instance devisContactService créée');
        console.log('Type:', typeof window.devisContactService);
        console.log('Debug activé:', window.devisContactService.debug);
        
        // Test des méthodes principales
        const methods = ['init', 'handleClientChange', 'populateContacts', 'populateAddresses', 'destroy'];
        methods.forEach(method => {
            console.log(`Méthode ${method}:`, typeof window.devisContactService[method] === 'function' ? '✅' : '❌');
        });
    } else {
        console.warn('⚠️ Instance devisContactService non créée');
    }
} else {
    console.error('❌ DevisContactService non disponible');
}
console.groupEnd();

// 3. Test des éléments DOM
console.group('3️⃣ ÉLÉMENTS DOM PRÉSENTS');
const elements = [
    'devis_client',
    'contact_defaut', 
    'contact_facturation',
    'adresse_livraison_select',
    'adresse_facturation_select',
    'prospect'
];

elements.forEach(id => {
    const element = document.getElementById(id);
    console.log(`#${id}:`, element ? '✅ Présent' : '❌ Absent');
});
console.groupEnd();

// 4. Test de sélection de client si possible
console.group('4️⃣ TEST SÉLECTION CLIENT');
const clientSelectors = ['devis_client', 'prospect'];
let clientElement = null;

for (const selector of clientSelectors) {
    clientElement = document.getElementById(selector);
    if (clientElement && clientElement.options && clientElement.options.length > 1) {
        console.log(`Utilisation du sélecteur: #${selector}`);
        break;
    }
}

if (clientElement && clientElement.options.length > 1) {
    // Trouver un client valide
    let testClientId = null;
    for (let i = 1; i < clientElement.options.length; i++) {
        if (clientElement.options[i].value) {
            testClientId = clientElement.options[i].value;
            break;
        }
    }
    
    if (testClientId) {
        console.log(`🎯 Test avec client ID: ${testClientId}`);
        
        // Mémoriser l'état initial
        const initialContactOptions = document.getElementById('contact_defaut')?.options.length || 0;
        const initialAddressOptions = document.getElementById('adresse_livraison_select')?.options.length || 0;
        
        console.log(`État initial - Contacts: ${initialContactOptions}, Adresses: ${initialAddressOptions}`);
        
        // Simuler la sélection
        clientElement.value = testClientId;
        const event = new Event('change', { bubbles: true });
        clientElement.dispatchEvent(event);
        
        console.log('✅ Événement change déclenché');
        
        // Vérifier après un délai
        setTimeout(() => {
            const finalContactOptions = document.getElementById('contact_defaut')?.options.length || 0;
            const finalAddressOptions = document.getElementById('adresse_livraison_select')?.options.length || 0;
            
            console.log(`État final - Contacts: ${finalContactOptions}, Adresses: ${finalAddressOptions}`);
            
            if (finalContactOptions > initialContactOptions || finalAddressOptions > initialAddressOptions) {
                console.log('%c✅ SUCCÈS! Les données ont été chargées!', 'color: green; font-weight: bold');
            } else {
                console.log('%c⚠️ Les données ne semblent pas avoir changé', 'color: orange; font-weight: bold');
                
                // Diagnostic supplémentaire
                if (window.devisContactService && window.devisContactService.logger) {
                    console.log('📋 Logger disponible pour diagnostic');
                    console.log('Utilisez: window.devisContactService.logger.export()');
                }
            }
            
            console.groupEnd();
            console.log('='.repeat(60));
            console.log('%c🏁 TEST TERMINÉ', 'color: blue; font-weight: bold');
            
        }, 2000);
        
    } else {
        console.warn('Aucun client valide trouvé pour le test');
        console.groupEnd();
    }
} else {
    console.warn('Aucun sélecteur client valide trouvé');
    console.groupEnd();
}

// Export pour usage manuel
window.testDevisContactService = () => {
    const script = document.createElement('script');
    script.src = '/test-devis-contact-service.js?t=' + Date.now();
    document.head.appendChild(script);
};

console.log('💡 Pour relancer: testDevisContactService()');