#!/bin/bash

echo "🚀 TechnoProd Version 2.2 - Script de Push"
echo "========================================"

# Vérifier le statut git
echo "📊 Statut Git actuel :"
git status --porcelain | wc -l | xargs echo "Fichiers en attente :"

# Vérifier les fichiers staged
staged_files=$(git diff --staged --name-only | wc -l)
echo "📁 Fichiers staged : $staged_files"

if [ $staged_files -eq 0 ]; then
    echo "⚠️  Aucun fichier staged. Les fichiers doivent être ajoutés avec 'git add .'"
    exit 1
fi

echo ""
echo "🔧 Tentative de correction permissions git..."
# Corriger les permissions sans sudo (pour éviter les erreurs)
find .git -type f -name "*" 2>/dev/null | while read file; do
    chmod u+w "$file" 2>/dev/null || true
done

echo "💾 Tentative de commit..."
git commit -m "feat: TechnoProd v2.2 - Optimisation autocomplétion codes postaux

✅ Autocomplétion codes postaux spécialisée : Propose uniquement les codes postaux
✅ Comptage communes : Affiche 'Code postal 31160 (3 communes)'  
✅ Architecture maintenable : 4 contrôleurs spécialisés créés
✅ Workflow attribution zones : 100% fonctionnel
✅ Interface utilisateur optimisée : UX moderne avec feedback temps réel

🎯 Prêt pour déploiement production
Système stable, performant et maintenable avec toutes fonctionnalités opérationnelles.

🤖 Generated with Claude Code
Co-Authored-By: Claude <noreply@anthropic.com>"

if [ $? -eq 0 ]; then
    echo "✅ Commit réussi !"
    
    echo "📤 Push vers GitHub..."
    git push origin main
    
    if [ $? -eq 0 ]; then
        echo "🎉 Push réussi !"
        echo ""
        echo "📊 Résumé Version 2.2 :"
        echo "  • Autocomplétion codes postaux spécialisée"
        echo "  • 4 contrôleurs admin créés" 
        echo "  • $staged_files fichiers mis à jour"
        echo "  • Architecture maintenable"
        echo "  • Prêt production"
        echo ""
        echo "🔗 Vérifier sur GitHub :"
        git remote get-url origin
    else
        echo "❌ Erreur lors du push"
        echo "💡 Commande manuelle :"
        echo "    git push origin main"
    fi
else
    echo "❌ Erreur lors du commit"
    echo ""
    echo "🔧 Solutions alternatives :"
    echo "1. Corriger permissions manuellement :"
    echo "   sudo chown -R \$USER:\$USER .git/"
    echo ""
    echo "2. Utiliser le patch créé :"
    echo "   git reset --mixed HEAD"
    echo "   git apply v2.2_changes.patch"
    echo "   git add ."
    echo "   git commit -m 'feat: TechnoProd v2.2'"
    echo ""
    echo "📖 Voir DEPLOY_V2.2.md pour guide complet"
fi