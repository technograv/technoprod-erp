#!/bin/bash

# Script simple pour créer le repository GitHub après authentification

echo "🚀 Création du repository GitHub..."

# Description simple sans caractères spéciaux
gh repo create technoprod-erp \
  --public \
  --description "TechnoProd ERP/CRM - Systeme complet de gestion client/prospect avec devis et conformite comptable francaise" \
  --source=. \
  --push

if [ $? -eq 0 ]; then
    echo "✅ SUCCÈS ! Votre repository est créé !"
    echo "🔗 URL: https://github.com/$(gh api user --jq .login)/technoprod-erp"
    echo ""
    echo "📋 Pour vos prochaines modifications :"
    echo "   ./quick-commit.sh \"Description de vos changements\""
else
    echo "❌ Erreur lors de la création du repository"
    echo "Vérifiez votre authentification GitHub avec: gh auth status"
fi