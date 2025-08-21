<?php
/**
 * VERIFICAÇÃO COMPLETA DA TABELA BOOKINGS
 */

require_once 'config.php';

echo "<h1>🔍 VERIFICAÇÃO COMPLETA DA TABELA BOOKINGS</h1>";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conectado ao banco: " . DB_NAME . "<br><br>";
} catch (Exception $e) {
    echo "❌ ERRO DE CONEXÃO: " . $e->getMessage();
    exit;
}

// 1. VERIFICAR ESTRUTURA DA TABELA
echo "<h2>📋 ESTRUTURA DA TABELA BOOKINGS</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

$stmt = $pdo->query("DESCRIBE bookings");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasReferralCode = false;
$hasScheduledDate = false;
$hasScheduledTime = false;
$hasStreetAddress = false;

foreach ($columns as $column) {
    echo "<tr>";
    echo "<td><strong>{$column['Field']}</strong></td>";
    echo "<td>{$column['Type']}</td>";
    echo "<td>{$column['Null']}</td>";
    echo "<td>{$column['Key']}</td>";
    echo "<td>{$column['Default']}</td>";
    echo "<td>{$column['Extra']}</td>";
    echo "</tr>";
    
    // Verificar campos críticos
    if ($column['Field'] === 'referral_code') $hasReferralCode = true;
    if ($column['Field'] === 'scheduled_date') $hasScheduledDate = true;
    if ($column['Field'] === 'scheduled_time') $hasScheduledTime = true;
    if ($column['Field'] === 'street_address') $hasStreetAddress = true;
}

echo "</table><br>";

// 2. VERIFICAR CAMPOS CRÍTICOS
echo "<h2>✅ VERIFICAÇÃO DOS CAMPOS CRÍTICOS</h2>";
echo "🎯 referral_code: " . ($hasReferralCode ? "✅ EXISTE" : "❌ NÃO EXISTE") . "<br>";
echo "📅 scheduled_date: " . ($hasScheduledDate ? "✅ EXISTE" : "❌ NÃO EXISTE") . "<br>";
echo "⏰ scheduled_time: " . ($hasScheduledTime ? "✅ EXISTE" : "❌ NÃO EXISTE") . "<br>";
echo "📍 street_address: " . ($hasStreetAddress ? "✅ EXISTE" : "❌ NÃO EXISTE") . "<br><br>";

// 3. VERIFICAR ÚLTIMOS 10 BOOKINGS
echo "<h2>📊 ÚLTIMOS 10 BOOKINGS</h2>";
$sql = "SELECT 
    id, 
    booking_code, 
    referral_code, 
    scheduled_date, 
    scheduled_time, 
    street_address,
    customer_name,
    created_at 
FROM bookings 
ORDER BY id DESC 
LIMIT 10";

$stmt = $pdo->query($sql);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($bookings) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>Booking Code</th><th>Referral Code</th><th>Scheduled Date</th>";
    echo "<th>Scheduled Time</th><th>Street Address</th><th>Customer</th><th>Created</th>";
    echo "</tr>";
    
    foreach ($bookings as $booking) {
        $referralCode = $booking['referral_code'] ?: '(vazio)';
        $scheduledDate = $booking['scheduled_date'] ?: '(vazio)';
        $scheduledTime = $booking['scheduled_time'] ?: '(vazio)';
        $streetAddress = $booking['street_address'] ?: '(vazio)';
        
        // Destacar campos vazios
        $referralStyle = $booking['referral_code'] ? '' : 'color: red; font-weight: bold;';
        $dateStyle = $booking['scheduled_date'] && $booking['scheduled_date'] !== '0000-00-00' ? '' : 'color: red; font-weight: bold;';
        $timeStyle = $booking['scheduled_time'] ? '' : 'color: red; font-weight: bold;';
        $addressStyle = $booking['street_address'] ? '' : 'color: red; font-weight: bold;';
        
        echo "<tr>";
        echo "<td>{$booking['id']}</td>";
        echo "<td>{$booking['booking_code']}</td>";
        echo "<td style='$referralStyle'>$referralCode</td>";
        echo "<td style='$dateStyle'>$scheduledDate</td>";
        echo "<td style='$timeStyle'>$scheduledTime</td>";
        echo "<td style='$addressStyle'>$streetAddress</td>";
        echo "<td>" . substr($booking['customer_name'], 0, 20) . "</td>";
        echo "<td>" . substr($booking['created_at'], 0, 16) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Nenhum booking encontrado na tabela.";
}

// 4. CONTAR BOOKINGS COM CAMPOS VAZIOS
echo "<h2>📈 ESTATÍSTICAS DOS CAMPOS</h2>";

$stats = [
    'total_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'empty_referral_code' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE referral_code IS NULL OR referral_code = ''")->fetchColumn(),
    'empty_scheduled_date' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE scheduled_date IS NULL OR scheduled_date = '' OR scheduled_date = '0000-00-00'")->fetchColumn(),
    'empty_scheduled_time' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE scheduled_time IS NULL OR scheduled_time = ''")->fetchColumn(),
    'empty_street_address' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE street_address IS NULL OR street_address = ''")->fetchColumn(),
];

echo "📊 Total de bookings: <strong>" . $stats['total_bookings'] . "</strong><br>";
echo "❌ referral_code vazio: <strong>" . $stats['empty_referral_code'] . "</strong> (" . ($stats['total_bookings'] > 0 ? round($stats['empty_referral_code']/$stats['total_bookings']*100, 1) : 0) . "%)<br>";
echo "❌ scheduled_date vazio: <strong>" . $stats['empty_scheduled_date'] . "</strong> (" . ($stats['total_bookings'] > 0 ? round($stats['empty_scheduled_date']/$stats['total_bookings']*100, 1) : 0) . "%)<br>";
echo "❌ scheduled_time vazio: <strong>" . $stats['empty_scheduled_time'] . "</strong> (" . ($stats['total_bookings'] > 0 ? round($stats['empty_scheduled_time']/$stats['total_bookings']*100, 1) : 0) . "%)<br>";
echo "❌ street_address vazio: <strong>" . $stats['empty_street_address'] . "</strong> (" . ($stats['total_bookings'] > 0 ? round($stats['empty_street_address']/$stats['total_bookings']*100, 1) : 0) . "%)<br>";

echo "<br><h2>🎯 DIAGNÓSTICO</h2>";
if ($stats['empty_referral_code'] > 0) {
    echo "⚠️ <strong>Problema confirmado:</strong> " . $stats['empty_referral_code'] . " bookings com referral_code vazio<br>";
}
if ($stats['empty_scheduled_date'] > 0) {
    echo "⚠️ <strong>Problema confirmado:</strong> " . $stats['empty_scheduled_date'] . " bookings com scheduled_date vazio<br>";
}
if ($stats['empty_scheduled_time'] == 0) {
    echo "✅ <strong>Campo OK:</strong> scheduled_time está sendo preenchido corretamente<br>";
}
if ($stats['empty_street_address'] == 0) {
    echo "✅ <strong>Campo OK:</strong> street_address está sendo preenchido corretamente<br>";
}

?>
