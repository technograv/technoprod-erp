#!/bin/bash

# Script de vérification de sécurité pour TechnoProd
# Vérifie qu'aucune clé API n'est exposée dans les fichiers versionnés

echo "🔍 Vérification de sécurité TechnoProd..."

# Vérifier les clés Google API
echo "   Vérification clés Google API..."
if git ls-files | xargs grep -l "AIza[0-9A-Za-z_-]\{35\}" 2>/dev/null; then
    echo "❌ ERREUR: Clé Google API détectée dans les fichiers versionnés !"
    exit 1
fi

# Vérifier les autres patterns de clés API
echo "   Vérification autres clés API..."
if git ls-files | xargs grep -l "sk-[0-9A-Za-z]\{48\}" 2>/dev/null; then
    echo "❌ ERREUR: Clé OpenAI détectée !"
    exit 1
fi

# Vérifier que .env.local n'est pas versionné
echo "   Vérification fichiers .env..."
if git ls-files | grep -E "\.env\.(local|dev|prod)$" 2>/dev/null; then
    echo "❌ ERREUR: Fichiers .env sensibles versionnés !"
    exit 1
fi

# Vérifier les mots de passe en dur (éviter faux positifs)
echo "   Vérification mots de passe..."
if git ls-files | xargs grep -E "password\s*=\s*['\"][^'\"]*[a-zA-Z0-9]{8,}" | grep -v "your-password\|password-app\|PASSWORD=\|votre-mot-de-passe" 2>/dev/null; then
    echo "❌ ERREUR: Mots de passe en dur détectés !"
    exit 1
fi

echo "✅ Vérification de sécurité réussie - Aucune clé API exposée"
exit 0