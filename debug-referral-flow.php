<?php
/**
 * DEBUG ULTRA DETALHADO - Simular exatamente o que acontece no booking
 */

echo "🔍 DEBUG ULTRA DETALHADO - FLUXO COMPLETO\n";
echo "==========================================\n\n";

// Simular dados POST como se viessem do formulário
$_POST = [
    'first_name' => 'Test',
    'last_name' => 'User', 
    'email' => 'test@test.com',
    'phone' => '1234567890',
    'address' => 'Test Address',
    'service_id' => '1',
    'booking_date' => '2025-08-22',
    'booking_time' => '10:00',
    'recurrence' => 'one-time',
    'time_window' => '10:00',
    'total_amount' => '265.00',
    
    // CAMPOS DE CÓDIGOS - SIMULANDO DIFERENTES CENÁRIOS
    'unifiedCodeInput' => 'TEST123',  // Usuário digitou código
    'referral_code' => '',            // Campo hidden vazio (problema!)
    'code_type' => 'auto'
];

$_GET = [
    'referral_code' => 'GETCODE'  // Também testar GET
];

echo "📋 SIMULAÇÃO 1: $_POST contents\n";
echo "--------------------------------\n";
foreach ($_POST as $key => $value) {
    echo "$key: '$value'\n";
}

echo "\n📋 SIMULAÇÃO 2: $_GET contents\n";
echo "------------------------------\n";
foreach ($_GET as $key => $value) {
    echo "$key: '$value'\n";
}

// TESTAR A NOVA LÓGICA DE COLETA
echo "\n🔍 TESTANDO NOVA LÓGICA DE COLETA\n";
echo "---------------------------------\n";

$referral_code_collected = !empty($_POST['referral_code']) 
    ? $_POST['referral_code'] 
    : ($_POST['unifiedCodeInput'] ?? $_GET['referral_code'] ?? $_GET['promo_code'] ?? '');

echo "Resultado da coleta: '$referral_code_collected'\n";

if (empty($referral_code_collected)) {
    echo "❌ PROBLEMA: Código coletado está VAZIO!\n";
    
    // Analisar cada etapa
    echo "\n🔍 ANÁLISE DETALHADA:\n";
    echo "---------------------\n";
    echo "1. \$_POST['referral_code']: '" . ($_POST['referral_code'] ?? 'NULL') . "'\n";
    echo "2. \$_POST['unifiedCodeInput']: '" . ($_POST['unifiedCodeInput'] ?? 'NULL') . "'\n";
    echo "3. \$_GET['referral_code']: '" . ($_GET['referral_code'] ?? 'NULL') . "'\n";
    echo "4. \$_GET['promo_code']: '" . ($_GET['promo_code'] ?? 'NULL') . "'\n";
    
    echo "\n💡 PROBLEMA IDENTIFICADO:\n";
    if (!empty($_POST['unifiedCodeInput'])) {
        echo "✅ unifiedCodeInput TEM valor: '{$_POST['unifiedCodeInput']}'\n";
        echo "❌ MAS referral_code está vazio: '{$_POST['referral_code']}'\n";
        echo "🎯 SOLUÇÃO: O JavaScript não está atualizando o campo hidden!\n";
    }
    
} else {
    echo "✅ Código coletado com sucesso: '$referral_code_collected'\n";
}

// Testar se entraria no banco
echo "\n📊 DADOS PARA BANCO:\n";
echo "--------------------\n";
$booking_data = [
    'customer_name' => trim(($_POST['first_name'] ?? '') . ' ' . ($_POST['last_name'] ?? '')),
    'email' => $_POST['email'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'referral_code' => $referral_code_collected,  // ESTE É O CAMPO CRÍTICO!
    'total_amount' => $_POST['total_amount'] ?? 0,
    'status' => 'pending'
];

foreach ($booking_data as $key => $value) {
    echo "$key: '$value'\n";
}

if (empty($booking_data['referral_code'])) {
    echo "\n❌ CONFIRMADO: referral_code iria para o banco VAZIO!\n";
} else {
    echo "\n✅ referral_code iria para o banco: '{$booking_data['referral_code']}'\n";
}
?>
