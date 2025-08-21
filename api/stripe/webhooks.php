<?php
/**
 * Webhooks do Stripe - Blue Project V2
 * Processamento automático de eventos do Stripe com sistema completo
 */

header('Content-Type: application/json');

// Inclui configurações
require_once '../../config/stripe-config.php';
require_once '../../config/email-system.php';

// Log de entrada
error_log("Stripe Webhook: Received request - " . $_SERVER['REQUEST_METHOD']);

try {
    // Inicializar Stripe
    StripeConfig::initialize();
    
    // Obtém payload
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    if (empty($payload)) {
        throw new Exception('Empty payload');
    }

    // Verifica assinatura do webhook
    if (StripeConfig::isProduction()) {
        // Em produção, verificar assinatura real
        $event = StripeUtils::validateWebhookSignature(
            $payload, 
            $sig_header, 
            StripeConfig::getWebhookSecret()
        );
    } else {
        // Em desenvolvimento, simular verificação
        $event = json_decode($payload, true);
        if (!$event) {
            throw new Exception('Invalid JSON payload');
        }
    }

    error_log("Stripe Webhook: Event type - " . ($event['type'] ?? 'unknown'));

    // Processa evento baseado no tipo
    switch ($event['type']) {
        case 'invoice.payment_succeeded':
            handlePaymentSucceeded($event['data']['object']);
            break;
            
        case 'invoice.payment_failed':
            handlePaymentFailed($event['data']['object']);
            break;
            
        case 'customer.subscription.created':
            handleSubscriptionCreated($event['data']['object']);
            break;
            
        case 'customer.subscription.updated':
            handleSubscriptionUpdated($event['data']['object']);
            break;
            
        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($event['data']['object']);
            break;
            
        case 'customer.subscription.paused':
            handleSubscriptionPaused($event['data']['object']);
            break;
            
        case 'customer.subscription.resumed':
            handleSubscriptionResumed($event['data']['object']);
            break;
            
        case 'charge.dispute.created':
            handleChargeDispute($event['data']['object']);
            break;
            
        case 'payment_intent.succeeded':
            handlePaymentIntentSucceeded($event['data']['object']);
            break;
            
        case 'payment_intent.payment_failed':
            handlePaymentIntentFailed($event['data']['object']);
            break;
            
        default:
            error_log("Stripe Webhook: Unhandled event type - " . $event['type']);
    }

    // Resposta de sucesso
    http_response_code(200);
    echo json_encode(['status' => 'success', 'processed_at' => date('c')]);

} catch (Exception $e) {
    error_log("Stripe Webhook Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage(), 'timestamp' => date('c')]);
}

/**
 * Funções de processamento de eventos
 */

function handlePaymentSucceeded($invoice) {
    try {
        $subscription_id = $invoice['subscription'] ?? null;
        $amount = StripeUtils::fromCents($invoice['amount_paid']);
        $customer_id = $invoice['customer'] ?? null;
        
        if (!$subscription_id) {
            throw new Exception('No subscription ID in invoice');
        }

        // Carrega booking baseado no subscription_id
        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for subscription: ' . $subscription_id);
        }

        // Atualiza status do pagamento
        updateBookingPaymentStatus($booking['booking_id'], 'paid', [
            'stripe_invoice_id' => $invoice['id'],
            'amount' => $amount,
            'payment_date' => date('Y-m-d H:i:s', $invoice['created'])
        ]);

        // Avança status do booking se necessário
        if ($booking['status'] === 'pending') {
            updateBookingStatus($booking['booking_id'], 'active');
        }

        // Cria/atualiza comissão de referral se aplicável
        if (!empty($booking['referral_code'])) {
            processReferralCommission($booking, $amount);
        }

        // Envia confirmação de pagamento
        EmailSystem::sendPaymentConfirmation($booking, [
            'amount' => $amount,
            'payment_date' => date('Y-m-d', $invoice['created'])
        ]);

        // Agenda próximo serviço se recorrente
        if ($booking['recurrence_pattern'] !== 'one-time') {
            scheduleNextService($booking);
        }

        error_log("Payment succeeded processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing payment_succeeded: " . $e->getMessage());
        throw $e;
    }
}

