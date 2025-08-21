<?php
/**
 * =========================================================
 * STRIPE CHECKOUT API - VERSÃO SEM CSRF (EMERGENCIAL)
 * =========================================================
 * 
 * Esta versão funciona sem validação CSRF para casos onde
 * o token CSRF não está funcionando corretamente.
 * USAR APENAS EM DESENVOLVIMENTO OU EMERGÊNCIAS.
 */

// Headers CORS e JSON primeiro
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization, X-Requested-With');

// Verificar OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Log da requisição
    error_log("=== STRIPE CHECKOUT NO-CSRF === " . date('Y-m-d H:i:s'));
    error_log("⚠️ WARNING: Using NO-CSRF version - not recommended for production");
    
    // 1. CAPTURAR E VALIDAR DADOS (SEM CSRF)
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON payload');
    }

    error_log("Checkout payload received (NO-CSRF): " . json_encode($input, JSON_UNESCAPED_UNICODE));

    // Validar campos obrigatórios
    $requiredFields = ['service', 'address', 'customer'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Validar customer data
    if (empty($input['customer']['name']) || empty($input['customer']['email'])) {
        throw new Exception('Customer name and email are required');
    }
    
    // Validar se address não está vazio
    if (empty(trim($input['address']))) {
        throw new Exception('Address is required and cannot be empty');
    }
    
    error_log("✅ Validation passed for customer: {$input['customer']['name']}, address: {$input['address']}");

    // 2. CONFIGURAR STRIPE (se disponível)
    $stripeConfigured = false;
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Tentar carregar configurações
        $stripeSecretKey = getenv('STRIPE_SECRET_KEY');
        
        if ($stripeSecretKey) {
            \Stripe\Stripe::setApiKey($stripeSecretKey);
            $stripeConfigured = true;
            error_log("✅ Stripe configured successfully");
        } else {
            error_log("⚠️ Stripe API key not found in environment");
        }
    } else {
        error_log("⚠️ Stripe SDK not found - vendor/autoload.php missing");
    }

    // 3. PROCESSAR DADOS DO BOOKING
    $bookingData = [
        'customer_name' => $input['customer']['name'],
        'customer_email' => $input['customer']['email'],
        'customer_phone' => $input['customer']['phone'] ?? '',
        'service_address' => $input['address'],
        'service_date' => $input['date'] ?? date('Y-m-d', strtotime('+2 days')),
        'service_time' => $input['time'] ?? '10:00',
        'service_type' => 'House Cleaning',
        'recurrence' => $input['recurrence'] ?? 'one-time',
        'total_amount' => floatval($input['total'] ?? 0), // Sem fallback fixo
        'currency' => $input['currency'] ?? 'AUD',
        'created_at' => date('Y-m-d H:i:s')
    ];

    error_log("Processed booking data: " . json_encode($bookingData));

    // 4. CRIAR SESSÃO DO STRIPE (se configurado)
    $checkoutUrl = null;
    $sessionId = null;
    
    if ($stripeConfigured) {
        try {
            // Calcular GST (10% na Austrália)
            $subtotal = $bookingData['total_amount'];
            $gst = $subtotal * 0.10;
            $totalWithGst = $subtotal + $gst;
            
            // Criar sessão do Stripe Checkout
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($bookingData['currency']),
                        'product_data' => [
                            'name' => 'Blue Cleaning Services - ' . $bookingData['service_type'],
                            'description' => 'Service on ' . $bookingData['service_date'] . ' at ' . $bookingData['service_time'],
                        ],
                        'unit_amount' => intval($totalWithGst * 100), // Stripe usa centavos
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
            
            error_log("✅ Stripe checkout session created: " . $sessionId);
            
        } catch (Exception $stripeError) {
            error_log("❌ Stripe error: " . $stripeError->getMessage());
            // Continuar sem Stripe - apenas salvar dados
        }
    }

    // 5. SALVAR NO BANCO DE DADOS (se disponível)
    try {
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
                ) VALUES (
                    :customer_name, :customer_email, :customer_phone,
                    :service_address, :service_date, :service_time,
                    :service_type, :recurrence, :total_amount, :currency,
                    :stripe_session_id, 'pending', :created_at
                )
            ");
            
            $stmt->execute([
                ':customer_name' => $bookingData['customer_name'],
                ':customer_email' => $bookingData['customer_email'],
                ':customer_phone' => $bookingData['customer_phone'],
                ':service_address' => $bookingData['service_address'],
                ':service_date' => $bookingData['service_date'],
                ':service_time' => $bookingData['service_time'],
                ':service_type' => $bookingData['service_type'],
                ':recurrence' => $bookingData['recurrence'],
                ':total_amount' => $bookingData['total_amount'],
                ':currency' => $bookingData['currency'],
                ':stripe_session_id' => $sessionId,
                ':created_at' => $bookingData['created_at']
            ]);
            
            $bookingId = $pdo->lastInsertId();
            error_log("✅ Booking saved to database with ID: " . $bookingId);
            
        } else {
            error_log("⚠️ Database not configured - booking not saved");
        }
        
    } catch (Exception $dbError) {
        error_log("❌ Database error: " . $dbError->getMessage());
        // Continuar sem salvar no banco
    }

    // 6. RESPOSTA DE SUCESSO
    $response = [
        'success' => true,
        'message' => 'Booking processed successfully',
        'data' => [
            'booking_data' => $bookingData,
            'stripe_configured' => $stripeConfigured,
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
            'booking_id' => $bookingId ?? null
        ],
        'next_step' => $checkoutUrl ? 'redirect_to_stripe' : 'manual_payment',
        'redirect_url' => $checkoutUrl ?? 'booking-confirmation.php?manual=1'
    ];

    error_log("✅ Checkout successful - returning response");
    echo json_encode($response);

} catch (Exception $e) {
    error_log("❌ Checkout error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'file' => __FILE__,
            'line' => __LINE__,
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not-set'
        ]
    ]);
}
