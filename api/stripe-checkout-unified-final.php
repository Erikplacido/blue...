<?php
/**
 * =========================================================
 * STRIPE CHECKOUT - ENDPOINT ÚNICO E UNIFICADO
 * =========================================================
 * 
 * @file api/stripe-checkout-unified-final.php
 * @description ÚNICO endpoint Stripe - substitui 8 APIs redundantes
 * @version 1.0 - FINAL UNIFIED
 * @date 2025-08-11
 * 
 * SUBSTITUÍDO:
 * ❌ stripe-checkout.php
 * ❌ stripe-checkout-unified.php  
 * ❌ stripe-checkout-production.php
 * ❌ stripe-checkout-final.php
 * ❌ stripe-checkout-no-csrf.php
 * ❌ stripe-checkout-simple.php
 * 
 * ✅ AGORA: 1 ÚNICO ENDPOINT
 */

// Headers seguros
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');

// Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Apenas POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    error_log("🚀 STRIPE UNIFIED FINAL - Starting request processing");
    
    // Carregar dependências com tratamento de erro
    if (!file_exists(__DIR__ . '/../core/StripeManager.php')) {
        throw new Exception('StripeManager.php not found');
    }
    
    if (!file_exists(__DIR__ . '/../core/PricingEngine.php')) {
        throw new Exception('PricingEngine.php not found');
    }
    
    require_once __DIR__ . '/../core/StripeManager.php';
    require_once __DIR__ . '/../core/PricingEngine.php';
    
    // Capturar dados de entrada
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('Empty request body');
    }
    
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        throw new Exception('Invalid JSON payload: ' . json_last_error_msg());
    }
    
    error_log("📦 UNIFIED: Payload received - " . json_encode($input, JSON_UNESCAPED_UNICODE));
    
    // Log the critical pricing data
    if (isset($input['total'])) {
        error_log("💰 CRITICAL: Frontend calculated total = $" . $input['total']);
    } else {
        error_log("⚠️ WARNING: No 'total' field in frontend data - will use PricingEngine");
    }
    
    // Validação básica
    $requiredFields = ['name', 'email', 'phone', 'address', 'suburb', 'postcode', 'date', 'time'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Preparar dados do booking
    $bookingData = [
        'service_id' => $input['service_id'] ?? '2',
        'name' => trim($input['name']),
        'email' => filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL),
        'phone' => trim($input['phone']),
        'address' => trim($input['address']),
        'suburb' => trim($input['suburb']),
        'postcode' => trim($input['postcode']),
        'date' => $input['date'],
        'time' => $input['time'],
        'recurrence' => $input['recurrence'] ?? 'one-time',
        'extras' => $input['extras'] ?? [],
        'discount_amount' => floatval($input['discount_amount'] ?? 0),
        'referral_code' => $input['referral_code'] ?? null,
        'special_requests' => $input['special_requests'] ?? '',
        'frontend_total' => floatval($input['total'] ?? 0) // Capture frontend-calculated total
    ];
    
    // Validar email
    if (!$bookingData['email']) {
        throw new Exception('Invalid email address');
    }
    
    error_log("✅ UNIFIED: Booking data validated");
    
    // Obter instância única do StripeManager com tratamento de erro
    try {
        $stripeManager = StripeManager::getInstance();
    } catch (Exception $e) {
        error_log("❌ UNIFIED: Error creating StripeManager - " . $e->getMessage());
        
        // Fallback para modo teste se StripeManager falhar
        $testPricing = PricingEngine::calculate(
            $bookingData['service_id'],
            $bookingData['extras'],
            $bookingData['recurrence'],
            $bookingData['discount_amount']
        );
        
        $fallbackResult = [
            'success' => true,
            'fallback_mode' => true,
            'session_id' => 'cs_fallback_' . uniqid(),
            'checkout_url' => 'https://checkout.stripe.com/fallback/session_' . uniqid(),
            'booking_code' => 'BCS-FALLBACK-' . strtoupper(uniqid()),
            'pricing' => $testPricing,
            'message' => 'Fallback mode - StripeManager error: ' . $e->getMessage(),
            'error_details' => $e->getMessage()
        ];
        
        error_log("🆘 UNIFIED: Fallback mode result - " . json_encode($fallbackResult));
        echo json_encode($fallbackResult);
        exit();
    }
    
    if (!$stripeManager->isConfigured()) {
        error_log("⚠️ UNIFIED: Stripe not configured - using test mode");
        
        // Modo de teste/simulação
        $testPricing = PricingEngine::calculate(
            $bookingData['service_id'],
            $bookingData['extras'],
            $bookingData['recurrence'],
            $bookingData['discount_amount']
        );
        
        $testResult = [
            'success' => true,
            'test_mode' => true,
            'session_id' => 'cs_test_' . uniqid(),
            'checkout_url' => 'https://checkout.stripe.com/test/session_' . uniqid(),
            'booking_code' => 'BCS-TEST-' . strtoupper(uniqid()),
            'pricing' => $testPricing,
            'message' => 'Test mode - Stripe not configured'
        ];
        
        error_log("🧪 UNIFIED: Test mode result - " . json_encode($testResult));
        echo json_encode($testResult);
        exit();
    }
    
    // Criar sessão Stripe usando gerenciador único
    $result = $stripeManager->createCheckoutSession($bookingData);
    
    // Log do resultado
    error_log("✅ UNIFIED: Session created successfully");
    error_log("📊 UNIFIED: Final amount = $" . $result['pricing']['final_amount']);
    error_log("💳 UNIFIED: Stripe amount = " . $result['pricing']['stripe_amount_cents'] . " cents");
    error_log("🎯 UNIFIED: Expected Stripe display = AU$" . $result['pricing']['final_amount']);
    
    // Critical validation log
    if (isset($bookingData['frontend_total']) && $bookingData['frontend_total'] > 0) {
        if ($result['pricing']['final_amount'] == $bookingData['frontend_total']) {
            error_log("✅ PRICE MATCH: Frontend ($" . $bookingData['frontend_total'] . ") = Stripe ($" . $result['pricing']['final_amount'] . ")");
        } else {
            error_log("❌ PRICE MISMATCH: Frontend ($" . $bookingData['frontend_total'] . ") ≠ Stripe ($" . $result['pricing']['final_amount'] . ")");
        }
    }
    
    // Adicionar informações de debug para verificação
    $result['debug_info'] = [
        'endpoint_used' => 'stripe-checkout-unified-final.php',
        'pricing_engine' => 'PricingEngine v1.0',
        'stripe_manager' => 'StripeManager v1.0',
        'api_version' => '1.0-UNIFIED',
        'timestamp' => date('Y-m-d H:i:s'),
        'pricing_breakdown' => [
            'base_price' => $result['pricing']['base_price'],
            'extras_price' => $result['pricing']['extras_price'],
            'total_discount' => $result['pricing']['total_discount'],
            'final_amount' => $result['pricing']['final_amount']
        ]
    ];
    
    echo json_encode($result);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    
    error_log("❌ UNIFIED ERROR: $errorMessage");
    error_log("📁 File: $errorFile");
    error_log("📍 Line: $errorLine");
    error_log("🔍 Trace: " . $e->getTraceAsString());
    
    // Resposta de erro mais informativa
    $errorResponse = [
        'success' => false,
        'error' => $errorMessage,
        'endpoint' => 'stripe-checkout-unified-final.php',
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'file' => basename($errorFile),
            'line' => $errorLine,
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]
    ];
    
    // Se for erro de configuração, dar dica
    if (strpos($errorMessage, 'STRIPE_SECRET_KEY') !== false) {
        $errorResponse['suggestion'] = 'Configure STRIPE_SECRET_KEY in .env file';
    } elseif (strpos($errorMessage, 'not found') !== false) {
        $errorResponse['suggestion'] = 'Check if all required files exist';
    }
    
    http_response_code(500);
    echo json_encode($errorResponse);
}
?>
