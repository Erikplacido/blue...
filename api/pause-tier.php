<?php
/**
 * API para Determinação de Tier de Pausas - Blue Project V2
 * Endpoint: /api/pause-tier/{booking_id}
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

// Inclui configurações
require_once '../booking2.php';

try {
    // Extrai booking_id da URL
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    $pathParts = explode('/', trim($pathInfo, '/'));
    
    if (count($pathParts) < 2 || empty($pathParts[1])) {
        throw new Exception('Booking ID é obrigatório');
    }
    
    $bookingId = $pathParts[1];
    
    // Carrega informações do booking
    $bookingInfo = loadBookingInfo($bookingId);
    if (!$bookingInfo) {
        throw new Exception('Booking não encontrado');
    }
    
    // Carrega histórico do cliente
    $customerHistory = getCustomerHistory($bookingInfo['customer_email']);
    
    // Determina o tier de pausas
    $pauseTier = determinePauseTier($customerHistory, $pauseConfig);
    
    // Calcula informações adicionais
    $usedPauses = $customerHistory['used_pauses'] ?? 0;
    $remainingPauses = max(0, $pauseTier['free_pauses'] - $usedPauses);
    
    // Busca histórico de pausas
    $pauseHistory = getPauseHistory($bookingInfo['customer_email']);
    
    echo json_encode([
        'success' => true,
        'tier_id' => $pauseTier['tier_id'],
        'tier_name' => $pauseTier['tier_name'],
        'free_pauses' => $pauseTier['free_pauses'],
        'used_pauses' => $usedPauses,
        'remaining_pauses' => $remainingPauses,
        'period_weeks' => $pauseTier['period_weeks'],
        'min_services' => $pauseTier['min_services'],
        'max_services' => $pauseTier['max_services'],
        'customer_services' => $customerHistory['services_since'] ?? 0,
        'total_services' => $customerHistory['total_services'] ?? 0,
        'pause_history' => $pauseHistory,
        'stripe_metadata' => $pauseTier['stripe_metadata'] ?? []
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    error_log("Pause Tier API Error: " . $e->getMessage());
}

/**
 * Funções auxiliares
 */

function loadBookingInfo($bookingId) {
    // Simula carregamento do banco - substitua pela sua implementação
    return [
        'booking_id' => $bookingId,
        'customer_email' => 'customer@example.com',
        'customer_name' => 'Customer Name',
        'service_type' => 'home_cleaning',
        'recurrence_pattern' => 'weekly',
        'status' => 'active'
    ];
}

function getCustomerHistory($customerEmail) {
    // Simula histórico do cliente - substitua pela sua implementação
    // Aqui você buscaria do banco de dados:
    // - Total de serviços realizados
    // - Serviços no período de análise
    // - Pausas já utilizadas
    
    return [
        'total_services' => 25, // Total de serviços já realizados
        'services_since' => 20, // Serviços no período de análise (últimas 26 semanas)
        'used_pauses' => 1,     // Pausas já utilizadas no período
        'last_service_date' => '2024-01-15',
        'first_service_date' => '2023-06-01'
    ];
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
            'is_free' => true,
            'status' => 'completed'
        ]
    ];
}
?>
