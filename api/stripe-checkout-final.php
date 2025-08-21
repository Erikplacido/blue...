<?php
/**
 * ⚠️  DEPRECATED - ESTE ARQUIVO ESTÁ DEPRECIADO
 * =========================================================
 * 
 * SUBSTITUÍDO POR: api/stripe-checkout-unified-final.php
 * 
 * DATA DE DEPRECIAÇÃO: 2025-08-11
 * MOTIVO: Eliminação de redundâncias no sistema Stripe
 * 
 * ❌ NÃO USE MAIS ESTE ARQUIVO
 * ✅ USE: api/stripe-checkout-unified-final.php
 * 
 * Este arquivo será removido em futuras versões.
 * =========================================================
 */

// Redirecionar para o novo endpoint
header('HTTP/1.1 301 Moved Permanently');
header('Location: /api/stripe-checkout-unified-final.php');
exit('This endpoint has been deprecated. Use /api/stripe-checkout-unified-final.php instead.');

/**
 * =========================================================
 * STRIPE CHECKOUT API - VERSÃO FINAL SEM AVAILABILITY CHECK
 * =========================================================
 */

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization, X-Requested-With');

// Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    error_log("=== STRIPE CHECKOUT FINAL === " . date('Y-m-d H:i:s'));
    error_log("⚠️ NO AVAILABILITY CHECKS - Direct Stripe processing");
    
    // Capturar dados
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON payload');
    }

    error_log("Payload recebido: " . json_encode($input, JSON_UNESCAPED_UNICODE));
    
    // 🔍 DEBUG DETALHADO DO VALOR TOTAL RECEBIDO
    error_log("🔍 VALOR TOTAL RECEBIDO: " . ($input['total'] ?? 'NÃO ENVIADO'));
    error_log("🔍 TIPO DO VALOR: " . gettype($input['total'] ?? null));
    if (isset($input['total'])) {
        error_log("🔍 VALOR FLOAT: " . floatval($input['total']));
    }
    error_log("🔍 DEBUG TOTAL: " . json_encode([
        'received_total' => $input['total'] ?? 'undefined',
        'type' => gettype($input['total'] ?? null),
        'float_value' => isset($input['total']) ? floatval($input['total']) : 'N/A',
        'no_default_fallback' => 'Dynamic pricing only'
    ]));

    // Validação mínima
    if (empty($input['customer']['name']) || empty($input['customer']['email']) || empty(trim($input['address']))) {
        throw new Exception('Customer name, email and address are required');
    }
    
    // Processar dados do booking
    $bookingData = [
        'customer_name' => $input['customer']['name'],
        'customer_email' => $input['customer']['email'],
        'customer_phone' => $input['customer']['phone'] ?? '',
        'service_address' => trim($input['address']),
        'service_date' => $input['date'] ?? date('Y-m-d', strtotime('+2 days')),
        'service_time' => $input['time'] ?? '10:00',
        'service_type' => 'House Cleaning',
        'recurrence' => $input['recurrence'] ?? 'one-time',
        // Usando apenas valor dinâmico sem fallback
        'total_amount' => floatval($input['total'] ?? 0),
        'currency' => 'AUD'
    ];

    error_log("Dados do booking processados: " . json_encode($bookingData));
    
    // 🔍 DEBUG DO VALOR FINAL PROCESSADO
    error_log("💰 VALOR FINAL PROCESSADO: " . $bookingData['total_amount']);
    error_log("💰 SERÁ ENVIADO AO STRIPE: " . intval($bookingData['total_amount'] * 100) . " cents");

    // Tentar configurar Stripe
    $checkoutUrl = null;
    $sessionId = null;
    
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            // Carregar config
            if (file_exists(__DIR__ . '/../config.php')) {
                require_once __DIR__ . '/../config.php';
            }
            
            $stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? 
                        getenv('STRIPE_SECRET_KEY') ?? 
                        (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : null);
            
            if ($stripeKey) {
                \Stripe\Stripe::setApiKey($stripeKey);
                
                // Send exact amount to Stripe without any tax calculations
                $subtotal = $bookingData['total_amount']; // Send exactly what frontend calculated
                
                // 🔍 LOGS STRIPE ESPECÍFICOS
                error_log("💳 ENVIANDO PARA STRIPE: $subtotal AUD");
                error_log("💳 CENTAVOS STRIPE: " . intval($subtotal * 100));
                
                // Criar sessão Stripe
                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'aud',
                            'product_data' => [
                                'name' => 'Blue Cleaning Services - House Cleaning',
                                'description' => "Service on {$bookingData['service_date']} at {$bookingData['service_time']}",
                            ],
                            'unit_amount' => intval($subtotal * 100), // Send exact amount
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => 'https://bluefacilityservices.com.au/allblue2/booking-confirmation.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => 'https://bluefacilityservices.com.au/allblue2/booking3.php?service_id=1&cancelled=1',
                    'customer_email' => $bookingData['customer_email'],
                    'metadata' => [
                        'customer_name' => $bookingData['customer_name'],
                        'service_address' => $bookingData['service_address'],
                        'service_date' => $bookingData['service_date'],
                        'service_time' => $bookingData['service_time'],
                        'recurrence' => $bookingData['recurrence']
                    ]
                ]);
                
                $checkoutUrl = $session->url;
                $sessionId = $session->id;
                
                error_log("✅ Stripe session criada: " . $sessionId);
                
            } else {
                error_log("⚠️ Chave do Stripe não encontrada");
            }
            
        } catch (Exception $stripeError) {
            error_log("❌ Erro do Stripe: " . $stripeError->getMessage());
        }
    }

    // Tentar salvar no banco
    $bookingId = null;
    try {
        if (file_exists(__DIR__ . '/../config.php')) {
            require_once __DIR__ . '/../config.php';
            
            if (defined('DB_HOST') && defined('DB_NAME')) {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (
                        customer_name, customer_email, customer_phone,
                        service_address, service_date, service_time,
                        service_type, recurrence, total_amount, currency,
                        stripe_session_id, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $bookingData['customer_name'],
                    $bookingData['customer_email'],
                    $bookingData['customer_phone'],
                    $bookingData['service_address'],
                    $bookingData['service_date'],
                    $bookingData['service_time'],
                    $bookingData['service_type'],
                    $bookingData['recurrence'],
                    $bookingData['total_amount'],
                    $bookingData['currency'],
                    $sessionId
                ]);
                
                $bookingId = $pdo->lastInsertId();
                error_log("✅ Booking salvo no banco com ID: " . $bookingId);
            }
        }
    } catch (Exception $dbError) {
        error_log("⚠️ Erro do banco: " . $dbError->getMessage());
    }

    // Resposta final
    $response = [
        'success' => true,
        'message' => 'Booking processed successfully - NO availability checks',
        'data' => [
            'booking_data' => $bookingData,
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
            'booking_id' => $bookingId,
            'stripe_configured' => !empty($checkoutUrl)
        ],
        'next_step' => $checkoutUrl ? 'redirect_to_stripe' : 'manual_confirmation',
        'redirect_url' => $checkoutUrl ?? 'booking-confirmation.php?manual=1',
        'version' => 'final_no_availability',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    error_log("✅ Checkout finalizado com sucesso");
    echo json_encode($response);

} catch (Exception $e) {
    error_log("❌ Erro no checkout final: " . $e->getMessage());
    error_log("❌ Stack: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => 'final_no_availability',
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not-set'
        ]
    ]);
}
?>
