<?php
/**
 * Analytics System - Blue Cleaning Services
 * Google Analytics, conversion tracking, and user behavior analysis
 */

class AnalyticsService {
    
    private $config;
    private $logger;
    private $db;
    
    public function __construct($database = null) {
        $this->config = EnvironmentConfig::get('analytics', [
            'google_analytics' => [
                'tracking_id' => '',
                'measurement_id' => '', // GA4
                'api_secret' => ''
            ],
            'facebook_pixel' => [
                'pixel_id' => '',
                'access_token' => ''
            ],
            'enabled' => true,
            'track_user_behavior' => true,
            'track_conversions' => true
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
     * Initialize analytics database tables
     */
    private function initializeDatabase() {
        $queries = [
            // Events table
            "CREATE TABLE IF NOT EXISTS analytics_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_name VARCHAR(255) NOT NULL,
                user_id INT NULL,
                session_id VARCHAR(255),
                user_agent TEXT,
                ip_address VARCHAR(45),
                page_url TEXT,
                referrer TEXT,
                event_data JSON,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_name (event_name),
                INDEX idx_user_id (user_id),
                INDEX idx_timestamp (timestamp)
            )",
            
            // Conversions table
            "CREATE TABLE IF NOT EXISTS analytics_conversions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversion_type ENUM('booking', 'payment', 'signup', 'subscription') NOT NULL,
                user_id INT,
                session_id VARCHAR(255),
                value DECIMAL(10,2) DEFAULT 0,
                currency VARCHAR(3) DEFAULT 'AUD',
                conversion_data JSON,
                attribution_source VARCHAR(255),
                attribution_medium VARCHAR(255),
                attribution_campaign VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_conversion_type (conversion_type),
                INDEX idx_user_id (user_id)
            )",
            
            // User sessions table
            "CREATE TABLE IF NOT EXISTS analytics_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(255) UNIQUE NOT NULL,
                user_id INT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                landing_page TEXT,
                referrer TEXT,
                utm_source VARCHAR(255),
                utm_medium VARCHAR(255),
                utm_campaign VARCHAR(255),
                utm_term VARCHAR(255),
                utm_content VARCHAR(255),
                device_type VARCHAR(50),
                browser VARCHAR(100),
                os VARCHAR(100),
                country VARCHAR(2),
                city VARCHAR(100),
                session_duration INT DEFAULT 0,
                page_views INT DEFAULT 0,
                is_bounce BOOLEAN DEFAULT TRUE,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ended_at TIMESTAMP NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_started_at (started_at)
            )",
            
            // A/B test variants table
            "CREATE TABLE IF NOT EXISTS ab_test_variants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                test_name VARCHAR(255) NOT NULL,
                variant_name VARCHAR(255) NOT NULL,
                user_id INT,
                session_id VARCHAR(255),
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                converted BOOLEAN DEFAULT FALSE,
                conversion_value DECIMAL(10,2) DEFAULT 0,
                UNIQUE KEY unique_test_user (test_name, user_id),
                UNIQUE KEY unique_test_session (test_name, session_id)
            )"
        ];
        
