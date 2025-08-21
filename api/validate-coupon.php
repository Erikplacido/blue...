<?php
/**
 * API: VALIDAÇÃO REAL DE CÓDIGOS UNIFICADOS
 * =========================================
 * Valida codes promocionais e de referral no banco de dados
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['code'])) {
        throw new Exception('Code is required');
    }
    
    $code = strtoupper(trim($input['code']));
    
    if (empty($code)) {
        throw new Exception('Code cannot be empty');
    }
    
    error_log("🔍 VALIDATING CODE: $code");
    
    // 1. VERIFICAR EM REFERRAL_USERS (códigos de referral)
    $stmt = $pdo->prepare("
        SELECT id, referral_code, current_level_id, is_active 
        FROM referral_users 
        WHERE referral_code = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $referralUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($referralUser) {
        // É um código de referral válido
        $discountPercentage = 5; // Padrão
        
        switch ($referralUser['current_level_id']) {
            case 3: $discountPercentage = 15; break; // Sapphire
            case 2: $discountPercentage = 10; break; // Tanzanite  
            case 1: default: $discountPercentage = 5; break; // Topaz
        }
        
        echo json_encode([
            'success' => true,
            'valid' => true,
            'type' => 'referral',
            'code' => $code,
            'discount_percentage' => $discountPercentage,
            'message' => "Referral code applied! {$discountPercentage}% discount + commission for referrer",
            'referrer_id' => $referralUser['id']
        ]);
        error_log("✅ VALID REFERRAL CODE: $code (User ID: {$referralUser['id']})");
        exit;
    }
    
    // 2. VERIFICAR EM PROMO_CODES (códigos promocionais - tabela nova)
    $stmt = $pdo->prepare("
        SELECT code, discount_percentage, discount_amount, is_active, valid_until
        FROM promo_codes 
        WHERE code = ? AND is_active = 1 
        AND (valid_until IS NULL OR valid_until > NOW())
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $promoCode = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($promoCode) {
        $discountPercentage = $promoCode['discount_percentage'] ?? 0;
        $discountAmount = $promoCode['discount_amount'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'valid' => true,
            'type' => 'promo',
            'code' => $code,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'message' => "Promo code applied! " . 
                        ($discountPercentage > 0 ? "{$discountPercentage}% discount" : "\${$discountAmount} discount")
        ]);
        error_log("✅ VALID PROMO CODE: $code");
        exit;
    }
    
    // 3. VERIFICAR EM COUPONS (códigos na tabela coupons - sistema atual)
    $stmt = $pdo->prepare("
        SELECT code, type, value, is_active, valid_until
        FROM coupons 
        WHERE code = ? AND is_active = 1 
        AND (valid_until IS NULL OR valid_until > NOW())
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coupon) {
        $discountValue = floatval($coupon['value']);
        
        echo json_encode([
            'success' => true,
            'valid' => true,
            'type' => 'coupon',
            'subtype' => $coupon['type'], // percentage, fixed
            'code' => $code,
            'discount_percentage' => $coupon['type'] === 'percentage' ? $discountValue : 0,
            'discount_amount' => $coupon['type'] === 'fixed' ? $discountValue : 0,
            'message' => "Coupon applied! " . 
                        ($coupon['type'] === 'percentage' ? "{$discountValue}% discount" : "\${$discountValue} discount")
        ]);
        error_log("✅ VALID COUPON: $code (Type: {$coupon['type']}, Value: {$discountValue})");
        exit;
    }
    
    // 4. CÓDIGO NÃO ENCONTRADO
    echo json_encode([
        'success' => true,
        'valid' => false,
        'code' => $code,
        'message' => "Code '{$code}' not found or expired"
    ]);
    error_log("❌ INVALID CODE: $code");
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    error_log("❌ CODE VALIDATION ERROR: " . $e->getMessage());
}
?>
