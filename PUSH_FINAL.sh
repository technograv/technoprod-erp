#!/bin/bash

# Script pour finaliser le push avec authentification correcte

echo "🔧 Configuration de l'authentification Git..."

# Solution 1: Utiliser un token personnel
echo ""
echo "🎯 SOLUTION SIMPLE (2 minutes):"
echo ""
echo "1. Allez sur: https://github.com/settings/tokens"
echo "2. Cliquez 'Generate new token' > 'Generate new token (classic)'"
echo "3. Nom: 'TechnoProd Git Access'"
echo "4. Cochez 'repo' (toutes les sous-options)"
echo "5. Cliquez 'Generate token'"
echo "6. COPIEZ le token généré"
echo ""
echo "7. Puis exécutez ces commandes avec VOTRE token:"
echo ""
echo "git remote set-url origin https://technograv:VOTRE_TOKEN_ICI@github.com/technograv/technoprod-erp.git"
echo "git push origin main"
echo ""
echo "✅ Remplacez VOTRE_TOKEN_ICI par le token copié !"
echo ""
echo "Exemple:"
echo "git remote set-url origin https://technograv:ghp_1234567890abcdef@github.com/technograv/technoprod-erp.git"
echo "git push origin main"