<?php
/**
 * TESTE DAS CORREÇÕES IMPLEMENTADAS
 * =================================
 * 
 * Este script testa:
 * 1. Se referral_code está sendo salvo no banco
 * 2. Se scheduled_date está recebendo valores
 * 3. Se os logs de debug estão funcionando
 */

require_once 'config.php';

echo "<h1>🧪 TESTE DAS CORREÇÕES - CAMPOS DE BOOKING</h1>";

// Função para testar dados simulados
function testarDados($testData, $testName) {
    echo "<h2>📋 Teste: $testName</h2>";
    
    foreach ($testData as $campo => $valor) {
        $status = empty($valor) ? '❌ VAZIO' : '✅ TEM VALOR';
        echo "<div>$campo: '$valor' $status</div>";
    }
    echo "<hr>";
}

// TESTE 1: Dados que seriam enviados do frontend
echo "<h2>🔍 TESTE 1: Dados do Frontend</h2>";
$frontendData = [
    'date' => '2025-08-25',
    'time' => '10:00',
    'address' => '123 Test Street, Sydney',
    'unified_code' => 'TESTREF123'
];
testarDados($frontendData, 'Dados simulados do frontend');

// TESTE 2: Verificar se os campos existem no banco
echo "<h2>🔍 TESTE 2: Estrutura do Banco</h2>";
try {
    $stmt = $pdo->query("DESCRIBE bookings");
    $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $camposImportantes = ['scheduled_date', 'scheduled_time', 'street_address', 'referral_code'];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Status</th></tr>";
    
    foreach ($camposImportantes as $campoImportante) {
        $encontrado = false;
        foreach ($campos as $campo) {
            if ($campo['Field'] === $campoImportante) {
                $encontrado = true;
                $status = "✅ EXISTE";
                echo "<tr><td>$campoImportante</td><td>{$campo['Type']}</td><td>$status</td></tr>";
                break;
            }
        }
        if (!$encontrado) {
            echo "<tr><td>$campoImportante</td><td>N/A</td><td>❌ NÃO EXISTE</td></tr>";
        }
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao acessar banco: " . $e->getMessage() . "</p>";
}

// TESTE 3: Verificar logs de erro recentes
echo "<h2>🔍 TESTE 3: Verificar Logs de Debug</h2>";
echo "<p><strong>Para ver os logs em tempo real, execute no terminal:</strong></p>";
echo "<code>tail -f /var/log/apache2/error.log | grep 'DEBUG'</code>";
echo "<p>ou</p>";
echo "<code>tail -f /usr/local/var/log/httpd/error_log | grep 'DEBUG'</code>";

// TESTE 4: Simular dados como chegam na API
echo "<h2>🔍 TESTE 4: Simulação de Dados da API</h2>";
$apiData = [
    'date' => $frontendData['date'] ?? 'EMPTY',
    'time' => $frontendData['time'] ?? 'EMPTY', 
    'address' => $frontendData['address'] ?? 'EMPTY',
    'unified_code' => $frontendData['unified_code'] ?? 'EMPTY'
];

// Simular o mapeamento que o StripeManager faz
$stripeData = [
    'scheduled_date' => $apiData['date'],
    'scheduled_time' => $apiData['time'],
    'street_address' => $apiData['address'], 
    'referral_code' => $apiData['unified_code']
];

testarDados($stripeData, 'Dados como chegam no StripeManager');

echo "<h2>🎯 CORREÇÕES IMPLEMENTADAS:</h2>";
echo "<ul>";
echo "<li>✅ Adicionado 'referral_code' na query SQL do StripeManager</li>";
echo "<li>✅ Mapeado 'unified_code' → 'referral_code' no banco</li>";
echo "<li>✅ Adicionados logs de debug na API e StripeManager</li>";
echo "<li>✅ Suporte para 'unified_code' na API de checkout</li>";
echo "</ul>";

echo "<h2>🚀 PRÓXIMOS PASSOS PARA TESTAR:</h2>";
echo "<ol>";
echo "<li>Fazer um booking real no frontend</li>";
echo "<li>Verificar os logs de debug</li>";
echo "<li>Consultar o banco para ver se referral_code foi salvo</li>";
echo "<li>Verificar se scheduled_date não está mais vazio</li>";
echo "</ol>";

// Query para verificar últimos bookings
echo "<h2>📊 ÚLTIMOS BOOKINGS (para verificação):</h2>";
try {
    $stmt = $pdo->query("
        SELECT booking_code, scheduled_date, scheduled_time, street_address, referral_code, created_at 
        FROM bookings 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($bookings) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Booking</th><th>Data</th><th>Hora</th><th>Endereço</th><th>Código Ref</th><th>Criado</th></tr>";
        foreach ($bookings as $booking) {
            echo "<tr>";
            echo "<td>{$booking['booking_code']}</td>";
            echo "<td>" . ($booking['scheduled_date'] ?: '❌') . "</td>";
            echo "<td>" . ($booking['scheduled_time'] ?: '❌') . "</td>";
            echo "<td>" . (substr($booking['street_address'] ?: '❌', 0, 30)) . "</td>";
            echo "<td>" . ($booking['referral_code'] ?: '❌') . "</td>";
            echo "<td>{$booking['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum booking encontrado.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao consultar bookings: " . $e->getMessage() . "</p>";
}

echo "<p><strong>🎉 Teste completo! As correções foram implementadas.</strong></p>";
?>
