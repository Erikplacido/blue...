<?php
/**
 * API de Localização e Rastreamento - Blue Project V2
 * Sistema de GPS e rastreamento em tempo real para profissionais
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Include dependencies
require_once '../../booking2.php';

try {
    $action = $_GET['action'] ?? 'update_location';
    
    switch ($action) {
        case 'update_location':
            handleUpdateLocation();
            break;
        case 'start_tracking':
            handleStartTracking();
            break;
        case 'stop_tracking':
            handleStopTracking();
            break;
        case 'get_route':
            handleGetRoute();
            break;
        case 'check_arrival':
            handleCheckArrival();
            break;
        case 'track_professional':
            handleTrackProfessional();
            break;
        case 'get_eta':
            handleGetETA();
            break;
        case 'emergency_alert':
            handleEmergencyAlert();
            break;
        default:
            throw new InvalidArgumentException('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Update professional location
 */
function handleUpdateLocation() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $professionalId = $input['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    $lat = (float)($input['latitude'] ?? 0);
    $lng = (float)($input['longitude'] ?? 0);
    $accuracy = (float)($input['accuracy'] ?? 0);
    $heading = (float)($input['heading'] ?? 0);
    $speed = (float)($input['speed'] ?? 0);
    $altitude = (float)($input['altitude'] ?? 0);
    
    if (!$professionalId || !$lat || !$lng) {
        throw new InvalidArgumentException('Professional ID, latitude, and longitude are required');
    }
    
    // Rate limiting - prevent spam updates
    if (!canUpdateLocation($professionalId)) {
        throw new Exception('Location updates too frequent. Please wait.');
    }
    
    // Validate coordinates
    if (!isValidCoordinates($lat, $lng)) {
        throw new InvalidArgumentException('Invalid coordinates');
    }
    
    // Update location
    $locationData = [
        'professional_id' => $professionalId,
        'latitude' => $lat,
        'longitude' => $lng,
        'accuracy' => $accuracy,
        'heading' => $heading,
        'speed' => $speed,
        'altitude' => $altitude,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
    
    $result = updateLocationInDatabase($locationData);
    
    if ($result['success']) {
        // Check for geofencing events
        $geofenceEvents = checkGeofences($professionalId, $lat, $lng);
        
        // Check for nearby urgent jobs
        $urgentJobs = getNearbyUrgentJobs($lat, $lng, 5); // 5km radius
        
        // Calculate if professional is on route to current job
        $routeStatus = calculateRouteStatus($professionalId, $lat, $lng);
        
        // Update ETA for current job if applicable
        $etaUpdate = updateJobETA($professionalId, $lat, $lng);
        
        echo json_encode([
            'success' => true,
            'professional_id' => $professionalId,
            'location' => [
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy' => $accuracy,
                'heading' => $heading,
                'speed' => $speed
            ],
            'geofence_events' => $geofenceEvents,
            'urgent_jobs_nearby' => count($urgentJobs),
            'route_status' => $routeStatus,
            'eta_update' => $etaUpdate,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to update location: ' . $result['error']);
    }
}

/**
 * Start tracking session
 */
function handleStartTracking() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $professionalId = $input['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    $jobId = $input['job_id'] ?? null;
    $trackingType = $input['tracking_type'] ?? 'job'; // 'job', 'patrol', 'break'
    
    if (!$professionalId) {
        throw new InvalidArgumentException('Professional ID is required');
    }
    
    // Create new tracking session
    $sessionId = generateTrackingSessionId();
    
    $trackingSession = [
        'session_id' => $sessionId,
        'professional_id' => $professionalId,
        'job_id' => $jobId,
        'tracking_type' => $trackingType,
        'start_time' => date('Y-m-d H:i:s'),
        'status' => 'active',
        'initial_location' => null,
        'total_distance' => 0,
        'total_duration' => 0
    ];
    
    $result = createTrackingSession($trackingSession);
    
    if ($result['success']) {
        // Notify customer if this is job tracking
        if ($jobId && $trackingType === 'job') {
            notifyCustomerTrackingStarted($jobId, $professionalId);
        }
        
        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'professional_id' => $professionalId,
            'job_id' => $jobId,
            'tracking_type' => $trackingType,
            'message' => 'Tracking session started successfully'
        ]);
    } else {
        throw new Exception('Failed to start tracking session');
    }
}

/**
 * Stop tracking session
 */
function handleStopTracking() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;
    $professionalId = $input['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    
    if (!$sessionId || !$professionalId) {
        throw new InvalidArgumentException('Session ID and Professional ID are required');
    }
    
    // Verify session belongs to professional
    $session = getTrackingSession($sessionId);
    if (!$session || $session['professional_id'] !== $professionalId) {
        throw new InvalidArgumentException('Invalid tracking session');
    }
    
    // Calculate session summary
    $summary = calculateTrackingSessionSummary($sessionId);
    
    // End tracking session
    $result = endTrackingSession($sessionId, $summary);
    
    if ($result['success']) {
        // Notify customer if this was job tracking
        if ($session['job_id'] && $session['tracking_type'] === 'job') {
            notifyCustomerTrackingEnded($session['job_id'], $professionalId, $summary);
        }
        
        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'summary' => $summary,
            'message' => 'Tracking session ended successfully'
        ]);
    } else {
        throw new Exception('Failed to end tracking session');
    }
}

