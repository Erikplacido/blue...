<?php
/**
 * API: Get Available Days for Service Calendar
 * Returns days where professionals have availability for selected service
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Load configuration  
require_once __DIR__ . '/../config.php';

try {
    // Get and validate parameters - Handle both web and CLI
    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
    $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    
    if (!$service_id || $service_id <= 0) {
        throw new Exception('service_id is required and must be a valid positive integer');
    }
    
    if ($month < 1 || $month > 12) {
        throw new Exception('month must be between 1 and 12');
    }
    
    if ($year < 2024 || $year > 2030) {
        throw new Exception('year must be between 2024 and 2030');
    }
    
    // Calculate minimum booking date (48 hours from now)
    $now = new DateTime('now', new DateTimeZone('Australia/Sydney'));
    $minimum_datetime = clone $now;
    $minimum_datetime->add(new DateInterval('PT48H')); // +48 hours
    $minimum_date = $minimum_datetime->format('Y-m-d');
    
    // Query to get available days with booking counts
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            DAY(pa.date) as day_number,
            DATE(pa.date) as full_date,
            COUNT(DISTINCT CONCAT(pa.id, '_', HOUR(pa.start_time))) as total_time_slots,
            COUNT(DISTINCT b.id) as booked_slots,
            SUM(pa.max_concurrent_bookings) as total_capacity,
            (SUM(pa.max_concurrent_bookings) - COALESCE(COUNT(DISTINCT b.id), 0)) as available_capacity,
            MIN(pa.start_time) as earliest_time,
            MAX(pa.end_time) as latest_time
        FROM professional_availability pa
        INNER JOIN professional_services ps ON pa.professional_id = ps.professional_id
        INNER JOIN professionals p ON pa.professional_id = p.id
        LEFT JOIN bookings b ON (
            b.professional_id = pa.professional_id 
            AND DATE(b.scheduled_date) = DATE(pa.date)
            AND b.status IN ('confirmed', 'in_progress', 'pending')
            AND TIME(b.scheduled_time) >= pa.start_time 
            AND TIME(b.scheduled_time) < pa.end_time
        )
        WHERE ps.service_id = :service_id
        AND ps.is_active = 1
        AND p.status = 'active'
        AND pa.is_available = 1
        AND MONTH(pa.date) = :month
        AND YEAR(pa.date) = :year
        AND DATE(pa.date) >= :minimum_date
        GROUP BY DATE(pa.date), DAY(pa.date)
        HAVING available_capacity > 0
        ORDER BY pa.date ASC
    ");
    
    $stmt->execute([
        ':service_id' => $service_id,
        ':month' => $month,
        ':year' => $year,
        ':minimum_date' => $minimum_date
    ]);
    
    $results = $stmt->fetchAll();
    
    // Format response data
    $available_days = [];
    $slots_details = [];
    
    foreach ($results as $row) {
        $day = (int)$row['day_number'];
        $available_days[] = $day;
        
        $slots_details[$day] = [
            'date' => $row['full_date'],
            'total_slots' => (int)$row['total_time_slots'],
            'booked_slots' => (int)$row['booked_slots'],
            'total_capacity' => (int)$row['total_capacity'],
            'available_capacity' => (int)$row['available_capacity'],
            'earliest_time' => $row['earliest_time'],
            'latest_time' => $row['latest_time']
        ];
    }
    
    // Get service details for context
    $service_stmt = $pdo->prepare("SELECT name, duration_minutes FROM services WHERE id = ?");
    $service_stmt->execute([$service_id]);
    $service_info = $service_stmt->fetch();
    
    // Success response
    echo json_encode([
        'success' => true,
        'service_id' => $service_id,
        'service_name' => $service_info['name'] ?? 'Unknown Service',
        'service_duration' => $service_info['duration_minutes'] ?? 60,
        'month' => $month,
        'year' => $year,
        'available_days' => $available_days,
        'slots_details' => $slots_details,
        'total_available_days' => count($available_days),
        'minimum_booking_date' => $minimum_date,
        'current_time' => $now->format('Y-m-d H:i:s T'),
        'timezone' => 'Australia/Sydney',
        'generated_at' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'query_executed' => true,
            'results_found' => count($results),
            'minimum_date_calculated' => $minimum_datetime->format('Y-m-d H:i:s T'),
            '48h_rule_active' => true
        ]
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
            'month' => $month ?? null,
            'year' => $year ?? null
        ]
    ]);
}
?>
