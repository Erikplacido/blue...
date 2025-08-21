<?php
/**
 * API para Sistema de Cancelamentos - Blue Project V2
 * Endpoint: /api/cancel-subscription
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
    $requiredFields = ['booking_id', 'reason', 'confirmed'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Campo obrigatório: {$field}");
        }
    }
    
    if (!$input['confirmed']) {
        throw new Exception('Cancelamento deve ser confirmado');
    }
    
    $bookingId = $input['booking_id'];
    $reason = $input['reason'];
    $feedback = $input['feedback'] ?? '';
    
    // Carrega informações do booking
    $bookingInfo = loadBookingInfo($bookingId);
    if (!$bookingInfo) {
        throw new Exception('Booking não encontrado');
    }
    
    if ($bookingInfo['status'] === 'cancelled') {
        throw new Exception('Esta assinatura já foi cancelada');
    }
    
    // Verifica se está dentro do período de cancelamento gratuito
    $withinFreeWindow = isWithinFreeCancellationWindow($bookingInfo);
    
    // Calcula penalidade se aplicável
    $penaltyInfo = null;
    if (!$withinFreeWindow) {
        $penaltyInfo = calculateCancellationPenalty($bookingInfo, $cancellationConfig);
    }
    
    // Processa cancelamento via Stripe
    $stripeResult = processStripeCancellation($bookingInfo, [
        'reason' => $reason,
        'feedback' => $feedback,
        'penalty' => $penaltyInfo,
        'immediate' => $cancellationConfig['immediate_cancellation'] ?? true
    ]);
    
    if (!$stripeResult['success']) {
        throw new Exception('Erro ao processar cancelamento no Stripe: ' . $stripeResult['error']);
    }
    
    // Processa pagamento da penalidade se houver
    $penaltyPaymentResult = null;
    if ($penaltyInfo && $penaltyInfo['penalty_amount'] > 0) {
        $penaltyPaymentResult = processPenaltyPayment($bookingInfo, $penaltyInfo);
        
        if (!$penaltyPaymentResult['success']) {
            throw new Exception('Erro ao processar pagamento da penalidade: ' . $penaltyPaymentResult['error']);
        }
    }
    
    // Salva cancelamento no banco de dados
    $cancellationId = saveCancellationRecord([
        'booking_id' => $bookingId,
        'customer_email' => $bookingInfo['customer_email'],
        'reason' => $reason,
        'feedback' => $feedback,
        'penalty_amount' => $penaltyInfo['penalty_amount'] ?? 0,
        'penalty_percentage' => $penaltyInfo['penalty_percentage'] ?? 0,
        'within_free_window' => $withinFreeWindow,
        'stripe_subscription_id' => $stripeResult['subscription_id'],
        'stripe_cancellation_id' => $stripeResult['cancellation_id'],
        'penalty_payment_id' => $penaltyPaymentResult['payment_id'] ?? null,
        'refund_amount' => $stripeResult['refund_amount'] ?? 0,
        'status' => 'completed',
        'cancelled_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Atualiza status do booking
    updateBookingStatus($bookingId, 'cancelled');
    
    // Processa reembolso se aplicável
    $refundResult = null;
    if ($stripeResult['refund_amount'] > 0) {
        $refundResult = processRefund($bookingInfo, $stripeResult['refund_amount']);
    }
    
    // Envia email de confirmação
    sendCancellationConfirmationEmail($bookingInfo, [
        'reason' => $reason,
        'feedback' => $feedback,
        'penalty_amount' => $penaltyInfo['penalty_amount'] ?? 0,
        'refund_amount' => $stripeResult['refund_amount'] ?? 0,
        'within_free_window' => $withinFreeWindow,
        'cancellation_date' => date('Y-m-d H:i:s')
    ]);
    
    // Processa survey se habilitado
    if ($cancellationConfig['cancellation_survey_enabled'] ?? false) {
        saveCancellationSurvey($cancellationId, $reason, $feedback);
    }
    
    // Log da ação
    logAction('cancel_subscription', [
        'booking_id' => $bookingId,
        'cancellation_id' => $cancellationId,
        'customer_email' => $bookingInfo['customer_email'],
        'reason' => $reason,
        'penalty_amount' => $penaltyInfo['penalty_amount'] ?? 0,
        'refund_amount' => $stripeResult['refund_amount'] ?? 0,
        'stripe_result' => $stripeResult
    ]);
    
    echo json_encode([
        'success' => true,
        'cancellation_id' => $cancellationId,
        'message' => 'Assinatura cancelada com sucesso',
        'details' => [
            'cancelled_at' => date('Y-m-d H:i:s'),
            'penalty_amount' => $penaltyInfo['penalty_amount'] ?? 0,
            'refund_amount' => $stripeResult['refund_amount'] ?? 0,
            'within_free_window' => $withinFreeWindow,
            'reason' => $reason,
            'payment_processed' => $penaltyPaymentResult !== null
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    // Log do erro
    error_log("Cancellation API Error: " . $e->getMessage() . " - Input: " . json_encode($input ?? []));
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
        'remaining_services' => 8,
        'stripe_subscription_id' => 'sub_' . uniqid(),
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

function processStripeCancellation($bookingInfo, $cancellationData) {
    global $cancellationConfig;
    
    try {
        // ✅ ATUALIZADO: Usar StripeManager unificado ao invés de configuração antiga
        $stripeManager = StripeManager::getInstance();
        
        // Verifica configuração de taxas unificada
        $taxConfig = $stripeManager->getTaxConfig();
        
        $subscriptionId = $bookingInfo['stripe_subscription_id'];
        
        // Calcula reembolso baseado na política unificada
        $refundAmount = 0;
        if (isWithinFreeCancellationWindow($bookingInfo)) {
            $baseAmount = $bookingInfo['total_amount'];
            $refundAmount = $baseAmount * 0.9; // 90% de reembolso
        }
        
        // Log da operação com nova arquitetura
        error_log("📋 Subscription Cancellation - Using unified StripeManager");
        error_log("💰 Base amount: $" . $bookingInfo['total_amount']);
        error_log("💸 Refund amount: $" . $refundAmount);
        error_log("🏷️  Tax policy: " . $taxConfig['policy_description']);
        
        // Retorna resultado consistente com sistema unificado
        $stripeResponse = [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'cancellation_id' => 'cancel_' . uniqid(),
            'cancelled_at' => time(),
            'refund_amount' => $refundAmount,
            'status' => 'cancelled',
            'tax_config' => $taxConfig,
            'stripe_manager_version' => 'unified_2025'
        ];
        
        return $stripeResponse;
        
    } catch (Exception $e) {
        error_log("❌ Stripe Cancellation Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function processPenaltyPayment($bookingInfo, $penaltyInfo) {
    try {
        // Aqui você processaria o pagamento da penalidade
        // Este é um exemplo simulado
        
        if ($penaltyInfo['penalty_amount'] <= 0) {
            return ['success' => true, 'payment_id' => null];
        }
        
        // Simula cobrança da penalidade
        $paymentResult = [
            'success' => true,
            'payment_id' => 'pi_' . uniqid(),
            'amount' => $penaltyInfo['penalty_amount'],
            'currency' => 'AUD',
            'status' => 'succeeded'
        ];
        
        return $paymentResult;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function processRefund($bookingInfo, $refundAmount) {
    try {
        // Aqui você processaria o reembolso
        // Este é um exemplo simulado
        
        if ($refundAmount <= 0) {
            return ['success' => true, 'refund_id' => null];
        }
        
        $refundResult = [
            'success' => true,
            'refund_id' => 're_' . uniqid(),
            'amount' => $refundAmount,
            'currency' => 'AUD',
            'status' => 'succeeded'
        ];
        
        return $refundResult;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function saveCancellationRecord($cancellationData) {
    // Simula salvamento no banco - substitua pela sua implementação
    $cancellationId = 'cancel_' . uniqid();
    
    // Aqui você salvaria no seu banco de dados
    // INSERT INTO cancellations (cancellation_id, booking_id, ...) VALUES (...)
    
    return $cancellationId;
}

function updateBookingStatus($bookingId, $status) {
    // Simula atualização do status - substitua pela sua implementação
    // UPDATE bookings SET status = ? WHERE booking_id = ?
    return true;
}

function sendCancellationConfirmationEmail($bookingInfo, $cancellationDetails) {
    // Simula envio de email - substitua pela sua implementação
    // Aqui você enviaria um email de confirmação do cancelamento
    return true;
}

function saveCancellationSurvey($cancellationId, $reason, $feedback) {
    // Simula salvamento da pesquisa - substitua pela sua implementação
    // INSERT INTO cancellation_surveys (cancellation_id, reason, feedback) VALUES (...)
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
