#!/bin/bash
# Script de scan de sécurité pour TechnoProd
# Détecte les secrets potentiels avant commit

set -e

echo "🔍 TechnoProd Security Scanner"
echo "================================"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Compteur d'erreurs
ERRORS=0
WARNINGS=0

# Patterns critiques à détecter (plus stricts pour éviter faux positifs)
declare -A CRITICAL_PATTERNS=(
    ["APP_SECRET"]='APP_SECRET\s*=\s*[a-f0-9]{64}'
    ["DB_PASSWORD"]='postgresql://[^:]+:[^@/]{3,}@'
    ["MYSQL_PASSWORD"]='mysql://[^:]+:[^@/]{3,}@'
    ["GOOGLE_API_KEY"]='GOOGLE[_-]?API[_-]?KEY\s*=\s*['\''"][A-Za-z0-9_-]{30,}['\''"]'
    ["OAUTH_SECRET"]='OAUTH[_-]?SECRET\s*=\s*['\''"][A-Za-z0-9_-]{20,}['\''"]'
    ["JWT_SECRET"]='JWT[_-]?SECRET\s*=\s*['\''"][A-Za-z0-9_-]{20,}['\''"]'
)

# Patterns d'avertissement (à vérifier manuellement)
declare -A WARNING_PATTERNS=(
    ["PASSWORD"]='password\s*=\s*["\x27][^"\x27]{3,}["\x27]'
    ["TOKEN"]='token\s*=\s*["\x27][^"\x27]{10,}["\x27]'
    ["API_KEY"]='api[_-]?key\s*=\s*["\x27][^"\x27]{10,}["\x27]'
)

# Fichiers/dossiers à ignorer
IGNORE_PATTERNS=(
    "*.md"
    "*.lock"
    "*.example"
    "*.dist"
    "scripts/*"
)

echo ""
echo "📋 Scanning staged files..."

# Obtenir les fichiers staged
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM)

if [ -z "$STAGED_FILES" ]; then
    echo "ℹ️  No staged files to check"
    exit 0
fi

# Fonction pour vérifier si un fichier doit être ignoré
should_ignore() {
    local file=$1
    for pattern in "${IGNORE_PATTERNS[@]}"; do
        # Utiliser pattern matching bash
        if [[ $file == $pattern ]]; then
            return 0
        fi
    done
    return 1
}

# Scanner les patterns critiques
echo ""
echo "🔴 Critical patterns check..."
for name in "${!CRITICAL_PATTERNS[@]}"; do
    pattern="${CRITICAL_PATTERNS[$name]}"
    
    while IFS= read -r file; do
        if should_ignore "$file"; then
            continue
        fi
        
        if git diff --cached "$file" | grep -qiE "$pattern"; then
            echo -e "${RED}❌ CRITICAL: $name detected in $file${NC}"
            ERRORS=$((ERRORS + 1))
        fi
    done <<< "$STAGED_FILES"
done

# Scanner les patterns d'avertissement
echo ""
echo "🟡 Warning patterns check..."
for name in "${!WARNING_PATTERNS[@]}"; do
    pattern="${WARNING_PATTERNS[$name]}"
    
    while IFS= read -r file; do
        if should_ignore "$file"; then
            continue
        fi
        
        if git diff --cached "$file" | grep -qiE "$pattern"; then
            echo -e "${YELLOW}⚠️  WARNING: Possible $name in $file (verify manually)${NC}"
            WARNINGS=$((WARNINGS + 1))
        fi
    done <<< "$STAGED_FILES"
done

# Vérifier que .env n'est pas staged
if echo "$STAGED_FILES" | grep -q "^\.env$"; then
    echo -e "${RED}❌ CRITICAL: .env file is staged!${NC}"
    echo "   .env should never be committed. Use .env.local instead."
    ERRORS=$((ERRORS + 1))
fi

# Résumé
echo ""
echo "================================"
if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}❌ Security check FAILED${NC}"
    echo "   Critical issues: $ERRORS"
    echo "   Warnings: $WARNINGS"
    echo ""
    echo "💡 To fix:"
    echo "   1. Remove secrets from staged files"
    echo "   2. Use .env.local for real secrets"
    echo "   3. Use placeholders in committed files"
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Security check PASSED with warnings${NC}"
    echo "   Warnings: $WARNINGS (please review manually)"
    echo ""
    echo "Continue with commit? (y/n)"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        echo "Commit aborted."
        exit 1
    fi
else
    echo -e "${GREEN}✅ Security check PASSED${NC}"
    echo "   No secrets detected"
fi

exit 0
