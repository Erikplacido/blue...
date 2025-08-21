<?php
/**
 * API: Get Available Time Slots for Specific Date and Service
 * Returns specific time slots available for booking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';

try {
    // Get and validate parameters
    $service_id = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);
    
    if (!$service_id) {
        throw new Exception('service_id is required');
    }
    
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('date is required in YYYY-MM-DD format');
    }
    
    // Validate date is not in the past
    if ($date < date('Y-m-d')) {
        throw new Exception('Cannot book dates in the past');
    }
    
    // Get service duration
    $service_stmt = $pdo->prepare("SELECT name, duration_minutes FROM services WHERE id = ? AND is_active = 1");
    $service_stmt->execute([$service_id]);
    $service = $service_stmt->fetch();
    
    if (!$service) {
        throw new Exception('Service not found or inactive');
    }
    
    $duration_minutes = $service['duration_minutes'];
    
    // Get professional availability for the date
    $stmt = $pdo->prepare("
        SELECT 
            pa.id as availability_id,
            pa.professional_id,
            pa.start_time,
            pa.end_time,
            pa.max_concurrent_bookings,
            p.first_name,
            p.last_name,
            COUNT(b.id) as current_bookings
        FROM professional_availability pa
        INNER JOIN professional_services ps ON pa.professional_id = ps.professional_id
        INNER JOIN professionals p ON pa.professional_id = p.id
        LEFT JOIN bookings b ON (
            b.professional_id = pa.professional_id 
            AND b.scheduled_date = pa.date 
            AND b.status IN ('confirmed', 'in_progress', 'pending')
            AND TIME(b.scheduled_time) >= pa.start_time 
            AND TIME(b.scheduled_time) < pa.end_time
        )
        WHERE ps.service_id = :service_id
        AND ps.is_active = 1
        AND p.status = 'active'
        AND pa.is_available = 1
        AND pa.date = :date
        GROUP BY pa.id, pa.professional_id, pa.start_time, pa.end_time
        HAVING current_bookings < pa.max_concurrent_bookings
        ORDER BY pa.start_time ASC, p.first_name ASC
    ");
    
    $stmt->execute([
        ':service_id' => $service_id,
        ':date' => $date
    ]);
    
    $availability_slots = $stmt->fetchAll();
    
    if (empty($availability_slots)) {
        echo json_encode([
            'success' => true,
            'service_id' => $service_id,
            'service_name' => $service['name'],
            'date' => $date,
            'available_times' => [],
            'message' => 'No availability for this date'
        ]);
        exit;
    }
    
    // Generate time slots based on availability windows and service duration
    $available_times = [];
    
    foreach ($availability_slots as $slot) {
        $start = new DateTime($slot['start_time']);
        $end = new DateTime($slot['end_time']);
        
        // Generate 30-minute intervals within the availability window
        $current = clone $start;
        while ($current < $end) {
            // Check if there's enough time for the full service
            $service_end = clone $current;
            $service_end->add(new DateInterval('PT' . $duration_minutes . 'M'));
            
            if ($service_end <= $end) {
                $time_key = $current->format('H:i');
                
                // Avoid duplicates and track capacity
                if (!isset($available_times[$time_key])) {
                    $available_times[$time_key] = [
                        'time' => $time_key,
                        'display_time' => $current->format('g:i A'),
                        'professionals' => [],
                        'total_capacity' => 0
                    ];
                }
                
                $available_times[$time_key]['professionals'][] = [
                    'id' => $slot['professional_id'],
                    'name' => $slot['first_name'] . ' ' . $slot['last_name'],
                    'available_slots' => $slot['max_concurrent_bookings'] - $slot['current_bookings']
                ];
                
                $available_times[$time_key]['total_capacity'] += 
                    $slot['max_concurrent_bookings'] - $slot['current_bookings'];
            }
            
            // Move to next 30-minute slot
            $current->add(new DateInterval('PT30M'));
        }
    }
    
    // Sort and format final response
    ksort($available_times);
    $formatted_times = array_values($available_times);
    
    echo json_encode([
        'success' => true,
        'service_id' => $service_id,
        'service_name' => $service['name'],
        'service_duration' => $duration_minutes,
        'date' => $date,
        'formatted_date' => date('l, F j, Y', strtotime($date)),
        'available_times' => $formatted_times,
        'total_slots' => count($formatted_times),
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'debug' => DEBUG ? $e->getMessage() : 'Enable debug mode for details'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'provided_params' => [
            'service_id' => $service_id ?? null,
            'date' => $date ?? null
        ]
    ]);
}
?>
