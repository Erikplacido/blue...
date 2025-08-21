<?php
/**
 * Calendar Synchronization Service - Blue Cleaning Services
 * Integration with Google Calendar and Apple Calendar
 */

class CalendarSyncService {
    
    private $config;
    private $logger;
    private $db;
    
    public function __construct($database = null) {
        $this->config = EnvironmentConfig::get('calendar_sync', [
            'google' => [
                'client_id' => '',
                'client_secret' => '',
                'redirect_uri' => '',
                'calendar_id' => 'primary'
            ],
            'enabled' => true
        ]);
        
        $this->db = $database ?? new PDO(
            "mysql:host=" . EnvironmentConfig::get('database.host') . ";dbname=" . EnvironmentConfig::get('database.database'),
            EnvironmentConfig::get('database.username'),
            EnvironmentConfig::get('database.password')
        );
        
        if (class_exists('Logger')) {
            $this->logger = Logger::getInstance();
        }
        
        $this->initializeDatabase();
    }
    
    /**
     * Initialize database tables
     */
    private function initializeDatabase() {
        $query = "CREATE TABLE IF NOT EXISTS calendar_sync_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            provider ENUM('google', 'apple') NOT NULL,
            access_token TEXT,
            refresh_token TEXT,
            expires_at TIMESTAMP NULL,
            calendar_id VARCHAR(255),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_provider (user_id, provider)
        )";
        
