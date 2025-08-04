#!/usr/bin/env node

const http = require('http');
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

/**
 * Serveur MCP personnalisé pour TechnoProd
 * Permet l'inspection visuelle et modification en temps réel
 */

const PORT = 3001;
const SYMFONY_URL = 'https://127.0.0.1:8080';

// Types MIME pour les fichiers statiques
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
    // Live reload pour développement TechnoProd
    (function() {
        const ws = new WebSocket('ws://localhost:${PORT + 1}');
        ws.onmessage = function(event) {
            if (event.data === 'reload') {
                window.location.reload();
            }
        };
        
        // Reconnexion automatique
        ws.onclose = function() {
            setTimeout(() => window.location.reload(), 1000);
        };
        
        // Styles pour debug MCP
        const style = document.createElement('style');
        style.textContent = \`
            #mcp-debug {
                position: fixed;
                top: 10px;
                right: 10px;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 10000;
            }
        \`;
        document.head.appendChild(style);
        
        // Indicateur MCP actif
        const indicator = document.createElement('div');
        indicator.id = 'mcp-debug';
        indicator.textContent = '🚀 MCP Active';
        document.body.appendChild(indicator);
    })();
    </script>
    `;
    
    return html.replace('</body>', liveReloadScript + '</body>');
}

const server = http.createServer(async (req, res) => {
    try {
        // Proxy vers Symfony pour les pages HTML
        if (req.url.endsWith('/') || req.url.includes('secteur') || req.url.includes('admin')) {
            const response = await fetch(SYMFONY_URL + req.url);
            const html = await response.text();
            
            res.writeHead(200, { 'Content-Type': 'text/html' });
            res.end(injectLiveReload(html));
            return;
        }
        
        // Servir les fichiers statiques depuis public/
        const filePath = path.join(__dirname, 'public', req.url);
        if (fs.existsSync(filePath)) {
            const ext = path.extname(filePath);
            const contentType = mimeTypes[ext] || 'application/octet-stream';
            
            const content = fs.readFileSync(filePath);
            res.writeHead(200, { 'Content-Type': contentType });
            res.end(content);
            return;
        }
        
        // Fallback vers Symfony
        const response = await fetch(SYMFONY_URL + req.url);
        const content = await response.text();
        
        res.writeHead(response.status, { 'Content-Type': response.headers.get('content-type') });
        res.end(content);
        
    } catch (error) {
        res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Erreur MCP: ' + error.message);
    }
});

console.log(`🚀 Serveur MCP TechnoProd démarré sur http://localhost:${PORT}`);
console.log(`📡 Proxy vers Symfony: ${SYMFONY_URL}`);
console.log(`🎨 Hot-reload activé pour développement`);

server.listen(PORT, () => {
    console.log(`✅ Prêt pour inspection visuelle et modifications en temps réel !`);
});