function handlePaymentFailed($invoice) {
    try {
        $subscription_id = $invoice['subscription'] ?? null;
        $attempt_count = $invoice['attempt_count'] ?? 1;
        $amount = StripeUtils::fromCents($invoice['amount_due']);
        
        if (!$subscription_id) {
            throw new Exception('No subscription ID in failed invoice');
        }

        // Carrega booking
        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for subscription: ' . $subscription_id);
        }

        // Registra falha de pagamento
        logPaymentFailure($booking['booking_id'], [
            'stripe_invoice_id' => $invoice['id'],
            'attempt_count' => $attempt_count,
            'failure_reason' => $invoice['charge']['failure_message'] ?? 'Payment declined',
            'failed_at' => date('Y-m-d H:i:s', $invoice['created']),
            'amount' => $amount
        ]);

        // Envia email de falha de pagamento
        EmailSystem::sendPaymentFailed($booking, $attempt_count);

        // Política de retry baseada na tentativa
        $maxAttempts = StripeConfig::getConfig('retry_config')['max_attempts'] ?? 3;
        
        if ($attempt_count >= $maxAttempts) {
            // Após tentativas máximas, suspende serviço
            updateBookingStatus($booking['booking_id'], 'payment_failed');
            
            // Cancela próximo serviço agendado
            cancelUpcomingService($booking['booking_id']);
            
            // Log para acompanhamento manual
            error_log("URGENT: Booking {$booking['booking_id']} suspended after {$maxAttempts} failed payment attempts");
            
        } else {
            // Mantém ativo durante tentativas de retry
            error_log("Payment retry {$attempt_count}/{$maxAttempts} for booking: " . $booking['booking_id']);
        }

        error_log("Payment failed processed for booking: " . $booking['booking_id'] . " (attempt $attempt_count)");

    } catch (Exception $e) {
        error_log("Error processing payment_failed: " . $e->getMessage());
        throw $e;
    }
}

function handlePaymentIntentSucceeded($payment_intent) {
    try {
        $amount = StripeUtils::fromCents($payment_intent['amount']);
        $metadata = $payment_intent['metadata'] ?? [];
        
        // Para pagamentos one-time
        if (isset($metadata['booking_id'])) {
            $booking = loadBookingById($metadata['booking_id']);
            if ($booking) {
                updateBookingPaymentStatus($booking['booking_id'], 'paid', [
                    'stripe_payment_intent_id' => $payment_intent['id'],
                    'amount' => $amount,
                    'payment_date' => date('Y-m-d H:i:s', $payment_intent['created'])
                ]);
                
                // Ativar booking one-time
                updateBookingStatus($booking['booking_id'], 'confirmed');
                
                // Enviar confirmação
                EmailSystem::sendPaymentConfirmation($booking, [
                    'amount' => $amount,
                    'payment_date' => date('Y-m-d', $payment_intent['created'])
                ]);
                
                error_log("One-time payment succeeded for booking: " . $booking['booking_id']);
            }
        }

    } catch (Exception $e) {
        error_log("Error processing payment_intent_succeeded: " . $e->getMessage());
        throw $e;
    }
}

