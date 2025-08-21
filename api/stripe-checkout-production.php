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
 * STRIPE CHECKOUT API - BLUE CLEANING SERVICES
 * =========================================================
 * 
 * VERSÃO CORRIGIDA PARA PRODUÇÃO
 * - Sem SecurityMiddleware problemático
 * - Headers CORS corretos
 * - Syntax errors corrigidos
 */

// Headers CORS e JSON primeiro
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');

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
    error_log("=== STRIPE CHECKOUT PRODUCTION === " . date('Y-m-d H:i:s'));
    error_log("Request headers: " . json_encode(getallheaders()));
    
    // Ler JSON input primeiro para ter acesso ao CSRF token
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON payload');
    }
    
    // 1. VALIDAR TOKEN CSRF PRIMEIRO
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 
                 $_POST['_csrf_token'] ?? 
                 $input['_csrf_token'] ?? null;
    
    if (!$csrfToken) {
        error_log("❌ No CSRF token provided");
        throw new Exception('CSRF token is required');
    }
    
    // Inicializar AuthManager para validar CSRF
    require_once __DIR__ . '/../auth/AuthManager.php';
    $auth = AuthManager::getInstance();
    
    if (!$auth->verifyCSRFToken($csrfToken)) {
        error_log("❌ CSRF token validation failed: " . $csrfToken);
        http_response_code(419);
        echo json_encode([
            'error' => 'CSRF token validation failed',
            'code' => 419,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    error_log("✅ CSRF token validated successfully");

    error_log("Checkout payload received: " . json_encode($input, JSON_UNESCAPED_UNICODE));

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
    
    // Conectar ao banco se possível para obter preços reais
    // IMPORTANTE: Sem preço padrão - valores totalmente dinâmicos
    $basePrice = 0.00; // Será calculado dinamicamente
    $extrasPrice = 0;
    $discountAmount = 0;
    
    // CRÍTICO: Se o frontend enviou um total, usar ele (mais confiável)
    if (!empty($input['total']) && is_numeric($input['total']) && $input['total'] > 0) {
        $totalAmount = floatval($input['total']);
        error_log("💰 Using total from frontend: $totalAmount (exactly as received)");
        
        // Use frontend total exactly without any modifications
        $basePrice = $totalAmount; // Keep exact value from frontend
        $extrasPrice = 0;
        $discountAmount = 0;
        $gstAmount = 0; // No tax calculations
    } else {
        // Fallback: tentar conectar ao banco para preços reais
        try {
            require_once __DIR__ . '/../config/australian-database.php';
            $db = AustralianDatabase::getInstance();
            $connection = $db->getConnection();
            
            $serviceId = intval($input['service']);
            $serviceStmt = $connection->prepare("SELECT base_price FROM services WHERE id = ?");
            $serviceStmt->execute([$serviceId]);
            $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($service && !empty($service['base_price'])) {
                $basePrice = floatval($service['base_price']);
                error_log("💰 Retrieved base price from database: $basePrice");
            } else {
                error_log("⚠️ Could not retrieve price from database, using default: $basePrice");
            }
        } catch (Exception $dbError) {
            error_log("⚠️ Database connection failed, using default price: " . $dbError->getMessage());
        }
    }
    
    // Calcular extras se especificados
    // Inicializar variáveis para logs (serão definidas corretamente na seção CALCULAR PREÇOS)
    $basePrice = 0;
    $extrasPrice = 0; 
    $discountAmount = 0;
    $gstAmount = 0;
    $totalAmount = 0;

    // 2. CONFIGURAR STRIPE (se disponível)
    $stripeConfigured = false;
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Tentar carregar configurações
        if (file_exists(__DIR__ . '/../config.php')) {
            require_once __DIR__ . '/../config.php';
        }
        
        // Verificar se temos as chaves do Stripe
        $stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?? null;
        
        if ($stripeSecretKey && class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($stripeSecretKey);
            $stripeConfigured = true;
            error_log("✅ Stripe configured successfully");
        } else {
            error_log("⚠️ Stripe not configured, using test mode");
        }
    }

    // 3. CALCULAR PREÇOS
    $serviceId = intval($input['service']);
    
    // CORREÇÃO CRÍTICA: Priorizar total do frontend sobre cálculos hardcoded
    if (isset($input['total']) && $input['total'] > 0) {
        $totalAmount = floatval($input['total']);
        error_log("✅ PRODUCTION API - Using frontend total EXACTLY: $totalAmount");
        error_log("✅ Frontend sent exact value to charge");
        
        // Use exact frontend value without any tax modifications
        $basePrice = $totalAmount; // Exact value from frontend
        $extrasPrice = 0;
        $discountAmount = 0;
        $gstAmount = 0; // No tax calculations
        
    } else {
        // Fallback para cálculos internos apenas se não há total do frontend
        error_log("⚠️ PRODUCTION API - No frontend total provided, using dynamic database values");
        
        // IMPORTANTE: Buscar preços do banco de dados em vez de valores fixos
        $databasePrice = getDynamicServicePrice($serviceId); // Implementar esta função
        $basePrice = $databasePrice > 0 ? $databasePrice : 0.00; // Sem fallback fixo
        
        // Se não conseguir do banco, retornar erro em vez de usar valor fixo
        if ($basePrice <= 0) {
            throw new Exception("Unable to determine service price. Please refresh the page and try again.");
        }

        // Calcular extras
        $extrasPrice = 0;
        if (!empty($input['extras']) && is_array($input['extras'])) {
            foreach ($input['extras'] as $extraId => $selected) {
                if ($selected) {
                    $extrasPrice += 15.00; // Preço padrão por extra
                }
            }
        }

        // Calcular desconto (se houver)
        $discountAmount = 0;
        if (!empty($input['discount_code'])) {
            // Lógica de desconto pode ser implementada aqui
            $discountAmount = 5.00; // Exemplo
        }

        // Fallback calculation WITHOUT any tax
        $subtotal = ($basePrice + $extrasPrice) - $discountAmount;
        $gstAmount = 0; // No tax calculations
        $totalAmount = $subtotal;
        
        error_log("⚠️ FALLBACK CALCULATION: base=$basePrice + extras=$extrasPrice - discount=$discountAmount = $totalAmount");
    }    error_log("💰 Pricing calculated: Base: {$basePrice}, Extras: {$extrasPrice}, Discount: {$discountAmount}, Total: {$totalAmount}");

    // 4. GERAR BOOKING CODE
    $bookingCode = 'BK' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

    // 5. CRIAR CHECKOUT SESSION
    if ($stripeConfigured) {
        // Stripe real
        try {
            $checkoutSession = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'aud',
                        'product_data' => [
                            'name' => 'Blue Cleaning Services',
                            'description' => "Service booking #{$bookingCode}",
                        ],
                        'unit_amount' => round($totalAmount * 100), // Stripe usa cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => 'https://bluefacilityservices.com.au/allblue2/booking-confirmation-stripe.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'https://bluefacilityservices.com.au/allblue2/booking3.php?cancelled=1',
                'metadata' => [
                    'booking_code' => $bookingCode,
                    'service_id' => $serviceId,
                    'customer_name' => $input['customer']['name'],
                    'customer_email' => $input['customer']['email'],
                    'customer_phone' => $input['customer']['phone'] ?? '',
                    'address' => $input['address'],
                    'date' => $input['date'] ?? '',
                    'time' => $input['time'] ?? '',
                    'recurrence' => $input['recurrence'] ?? 'one-time',
                ],
            ]);

            $checkoutUrl = $checkoutSession->url;
            $sessionId = $checkoutSession->id;
            
        } catch (Exception $stripeError) {
            error_log("Stripe error: " . $stripeError->getMessage());
            throw new Exception("Payment processing error: " . $stripeError->getMessage());
        }
        
    } else {
        // Modo de teste - simular Stripe
        $checkoutUrl = "https://checkout.stripe.com/pay/test_" . $bookingCode;
        $sessionId = "cs_test_" . $bookingCode;
        
        error_log("🧪 Test mode: Generated checkout URL: {$checkoutUrl}");
    }

    // 6. RESPOSTA DE SUCESSO
    $response = [
        'success' => true,
        'message' => 'Checkout session created successfully',
        'checkout_url' => $checkoutUrl,
        'session_id' => $sessionId,
        'booking_code' => $bookingCode,
        'total_amount' => $totalAmount,
        'currency' => 'AUD',
        'stripe_configured' => $stripeConfigured,
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    error_log("✅ Checkout success: " . json_encode($response));
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("❌ Checkout error: " . $e->getMessage());
    error_log("❌ Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'php_version' => PHP_VERSION,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
        ]
    ]);
}
?>
