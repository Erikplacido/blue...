<?php
/**
 * API para Cálculo de Penalidade de Cancelamento - Blue Project V2
 * Endpoint: /api/cancellation-penalty/{booking_id}
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
    
    // Verifica se está dentro da janela de cancelamento gratuito
    $withinFreeWindow = isWithinFreeCancellationWindow($bookingInfo);
    
    if ($withinFreeWindow) {
        echo json_encode([
            'success' => true,
            'penalty_amount' => 0,
            'penalty_percentage' => 0,
            'within_free_window' => true,
            'free_cancellation_hours' => $cancellationConfig['free_cancellation_hours'] ?? 48,
            'remaining_free_hours' => calculateRemainingFreeHours($bookingInfo),
            'policy_type' => 'free_cancellation',
            'refund_policy' => 'full_refund'
        ]);
        exit();
    }
    
    // Calcula penalidade usando a função do sistema
    $penaltyInfo = calculateCancellationPenalty($bookingInfo, $cancellationConfig);
    
    // Calcula informações adicionais
    $totalRefund = calculateRefundAmount($bookingInfo, $penaltyInfo);
    $effectiveRefund = $totalRefund - $penaltyInfo['penalty_amount'];
    
    echo json_encode([
        'success' => true,
        'penalty_amount' => $penaltyInfo['penalty_amount'],
        'penalty_percentage' => $penaltyInfo['penalty_percentage'],
        'within_free_window' => false,
        'policy_type' => $penaltyInfo['policy_type'],
        'refund_policy' => $penaltyInfo['refund_policy'],
        'booking_details' => [
            'total_amount' => $bookingInfo['total_amount'],
            'remaining_services' => $bookingInfo['remaining_services'],
            'recurrence_pattern' => $bookingInfo['recurrence_pattern'],
            'services_used' => $bookingInfo['services_used'] ?? 0
        ],
        'financial_breakdown' => [
            'total_paid' => $bookingInfo['total_amount'],
            'penalty_amount' => $penaltyInfo['penalty_amount'],
            'gross_refund' => $totalRefund,
            'net_refund' => $effectiveRefund,
            'currency' => 'AUD'
        ],
        'timeline' => [
            'booking_created' => $bookingInfo['created_at'],
            'free_cancellation_deadline' => calculateFreeCancellationDeadline($bookingInfo),
            'current_time' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    error_log("Cancellation Penalty API Error: " . $e->getMessage());
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
        'total_amount' => 600.00,    // $150 x 4 serviços
        'remaining_services' => 3,   // 3 serviços restantes
        'services_used' => 1,        // 1 serviço já realizado
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 week'))
    ];
}

function isWithinFreeCancellationWindow($bookingInfo) {
    global $cancellationConfig;
    
    $createdAt = new DateTime($bookingInfo['created_at']);
    $now = new DateTime();
    $hoursDiff = ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600;
    
    return $hoursDiff <= ($cancellationConfig['free_cancellation_hours'] ?? 48);
}

function calculateRemainingFreeHours($bookingInfo) {
    global $cancellationConfig;
    
    $createdAt = new DateTime($bookingInfo['created_at']);
    $now = new DateTime();
    $hoursElapsed = ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600;
    $freeHours = $cancellationConfig['free_cancellation_hours'] ?? 48;
    
    return max(0, $freeHours - $hoursElapsed);
}

function calculateFreeCancellationDeadline($bookingInfo) {
    global $cancellationConfig;
    
    $createdAt = new DateTime($bookingInfo['created_at']);
    $freeHours = $cancellationConfig['free_cancellation_hours'] ?? 48;
    $deadline = clone $createdAt;
    $deadline->add(new DateInterval('PT' . $freeHours . 'H'));
    
    return $deadline->format('Y-m-d H:i:s');
}

function calculateRefundAmount($bookingInfo, $penaltyInfo) {
    // Calcula reembolso baseado na política
    $remainingValue = $bookingInfo['total_amount'] * 
                     ($bookingInfo['remaining_services'] / 
                      ($bookingInfo['remaining_services'] + ($bookingInfo['services_used'] ?? 0)));
    
    // Para recorrências, geralmente não há reembolso após o primeiro serviço
    if ($bookingInfo['recurrence_pattern'] !== 'one-time' && 
        ($bookingInfo['services_used'] ?? 0) > 0) {
        return 0;
    }
    
    return $remainingValue;
}
?>