function handlePaymentIntentFailed($payment_intent) {
    try {
        $metadata = $payment_intent['metadata'] ?? [];
        $failure_reason = $payment_intent['last_payment_error']['message'] ?? 'Payment failed';
        
        // Para pagamentos one-time que falharam
        if (isset($metadata['booking_id'])) {
            $booking = loadBookingById($metadata['booking_id']);
            if ($booking) {
                updateBookingStatus($booking['booking_id'], 'payment_failed');
                
                logPaymentFailure($booking['booking_id'], [
                    'stripe_payment_intent_id' => $payment_intent['id'],
                    'failure_reason' => $failure_reason,
                    'failed_at' => date('Y-m-d H:i:s', $payment_intent['created'])
                ]);
                
                error_log("One-time payment failed for booking: " . $booking['booking_id']);
            }
        }

    } catch (Exception $e) {
        error_log("Error processing payment_intent_failed: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionCreated($subscription) {
    try {
        $subscription_id = $subscription['id'];
        $customer_id = $subscription['customer'];
        $metadata = $subscription['metadata'] ?? [];

        // Carrega booking pelo metadata ou customer
        $booking_id = $metadata['booking_id'] ?? null;
        if (!$booking_id) {
            // Tenta encontrar pelo customer
            $booking = loadBookingByCustomer($customer_id);
        } else {
            $booking = loadBookingById($booking_id);
        }

        if (!$booking) {
            throw new Exception('Booking not found for new subscription');
        }

        // Atualiza booking com subscription_id
        updateBookingStripeSubscription($booking['booking_id'], $subscription_id);

        // Atualiza metadata se necessário
        if (empty($metadata['booking_id'])) {
            updateStripeSubscriptionMetadata($subscription_id, StripeConfig::getDefaultMetadata($booking));
        }

        error_log("Subscription created processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing subscription_created: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionUpdated($subscription) {
    try {
        $subscription_id = $subscription['id'];
        $status = $subscription['status'];
        $metadata = $subscription['metadata'] ?? [];

        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for updated subscription: ' . $subscription_id);
        }

        // Mapeia status do Stripe para status interno
        $internal_status = mapStripeStatusToInternal($status);
        
        // Atualiza status do booking se mudou
        if ($booking['status'] !== $internal_status) {
            updateBookingStatus($booking['booking_id'], $internal_status);
            
            // Notifica cliente sobre mudança de status se necessário
            if (in_array($internal_status, ['active', 'cancelled', 'paused'])) {
                // EmailSystem pode enviar notificação de mudança de status
                error_log("Status change notification needed for booking: " . $booking['booking_id'] . " -> $internal_status");
            }
        }

        // Processa pausas automáticas se indicado no metadata
        if (isset($metadata['auto_pause_start']) && $status === 'paused') {
            processPauseFromMetadata($booking, $metadata);
        }

        error_log("Subscription updated processed for booking: " . $booking['booking_id'] . " (status: $status)");

    } catch (Exception $e) {
        error_log("Error processing subscription_updated: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionDeleted($subscription) {
    try {
        $subscription_id = $subscription['id'];
        $metadata = $subscription['metadata'] ?? [];

        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for deleted subscription: ' . $subscription_id);
        }

        // Atualiza status para cancelado
        updateBookingStatus($booking['booking_id'], 'cancelled');

        // Processa cancelamento e penalidades
        processCancellationFromWebhook($booking, $metadata);

        // Cancela serviços futuros
        cancelFutureServices($booking['booking_id']);

        // Envia confirmação de cancelamento
        EmailSystem::sendCancellationConfirmation($booking, [
            'penalty_amount' => $metadata['penalty_amount'] ?? 0,
            'refund_amount' => $metadata['refund_amount'] ?? 0,
            'reason' => $metadata['cancellation_reason'] ?? 'Customer request'
        ]);

        error_log("Subscription deleted processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing subscription_deleted: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionPaused($subscription) {
    try {
        $subscription_id = $subscription['id'];
        $metadata = $subscription['metadata'] ?? [];

        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for paused subscription: ' . $subscription_id);
        }

        // Atualiza status para pausado
        updateBookingStatus($booking['booking_id'], 'paused');

        // Registra pausa no histórico
        recordPauseAction($booking['booking_id'], [
            'paused_via' => 'stripe_webhook',
            'metadata' => $metadata,
            'paused_at' => date('Y-m-d H:i:s')
        ]);

        // Pausa serviços agendados
        pauseScheduledServices($booking['booking_id']);

        // Envia confirmação se não foi enviada pela API
        if (!isset($metadata['pause_email_sent'])) {
            EmailSystem::sendPauseConfirmation($booking, [
                'start_date' => $metadata['pause_start'] ?? date('Y-m-d'),
                'end_date' => $metadata['pause_end'] ?? date('Y-m-d', strtotime('+30 days')),
                'duration' => $metadata['pause_duration'] ?? 30,
                'fee' => $metadata['pause_fee'] ?? 0,
                'is_free' => ($metadata['pause_fee'] ?? 0) == 0
            ]);
        }

        error_log("Subscription paused processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing subscription_paused: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionResumed($subscription) {
    try {
        $subscription_id = $subscription['id'];

        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for resumed subscription: ' . $subscription_id);
        }

        // Atualiza status para ativo
        updateBookingStatus($booking['booking_id'], 'active');

        // Reagenda próximos serviços
        resumeScheduledServices($booking['booking_id']);

        // Envia confirmação de retomada
        $resumeTemplate = "
            <h2>🎉 Service Resumed!</h2>
            <p>Great news! Your cleaning service has been resumed and is now active again.</p>
            <p>Your next service will be scheduled according to your regular pattern.</p>
        ";
        
        // Seria ideal ter um template específico para resume, mas por enquanto usando estrutura básica
        error_log("Service resumed confirmation needed for booking: " . $booking['booking_id']);

        error_log("Subscription resumed processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing subscription_resumed: " . $e->getMessage());
        throw $e;
    }
}

function handleChargeDispute($dispute) {
    try {
        $charge_id = $dispute['charge'];
        $amount = StripeUtils::fromCents($dispute['amount']);
        $reason = $dispute['reason'];
        $dispute_id = $dispute['id'];

        // Carrega informações do charge para encontrar booking
        $booking = loadBookingByChargeId($charge_id);
        if (!$booking) {
            error_log("No booking found for disputed charge: " . $charge_id);
            return;
        }

        // Registra disputa
        recordChargeback($booking['booking_id'], [
            'stripe_dispute_id' => $dispute_id,
            'charge_id' => $charge_id,
            'amount' => $amount,
            'reason' => $reason,
            'disputed_at' => date('Y-m-d H:i:s', $dispute['created'])
        ]);

        // Notifica admin sobre disputa
        EmailSystem::sendChargebackNotification($booking, [
            'amount' => $amount,
            'reason' => $reason,
            'disputed_at' => date('Y-m-d H:i:s', $dispute['created'])
        ]);

        // Suspende serviços futuros até resolução da disputa
        updateBookingStatus($booking['booking_id'], 'disputed');

        error_log("Charge dispute processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing charge_dispute: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Funções auxiliares - Implementar com banco de dados real
 */

function loadBookingByStripeSubscription($subscription_id) {
    // Simula busca no banco - implementar busca real
    return [
        'booking_id' => 'book_' . substr($subscription_id, -8),
        'customer_email' => 'customer@example.com',
        'customer_name' => 'John Doe',
        'status' => 'active',
        'service_type' => 'house-cleaning',
        'recurrence_pattern' => 'weekly',
        'final_amount' => 150.00,
        'referral_code' => 'ERIK2025',
        'first_service_date' => date('Y-m-d', strtotime('+3 days'))
    ];
}

function loadBookingById($booking_id) {
    // Simula busca no banco - implementar busca real
    return [
        'booking_id' => $booking_id,
        'customer_email' => 'customer@example.com',
        'customer_name' => 'John Doe', 
        'status' => 'pending',
        'service_type' => 'house-cleaning',
        'recurrence_pattern' => 'one-time',
        'final_amount' => 180.00
    ];
}

function loadBookingByCustomer($customer_id) {
    // Implementar busca real
    return null;
}

function loadBookingByChargeId($charge_id) {
    // Implementar busca real  
    return [
        'booking_id' => 'book_001',
        'customer_email' => 'customer@example.com',
        'customer_name' => 'John Doe',
        'status' => 'active'
    ];
}

function updateBookingPaymentStatus($booking_id, $status, $payment_data) {
    // Implementar atualização no banco
    error_log("Updated payment status for $booking_id to $status - Amount: " . ($payment_data['amount'] ?? 'N/A'));
}

function updateBookingStatus($booking_id, $status) {
    // Implementar atualização no banco
    error_log("Updated booking $booking_id status to $status");
}

function updateBookingStripeSubscription($booking_id, $subscription_id) {
    // Implementar atualização no banco
    error_log("Updated booking $booking_id with subscription $subscription_id");
}

function updateStripeSubscriptionMetadata($subscription_id, $metadata) {
    try {
        StripeConfig::initialize();
        \Stripe\Subscription::update($subscription_id, ['metadata' => $metadata]);
        error_log("Updated Stripe subscription metadata: $subscription_id");
    } catch (Exception $e) {
        error_log("Failed to update Stripe subscription metadata: " . $e->getMessage());
    }
}

function processReferralCommission($booking, $amount) {
    // Implementar lógica de comissão
    error_log("Processing referral commission for booking: " . $booking['booking_id'] . " - Commission amount: " . ($amount * 0.1));
}

function logPaymentFailure($booking_id, $data) {
    // Implementar log no banco
    error_log("Payment failure logged for $booking_id: " . json_encode($data));
}

function scheduleNextService($booking) {
    // Implementar agendamento
    error_log("Scheduling next service for booking: " . $booking['booking_id']);
}

function cancelUpcomingService($booking_id) {
    // Implementar cancelamento
    error_log("Cancelled upcoming service for booking: $booking_id");
}

function cancelFutureServices($booking_id) {
    // Implementar cancelamento de serviços futuros
    error_log("Cancelled all future services for booking: $booking_id");
}

function recordPauseAction($booking_id, $data) {
    // Implementar registro no banco
    error_log("Pause action recorded for $booking_id: " . json_encode($data));
}

function pauseScheduledServices($booking_id) {
    // Implementar pausa de serviços
    error_log("Paused scheduled services for booking: $booking_id");
}

function resumeScheduledServices($booking_id) {
    // Implementar retomada de serviços
    error_log("Resumed scheduled services for booking: $booking_id");
}

function recordChargeback($booking_id, $data) {
    // Implementar registro de chargeback
    error_log("Chargeback recorded for $booking_id: " . json_encode($data));
}

function processCancellationFromWebhook($booking, $metadata) {
    // Implementar lógica de cancelamento
    error_log("Processing cancellation from webhook for booking: " . $booking['booking_id']);
}

function processPauseFromMetadata($booking, $metadata) {
    // Implementar pausa automática baseada em metadata
    error_log("Processing automatic pause for booking: " . $booking['booking_id']);
}

function mapStripeStatusToInternal($stripe_status) {
    $mapping = [
        'active' => 'active',
        'paused' => 'paused', 
        'canceled' => 'cancelled',
        'incomplete' => 'pending',
        'incomplete_expired' => 'expired',
        'trialing' => 'trial',
        'past_due' => 'payment_failed',
        'unpaid' => 'payment_failed'
    ];
    
    return $mapping[$stripe_status] ?? 'unknown';
}

?>

    // Processa evento baseado no tipo
    switch ($event['type']) {
        case 'invoice.payment_succeeded':
            handlePaymentSucceeded($event['data']['object']);
            break;
            
        case 'invoice.payment_failed':
            handlePaymentFailed($event['data']['object']);
            break;
            
        case 'customer.subscription.created':
            handleSubscriptionCreated($event['data']['object']);
            break;
            
        case 'customer.subscription.updated':
            handleSubscriptionUpdated($event['data']['object']);
            break;
            
        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($event['data']['object']);
            break;
            
        case 'customer.subscription.paused':
            handleSubscriptionPaused($event['data']['object']);
            break;
            
        case 'customer.subscription.resumed':
            handleSubscriptionResumed($event['data']['object']);
            break;
            
        case 'charge.dispute.created':
            handleChargeDispute($event['data']['object']);
            break;
            
        default:
            error_log("Stripe Webhook: Unhandled event type - " . $event['type']);
    }

    // Resposta de sucesso
    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    error_log("Stripe Webhook Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Funções de processamento de eventos
 */

function handlePaymentSucceeded($invoice) {
    try {
        $subscription_id = $invoice['subscription'] ?? null;
        $amount = $invoice['amount_paid'] / 100; // Stripe usa centavos
        $customer_id = $invoice['customer'] ?? null;
        
        if (!$subscription_id) {
            throw new Exception('No subscription ID in invoice');
        }

        // Carrega booking baseado no subscription_id
        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for subscription: ' . $subscription_id);
        }

        // Atualiza status do pagamento
        updateBookingPaymentStatus($booking['booking_id'], 'paid', [
            'stripe_invoice_id' => $invoice['id'],
            'amount' => $amount,
            'payment_date' => date('Y-m-d H:i:s', $invoice['created'])
        ]);

        // Avança status do booking se necessário
        if ($booking['status'] === 'pending') {
            updateBookingStatus($booking['booking_id'], 'paid');
        }

        // Cria/atualiza comissão de referral se aplicável
        if (!empty($booking['referral_code'])) {
            processReferralCommission($booking, $amount);
        }

        // Envia notificação para o cliente
        sendPaymentConfirmationEmail($booking, $amount);

        // Agenda próximo serviço se recorrente
        if ($booking['recurrence_pattern'] !== 'one-time') {
            scheduleNextService($booking);
        }

        error_log("Payment succeeded processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing payment_succeeded: " . $e->getMessage());
        throw $e;
    }
}

function handlePaymentFailed($invoice) {
    try {
        $subscription_id = $invoice['subscription'] ?? null;
        $attempt_count = $invoice['attempt_count'] ?? 1;
        
        if (!$subscription_id) {
            throw new Exception('No subscription ID in failed invoice');
        }

        // Carrega booking
        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for subscription: ' . $subscription_id);
        }

        // Registra falha de pagamento
        logPaymentFailure($booking['booking_id'], [
            'stripe_invoice_id' => $invoice['id'],
            'attempt_count' => $attempt_count,
            'failure_reason' => $invoice['charge']['failure_message'] ?? 'Unknown',
            'failed_at' => date('Y-m-d H:i:s', $invoice['created'])
        ]);

        // Política de retry baseada na tentativa
        if ($attempt_count >= 3) {
            // Após 3 tentativas, suspende serviço
            updateBookingStatus($booking['booking_id'], 'payment_failed');
            sendPaymentFailedEmail($booking, 'suspended');
            
            // Cancela próximo serviço agendado
            cancelUpcomingService($booking['booking_id']);
            
        } else {
            // Notifica sobre falha mas mantém ativo
            sendPaymentFailedEmail($booking, 'retry');
        }

        error_log("Payment failed processed for booking: " . $booking['booking_id'] . " (attempt $attempt_count)");

    } catch (Exception $e) {
        error_log("Error processing payment_failed: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionCreated($subscription) {
    try {
        $subscription_id = $subscription['id'];
        $customer_id = $subscription['customer'];
        $metadata = $subscription['metadata'] ?? [];

        // Carrega booking pelo metadata ou customer
        $booking_id = $metadata['booking_id'] ?? null;
        if (!$booking_id) {
            // Tenta encontrar pelo customer
            $booking = loadBookingByCustomer($customer_id);
        } else {
            $booking = loadBookingById($booking_id);
        }

        if (!$booking) {
            throw new Exception('Booking not found for new subscription');
        }

        // Atualiza booking com subscription_id
        updateBookingStripeSubscription($booking['booking_id'], $subscription_id);

        // Atualiza metadata se necessário
        if (empty($metadata['booking_id'])) {
            updateStripeSubscriptionMetadata($subscription_id, [
                'booking_id' => $booking['booking_id'],
                'customer_email' => $booking['customer_email']
            ]);
        }

        error_log("Subscription created processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing subscription_created: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionUpdated($subscription) {
    try {
        $subscription_id = $subscription['id'];
        $status = $subscription['status'];
        $metadata = $subscription['metadata'] ?? [];

        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for updated subscription: ' . $subscription_id);
        }

        // Mapeia status do Stripe para status interno
        $internal_status = mapStripeStatusToInternal($status);
        
        // Atualiza status do booking se mudou
        if ($booking['status'] !== $internal_status) {
            updateBookingStatus($booking['booking_id'], $internal_status);
            
            // Notifica cliente sobre mudança de status
            sendStatusChangeEmail($booking, $internal_status);
        }

        // Processa pausas automáticas se indicado no metadata
        if (isset($metadata['auto_pause_start']) && $status === 'paused') {
            processPauseFromMetadata($booking, $metadata);
        }

        error_log("Subscription updated processed for booking: " . $booking['booking_id'] . " (status: $status)");

    } catch (Exception $e) {
        error_log("Error processing subscription_updated: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionDeleted($subscription) {
    try {
        $subscription_id = $subscription['id'];
        $metadata = $subscription['metadata'] ?? [];

        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for deleted subscription: ' . $subscription_id);
        }

        // Atualiza status para cancelado
        updateBookingStatus($booking['booking_id'], 'cancelled');

        // Processa cancelamento e penalidades
        processCancellationFromWebhook($booking, $metadata);

        // Cancela serviços futuros
        cancelFutureServices($booking['booking_id']);

        // Notifica cliente
        sendCancellationConfirmationEmail($booking);

        error_log("Subscription deleted processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing subscription_deleted: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionPaused($subscription) {
    try {
        $subscription_id = $subscription['id'];
        $metadata = $subscription['metadata'] ?? [];

        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for paused subscription: ' . $subscription_id);
        }

        // Atualiza status para pausado
        updateBookingStatus($booking['booking_id'], 'paused');

        // Registra pausa no histórico
        recordPauseAction($booking['booking_id'], [
            'paused_via' => 'stripe_webhook',
            'metadata' => $metadata,
            'paused_at' => date('Y-m-d H:i:s')
        ]);

        // Pausa serviços agendados
        pauseScheduledServices($booking['booking_id']);

        // Notifica cliente
        sendPauseConfirmationEmail($booking);

        error_log("Subscription paused processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing subscription_paused: " . $e->getMessage());
        throw $e;
    }
}

function handleSubscriptionResumed($subscription) {
    try {
        $subscription_id = $subscription['id'];

        $booking = loadBookingByStripeSubscription($subscription_id);
        if (!$booking) {
            throw new Exception('Booking not found for resumed subscription: ' . $subscription_id);
        }

        // Atualiza status para ativo
        updateBookingStatus($booking['booking_id'], 'active');

        // Reagenda próximos serviços
        resumeScheduledServices($booking['booking_id']);

        // Notifica cliente
        sendResumeConfirmationEmail($booking);

        error_log("Subscription resumed processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing subscription_resumed: " . $e->getMessage());
        throw $e;
    }
}

function handleChargeDispute($dispute) {
    try {
        $charge_id = $dispute['charge'];
        $amount = $dispute['amount'] / 100;
        $reason = $dispute['reason'];

        // Carrega informações do charge para encontrar booking
        $booking = loadBookingByChargeId($charge_id);
        if (!$booking) {
            error_log("No booking found for disputed charge: " . $charge_id);
            return;
        }

        // Registra disputa
        recordChargeback($booking['booking_id'], [
            'stripe_dispute_id' => $dispute['id'],
            'charge_id' => $charge_id,
            'amount' => $amount,
            'reason' => $reason,
            'disputed_at' => date('Y-m-d H:i:s', $dispute['created'])
        ]);

        // Notifica admin sobre disputa
        sendChargebackAlert($booking, $dispute);

        error_log("Charge dispute processed for booking: " . $booking['booking_id']);

    } catch (Exception $e) {
        error_log("Error processing charge_dispute: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Funções auxiliares (implementar com banco de dados real)
 */

function loadBookingByStripeSubscription($subscription_id) {
    // Simula busca no banco - implementar busca real
    return [
        'booking_id' => 'book_001',
        'customer_email' => 'customer@example.com',
        'status' => 'active',
        'referral_code' => 'ERIK2025',
        'recurrence_pattern' => 'weekly'
    ];
}

function updateBookingPaymentStatus($booking_id, $status, $payment_data) {
    // Implementar atualização no banco
    error_log("Updated payment status for $booking_id to $status");
}

function updateBookingStatus($booking_id, $status) {
    // Implementar atualização no banco
    error_log("Updated booking $booking_id status to $status");
}

function processReferralCommission($booking, $amount) {
    // Implementar lógica de comissão
    error_log("Processing referral commission for booking: " . $booking['booking_id']);
}

function sendPaymentConfirmationEmail($booking, $amount) {
    // Implementar envio de email
    error_log("Sending payment confirmation email for booking: " . $booking['booking_id']);
}

function mapStripeStatusToInternal($stripe_status) {
    $mapping = [
        'active' => 'active',
        'paused' => 'paused', 
        'canceled' => 'cancelled',
        'incomplete' => 'pending',
        'incomplete_expired' => 'expired',
        'trialing' => 'trial',
        'past_due' => 'payment_failed'
    ];
    
    return $mapping[$stripe_status] ?? 'unknown';
}

// Outras funções auxiliares...
function scheduleNextService($booking) { /* implementar */ }
function logPaymentFailure($booking_id, $data) { /* implementar */ }
function sendPaymentFailedEmail($booking, $type) { /* implementar */ }
function cancelUpcomingService($booking_id) { /* implementar */ }
function loadBookingByCustomer($customer_id) { /* implementar */ }
function loadBookingById($booking_id) { /* implementar */ }
function updateBookingStripeSubscription($booking_id, $subscription_id) { /* implementar */ }
function updateStripeSubscriptionMetadata($subscription_id, $metadata) { /* implementar */ }
function sendStatusChangeEmail($booking, $status) { /* implementar */ }
function processPauseFromMetadata($booking, $metadata) { /* implementar */ }
function processCancellationFromWebhook($booking, $metadata) { /* implementar */ }
function cancelFutureServices($booking_id) { /* implementar */ }
function sendCancellationConfirmationEmail($booking) { /* implementar */ }
function recordPauseAction($booking_id, $data) { /* implementar */ }
function pauseScheduledServices($booking_id) { /* implementar */ }
function sendPauseConfirmationEmail($booking) { /* implementar */ }
function resumeScheduledServices($booking_id) { /* implementar */ }
function sendResumeConfirmationEmail($booking) { /* implementar */ }
function loadBookingByChargeId($charge_id) { /* implementar */ }
function recordChargeback($booking_id, $data) { /* implementar */ }
function sendChargebackAlert($booking, $dispute) { /* implementar */ }

?>
