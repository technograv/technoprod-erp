# Makefile pour l'analyse d'architecture TechnoProd
# Usage: make <target>

.PHONY: help analyze-arch analyze-deps install-tools clean generate-docs all

# Variables
SCRIPT_ANALYZER = ./symfony-arch-analyzer.php
DEPTRAC = ./vendor/bin/deptrac
OUTPUT_DIR = architecture-reports
DATE = $(shell date +%Y%m%d_%H%M%S)

# Couleurs pour l'affichage
GREEN = \033[0;32m
YELLOW = \033[1;33m
BLUE = \033[0;34m
RED = \033[0;31m
NC = \033[0m # No Color

help: ## Afficher cette aide
	@echo "$(BLUE)📊 Outils d'analyse d'architecture TechnoProd$(NC)"
	@echo ""
	@echo "$(GREEN)Cibles disponibles:$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(BLUE)Exemples d'utilisation:$(NC)"
	@echo "  make all              # Analyse complète"
	@echo "  make analyze-arch     # Analyse architecture uniquement"
	@echo "  make analyze-deps     # Analyse dépendances uniquement"
	@echo "  make install-tools    # Installer les outils système"

all: analyze-arch analyze-deps generate-docs ## Exécuter toutes les analyses
	@echo "$(GREEN)✅ Analyse complète terminée !$(NC)"
	@echo "$(BLUE)📁 Consultez les rapports dans: $(OUTPUT_DIR)/$(NC)"

analyze-arch: ## Analyser l'architecture avec le script personnalisé
	@echo "$(BLUE)🔍 Analyse de l'architecture TechnoProd...$(NC)"
	@mkdir -p $(OUTPUT_DIR)
	@$(SCRIPT_ANALYZER) . 2>&1 | tee $(OUTPUT_DIR)/analysis_$(DATE).log
	@if [ -f technoprod-architecture.json ]; then \
		mv technoprod-architecture.json $(OUTPUT_DIR)/; \
		echo "$(GREEN)✅ Rapport JSON généré: $(OUTPUT_DIR)/technoprod-architecture.json$(NC)"; \
	fi
	@if [ -f technoprod-architecture.dot ]; then \
		mv technoprod-architecture.dot $(OUTPUT_DIR)/; \
		echo "$(GREEN)✅ Fichier DOT généré: $(OUTPUT_DIR)/technoprod-architecture.dot$(NC)"; \
	fi
	@if [ -f technoprod-architecture-report.md ]; then \
		mv technoprod-architecture-report.md $(OUTPUT_DIR)/; \
		echo "$(GREEN)✅ Rapport Markdown généré: $(OUTPUT_DIR)/technoprod-architecture-report.md$(NC)"; \
	fi
	@if [ -f technoprod-architecture.png ]; then \
		mv technoprod-architecture.png $(OUTPUT_DIR)/; \
		echo "$(GREEN)✅ Image PNG générée: $(OUTPUT_DIR)/technoprod-architecture.png$(NC)"; \
	fi
	@if [ -f technoprod-architecture.svg ]; then \
		mv technoprod-architecture.svg $(OUTPUT_DIR)/; \
		echo "$(GREEN)✅ Image SVG générée: $(OUTPUT_DIR)/technoprod-architecture.svg$(NC)"; \
	fi

analyze-deps: ## Analyser les dépendances avec Deptrac
	@echo "$(BLUE)🔗 Analyse des dépendances avec Deptrac...$(NC)"
	@mkdir -p $(OUTPUT_DIR)
	@if [ ! -f $(DEPTRAC) ]; then \
		echo "$(RED)❌ Deptrac non trouvé. Exécutez: composer require --dev qossmic/deptrac-shim$(NC)"; \
		exit 1; \
	fi
	@$(DEPTRAC) analyze 2>&1 | tee $(OUTPUT_DIR)/deptrac_$(DATE).log
	@if [ -f .deptrac.cache ]; then \
		mv .deptrac.cache $(OUTPUT_DIR)/; \
	fi
	@echo "$(GREEN)✅ Analyse Deptrac terminée. Consultez: $(OUTPUT_DIR)/deptrac_$(DATE).log$(NC)"

analyze-deps-graph: ## Générer un graphique des dépendances avec Deptrac
	@echo "$(BLUE)🎨 Génération du graphique des dépendances...$(NC)"
	@mkdir -p $(OUTPUT_DIR)
	@$(DEPTRAC) analyze --formatter=graphviz --output=$(OUTPUT_DIR)/dependencies-graph.dot
	@if command -v dot >/dev/null 2>&1; then \
		dot -Tpng $(OUTPUT_DIR)/dependencies-graph.dot -o $(OUTPUT_DIR)/dependencies-graph.png; \
		dot -Tsvg $(OUTPUT_DIR)/dependencies-graph.dot -o $(OUTPUT_DIR)/dependencies-graph.svg; \
		echo "$(GREEN)✅ Graphiques générés: dependencies-graph.png et dependencies-graph.svg$(NC)"; \
	else \
		echo "$(YELLOW)⚠️ GraphViz non installé. Exécutez: make install-tools$(NC)"; \
	fi

install-tools: ## Installer les outils système (nécessite sudo)
	@echo "$(BLUE)🔧 Installation des outils système...$(NC)"
	@if command -v apt-get >/dev/null 2>&1; then \
		echo "$(YELLOW)Installation de GraphViz et PlantUML...$(NC)"; \
		sudo apt-get update && sudo apt-get install -y graphviz plantuml; \
		echo "$(GREEN)✅ Outils installés avec succès$(NC)"; \
	elif command -v yum >/dev/null 2>&1; then \
		echo "$(YELLOW)Installation avec yum...$(NC)"; \
		sudo yum install -y graphviz plantuml; \
	elif command -v brew >/dev/null 2>&1; then \
		echo "$(YELLOW)Installation avec Homebrew...$(NC)"; \
		brew install graphviz plantuml; \
	else \
		echo "$(RED)❌ Gestionnaire de paquets non supporté$(NC)"; \
		exit 1; \
	fi

generate-docs: ## Générer la documentation complète
	@echo "$(BLUE)📚 Génération de la documentation...$(NC)"
	@mkdir -p $(OUTPUT_DIR)/docs
	@echo "# 📊 Documentation Architecture TechnoProd" > $(OUTPUT_DIR)/docs/README.md
	@echo "" >> $(OUTPUT_DIR)/docs/README.md
	@echo "Générée le: $(shell date)" >> $(OUTPUT_DIR)/docs/README.md
	@echo "" >> $(OUTPUT_DIR)/docs/README.md
	@echo "## 📁 Fichiers disponibles" >> $(OUTPUT_DIR)/docs/README.md
	@echo "" >> $(OUTPUT_DIR)/docs/README.md
	@echo "- **technoprod-architecture.json** : Données structurées de l'architecture" >> $(OUTPUT_DIR)/docs/README.md
	@echo "- **technoprod-architecture-report.md** : Rapport d'analyse détaillé" >> $(OUTPUT_DIR)/docs/README.md
	@echo "- **technoprod-architecture.dot** : Fichier source GraphViz" >> $(OUTPUT_DIR)/docs/README.md
	@echo "- **technoprod-architecture.png/.svg** : Diagrammes visuels (si GraphViz installé)" >> $(OUTPUT_DIR)/docs/README.md
	@echo "- **dependencies-graph.png/.svg** : Graphiques des dépendances Deptrac" >> $(OUTPUT_DIR)/docs/README.md
	@echo "- **deptrac_*.log** : Logs d'analyse des violations architecturales" >> $(OUTPUT_DIR)/docs/README.md
	@echo "" >> $(OUTPUT_DIR)/docs/README.md
	@echo "## 🚀 Usage" >> $(OUTPUT_DIR)/docs/README.md
	@echo "" >> $(OUTPUT_DIR)/docs/README.md
	@echo "\`\`\`bash" >> $(OUTPUT_DIR)/docs/README.md
	@echo "# Analyse complète" >> $(OUTPUT_DIR)/docs/README.md
	@echo "make all" >> $(OUTPUT_DIR)/docs/README.md
	@echo "" >> $(OUTPUT_DIR)/docs/README.md
	@echo "# Analyse spécifique" >> $(OUTPUT_DIR)/docs/README.md
	@echo "make analyze-arch    # Architecture uniquement" >> $(OUTPUT_DIR)/docs/README.md
	@echo "make analyze-deps    # Dépendances uniquement" >> $(OUTPUT_DIR)/docs/README.md
	@echo "\`\`\`" >> $(OUTPUT_DIR)/docs/README.md
	@echo "$(GREEN)✅ Documentation générée: $(OUTPUT_DIR)/docs/README.md$(NC)"

clean: ## Nettoyer les fichiers temporaires
	@echo "$(BLUE)🧹 Nettoyage des fichiers temporaires...$(NC)"
	@rm -f *.dot *.png *.svg *.json *.log
	@rm -f .deptrac.cache
	@echo "$(GREEN)✅ Nettoyage terminé$(NC)"

clean-all: clean ## Nettoyer tous les rapports
	@echo "$(BLUE)🧹 Suppression de tous les rapports...$(NC)"
	@rm -rf $(OUTPUT_DIR)
	@echo "$(GREEN)✅ Tous les rapports supprimés$(NC)"

status: ## Afficher le statut des outils
	@echo "$(BLUE)📋 Statut des outils d'analyse$(NC)"
	@echo ""
	@echo -n "Script d'analyse: "
	@if [ -x $(SCRIPT_ANALYZER) ]; then \
		echo "$(GREEN)✅ Disponible$(NC)"; \
	else \
		echo "$(RED)❌ Non trouvé ou non exécutable$(NC)"; \
	fi
	@echo -n "Deptrac: "
	@if [ -f $(DEPTRAC) ]; then \
		echo "$(GREEN)✅ Installé$(NC)"; \
	else \
		echo "$(RED)❌ Non installé$(NC)"; \
	fi
	@echo -n "GraphViz: "
	@if command -v dot >/dev/null 2>&1; then \
		echo "$(GREEN)✅ Installé$(NC)"; \
	else \
		echo "$(YELLOW)⚠️ Non installé$(NC)"; \
	fi
	@echo -n "PlantUML: "
	@if command -v plantuml >/dev/null 2>&1; then \
		echo "$(GREEN)✅ Installé$(NC)"; \
	else \
		echo "$(YELLOW)⚠️ Non installé$(NC)"; \
	fi
	@echo ""
	@if [ -d $(OUTPUT_DIR) ]; then \
		echo "$(BLUE)📁 Rapports existants:$(NC)"; \
		ls -la $(OUTPUT_DIR)/ 2>/dev/null || echo "Aucun rapport trouvé"; \
	else \
		echo "$(YELLOW)📁 Aucun répertoire de rapports trouvé$(NC)"; \
	fi

# Target par défaut
.DEFAULT_GOAL := help