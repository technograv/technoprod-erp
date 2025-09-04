/**
 * Module Calendrier Commercial - TechnoProd V2.2
 * Gestion du calendrier hebdomadaire et navigation AJAX
 */

class CalendarDashboard {
    constructor() {
        this.currentWeekOffset = window.currentWeekOffset || 0;
    }

    // Navigation AJAX entre les semaines
    changeWeekAjax(offset) {
        // Afficher le loading
        document.querySelector('.calendar-loading').style.display = 'block';
        document.querySelector('#calendar-content').style.opacity = '0.5';
        
        // Désactiver les boutons pendant le chargement
        document.querySelector('#btn-prev-week').disabled = true;
        document.querySelector('#btn-next-week').disabled = true;
        
        fetch(`/workflow/dashboard/calendar-ajax?week=${offset}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateCalendarDisplay(data);
                    window.currentWeekOffset = offset;
                } else {
                    console.error('Erreur chargement semaine:', data.message);
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur AJAX calendar:', error);
                alert('Erreur de communication avec le serveur');
            })
            .finally(() => {
                // Masquer loading et réactiver boutons
                document.querySelector('.calendar-loading').style.display = 'none';
                document.querySelector('#calendar-content').style.opacity = '1';
                document.querySelector('#btn-prev-week').disabled = false;
                document.querySelector('#btn-next-week').disabled = false;
            });
    }

    updateCalendarDisplay(data) {
        // Mise à jour affichage calendrier
        const calendarContent = document.getElementById('calendar-content');
        if (calendarContent && data.calendar_html) {
            calendarContent.innerHTML = data.calendar_html;
        }
        
        // Mise à jour titre semaine
        const weekTitle = document.getElementById('calendar-week-title');
        if (weekTitle && data.week_title) {
            weekTitle.textContent = data.week_title;
        }
    }

    // Fonctions prospection
    lancerProspectionTelephonique() {
        const zone = document.getElementById('phone-prospection-zone').value;
        if (!zone) {
            alert('Veuillez sélectionner une zone de prospection');
            return;
        }
        
        console.log('📞 Lancement prospection téléphonique pour zone:', zone);
        
        fetch('/workflow/prospection-telephonique', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ zone: zone })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Prospection téléphonique lancée!');
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur prospection téléphonique:', error);
            alert('Erreur de communication');
        });
    }

    preparerProspectionTerrain() {
        const zone = document.getElementById('field-prospection-zone').value;
        if (!zone) {
            alert('Veuillez sélectionner une zone de prospection');
            return;
        }
        
        console.log('🚗 Préparation prospection terrain pour zone:', zone);
        
        fetch('/workflow/prospection-terrain', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ zone: zone })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Circuit de prospection optimisé!');
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur prospection terrain:', error);
            alert('Erreur de communication');
        });
    }
}

// Export global
window.CalendarDashboard = CalendarDashboard;