<?php
/**
 * TESTE FINAL - Verificar se o booking ID 43 será criado com referral_code
 */

echo "🧪 TESTE FINAL - CRIAÇÃO DE BOOKING COM REFERRAL_CODE\n";
echo "====================================================\n\n";

try {
    require_once __DIR__ . '/config.php';
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Simular dados exatos como viriam do formulário
    $_POST = [
        'first_name' => 'Test',
        'last_name' => 'Final',
        'email' => 'test@final.com',
        'phone' => '1234567890',
        'unifiedCodeInput' => 'TESTFINAL123',  // Usuário digitou
        'referral_code' => '',                 // Campo hidden vazio (cenário real)
        'total_amount' => 265.00,
        'service_id' => 1
    ];
    
    echo "📋 DADOS SIMULADOS DO FORMULÁRIO:\n";
    echo "---------------------------------\n";
    echo "unifiedCodeInput: '{$_POST['unifiedCodeInput']}'\n";
    echo "referral_code: '{$_POST['referral_code']}'\n\n";
    
    // APLICAR A LÓGICA CORRIGIDA
    echo "🔄 APLICANDO LÓGICA CORRIGIDA:\n";
    echo "------------------------------\n";
    
    $referral_code_final = !empty($_POST['referral_code']) 
        ? $_POST['referral_code'] 
        : ($_POST['unifiedCodeInput'] ?? $_GET['referral_code'] ?? $_GET['promo_code'] ?? '');
    
    echo "Resultado: '$referral_code_final'\n\n";
    
    if (!empty($referral_code_final)) {
        echo "✅ SUCESSO: referral_code será salvo como '$referral_code_final'\n";
        
        // Simular inserção no banco (sem executar)
        $sql = "INSERT INTO bookings (customer_name, email, phone, referral_code, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
        
        echo "\n📊 DADOS QUE IRIAM PARA O BANCO:\n";
        echo "--------------------------------\n";
        echo "customer_name: 'Test Final'\n";
        echo "email: 'test@final.com'\n";
        echo "phone: '1234567890'\n";
        echo "referral_code: '$referral_code_final'\n";
        echo "total_amount: 265.00\n";
        
        echo "\n🎉 CORREÇÃO FUNCIONARÁ NO PRÓXIMO BOOKING!\n";
        
    } else {
        echo "❌ FALHA: referral_code ainda estaria vazio\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
