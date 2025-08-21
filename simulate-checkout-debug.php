<?php
/**
 * SIMULAÇÃO DE CHECKOUT - DEBUG DO VALOR A$85.00
 * 
 * Este script simula exatamente o que o frontend está enviando
 * para identificar onde o valor de $265.00 vira A$85.00
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 SIMULAÇÃO DE CHECKOUT - DEBUG DO VALOR A$85.00\n";
echo str_repeat("=", 60) . "\n";

// 1. SIMULAR DADOS DO FRONTEND (exatamente como booking3.php)
$frontendData = [
    'service_id' => '2',
    'name' => 'Test User',
    'email' => 'test@example.com',
    'phone' => '+61400000000',
    'address' => '123 Test St',
    'suburb' => 'Sydney',
    'postcode' => '2000',
    'date' => '2025-08-22',
    'time' => '10:00',
    'recurrence' => 'one-time',
    'extras' => [],
    'discount_amount' => 0,
    'special_requests' => '',
    'total' => 265.00  // ✅ Frontend calcula $265.00
];

echo "📤 DADOS DO FRONTEND:\n";
echo "   Total calculado: $" . $frontendData['total'] . "\n";
echo "   Service ID: " . $frontendData['service_id'] . "\n";
echo "\n";

// 2. TESTAR CONSULTA DIRETA NO BANCO DE DADOS
echo "🔍 VERIFICAÇÃO DO BANCO DE DADOS:\n";

try {
    // Verificar se existe o arquivo de configuração da database
    if (file_exists(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    }
    
    if (file_exists(__DIR__ . '/config/database.php')) {
        require_once __DIR__ . '/config/database.php';
    }
    
    // Tentar conectar ao banco
    $connections_tried = [];
    $connection = null;
    
    // Tentar diferentes configurações de conexão
    $possible_configs = [
        'postgresql://postgres:password@localhost:5432/blue_cleaning',
        'postgresql://admin:admin@localhost:5432/cleaning_bookings',
        'postgresql://user:pass@localhost:5432/bookings'
    ];
    
    foreach ($possible_configs as $config) {
        try {
            $connection = new PDO($config);
            $connections_tried[] = "✅ Connected to: $config";
            break;
        } catch (PDOException $e) {
            $connections_tried[] = "❌ Failed to connect to: $config - " . $e->getMessage();
        }
    }
    
    if ($connection) {
        echo "   ✅ Conexão com banco estabelecida\n";
        
        // Consultar o serviço ID=2
        $stmt = $connection->prepare("SELECT id, name, base_price, is_active FROM services WHERE id = ?");
        $stmt->execute([2]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            echo "   📋 Serviço encontrado:\n";
            echo "      ID: " . $service['id'] . "\n";
            echo "      Nome: " . $service['name'] . "\n";
            echo "      Base Price: $" . $service['base_price'] . "\n";
            echo "      Ativo: " . ($service['is_active'] ? 'Sim' : 'Não') . "\n";
        } else {
            echo "   ❌ Serviço ID=2 não encontrado no banco\n";
        }
        
    } else {
        echo "   ❌ Não foi possível conectar ao banco\n";
        foreach ($connections_tried as $attempt) {
            echo "      $attempt\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro ao verificar banco: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. SIMULAR PROCESSAMENTO DA API
echo "🚀 SIMULAÇÃO DA API stripe-checkout-unified-final.php:\n";

try {
    // Simular o processamento da API sem fazer requisição real
    
    // Verificar se StripeManager existe
    if (file_exists(__DIR__ . '/core/StripeManager.php')) {
        require_once __DIR__ . '/core/StripeManager.php';
        echo "   ✅ StripeManager.php encontrado\n";
    } else {
        echo "   ❌ StripeManager.php não encontrado\n";
    }
    
    // Verificar se PricingEngine existe
    if (file_exists(__DIR__ . '/core/PricingEngine.php')) {
        require_once __DIR__ . '/core/PricingEngine.php';
        echo "   ✅ PricingEngine.php encontrado\n";
        
        // Testar PricingEngine diretamente
        echo "\n📊 TESTANDO PricingEngine:\n";
        
        $pricingResult = PricingEngine::calculate(
            '2',
            [],
            'one-time',
            0,
            '',
            'test@example.com'
        );
        
        echo "   Resultado do PricingEngine:\n";
        echo "      Base Price: $" . $pricingResult['base_price'] . "\n";
        echo "      Final Amount: $" . $pricingResult['final_amount'] . "\n";
        echo "      Stripe Cents: " . $pricingResult['stripe_amount_cents'] . "\n";
        
        // Verificar se o valor está correto
        if ($pricingResult['base_price'] == 85.00) {
            echo "   ❌ PROBLEMA ENCONTRADO: PricingEngine retorna $85.00!\n";
            echo "   ⚠️ Isso explica porque Stripe recebe A$85.00\n";
        } elseif ($pricingResult['base_price'] == 265.00) {
            echo "   ✅ PricingEngine correto: $265.00\n";
        } else {
            echo "   ⚠️ PricingEngine retorna valor inesperado: $" . $pricingResult['base_price'] . "\n";
        }
        
    } else {
        echo "   ❌ PricingEngine.php não encontrado\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro ao simular API: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. TESTAR FLUXO COMPLETO COM FRONTEND TOTAL
echo "🎯 TESTE DO FRONTEND TOTAL:\n";

try {
    // Simular o que deveria acontecer quando frontend_total é fornecido
    $simulatedBookingData = [
        'service_id' => '2',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '+61400000000',
        'address' => '123 Test St',
        'suburb' => 'Sydney',
        'postcode' => '2000',
        'date' => '2025-08-22',
        'time' => '10:00',
        'recurrence' => 'one-time',
        'extras' => [],
        'discount_amount' => 0,
        'referral_code' => null,
        'special_requests' => '',
        'frontend_total' => 265.00  // ✅ Valor do frontend
    ];
    
    echo "   Frontend Total fornecido: $" . $simulatedBookingData['frontend_total'] . "\n";
    
    // Simular a lógica do StripeManager
    if (isset($simulatedBookingData['frontend_total']) && $simulatedBookingData['frontend_total'] > 0) {
        echo "   ✅ StripeManager deveria usar: $" . $simulatedBookingData['frontend_total'] . "\n";
        
        $expectedStripeAmount = intval($simulatedBookingData['frontend_total'] * 100);
        echo "   ✅ Stripe deveria receber: $expectedStripeAmount cents (A$" . $simulatedBookingData['frontend_total'] . ")\n";
        
        // Verificar se a matemática está correta
        if ($expectedStripeAmount == 26500) {
            echo "   ✅ Matemática correta: 265.00 × 100 = 26500 cents\n";
        } else {
            echo "   ❌ Erro de matemática: " . $simulatedBookingData['frontend_total'] . " × 100 = $expectedStripeAmount\n";
        }
    } else {
        echo "   ❌ Frontend total não detectado ou inválido\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro no teste: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🎯 CONCLUSÃO E PRÓXIMOS PASSOS:\n";
echo "1. Verificar se PricingEngine está retornando $85.00 em vez de $265.00\n";
echo "2. Confirmar se StripeManager está usando frontend_total corretamente\n";
echo "3. Testar requisição real para a API\n";
echo "4. Verificar logs do servidor durante checkout real\n";

echo "\n" . str_repeat("=", 60) . "\n";
?>
