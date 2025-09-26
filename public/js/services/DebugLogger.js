/**
 * DebugLogger - Système de logs centralisé pour debug rapide
 * @version 1.0.0
 */
class DebugLogger {
    constructor(serviceName = 'Unknown', enabled = true) {
        this.serviceName = serviceName;
        this.enabled = enabled;
        this.logs = [];
        this.startTime = Date.now();
        
        // Couleurs pour la console
        this.colors = {
            info: '#4CAF50',
            warn: '#FF9800',
            error: '#F44336',
            debug: '#2196F3',
            success: '#8BC34A',
            critical: '#E91E63'
        };
        
        // Auto-expose globalement pour debug rapide
        if (!window.debugLoggers) {
            window.debugLoggers = {};
        }
        window.debugLoggers[serviceName] = this;
        
        // Panneau de debug désactivé pour la production
        // this.createDebugPanel();
    }
    
    createDebugPanel() {
        // Vérifier si le panneau existe déjà
        if (document.getElementById('debug-panel')) return;
        
        // Créer le panneau HTML
        const panel = document.createElement('div');
        panel.id = 'debug-panel';
        panel.style.cssText = `
            position: fixed;
            bottom: 0;
            right: 0;
            width: 500px;
            max-height: 300px;
            background: rgba(0,0,0,0.9);
            color: #00ff00;
            font-family: monospace;
            font-size: 11px;
            padding: 10px;
            overflow-y: auto;
            z-index: 99999;
            border: 1px solid #00ff00;
            display: none;
        `;
        
        // Bouton toggle
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'debug-toggle';
        toggleBtn.textContent = '🐛 Debug';
        toggleBtn.style.cssText = `
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: #333;
            color: #00ff00;
            border: 1px solid #00ff00;
            padding: 5px 10px;
            cursor: pointer;
            z-index: 99998;
            font-family: monospace;
        `;
        toggleBtn.onclick = () => {
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        };
        
        document.body.appendChild(panel);
        document.body.appendChild(toggleBtn);
    }
    
    updateDebugPanel() {
        const panel = document.getElementById('debug-panel');
        if (!panel) return;
        
        const lastLogs = this.logs.slice(-50); // Derniers 50 logs
        panel.innerHTML = `
            <div style="border-bottom: 1px solid #00ff00; padding-bottom: 5px; margin-bottom: 5px;">
                <strong>🔍 ${this.serviceName} - Debug Console</strong>
                <button onclick="window.debugLoggers['${this.serviceName}'].clear()" style="float: right; background: #333; color: #00ff00; border: 1px solid #00ff00; cursor: pointer;">Clear</button>
            </div>
            ${lastLogs.map(log => this.formatLogHTML(log)).join('')}
        `;
        
        // Auto-scroll vers le bas
        panel.scrollTop = panel.scrollHeight;
    }
    
    formatLogHTML(log) {
        const timestamp = new Date(log.timestamp).toLocaleTimeString();
        const color = this.colors[log.level] || '#fff';
        const icon = this.getIcon(log.level);
        
        let dataStr = '';
        if (log.data) {
            try {
                dataStr = JSON.stringify(log.data, null, 2);
            } catch (e) {
                dataStr = String(log.data);
            }
        }
        
        return `
            <div style="margin: 2px 0; padding: 2px; border-left: 3px solid ${color};">
                <span style="color: ${color}">${icon} [${timestamp}] ${log.message}</span>
                ${dataStr ? `<pre style="margin: 2px 0 0 20px; color: #888; font-size: 10px;">${dataStr}</pre>` : ''}
            </div>
        `;
    }
    
    getIcon(level) {
        const icons = {
            info: 'ℹ️',
            warn: '⚠️',
            error: '❌',
            debug: '🔧',
            success: '✅',
            critical: '🚨'
        };
        return icons[level] || '📝';
    }
    
    log(level, message, data = null) {
        if (!this.enabled) return;
        
        const logEntry = {
            timestamp: Date.now(),
            level,
            message,
            data,
            stack: new Error().stack
        };
        
        this.logs.push(logEntry);
        
        // Console log avec style
        const color = this.colors[level] || '#fff';
        const icon = this.getIcon(level);
        const elapsed = ((Date.now() - this.startTime) / 1000).toFixed(2);
        
        console.log(
            `%c${icon} [${this.serviceName}] +${elapsed}s ${message}`,
            `color: ${color}; font-weight: bold;`,
            data || ''
        );
        
        // Si erreur critique, afficher la stack
        if (level === 'error' || level === 'critical') {
            console.trace();
        }
        
        // Mettre à jour le panneau visuel
        this.updateDebugPanel();
    }
    
    // Méthodes raccourcies
    info(message, data) { this.log('info', message, data); }
    warn(message, data) { this.log('warn', message, data); }
    error(message, data) { this.log('error', message, data); }
    debug(message, data) { this.log('debug', message, data); }
    success(message, data) { this.log('success', message, data); }
    critical(message, data) { this.log('critical', message, data); }
    
    // Tracer une fonction
    trace(functionName, args) {
        this.debug(`📍 ${functionName} appelée`, args);
    }
    
    // Mesurer le temps
    time(label) {
        this.timers = this.timers || {};
        this.timers[label] = Date.now();
        this.debug(`⏱️ Timer '${label}' démarré`);
    }
    
    timeEnd(label) {
        if (!this.timers || !this.timers[label]) {
            this.warn(`Timer '${label}' non trouvé`);
            return;
        }
        const elapsed = Date.now() - this.timers[label];
        this.info(`⏱️ Timer '${label}': ${elapsed}ms`);
        delete this.timers[label];
    }
    
    // Grouper les logs
    group(label) {
        console.group(`[${this.serviceName}] ${label}`);
        this.info(`📂 Groupe: ${label}`);
    }
    
    groupEnd() {
        console.groupEnd();
    }
    
    // Clear logs
    clear() {
        this.logs = [];
        const panel = document.getElementById('debug-panel');
        if (panel) {
            panel.innerHTML = `<div style="color: #888;">Logs cleared</div>`;
        }
        console.clear();
    }
    
    // Export logs pour analyse
    export() {
        const data = {
            service: this.serviceName,
            startTime: this.startTime,
            logs: this.logs
        };
        const json = JSON.stringify(data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `debug-${this.serviceName}-${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);
        this.success('Logs exportés');
    }
    
    // Afficher un résumé
    summary() {
        const summary = {
            service: this.serviceName,
            totalLogs: this.logs.length,
            errors: this.logs.filter(l => l.level === 'error').length,
            warnings: this.logs.filter(l => l.level === 'warn').length,
            elapsed: ((Date.now() - this.startTime) / 1000).toFixed(2) + 's'
        };
        console.table(summary);
        return summary;
    }
}

// Fonction globale de debug rapide
window.quickDebug = function(message, data) {
    if (!window.globalDebugLogger) {
        window.globalDebugLogger = new DebugLogger('GLOBAL', true);
    }
    window.globalDebugLogger.info(message, data);
};

// Intercepter les erreurs globales
window.addEventListener('error', function(event) {
    if (window.globalDebugLogger) {
        window.globalDebugLogger.critical(`Erreur globale: ${event.message}`, {
            file: event.filename,
            line: event.lineno,
            col: event.colno,
            error: event.error
        });
    }
});

// Export pour utilisation
window.DebugLogger = DebugLogger;