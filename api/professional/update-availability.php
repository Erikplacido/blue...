<?php
/**
 * API - Update Professional Availability
 * Updates availability status for selected time slots
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    $professional_id = (int)($data['professional_id'] ?? 0);
    $date = $data['date'] ?? '';
    $timeSlots = $data['time_slots'] ?? [];
    $status = $data['status'] ?? '';
    
    if (!$professional_id || !$date || !$timeSlots || !$status) {
        throw new Exception('Missing required fields: professional_id, date, time_slots, status');
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }
    
    // Validate status
    $validStatuses = ['available', 'unavailable', 'blocked'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status. Must be: ' . implode(', ', $validStatuses));
    }
    
    // In a real application, you would update the database here
    // For demo purposes, we'll just simulate the update
    
    $updatedSlots = [];
    foreach ($timeSlots as $time) {
        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            continue; // Skip invalid times
        }
        
        $updatedSlots[] = [
            'date' => $date,
            'time' => $time,
            'old_status' => 'unknown', // In real app, get from database
            'new_status' => $status,
            'professional_id' => $professional_id
        ];
    }
    
    // Simulate database operation delay
    usleep(100000); // 0.1 second delay
    
    $response = [
        'success' => true,
        'message' => count($updatedSlots) . ' time slots updated successfully',
        'updated_slots' => $updatedSlots,
        'professional_id' => $professional_id,
        'date' => $date,
        'new_status' => $status,
        'timestamp' => date('Y-m-d H:i:s')
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
