#!/usr/bin/env node

const http = require('http');
const https = require('https');
const { URL } = require('url');

/**
 * Serveur MCP simplifié pour TechnoProd
 * Proxy intelligent vers Symfony HTTPS
 */

const PORT = 3001;
const SYMFONY_URL = 'https://127.0.0.1:8080';

// Agent HTTPS qui ignore les certificats auto-signés
const httpsAgent = new https.Agent({
    rejectUnauthorized: false
});

function injectMCPIndicator(html) {
    const mcpScript = `
    <script>
        // Indicateur MCP actif
        const style = document.createElement('style');
        style.textContent = \`
            #mcp-indicator {
                position: fixed;
                top: 10px;
                right: 10px;
                background: linear-gradient(45deg, #28a745, #20c997);
                color: white;
                padding: 8px 12px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: bold;
                z-index: 10000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                opacity: 0.9;
            }
        \`;
        document.head.appendChild(style);
        
        const indicator = document.createElement('div');
        indicator.id = 'mcp-indicator';
        indicator.textContent = '🎯 MCP Active - Debug Mode';
        document.body.appendChild(indicator);
        
        console.log('🎯 MCP Debug Mode actif - Modifications CSS en temps réel');
    </script>
    `;
    
    return html.replace('</body>', mcpScript + '</body>');
}

const server = http.createServer((req, res) => {
    console.log(`📡 ${new Date().toLocaleTimeString()} - ${req.method} ${req.url}`);
    
    // Options pour la requête vers Symfony
    const targetUrl = new URL(req.url, SYMFONY_URL);
    const options = {
        hostname: targetUrl.hostname,
        port: targetUrl.port,
        path: targetUrl.pathname + targetUrl.search,
        method: req.method,
        headers: {
            ...req.headers,
            host: '127.0.0.1:8080'
        },
        agent: httpsAgent
    };
    
    // Effectuer la requête vers Symfony
    const proxyReq = https.request(options, (proxyRes) => {
        // Copier les headers de réponse
        res.writeHead(proxyRes.statusCode, proxyRes.headers);
        
        // Pour les pages HTML, injecter l'indicateur MCP
        if (proxyRes.headers['content-type']?.includes('text/html')) {
            let data = '';
            proxyRes.on('data', chunk => data += chunk);
            proxyRes.on('end', () => {
                const modifiedData = injectMCPIndicator(data);
                res.end(modifiedData);
            });
        } else {
            // Pour les autres fichiers, pipe direct
            proxyRes.pipe(res);
        }
    });
    
    proxyReq.on('error', (error) => {
        console.error('❌ Erreur proxy:', error.message);
        res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(`
            <div style="font-family: Arial; padding: 20px; max-width: 600px; margin: 50px auto;">
                <h2 style="color: #dc3545;">🚨 Erreur MCP Proxy</h2>
                <p><strong>Erreur:</strong> ${error.message}</p>
                <p><strong>URL cible:</strong> ${SYMFONY_URL}${req.url}</p>
                <hr>
                <h3>💡 Solutions possibles :</h3>
                <ul>
                    <li>Vérifiez que Symfony tourne : <code>symfony server:status</code></li>
                    <li>Redémarrez Symfony : <code>symfony server:start -d</code></li>
                    <li>Accès direct : <a href="${SYMFONY_URL}${req.url}">Symfony direct</a></li>
                </ul>
            </div>
        `);
    });
    
    // Transférer le body de la requête
    req.pipe(proxyReq);
});

server.listen(PORT, () => {
    console.log(`🚀 Serveur MCP simplifié démarré sur http://localhost:${PORT}`);
    console.log(`📡 Proxy vers Symfony: ${SYMFONY_URL}`);
    console.log(`🎯 Mode debug actif pour modifications CSS`);
    console.log(`\n🌐 URLs de test:`);
    console.log(`   - Interface secteurs: http://localhost:${PORT}/admin/secteurs`);
    console.log(`   - Page d'accueil: http://localhost:${PORT}/`);
    console.log(`\n✅ Prêt pour inspection visuelle des InfoWindows !`);
});