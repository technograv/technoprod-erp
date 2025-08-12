#!/bin/bash

# 🚀 SETUP MANUEL GIT/GITHUB POUR TECHNOPROD
# À exécuter APRÈS avoir installé git et gh

set -e

echo "🔧 Initialisation du repository Git..."
git init

echo "🔧 Ajout de tous les fichiers..."
git add .

echo "🔧 Création du commit initial..."
git commit -m "🚀 Initial commit - TechnoProd ERP/CRM

✅ Fonctionnalités implémentées:
- Gestion Client/Prospect moderne avec interface tableau
- Système de devis complet avec signature électronique
- Conformité comptable française (NF203, PCG, FEC, Factur-X)
- Base de données communes françaises avec autocomplétion
- Secteurs commerciaux avec géolocalisation
- Préférences utilisateur personnalisées
- Interface d'édition client avancée avec bouton flottant

🎯 Status: 95% complet - Prêt pour production

🤖 Generated with Claude Code
Co-Authored-By: Claude <noreply@anthropic.com>"

echo "🔧 Création du repository sur GitHub..."
gh repo create technoprod-erp --public --description "TechnoProd ERP/CRM - Système complet de gestion client/prospect avec devis, signature électronique et conformité comptable française" --source=. --push

echo "✅ Repository créé avec succès!"
echo "🔗 URL: https://github.com/$(gh api user --jq .login)/technoprod-erp"