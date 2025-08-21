<?php
/**
 * API: Criação de Booking Dinâmica
 * Endpoint: /api/booking/create-dynamic
 * Processa bookings usando dados dinâmicos do banco
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
    
    // Calcular preços
    $base_price = $settings['base_cleaning_price'] ?? 45.00;
    $duration = (float)$booking_data['duration_hours'];
    $subtotal = $base_price * $duration;
    
    // Adicionar extras se selecionados
    $extras_cost = 0;
    if (!empty($booking_data['selected_extras']) && is_array($booking_data['selected_extras'])) {
        $extras_ids = array_map('intval', $booking_data['selected_extras']);
        if (!empty($extras_ids)) {
            $placeholders = str_repeat('?,', count($extras_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT id, name, price, price_type 
                FROM service_extras 
                WHERE id IN ($placeholders) AND is_active = TRUE
            ");
            $stmt->execute($extras_ids);
            
            while ($extra = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($extra['price_type'] === 'per_hour') {
                    $extras_cost += $extra['price'] * $duration;
                } else {
                    $extras_cost += $extra['price'];
                }
            }
        }
    }
    
    // Calcular desconto se código de referral foi usado
    $discount = 0;
    $referral_user_id = null;
    if (!empty($booking_data['referral_code'])) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.current_tier 
            FROM users u 
            WHERE u.referral_code = ? AND u.is_active = TRUE
        ");
        $stmt->execute([$booking_data['referral_code']]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($referrer) {
            $referral_user_id = $referrer['id'];
            // Aplicar desconto baseado no tier
            switch ($referrer['current_tier']) {
                case 'blue_sapphire':
                    $discount = 0.15; // 15%
                    break;
                case 'blue_tanzanite':
                    $discount = 0.10; // 10%
                    break;
                case 'blue_topaz':
                default:
                    $discount = 0.05; // 5%
                    break;
            }
        }
    }
    
    // Calcular totais
    $total_before_discount = $subtotal + $extras_cost;
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
    $existing_customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_customer) {
        $customer_id = $existing_customer['id'];
        
        // Atualizar dados do cliente
        $stmt = $pdo->prepare("
            UPDATE customers SET 
                name = ?, phone = ?, address = ?, postcode = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $booking_data['customer_name'],
            $booking_data['customer_phone'],
            $booking_data['service_address'],
            $booking_data['postcode'],
            $customer_id
        ]);
    } else {
        // Criar novo cliente
        $stmt = $pdo->prepare("
            INSERT INTO customers 
            (name, email, phone, address, postcode, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $booking_data['customer_name'],
            $booking_data['customer_email'],
            $booking_data['customer_phone'],
            $booking_data['service_address'],
            $booking_data['postcode']
        ]);
        $customer_id = $pdo->lastInsertId();
    }
    
    // Criar booking
    $booking_reference = 'BC' . date('Ymd') . str_pad($customer_id, 4, '0', STR_PAD_LEFT) . mt_rand(100, 999);
    
    $stmt = $pdo->prepare("
        INSERT INTO bookings 
        (reference_number, customer_id, service_date, service_time, duration_hours,
         subtotal, extras_cost, discount_percentage, discount_amount, gst_amount, 
         total_amount, status, referral_code, referral_user_id, special_instructions, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $booking_reference,
        $customer_id,
        $booking_data['service_date'],
        $booking_data['service_time'],
        $duration,
        $subtotal,
        $extras_cost,
        $discount * 100, // converter para porcentagem
        $discount_amount,
        $gst_amount,
        $final_total,
        $booking_data['referral_code'] ?? null,
        $referral_user_id,
        $booking_data['special_instructions'] ?? null
    ]);
    
    $booking_id = $pdo->lastInsertId();
    
    // Salvar extras selecionados
    if (!empty($booking_data['selected_extras'])) {
        $stmt = $pdo->prepare("
            INSERT INTO booking_extras (booking_id, extra_id, price_paid)
            SELECT ?, id, 
                   CASE 
                       WHEN price_type = 'per_hour' THEN price * ?
                       ELSE price 
                   END
            FROM service_extras 
            WHERE id = ? AND is_active = TRUE
        ");
        
        foreach ($extras_ids as $extra_id) {
            $stmt->execute([$booking_id, $duration, $extra_id]);
        }
    }
    
    // Salvar preferências selecionadas
    if (!empty($booking_data['selected_preferences']) && is_array($booking_data['selected_preferences'])) {
        $preferences_ids = array_map('intval', $booking_data['selected_preferences']);
        $stmt = $pdo->prepare("
            INSERT INTO booking_preferences (booking_id, preference_id)
            VALUES (?, ?)
        ");
        
        foreach ($preferences_ids as $preference_id) {
            $stmt->execute([$booking_id, $preference_id]);
        }
    }
    
    // Log da atividade
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, activity, details, created_at)
        VALUES (?, 'booking_created', ?, NOW())
    ");
    $stmt->execute([
        $customer_id,
        json_encode([
            'booking_id' => $booking_id,
            'reference' => $booking_reference,
            'amount' => $final_total,
            'referral_used' => !empty($referral_user_id)
        ])
    ]);
    
    // Commit da transação
    $pdo->commit();
    
    // Resposta de sucesso
    $response = [
        'success' => true,
        'data' => [
            'booking_id' => $booking_id,
            'reference_number' => $booking_reference,
            'customer_id' => $customer_id,
            'pricing' => [
                'subtotal' => $subtotal,
                'extras_cost' => $extras_cost,
                'discount_percentage' => $discount * 100,
                'discount_amount' => $discount_amount,
                'gst_amount' => $gst_amount,
                'final_total' => $final_total
            ],
            'status' => 'pending',
            'next_step' => 'payment'
        ],
        'message' => 'Booking created successfully'
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
    
    error_log("Booking Creation Error: " . $e->getMessage());
}
?>