        foreach ($queries as $query) {
            $this->db->exec($query);
        }
    }
    
    /**
     * Track page view
     */
    public function trackPageView($page, $title = null, $userId = null) {
        if (!$this->config['enabled']) {
            return false;
        }
        
        $sessionId = $this->getOrCreateSession($userId);
        
        // Track locally
        $this->trackEvent('page_view', [
            'page_url' => $page,
            'page_title' => $title,
            'user_id' => $userId
        ], $sessionId);
        
        // Update session page views
        $this->updateSessionPageViews($sessionId);
        
        // Send to Google Analytics 4
        $this->sendGA4Event('page_view', [
            'page_title' => $title,
            'page_location' => $this->getCurrentUrl()
        ], $userId);
        
        return true;
    }
    
    /**
     * Track custom event
     */
    public function trackEvent($eventName, $properties = [], $sessionId = null, $userId = null) {
        if (!$this->config['enabled']) {
            return false;
        }
        
        $sessionId = $sessionId ?? $this->getOrCreateSession($userId);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO analytics_events 
                (event_name, user_id, session_id, user_agent, ip_address, page_url, referrer, event_data)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $eventName,
                $userId,
                $sessionId,
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['REQUEST_URI'] ?? '',
                $_SERVER['HTTP_REFERER'] ?? '',
                json_encode($properties)
            ]);
            
            // Send to external analytics
            $this->sendGA4Event($eventName, $properties, $userId);
            
            if ($this->logger) {
                $this->logger->info('Event tracked', [
                    'event' => $eventName,
                    'user_id' => $userId,
                    'properties' => $properties
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Event tracking failed', [
                    'event' => $eventName,
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Track conversion
     */
    public function trackConversion($type, $value = 0, $userId = null, $additionalData = []) {
        if (!$this->config['track_conversions']) {
            return false;
        }
        
        $sessionId = $this->getOrCreateSession($userId);
        $session = $this->getSession($sessionId);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO analytics_conversions 
                (conversion_type, user_id, session_id, value, conversion_data, 
                 attribution_source, attribution_medium, attribution_campaign)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $type,
                $userId,
                $sessionId,
                $value,
                json_encode($additionalData),
                $session['utm_source'] ?? 'direct',
                $session['utm_medium'] ?? 'none',
                $session['utm_campaign'] ?? ''
            ]);
            
            // Track as event too
            $this->trackEvent('conversion', [
                'conversion_type' => $type,
                'value' => $value,
                'currency' => 'AUD'
            ] + $additionalData, $sessionId, $userId);
            
            // Send to Google Analytics
            $this->sendGA4Conversion($type, $value, $additionalData, $userId);
            
            // Update A/B test conversions
            $this->updateABTestConversions($sessionId, $value);
            
            if ($this->logger) {
                $this->logger->info('Conversion tracked', [
                    'type' => $type,
                    'value' => $value,
                    'user_id' => $userId
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Conversion tracking failed', [
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Get or create user session
     */
    private function getOrCreateSession($userId = null) {
        session_start();
        
        if (!isset($_SESSION['analytics_session_id'])) {
            $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
            
            // Create new session record
            $this->createSessionRecord($_SESSION['analytics_session_id'], $userId);
        } else {
            // Update existing session
            $this->updateSessionRecord($_SESSION['analytics_session_id'], $userId);
        }
        
        return $_SESSION['analytics_session_id'];
    }
    
    /**
     * Create session record
     */
    private function createSessionRecord($sessionId, $userId = null) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceInfo = $this->parseUserAgent($userAgent);
        $geoInfo = $this->getGeoLocation($_SERVER['REMOTE_ADDR'] ?? '');
        
        // Parse UTM parameters
        $utm = [
            'source' => $_GET['utm_source'] ?? $_SESSION['utm_source'] ?? null,
            'medium' => $_GET['utm_medium'] ?? $_SESSION['utm_medium'] ?? null,
            'campaign' => $_GET['utm_campaign'] ?? $_SESSION['utm_campaign'] ?? null,
            'term' => $_GET['utm_term'] ?? $_SESSION['utm_term'] ?? null,
            'content' => $_GET['utm_content'] ?? $_SESSION['utm_content'] ?? null
        ];
        
        // Store UTM in session for attribution
        foreach ($utm as $key => $value) {
            if ($value) {
                $_SESSION["utm_{$key}"] = $value;
            }
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO analytics_sessions 
            (session_id, user_id, ip_address, user_agent, landing_page, referrer,
             utm_source, utm_medium, utm_campaign, utm_term, utm_content,
             device_type, browser, os, country, city)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $sessionId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $userAgent,
            $_SERVER['REQUEST_URI'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? '',
            $utm['source'],
            $utm['medium'],
            $utm['campaign'],
            $utm['term'],
            $utm['content'],
            $deviceInfo['device_type'],
            $deviceInfo['browser'],
            $deviceInfo['os'],
            $geoInfo['country'],
            $geoInfo['city']
        ]);
    }
    
    /**
     * Update session record
     */
    private function updateSessionRecord($sessionId, $userId = null) {
        $updateData = ['ended_at' => date('Y-m-d H:i:s')];
        
        if ($userId) {
            $updateData['user_id'] = $userId;
        }
        
        $stmt = $this->db->prepare("
            UPDATE analytics_sessions 
            SET user_id = COALESCE(?, user_id), ended_at = ?
            WHERE session_id = ?
        ");
        
        $stmt->execute([$userId, $updateData['ended_at'], $sessionId]);
    }
    
    /**
     * Update session page views
     */
    private function updateSessionPageViews($sessionId) {
        $stmt = $this->db->prepare("
            UPDATE analytics_sessions 
            SET page_views = page_views + 1,
                is_bounce = (page_views = 0),
                session_duration = TIMESTAMPDIFF(SECOND, started_at, NOW())
            WHERE session_id = ?
        ");
        
        $stmt->execute([$sessionId]);
    }
    
    /**
     * Get session data
     */
    private function getSession($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM analytics_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Send event to Google Analytics 4
     */
    private function sendGA4Event($eventName, $parameters = [], $userId = null) {
        if (empty($this->config['google_analytics']['measurement_id'])) {
            return false;
        }
        
        $payload = [
            'client_id' => $this->getClientId(),
            'events' => [
                [
                    'name' => $eventName,
                    'params' => array_merge($parameters, [
                        'engagement_time_msec' => 1000,
                        'session_id' => $this->getOrCreateSession($userId)
                    ])
                ]
            ]
        ];
        
        if ($userId) {
            $payload['user_id'] = (string)$userId;
        }
        
        $url = 'https://www.google-analytics.com/mp/collect?' . http_build_query([
            'measurement_id' => $this->config['google_analytics']['measurement_id'],
            'api_secret' => $this->config['google_analytics']['api_secret']
        ]);
        
        $this->sendAsyncRequest($url, $payload);
    }
    
    /**
     * Send conversion to Google Analytics 4
     */
    private function sendGA4Conversion($type, $value, $data, $userId = null) {
        $eventName = match($type) {
            'booking' => 'begin_checkout',
            'payment' => 'purchase',
            'signup' => 'sign_up',
            'subscription' => 'subscribe',
            default => 'conversion'
        };
        
        $parameters = [
            'value' => $value,
            'currency' => 'AUD',
            'conversion_type' => $type
        ];
        
        if ($type === 'purchase' || $type === 'booking') {
            $parameters['transaction_id'] = $data['booking_id'] ?? $data['transaction_id'] ?? uniqid();
        }
        
        $this->sendGA4Event($eventName, $parameters, $userId);
    }
    
    /**
     * A/B Testing Functions
     */
    public function assignABTestVariant($testName, $variants, $userId = null) {
        $sessionId = $this->getOrCreateSession($userId);
        
        // Check if already assigned
        $stmt = $this->db->prepare("
            SELECT variant_name FROM ab_test_variants 
            WHERE test_name = ? AND (user_id = ? OR session_id = ?)
        ");
        $stmt->execute([$testName, $userId, $sessionId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return $existing['variant_name'];
        }
        
        // Assign new variant
        $variant = $variants[array_rand($variants)];
        
        $stmt = $this->db->prepare("
            INSERT INTO ab_test_variants (test_name, variant_name, user_id, session_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$testName, $variant, $userId, $sessionId]);
        
        // Track assignment
        $this->trackEvent('ab_test_assigned', [
            'test_name' => $testName,
            'variant' => $variant
        ], $sessionId, $userId);
        
        return $variant;
    }
    
    /**
     * Update A/B test conversions
     */
    private function updateABTestConversions($sessionId, $value) {
        $stmt = $this->db->prepare("
            UPDATE ab_test_variants 
            SET converted = TRUE, conversion_value = conversion_value + ?
            WHERE session_id = ? AND converted = FALSE
        ");
        $stmt->execute([$value, $sessionId]);
    }
    
    /**
     * Get analytics dashboard data
     */
    public function getDashboardData($period = '30 days') {
        $days = match($period) {
            '7 days' => 7,
            '30 days' => 30,
            '90 days' => 90,
            default => 30
        };
        
        // Page views
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as page_views 
            FROM analytics_events 
            WHERE event_name = 'page_view' 
            AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $pageViews = $stmt->fetchColumn();
        
        // Unique visitors
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT session_id) as unique_visitors
            FROM analytics_sessions 
            WHERE started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $uniqueVisitors = $stmt->fetchColumn();
        
        // Conversions
        $stmt = $this->db->prepare("
            SELECT conversion_type, COUNT(*) as count, SUM(value) as total_value
            FROM analytics_conversions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY conversion_type
        ");
        $stmt->execute([$days]);
        $conversions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top pages
        $stmt = $this->db->prepare("
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.page_url')) as page,
                COUNT(*) as views
            FROM analytics_events 
            WHERE event_name = 'page_view' 
            AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY page
            ORDER BY views DESC
            LIMIT 10
        ");
        $stmt->execute([$days]);
        $topPages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Traffic sources
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(utm_source, 'direct') as source,
                COUNT(*) as sessions
            FROM analytics_sessions 
            WHERE started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY source
            ORDER BY sessions DESC
        ");
        $stmt->execute([$days]);
        $trafficSources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'page_views' => $pageViews,
            'unique_visitors' => $uniqueVisitors,
            'conversions' => $conversions,
            'top_pages' => $topPages,
            'traffic_sources' => $trafficSources,
            'period' => $period
        ];
    }
    
    /**
     * Helper functions
     */
    private function getClientId() {
        if (!isset($_COOKIE['_ga'])) {
            $clientId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            setcookie('_ga', 'GA1.2.' . $clientId, time() + (2 * 365 * 24 * 60 * 60), '/');
            return $clientId;
        }
        
        return explode('.', $_COOKIE['_ga'])[2] ?? uniqid();
    }
    
    private function parseUserAgent($userAgent) {
        // Basic user agent parsing
        $device = 'desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $device = 'mobile';
        } elseif (preg_match('/Tablet|iPad/', $userAgent)) {
            $device = 'tablet';
        }
        
        $browser = 'Unknown';
        if (preg_match('/Chrome\/[\d.]+/', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/[\d.]+/', $userAgent)) {
            $browser = 'Safari';
        }
        
        $os = 'Unknown';
        if (preg_match('/Windows/', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad/', $userAgent)) {
            $os = 'iOS';
        }
        
        return [
            'device_type' => $device,
            'browser' => $browser,
            'os' => $os
        ];
    }
    
    private function getGeoLocation($ip) {
        // Basic geo location - in production use a proper service
        return [
            'country' => 'AU',
            'city' => 'Melbourne'
        ];
    }
    
    private function getCurrentUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    private function sendAsyncRequest($url, $data) {
        // Send request asynchronously to avoid blocking
        $postData = json_encode($data);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postData)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOSIGNAL => 1
        ]);
        
        // Execute in background if possible
        if (function_exists('curl_multi_init')) {
            $mh = curl_multi_init();
            curl_multi_add_handle($mh, $ch);
            curl_multi_exec($mh, $running);
            curl_multi_remove_handle($mh, $ch);
            curl_multi_close($mh);
        } else {
            curl_exec($ch);
        }
        
        curl_close($ch);
    }
}

// JavaScript tracking code generator
function generateTrackingCode() {
    $measurementId = EnvironmentConfig::get('analytics.google_analytics.measurement_id');
    
    if (empty($measurementId)) {
        return '';
    }
    
    return "
<!-- Google Analytics 4 -->
<script async src='https://www.googletagmanager.com/gtag/js?id={$measurementId}'></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{$measurementId}', {
        page_title: document.title,
        page_location: window.location.href,
        send_page_view: true
    });
    
    // Blue Cleaning Services Analytics Enhancement
    window.BlueAnalytics = {
        trackEvent: function(eventName, parameters = {}) {
            gtag('event', eventName, parameters);
            
            // Also send to our backend
            fetch('/api/analytics.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'track_event',
                    event_name: eventName,
                    properties: parameters
                })
            }).catch(console.error);
        },
        
        trackConversion: function(type, value = 0, data = {}) {
            this.trackEvent('conversion', {
                conversion_type: type,
                value: value,
                currency: 'AUD',
                ...data
            });
            
            // Send conversion to backend
            fetch('/api/analytics.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'track_conversion',
                    type: type,
                    value: value,
                    data: data
                })
            }).catch(console.error);
        }
    };
    
    // Auto-track form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.dataset.trackSubmission !== 'false') {
            BlueAnalytics.trackEvent('form_submit', {
                form_id: form.id || 'unknown',
                form_action: form.action || window.location.pathname
            });
        }
    });
    
    // Auto-track button clicks
    document.addEventListener('click', function(e) {
        const button = e.target.closest('button, .btn, a[data-track]');
        if (button) {
            BlueAnalytics.trackEvent('button_click', {
                button_text: button.textContent.trim(),
                button_id: button.id || 'unknown',
                page: window.location.pathname
            });
        }
    });
</script>
";
}

// API endpoint
if (basename(__FILE__) === 'analytics.php') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $analytics = new AnalyticsService();
            
            switch ($input['action']) {
                case 'track_event':
                    if (isset($input['event_name'])) {
                        $success = $analytics->trackEvent(
                            $input['event_name'],
                            $input['properties'] ?? [],
                            null,
                            $input['user_id'] ?? null
                        );
                        $response = ['success' => $success];
                    }
                    break;
                    
                case 'track_conversion':
                    if (isset($input['type'])) {
                        $success = $analytics->trackConversion(
                            $input['type'],
                            $input['value'] ?? 0,
                            $input['user_id'] ?? null,
                            $input['data'] ?? []
                        );
                        $response = ['success' => $success];
                    }
                    break;
                    
                case 'get_dashboard':
                    $data = $analytics->getDashboardData($input['period'] ?? '30 days');
                    $response = ['success' => true, 'data' => $data];
                    break;
                    
                case 'ab_test':
                    if (isset($input['test_name'], $input['variants'])) {
                        $variant = $analytics->assignABTestVariant(
                            $input['test_name'],
                            $input['variants'],
                            $input['user_id'] ?? null
                        );
                        $response = ['success' => true, 'variant' => $variant];
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
