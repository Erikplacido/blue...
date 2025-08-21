<?php
/**
 * Google Maps API Integration - Blue Cleaning Services
 * Geocoding, distance calculation, and address validation
 */

class GoogleMapsService {
    
    private $apiKey;
    private $baseUrl = 'https://maps.googleapis.com/maps/api/';
    private $logger;
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?? EnvironmentConfig::get('services.geocoding.api_key');
        
        if (class_exists('Logger')) {
            $this->logger = Logger::getInstance();
        }
        
        if (empty($this->apiKey)) {
            throw new Exception('Google Maps API key is required');
        }
    }
    
    /**
     * Geocode an address to coordinates
     */
    public function geocodeAddress($address) {
        $url = $this->baseUrl . 'geocode/json?' . http_build_query([
            'address' => $address,
            'key' => $this->apiKey,
            'region' => 'AU',
            'language' => 'en'
        ]);
        
        try {
            $response = $this->makeRequest($url);
            
            if ($response['status'] !== 'OK') {
                throw new Exception('Geocoding failed: ' . $response['status']);
            }
            
            $result = $response['results'][0];
            
            return [
                'success' => true,
                'latitude' => $result['geometry']['location']['lat'],
                'longitude' => $result['geometry']['location']['lng'],
                'formatted_address' => $result['formatted_address'],
                'place_id' => $result['place_id'],
                'address_components' => $this->parseAddressComponents($result['address_components'])
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Geocoding failed', [
                    'address' => $address,
                    'error' => $e->getMessage()
                ]);
            }
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Calculate distance between two points
     */
    public function calculateDistance($origin, $destination, $mode = 'driving') {
        $url = $this->baseUrl . 'distancematrix/json?' . http_build_query([
            'origins' => is_array($origin) ? implode(',', $origin) : $origin,
            'destinations' => is_array($destination) ? implode(',', $destination) : $destination,
            'mode' => $mode,
            'units' => 'metric',
            'key' => $this->apiKey,
            'region' => 'AU'
        ]);
        
        try {
            $response = $this->makeRequest($url);
            
            if ($response['status'] !== 'OK') {
                throw new Exception('Distance calculation failed: ' . $response['status']);
            }
            
            $element = $response['rows'][0]['elements'][0];
            
            if ($element['status'] !== 'OK') {
                throw new Exception('Route not found');
            }
            
            return [
                'success' => true,
                'distance' => [
                    'text' => $element['distance']['text'],
                    'value' => $element['distance']['value'] // meters
                ],
                'duration' => [
                    'text' => $element['duration']['text'],
                    'value' => $element['duration']['value'] // seconds
                ]
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Distance calculation failed', [
                    'origin' => $origin,
                    'destination' => $destination,
                    'error' => $e->getMessage()
                ]);
            }
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Validate Australian address
     */
    public function validateAddress($address) {
        $geocodeResult = $this->geocodeAddress($address);
        
        if (!$geocodeResult['success']) {
            return $geocodeResult;
        }
        
        $components = $geocodeResult['address_components'];
        
        // Check if it's in Australia
        if (!isset($components['country']) || $components['country']['code'] !== 'AU') {
            return [
                'success' => false,
                'error' => 'Address must be in Australia'
            ];
        }
        
        // Extract Australian address components
        return [
            'success' => true,
            'validated_address' => [
                'street_number' => $components['street_number']['value'] ?? '',
                'street_name' => $components['route']['value'] ?? '',
                'suburb' => $components['locality']['value'] ?? $components['sublocality']['value'] ?? '',
                'state' => $components['administrative_area_level_1']['code'] ?? '',
                'postcode' => $components['postal_code']['value'] ?? '',
                'country' => 'Australia',
                'formatted_address' => $geocodeResult['formatted_address'],
                'coordinates' => [
                    'lat' => $geocodeResult['latitude'],
                    'lng' => $geocodeResult['longitude']
                ]
            ]
        ];
    }
    
    /**
     * Check service area coverage
     */
    public function checkServiceCoverage($address) {
        $validation = $this->validateAddress($address);
        
        if (!$validation['success']) {
            return $validation;
        }
        
        $addressData = $validation['validated_address'];
        
        // Define service areas (Melbourne metro for now)
        $servicedStates = ['VIC', 'NSW', 'QLD'];
        $servicedPostcodes = [
            'VIC' => ['3000-3999', '8000-8999'], // Melbourne metro
            'NSW' => ['2000-2999'], // Sydney metro
            'QLD' => ['4000-4999'] // Brisbane metro
        ];
        
        $state = $addressData['state'];
        $postcode = intval($addressData['postcode']);
        
        $inServiceArea = false;
        
        if (in_array($state, $servicedStates) && isset($servicedPostcodes[$state])) {
            foreach ($servicedPostcodes[$state] as $range) {
                if (strpos($range, '-') !== false) {
                    list($min, $max) = explode('-', $range);
                    if ($postcode >= intval($min) && $postcode <= intval($max)) {
                        $inServiceArea = true;
                        break;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'in_service_area' => $inServiceArea,
            'address' => $addressData,
            'message' => $inServiceArea ? 
                'This address is in our service area' : 
                'Sorry, we don\'t currently service this area'
        ];
    }
    
    /**
     * Find nearby professionals
     */
    public function findNearbyProfessionals($customerAddress, $maxDistance = 20000) {
        // This would typically query your professional database
        // For now, we'll simulate the process
        
        $customerGeocode = $this->geocodeAddress($customerAddress);
        if (!$customerGeocode['success']) {
            return $customerGeocode;
        }
        
        // In a real implementation, you'd query professionals from database
        // and calculate distances to each
        
        return [
            'success' => true,
            'customer_location' => [
                'lat' => $customerGeocode['latitude'],
                'lng' => $customerGeocode['longitude']
            ],
            'professionals' => [], // Would be populated from database
            'search_radius' => $maxDistance
        ];
    }
    
    /**
     * Parse address components
     */
    private function parseAddressComponents($components) {
        $parsed = [];
        
        foreach ($components as $component) {
            foreach ($component['types'] as $type) {
                $parsed[$type] = [
                    'value' => $component['long_name'],
                    'short' => $component['short_name']
                ];
            }
        }
        
        return $parsed;
    }
    
    /**
     * Make HTTP request to Google Maps API
     */
    private function makeRequest($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Blue Cleaning Services/2.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: {$httpCode}");
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception('Invalid JSON response');
        }
        
        return $data;
    }
}

// Twilio SMS Service
class TwilioSMSService {
    
    private $accountSid;
    private $authToken;
    private $fromNumber;
    private $logger;
    
    public function __construct($accountSid = null, $authToken = null, $fromNumber = null) {
        $this->accountSid = $accountSid ?? EnvironmentConfig::get('services.sms.account_sid');
        $this->authToken = $authToken ?? EnvironmentConfig::get('services.sms.auth_token');
        $this->fromNumber = $fromNumber ?? EnvironmentConfig::get('services.sms.from_number');
        
        if (class_exists('Logger')) {
            $this->logger = Logger::getInstance();
        }
        
        if (empty($this->accountSid) || empty($this->authToken) || empty($this->fromNumber)) {
            throw new Exception('Twilio credentials are required');
        }
    }
    
    /**
     * Send SMS message
     */
    public function sendSMS($to, $message, $options = []) {
        try {
            // Format Australian mobile numbers
            $to = $this->formatAustralianNumber($to);
            
            $data = [
                'From' => $this->fromNumber,
                'To' => $to,
                'Body' => $message
            ];
            
            if (isset($options['media_url'])) {
                $data['MediaUrl'] = $options['media_url'];
            }
            
            $response = $this->makeRequest('Messages.json', $data);
            
            if ($this->logger) {
                $this->logger->info('SMS sent successfully', [
                    'to' => $to,
                    'message_sid' => $response['sid'],
                    'status' => $response['status']
                ]);
            }
            
            return [
                'success' => true,
                'message_sid' => $response['sid'],
                'status' => $response['status'],
                'to' => $to
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('SMS sending failed', [
                    'to' => $to,
                    'error' => $e->getMessage()
                ]);
            }
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send booking reminder SMS
     */
    public function sendBookingReminder($customerPhone, $booking) {
        $message = "Hi {$booking['customer_name']}! Reminder: Your cleaning service is scheduled for {$booking['date']} at {$booking['time']}. Our professional {$booking['professional_name']} will arrive shortly. Blue Cleaning Services";
        
        return $this->sendSMS($customerPhone, $message);
    }
    
    /**
     * Send booking confirmation SMS
     */
    public function sendBookingConfirmation($customerPhone, $booking) {
        $message = "Booking confirmed! Your cleaning service is scheduled for {$booking['date']} at {$booking['time']}. Professional: {$booking['professional_name']}. Booking ID: {$booking['id']}. Blue Cleaning Services";
        
        return $this->sendSMS($customerPhone, $message);
    }
    
    /**
     * Format Australian mobile number
     */
    private function formatAustralianNumber($number) {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $number);
        
        // Handle different Australian number formats
        if (strlen($cleaned) === 10 && substr($cleaned, 0, 1) === '0') {
            // Convert 04XXXXXXXX to +614XXXXXXXX
            return '+61' . substr($cleaned, 1);
        } elseif (strlen($cleaned) === 9 && substr($cleaned, 0, 1) === '4') {
            // Convert 4XXXXXXXX to +614XXXXXXXX
            return '+61' . $cleaned;
        } elseif (substr($cleaned, 0, 3) === '614') {
            // Already in +614 format, add +
            return '+' . $cleaned;
        }
        
        return $number; // Return as-is if format not recognized
    }
    
    /**
     * Make request to Twilio API
     */
    private function makeRequest($endpoint, $data) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/{$endpoint}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->accountSid . ':' . $this->authToken,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            $error = json_decode($response, true);
            throw new Exception($error['message'] ?? "HTTP Error: {$httpCode}");
        }
        
        return json_decode($response, true);
    }
}

