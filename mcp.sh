#!/bin/bash

# Script de gestion MCP TechnoProd

case "$1" in
    start)
        echo "🚀 Démarrage MCP TechnoProd..."
        
        # Vérifier si MCP tourne déjà
        if pgrep -f "node.*mcp-simple" > /dev/null; then
            echo "⚠️  MCP déjà en cours d'exécution"
            exit 1
        fi
        
        # Vérifier Symfony
        if ! curl -k -s https://127.0.0.1:8080 > /dev/null; then
            echo "❌ Symfony n'est pas accessible sur https://127.0.0.1:8080"
            echo "💡 Démarrez Symfony : symfony server:start -d"
            exit 1
        fi
        
        # Démarrer MCP
        node mcp-simple.js &
        MCP_PID=$!
        sleep 2
        
        # Vérifier le démarrage
        if curl -s -o /dev/null -w "%{http_code}" http://localhost:3001 | grep -q "200\|302"; then
            echo "✅ MCP démarré avec succès (PID: $MCP_PID)"
            echo "🌐 Interface secteurs: http://localhost:3001/admin/secteurs"
        else
            echo "❌ Erreur de démarrage MCP"
            kill $MCP_PID 2>/dev/null
            exit 1
        fi
        ;;
        
    stop)
        echo "🛑 Arrêt MCP TechnoProd..."
        if pkill -f "node.*mcp-simple"; then
            echo "✅ MCP arrêté"
        else
            echo "⚠️  Aucun processus MCP trouvé"
        fi
        ;;
        
    status)
        if pgrep -f "node.*mcp-simple" > /dev/null; then
            PID=$(pgrep -f "node.*mcp-simple")
            echo "✅ MCP actif (PID: $PID)"
            echo "🌐 Interface: http://localhost:3001/admin (onglet Secteurs)"
            
            # Test de connectivité
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:3001)
            if [[ "$HTTP_CODE" =~ ^[23] ]]; then
                echo "🔗 Connectivité: OK ($HTTP_CODE)"
            else
                echo "⚠️  Connectivité: Problème ($HTTP_CODE)"
            fi
        else
            echo "❌ MCP non démarré"
        fi
        ;;
        
    restart)
        $0 stop
        sleep 2
        $0 start
        ;;
        
    logs)
        echo "📋 Logs MCP (Ctrl+C pour arrêter):"
        tail -f /tmp/mcp.log 2>/dev/null || echo "Pas de logs disponibles"
        ;;
        
    *)
        echo "🎯 Gestionnaire MCP TechnoProd"
        echo ""
        echo "Usage: $0 {start|stop|status|restart|logs}"
        echo ""
        echo "Commandes:"
        echo "  start   - Démarrer le serveur MCP"
        echo "  stop    - Arrêter le serveur MCP"  
        echo "  status  - Vérifier l'état du MCP"
        echo "  restart - Redémarrer le MCP"
        echo "  logs    - Voir les logs en temps réel"
        echo ""
        echo "URLs après démarrage:"
        echo "  🌐 Interface secteurs: http://localhost:3001/admin (onglet Secteurs)"
        echo "  🔗 Symfony original: https://127.0.0.1:8080/admin (onglet Secteurs)"
        ;;
esac