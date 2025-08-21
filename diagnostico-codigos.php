<?php
/**
 * FERRAMENTA DE TESTE SIMPLES - DIAGNÓSTICO CÓDIGOS
 * Execute este arquivo para testar o fluxo completo
 */

echo "<h1>🔍 DIAGNÓSTICO COMPLETO - CÓDIGOS REFERRAL</h1>";

// 1. TESTAR CONEXÃO COM BANCO
try {
    require_once 'config.php';
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conexão com banco: OK<br>";
} catch (Exception $e) {
    echo "❌ Conexão com banco: FALHOU - " . $e->getMessage() . "<br>";
    exit;
}

// 2. VERIFICAR ESTRUTURA DA TABELA BOOKINGS
echo "<h2>📊 ESTRUTURA DA TABELA BOOKINGS</h2>";
$stmt = $pdo->query("DESCRIBE bookings");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasReferralCode = false;
foreach ($columns as $column) {
    if ($column['Field'] === 'referral_code') {
        $hasReferralCode = true;
        echo "✅ Campo referral_code: EXISTS - Tipo: {$column['Type']}<br>";
    }
}

if (!$hasReferralCode) {
    echo "❌ Campo referral_code: NÃO EXISTE na tabela bookings<br>";
}

// 3. VERIFICAR CÓDIGOS DE TESTE DISPONÍVEIS
echo "<h2>🎯 CÓDIGOS DE TESTE DISPONÍVEIS</h2>";

// Referral codes
$stmt = $pdo->query("SELECT referral_code, id, current_level_id FROM referral_users WHERE is_active = 1 LIMIT 3");
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<strong>REFERRAL CODES:</strong><br>";
foreach ($referrals as $ref) {
    echo "📋 Código: <strong>{$ref['referral_code']}</strong> (Level: {$ref['current_level_id']})<br>";
}

// Promo codes  
$stmt = $pdo->query("SELECT code, discount_percentage, discount_amount FROM coupons WHERE is_active = 1 LIMIT 3");
$promos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<strong>PROMO CODES:</strong><br>";
foreach ($promos as $promo) {
    echo "🏷️ Código: <strong>{$promo['code']}</strong> ";
    if ($promo['discount_percentage']) {
        echo "({$promo['discount_percentage']}% off)";
    } elseif ($promo['discount_amount']) {
        echo "(\$" . $promo['discount_amount'] . " off)";
    }
    echo "<br>";
}

// 4. TESTAR ENDPOINT DE VALIDAÇÃO
echo "<h2>🧪 TESTE DO ENDPOINT DE VALIDAÇÃO</h2>";

$testCode = $promos[0]['code'] ?? 'ERIK42';
echo "Testando código: <strong>$testCode</strong><br>";

// Simular POST request
$postData = json_encode(['code' => $testCode]);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $postData
    ]
]);

$url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/validate-unified-code.php';
$result = @file_get_contents($url, false, $context);

if ($result) {
    $response = json_decode($result, true);
    if ($response['success']) {
        echo "✅ Endpoint funcionando: {$response['message']}<br>";
        echo "📋 Tipo: {$response['type']}, Desconto: {$response['discount_percentage']}%<br>";
    } else {
        echo "❌ Endpoint respondeu com erro: {$response['message']}<br>";
    }
} else {
    echo "❌ Endpoint não acessível em: $url<br>";
}

// 5. VERIFICAR ÚLTIMOS BOOKINGS
echo "<h2>📋 ÚLTIMOS 5 BOOKINGS</h2>";
$stmt = $pdo->query("
    SELECT id, booking_code, referral_code, scheduled_date, created_at 
    FROM bookings 
    ORDER BY id DESC 
    LIMIT 5
");
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Booking Code</th><th>Referral Code</th><th>Scheduled Date</th><th>Created</th></tr>";
foreach ($bookings as $booking) {
    $referralCode = $booking['referral_code'] ?: '(empty)';
    $scheduledDate = $booking['scheduled_date'] ?: '(empty)';
    echo "<tr>";
    echo "<td>{$booking['id']}</td>";
    echo "<td>{$booking['booking_code']}</td>";
    echo "<td><strong>$referralCode</strong></td>";
    echo "<td>$scheduledDate</td>";
    echo "<td>{$booking['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>🎯 RESUMO DO DIAGNÓSTICO</h2>";
echo "✅ Para testar, use um dos códigos listados acima<br>";
echo "✅ Abra o Console do navegador (F12) para ver logs detalhados<br>";
echo "✅ Faça um booking completo e verifique se o referral_code é preenchido<br>";
echo "✅ Se o problema persistir, os logs do console mostrarão onde está falhando<br>";
?>
