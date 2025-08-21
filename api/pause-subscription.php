<?php
/**
 * API para Sistema de Pausas - Blue Project V2
 * Endpoint: /api/pause-subscription
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit();
}

// Inclui configurações
require_once '../booking3.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }
    
    // Validações obrigatórias
    $requiredFields = ['booking_id', 'start_date', 'duration'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Campo obrigatório: {$field}");
        }
    }
    
    $bookingId = $input['booking_id'];
    $startDate = $input['start_date'];
    $duration = intval($input['duration']);
    $reason = $input['reason'] ?? '';
    $tierInfo = $input['tier_info'] ?? [];
    
    // Validações de business rules
    if ($duration < 1 || $duration > ($pauseConfig['max_pause_duration_days'] ?? 90)) {
        throw new Exception('Duração de pausa inválida');
    }
    
    $startDateTime = new DateTime($startDate);
    $minDate = new DateTime();
    $minDate->add(new DateInterval('PT' . ($pauseConfig['minimum_notice_hours'] ?? 48) . 'H'));
    
    if ($startDateTime < $minDate) {
        throw new Exception('Data de início deve ser com pelo menos 48h de antecedência');
    }
    
    // Carrega informações do booking
    $bookingInfo = loadBookingInfo($bookingId);
    if (!$bookingInfo) {
        throw new Exception('Booking não encontrado');
    }
    
    // Determina se é pausa gratuita
    $customerHistory = getCustomerHistory($bookingInfo['customer_email']);
    $pauseTier = determinePauseTier($customerHistory, $pauseConfig);
    
    $usedPauses = $customerHistory['used_pauses'] ?? 0;
    $allowedPauses = $pauseTier['free_pauses'];
    $isFreePause = $usedPauses < $allowedPauses;
    
    // Calcula taxa se necessário
    $pauseFee = $isFreePause ? 0 : ($pauseConfig['pause_fee'] ?? 0);
    
    // Processa a pausa via Stripe
    $stripeResult = processStripePause($bookingInfo, [
        'start_date' => $startDate,
        'duration' => $duration,
        'reason' => $reason,
        'fee' => $pauseFee,
        'tier' => $pauseTier,
        'is_free' => $isFreePause
    ]);
    
    if (!$stripeResult['success']) {
        throw new Exception('Erro ao processar pausa no Stripe: ' . $stripeResult['error']);
    }
    
    // Salva no banco de dados
    $pauseId = savePauseRecord([
        'booking_id' => $bookingId,
        'customer_email' => $bookingInfo['customer_email'],
        'start_date' => $startDate,
        'end_date' => date('Y-m-d', strtotime($startDate . ' +' . $duration . ' days')),
        'duration_days' => $duration,
        'reason' => $reason,
        'fee' => $pauseFee,
        'tier_id' => $pauseTier['tier_id'],
        'is_free' => $isFreePause,
        'stripe_subscription_id' => $stripeResult['subscription_id'],
        'stripe_pause_id' => $stripeResult['pause_id'],
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Atualiza contador de pausas do cliente
    updateCustomerPauseCount($bookingInfo['customer_email'], $usedPauses + 1);
    
    // Envia email de confirmação
    sendPauseConfirmationEmail($bookingInfo, [
        'start_date' => $startDate,
        'end_date' => date('Y-m-d', strtotime($startDate . ' +' . $duration . ' days')),
        'duration' => $duration,
        'fee' => $pauseFee,
        'is_free' => $isFreePause
    ]);
    
    // Log da ação
    logAction('pause_subscription', [
        'booking_id' => $bookingId,
        'pause_id' => $pauseId,
        'customer_email' => $bookingInfo['customer_email'],
        'duration' => $duration,
        'fee' => $pauseFee,
        'stripe_result' => $stripeResult
    ]);
    
    echo json_encode([
        'success' => true,
        'pause_id' => $pauseId,
        'message' => 'Assinatura pausada com sucesso',
        'details' => [
            'start_date' => $startDate,
            'end_date' => date('Y-m-d', strtotime($startDate . ' +' . $duration . ' days')),
            'duration_days' => $duration,
            'fee' => $pauseFee,
            'is_free' => $isFreePause,
            'remaining_free_pauses' => max(0, $allowedPauses - ($usedPauses + 1))
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    // Log do erro
    error_log("Pause API Error: " . $e->getMessage() . " - Input: " . json_encode($input ?? []));
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
        'total_amount' => 150.00,
        'stripe_subscription_id' => 'sub_' . uniqid(),
        'status' => 'active'
    ];
}

function getCustomerHistory($customerEmail) {
    // Simula histórico do cliente - substitua pela sua implementação
    return [
        'total_services' => 20,
        'services_since' => 20,
        'used_pauses' => 1,
        'last_pause_date' => '2024-01-15'
    ];
}

function processStripePause($bookingInfo, $pauseData) {
    try {
        // ✅ ATUALIZADO: Usar StripeManager unificado
        $stripeManager = StripeManager::getInstance();
        $taxConfig = $stripeManager->getTaxConfig();
        
        $subscriptionId = $bookingInfo['stripe_subscription_id'];
        
        // Calcula taxa de processamento usando política unificada
        $pauseFee = $bookingInfo['total_amount'] * 0.05; // 5% taxa de processamento
        
        // Log da operação com nova arquitetura
        error_log("📋 Subscription Pause - Using unified StripeManager");
        error_log("💰 Base amount: $" . $bookingInfo['total_amount']);
        error_log("💸 Pause fee: $" . $pauseFee);
        error_log("🏷️  Tax policy: " . $taxConfig['policy_description']);
        
        $stripeResponse = [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'pause_id' => 'pause_' . uniqid(),
            'pause_start' => $pauseData['start_date'],
            'pause_end' => date('Y-m-d', strtotime($pauseData['start_date'] . ' +' . $pauseData['duration'] . ' days')),
            'pause_fee' => $pauseFee,
            'tax_config' => $taxConfig,
            'stripe_manager_version' => 'unified_2025'
        ];
        
        return $stripeResponse;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function savePauseRecord($pauseData) {
    // Simula salvamento no banco - substitua pela sua implementação
    $pauseId = 'pause_' . uniqid();
    
    // Aqui você salvaria no seu banco de dados
    // INSERT INTO pauses (pause_id, booking_id, customer_email, ...) VALUES (...)
    
    return $pauseId;
}

function updateCustomerPauseCount($customerEmail, $newCount) {
    // Simula atualização do contador - substitua pela sua implementação
    // UPDATE customers SET used_pauses = ? WHERE email = ?
    return true;
}

function sendPauseConfirmationEmail($bookingInfo, $pauseDetails) {
    // Simula envio de email - substitua pela sua implementação
    // Aqui você enviaria um email de confirmação da pausa
    return true;
}

function logAction($action, $data) {
    // Log simples - substitua pela sua implementação
    $logEntry = [
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data
    ];
    
    error_log("Action Log: " . json_encode($logEntry));
    return true;
}
?>