        $this->db->exec($query);
    }
    
    /**
     * Get Google Calendar authorization URL
     */
    public function getGoogleAuthUrl($userId, $scopes = ['calendar']) {
        $state = base64_encode(json_encode([
            'user_id' => $userId,
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16))
        ]));
        
        $scopeMap = [
            'calendar' => 'https://www.googleapis.com/auth/calendar',
            'calendar.readonly' => 'https://www.googleapis.com/auth/calendar.readonly'
        ];
        
        $requestedScopes = array_map(function($scope) use ($scopeMap) {
            return $scopeMap[$scope] ?? $scope;
        }, $scopes);
        
        $params = [
            'client_id' => $this->config['google']['client_id'],
            'redirect_uri' => $this->config['google']['redirect_uri'],
            'scope' => implode(' ', $requestedScopes),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        ];
        
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }
    
    /**
     * Handle Google Calendar OAuth callback
     */
    public function handleGoogleCallback($code, $state) {
        try {
            // Decode and validate state
            $stateData = json_decode(base64_decode($state), true);
            if (!$stateData || !isset($stateData['user_id'])) {
                throw new Exception('Invalid state parameter');
            }
            
            $userId = $stateData['user_id'];
            
            // Exchange code for tokens
            $tokenData = $this->exchangeGoogleCode($code);
            
            // Store tokens
            $this->storeTokens($userId, 'google', $tokenData);
            
            if ($this->logger) {
                $this->logger->info('Google Calendar connected', [
                    'user_id' => $userId
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Google Calendar connected successfully'
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Google Calendar connection failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create calendar event for booking
     */
    public function createBookingEvent($userId, $booking) {
        try {
            $tokens = $this->getValidTokens($userId, 'google');
            if (!$tokens) {
                return ['success' => false, 'error' => 'Calendar not connected'];
            }
            
            $event = $this->createGoogleCalendarEvent($tokens['access_token'], $booking);
            
            if ($this->logger) {
                $this->logger->info('Calendar event created', [
                    'user_id' => $userId,
                    'booking_id' => $booking['id'],
                    'event_id' => $event['id']
                ]);
            }
            
            return [
                'success' => true,
                'event_id' => $event['id'],
                'event_link' => $event['htmlLink']
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Calendar event creation failed', [
                    'user_id' => $userId,
                    'booking_id' => $booking['id'] ?? null,
                    'error' => $e->getMessage()
                ]);
            }
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update calendar event
     */
    public function updateBookingEvent($userId, $booking, $eventId) {
        try {
            $tokens = $this->getValidTokens($userId, 'google');
            if (!$tokens) {
                return ['success' => false, 'error' => 'Calendar not connected'];
            }
            
            $event = $this->updateGoogleCalendarEvent($tokens['access_token'], $eventId, $booking);
            
            return [
                'success' => true,
                'event_id' => $event['id']
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete calendar event
     */
    public function deleteBookingEvent($userId, $eventId) {
        try {
            $tokens = $this->getValidTokens($userId, 'google');
            if (!$tokens) {
                return ['success' => false, 'error' => 'Calendar not connected'];
            }
            
            $this->deleteGoogleCalendarEvent($tokens['access_token'], $eventId);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate iCal file for booking
     */
    public function generateICalFile($booking) {
        $startTime = new DateTime($booking['date'] . ' ' . $booking['time']);
        $endTime = clone $startTime;
        $endTime->add(new DateInterval('PT' . ($booking['duration'] ?? 120) . 'M'));
        
        $ical = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Blue Cleaning Services//Booking Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . 'booking-' . $booking['id'] . '@bluecleaningservices.com.au',
            'DTSTART:' . $startTime->format('Ymd\THis'),
            'DTEND:' . $endTime->format('Ymd\THis'),
            'DTSTAMP:' . gmdate('Ymd\THis') . 'Z',
            'SUMMARY:' . $this->escapeICalText($booking['service_name'] ?? 'Cleaning Service'),
            'DESCRIPTION:' . $this->escapeICalText($this->generateEventDescription($booking)),
            'LOCATION:' . $this->escapeICalText($booking['address'] ?? ''),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'BEGIN:VALARM',
            'TRIGGER:-PT30M',
            'ACTION:DISPLAY',
            'DESCRIPTION:Reminder: Your cleaning service starts in 30 minutes',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR'
        ];
        
        return implode("\r\n", $ical);
    }
    
    /**
     * Exchange Google authorization code for tokens
     */
    private function exchangeGoogleCode($code) {
        $data = [
            'client_id' => $this->config['google']['client_id'],
            'client_secret' => $this->config['google']['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->config['google']['redirect_uri']
        ];
        
        return $this->makeGoogleRequest('https://oauth2.googleapis.com/token', $data, 'POST');
    }
    
    /**
     * Refresh Google access token
     */
    private function refreshGoogleToken($refreshToken) {
        $data = [
            'client_id' => $this->config['google']['client_id'],
            'client_secret' => $this->config['google']['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        return $this->makeGoogleRequest('https://oauth2.googleapis.com/token', $data, 'POST');
    }
    
    /**
     * Create Google Calendar event
     */
    private function createGoogleCalendarEvent($accessToken, $booking) {
        $startTime = new DateTime($booking['date'] . ' ' . $booking['time']);
        $endTime = clone $startTime;
        $endTime->add(new DateInterval('PT' . ($booking['duration'] ?? 120) . 'M'));
        
        $event = [
            'summary' => $booking['service_name'] ?? 'Cleaning Service',
            'description' => $this->generateEventDescription($booking),
            'start' => [
                'dateTime' => $startTime->format('c'),
                'timeZone' => 'Australia/Melbourne'
            ],
            'end' => [
                'dateTime' => $endTime->format('c'),
                'timeZone' => 'Australia/Melbourne'
            ],
            'location' => $booking['address'] ?? '',
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 30],
                    ['method' => 'email', 'minutes' => 60]
                ]
            ],
            'attendees' => []
        ];
        
        if (!empty($booking['customer_email'])) {
            $event['attendees'][] = [
                'email' => $booking['customer_email'],
                'displayName' => $booking['customer_name'] ?? '',
                'responseStatus' => 'accepted'
            ];
        }
        
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . 
               urlencode($this->config['google']['calendar_id']) . '/events';
        
        return $this->makeGoogleRequest($url, $event, 'POST', $accessToken);
    }
    
    /**
     * Update Google Calendar event
     */
    private function updateGoogleCalendarEvent($accessToken, $eventId, $booking) {
        $startTime = new DateTime($booking['date'] . ' ' . $booking['time']);
        $endTime = clone $startTime;
        $endTime->add(new DateInterval('PT' . ($booking['duration'] ?? 120) . 'M'));
        
        $event = [
            'summary' => $booking['service_name'] ?? 'Cleaning Service',
            'description' => $this->generateEventDescription($booking),
            'start' => [
                'dateTime' => $startTime->format('c'),
                'timeZone' => 'Australia/Melbourne'
            ],
            'end' => [
                'dateTime' => $endTime->format('c'),
                'timeZone' => 'Australia/Melbourne'
            ],
            'location' => $booking['address'] ?? ''
        ];
        
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . 
               urlencode($this->config['google']['calendar_id']) . '/events/' . 
               urlencode($eventId);
        
        return $this->makeGoogleRequest($url, $event, 'PUT', $accessToken);
    }
    
    /**
     * Delete Google Calendar event
     */
    private function deleteGoogleCalendarEvent($accessToken, $eventId) {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . 
               urlencode($this->config['google']['calendar_id']) . '/events/' . 
               urlencode($eventId);
        
        return $this->makeGoogleRequest($url, null, 'DELETE', $accessToken);
    }
    
    /**
     * Store tokens in database
     */
    private function storeTokens($userId, $provider, $tokenData) {
        $expiresAt = isset($tokenData['expires_in']) ? 
            date('Y-m-d H:i:s', time() + $tokenData['expires_in']) : null;
        
        $stmt = $this->db->prepare("
            INSERT INTO calendar_sync_tokens 
            (user_id, provider, access_token, refresh_token, expires_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                expires_at = VALUES(expires_at),
                is_active = TRUE,
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $userId,
            $provider,
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $expiresAt
        ]);
    }
    
    /**
     * Get valid tokens for user
     */
    private function getValidTokens($userId, $provider) {
        $stmt = $this->db->prepare("
            SELECT * FROM calendar_sync_tokens 
            WHERE user_id = ? AND provider = ? AND is_active = TRUE
        ");
        
        $stmt->execute([$userId, $provider]);
        $tokens = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokens) {
            return null;
        }
        
        // Check if token needs refresh
        if ($tokens['expires_at'] && strtotime($tokens['expires_at']) <= time() + 300) {
            if ($tokens['refresh_token']) {
                try {
                    $newTokens = $this->refreshGoogleToken($tokens['refresh_token']);
                    $this->storeTokens($userId, $provider, array_merge($tokens, $newTokens));
                    $tokens['access_token'] = $newTokens['access_token'];
                } catch (Exception $e) {
                    if ($this->logger) {
                        $this->logger->warning('Token refresh failed', [
                            'user_id' => $userId,
                            'provider' => $provider,
                            'error' => $e->getMessage()
                        ]);
                    }
                    return null;
                }
            } else {
                return null; // Token expired and no refresh token
            }
        }
        
        return $tokens;
    }
    
    /**
     * Generate event description
     */
    private function generateEventDescription($booking) {
        $description = "Cleaning Service Booking\n\n";
        $description .= "Service: " . ($booking['service_name'] ?? 'Standard Cleaning') . "\n";
        $description .= "Duration: " . ($booking['duration'] ?? 120) . " minutes\n";
        $description .= "Professional: " . ($booking['professional_name'] ?? 'TBD') . "\n";
        
        if (!empty($booking['notes'])) {
            $description .= "Notes: " . $booking['notes'] . "\n";
        }
        
        $description .= "\nBooking ID: " . ($booking['id'] ?? '') . "\n";
        $description .= "Contact: support@bluecleaningservices.com.au";
        
        return $description;
    }
    
    /**
     * Escape text for iCal format
     */
    private function escapeICalText($text) {
        $text = str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $text);
        return $text;
    }
    
    /**
     * Make request to Google API
     */
    private function makeGoogleRequest($url, $data = null, $method = 'GET', $accessToken = null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        $headers = ['Content-Type: application/json'];
        
        if ($accessToken) {
            $headers[] = "Authorization: Bearer {$accessToken}";
        }
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($method === 'DELETE' && $httpCode === 204) {
            return true; // Successful delete
        }
        
        if (!in_array($httpCode, [200, 201, 204])) {
            $error = json_decode($response, true);
            throw new Exception($error['error']['message'] ?? "HTTP Error: {$httpCode}");
        }
        
        return json_decode($response, true);
    }
}

// API endpoint
if (basename(__FILE__) === 'calendar-sync.php') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $calendarService = new CalendarSyncService();
            
            switch ($input['action']) {
                case 'get_auth_url':
                    if (isset($input['user_id'])) {
                        $url = $calendarService->getGoogleAuthUrl($input['user_id']);
                        $response = ['success' => true, 'auth_url' => $url];
                    }
                    break;
                    
                case 'create_event':
                    if (isset($input['user_id'], $input['booking'])) {
                        $response = $calendarService->createBookingEvent($input['user_id'], $input['booking']);
                    }
                    break;
                    
                case 'generate_ical':
                    if (isset($input['booking'])) {
                        $ical = $calendarService->generateICalFile($input['booking']);
                        header('Content-Type: text/calendar');
                        header('Content-Disposition: attachment; filename="booking-' . $input['booking']['id'] . '.ics"');
                        echo $ical;
                        exit;
                    }
                    break;
            }
        } catch (Exception $e) {
            $response = ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    echo json_encode($response);
}

?>
