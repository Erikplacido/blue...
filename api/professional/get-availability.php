<?php
/**
 * API - Get Professional Availability
 * Returns availability data for professional schedule calendar
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
    $month = (int)($_GET['month'] ?? date('n'));
    $year = (int)($_GET['year'] ?? date('Y'));
    $professional_id = (int)($_GET['professional_id'] ?? 1);
    
    // Validate parameters
    if ($month < 1 || $month > 12 || $year < 2024 || $year > 2030) {
        throw new Exception('Invalid month or year');
    }
    
    // Calculate days in month
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $availableDays = [];
    $slotsDetails = [];
    $availabilityData = [];
    
    // Generate demo availability data
    // Professional typically works Monday to Friday, with some weekend availability
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        $dayOfWeek = date('w', strtotime($date)); // 0 = Sunday, 6 = Saturday
        
        // Skip past dates
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            continue;
        }
        
        // Professional available Monday to Friday (1-5) and some Saturdays
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            // Weekdays - full availability
            $availableDays[] = $day;
            $availableSlots = rand(4, 8); // 4-8 available slots per day
            $totalSlots = 8; // 8 total slots per day (8 AM - 4 PM)
            
            $slotsDetails[$day] = [
                'available_capacity' => $availableSlots,
                'total_capacity' => $totalSlots,
                'booked' => $totalSlots - $availableSlots
            ];
            
            $availabilityData[$date] = [
                'available' => $availableSlots,
                'booked' => $totalSlots - $availableSlots,
                'blocked' => 0
            ];
        } elseif ($dayOfWeek === 6 && rand(0, 100) > 60) {
            // Saturday - limited availability (40% chance)
            $availableDays[] = $day;
            $availableSlots = rand(2, 4); // 2-4 available slots on Saturday
            $totalSlots = 4; // Half day on Saturday
            
            $slotsDetails[$day] = [
                'available_capacity' => $availableSlots,
                'total_capacity' => $totalSlots,
                'booked' => $totalSlots - $availableSlots
            ];
            
            $availabilityData[$date] = [
                'available' => $availableSlots,
                'booked' => $totalSlots - $availableSlots,
                'blocked' => 0
            ];
        }
        // Sunday (0) - not available
    }
    
    $response = [
        'success' => true,
        'available_days' => $availableDays,
        'slots_details' => $slotsDetails,
        'availability_data' => $availabilityData,
        'month' => $month,
        'year' => $year,
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
