#!/bin/bash

# Script d'analyse d'architecture TechnoProd
# Alternative au Makefile pour les systèmes sans 'make'

# Variables
SCRIPT_ANALYZER="./symfony-arch-analyzer.php"
DEPTRAC="./vendor/bin/deptrac"
OUTPUT_DIR="architecture-reports"
DATE=$(date +%Y%m%d_%H%M%S)

# Couleurs pour l'affichage
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Fonction d'aide
show_help() {
    echo -e "${BLUE}📊 Outils d'analyse d'architecture TechnoProd${NC}"
    echo ""
    echo -e "${GREEN}Commandes disponibles:${NC}"
    echo -e "  ${YELLOW}./analyze-architecture.sh help${NC}              Afficher cette aide"
    echo -e "  ${YELLOW}./analyze-architecture.sh all${NC}               Analyse complète"
    echo -e "  ${YELLOW}./analyze-architecture.sh arch${NC}              Analyse architecture uniquement"
    echo -e "  ${YELLOW}./analyze-architecture.sh deps${NC}              Analyse dépendances uniquement"
    echo -e "  ${YELLOW}./analyze-architecture.sh deps-graph${NC}        Générer graphique dépendances"
    echo -e "  ${YELLOW}./analyze-architecture.sh status${NC}            Afficher le statut des outils"
    echo -e "  ${YELLOW}./analyze-architecture.sh clean${NC}             Nettoyer les fichiers temporaires"
    echo ""
    echo -e "${BLUE}Exemples d'utilisation:${NC}"
    echo "  ./analyze-architecture.sh all        # Analyse complète"
    echo "  ./analyze-architecture.sh arch       # Architecture uniquement"
    echo "  ./analyze-architecture.sh deps       # Dépendances uniquement"
}

# Fonction d'analyse d'architecture
analyze_arch() {
    echo -e "${BLUE}🔍 Analyse de l'architecture TechnoProd...${NC}"
    mkdir -p "$OUTPUT_DIR"
    
    if [ ! -x "$SCRIPT_ANALYZER" ]; then
        echo -e "${RED}❌ Script d'analyse non trouvé ou non exécutable: $SCRIPT_ANALYZER${NC}"
        return 1
    fi
    
    $SCRIPT_ANALYZER . 2>&1 | tee "$OUTPUT_DIR/analysis_$DATE.log"
    
    # Déplacer les fichiers générés
    for file in technoprod-architecture.json technoprod-architecture.dot technoprod-architecture-report.md technoprod-architecture.png technoprod-architecture.svg; do
        if [ -f "$file" ]; then
            mv "$file" "$OUTPUT_DIR/"
            echo -e "${GREEN}✅ Fichier généré: $OUTPUT_DIR/$file${NC}"
        fi
    done
}

# Fonction d'analyse des dépendances
analyze_deps() {
    echo -e "${BLUE}🔗 Analyse des dépendances avec Deptrac...${NC}"
    mkdir -p "$OUTPUT_DIR"
    
    if [ ! -f "$DEPTRAC" ]; then
        echo -e "${RED}❌ Deptrac non trouvé. Exécutez: composer require --dev qossmic/deptrac-shim${NC}"
        return 1
    fi
    
    $DEPTRAC analyze 2>&1 | tee "$OUTPUT_DIR/deptrac_$DATE.log"
    
    if [ -f ".deptrac.cache" ]; then
        mv .deptrac.cache "$OUTPUT_DIR/"
    fi
    
    echo -e "${GREEN}✅ Analyse Deptrac terminée. Consultez: $OUTPUT_DIR/deptrac_$DATE.log${NC}"
}

# Fonction de génération de graphique des dépendances
analyze_deps_graph() {
    echo -e "${BLUE}🎨 Génération du graphique des dépendances...${NC}"
    mkdir -p "$OUTPUT_DIR"
    
    if [ ! -f "$DEPTRAC" ]; then
        echo -e "${RED}❌ Deptrac non trouvé.${NC}"
        return 1
    fi
    
    $DEPTRAC analyze --formatter=graphviz --output="$OUTPUT_DIR/dependencies-graph.dot"
    
    if command -v dot >/dev/null 2>&1; then
        dot -Tpng "$OUTPUT_DIR/dependencies-graph.dot" -o "$OUTPUT_DIR/dependencies-graph.png"
        dot -Tsvg "$OUTPUT_DIR/dependencies-graph.dot" -o "$OUTPUT_DIR/dependencies-graph.svg"
        echo -e "${GREEN}✅ Graphiques générés: dependencies-graph.png et dependencies-graph.svg${NC}"
    else
        echo -e "${YELLOW}⚠️ GraphViz non installé. Installez-le avec: sudo apt-get install graphviz${NC}"
    fi
}

