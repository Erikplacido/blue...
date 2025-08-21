<?php
/**
 * Stripe Webhook Enterprise - Blue Cleaning Services
 * Processa assinaturas, pausas, cobranças e referrals
 */

require_once __DIR__ . '/../config/stripe-enterprise.php';
require_once __DIR__ . '/../config/australian-database.php';
require_once __DIR__ . '/../classes/StripeSubscriptionManager.php';

// Log inicial
error_log("=== STRIPE WEBHOOK ENTERPRISE INICIADO === " . date('Y-m-d H:i:s'));

// Capturar payload e signature
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

error_log("Webhook payload size: " . strlen($payload) . " bytes");

if (!$payload) {
    error_log("ERRO: Payload vazio");
    http_response_code(400);
    exit('No payload');
}

if (!$sig_header) {
    error_log("ERRO: Signature ausente");
    http_response_code(400);
    exit('No signature');
}

try {
    // Verificar assinatura do webhook
    $webhookSecret = STRIPE_WEBHOOK_SECRET;
    
    if ($webhookSecret === 'whsec_australian_webhook_here') {
        error_log("AVISO: Webhook secret ainda não configurado no .env");
        // Em desenvolvimento, pular verificação
        $event = json_decode($payload, true);
    } else {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhookSecret);
    }
    
    error_log("Webhook event type: " . $event['type']);
    error_log("Event ID: " . $event['id']);
    
} catch(\UnexpectedValueException $e) {
    error_log("Invalid payload: " . $e->getMessage());
    http_response_code(400);
    exit('Invalid payload');
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    error_log("Invalid signature: " . $e->getMessage());
    http_response_code(400);
    exit('Invalid signature');
}

// Database connection
$db = AustralianDatabase::getInstance();
$connection = $db->getConnection();

// Processar eventos baseado no tipo
switch ($event['type']) {
    // === CHECKOUT EVENTS ===
    case 'checkout.session.completed':
        handleCheckoutCompleted($event['data']['object'], $connection);
        break;
        
    // === SUBSCRIPTION EVENTS ===
    case 'customer.subscription.created':
        handleSubscriptionCreated($event['data']['object'], $connection);
        break;
        
    case 'customer.subscription.updated':
        handleSubscriptionUpdated($event['data']['object'], $connection);
        break;
        
    case 'customer.subscription.deleted':
        handleSubscriptionDeleted($event['data']['object'], $connection);
        break;
        
    // === PAYMENT EVENTS ===
    case 'invoice.payment_succeeded':
        handlePaymentSucceeded($event['data']['object'], $connection);
        break;
        
    case 'invoice.payment_failed':
        handlePaymentFailed($event['data']['object'], $connection);
        break;
        
    // === CUSTOMER EVENTS ===
    case 'customer.subscription.paused':
        handleSubscriptionPaused($event['data']['object'], $connection);
        break;
        
    case 'customer.subscription.resumed':
        handleSubscriptionResumed($event['data']['object'], $connection);
        break;
        
    default:
        error_log("Evento não processado: " . $event['type']);
}

http_response_code(200);
exit('OK');

// ============================================================================
// FUNÇÕES DE PROCESSAMENTO
// ============================================================================

/**
 * Processar checkout completado
 */
