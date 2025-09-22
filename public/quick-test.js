// Script de test rapide pour DevisContactService
// À copier-coller dans la console du navigateur sur /devis/new

console.clear();
console.log('%c🔴 TEST RAPIDE DEVISCONTACTSERVICE', 'color: red; font-size: 20px; font-weight: bold');
console.log('====================================');

// 1. Vérifier que les éléments DOM existent
console.group('1️⃣ VÉRIFICATION DOM');
const elements = {
    'prospect': document.getElementById('prospect'),
    'client_field': document.getElementById('client_field'),
    'contact_defaut': document.getElementById('contact_defaut'),
    'contact_facturation': document.getElementById('contact_facturation'),
    'adresse_livraison_select': document.getElementById('adresse_livraison_select'),
    'adresse_facturation_select': document.getElementById('adresse_facturation_select'),
    'email_envoi_automatique': document.getElementById('email_envoi_automatique')
};

Object.entries(elements).forEach(([key, el]) => {
    if (el) {
        console.log(`✅ ${key}: TROUVÉ (value="${el.value || ''}")`);
    } else {
        console.error(`❌ ${key}: NON TROUVÉ`);
    }
});
console.groupEnd();

// 2. Vérifier le service
console.group('2️⃣ VÉRIFICATION SERVICE');
if (window.devisContactService) {
    console.log('✅ DevisContactService: ACTIF');
    console.log('  Mode:', window.devisContactService.getMode());
    console.log('  Initialisé:', window.devisContactService.isInitialized);
    const selector = window.devisContactService.getClientSelector();
    if (selector) {
        console.log('  Sélecteur client:', selector.id);
        console.log('  Valeur actuelle:', selector.value);
    } else {
        console.error('  ❌ Aucun sélecteur client trouvé!');
    }
} else {
    console.error('❌ DevisContactService: NON TROUVÉ');
}
console.groupEnd();

// 3. Vérifier les loggers
console.group('3️⃣ VÉRIFICATION LOGGERS');
if (window.debugLoggers) {
    console.log('✅ Debug Loggers disponibles:', Object.keys(window.debugLoggers));
    // Afficher le résumé de chaque logger
    Object.entries(window.debugLoggers).forEach(([name, logger]) => {
        const summary = logger.summary();
        console.log(`  📊 ${name}:`, summary);
    });
} else {
    console.warn('⚠️ Aucun debug logger trouvé');
}
console.groupEnd();

// 4. Test de chargement client
console.group('4️⃣ TEST CHARGEMENT CLIENT #2');
console.log('🔄 Tentative de chargement du client #2...');

// Forcer la sélection du client #2
const prospectSelect = document.getElementById('prospect');
if (prospectSelect) {
    // Vérifier si l'option existe
    const option2 = Array.from(prospectSelect.options).find(opt => opt.value === '2');
    if (option2) {
        console.log('✅ Option client #2 trouvée:', option2.text);
        prospectSelect.value = '2';
        
        // Déclencher manuellement le changement
        if (window.devisContactService) {
            console.log('📡 Appel handleClientChange(2)...');
            window.devisContactService.handleClientChange(2);
            
            // Attendre un peu et vérifier
            setTimeout(() => {
                console.group('📋 RÉSULTATS APRÈS CHARGEMENT');
                
                // Vérifier les contacts
                const contactLivraison = document.getElementById('contact_defaut');
                const contactFacturation = document.getElementById('contact_facturation');
                
                if (contactLivraison && contactLivraison.options.length > 1) {
                    console.log('✅ Contacts livraison chargés:', contactLivraison.options.length - 1);
                    console.log('  Sélectionné:', contactLivraison.options[contactLivraison.selectedIndex]?.text);
                } else {
                    console.error('❌ Aucun contact livraison chargé');
                }
                
                if (contactFacturation && contactFacturation.options.length > 1) {
                    console.log('✅ Contacts facturation chargés:', contactFacturation.options.length - 1);
                    console.log('  Sélectionné:', contactFacturation.options[contactFacturation.selectedIndex]?.text);
                } else {
                    console.error('❌ Aucun contact facturation chargé');
                }
                
                // Vérifier les adresses
                const adresseLivraison = document.getElementById('adresse_livraison_select');
                const adresseFacturation = document.getElementById('adresse_facturation_select');
                
                if (adresseLivraison && adresseLivraison.options.length > 1) {
                    console.log('✅ Adresses livraison chargées:', adresseLivraison.options.length - 1);
                } else {
                    console.error('❌ Aucune adresse livraison chargée');
                }
                
                if (adresseFacturation && adresseFacturation.options.length > 1) {
                    console.log('✅ Adresses facturation chargées:', adresseFacturation.options.length - 1);
                } else {
                    console.error('❌ Aucune adresse facturation chargée');
                }
                
                // Vérifier l'email
                const email = document.getElementById('email_envoi_automatique');
                if (email && email.value) {
                    console.log('✅ Email synchronisé:', email.value);
                } else {
                    console.warn('⚠️ Aucun email synchronisé');
                }
                
                console.groupEnd();
            }, 2000);
        }
    } else {
        console.error('❌ Client #2 non trouvé dans la liste');
    }
} else {
    console.error('❌ Sélecteur prospect non trouvé');
}
console.groupEnd();

console.log('====================================');
console.log('ℹ️ Pour voir les logs détaillés, cliquez sur le bouton "🐛 Debug" en bas à droite');
console.log('ℹ️ Pour exporter les logs: window.debugLoggers["DevisContactService"].export()');