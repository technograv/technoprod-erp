#!/usr/bin/env node

const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

/**
 * Serveur MCP HTTPS pour TechnoProd
 * Compatible avec Google OAuth (HTTPS obligatoire)
 */

const PORT = 3001;
const SYMFONY_URL = 'https://127.0.0.1:8080';

// Configuration HTTPS avec certificats auto-signés
const httpsOptions = {
    key: fs.readFileSync(path.join(__dirname, 'mcp-key.pem')),
    cert: fs.readFileSync(path.join(__dirname, 'mcp-cert.pem')),
    // Ignorer les certificats auto-signés
    rejectUnauthorized: false
};

// Types MIME
const mimeTypes = {
    '.html': 'text/html',
    '.css': 'text/css',
    '.js': 'application/javascript',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.svg': 'image/svg+xml',
    '.json': 'application/json'
};

function injectLiveReload(html) {
    const liveReloadScript = `
    <script>
    // Live reload HTTPS pour TechnoProd + OAuth
    (function() {
        // WebSocket sécurisé
        const ws = new WebSocket('wss://localhost:${PORT + 1}');
        ws.onmessage = function(event) {
            if (event.data === 'reload') {
                window.location.reload();
            }
        };
        
        ws.onclose = function() {
            setTimeout(() => window.location.reload(), 2000);
        };
        
        // Indicateur MCP HTTPS
        const style = document.createElement('style');
        style.textContent = \`
            #mcp-debug {
                position: fixed;
                top: 10px;
                right: 10px;
                background: rgba(0,128,0,0.9);
                color: white;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 10000;
                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }
        \`;
        document.head.appendChild(style);
        
        const indicator = document.createElement('div');
        indicator.id = 'mcp-debug';
        indicator.textContent = '🔒 MCP HTTPS + OAuth';
        document.body.appendChild(indicator);
    })();
    </script>
    `;
    
    return html.replace('</body>', liveReloadScript + '</body>');
}

// Agent HTTPS pour ignorer les certificats auto-signés
const httpsAgent = new https.Agent({
    rejectUnauthorized: false
});

const server = https.createServer(httpsOptions, async (req, res) => {
    try {
        console.log(`📡 Requête MCP: ${req.method} ${req.url}`);
        
        // Headers CORS pour développement
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE');
        res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        // Proxy intelligent vers Symfony
        if (req.url.includes('secteur') || req.url.includes('admin') || req.url === '/') {
            
            // Préparer les options de requête
            const requestOptions = {
                method: req.method,
                headers: {
                    ...req.headers,
                    'host': '127.0.0.1:8080',  // Correction du host
                    'x-forwarded-proto': 'https',
                    'x-forwarded-for': req.connection.remoteAddress
                },
                agent: httpsAgent
            };
            
            // Effectuer la requête vers Symfony
            const symfonyReq = https.request(SYMFONY_URL + req.url, requestOptions, (symfonyRes) => {
                
                // Copier les headers (sauf ceux problématiques)
                Object.keys(symfonyRes.headers).forEach(key => {
                    if (!['transfer-encoding', 'connection'].includes(key.toLowerCase())) {
                        res.setHeader(key, symfonyRes.headers[key]);
                    }
                });
                
                res.statusCode = symfonyRes.statusCode;
                
                let data = '';
                symfonyRes.on('data', chunk => data += chunk);
                symfonyRes.on('end', () => {
                    // Injection du live-reload uniquement pour HTML
                    if (symfonyRes.headers['content-type']?.includes('text/html')) {
                        data = injectLiveReload(data);
                    }
                    res.end(data);
                });
            });
            
            // Gestion des erreurs
            symfonyReq.on('error', (error) => {
                console.error('❌ Erreur Symfony:', error.message);
                res.statusCode = 500;
                res.setHeader('Content-Type', 'text/html');
                res.end(`
                    <h2>🚨 Erreur MCP</h2>
                    <p><strong>Erreur:</strong> ${error.message}</p>
                    <p><strong>URL:</strong> ${SYMFONY_URL + req.url}</p>
                    <p><strong>Conseil:</strong> Vérifiez que Symfony tourne sur HTTPS</p>
                    <hr>
                    <a href="https://127.0.0.1:8080${req.url}">🔗 Accès direct Symfony</a>
                `);
            });
            
            // Transférer le body pour POST/PUT
            if (req.method === 'POST' || req.method === 'PUT') {
                req.pipe(symfonyReq);
            } else {
                symfonyReq.end();
            }
            
        } else {
            // Fichiers statiques depuis public/
            const filePath = path.join(__dirname, 'public', req.url);
            if (fs.existsSync(filePath)) {
                const ext = path.extname(filePath);
                const contentType = mimeTypes[ext] || 'application/octet-stream';
                
                const content = fs.readFileSync(filePath);
                res.writeHead(200, { 'Content-Type': contentType });
                res.end(content);
            } else {
                res.writeHead(404);
                res.end('File not found');
            }
        }
        
    } catch (error) {
        console.error('❌ Erreur serveur MCP:', error);
        res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Erreur interne MCP: ' + error.message);
    }
});

server.listen(PORT, () => {
    console.log(`🔒 Serveur MCP HTTPS démarré sur https://localhost:${PORT}`);
    console.log(`📡 Proxy sécurisé vers: ${SYMFONY_URL}`);
    console.log(`🚀 Compatible Google OAuth et authentification`);
    console.log(`🎨 Hot-reload activé pour développement`);
    console.log(`✅ Prêt pour inspection visuelle des InfoWindows !`);
    console.log(`\n🌐 URLs disponibles:`);
    console.log(`   - Interface secteurs: https://localhost:${PORT}/admin/secteurs`);
    console.log(`   - Symfony original: ${SYMFONY_URL}/admin/secteurs`);
});