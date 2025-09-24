/**
 * Script de test pour valider le fonctionnement du NestedModalService
 */
console.log('🧪 Test du NestedModalService');

// Vérifier que le service est disponible
if (window.NestedModalService) {
    console.log('✅ NestedModalService est disponible');
    
    // Créer une instance de test
    const testService = new NestedModalService({
        debug: true,
        autoSelectCreated: true
    });
    
    console.log('✅ Instance créée avec succès');
    
    // Vérifier les méthodes principales
    const methods = [
        'openClientModal',
        'openContactModal', 
        'openAddressModal',
        'attachToDevisPage',
        'navigateBack',
        'resetState'
    ];
    
    let allMethodsPresent = true;
    methods.forEach(method => {
        if (typeof testService[method] === 'function') {
            console.log(`✅ Méthode ${method} présente`);
        } else {
            console.error(`❌ Méthode ${method} manquante`);
            allMethodsPresent = false;
        }
    });
    
    if (allMethodsPresent) {
        console.log('🎉 Toutes les méthodes sont présentes et le service est prêt à l\'emploi');
    }
    
    // Test de la pile de modales
    testService.pushModal('client', { mode: 'create' });
    testService.pushModal('contact', { mode: 'create' });
    console.log('📚 Stack après ajout:', testService.modalStack);
    
    const removed = testService.popModal();
    console.log('📤 Élément retiré:', removed);
    console.log('📚 Stack après retrait:', testService.modalStack);
    
    testService.resetState();
    console.log('🔄 Stack après reset:', testService.modalStack);
    
} else {
    console.error('❌ NestedModalService non disponible');
    console.log('Assurez-vous que le script est inclus dans la page');
}

console.log('🧪 Test terminé');