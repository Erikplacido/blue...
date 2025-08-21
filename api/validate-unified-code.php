<?php
/**
 * API: Validação de Códigos Unificados (Referrals + Promos)
 * Endpoint: /api/validate-unified-code.php
 * Substitui a simulação JavaScript por validação real
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
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!$data || empty($data['code'])) {
        throw new Exception('Invalid or missing code');
    }
    
    $code = strtoupper(trim($data['code']));
    $response = [
        'success' => false,
        'valid' => false,
        'code' => $code,
        'type' => 'unknown',
        'discount_percentage' => 0,
        'discount_amount' => 0,
        'message' => 'Code not found'
    ];
    
    // Conectar ao banco
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // 1. VERIFICAR COMO REFERRAL CODE
    $stmt = $pdo->prepare("
        SELECT id, current_level_id, referral_code 
        FROM referral_users 
        WHERE referral_code = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($referrer) {
        // É um código de referral
        $discount = 0;
        switch ($referrer['current_level_id']) {
            case 3: $discount = 15; break; // Sapphire 15%
            case 2: $discount = 10; break; // Tanzanite 10%
            case 1: 
            default: $discount = 5; break;  // Topaz 5%
        }
        
        $response = [
            'success' => true,
            'valid' => true,
            'code' => $code,
            'type' => 'referral',
            'discount_percentage' => $discount,
            'discount_amount' => 0, // Será calculado no frontend
            'referrer_id' => $referrer['id'],
            'level_id' => $referrer['current_level_id'],
            'message' => "Referral code valid - {$discount}% discount"
        ];
    } else {
        // 2. VERIFICAR COMO PROMO CODE (tabela coupons)
        $stmt = $pdo->prepare("
            SELECT id, code, discount_percentage, discount_amount, 
                   expires_at, is_active, usage_limit, used_count
            FROM coupons 
            WHERE code = ? AND is_active = 1 
            AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($promo) {
            // Verificar limite de uso
            if ($promo['usage_limit'] > 0 && $promo['used_count'] >= $promo['usage_limit']) {
                $response = [
                    'success' => false,
                    'valid' => false,
                    'code' => $code,
                    'type' => 'promo',
                    'message' => 'This coupon has reached its usage limit'
                ];
            } else {
                // Código promocional válido
                $discount = $promo['discount_percentage'] ?: 0;
                $fixedAmount = $promo['discount_amount'] ?: 0;
                
                $response = [
                    'success' => true,
                    'valid' => true,
                    'code' => $code,
                    'type' => 'promo',
                    'discount_percentage' => $discount,
                    'discount_amount' => $fixedAmount,
                    'coupon_id' => $promo['id'],
                    'expires_at' => $promo['expires_at'],
                    'message' => $fixedAmount > 0 ? 
                        "Promo code valid - $" . number_format($fixedAmount, 2) . " off" :
                        "Promo code valid - {$discount}% discount"
                ];
            }
        }
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'valid' => false,
        'error' => 'Code validation failed',
        'message' => $e->getMessage()
    ]);
    
    error_log("Code validation error: " . $e->getMessage());
}
?>
