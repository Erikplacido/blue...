<?php
/**
 * API Simplificada para Teste
 */

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
    
    // Log para debug
    error_log("Booking data received: " . json_encode($booking_data));
    
    // Validar campos básicos
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
    
    // Conectar ao banco
    require_once __DIR__ . '/../../config.php';
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Processar referral_code se presente
    $referral_user_id = null;
    $discount = 0.0;
    
    if (!empty($booking_data['referral_code'])) {
        $stmt = $pdo->prepare("
            SELECT id, current_level_id 
            FROM referral_users 
            WHERE referral_code = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$booking_data['referral_code']]);
        $referrer = $stmt->fetch();
        
        if ($referrer) {
            $referral_user_id = $referrer['id'];
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
        }
    }
    
    // Calcular preços básicos
    $base_price = 35.0; // Preço por hora
    $subtotal = $base_price * floatval($booking_data['duration_hours']);
    $discount_amount = $subtotal * $discount;
    $total_after_discount = $subtotal - $discount_amount;
    $gst_amount = $total_after_discount * 0.10; // GST 10%
    $final_total = $total_after_discount + $gst_amount;
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Criar ou encontrar cliente
    $stmt = $pdo->prepare("
        SELECT id FROM customers 
        WHERE email = ? 
        LIMIT 1
    ");
    $stmt->execute([$booking_data['customer_email']]);
    $existing_customer = $stmt->fetch();
    
    if ($existing_customer) {
        $customer_id = $existing_customer['id'];
    } else {
        // Dividir o nome em primeiro e último nome
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
    
    // Gerar referência única
    $booking_reference = 'BC' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
    
    // Inserir booking
    $stmt = $pdo->prepare("
        INSERT INTO bookings (
            customer_id, service_address, postcode, service_date, service_time,
            duration_hours, subtotal, discount_amount, gst_amount, total_amount,
            booking_reference, status, referral_code, referred_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
    ");
    
    $stmt->execute([
        $customer_id,
        $booking_data['service_address'],
        $booking_data['postcode'],
        $booking_data['service_date'],
        $booking_data['service_time'],
        $booking_data['duration_hours'],
        $subtotal,
        $discount_amount,
        $gst_amount,
        $final_total,
        $booking_reference,
        $booking_data['referral_code'] ?? null,
        $referral_user_id
    ]);
    
    $booking_id = $pdo->lastInsertId();
    
    // Se há referral, criar registro na tabela referrals
    if ($referral_user_id && !empty($booking_data['referral_code'])) {
        $commission = $final_total * 0.05; // 5% de comissão
        
        $stmt = $pdo->prepare("
            INSERT INTO referrals (
                referrer_id, customer_name, customer_email, customer_phone,
                booking_id, booking_value, commission_earned, booking_date,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $referral_user_id,
            $booking_data['customer_name'],
            $booking_data['customer_email'],
            $booking_data['customer_phone'],
            $booking_id,
            $final_total,
            $commission,
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
            'pricing' => [
                'subtotal' => $subtotal,
                'discount_percentage' => $discount * 100,
                'discount_amount' => $discount_amount,
                'gst_amount' => $gst_amount,
                'final_total' => $final_total
            ],
            'referral_processed' => !empty($referral_user_id),
            'status' => 'pending',
            'next_step' => 'payment'
        ],
        'message' => 'Booking created successfully'
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Booking Creation Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Booking creation failed',
        'message' => $e->getMessage()
    ]);
}
?>
