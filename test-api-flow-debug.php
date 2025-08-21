<?php
/**
 * TESTE SIMULADO DA API - RASTREAR FLUXO COMPLETO
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🚀 TESTE SIMULADO DA API - RASTREAR ONDE $265.00 VIRA A$85.00\n";
echo str_repeat("=", 70) . "\n";

// Simular dados exatos que vêm do frontend
$frontendPayload = [
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
    'total' => 265.00  // ✅ Frontend envia $265.00
];

echo "📤 PAYLOAD DO FRONTEND:\n";
foreach ($frontendPayload as $key => $value) {
    if ($key === 'total') {
        echo "   ✅ $key: \$$value (VALOR CRÍTICO)\n";
    } else {
        echo "   $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
}
echo "\n";

// SIMULAR PROCESSAMENTO DA API stripe-checkout-unified-final.php
echo "🔧 SIMULANDO PROCESSAMENTO DA API:\n";

try {
    // 1. PREPARAR BOOKING DATA (como na API)
    $bookingData = [
        'service_id' => $frontendPayload['service_id'] ?? '2',
        'name' => trim($frontendPayload['name']),
        'email' => filter_var(trim($frontendPayload['email']), FILTER_VALIDATE_EMAIL),
        'phone' => trim($frontendPayload['phone']),
        'address' => trim($frontendPayload['address']),
        'suburb' => trim($frontendPayload['suburb']),
        'postcode' => trim($frontendPayload['postcode']),
        'date' => $frontendPayload['date'],
        'time' => $frontendPayload['time'],
        'recurrence' => $frontendPayload['recurrence'] ?? 'one-time',
        'extras' => $frontendPayload['extras'] ?? [],
        'discount_amount' => floatval($frontendPayload['discount_amount'] ?? 0),
        'referral_code' => $frontendPayload['referral_code'] ?? null,
        'special_requests' => $frontendPayload['special_requests'] ?? '',
        'frontend_total' => floatval($frontendPayload['total'] ?? 0) // ✅ CRÍTICO
    ];
    
    echo "   ✅ BookingData preparado\n";
    echo "      frontend_total: $" . $bookingData['frontend_total'] . "\n\n";

    // 2. SIMULAR StripeManager::createCheckoutSession()
    echo "💳 SIMULANDO StripeManager::createCheckoutSession():\n";
    
    // Simular lógica do StripeManager
    if (isset($bookingData['frontend_total']) && $bookingData['frontend_total'] > 0) {
        echo "   ✅ Frontend total detectado: $" . $bookingData['frontend_total'] . "\n";
        
        // Criar estrutura de pricing (como no StripeManager)
        $pricing = [
            'base_price' => $bookingData['frontend_total'],
            'extras_price' => 0.00,
            'subtotal' => $bookingData['frontend_total'],
            'total_discount' => 0.00,
            'final_amount' => $bookingData['frontend_total'],
            'stripe_amount_cents' => intval($bookingData['frontend_total'] * 100),
            'currency' => 'AUD',
            'source' => 'frontend_calculated'
        ];
        
        echo "   ✅ Pricing criado com frontend_total\n";
        echo "      final_amount: $" . $pricing['final_amount'] . "\n";
        echo "      stripe_amount_cents: " . $pricing['stripe_amount_cents'] . "\n";
        
    } else {
        echo "   ❌ Frontend total NÃO detectado - usando PricingEngine\n";
        
        // Carregar PricingEngine para teste
        if (file_exists(__DIR__ . '/core/PricingEngine.php')) {
            require_once __DIR__ . '/core/PricingEngine.php';
            
            echo "   🧮 Chamando PricingEngine::calculate()...\n";
            $pricing = PricingEngine::calculate(
                $bookingData['service_id'] ?? '2',
                $bookingData['extras'] ?? [],
                $bookingData['recurrence'] ?? 'one-time',
                $bookingData['discount_amount'] ?? 0,
                '',
                $bookingData['email'] ?? ''
            );
            
            echo "   🧮 PricingEngine resultado:\n";
            echo "      final_amount: $" . $pricing['final_amount'] . "\n";
            echo "      stripe_amount_cents: " . $pricing['stripe_amount_cents'] . "\n";
            
            if ($pricing['final_amount'] == 85.00) {
                echo "   ❌ PROBLEMA: PricingEngine retorna $85.00!\n";
                echo "   ❌ Por isso Stripe recebe A$85.00 em vez de A$265.00\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro na simulação: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. TESTE DO PricingEngine SEPARADO
echo "🧮 TESTE ISOLADO DO PricingEngine:\n";

try {
    if (file_exists(__DIR__ . '/core/PricingEngine.php')) {
        require_once __DIR__ . '/core/PricingEngine.php';
        
        echo "   Testando PricingEngine::calculate(serviceId='2')...\n";
        
        $testPricing = PricingEngine::calculate('2', [], 'one-time', 0, '', '');
        
        echo "   Resultado:\n";
        echo "      base_price: $" . $testPricing['base_price'] . "\n";
        echo "      final_amount: $" . $testPricing['final_amount'] . "\n";
        echo "      stripe_amount_cents: " . $testPricing['stripe_amount_cents'] . "\n";
        
        // Análise do resultado
        if ($testPricing['base_price'] == 85.00) {
            echo "\n   ❌ CAUSA RAIZ ENCONTRADA!\n";
            echo "      PricingEngine está retornando $85.00 para service_id=2\n";
            echo "      Mas o banco tem $265.00\n";
            echo "      Isso indica falha na conexão com banco ou cache inválido\n";
        } elseif ($testPricing['base_price'] == 265.00) {
            echo "\n   ✅ PricingEngine está correto!\n";
            echo "      O problema deve estar em outro lugar no fluxo\n";
        } else {
            echo "\n   ⚠️ PricingEngine retorna valor inesperado: $" . $testPricing['base_price'] . "\n";
        }
        
    } else {
        echo "   ❌ PricingEngine.php não encontrado\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro no PricingEngine: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'Unable to determine price') !== false) {
        echo "   💡 PricingEngine não consegue conectar ao banco\n";
        echo "   💡 Pode estar usando fallback com valor incorreto\n";
    }
}

echo "\n🎯 DIAGNÓSTICO FINAL:\n";
echo "1. ✅ Banco de dados: service_id=2 = \$265.00 (CORRETO)\n";
echo "2. ✅ Frontend: calcula e envia \$265.00 (CORRETO)\n"; 
echo "3. ❓ PricingEngine: pode estar retornando \$85.00 (INVESTIGAR)\n";
echo "4. ❓ StripeManager: pode não estar usando frontend_total (INVESTIGAR)\n";
echo "\nSe PricingEngine = \$85.00 → Problema na conexão com banco\n";
echo "Se StripeManager ignora frontend_total → Problema na lógica da API\n";

echo "\n" . str_repeat("=", 70) . "\n";
?>
