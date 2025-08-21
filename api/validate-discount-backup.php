<?php
/**
 * =========================================================
 * API DE VALIDAÇÃO DE DESCONTO - VERSÃO BANCO DE DADOS
 * =========================================================
 * 
 * @file api/validate-discount.php
 * @description API para validar cupons de desconto usando banco
 * @date 2025-08-11
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load CouponManager
require_once __DIR__ . '/../core/CouponManager.php';

try {
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // VALIDAR CUPOM
        $input = json_decode(file_get_contents('php://input'), true);
        $code = trim($input['code'] ?? '');
        $subtotal = floatval($input['subtotal'] ?? 0);
        $customerEmail = trim($input['customer_email'] ?? '');
        
        if (empty($code)) {
            echo json_encode([
                'valid' => false,
                'message' => 'Código do cupom é obrigatório',
                'discount_amount' => 0
            ]);
            exit;
        }
        
        if ($subtotal <= 0) {
            echo json_encode([
                'valid' => false,
                'message' => 'Valor do subtotal inválido',
                'discount_amount' => 0
            ]);
            exit;
        }
        
        // Inicializar CouponManager
        $couponManager = createCouponManager(false);
        
        // Validar cupom
        $result = $couponManager->validateCoupon($code, $subtotal, $customerEmail);
        
        echo json_encode($result);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // LISTAR CUPONS ATIVOS (para debug/admin)
        $couponManager = createCouponManager(false);
        
        if (isset($_GET['action'])) {
            
            if ($_GET['action'] === 'list') {
                $coupons = $couponManager->getActiveCoupons();
                echo json_encode([
                    'success' => true,
                    'coupons' => $coupons,
                    'total' => count($coupons)
                ]);
                
            } elseif ($_GET['action'] === 'stats') {
                $couponCode = $_GET['code'] ?? null;
                $stats = $couponManager->getCouponStats($couponCode);
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
                
            } else {
                echo json_encode([
                    'error' => 'Ação não reconhecida',
                    'available_actions' => ['list', 'stats']
                ]);
            }
            
        } else {
            // API Info
            echo json_encode([
                'api' => 'Blue Cleaning - Discount Validation API',
                'version' => '2.0',
                'database' => 'enabled',
                'methods' => [
                    'POST' => 'Validate coupon code',
                    'GET ?action=list' => 'List active coupons',
                    'GET ?action=stats' => 'Get coupon statistics'
                ],
                'usage' => [
                    'validate' => 'POST {code: "WELCOME10", subtotal: 100.00, customer_email: "user@example.com"}',
                    'list' => 'GET ?action=list',
                    'stats' => 'GET ?action=stats&code=WELCOME10'
                ]
            ]);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'error' => 'Erro interno do servidor',
        'message' => 'Não foi possível processar a solicitação',
        'debug' => $e->getMessage() // Remover em produção
    ]);
    
}
}

// Check if code exists
if (!isset($discountCodes[$code])) {
    echo json_encode([
        'valid' => false,
        'message' => 'Invalid discount code'
    ]);
    exit();
}

$discount = $discountCodes[$code];

// Check expiry date
$currentDate = new DateTime();
$validUntil = new DateTime($discount['valid_until']);

if ($currentDate > $validUntil) {
    echo json_encode([
        'valid' => false,
        'message' => 'Discount code has expired'
    ]);
    exit();
}

// Check minimum amount
if ($subtotal < $discount['minimum_amount']) {
    echo json_encode([
        'valid' => false,
        'message' => sprintf(
            'Minimum booking amount of $%.2f required for this discount',
            $discount['minimum_amount']
        )
    ]);
    exit();
}

// Calculate discount amount
$discountAmount = 0;

if ($discount['type'] === 'percentage') {
    $discountAmount = ($subtotal * $discount['value']) / 100;
    // Apply maximum discount limit
    if ($discount['maximum_discount'] && $discountAmount > $discount['maximum_discount']) {
        $discountAmount = $discount['maximum_discount'];
    }
} else if ($discount['type'] === 'fixed') {
    $discountAmount = $discount['value'];
    // Cannot exceed subtotal
    if ($discountAmount > $subtotal) {
        $discountAmount = $subtotal;
    }
}

// Simulate usage limit check (in real app, check database)
if ($discount['usage_limit']) {
    $currentUsage = rand(0, $discount['usage_limit']); // Mock current usage
    
    if ($currentUsage >= $discount['usage_limit']) {
        echo json_encode([
            'valid' => false,
            'message' => 'Discount code usage limit has been reached'
        ]);
        exit();
    }
}

// Simulate first-time customer check (in real app, check customer history)
if ($discount['first_time_only']) {
    // Mock: 20% chance they're not first-time customer
    $isFirstTime = rand(1, 100) > 20;
    
    if (!$isFirstTime) {
        echo json_encode([
            'valid' => false,
            'message' => 'This discount is only available for first-time customers'
        ]);
        exit();
    }
}

// Success response
echo json_encode([
    'valid' => true,
    'code' => $code,
    'description' => $discount['description'],
    'discount_amount' => round($discountAmount, 2),
    'discount_type' => $discount['type'],
    'discount_value' => $discount['value'],
    'applied_to_subtotal' => $subtotal,
    'final_discount' => round($discountAmount, 2),
    'savings_message' => sprintf(
        'You saved $%.2f with code %s!',
        $discountAmount,
        $code
    )
]);
?>
