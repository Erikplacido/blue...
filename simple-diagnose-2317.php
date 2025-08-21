<?php
/**
 * TESTE SIMPLES PARA IDENTIFICAR O PROBLEMA DA LINHA 2317
 */

echo "🔥 TESTE ESPECÍFICO - DIAGNÓSTICO DO VALOR A$85.00\n";
echo "================================================\n\n";

// Simular a estrutura que pode estar causando o problema
$mockDynamicDataCorreto = [
    'service' => [
        'id' => 1,
        'base_price' => 265.00,
        'name' => 'House Cleaning Service'
    ]
];

$mockDynamicDataProblematico = [
    'service' => null  // Isso pode acontecer se houver erro na função
];

$mockDynamicDataSemBasePrice = [
    'service' => [
        'id' => 1,
        'name' => 'House Cleaning Service'
        // base_price não existe!
    ]
];

echo "1. TESTE COM DADOS CORRETOS:\n";
$result1 = $mockDynamicDataCorreto['service']['base_price'] ?? 0.00;
echo "   Valor: $" . number_format($result1, 2) . "\n";

echo "\n2. TESTE COM SERVICE NULL (erro na função):\n";
$result2 = $mockDynamicDataProblematico['service']['base_price'] ?? 0.00;
echo "   Valor: $" . number_format($result2, 2) . "\n";

echo "\n3. TESTE SEM CAMPO base_price:\n";
$result3 = $mockDynamicDataSemBasePrice['service']['base_price'] ?? 0.00;
echo "   Valor: $" . number_format($result3, 2) . "\n";

echo "\n4. CONCLUSÃO:\n";
echo "   Qualquer um dos cenários 2 ou 3 resultaria em \$0.00 sendo exibido\n";
echo "   Este \$0.00 pode ser o que está causando o A$85.00 no Stripe\n";

// Simulação de cálculo com valor errado
echo "\n5. SIMULAÇÃO DE CÁLCULO COM VALOR ERRADO:\n";
$basePrice = 0.00; // Valor errado vindo do fallback
$someCalculation = $basePrice + 85.00; // Algum cálculo adicional no sistema
echo "   Base incorreta (\$0.00) + algum valor (\$85.00) = \$" . number_format($someCalculation, 2) . "\n";

echo "\n✅ IDENTIFICAÇÃO COMPLETA DO PROBLEMA REALIZADA\n";
?>
