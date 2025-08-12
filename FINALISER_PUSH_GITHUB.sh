#!/bin/bash

# 🚀 SCRIPT POUR FINALISER LE PUSH GITHUB
# Repository créé avec succès : https://github.com/technograv/technoprod-erp

echo "🎯 Repository GitHub créé avec succès !"
echo "🔗 URL: https://github.com/technograv/technoprod-erp"
echo ""
echo "💾 Pour pousser le code propre (2 options) :"
echo ""
echo "OPTION 1 - Authentification GitHub CLI simple :"
echo "sudo su"
echo "cd /home/decorpub/TechnoProd/technoprod"
echo "git push origin main"
echo ""
echo "OPTION 2 - Authentification manuelle :"
echo "1. Allez sur : https://github.com/settings/tokens"
echo "2. Créez un token avec scope 'repo'"
echo "3. Exécutez : git remote set-url origin https://USERNAME:TOKEN@github.com/technograv/technoprod-erp.git"
echo "4. Exécutez : git push origin main"
echo ""
echo "✅ Une fois poussé, utilisez './quick-commit.sh' pour vos futurs commits !"