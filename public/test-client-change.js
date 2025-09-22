// Test changement de client pour vérifier que les contacts changent
console.clear();
console.log('%c🎯 TEST CHANGEMENT CLIENT', 'color: blue; font-size: 16px; font-weight: bold');
console.log('='.repeat(50));

// 1. État initial
console.group('1️⃣ ÉTAT INITIAL');
const clientSelect = document.getElementById('devis_client');
const contactLivraison = document.getElementById('contact_defaut');
const contactFacturation = document.getElementById('contact_facturation');

console.log('Client actuel:', clientSelect?.value || 'AUCUN');
console.log('Contact livraison options:', contactLivraison?.options.length || 0);
console.log('Contact facturation options:', contactFacturation?.options.length || 0);

// Afficher quelques contacts actuels
if (contactLivraison && contactLivraison.options.length > 1) {
    console.log('Contacts actuels (premiers 3):');
    for (let i = 1; i <= Math.min(3, contactLivraison.options.length - 1); i++) {
        console.log(`  ${i}: ${contactLivraison.options[i].text}`);
    }
}
console.groupEnd();

// 2. Chercher un autre client
console.group('2️⃣ RECHERCHE AUTRE CLIENT');
let autreClientId = null;
let autreClientText = null;
const clientActuel = clientSelect?.value;

if (clientSelect && clientSelect.options.length > 2) {
    for (let i = 1; i < clientSelect.options.length; i++) {
        const option = clientSelect.options[i];
        if (option.value && option.value !== clientActuel) {
            autreClientId = option.value;
            autreClientText = option.text;
            break;
        }
    }
}

if (autreClientId) {
    console.log(`✅ Autre client trouvé: ${autreClientId} = ${autreClientText}`);
} else {
    console.error('❌ Aucun autre client disponible pour le test');
    console.groupEnd();
    console.log('='.repeat(50));
    return;
}
console.groupEnd();

// 3. Test du changement
console.group('3️⃣ TEST CHANGEMENT');
console.log(`🔄 Changement de client ${clientActuel} → ${autreClientId}`);

// Mémoriser l'état avant
const contactsAvant = Array.from(contactLivraison?.options || []).map(opt => opt.text);
console.log(`Contacts avant (${contactsAvant.length - 1}):`, contactsAvant.slice(1, 4));

// Effectuer le changement
clientSelect.value = autreClientId;
const event = new Event('change', { bubbles: true });
clientSelect.dispatchEvent(event);

console.log('✅ Événement change déclenché');
console.log('⏱️ Attente de 3 secondes pour voir les changements...');

setTimeout(() => {
    console.log('📊 RÉSULTATS APRÈS 3 SECONDES:');
    
    const contactsApres = Array.from(contactLivraison?.options || []).map(opt => opt.text);
    console.log(`Contacts après (${contactsApres.length - 1}):`, contactsApres.slice(1, 4));
    
    // Comparer
    const aChange = JSON.stringify(contactsAvant) !== JSON.stringify(contactsApres);
    
    if (aChange) {
        console.log('%c✅ SUCCÈS ! Les contacts ont changé !', 'color: green; font-weight: bold');
        
        // Montrer la différence
        console.log('🆕 Nouveaux contacts:');
        contactsApres.slice(1, 6).forEach((contact, i) => {
            if (!contactsAvant.includes(contact)) {
                console.log(`  ✨ ${i + 1}: ${contact} (nouveau)`);
            } else {
                console.log(`  📋 ${i + 1}: ${contact}`);
            }
        });
    } else {
        console.log('%c❌ ÉCHEC ! Les contacts n\'ont pas changé', 'color: red; font-weight: bold');
        
        // Diagnostic
        if (window.devisContactService) {
            console.log('📋 Service DevisContactService présent');
            if (window.devisContactService.logger) {
                console.log('💡 Pour diagnostiquer: window.devisContactService.logger.export()');
            }
        } else {
            console.log('❌ Service DevisContactService introuvable');
        }
    }
    
    console.groupEnd();
    console.log('='.repeat(50));
    console.log('%c🏁 TEST TERMINÉ', 'color: blue; font-weight: bold');
    
}, 3000);

console.groupEnd();

// Export pour usage manuel
window.testClientChange = () => {
    const script = document.createElement('script');
    script.src = '/test-client-change.js?t=' + Date.now();
    document.head.appendChild(script);
};

console.log('💡 Pour relancer: testClientChange()');