// Social Login Service
class SocialLoginService {
    
    private $config;
    private $logger;
    
    public function __construct() {
        $this->config = EnvironmentConfig::get('social_login', [
            'google' => [
                'client_id' => '',
                'client_secret' => '',
                'redirect_uri' => ''
            ],
            'facebook' => [
                'app_id' => '',
                'app_secret' => '',
                'redirect_uri' => ''
            ]
        ]);
        
        if (class_exists('Logger')) {
            $this->logger = Logger::getInstance();
        }
    }
    
    /**
     * Get Google OAuth URL
     */
    public function getGoogleAuthUrl($state = null) {
        $params = [
            'client_id' => $this->config['google']['client_id'],
            'redirect_uri' => $this->config['google']['redirect_uri'],
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'state' => $state ?? bin2hex(random_bytes(16))
        ];
        
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }
    
    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback($code, $state = null) {
        try {
            // Exchange code for access token
            $tokenData = $this->exchangeGoogleCode($code);
            
            // Get user info
            $userInfo = $this->getGoogleUserInfo($tokenData['access_token']);
            
            return [
                'success' => true,
                'provider' => 'google',
                'user_info' => [
                    'id' => $userInfo['id'],
                    'email' => $userInfo['email'],
                    'name' => $userInfo['name'],
                    'first_name' => $userInfo['given_name'] ?? '',
                    'last_name' => $userInfo['family_name'] ?? '',
                    'picture' => $userInfo['picture'] ?? '',
                    'verified_email' => $userInfo['email_verified'] ?? false
                ]
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Google OAuth failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Exchange Google authorization code for access token
     */
    private function exchangeGoogleCode($code) {
        $data = [
            'client_id' => $this->config['google']['client_id'],
            'client_secret' => $this->config['google']['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->config['google']['redirect_uri']
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Token exchange failed: HTTP {$httpCode}");
        }
        
        $tokenData = json_decode($response, true);
        if (!$tokenData || isset($tokenData['error'])) {
            throw new Exception($tokenData['error_description'] ?? 'Token exchange failed');
        }
        
        return $tokenData;
    }
    
    /**
     * Get Google user information
     */
    private function getGoogleUserInfo($accessToken) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.googleapis.com/oauth2/v2/userinfo',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$accessToken}"],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("User info request failed: HTTP {$httpCode}");
        }
        
        return json_decode($response, true);
    }
}

// API endpoints
if (basename(__FILE__) === 'external-apis.php') {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            switch ($input['action']) {
                case 'validate_address':
                    if (isset($input['address'])) {
                        $mapsService = new GoogleMapsService();
                        $response = $mapsService->validateAddress($input['address']);
                    }
                    break;
                    
                case 'check_service_area':
                    if (isset($input['address'])) {
                        $mapsService = new GoogleMapsService();
                        $response = $mapsService->checkServiceCoverage($input['address']);
                    }
                    break;
                    
                case 'send_sms':
                    if (isset($input['phone'], $input['message'])) {
                        $smsService = new TwilioSMSService();
                        $response = $smsService->sendSMS($input['phone'], $input['message']);
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
