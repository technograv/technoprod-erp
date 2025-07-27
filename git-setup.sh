#!/bin/bash

# 🚀 SCRIPT D'AUTOMATISATION GIT/GITHUB POUR TECHNOPROD
# Usage: ./git-setup.sh [nom-du-repo]

set -e  # Arrêter en cas d'erreur

# Couleurs pour affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction d'affichage avec couleurs
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

# Vérifications préliminaires
print_step "Vérification des prérequis..."

# Vérifier si git est installé
if ! command -v git &> /dev/null; then
    print_error "Git n'est pas installé. Installez-le avec: sudo apt install git"
    exit 1
fi

# Vérifier si gh CLI est installé
if ! command -v gh &> /dev/null; then
    print_warning "GitHub CLI n'est pas installé. Installation automatique..."
    
    # Installation GitHub CLI
    sudo apt update
    sudo apt install -y software-properties-common
    sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-key C99B11DEB97541F0
    sudo apt-add-repository https://cli.github.com/packages
    sudo apt update
    sudo apt install -y gh
    
    print_success "GitHub CLI installé avec succès"
fi

# Nom du repository
REPO_NAME=${1:-"technoprod-erp"}
print_step "Repository à créer: $REPO_NAME"

# Configuration Git si nécessaire
print_step "Configuration Git..."
if [ -z "$(git config --global user.email)" ]; then
    echo "Configuration initiale Git requise:"
    read -p "Votre email GitHub: " USER_EMAIL
    read -p "Votre nom complet: " USER_NAME
    
    git config --global user.email "$USER_EMAIL"
    git config --global user.name "$USER_NAME"
    print_success "Configuration Git terminée"
else
    print_success "Git déjà configuré pour $(git config --global user.name)"
fi

# Initialisation du repository local
print_step "Initialisation du repository local..."
if [ ! -d ".git" ]; then
    git init
    print_success "Repository Git initialisé"
else
    print_success "Repository Git déjà existant"
fi

# Ajout des fichiers
print_step "Ajout des fichiers au repository..."
git add .
git status

# Commit initial
print_step "Création du commit initial..."
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

# Authentification GitHub
print_step "Authentification GitHub..."
if ! gh auth status &> /dev/null; then
    print_warning "Authentification GitHub requise"
    gh auth login
    print_success "Authentification GitHub réussie"
else
    print_success "Déjà authentifié sur GitHub"
fi

# Création du repository sur GitHub
print_step "Création du repository sur GitHub..."
DESCRIPTION="TechnoProd ERP/CRM - Système complet de gestion client/prospect avec devis, signature électronique et conformité comptable française"

if gh repo create "$REPO_NAME" --public --description "$DESCRIPTION" --source=. --push; then
    print_success "Repository créé et poussé sur GitHub avec succès!"
    
    # Affichage des informations
    echo ""
    echo -e "${GREEN}🎉 SUCCÈS! Votre repository est maintenant sur GitHub:${NC}"
    echo -e "${BLUE}🔗 URL: https://github.com/$(gh api user --jq .login)/$REPO_NAME${NC}"
    echo ""
    echo -e "${YELLOW}📋 PROCHAINES ÉTAPES RECOMMANDÉES:${NC}"
    echo "1. Configurez les GitHub Actions pour CI/CD"
    echo "2. Ajoutez un README.md détaillé"
    echo "3. Configurez les issues templates"
    echo "4. Ajoutez une licence (MIT recommandée)"
    echo "5. Configurez les branch protection rules"
    
else
    print_error "Erreur lors de la création du repository"
    print_warning "Le repository existe peut-être déjà. Tentative de push..."
    
    # Tentative d'ajout de remote et push
    git remote add origin "https://github.com/$(gh api user --jq .login)/$REPO_NAME.git"
    git branch -M main
    git push -u origin main
fi

print_success "Script terminé avec succès!"