<?php
/**
 * API para Informações de Booking - Blue Project V2
 * Endpoint: /api/booking/{booking_id}
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit();
}

// Inclui configurações do booking principal
require_once '../booking.php';

try {
    // Extrai booking_id da URL
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    $pathParts = explode('/', trim($pathInfo, '/'));
    
    if (count($pathParts) < 2 || empty($pathParts[1])) {
        throw new Exception('Booking ID é obrigatório');
    }
    
    $bookingId = $pathParts[1];
    
    // Carrega informações completas do booking
    $bookingInfo = loadFullBookingInfo($bookingId);
    if (!$bookingInfo) {
        throw new Exception('Booking não encontrado');
    }
    
    // Carrega histórico do cliente
    $customerHistory = getCustomerHistory($bookingInfo['customer_email']);
    
    // Determina tier de pausas
    $pauseTier = determinePauseTier($customerHistory, $pauseConfig);
    
    // Gera metadata para Stripe
    $stripeMetadata = generateStripeMetadata([
        'booking_id' => $bookingId,
        'customer_email' => $bookingInfo['customer_email'],
        'customer_name' => $bookingInfo['customer_name'],
        'service_type' => $bookingInfo['service_type'],
        'recurrence_pattern' => $bookingInfo['recurrence_pattern'],
        'total_amount' => $bookingInfo['total_amount'],
        'pause_tier' => $pauseTier,
        'used_pauses' => $customerHistory['used_pauses']
    ], $pauseConfig, $cancellationConfig);
    
    echo json_encode([
        'success' => true,
        'booking' => [
            'booking_id' => $bookingInfo['booking_id'],
            'status' => $bookingInfo['status'],
            'service_type' => $bookingInfo['service_type'],
            'recurrence_pattern' => $bookingInfo['recurrence_pattern'],
            'total_amount' => $bookingInfo['total_amount'],
            'remaining_services' => $bookingInfo['remaining_services'],
            'services_completed' => $bookingInfo['services_completed'],
            'next_service_date' => $bookingInfo['next_service_date'],
            'created_at' => $bookingInfo['created_at'],
            'updated_at' => $bookingInfo['updated_at']
        ],
        'customer' => [
            'email' => $bookingInfo['customer_email'],
            'name' => $bookingInfo['customer_name'],
            'phone' => $bookingInfo['customer_phone'],
            'address' => $bookingInfo['service_address']
        ],
        'pause_info' => [
            'tier' => $pauseTier,
            'used_pauses' => $customerHistory['used_pauses'],
            'remaining_pauses' => max(0, $pauseTier['free_pauses'] - $customerHistory['used_pauses']),
            'can_pause' => $bookingInfo['status'] === 'active',
            'next_free_pause_date' => calculateNextFreePauseDate($customerHistory)
        ],
        'cancellation_info' => [
            'can_cancel' => in_array($bookingInfo['status'], ['active', 'paused']),
            'within_free_window' => isWithinFreeCancellationWindow($bookingInfo),
            'estimated_penalty' => calculateCancellationPenalty($bookingInfo, $cancellationConfig)
        ],
        'stripe' => [
            'subscription_id' => $bookingInfo['stripe_subscription_id'],
            'metadata' => $stripeMetadata
        ],
        'history' => [
            'pauses' => getPauseHistory($bookingInfo['customer_email']),
            'modifications' => getBookingModifications($bookingId)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    error_log("Booking API Error: " . $e->getMessage());
}

/**
 * Funções auxiliares
 */

function loadFullBookingInfo($bookingId) {
    // Simula carregamento completo do banco - substitua pela sua implementação
    return [
        'booking_id' => $bookingId,
        'customer_email' => 'customer@example.com',
        'customer_name' => 'John Doe',
        'customer_phone' => '+61 400 000 000',
        'service_type' => 'home_cleaning',
        'service_address' => '123 Main St, Sydney NSW 2000',
        'recurrence_pattern' => 'weekly',
        'total_amount' => 600.00,
        'remaining_services' => 8,
        'services_completed' => 4,
        'next_service_date' => date('Y-m-d', strtotime('+1 week')),
        'stripe_subscription_id' => 'sub_' . uniqid(),
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 month')),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

function getCustomerHistory($customerEmail) {
    // Simula histórico do cliente - substitua pela sua implementação
    return [
        'total_services' => 50,
        'services_since' => 25, // Últimas 26 semanas
        'used_pauses' => 2,
        'last_pause_date' => '2023-12-15',
        'first_service_date' => '2023-01-01',
        'loyalty_tier' => 'standard'
    ];
}

function calculateNextFreePauseDate($customerHistory) {
    // Calcula quando o próximo período de pausas gratuitas estará disponível
    if (empty($customerHistory['last_pause_date'])) {
        return date('Y-m-d'); // Pode pausar agora
    }
    
    $lastPause = new DateTime($customerHistory['last_pause_date']);
    $nextPeriod = clone $lastPause;
    $nextPeriod->add(new DateInterval('P26W')); // 26 semanas
    
    return $nextPeriod->format('Y-m-d');
}

function isWithinFreeCancellationWindow($bookingInfo) {
    global $cancellationConfig;
    
    $createdAt = new DateTime($bookingInfo['created_at']);
    $now = new DateTime();
    $hoursDiff = ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600;
    
    return $hoursDiff <= ($cancellationConfig['free_cancellation_hours'] ?? 48);
}

function getPauseHistory($customerEmail) {
    // Simula histórico de pausas - substitua pela sua implementação
    return [
        [
            'pause_id' => 'pause_001',
            'start_date' => '2023-12-15',
            'end_date' => '2023-12-29',
            'duration_days' => 14,
            'reason' => 'vacation',
            'fee' => 0.00,
            'status' => 'completed'
        ],
        [
            'pause_id' => 'pause_002',
            'start_date' => '2023-08-10',
            'end_date' => '2023-08-17',
            'duration_days' => 7,
            'reason' => 'financial',
            'fee' => 0.00,
            'status' => 'completed'
        ]
    ];
}

function getBookingModifications($bookingId) {
    // Simula histórico de modificações - substitua pela sua implementação
    return [
        [
            'modification_id' => 'mod_001',
            'type' => 'frequency_change',
            'description' => 'Changed from weekly to fortnightly',
            'date' => '2023-11-15',
            'by' => 'customer'
        ],
        [
            'modification_id' => 'mod_002',
            'type' => 'address_change',
            'description' => 'Updated service address',
            'date' => '2023-10-20',
            'by' => 'customer'
        ]
    ];
}
?>
