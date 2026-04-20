/**
 * Module Dashboard Commercial - TechnoProd V2.2
 * Gestion des onglets secteur/performances et système d'alertes
 */

class CommercialDashboard {
    constructor(config) {
        console.log('🚀 [DEBUG] CommercialDashboard INIT avec config:', config);
        this.googleMapsApiKey = config.googleMapsApiKey;
        this.currentWeekOffset = config.currentWeekOffset || 0;
        
        console.log('🔍 [DEBUG] Config parsée:', {
            googleMapsApiKey: this.googleMapsApiKey ? this.googleMapsApiKey.substring(0,10) + '...' : 'MANQUANT',
            currentWeekOffset: this.currentWeekOffset
        });
        
        this.initializeGlobalVariables();
        this.initializeEventListeners();
        console.log('✅ [DEBUG] CommercialDashboard initialisé avec succès');
    }

    initializeGlobalVariables() {
        window.currentWeekOffset = this.currentWeekOffset;
        window.googleMapsLoaded = window.googleMapsLoaded || false;
    }

    initializeEventListeners() {
        console.log('🎧 [DEBUG] Initialisation des event listeners...');
        document.addEventListener('DOMContentLoaded', () => {
            console.log('📄 [DEBUG] DOM Content Loaded déclenché');
            
            // Vérifier présence onglets
            const monSecteurTab = document.getElementById('mon-secteur-tab');
            const mesPerformancesTab = document.getElementById('mes-performances-tab');
            
            console.log('🔍 [DEBUG] Onglets trouvés:', {
                monSecteur: monSecteurTab ? 'OUI' : 'NON',
                mesPerformances: mesPerformancesTab ? 'OUI' : 'NON',
                monSecteurActive: monSecteurTab?.classList.contains('active') ? 'OUI' : 'NON'
            });
            
            // Initialiser l'onglet Mon Secteur au chargement de la page
            if (monSecteurTab && monSecteurTab.classList.contains('active')) {
                console.log('📍 [DEBUG] Onglet Mon Secteur actif, initialisation...');
                this.initialiserMonSecteur();
            }
            
            // Initialiser quand on clique sur l'onglet Mon Secteur
            if (monSecteurTab) {
                monSecteurTab.addEventListener('click', () => {
                    console.log('👆 [DEBUG] Clic sur onglet Mon Secteur');
                    setTimeout(() => {
                        this.initialiserMonSecteur();
                    }, 300);
                });
            }
            
            // Initialiser quand on clique sur l'onglet Mes Performances
            if (mesPerformancesTab) {
                mesPerformancesTab.addEventListener('click', () => {
                    console.log('👆 [DEBUG] Clic sur onglet Mes Performances');
                    setTimeout(() => {
                        this.initialiserMesPerformances();
                    }, 300);
                });
            }
            
            // Charger les alertes au chargement de la page
            console.log('🔔 [DEBUG] Activation du chargement des alertes...');
            this.chargerMesAlertes();
        });
    }

