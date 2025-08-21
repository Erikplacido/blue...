<?php
/**
 * TESTE REAL - Simula EXATAMENTE como o booking3.php deveria enviar dados
 */

echo "🧪 TESTE REAL - Simulação completa do fluxo\n\n";

// =====================================
// DADOS COMO O FRONTEND DEVERIA ENVIAR
// =====================================
echo "📤 DADOS DO FRONTEND (como booking3.php deveria enviar):\n";

$postData = [
    // Dados pessoais
    'name' => 'João Silva',
    'email' => 'joao@teste.com', 
    'phone' => '0412345678',
    
    // Endereço
    'address' => '123 Test Street, Sydney NSW 2000',
    'suburb' => 'Sydney',
    'postcode' => '2000',
    
    // Agendamento
    'date' => '2024-12-25',  // Formato que a API espera
    'time' => '10:00:00',    // Formato que a API espera
    
    // Código de referral
    'referral_code' => 'TESTE123',
    
    // Outros
    'service_id' => '2',
    'recurrence' => 'one-time',
    'total' => '265.00',
    'special_requests' => ''
];

print_r($postData);

// =====================================
// SIMULAR PROCESSAMENTO DA API
// =====================================
echo "\n🔄 PROCESSAMENTO DA API (stripe-checkout-unified-final.php):\n";

$bookingData = [
    'service_id' => $postData['service_id'] ?? '2',
    'name' => trim($postData['name']),
    'email' => filter_var(trim($postData['email']), FILTER_VALIDATE_EMAIL),
    'phone' => trim($postData['phone']),
    'address' => trim($postData['address']),
    'suburb' => trim($postData['suburb']),
    'postcode' => trim($postData['postcode']),
    'date' => $postData['date'],
    'time' => $postData['time'],
    'recurrence' => $postData['recurrence'] ?? 'one-time',
    'extras' => $postData['extras'] ?? [],
    'discount_amount' => floatval($postData['discount_amount'] ?? 0),
    'referral_code' => $postData['referral_code'] ?? null,
    'special_requests' => $postData['special_requests'] ?? '',
    'frontend_total' => floatval($postData['total'] ?? 0)
];

print_r($bookingData);

// =====================================
// SIMULAR STRIPEMANAGER
// =====================================
echo "\n💳 STRIPEMANAGER - Parâmetros para banco:\n";

$dbParams = [
    ':booking_code' => 'BCS-TEST123456',
    ':service_id' => $bookingData['service_id'] ?? '2',
    ':customer_name' => $bookingData['name'] ?? 'Unknown',
    ':customer_email' => $bookingData['email'] ?? 'unknown@email.com',
    ':customer_phone' => $bookingData['phone'] ?? '',
    ':address' => $bookingData['address'] ?? '',
    ':street_address' => $bookingData['address'] ?? '',
    ':suburb' => $bookingData['suburb'] ?? '',
    ':postcode' => $bookingData['postcode'] ?? '',
    ':scheduled_date' => $bookingData['date'] ?? '',
    ':scheduled_time' => $bookingData['time'] ?? '',
    ':special_instructions' => $bookingData['special_requests'] ?? '',
    ':base_price' => 265.00,
    ':extras_price' => 0.00,
    ':discount_amount' => 0.00,
    ':total_amount' => 265.00,
    ':stripe_session_id' => 'cs_test_fake123',
    ':status' => 'pending',
    ':referral_code' => $bookingData['referral_code'] ?? ''
];

foreach ($dbParams as $param => $value) {
    echo "$param => " . ($value ?: '[EMPTY]') . "\n";
}

// =====================================
// TESTAR NO BANCO REAL
// =====================================
echo "\n🗃️ TESTE NO BANCO REAL:\n";

try {
    require_once 'config.php';
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL correto baseado na estrutura real da tabela
    $sql = "INSERT INTO bookings (
                booking_code, service_id, customer_name, customer_email, 
                customer_phone, address, street_address, suburb, postcode, 
                scheduled_date, scheduled_time, special_instructions,
                base_price, extras_price, discount_amount, total_amount, 
                stripe_session_id, status, referral_code,
                duration_minutes, gst_amount, state
            ) VALUES (
                :booking_code, :service_id, :customer_name, :customer_email,
                :customer_phone, :address, :street_address, :suburb, :postcode,
                :scheduled_date, :scheduled_time, :special_instructions,
                :base_price, :extras_price, :discount_amount, :total_amount,
                :stripe_session_id, :status, :referral_code,
                120, 0.00, 'NSW'
            )";
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute($dbParams);
    
    if ($resultado) {
        $bookingId = $pdo->lastInsertId();
        echo "✅ SUCESSO! Booking inserido com ID: $bookingId\n";
        
        // Verificar o que foi salvo
        $verificacao = $pdo->prepare("SELECT referral_code, scheduled_date, scheduled_time, street_address FROM bookings WHERE id = ?");
        $verificacao->execute([$bookingId]);
        $dadosSalvos = $verificacao->fetch(PDO::FETCH_ASSOC);
        
        echo "\n🔍 CAMPOS CRÍTICOS SALVOS:\n";
        echo "- referral_code: '" . ($dadosSalvos['referral_code'] ?: '[EMPTY]') . "'\n";
        echo "- scheduled_date: '" . ($dadosSalvos['scheduled_date'] ?: '[EMPTY]') . "'\n"; 
        echo "- scheduled_time: '" . ($dadosSalvos['scheduled_time'] ?: '[EMPTY]') . "'\n";
        echo "- street_address: '" . ($dadosSalvos['street_address'] ?: '[EMPTY]') . "'\n";
        
        // Verificar se referral_code foi salvo
        if ($dadosSalvos['referral_code'] === 'TESTE123') {
            echo "\n🎉 REFERRAL_CODE FUNCIONOU PERFEITAMENTE!\n";
        } else {
            echo "\n❌ REFERRAL_CODE AINDA NÃO FUNCIONOU!\n";
        }
        
    } else {
        echo "❌ Falha na inserção\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
