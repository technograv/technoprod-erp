#!/bin/bash

echo "🔒 Démarrage environnement MCP HTTPS TechnoProd"
echo "   Compatible Google OAuth et authentification sécurisée"

# Vérifier que Symfony tourne en HTTPS
if ! curl -k -s https://127.0.0.1:8080 > /dev/null; then
    echo "⚠️  Symfony HTTPS n'est pas accessible, vérification..."
    symfony server:status
    echo "💡 Si nécessaire, redémarrez Symfony avec: symfony server:start -d"
    exit 1
fi

echo "✅ Symfony HTTPS actif sur https://127.0.0.1:8080"

# Vérifier les certificats MCP
if [[ ! -f "mcp-key.pem" || ! -f "mcp-cert.pem" ]]; then
    echo "🔐 Génération des certificats SSL pour MCP..."
    openssl req -x509 -newkey rsa:2048 -keyout mcp-key.pem -out mcp-cert.pem -days 365 -nodes -subj "/C=FR/ST=France/L=Local/O=TechnoProd/CN=localhost"
    echo "✅ Certificats générés"
fi

# Arrêter l'ancien MCP s'il tourne
pkill -f "node.*mcp-server" 2>/dev/null

# Démarrer le serveur MCP HTTPS
echo "🎯 Démarrage serveur MCP HTTPS..."
node mcp-server-https.js &
MCP_PID=$!

# Attendre que le serveur démarre
sleep 2

# Test de connectivité
if curl -k -s -o /dev/null -w "%{http_code}" https://localhost:3001 | grep -q "200\|302"; then
    echo "✅ Serveur MCP HTTPS opérationnel"
else
    echo "❌ Problème de démarrage MCP"
    kill $MCP_PID 2>/dev/null
    exit 1
fi

echo "🌐 URLs disponibles:"
echo "  🔒 Interface secteurs MCP: https://localhost:3001/admin/secteurs"
echo "  🔗 Symfony original: https://127.0.0.1:8080/admin/secteurs"
echo ""
echo "🔧 Commandes utiles:"
echo "  - Arrêter MCP: kill $MCP_PID"
echo "  - Voir logs Symfony: symfony server:log"
echo "  - Redémarrer: ./start-mcp-https.sh"
echo ""
echo "✨ Prêt pour inspection visuelle et modifications InfoWindows avec OAuth !"
echo "💡 Acceptez le certificat auto-signé dans votre navigateur"

# Garder le script actif
wait $MCP_PID