    // === GOOGLE MAPS ===
    loadGoogleMapsApi() {
        console.log('🌍 [DEBUG] Chargement Google Maps API...');
        console.log('🔍 [DEBUG] État initial:', {
            googleMapsLoaded: window.googleMapsLoaded,
            googleExists: typeof google !== 'undefined',
            googleMapsExists: typeof google !== 'undefined' && typeof google.maps !== 'undefined',
            apiKey: this.googleMapsApiKey ? this.googleMapsApiKey.substring(0, 10) + '...' : 'MANQUANT'
        });
        
        if (!window.googleMapsLoaded && (typeof google === 'undefined' || typeof google.maps === 'undefined')) {
            console.log('📡 [DEBUG] Chargement du script Google Maps API...');
            window.googleMapsLoaded = true;
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${this.googleMapsApiKey}&libraries=geometry&callback=initGoogleMapsCallback`;
            script.defer = true;
            script.async = true;
            script.onerror = () => console.error('❌ [DEBUG] Erreur chargement script Google Maps');
            script.onload = () => console.log('✅ [DEBUG] Script Google Maps chargé');
            document.head.appendChild(script);
            console.log('🗺️ [DEBUG] Script Google Maps ajouté au DOM:', script.src);
        } else {
            console.log('🔄 [DEBUG] Google Maps API déjà chargée, réutilisation');
        }

        // Callback global pour l'initialisation Google Maps
        window.initGoogleMapsCallback = () => {
            console.log('✅ [DEBUG] Callback Google Maps déclenché - API prête!');
        };
    }

    // === ONGLET MON SECTEUR ===
    async initialiserMonSecteur() {
        console.log('🚀 [DEBUG] Initialisation Mon Secteur...');
        
        // Vérifier présence DOM
        const secteurTab = document.getElementById('mon-secteur');
        console.log('🔍 [DEBUG] Élément mon-secteur trouvé:', secteurTab ? 'OUI' : 'NON');
        
        // Afficher loading pour contrats actifs
        const contractsLoading = document.getElementById('contrats-actifs-loading');
        const contractsList = document.getElementById('contrats-actifs-liste');
        
        console.log('🔍 [DEBUG] Éléments contrats trouvés:', {
            loading: contractsLoading ? 'OUI' : 'NON',
            liste: contractsList ? 'OUI' : 'NON'
        });
        
        if (contractsLoading) contractsLoading.style.display = 'block';
        if (contractsList) contractsList.style.display = 'none';
        
        try {
            console.log('📡 [DEBUG] Appel API /workflow/dashboard/mon-secteur...');
            const response = await fetch('/workflow/dashboard/mon-secteur', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('📡 [DEBUG] Réponse API reçue:', {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok,
                headers: [...response.headers.entries()]
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('📊 [DEBUG] Données secteur reçues:', data);
            
            if (data && data.success) {
                console.log('✅ [DEBUG] Données valides, affichage secteur...');
                this.afficherMonSecteur(data);
            } else {
                console.error('❌ [DEBUG] Erreur dans data.success:', data?.message || 'Pas de message d\'erreur');
                this.afficherErreurSecteur(data?.message || 'Erreur inconnue');
            }
            
        } catch (error) {
            console.error('💥 [DEBUG] Exception chargement secteur:', {
                message: error.message,
                stack: error.stack,
                type: typeof error
            });
            this.afficherErreurSecteur('Erreur de communication avec le serveur: ' + error.message);
        }
    }

    afficherMonSecteur(data) {
        const contractsLoading = document.getElementById('contrats-actifs-loading');
        const contractsList = document.getElementById('contrats-actifs-liste');
        
        if (contractsLoading) contractsLoading.style.display = 'none';
        if (contractsList) contractsList.style.display = 'block';
        
        if (data.secteurs && data.secteurs.length > 0) {
            this.initialiserCarteSecteur(data.secteurs, data.contrats_actifs);
            // Skip afficherStatistiquesSecteur for now as DOM structure doesn't match
        } else {
            if (contractsList) {
                contractsList.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        ${data.message || 'Aucun secteur assigné'}
                    </div>
                `;
            }
        }
    }

    afficherErreurSecteur(message) {
        const contractsLoading = document.getElementById('contrats-actifs-loading');
        const contractsList = document.getElementById('contrats-actifs-liste');
        
        if (contractsLoading) contractsLoading.style.display = 'none';
        if (contractsList) {
            contractsList.style.display = 'block';
            contractsList.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
        }
    }

    initialiserCarteSecteur(secteurs, contratsActifs) {
        console.log('🗺️ [DEBUG] Initialisation carte secteur...');
        console.log('🔍 [DEBUG] Google Maps disponible:', typeof google !== 'undefined' && typeof google.maps !== 'undefined' ? 'OUI' : 'NON');
        console.log('📊 [DEBUG] Données reçues:', { 
            secteurs: secteurs ? secteurs.length + ' secteurs' : 'NULL/UNDEFINED',
            contratsActifs: contratsActifs ? contratsActifs.length + ' contrats' : 'NULL/UNDEFINED'
        });
        
        // Vérifier élément carte DOM
        const mapElement = document.getElementById('map-secteur-commercial');
        console.log('🔍 [DEBUG] Élément carte trouvé:', mapElement ? 'OUI' : 'NON');
        if (!mapElement) {
            console.error('❌ [DEBUG] Élément map-secteur-commercial introuvable dans le DOM');
            return;
        }
        
        if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
            console.log('⏳ [DEBUG] Google Maps pas encore chargé, tentative de chargement...');
            this.loadGoogleMapsApi();
            console.log('⏳ [DEBUG] Re-tentative dans 2s...');
            setTimeout(() => this.initialiserCarteSecteur(secteurs, contratsActifs), 2000);
            return;
        }

        // Calcul automatique du centre et bounds basés sur les secteurs
        const bounds = new google.maps.LatLngBounds();
        let hasValidCoords = false;
        let totalLat = 0, totalLng = 0, validSecteurs = 0;
        
        // Première passe : calculer les bounds et le centre
        secteurs.forEach(secteur => {
            const lat = parseFloat(secteur.latitude);
            const lng = parseFloat(secteur.longitude);
            
            if (!isNaN(lat) && !isNaN(lng) && lat !== 43.6 && lng !== 1.4) { // Éviter les coordonnées par défaut
                bounds.extend({ lat, lng });
                totalLat += lat;
                totalLng += lng;
                validSecteurs++;
                hasValidCoords = true;
            }
        });
        
        // Calculer le centre optimal
        let center = { lat: 43.6, lng: 1.4 }; // Toulouse par défaut
        let zoom = 8; // Zoom par défaut
        
        if (hasValidCoords && validSecteurs > 0) {
            center = {
                lat: totalLat / validSecteurs,
                lng: totalLng / validSecteurs
            };
            
            // Calculer un zoom approprié selon la dispersion des secteurs
            if (validSecteurs === 1) {
                zoom = 11; // Zoom rapproché pour un seul secteur
            } else {
                // Zoom basé sur la distance entre les points les plus éloignés
                const ne = bounds.getNorthEast();
                const sw = bounds.getSouthWest();
                const distance = Math.sqrt(
                    Math.pow(ne.lat() - sw.lat(), 2) + 
                    Math.pow(ne.lng() - sw.lng(), 2)
                );
                
                if (distance < 0.1) zoom = 12;      // Très proche
                else if (distance < 0.3) zoom = 10; // Proche  
                else if (distance < 0.8) zoom = 9;  // Moyen
                else zoom = 8;                      // Éloigné
            }
        }
        
        console.log('📍 [DEBUG] Centre calculé:', center, 'Zoom:', zoom, 'Secteurs valides:', validSecteurs);

        const map = new google.maps.Map(document.getElementById('map-secteur-commercial'), {
            zoom: zoom,
            center: center,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });
        
        console.log('📍 [DEBUG] Création des marqueurs pour', secteurs.length, 'secteurs...');
        
        // Afficher chaque secteur
        secteurs.forEach(secteur => {
            console.log('📍 [DEBUG] Secteur:', secteur.nom, 'Coords:', secteur.latitude, secteur.longitude);
            
            const position = { 
                lat: parseFloat(secteur.latitude) || 43.6, 
                lng: parseFloat(secteur.longitude) || 1.4 
            };
            
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: secteur.nom + ' (' + secteur.nombre_divisions + ' zones)',
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: secteur.couleur,
                    fillOpacity: 0.8,
                    scale: 12,
                    strokeColor: secteur.couleur,
                    strokeWeight: 3
                }
            });

            // Info window pour chaque secteur
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="padding: 10px;">
                        <h6 style="color: ${secteur.couleur}; margin: 0 0 8px 0;">
                            <i class="fas fa-map-marker-alt"></i> ${secteur.nom}
                        </h6>
                        <p style="margin: 0 0 5px 0;">
                            <strong>Territoire:</strong> ${secteur.resume_territoire}
                        </p>
                        <p style="margin: 0;">
                            <strong>Divisions:</strong> ${secteur.nombre_divisions} zones
                        </p>
                    </div>
                `
            });
            
            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });

            bounds.extend(marker.getPosition());
            hasValidCoords = true;
        });

        // Ajuster le zoom si on a des marqueurs
        console.log('🎯 [DEBUG] Ajustement zoom carte...', {
            secteursCount: secteurs.length,
            hasValidCoords: hasValidCoords
        });
        
        if (secteurs.length > 0 && hasValidCoords) {
            console.log('📏 [DEBUG] Application fitBounds avec bounds:', bounds.toString());
            map.fitBounds(bounds);
            
            // Assurer zoom minimum pour éviter zoom trop proche
            google.maps.event.addListenerOnce(map, 'bounds_changed', () => {
                const currentZoom = map.getZoom();
                console.log('🔍 [DEBUG] Zoom après fitBounds:', currentZoom);
                if (currentZoom > 12) {
                    console.log('📏 [DEBUG] Zoom trop proche, réduction à 12');
                    map.setZoom(12);
                }
            });
        } else {
            console.log('📍 [DEBUG] Pas de coordonnées valides, utilisation zoom/centre par défaut');
            map.setCenter({ lat: 43.6, lng: 1.4 });
            map.setZoom(10);
        }
    }

    afficherStatistiquesSecteur(data) {
        const statsHtml = `
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-map-marker-alt fa-2x me-3"></i>
                                <div>
                                    <div class="fs-6 fw-bold">${data.secteurs.length}</div>
                                    <div class="small">Secteur(s) assigné(s)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-handshake fa-2x me-3"></i>
                                <div>
                                    <div class="fs-6 fw-bold">${data.total_contrats || 0}</div>
                                    <div class="small">Contrat(s) actif(s)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-chart-line fa-2x me-3"></i>
                                <div>
                                    <div class="fs-6 fw-bold">À venir</div>
                                    <div class="small">Objectifs du mois</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const statsContainer = document.createElement('div');
        statsContainer.innerHTML = statsHtml;
        document.getElementById('mon-secteur').insertBefore(statsContainer, document.getElementById('map-secteur-commercial').parentNode.parentNode);
    }

    // === ONGLET MES PERFORMANCES ===
    async initialiserMesPerformances() {
        console.log('📊 Initialisation Mes Performances');
        
        try {
            const response = await fetch('/workflow/dashboard/mes-performances', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            console.log('📊 Données performances reçues:', data);
            
            if (data.success) {
                this.afficherMesPerformances(data);
            } else {
                this.afficherErreurPerformances(data.message || 'Fonctionnalité temporairement désactivée');
            }
            
        } catch (error) {
            console.error('❌ Erreur chargement performances:', error);
            this.afficherErreurPerformances('Erreur de communication avec le serveur');
        }
    }

    afficherMesPerformances(data) {
        // Graphique temporaire - données simulées
        document.getElementById('mes-performances').innerHTML = `
            <div class="row">
                <div class="col-md-8">
                    <canvas id="performance-chart" width="400" height="200"></canvas>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Résumé performances</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Objectif mensuel:</strong> 15,000 €</p>
                            <p class="mb-1"><strong>Réalisé:</strong> 12,500 €</p>
                            <p class="mb-0"><strong>Taux:</strong> <span class="text-success">83.3%</span></p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Initialiser le graphique
        this.initialiserGraphiquePerformances();
    }

    afficherErreurPerformances(message) {
        document.getElementById('mes-performances').innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            </div>
        `;
    }

    initialiserGraphiquePerformances() {
        setTimeout(() => {
            const ctx = document.getElementById('performance-chart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                        datasets: [{
                            label: 'Réalisé',
                            data: [12000, 15000, 13500, 16000, 14500, 12500],
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            tension: 0.1
                        }, {
                            label: 'Objectif',
                            data: [15000, 15000, 15000, 15000, 15000, 15000],
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            borderDash: [5, 5]
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('fr-FR', {
                                            style: 'currency',
                                            currency: 'EUR'
                                        }).format(value);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }, 300);
    }

    // === SYSTÈME D'ALERTES ===
    async chargerMesAlertes() {
        console.log('🔔 [DEBUG] Chargement alertes...');
        
        // Vérifier élément alertes DOM
        const alertesContainer = document.getElementById('alertes-container');
        console.log('🔍 [DEBUG] Container alertes trouvé:', alertesContainer ? 'OUI' : 'NON');
        
        try {
            console.log('📡 [DEBUG] Appel API /workflow/dashboard/mes-alertes...');
            const response = await fetch('/workflow/dashboard/mes-alertes', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('📡 [DEBUG] Réponse alertes reçue:', {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok,
                contentType: response.headers.get('content-type')
            });
            
            if (!response.ok) {
                if (response.status === 302) {
                    throw new Error('Session expirée - veuillez vous reconnecter');
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('❌ [DEBUG] Réponse non-JSON reçue:', text.substring(0, 200));
                if (text.includes('login')) {
                    throw new Error('Session expirée - veuillez vous reconnecter');
                }
                throw new Error('La réponse n\'est pas au format JSON');
            }
            
            const data = await response.json();
            console.log('📊 [DEBUG] Données alertes reçues:', data);
            
            if (data && data.success) {
                console.log('✅ [DEBUG] Affichage', data.alertes ? data.alertes.length : 0, 'alertes...');
                this.afficherAlertes(data.alertes || []);
            } else {
                console.error('❌ [DEBUG] Erreur dans data.success:', data?.message || 'Pas de message');
                this.afficherErreurAlertes(data?.message || 'Erreur inconnue');
            }
            
        } catch (error) {
            console.error('💥 [DEBUG] Exception chargement alertes:', {
                message: error.message,
                stack: error.stack,
                type: typeof error
            });
            this.afficherErreurAlertes('Erreur de communication: ' + error.message);
        }
    }

    afficherAlertes(alertes) {
        console.log('🎨 [DEBUG] Affichage alertes dans le DOM...');
        const container = document.getElementById('alertes-container');
        const badge = document.getElementById('badge-alertes-count');
        const loader = document.getElementById('alertes-loading');

        console.log('🔍 [DEBUG] Éléments DOM alertes:', {
            container: container ? 'OUI' : 'NON',
            badge: badge ? 'OUI' : 'NON',
            loader: loader ? 'OUI' : 'NON',
            alertesCount: alertes ? alertes.length : 'NULL/UNDEFINED'
        });

        // Masquer le loader
        if (loader) {
            loader.style.display = 'none';
        }

        if (!container) {
            console.error('❌ [DEBUG] Container alertes-container introuvable dans le DOM!');
            return;
        }

        if (!alertes || alertes.length === 0) {
            container.innerHTML = `
                <div class="alert alert-light text-center">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h5 class="mb-2">Aucune alerte</h5>
                    <p class="mb-0 text-muted">Toutes vos alertes ont été traitées.</p>
                </div>
            `;
            badge.textContent = '0';
            badge.style.display = 'none';
            return;
        }

        let html = '<div class="row">';

        alertes.forEach((alerte, index) => {
            const alertClass = 'alert-' + alerte.typeBootstrap;
            const iconClass = alerte.typeIcon;

            // Extraire l'URL du message si présente (pour les alertes automatiques)
            let messageText = alerte.message;
            let detailsUrl = null;

            // Chercher le lien "Voir détails" dans le message
            const linkMatch = alerte.message.match(/<a href="([^"]+)"[^>]*>Voir détails<\/a>/);
            if (linkMatch) {
                detailsUrl = linkMatch[1];
                // Retirer le lien du message
                messageText = alerte.message.replace(/<a href="[^"]+"[^>]*>Voir détails<\/a>/, '').trim();
            }

            // Bouton de fermeture (croix en haut à droite)
            let closeButton = '';
            if (alerte.dismissible) {
                // Pour les alertes manuelles
                closeButton = '<button type="button" class="btn-close position-absolute" ' +
                    'style="top: 10px; right: 15px;" ' +
                    'onclick="commercialDashboard.fermerAlerte(\'' + alerte.id + '\')" ' +
                    'title="Fermer cette alerte"></button>';
            } else if (alerte.source === 'automatic') {
                // Pour les alertes automatiques, permettre de résoudre l'instance
                closeButton = '<button type="button" class="btn-close position-absolute" ' +
                    'style="top: 10px; right: 15px;" ' +
                    'onclick="commercialDashboard.resoudreAlerteAutomatique(\'' + alerte.id + '\')" ' +
                    'title="Marquer comme résolue"></button>';
            }

            // Lien "Voir détails" inline avec le message (pour alertes automatiques avec URL)
            let detailsLink = '';
            if (detailsUrl) {
                detailsLink = '<a href="' + detailsUrl + '" target="_blank" class="btn btn-sm btn-outline-' + alerte.typeBootstrap + ' ms-2">' +
                    '<i class="fas fa-external-link-alt me-1"></i>Voir détails' +
                    '</a>';
            }

            html += '<div class="col-12 mb-3">' +
                '<div class="alert ' + alertClass + ' position-relative mb-0" style="padding-right: 40px;">' +
                '<div class="d-flex align-items-start">' +
                '<i class="' + iconClass + ' fa-lg me-3 mt-1"></i>' +
                '<div class="flex-grow-1">' +
                '<h6 class="alert-heading mb-2">' + alerte.titre + '</h6>' +
                '<div class="d-flex align-items-center">' +
                '<p class="mb-0 me-2">' + messageText + '</p>' +
                detailsLink +
                '</div>' +
                '</div>' +
                '</div>' +
                closeButton +
                '</div></div>';
        });

        html += '</div>';

        container.innerHTML = html;
        container.style.display = 'block';

        if (badge) {
            badge.textContent = alertes.length.toString();
            badge.style.display = alertes.length > 0 ? 'inline' : 'none';
        }
    }

    afficherErreurAlertes(message) {
        // Masquer le loader
        const loader = document.getElementById('alertes-loading');
        if (loader) {
            loader.style.display = 'none';
        }
        
        const container = document.getElementById('alertes-container');
        if (container) {
            container.style.display = 'block';
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
        }
        
        const badge = document.getElementById('badge-alertes-count');
        if (badge) {
            badge.style.display = 'none';
        }
    }

    async fermerAlerte(alerteId) {
        try {
            // Extraire l'ID numérique (retirer le préfixe 'auto_' ou 'manual_')
            const alerteNumericId = alerteId.replace(/^(auto_|manual_)/, '');

            const response = await fetch('/workflow/dashboard/alerte/' + alerteNumericId + '/dismiss', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });

            const data = await response.json();

            if (data.success) {
                // Recharger les alertes pour mise à jour
                this.chargerMesAlertes();

                // Notification success
                this.showNotification('Alerte fermée avec succès', 'success');
            } else {
                this.showNotification('Erreur: ' + data.message, 'danger');
            }

        } catch (error) {
            console.error('❌ Erreur fermeture alerte:', error);
            this.showNotification('Erreur de communication avec le serveur', 'danger');
        }
    }

    async resoudreAlerteAutomatique(alerteId) {
        try {
            // Extraire l'ID numérique (retirer le préfixe 'auto_' ou 'manual_')
            const alerteNumericId = alerteId.replace(/^(auto_|manual_)/, '');

            const response = await fetch('/workflow/dashboard/alerte/' + alerteNumericId + '/resolve', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                // Recharger les alertes pour mise à jour
                this.chargerMesAlertes();

                // Notification success
                this.showNotification('Alerte résolue avec succès', 'success');
            } else {
                this.showNotification('Erreur: ' + data.message, 'danger');
            }

        } catch (error) {
            console.error('❌ Erreur résolution alerte:', error);
            this.showNotification('Erreur de communication avec le serveur', 'danger');
        }
    }

    showNotification(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'danger' ? 'exclamation-triangle' : 'info'} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        setTimeout(() => toast.remove(), 5000);
    }
}

// Export pour utilisation globale
window.CommercialDashboard = CommercialDashboard;