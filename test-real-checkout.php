<?php
/**
 * TESTE DE CHECKOUT REAL - SIMULAR REQUISIÇÃO COMO O BROWSER
 */

echo "🚀 TESTE DE CHECKOUT REAL - SIMULAR BROWSER\n";
echo str_repeat("=", 60) . "\n";

// Dados exatamente como o frontend envia
$checkoutData = [
    'service_id' => '2',
    'name' => 'Test User Debug',
    'email' => 'debug@test.com',
    'phone' => '+61400000000',
    'address' => '123 Debug Street',
    'suburb' => 'Sydney',
    'postcode' => '2000',
    'date' => '2025-08-22',
    'time' => '10:00',
    'recurrence' => 'one-time',
    'extras' => [],
    'discount_amount' => 0,
    'special_requests' => '',
    'total' => 265.00  // ✅ Valor que DEVERIA chegar no Stripe
];

echo "📤 Enviando requisição para API com total: $" . $checkoutData['total'] . "\n\n";

// Fazer requisição POST real para a API
$url = 'http://localhost/booking_ok/api/stripe-checkout-unified-final.php';

// Se não conseguir localhost, testar com caminho relativo
if (!checkUrlExists($url)) {
    $url = __DIR__ . '/../api/stripe-checkout-unified-final.php';
    echo "⚠️ Localhost não disponível, testando diretamente no arquivo\n";
}

try {
    // Opção 1: cURL (se disponível)
    if (function_exists('curl_init')) {
        echo "🌐 Fazendo requisição cURL para: $url\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($checkoutData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: $error");
        }
        
        echo "✅ Requisição enviada. HTTP Code: $httpCode\n";
        
        if ($response) {
            echo "📥 RESPOSTA DA API:\n";
            $responseData = json_decode($response, true);
            
            if ($responseData) {
                // Analisar resposta
                if (isset($responseData['pricing']['final_amount'])) {
                    $finalAmount = $responseData['pricing']['final_amount'];
                    echo "   💰 Final Amount: $" . $finalAmount . "\n";
                    
                    if ($finalAmount == 265.00) {
                        echo "   ✅ SUCCESS: API retornou $265.00!\n";
                        echo "   ✅ Stripe deveria receber A$265.00\n";
                    } elseif ($finalAmount == 85.00) {
                        echo "   ❌ PROBLEM: API retornou $85.00!\n";
                        echo "   ❌ Por isso Stripe recebe A$85.00\n";
                        echo "   🔍 Investigar: por que API ignora frontend_total\n";
                    } else {
                        echo "   ⚠️ UNEXPECTED: API retornou $" . $finalAmount . "\n";
                    }
                }
                
                // Mostrar dados de debug
                if (isset($responseData['debug_info'])) {
                    echo "   🔍 Debug Info:\n";
                    echo "      API Version: " . $responseData['debug_info']['api_version'] . "\n";
                    echo "      Endpoint: " . $responseData['debug_info']['endpoint_used'] . "\n";
                }
                
                echo "\n📋 RESPOSTA COMPLETA:\n";
                echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
                
            } else {
                echo "❌ Resposta não é JSON válido:\n";
                echo substr($response, 0, 500) . "...\n";
            }
        } else {
            echo "❌ Nenhuma resposta recebida\n";
        }
        
    } else {
        echo "❌ cURL não disponível\n";
        
        // Opção 2: Simular incluindo o arquivo diretamente
        echo "🔧 Testando inclusão direta do arquivo API...\n";
        
        // Simular ambiente de requisição
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        // Simular input JSON
        $jsonInput = json_encode($checkoutData);
        
        // Capturar output
        ob_start();
        
        // Simular file_get_contents('php://input')
        $GLOBALS['test_input'] = $jsonInput;
        
        // Incluir e executar a API
        try {
            // Substituir file_get_contents temporariamente
            require_once __DIR__ . '/../api/stripe-checkout-unified-final.php';
        } catch (Exception $e) {
            echo "❌ Erro ao incluir API: " . $e->getMessage() . "\n";
        }
        
        $output = ob_get_clean();
        
        if ($output) {
            echo "📥 OUTPUT DA API:\n";
            echo $output . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO NO TESTE: " . $e->getMessage() . "\n";
}

echo "\n🎯 PRÓXIMOS PASSOS:\n";
echo "1. Se API retorna $265.00 → Problema pode ser no browser/cache\n";
echo "2. Se API retorna $85.00 → Problema na lógica da API\n";
echo "3. Verificar logs do servidor durante checkout real\n";
echo "4. Testar em browser com dados limpos (modo anônimo)\n";

function checkUrlExists($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode < 400;
    }
    return false;
}

echo "\n" . str_repeat("=", 60) . "\n";
?>
