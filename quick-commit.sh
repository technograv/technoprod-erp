#!/bin/bash

# 🚀 SCRIPT DE COMMIT RAPIDE POUR TECHNOPROD
# Usage: ./quick-commit.sh "Message du commit"

set -e

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
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

# Message de commit
if [ -z "$1" ]; then
    echo "Usage: $0 \"Message du commit\""
    echo "Exemple: $0 \"Ajout nouvelle fonctionnalité de facturation\""
    exit 1
fi

COMMIT_MESSAGE="$1"

print_step "Déploiement rapide en cours..."

# Vérifier les modifications
print_step "Vérification des fichiers modifiés..."
git status --porcelain

# Ajout des fichiers
print_step "Ajout des fichiers..."
git add .

# Vérification des différences
print_step "Affichage des modifications..."
git diff --cached --stat

# Demande de confirmation
echo ""
read -p "Confirmer le commit avec ce message: '$COMMIT_MESSAGE' ? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_warning "Commit annulé"
    exit 1
fi

# Commit
print_step "Création du commit..."
FULL_MESSAGE="$COMMIT_MESSAGE

🤖 Generated with Claude Code
Co-Authored-By: Claude <noreply@anthropic.com>"

git commit -m "$FULL_MESSAGE"

# Push
print_step "Push vers GitHub..."
git push

print_success "Déploiement terminé avec succès!"
print_success "Vos modifications sont maintenant sur GitHub"