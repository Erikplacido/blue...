<?php
/**
 * API - Get Time Slots for Date
 * Returns time slots for a specific date for professional schedule management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Get parameters
    $date = $_GET['date'] ?? '';
    $professional_id = (int)($_GET['professional_id'] ?? 1);
    
    // Validate date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }
    
    $dateObj = new DateTime($date);
    $dayOfWeek = $dateObj->format('w'); // 0 = Sunday, 6 = Saturday
    
    // Generate time slots (8 AM to 6 PM, 1-hour slots)
    $timeSlots = [];
    $stats = ['available' => 0, 'booked' => 0, 'blocked' => 0];
    
    // Professional schedule: 8 AM - 6 PM (10 slots total)
    for ($hour = 8; $hour < 18; $hour++) {
        $time = sprintf('%02d:00', $hour);
        $displayTime = date('g:00 A', strtotime($time));
        
        // Determine slot status based on various factors
        $status = 'available'; // Default
        
        // Past dates/times are blocked
        if (strtotime("$date $time") < time()) {
            $status = 'blocked';
        }
        // Some slots are booked (demo data)
        elseif (rand(0, 100) < 20) {
            $status = 'booked';
        }
        // Lunch break (12-1 PM) is blocked
        elseif ($hour === 12) {
            $status = 'blocked';
        }
        // Weekend limited hours
        elseif ($dayOfWeek === 6 && ($hour < 9 || $hour > 13)) {
            $status = 'blocked';
        }
        // Sunday is closed
        elseif ($dayOfWeek === 0) {
            $status = 'blocked';
        }
        
        $timeSlots[] = [
            'time' => $time,
            'display_time' => $displayTime,
            'status' => $status,
            'is_lunch_break' => ($hour === 12),
            'is_weekend' => ($dayOfWeek === 0 || $dayOfWeek === 6)
        ];
        
        $stats[$status]++;
    }
    
    $response = [
        'success' => true,
        'time_slots' => $timeSlots,
        'stats' => $stats,
        'date' => $date,
        'day_of_week' => $dayOfWeek,
        'professional_id' => $professional_id,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
