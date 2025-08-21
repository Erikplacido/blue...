<?php
/**
 * DIAGNÓSTICO ESPECÍFICO DO BOOKING ID 36
 * =======================================
 */

require_once 'config.php';

echo "<h1>🔍 DIAGNÓSTICO: Booking ID 36</h1>";

// 1. Dados do booking atual
echo "<h2>📊 DADOS DO BOOKING ID 36:</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM bookings WHERE id = 36");
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        foreach ($booking as $campo => $valor) {
            $status = empty($valor) && $valor !== '0' ? '❌ VAZIO' : '✅ TEM VALOR';
            $cor = empty($valor) && $valor !== '0' ? 'color: red;' : 'color: green;';
            echo "<tr><td><strong>$campo</strong></td><td style='$cor'>'" . htmlspecialchars($valor) . "'</td><td>$status</td></tr>";
        }
        echo "</table>";
        
        // Análise específica dos campos problemáticos
        echo "<h3>🔍 ANÁLISE DOS CAMPOS PROBLEMÁTICOS:</h3>";
        echo "<ul>";
        echo "<li><strong>referral_code:</strong> '" . $booking['referral_code'] . "' (deveria ser 'ERIK42')</li>";
        echo "<li><strong>scheduled_date:</strong> '" . $booking['scheduled_date'] . "' (deveria ter uma data válida)</li>";
        echo "<li><strong>scheduled_time:</strong> '" . $booking['scheduled_time'] . "' ✅ (está funcionando)</li>";
        echo "<li><strong>street_address:</strong> '" . $booking['street_address'] . "' ✅ (está funcionando)</li>";
        echo "</ul>";
        
    } else {
        echo "<p style='color: red;'>❌ Booking ID 36 não encontrado!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

// 2. Verificar cupom ERIK42
echo "<h2>🎫 CUPOM ERIK42:</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM coupons WHERE code = 'ERIK42'");
    $cupom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cupom) {
        echo "<p>✅ Cupom encontrado na tabela 'coupons':</p>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$cupom['id']}</li>";
        echo "<li><strong>Code:</strong> {$cupom['code']}</li>";
        echo "<li><strong>Name:</strong> {$cupom['name']}</li>";
        echo "<li><strong>Type:</strong> {$cupom['type']}</li>";
        echo "<li><strong>Value:</strong> {$cupom['value']}%</li>";
        echo "<li><strong>Status:</strong> " . ($cupom['is_active'] ? 'Ativo' : 'Inativo') . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ Cupom ERIK42 não encontrado na tabela coupons!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao verificar cupom: " . $e->getMessage() . "</p>";
}

// 3. Verificar nas outras tabelas de códigos
echo "<h2>🔍 VERIFICAÇÃO EM OUTRAS TABELAS:</h2>";

$tabelas = ['promo_codes', 'referral_users'];
foreach ($tabelas as $tabela) {
    try {
        // Verificar se a tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "<h3>Tabela: $tabela</h3>";
            
            // Procurar por ERIK42
            if ($tabela === 'referral_users') {
                $stmt = $pdo->query("SELECT * FROM $tabela WHERE referral_code = 'ERIK42'");
            } else {
                $stmt = $pdo->query("SELECT * FROM $tabela WHERE code = 'ERIK42'");
            }
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($resultado) {
                echo "<p>✅ ERIK42 encontrado em $tabela!</p>";
                print_r($resultado);
            } else {
                echo "<p>ℹ️ ERIK42 não encontrado em $tabela</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p>⚠️ Tabela $tabela não existe ou erro: " . $e->getMessage() . "</p>";
    }
}

// 4. TEORIAS DO PROBLEMA
echo "<h2>🧠 TEORIAS DO QUE PODE TER ACONTECIDO:</h2>";
echo "<ol>";
echo "<li><strong>API Errada Chamada:</strong> O frontend pode estar chamando uma API diferente da que corrigimos</li>";
echo "<li><strong>Mapeamento de Campos:</strong> O cupom pode estar sendo buscado na tabela 'coupons', mas o código está procurando em 'promo_codes'</li>";
echo "<li><strong>Campo unified_code Vazio:</strong> O JavaScript pode não estar enviando o unified_code corretamente</li>";
echo "<li><strong>Ordem de Execução:</strong> As correções podem ter sido implementadas após este booking</li>";
echo "</ol>";

// 5. VERIFICAR HORÁRIO DO BOOKING vs HORÁRIO DAS CORREÇÕES
echo "<h2>⏰ VERIFICAÇÃO DE TIMING:</h2>";
echo "<p><strong>Booking criado:</strong> " . $booking['created_at'] . "</p>";
echo "<p><strong>Correções implementadas:</strong> ~10:00 de hoje</p>";

if (strtotime($booking['created_at']) > strtotime('2025-08-21 10:00:00')) {
    echo "<p style='color: red;'>⚠️ Este booking foi feito APÓS as correções - problema ainda existe!</p>";
} else {
    echo "<p style='color: green;'>ℹ️ Este booking foi feito ANTES das correções - comportamento esperado</p>";
}

// 6. PRÓXIMOS PASSOS RECOMENDADOS
echo "<h2>🚀 PRÓXIMOS PASSOS:</h2>";
echo "<ol>";
echo "<li>Verificar qual API está sendo realmente chamada pelo frontend</li>";
echo "<li>Confirmar se o JavaScript está enviando 'unified_code' com o valor 'ERIK42'</li>";
echo "<li>Verificar se a correção no StripeManager está ativa</li>";
echo "<li>Fazer um novo teste com logging ativo</li>";
echo "</ol>";

?>