function handleCheckoutCompleted($session, $connection) {
    error_log("Processando checkout completado: " . $session['id']);
    
    try {
        $bookingId = $session['metadata']['booking_id'] ?? null;
        $referralCode = $session['metadata']['referral_code'] ?? null;
        
        if (!$bookingId) {
            error_log("ERRO: booking_id não encontrado nos metadata");
            return;
        }
        
        // Atualizar booking
        $stmt = $connection->prepare("
            UPDATE bookings 
            SET status = 'confirmed',
                stripe_session_id = ?,
                stripe_customer_id = ?,
                payment_status = 'paid',
                confirmed_at = NOW(),
                updated_at = NOW()
            WHERE id = ? OR booking_code = ?
        ");
        
        $result = $stmt->execute([
            $session['id'],
            $session['customer'],
            $bookingId,
            $session['metadata']['booking_code'] ?? $bookingId
        ]);
        
        if ($result) {
            error_log("Booking $bookingId atualizado com sucesso");
            
            // Se for assinatura, criar registro
            if ($session['mode'] === 'subscription' && !empty($session['subscription'])) {
                createSubscriptionRecord($bookingId, $session, $connection);
            }
            
            // Processar referral se existir
            if ($referralCode) {
                processReferralCommission($bookingId, $referralCode, $session, $connection);
            }
            
        } else {
            error_log("ERRO: Falha ao atualizar booking $bookingId");
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar checkout: " . $e->getMessage());
    }
}

/**
 * Processar assinatura criada
 */
function handleSubscriptionCreated($subscription, $connection) {
    error_log("Subscription criada: " . $subscription['id']);
    
    try {
        // Buscar booking pelos metadata
        $bookingId = $subscription['metadata']['booking_id'] ?? null;
        
        if ($bookingId) {
            // Criar registro de assinatura
            $stmt = $connection->prepare("
                INSERT INTO booking_subscriptions 
                (booking_id, stripe_subscription_id, stripe_customer_id, status, 
                 recurrence_type, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                stripe_subscription_id = VALUES(stripe_subscription_id),
                status = VALUES(status),
                updated_at = NOW()
            ");
            
            $result = $stmt->execute([
                $bookingId,
                $subscription['id'],
                $subscription['customer'],
                $subscription['status'],
                $subscription['metadata']['recurrence_type'] ?? 'weekly'
            ]);
            
            if ($result) {
                error_log("Registro de subscription criado para booking $bookingId");
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar subscription criada: " . $e->getMessage());
    }
}

/**
 * Processar pagamento bem-sucedido (cobrança recorrente)
 */
function handlePaymentSucceeded($invoice, $connection) {
    error_log("Pagamento bem-sucedido: " . $invoice['id']);
    
    try {
        $subscriptionId = $invoice['subscription'] ?? null;
        
        if ($subscriptionId) {
            // Buscar dados da assinatura
            $stmt = $connection->prepare("
                SELECT bs.booking_id, b.referral_code, b.customer_name, b.customer_email, b.total_amount
                FROM booking_subscriptions bs
                JOIN bookings b ON bs.booking_id = b.id
                WHERE bs.stripe_subscription_id = ?
            ");
            $stmt->execute([$subscriptionId]);
            $subData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subData && !empty($subData['referral_code'])) {
                // Processar comissão de referral para esta cobrança recorrente
                processRecurringReferralCommission($subData, $invoice, $connection);
            }
            
            // Registrar pagamento recorrente
            recordRecurringPayment($subscriptionId, $invoice, $connection);
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar pagamento recorrente: " . $e->getMessage());
    }
}

/**
 * Processar referral commission
 */
function processReferralCommission($bookingId, $referralCode, $session, $connection) {
    try {
        // Buscar dados do referenciador
        $stmt = $connection->prepare("
            SELECT ru.id, ru.name, rl.commission_percentage, rl.commission_type
            FROM referral_users ru
            LEFT JOIN referral_levels rl ON ru.current_level_id = rl.id
            WHERE ru.referral_code = ? AND ru.is_active = 1
        ");
        $stmt->execute([$referralCode]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$referrer) {
            error_log("Referenciador não encontrado: $referralCode");
            return;
        }
        
        // Buscar dados do booking
        $stmt = $connection->prepare("SELECT * FROM bookings WHERE id = ? OR booking_code = ?");
        $stmt->execute([$bookingId, $session['metadata']['booking_code'] ?? $bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            error_log("Booking não encontrado: $bookingId");
            return;
        }
        
        // Calcular comissão
        $bookingValue = $booking['total_amount'];
        $commission = ($bookingValue * $referrer['commission_percentage']) / 100;
        
        // Criar referral
        $stmt = $connection->prepare("
            INSERT INTO referrals 
            (referrer_id, booking_id, customer_name, customer_email,
             booking_value, commission_earned, status, payment_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'paid', 'initial', NOW())
        ");
        
        $result = $stmt->execute([
            $referrer['id'],
            $booking['booking_code'],
            $booking['customer_name'],
            $booking['customer_email'],
            $bookingValue,
            $commission
        ]);
        
        if ($result) {
            // Atualizar totais do referenciador
            $stmt = $connection->prepare("
                UPDATE referral_users 
                SET total_earned = total_earned + ?, 
                    total_referrals = total_referrals + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$commission, $referrer['id']]);
            
            error_log("Referral processado: $commission para {$referrer['name']}");
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar referral: " . $e->getMessage());
    }
}

/**
 * Processar comissão recorrente
 */
function processRecurringReferralCommission($subData, $invoice, $connection) {
    try {
        // Buscar dados do referenciador
        $stmt = $connection->prepare("
            SELECT ru.id, ru.name, rl.commission_percentage
            FROM referral_users ru
            LEFT JOIN referral_levels rl ON ru.current_level_id = rl.id
            WHERE ru.referral_code = ? AND ru.is_active = 1
        ");
        $stmt->execute([$subData['referral_code']]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($referrer) {
            $paidAmount = fromStripeAmount($invoice['amount_paid']);
            $commission = ($paidAmount * $referrer['commission_percentage']) / 100;
            
            // Criar referral recorrente
            $stmt = $connection->prepare("
                INSERT INTO referrals 
                (referrer_id, booking_id, customer_name, customer_email,
                 booking_value, commission_earned, status, payment_type, 
                 stripe_invoice_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'paid', 'recurring', ?, NOW())
            ");
            
            $result = $stmt->execute([
                $referrer['id'],
                $subData['booking_id'],
                $subData['customer_name'],
                $subData['customer_email'],
                $paidAmount,
                $commission,
                $invoice['id']
            ]);
            
            if ($result) {
                // Atualizar total ganho
                $stmt = $connection->prepare("
                    UPDATE referral_users 
                    SET total_earned = total_earned + ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$commission, $referrer['id']]);
                
                error_log("Comissão recorrente: $commission para {$referrer['name']}");
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar comissão recorrente: " . $e->getMessage());
    }
}

/**
 * Helper functions
 */
function createSubscriptionRecord($bookingId, $session, $connection) {
    // Implementar criação de registro de assinatura
    error_log("Criando registro de subscription para booking $bookingId");
}

function recordRecurringPayment($subscriptionId, $invoice, $connection) {
    // Implementar registro de pagamento recorrente
    error_log("Registrando pagamento recorrente para subscription $subscriptionId");
}

function handleSubscriptionUpdated($subscription, $connection) {
    error_log("Subscription atualizada: " . $subscription['id']);
}

function handleSubscriptionDeleted($subscription, $connection) {
    error_log("Subscription deletada: " . $subscription['id']);
}

function handlePaymentFailed($invoice, $connection) {
    error_log("Pagamento falhou: " . $invoice['id']);
}

function handleSubscriptionPaused($subscription, $connection) {
    error_log("Subscription pausada: " . $subscription['id']);
}

function handleSubscriptionResumed($subscription, $connection) {
    error_log("Subscription resumida: " . $subscription['id']);
}
?>