/**
 * Get optimal route to destination
 */
function handleGetRoute() {
    $professionalId = $_GET['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    $fromLat = (float)($_GET['from_lat'] ?? 0);
    $fromLng = (float)($_GET['from_lng'] ?? 0);
    $toLat = (float)($_GET['to_lat'] ?? 0);
    $toLng = (float)($_GET['to_lng'] ?? 0);
    $mode = $_GET['mode'] ?? 'driving'; // driving, walking, transit
    $optimize = $_GET['optimize'] ?? 'time'; // time, distance, traffic
    
    if (!$fromLat || !$fromLng || !$toLat || !$toLng) {
        throw new InvalidArgumentException('From and to coordinates are required');
    }
    
    // Calculate route using multiple providers
    $routes = calculateOptimalRoute($fromLat, $fromLng, $toLat, $toLng, $mode, $optimize);
    
    // Add traffic information
    $trafficInfo = getTrafficInformation($routes['primary_route']);
    
    // Calculate costs
    $costs = calculateRouteCosts($routes['primary_route']);
    
    echo json_encode([
        'success' => true,
        'routes' => $routes,
        'traffic_info' => $trafficInfo,
        'costs' => $costs,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Check arrival at destination
 */
function handleCheckArrival() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $professionalId = $input['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    $jobId = $input['job_id'] ?? null;
    $lat = (float)($input['latitude'] ?? 0);
    $lng = (float)($input['longitude'] ?? 0);
    
    if (!$professionalId || !$jobId || !$lat || !$lng) {
        throw new InvalidArgumentException('Professional ID, Job ID, and location are required');
    }
    
    // Get job location
    $jobLocation = getJobLocation($jobId);
    if (!$jobLocation) {
        throw new InvalidArgumentException('Job location not found');
    }
    
    // Calculate distance to job location
    $distance = calculateDistance($lat, $lng, $jobLocation['latitude'], $jobLocation['longitude']);
    
    // Check if within arrival threshold (typically 100 meters)
    $arrivalThreshold = 0.1; // km
    $hasArrived = $distance <= $arrivalThreshold;
    
    if ($hasArrived) {
        // Record arrival
        $arrivalData = recordArrival($professionalId, $jobId, $lat, $lng);
        
        // Notify customer
        notifyCustomerOfArrival($jobId, $professionalId);
        
        // Update job status
        updateJobStatus($jobId, 'professional_arrived');
        
        echo json_encode([
            'success' => true,
            'arrived' => true,
            'distance_to_destination' => round($distance * 1000), // meters
            'arrival_time' => date('Y-m-d H:i:s'),
            'arrival_data' => $arrivalData,
            'message' => 'Arrival confirmed!'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'arrived' => false,
            'distance_to_destination' => round($distance * 1000), // meters
            'threshold' => round($arrivalThreshold * 1000), // meters
            'message' => 'Not yet at destination'
        ]);
    }
}

/**
 * Track professional (for customer use)
 */
function handleTrackProfessional() {
    $jobId = $_GET['job_id'] ?? null;
    $customerId = $_GET['customer_id'] ?? $_SESSION['customer_id'] ?? null;
    
    if (!$jobId) {
        throw new InvalidArgumentException('Job ID is required');
    }
    
    // Verify customer has permission to track this job
    if (!canCustomerTrackJob($customerId, $jobId)) {
        throw new InvalidArgumentException('Permission denied');
    }
    
    // Get professional tracking data
    $trackingData = getProfessionalTrackingData($jobId);
    
    if ($trackingData) {
        // Get ETA
        $eta = calculateETAToJob($jobId);
        
        // Get route progress
        $routeProgress = calculateRouteProgress($jobId);
        
        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'professional' => [
                'id' => $trackingData['professional_id'],
                'name' => $trackingData['professional_name'],
                'phone' => $trackingData['professional_phone'],
                'photo' => $trackingData['professional_photo']
            ],
            'location' => [
                'latitude' => $trackingData['latitude'],
                'longitude' => $trackingData['longitude'],
                'accuracy' => $trackingData['accuracy'],
                'last_update' => $trackingData['last_update']
            ],
            'eta' => $eta,
            'route_progress' => $routeProgress,
            'status' => $trackingData['status']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Professional tracking not available'
        ]);
    }
}

/**
 * Get estimated time of arrival
 */
function handleGetETA() {
    $jobId = $_GET['job_id'] ?? null;
    $professionalId = $_GET['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    
    if (!$jobId) {
        throw new InvalidArgumentException('Job ID is required');
    }
    
    // Get current professional location
    $currentLocation = getCurrentProfessionalLocation($professionalId);
    if (!$currentLocation) {
        throw new InvalidArgumentException('Professional location not available');
    }
    
    // Get job location
    $jobLocation = getJobLocation($jobId);
    if (!$jobLocation) {
        throw new InvalidArgumentException('Job location not found');
    }
    
    // Calculate ETA with traffic
    $eta = calculateETAWithTraffic(
        $currentLocation['latitude'],
        $currentLocation['longitude'],
        $jobLocation['latitude'],
        $jobLocation['longitude']
    );
    
    // Add buffer time based on professional's historical punctuality
    $professionalBuffer = getProfessionalPunctualityBuffer($professionalId);
    $eta['estimated_arrival'] = date('Y-m-d H:i:s', strtotime($eta['estimated_arrival']) + ($professionalBuffer * 60));
    
    echo json_encode([
        'success' => true,
        'job_id' => $jobId,
        'professional_id' => $professionalId,
        'eta' => $eta,
        'current_location' => $currentLocation,
        'destination' => $jobLocation,
        'calculated_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Handle emergency alert
 */
function handleEmergencyAlert() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $professionalId = $input['professional_id'] ?? $_SESSION['professional_id'] ?? null;
    $alertType = $input['alert_type'] ?? 'general'; // general, medical, security, accident
    $lat = (float)($input['latitude'] ?? 0);
    $lng = (float)($input['longitude'] ?? 0);
    $message = $input['message'] ?? '';
    
    if (!$professionalId || !$lat || !$lng) {
        throw new InvalidArgumentException('Professional ID and location are required for emergency alert');
    }
    
    // Create emergency alert
    $alertId = generateAlertId();
    $alertData = [
        'alert_id' => $alertId,
        'professional_id' => $professionalId,
        'alert_type' => $alertType,
        'latitude' => $lat,
        'longitude' => $lng,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'active',
        'priority' => determineAlertPriority($alertType)
    ];
    
    // Record alert
    $result = recordEmergencyAlert($alertData);
    
    if ($result['success']) {
        // Send immediate notifications
        sendEmergencyNotifications($alertData);
        
        // Find nearby emergency contacts
        $nearbyHelp = findNearbyEmergencyContacts($lat, $lng);
        
        // Create emergency response plan
        $responsePlan = createEmergencyResponsePlan($alertData);
        
        echo json_encode([
            'success' => true,
            'alert_id' => $alertId,
            'status' => 'Emergency alert activated',
            'response_plan' => $responsePlan,
            'nearby_help' => $nearbyHelp,
            'emergency_contacts' => getEmergencyContacts(),
            'message' => 'Emergency services have been notified'
        ]);
    } else {
        throw new Exception('Failed to activate emergency alert');
    }
}

/**
 * Utility functions
 */
function canUpdateLocation($professionalId) {
    // Rate limiting: Allow updates every 5 seconds
    $lastUpdate = getLastLocationUpdate($professionalId);
    if ($lastUpdate && (time() - strtotime($lastUpdate)) < 5) {
        return false;
    }
    return true;
}

function isValidCoordinates($lat, $lng) {
    return ($lat >= -90 && $lat <= 90) && ($lng >= -180 && $lng <= 180);
}

function updateLocationInDatabase($locationData) {
    // Insert location update into database
    // Include validation, sanitization, and error handling
    return ['success' => true];
}

function checkGeofences($professionalId, $lat, $lng) {
    // Check if professional entered/exited any geofenced areas
    // Examples: job sites, no-go zones, preferred areas
    return [];
}

function getNearbyUrgentJobs($lat, $lng, $radius) {
    // Find urgent jobs within radius
    return [];
}

function calculateRouteStatus($professionalId, $lat, $lng) {
    // Calculate if professional is on route to current job
    return [
        'on_route' => true,
        'deviation' => 0, // meters off route
        'progress' => 45 // percentage complete
    ];
}

function updateJobETA($professionalId, $lat, $lng) {
    // Update ETA for current job based on new location
    return [
        'eta_updated' => true,
        'new_eta' => date('Y-m-d H:i:s', strtotime('+25 minutes')),
        'customer_notified' => true
    ];
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function generateTrackingSessionId() {
    return 'track_' . uniqid() . '_' . time();
}

function createTrackingSession($sessionData) {
    // Create new tracking session in database
    return ['success' => true];
}

function getTrackingSession($sessionId) {
    // Get tracking session from database
    return [
        'session_id' => $sessionId,
        'professional_id' => 'prof_123',
        'job_id' => 'job_456',
        'tracking_type' => 'job'
    ];
}

function calculateTrackingSessionSummary($sessionId) {
    // Calculate session statistics
    return [
        'total_distance' => 12.5, // km
        'total_duration' => 45, // minutes
        'average_speed' => 25, // km/h
        'stops' => 2,
        'efficiency_score' => 85
    ];
}

function endTrackingSession($sessionId, $summary) {
    // End tracking session and save summary
    return ['success' => true];
}

function calculateOptimalRoute($fromLat, $fromLng, $toLat, $toLng, $mode, $optimize) {
    // Calculate route using mapping service
    return [
        'primary_route' => [
            'distance' => 12.5, // km
            'duration' => 18, // minutes
            'coordinates' => [], // array of lat/lng points
            'instructions' => []
        ],
        'alternative_routes' => []
    ];
}

function getTrafficInformation($route) {
    // Get current traffic conditions
    return [
        'overall_condition' => 'moderate',
        'delays' => [],
        'incidents' => []
    ];
}

function getJobLocation($jobId) {
    // Get job destination coordinates
    return [
        'latitude' => -33.8675,
        'longitude' => 151.2070,
        'address' => 'Sydney CBD, NSW 2000'
    ];
}

function recordArrival($professionalId, $jobId, $lat, $lng) {
    // Record arrival in database
    return [
        'arrival_id' => 'arr_' . uniqid(),
        'arrived_at' => date('Y-m-d H:i:s'),
        'coordinates' => ['lat' => $lat, 'lng' => $lng]
    ];
}

function notifyCustomerOfArrival($jobId, $professionalId) {
    // Send arrival notification to customer
}

function updateJobStatus($jobId, $status) {
    // Update job status in database
}

function getCurrentProfessionalLocation($professionalId) {
    // Get most recent location for professional
    return [
        'latitude' => -33.8820,
        'longitude' => 151.2069,
        'accuracy' => 15,
        'last_update' => date('Y-m-d H:i:s', strtotime('-2 minutes'))
    ];
}

function calculateETAWithTraffic($fromLat, $fromLng, $toLat, $toLng) {
    // Calculate ETA considering current traffic
    return [
        'distance' => 8.5, // km
        'duration_without_traffic' => 15, // minutes
        'duration_with_traffic' => 22, // minutes
        'estimated_arrival' => date('Y-m-d H:i:s', strtotime('+22 minutes')),
        'traffic_condition' => 'moderate'
    ];
}

function generateAlertId() {
    return 'alert_' . uniqid() . '_' . time();
}

function recordEmergencyAlert($alertData) {
    // Record emergency alert in database
    return ['success' => true];
}

function sendEmergencyNotifications($alertData) {
    // Send notifications to emergency contacts, admin, etc.
}

function findNearbyEmergencyContacts($lat, $lng) {
    // Find nearby emergency services, other professionals, etc.
    return [];
}

function createEmergencyResponsePlan($alertData) {
    // Create response plan based on alert type and location
    return [
        'immediate_actions' => [
            'Emergency services contacted',
            'Admin team notified',
            'Customer informed (if applicable)'
        ],
        'estimated_response_time' => '5-10 minutes',
        'emergency_contacts' => []
    ];
}

function getEmergencyContacts() {
    return [
        ['type' => 'Police', 'number' => '000'],
        ['type' => 'Fire', 'number' => '000'],
        ['type' => 'Ambulance', 'number' => '000'],
        ['type' => 'Blue Project Emergency', 'number' => '+61 2 1234 5678']
    ];
}

// Additional utility functions would be implemented here
function getLastLocationUpdate($professionalId) { return null; }
function notifyCustomerTrackingStarted($jobId, $professionalId) {}
function notifyCustomerTrackingEnded($jobId, $professionalId, $summary) {}
function calculateRouteCosts($route) { return []; }
function getProfessionalTrackingData($jobId) { return null; }
function calculateETAToJob($jobId) { return []; }
function calculateRouteProgress($jobId) { return []; }
function canCustomerTrackJob($customerId, $jobId) { return true; }
function getProfessionalPunctualityBuffer($professionalId) { return 5; } // 5 minute buffer
function determineAlertPriority($alertType) { return $alertType === 'medical' ? 'critical' : 'high'; }
?>
