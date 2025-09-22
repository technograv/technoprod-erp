// Test et correction de l'API problématique
console.clear();
console.log('%c🔧 FIX API TEST', 'color: purple; font-size: 16px; font-weight: bold');

// Test direct avec des URLs complètes
const testUrls = [
    '/client/6/contacts',
    '/client/6/addresses', 
    '/client/44/contacts',
    '/client/44/addresses'
];

async function testApi(url) {
    console.log(`🌐 Test: ${url}`);
    
    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        console.log(`  Status: ${response.status}`);
        console.log(`  Content-Type: ${response.headers.get('content-type')}`);
        
        const text = await response.text();
        
        if (text.startsWith('<!DOCTYPE') || text.includes('<html>')) {
            console.error(`  ❌ HTML retourné au lieu de JSON`);
            console.log(`  Premier contenu: ${text.substring(0, 100)}...`);
            
            // Extraire le titre de l'erreur si possible
            const titleMatch = text.match(/<title>(.*?)<\/title>/);
            if (titleMatch) {
                console.log(`  Titre erreur: ${titleMatch[1]}`);
            }
        } else {
            console.log(`  ✅ JSON valide`);
            try {
                const data = JSON.parse(text);
                console.log(`  Résultats: ${Array.isArray(data) ? data.length : 'Object'} éléments`);
            } catch (e) {
                console.error(`  ❌ JSON invalide: ${e.message}`);
            }
        }
        
    } catch (error) {
        console.error(`  ❌ Erreur réseau: ${error.message}`);
    }
    
    console.log(''); // Ligne vide
}

// Tester toutes les URLs
async function runTests() {
    for (const url of testUrls) {
        await testApi(url);
        await new Promise(resolve => setTimeout(resolve, 500)); // Pause entre tests
    }
    
    console.log('%c🏁 TESTS TERMINÉS', 'color: purple; font-weight: bold');
    console.log('Si certaines URLs retournent du HTML, c\'est là le problème !');
}

runTests();

// Export pour usage manuel
window.fixApiTest = () => {
    const script = document.createElement('script');
    script.src = '/fix-api-test.js?t=' + Date.now();
    document.head.appendChild(script);
};