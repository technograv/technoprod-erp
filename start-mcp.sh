#!/bin/bash

echo "🚀 Démarrage environnement MCP TechnoProd"

# Vérifier que Symfony tourne
if ! curl -k -s https://127.0.0.1:8080 > /dev/null; then
    echo "⚠️  Symfony n'est pas démarré, lancement..."
    symfony server:start -d --port=8080
    sleep 3
fi

echo "✅ Symfony actif sur https://127.0.0.1:8080"

# Démarrer le serveur MCP
echo "🎯 Démarrage serveur MCP..."
node mcp-server.js &
MCP_PID=$!

echo "📡 Serveur MCP actif sur http://localhost:3001"
echo "🎨 Interface secteurs avec MCP: http://localhost:3001/admin/secteurs"
echo ""
echo "🔧 Commandes utiles:"
echo "  - Arrêter MCP: kill $MCP_PID"
echo "  - Voir logs Symfony: symfony server:log"
echo "  - Redémarrer: ./start-mcp.sh"
echo ""
echo "✨ Prêt pour inspection visuelle et modifications CSS en temps réel !"

# Garder le script actif
wait $MCP_PID