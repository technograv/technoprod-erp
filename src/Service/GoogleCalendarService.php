<?php

namespace App\Service;

use App\Entity\User;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Psr\Log\LoggerInterface;

class GoogleCalendarService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Récupère tous les calendriers accessibles pour un utilisateur
     */
    public function getUserCalendars(User $user): array
    {
        try {
            $client = $this->createGoogleClient($user);
            if (!$client) {
                return [];
            }

            $calendar = new Calendar($client);
            $calendarList = $calendar->calendarList->listCalendarList();
            
            $calendars = [];
            foreach ($calendarList->getItems() as $calendarListEntry) {
                $calendars[] = [
                    'id' => $calendarListEntry->getId(),
                    'summary' => $calendarListEntry->getSummary(),
                    'description' => $calendarListEntry->getDescription(),
                    'primary' => $calendarListEntry->getPrimary() ?: false,
                    'selected' => $calendarListEntry->getSelected() ?: false,
                    'access_role' => $calendarListEntry->getAccessRole(), // owner, freeBusyReader, reader, writer
                    'color_id' => $calendarListEntry->getColorId(),
                    'background_color' => $calendarListEntry->getBackgroundColor(),
                    'foreground_color' => $calendarListEntry->getForegroundColor()
                ];
            }
            
            // Trier les calendriers : principal en premier, puis par nom
            usort($calendars, function($a, $b) {
                if ($a['primary']) return -1;
                if ($b['primary']) return 1;
                return strcasecmp($a['summary'], $b['summary']);
            });
            
            return $calendars;

        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération calendriers Google', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Récupère les événements de la semaine pour un utilisateur
     */
    public function getWeekEvents(User $user, \DateTime $startOfWeek, ?array $selectedCalendarIds = null): array
    {
        try {
            $client = $this->createGoogleClient($user);
            if (!$client) {
                return [];
            }

            $calendar = new Calendar($client);
            
            // Calculer début et fin de semaine (lundi à vendredi)
            $startOfWeek = clone $startOfWeek;
            $startOfWeek->setTime(0, 0, 0);
            
            $endOfWeek = clone $startOfWeek;
            $endOfWeek->modify('+4 days'); // Lundi à vendredi
            $endOfWeek->setTime(23, 59, 59);

            // Utiliser les calendriers sélectionnés ou 'primary' par défaut
            $calendarIds = $selectedCalendarIds ?: ['primary'];
            
            // Récupérer les informations des calendriers pour les couleurs
            $calendarsInfo = $this->getCalendarsInfo($calendar, $calendarIds);
            
            $allEvents = [];
            
            // Récupérer les événements de chaque calendrier sélectionné
            foreach ($calendarIds as $calendarId) {
                try {
                    $events = $calendar->events->listEvents($calendarId, [
                        'timeMin' => $startOfWeek->format('c'),
                        'timeMax' => $endOfWeek->format('c'),
                        'singleEvents' => true,
                        'orderBy' => 'startTime',
                        'maxResults' => 50
                    ]);

                    $calendarInfo = $calendarsInfo[$calendarId] ?? null;

                    foreach ($events->getItems() as $event) {
                        $formattedEvent = $this->formatEvent($event, $calendarInfo);
                        $formattedEvent['calendar_id'] = $calendarId;
                        $allEvents[] = $formattedEvent;
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Erreur calendrier spécifique', [
                        'calendar_id' => $calendarId,
                        'error' => $e->getMessage()
                    ]);
                    continue; // Continuer avec les autres calendriers
                }
            }
            
            // Trier tous les événements par heure de début
            usort($allEvents, function($a, $b) {
                return $a['start']->getTimestamp() - $b['start']->getTimestamp();
            });

            return $allEvents;

        } catch (\Exception $e) {
            $this->logger->error('Erreur Google Calendar API', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Récupère les événements d'une journée spécifique
     */
    public function getDayEvents(User $user, \DateTime $date): array
    {
        try {
            $client = $this->createGoogleClient($user);
            if (!$client) {
                return [];
            }

            $calendar = new Calendar($client);
            
            $startOfDay = clone $date;
            $startOfDay->setTime(0, 0, 0);
            
            $endOfDay = clone $date;
            $endOfDay->setTime(23, 59, 59);

            $events = $calendar->events->listEvents('primary', [
                'timeMin' => $startOfDay->format('c'),
                'timeMax' => $endOfDay->format('c'),
                'singleEvents' => true,
                'orderBy' => 'startTime'
            ]);

            $formattedEvents = [];
            foreach ($events->getItems() as $event) {
                $formattedEvents[] = $this->formatEvent($event);
            }

            return $formattedEvents;

        } catch (\Exception $e) {
            $this->logger->error('Erreur Google Calendar API jour', [
                'user_id' => $user->getId(),
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Crée le client Google avec authentification
     */
    private function createGoogleClient(User $user): ?Client
    {
        if (!$user->isGoogleAccount() || !$user->getGoogleAccessToken()) {
            return null;
        }

        $client = new Client();
        
        // Reconstituer le token au format attendu par Google Client
        $accessToken = $user->getGoogleAccessToken();
        if (is_string($accessToken)) {
            // Si c'est une chaîne, créer la structure attendue
            $tokenArray = [
                'access_token' => $accessToken,
                'token_type' => 'Bearer'
            ];
            $client->setAccessToken($tokenArray);
        } else {
            // Si c'est déjà un tableau, l'utiliser directement
            $client->setAccessToken($accessToken);
        }

        // Vérifier si le token a expiré et le rafraîchir si nécessaire
        if ($client->isAccessTokenExpired()) {
            if ($refreshToken = $user->getGoogleRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($refreshToken);
                
                // Sauvegarder le nouveau token
                $newToken = $client->getAccessToken();
                if (is_array($newToken) && isset($newToken['access_token'])) {
                    $user->setGoogleAccessToken($newToken['access_token']);
                }
                // Note: Il faudra flush() depuis le contrôleur
            } else {
                return null;
            }
        }

        return $client;
    }

    /**
     * Récupère les informations des calendriers (couleurs, noms)
     */
    private function getCalendarsInfo(Calendar $calendar, array $calendarIds): array
    {
        $calendarsInfo = [];
        
        try {
            $calendarList = $calendar->calendarList->listCalendarList();
            foreach ($calendarList->getItems() as $calendarListEntry) {
                if (in_array($calendarListEntry->getId(), $calendarIds)) {
                    $calendarsInfo[$calendarListEntry->getId()] = [
                        'summary' => $calendarListEntry->getSummary(),
                        'background_color' => $calendarListEntry->getBackgroundColor(),
                        'foreground_color' => $calendarListEntry->getForegroundColor(),
                        'color_id' => $calendarListEntry->getColorId()
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Erreur récupération infos calendriers', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $calendarsInfo;
    }

    /**
     * Formate un événement Google Calendar pour l'affichage
     */
    private function formatEvent(Event $event, ?array $calendarInfo = null): array
    {
        $start = $event->getStart();
        $end = $event->getEnd();
        
        // Gérer les événements toute la journée
        $isAllDay = false;
        if ($start->getDate()) {
            $startTime = new \DateTime($start->getDate());
            $endTime = new \DateTime($end->getDate());
            $isAllDay = true;
        } else {
            $startTime = new \DateTime($start->getDateTime());
            $endTime = new \DateTime($end->getDateTime());
        }

        // Déterminer la couleur de l'événement
        $backgroundColor = '#007bff'; // Couleur par défaut (bleu)
        $foregroundColor = '#ffffff';
        
        // 1. Couleur spécifique de l'événement (priorité haute)
        if ($event->getColorId()) {
            $backgroundColor = $this->getEventColor($event->getColorId());
        }
        // 2. Couleur du calendrier (priorité moyenne)
        elseif ($calendarInfo && $calendarInfo['background_color']) {
            $backgroundColor = $calendarInfo['background_color'];
            $foregroundColor = $calendarInfo['foreground_color'] ?: '#ffffff';
        }

        return [
            'id' => $event->getId(),
            'title' => $event->getSummary() ?: 'Sans titre',
            'description' => $event->getDescription(),
            'location' => $event->getLocation(),
            'start' => $startTime,
            'end' => $endTime,
            'is_all_day' => $isAllDay,
            'start_formatted' => $isAllDay ? 'Toute la journée' : $startTime->format('H:i'),
            'end_formatted' => $isAllDay ? '' : $endTime->format('H:i'),
            'duration_minutes' => $isAllDay ? 0 : ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60,
            'html_link' => $event->getHtmlLink(),
            'status' => $event->getStatus(), // confirmed, tentative, cancelled
            'attendees_count' => $event->getAttendees() ? count($event->getAttendees()) : 0,
            'background_color' => $backgroundColor,
            'foreground_color' => $foregroundColor,
            'calendar_name' => $calendarInfo['summary'] ?? 'Calendrier',
            'color_id' => $event->getColorId()
        ];
    }

    /**
     * Récupère la couleur d'un événement selon son ID couleur Google
     */
    private function getEventColor(string $colorId): string
    {
        // Couleurs standard Google Calendar
        $colors = [
            '1' => '#a4bdfc', // Bleu clair
            '2' => '#7ae7bf', // Vert clair
            '3' => '#dbadff', // Violet clair
            '4' => '#ff887c', // Rouge clair
            '5' => '#fbd75b', // Jaune
            '6' => '#ffb878', // Orange
            '7' => '#46d6db', // Turquoise
            '8' => '#e1e1e1', // Gris
            '9' => '#5484ed', // Bleu
            '10' => '#51b749', // Vert
            '11' => '#dc2127'  // Rouge
        ];
        
        return $colors[$colorId] ?? '#007bff';
    }

    /**
     * Calcule le début de la semaine (lundi) pour une date donnée
     */
    public function getStartOfWeek(\DateTime $date = null): \DateTime
    {
        $date = $date ?: new \DateTime();
        $startOfWeek = clone $date;
        
        // Aller au lundi de la semaine
        $dayOfWeek = $startOfWeek->format('N'); // 1 = lundi, 7 = dimanche
        if ($dayOfWeek > 1) {
            $startOfWeek->modify('-' . ($dayOfWeek - 1) . ' days');
        }
        
        return $startOfWeek;
    }

    /**
     * Génère les jours de la semaine de travail (lundi à vendredi)
     */
    public function getWorkWeekDays(\DateTime $startOfWeek): array
    {
        $days = [];
        $current = clone $startOfWeek;
        
        for ($i = 0; $i < 5; $i++) { // Lundi à vendredi
            $days[] = [
                'date' => clone $current,
                'day_name' => $current->format('l'),
                'day_name_fr' => $this->getFrenchDayName($current->format('l')),
                'day_short_fr' => $this->getFrenchDayShort($current->format('l')),
                'day_number' => $current->format('j'),
                'month_name' => $current->format('F'),
                'month_name_fr' => $this->getFrenchMonthName($current->format('F')),
                'formatted' => $current->format('d/m/Y'),
                'is_today' => $current->format('Y-m-d') === (new \DateTime())->format('Y-m-d')
            ];
            $current->modify('+1 day');
        }
        
        return $days;
    }

    /**
     * Traduit les noms de jours en français
     */
    private function getFrenchDayName(string $englishDay): string
    {
        $translations = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi', 
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        ];
        
        return $translations[$englishDay] ?? $englishDay;
    }

    /**
     * Traduit les noms de jours courts en français
     */
    private function getFrenchDayShort(string $englishDay): string
    {
        $translations = [
            'Monday' => 'Lun',
            'Tuesday' => 'Mar', 
            'Wednesday' => 'Mer',
            'Thursday' => 'Jeu',
            'Friday' => 'Ven',
            'Saturday' => 'Sam',
            'Sunday' => 'Dim'
        ];
        
        return $translations[$englishDay] ?? $englishDay;
    }

    /**
     * Traduit les noms de mois en français
     */
    private function getFrenchMonthName(string $englishMonth): string
    {
        $translations = [
            'January' => 'Janvier',
            'February' => 'Février',
            'March' => 'Mars',
            'April' => 'Avril',
            'May' => 'Mai',
            'June' => 'Juin',
            'July' => 'Juillet',
            'August' => 'Août',
            'September' => 'Septembre',
            'October' => 'Octobre',
            'November' => 'Novembre',
            'December' => 'Décembre'
        ];
        
        return $translations[$englishMonth] ?? $englishMonth;
    }

    /**
     * Récupère les calendriers avec droits d'écriture pour un utilisateur
     */
    public function getWritableCalendars(User $user): array
    {
        $allCalendars = $this->getUserCalendars($user);
        
        // Filtrer pour ne garder que les calendriers avec droits d'écriture
        return array_filter($allCalendars, function($calendar) {
            return in_array($calendar['access_role'], ['owner', 'writer']);
        });
    }

    /**
     * Crée un événement dans un calendrier Google
     */
    public function createEvent(User $user, string $calendarId, array $eventData): ?string
    {
        try {
            $client = $this->createGoogleClient($user);
            if (!$client) {
                return null;
            }

            $calendar = new Calendar($client);
            
            $event = new Event($eventData);
            $createdEvent = $calendar->events->insert($calendarId, $event);
            
            $this->logger->info('Événement Google Calendar créé', [
                'user_id' => $user->getId(),
                'calendar_id' => $calendarId,
                'event_id' => $createdEvent->getId(),
                'event_title' => $eventData['summary'] ?? 'Sans titre'
            ]);
            
            return $createdEvent->getId();

        } catch (\Exception $e) {
            $this->logger->error('Erreur création événement Google Calendar', [
                'user_id' => $user->getId(),
                'calendar_id' => $calendarId,
                'error' => $e->getMessage(),
                'event_data' => $eventData
            ]);
            return null;
        }
    }
}