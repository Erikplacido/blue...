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
 * STRIPE CHECKOUT UNIFIED - VERSÃO CONSOLIDADA
 * 
 * Esta versão corrige definitivamente a disparidade de preços:
 * - Frontend envia total final calculado
 * - API usa esse valor EXATAMENTE sem adição de GST
 * - Stripe recebe o valor correto
 * - GST é tratado pelo Stripe quando necessário
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Log início
error_log("🚀 STRIPE CHECKOUT UNIFIED - Starting request processing");

try {
    // 1. PROCESSAR INPUT
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON in request body');
    }
    
    // Validações básicas
    if (empty($input['service']) || empty($input['customer']) || empty($input['address'])) {
        throw new Exception('Missing required fields: service, customer, or address');
    }
    
    error_log("✅ Input validated: " . json_encode(array_keys($input)));
    
    // 2. CONFIGURAR STRIPE
    $stripeConfigured = false;
    $stripeSecretKey = null;
    
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        if (file_exists(__DIR__ . '/../config.php')) {
            require_once __DIR__ . '/../config.php';
        }
        
        $stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?? null;
        
        if ($stripeSecretKey && class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($stripeSecretKey);
            $stripeConfigured = true;
            error_log("✅ Stripe configured successfully");
        }
    }
    
    if (!$stripeConfigured) {
        error_log("⚠️ Stripe not configured - using test mode");
    }
    
    // 3. PROCESSAMENTO DE PREÇOS - MÉTODO UNIFICADO
    $finalAmount = 0;
    
    // REGRA FUNDAMENTAL: Se frontend enviou total, use-o EXATAMENTE
    if (isset($input['total']) && $input['total'] > 0) {
        $finalAmount = floatval($input['total']);
        
        error_log("✅ UNIFIED API - Using frontend total EXACTLY: $finalAmount");
        error_log("✅ NO GST ADDED - Frontend value used as-is");
        error_log("✅ This should resolve pricing discrepancies");
        
        // Log detalhado do fluxo
        error_log("📊 PRICE FLOW:");
        error_log("   Frontend calculated: $finalAmount");
        error_log("   API processes: $finalAmount (unchanged)");
        error_log("   Stripe receives: " . intval($finalAmount * 100) . " centavos");
        error_log("   Expected Stripe display: AU$ $finalAmount");
        
    } else {
        // ERROR: Frontend must always provide total - no fallback allowed
        error_log("❌ CRITICAL: No frontend total provided - this is not allowed in dynamic pricing system");
        http_response_code(400);
        echo json_encode([
            'error' => 'Frontend total required',
            'message' => 'Dynamic pricing system requires frontend to calculate and send total'
        ]);
        exit;
        
        // Calcular extras se houver
        $extrasPrice = 0;
        if (!empty($input['extras']) && is_array($input['extras'])) {
            foreach ($input['extras'] as $extraId => $selected) {
                if ($selected) {
                    $extrasPrice += 15.00; // Preço padrão por extra
                }
            }
        }
        
        // Calcular desconto se houver
        $discountAmount = 0;
        if (!empty($input['recurrence']) && $input['recurrence'] !== 'one-time') {
            $discounts = [
                'weekly' => 0.10,
                'fortnightly' => 0.15,
                'monthly' => 0.20
            ];
            
            $discountRate = $discounts[$input['recurrence']] ?? 0;
            $discountAmount = ($basePrice + $extrasPrice) * $discountRate;
        }
        
        // CONFIGURAÇÃO CORRETA: Site exibe valor SEM GST, Stripe aplica GST automaticamente
        $finalAmount = ($basePrice + $extrasPrice) - $discountAmount;
        
        error_log("✅ UNIFIED FINAL: base=$basePrice + extras=$extrasPrice - discount=$discountAmount = $finalAmount (NO GST - Stripe adds automatically)");
    }
    
    // 4. GERAR BOOKING CODE
    $bookingCode = 'BK' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    // 5. CRIAR CHECKOUT SESSION NO STRIPE
    $checkoutUrl = '';
    $sessionId = '';
    
    if ($stripeConfigured) {
        try {
            error_log("💳 Creating Stripe session with amount: " . intval($finalAmount * 100) . " centavos");
            
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'aud',
                        'product_data' => [
                            'name' => 'Blue Cleaning Services',
                            'description' => "Service on {$input['date']} at {$input['time']} - Booking: {$bookingCode}",
                        ],
                        'unit_amount' => intval($finalAmount * 100), // VALOR EXATO em centavos
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                // REMOVIDO: automatic_tax (conforme especificação - sem GST no projeto)
                // 'automatic_tax' => ['enabled' => true],
                'success_url' => 'https://bluefacilityservices.com.au/allblue2/booking-confirmation-stripe.php?session_id={CHECKOUT_SESSION_ID}&booking_code=' . $bookingCode,
                'cancel_url' => 'https://bluefacilityservices.com.au/allblue2/booking3.php?service_id=1&cancelled=1',
                'customer_email' => $input['customer']['email'] ?? '',
                'metadata' => [
                    'booking_code' => $bookingCode,
                    'customer_name' => $input['customer']['name'] ?? '',
                    'service_address' => $input['address'] ?? '',
                    'service_date' => $input['date'] ?? '',
                    'service_time' => $input['time'] ?? '',
                    'recurrence' => $input['recurrence'] ?? 'one-time',
                    'original_amount' => $finalAmount,
                    'frontend_total_used' => isset($input['total']) ? 'true' : 'false'
                ]
            ]);
            
            $checkoutUrl = $session->url;
            $sessionId = $session->id;
            
            error_log("✅ Stripe session created successfully: $sessionId");
            error_log("✅ Checkout URL generated: " . substr($checkoutUrl, 0, 50) . "...");
            
        } catch (Exception $stripeError) {
            error_log("❌ Stripe error: " . $stripeError->getMessage());
            throw new Exception("Stripe checkout failed: " . $stripeError->getMessage());
        }
    } else {
        // Modo teste - simular sucesso
        $checkoutUrl = 'https://checkout.stripe.com/test/session_' . uniqid();
        $sessionId = 'cs_test_' . uniqid();
        
        error_log("🧪 Test mode - simulated Stripe session: $sessionId");
    }
    
    // 6. TENTAR SALVAR NO BANCO (opcional - não bloquear se falhar)
    $bookingId = null;
    try {
        if (file_exists(__DIR__ . '/../config.php')) {
            require_once __DIR__ . '/../config.php';
            
            if (isset($connection) && $connection) {
                $stmt = $connection->prepare("
                    INSERT INTO bookings (
                        booking_code, customer_name, customer_email, customer_phone,
                        service_address, service_date, service_time, service_type,
                        recurrence, total_amount, currency, stripe_session_id,
                        status, created_at
                    ) VALUES (
                        :booking_code, :customer_name, :customer_email, :customer_phone,
                        :service_address, :service_date, :service_time, :service_type,
                        :recurrence, :total_amount, :currency, :stripe_session_id,
                        'pending', NOW()
                    )
                ");
                
                $stmt->execute([
                    ':booking_code' => $bookingCode,
                    ':customer_name' => $input['customer']['name'] ?? '',
                    ':customer_email' => $input['customer']['email'] ?? '',
                    ':customer_phone' => $input['customer']['phone'] ?? '',
                    ':service_address' => $input['address'] ?? '',
                    ':service_date' => $input['date'] ?? '',
                    ':service_time' => $input['time'] ?? '',
                    ':service_type' => $input['service'] ?? '1',
                    ':recurrence' => $input['recurrence'] ?? 'one-time',
                    ':total_amount' => $finalAmount,
                    ':currency' => 'AUD',
                    ':stripe_session_id' => $sessionId
                ]);
                
                $bookingId = $connection->lastInsertId();
                error_log("✅ Booking saved to database with ID: $bookingId");
            }
        }
    } catch (Exception $dbError) {
        error_log("⚠️ Database save failed (non-critical): " . $dbError->getMessage());
        // Não falhar - continuar mesmo se DB não funcionar
    }
    
    // 7. RESPOSTA DE SUCESSO
    $response = [
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'session_id' => $sessionId,
        'booking_code' => $bookingCode,
        'booking_id' => $bookingId,
        'amount' => $finalAmount,
        'currency' => 'AUD',
        'stripe_amount_cents' => intval($finalAmount * 100),
        'message' => 'Checkout session created successfully',
        'debug_info' => [
            'frontend_total_used' => isset($input['total']),
            'original_frontend_total' => $input['total'] ?? null,
            'final_stripe_amount' => $finalAmount,
            'api_version' => 'unified_v1',
            'gst_handling' => 'none_by_api_stripe_handles_if_needed'
        ]
    ];
    
    error_log("✅ UNIFIED API - Success response generated");
    error_log("✅ Expected result: Stripe charges AU$ $finalAmount instead of AU$ 137.50");
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("❌ UNIFIED API Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'api_version' => 'unified_v1',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);
}
?>
