<?php
/**
 * API: Sistema Unificado de Códigos
 * Endpoint: /api/booking/create-unified
 * Processa referral_codes E promo_codes de forma inteligente
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Ler dados JSON
    $json_input = file_get_contents('php://input');
    $booking_data = json_decode($json_input, true);
    
    if (!$booking_data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validar dados obrigatórios
    $required_fields = [
        'customer_name', 'customer_email', 'customer_phone',
        'service_address', 'postcode', 'service_date', 
        'service_time', 'duration_hours'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($booking_data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Buscar configurações do sistema
    $stmt = $pdo->query("
        SELECT setting_key, setting_value, setting_type 
        FROM system_settings
    ");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = $row['setting_value'];
        if ($row['setting_type'] === 'number') {
            $value = (float)$value;
        } elseif ($row['setting_type'] === 'boolean') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        $settings[$row['setting_key']] = $value;
    }
    
    // Variáveis de desconto e referral
    $discount = 0;
    $referral_user_id = null;
    $code_type = 'none';
    $applied_code = '';
    
    // SISTEMA UNIFICADO DE CÓDIGOS
    if (!empty($booking_data['unified_code'])) {
        $code = strtoupper(trim($booking_data['unified_code']));
        $applied_code = $code;
        
        // Primeiro: Tentar como REFERRAL CODE
        $stmt = $pdo->prepare("
            SELECT id, current_level_id 
            FROM referral_users 
            WHERE referral_code = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $referrer = $stmt->fetch();
        
        if ($referrer) {
            // É um REFERRAL CODE - gera comissão + desconto
            $referral_user_id = $referrer['id'];
            $code_type = 'referral';
            
            // Aplicar desconto baseado no level_id
            switch ($referrer['current_level_id']) {
                case 3: // Sapphire
                    $discount = 0.15; // 15%
                    break;
                case 2: // Tanzanite  
                    $discount = 0.10; // 10%
                    break;
                case 1: // Topaz
                default:
                    $discount = 0.05; // 5%
                    break;
            }
        } else {
            // Segundo: Tentar como PROMO CODE (tabela de cupons/promos)
            $stmt = $pdo->prepare("
                SELECT discount_percentage, discount_amount, is_active 
                FROM promo_codes 
                WHERE code = ? AND is_active = 1 
                AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1
            ");
            $stmt->execute([$code]);
            $promo = $stmt->fetch();
            
            if ($promo) {
                // É um PROMO CODE - apenas desconto, sem comissão
                $code_type = 'promo';
                $discount = $promo['discount_percentage'] ? 
                    ($promo['discount_percentage'] / 100) : 0;
            } else {
                // Código não encontrado - usar padrões para detectar tipo
                $code_type = detectCodeType($code);
                if ($code_type === 'promo') {
                    // Aplicar desconto padrão para códigos promocionais não cadastrados
                    $discount = 0.10; // 10% padrão
                }
            }
        }
    }
    
    // Calcular preços base (simplificado)
    $base_price = $settings['base_hourly_rate'] ?? 50;
    $duration = floatval($booking_data['duration_hours']);
    $subtotal = $base_price * $duration;
    
    // Aplicar desconto
    $total_before_discount = $subtotal;
    $discount_amount = $total_before_discount * $discount;
    $total_after_discount = $total_before_discount - $discount_amount;
    
    // GST
    $gst_rate = $settings['gst_rate'] ?? 0.10;
    $gst_amount = $total_after_discount * $gst_rate;
    $final_total = $total_after_discount + $gst_amount;
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Criar ou encontrar cliente
    $stmt = $pdo->prepare("
        SELECT id FROM customers 
        WHERE email = ? OR phone = ?
        LIMIT 1
    ");
    $stmt->execute([$booking_data['customer_email'], $booking_data['customer_phone']]);
    $existing_customer = $stmt->fetch();
    
    if ($existing_customer) {
        $customer_id = $existing_customer['id'];
    } else {
        // Dividir o nome
        $name_parts = explode(' ', $booking_data['customer_name'], 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
        
        // Criar novo cliente
        $stmt = $pdo->prepare("
            INSERT INTO customers (first_name, last_name, email, phone, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $first_name,
            $last_name,
            $booking_data['customer_email'],
            $booking_data['customer_phone']
        ]);
        $customer_id = $pdo->lastInsertId();
    }
    
    // Criar booking
    $booking_reference = 'BK' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("
        INSERT INTO bookings (
            customer_id, service_date, service_time, duration_hours,
            service_address, postcode, subtotal, discount_amount, gst_amount, total_amount,
            status, reference_number, unified_code, code_type, referred_by, referral_code, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $customer_id,
        $booking_data['service_date'],
        $booking_data['service_time'],
        $duration,
        $booking_data['service_address'],
        $booking_data['postcode'],
        $subtotal,
        $discount_amount,
        $gst_amount,
        $final_total,
        $booking_reference,
        $applied_code,
        $code_type,
        $referral_user_id,
        $applied_code // Salvar também em referral_code para compatibilidade
    ]);
    
    $booking_id = $pdo->lastInsertId();
    
    // Se for referral, criar registro na tabela referrals
    if ($code_type === 'referral' && $referral_user_id) {
        $commission_rate = 0.05; // 5% de comissão
        $commission_amount = $final_total * $commission_rate;
        
        $stmt = $pdo->prepare("
            INSERT INTO referrals (
                referrer_id, customer_name, customer_email, customer_phone,
                status, booking_value, commission_earned, booking_id, 
                booking_date, created_at
            ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $referral_user_id,
            $booking_data['customer_name'],
            $booking_data['customer_email'],
            $booking_data['customer_phone'],
            $final_total,
            $commission_amount,
            $booking_id,
            $booking_data['service_date']
        ]);
    }
    
    // Commit da transação
    $pdo->commit();
    
    // Resposta de sucesso
    $response = [
        'success' => true,
        'data' => [
            'booking_id' => $booking_id,
            'reference_number' => $booking_reference,
            'customer_id' => $customer_id,
            'code_info' => [
                'applied_code' => $applied_code,
                'code_type' => $code_type,
                'discount_percentage' => $discount * 100,
                'discount_amount' => $discount_amount,
                'generates_commission' => ($code_type === 'referral')
            ],
            'pricing' => [
                'subtotal' => $subtotal,
                'discount_amount' => $discount_amount,
                'gst_amount' => $gst_amount,
                'final_total' => $final_total
            ],
            'status' => 'pending',
            'next_step' => 'payment'
        ],
        'message' => 'Booking created successfully with ' . 
                    ($code_type === 'referral' ? 'referral code (commission generated)' : 
                    ($code_type === 'promo' ? 'promo code (discount applied)' : 'no code'))
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Booking creation failed',
        'message' => DEBUG ? $e->getMessage() : 'Failed to create booking. Please try again.'
    ]);
    
    error_log("Unified Booking Creation Error: " . $e->getMessage());
}

/**
 * Detecta tipo de código baseado em padrões
 */
function detectCodeType($code) {
    // Padrões para referral codes
    if (preg_match('/^(FRIEND|REF|USER|MEMBER)/i', $code)) {
        return 'referral';
    }
    
    // Padrões para promo codes
    if (preg_match('/^(SUMMER|WINTER|SPRING|FALL|SALE|PROMO|DISCOUNT|NEW|WELCOME)/i', $code)) {
        return 'promo';
    }
    
    // Códigos com muitos números tendem a ser promos
    if (preg_match('/\d{2,}/', $code)) {
        return 'promo';
    }
    
    // Por padrão, assumir referral
    return 'referral';
}

?>