# Fonction de statut
show_status() {
    echo -e "${BLUE}📋 Statut des outils d'analyse${NC}"
    echo ""
    
    echo -n "Script d'analyse: "
    if [ -x "$SCRIPT_ANALYZER" ]; then
        echo -e "${GREEN}✅ Disponible${NC}"
    else
        echo -e "${RED}❌ Non trouvé ou non exécutable${NC}"
    fi
    
    echo -n "Deptrac: "
    if [ -f "$DEPTRAC" ]; then
        echo -e "${GREEN}✅ Installé${NC}"
    else
        echo -e "${RED}❌ Non installé${NC}"
    fi
    
    echo -n "GraphViz: "
    if command -v dot >/dev/null 2>&1; then
        echo -e "${GREEN}✅ Installé${NC}"
    else
        echo -e "${YELLOW}⚠️ Non installé${NC}"
    fi
    
    echo -n "PlantUML: "
    if command -v plantuml >/dev/null 2>&1; then
        echo -e "${GREEN}✅ Installé${NC}"
    else
        echo -e "${YELLOW}⚠️ Non installé${NC}"
    fi
    
    echo ""
    if [ -d "$OUTPUT_DIR" ]; then
        echo -e "${BLUE}📁 Rapports existants:${NC}"
        ls -la "$OUTPUT_DIR/" 2>/dev/null || echo "Aucun rapport trouvé"
    else
        echo -e "${YELLOW}📁 Aucun répertoire de rapports trouvé${NC}"
    fi
}

# Fonction de nettoyage
clean_files() {
    echo -e "${BLUE}🧹 Nettoyage des fichiers temporaires...${NC}"
    rm -f *.dot *.png *.svg *.json *.log
    rm -f .deptrac.cache
    echo -e "${GREEN}✅ Nettoyage terminé${NC}"
}

# Fonction d'analyse complète
analyze_all() {
    echo -e "${BLUE}🚀 Lancement de l'analyse complète TechnoProd...${NC}"
    echo ""
    
    analyze_arch
    echo ""
    analyze_deps
    echo ""
    
    echo -e "${GREEN}✅ Analyse complète terminée !${NC}"
    echo -e "${BLUE}📁 Consultez les rapports dans: $OUTPUT_DIR/${NC}"
}

# Fonction de génération de documentation
generate_docs() {
    echo -e "${BLUE}📚 Génération de la documentation...${NC}"
    mkdir -p "$OUTPUT_DIR/docs"
    
    cat > "$OUTPUT_DIR/docs/README.md" << EOF
# 📊 Documentation Architecture TechnoProd

Générée le: $(date)

## 📁 Fichiers disponibles

- **technoprod-architecture.json** : Données structurées de l'architecture
- **technoprod-architecture-report.md** : Rapport d'analyse détaillé
- **technoprod-architecture.dot** : Fichier source GraphViz
- **technoprod-architecture.png/.svg** : Diagrammes visuels (si GraphViz installé)
- **dependencies-graph.png/.svg** : Graphiques des dépendances Deptrac
- **deptrac_*.log** : Logs d'analyse des violations architecturales

## 🚀 Usage

\`\`\`bash
# Analyse complète
./analyze-architecture.sh all

# Analyse spécifique
./analyze-architecture.sh arch    # Architecture uniquement
./analyze-architecture.sh deps    # Dépendances uniquement
\`\`\`

## 📊 Résumé TechnoProd

Votre application TechnoProd contient :
- 61 entités Doctrine
- 26 controllers web + 15 controllers admin
- 44 services métier
- 61 repositories
- 14 formulaires Symfony
- 302 routes configurées

## 🏗️ Architecture

L'application suit une architecture classique Symfony avec :
- **Entités** : Modèle de données (Domain Layer)
- **Repositories** : Accès aux données (Data Layer) 
- **Services** : Logique métier (Business Layer)
- **Controllers** : Interface utilisateur (Presentation Layer)
- **Forms** : Gestion des formulaires
EOF
    
    echo -e "${GREEN}✅ Documentation générée: $OUTPUT_DIR/docs/README.md${NC}"
}

# Traitement des arguments
case "${1:-help}" in
    "help"|"-h"|"--help")
        show_help
        ;;
    "all")
        analyze_all
        generate_docs
        ;;
    "arch"|"architecture")
        analyze_arch
        ;;
    "deps"|"dependencies")
        analyze_deps
        ;;
    "deps-graph"|"dependencies-graph")
        analyze_deps_graph
        ;;
    "status")
        show_status
        ;;
    "clean")
        clean_files
        ;;
    "docs"|"documentation")
        generate_docs
        ;;
    *)
        echo -e "${RED}❌ Commande inconnue: $1${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac