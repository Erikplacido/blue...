<?php
/**
 * API: Dynamic System Configuration - FIXED VERSION
 * Endpoint: /api/system-config-dynamic.php
 * Returns all configurations needed for booking2_dynamic.php
 */

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/australian-database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get database instance
    $db = AustralianDatabase::getInstance();
    $connection = $db->getConnection();
    
    // Fetch system settings
    $stmt = $connection->query("
        SELECT setting_key, setting_value, setting_type 
        FROM system_settings 
        ORDER BY setting_key
    ");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to key-value format
    $config = [];
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];
        
        // Convert types
        switch ($setting['setting_type']) {
            case 'integer':
                $value = (int)$value;
                break;
            case 'decimal':
                $value = (float)$value;
                break;
            case 'boolean':
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
            case 'json':
                $value = json_decode($value, true) ?? $value;
                break;
        }
        
        $config[$setting['setting_key']] = $value;
    }
    
    // Fetch service inclusions
    $stmt = $connection->query("
        SELECT id, name, icon
        FROM service_inclusions 
        WHERE is_active = TRUE 
        ORDER BY sort_order, name
    ");
    $inclusions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch service extras
    $stmt = $connection->query("
        SELECT id, name, price, icon
        FROM service_extras 
        WHERE is_active = TRUE 
        ORDER BY sort_order, name
    ");
    $extras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch preferences
    $stmt = $connection->query("
        SELECT id, name, icon
        FROM cleaning_preferences 
        WHERE is_active = TRUE 
        ORDER BY sort_order, name
    ");
    $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch operating hours
    $stmt = $connection->query("
        SELECT day_of_week, open_time, close_time, is_open
        FROM operating_hours 
        ORDER BY day_of_week
    ");
    $operating_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert hours to a more friendly format
    $days_map = [
        1 => 'Monday', 
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ];
    
    $hours = [];
    foreach ($operating_hours as $hour) {
        $day_name = $days_map[$hour['day_of_week']];
        $hours[$day_name] = [
            'available' => (bool)$hour['is_open'],
            'start' => $hour['open_time'],
            'end' => $hour['close_time']
        ];
    }
    
    // Return complete response
    $response = [
        'success' => true,
        'data' => [
            'settings' => $config,
            'inclusions' => $inclusions,
            'extras' => $extras,
            'preferences' => $preferences,
            'operating_hours' => $hours
        ],
        'summary' => [
            'settings_count' => count($config),
            'inclusions_count' => count($inclusions),
            'extras_count' => count($extras),
            'preferences_count' => count($preferences),
            'hours_count' => count($hours)
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'debug' => getenv('APP_DEBUG') === 'true' ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ]);
    
    error_log("System Config API Error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'debug' => getenv('APP_DEBUG') === 'true' ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ]);
    
    error_log("System Config API Error: " . $e->getMessage());
}
?>
