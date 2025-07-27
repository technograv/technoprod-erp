#!/bin/bash

# 🚀 SCRIPT TOUT-EN-UN POUR TECHNOPROD
# Vous tapez votre mot de passe UNE FOIS, le script fait TOUT

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_step() {
    echo -e "${BLUE}🔧 $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

echo -e "${GREEN}"
echo "🚀 SETUP AUTOMATIQUE TECHNOPROD GITHUB"
echo "======================================="
echo -e "${NC}"
echo "Ce script va :"
echo "1. Installer Git et GitHub CLI"
echo "2. Vous demander vos informations Git"
echo "3. Vous guider pour l'authentification GitHub"
echo "4. Créer automatiquement le repository"
echo "5. Pousser tout le code"
echo ""

read -p "Continuer ? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_warning "Installation annulée"
    exit 1
fi

print_step "Mise à jour du système..."
sudo apt update

print_step "Installation de Git..."
sudo apt install -y git

print_step "Installation de GitHub CLI..."
sudo apt install -y software-properties-common
curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | sudo dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null
sudo apt update
sudo apt install -y gh

print_success "Outils installés avec succès!"

print_step "Configuration Git..."
echo "Veuillez entrer vos informations Git :"
read -p "Votre nom complet : " USER_NAME
read -p "Votre email GitHub : " USER_EMAIL

git config --global user.name "$USER_NAME"
git config --global user.email "$USER_EMAIL"
print_success "Git configuré pour $USER_NAME"

print_step "Configuration du repository local..."
git init
git add .

COMMIT_MESSAGE="🚀 Initial commit - TechnoProd ERP/CRM

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

git commit -m "$COMMIT_MESSAGE"
print_success "Commit initial créé"

print_step "Authentification GitHub..."
echo ""
echo -e "${YELLOW}ÉTAPE IMPORTANTE :${NC}"
echo "Le navigateur va s'ouvrir pour vous connecter à GitHub"
echo "1. Connectez-vous à votre compte GitHub"
echo "2. Autorisez l'application GitHub CLI"
echo "3. Revenez ici quand c'est fait"
echo ""
read -p "Appuyez sur Entrée quand vous êtes prêt..."

gh auth login

print_step "Création du repository sur GitHub..."
REPO_NAME="technoprod-erp"
DESCRIPTION="TechnoProd ERP/CRM - Système complet de gestion client/prospect avec devis, signature électronique et conformité comptable française"

if gh repo create "$REPO_NAME" --public --description "$DESCRIPTION" --source=. --push; then
    print_success "🎉 SUCCÈS TOTAL !"
    echo ""
    echo -e "${GREEN}✅ Votre code est maintenant sur GitHub !${NC}"
    echo -e "${BLUE}🔗 URL: https://github.com/$(gh api user --jq .login)/$REPO_NAME${NC}"
    echo ""
    echo -e "${YELLOW}📋 POUR VOS PROCHAINES MODIFICATIONS :${NC}"
    echo "   ./quick-commit.sh \"Description de vos changements\""
    echo ""
    print_success "Vous êtes maintenant 100% autonome sur GitHub !"
else
    print_error "Erreur lors de la création du repository"
    echo "Vérifiez votre connexion GitHub et réessayez"
fi