<?php
/**
 * TESTE ESPECÍFICO PARA DIAGNOSTICAR O PROBLEMA DO VALOR A$85.00
 * Este script vai simular exatamente o que booking3.php faz
 */

require_once 'config.php';

define('SERVICE_ID_HOUSE_CLEANING', 1);

function getDynamicServiceData($serviceId = 1) {
    try {
        $db = AustralianDatabase::getInstance();
        $connection = $db->getConnection();
        
        // PRIMEIRO: Buscar informações do serviço específico da tabela services
        $stmt = $connection->prepare("
            SELECT id, service_code, name, description, base_price, duration_minutes, category
            FROM services 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$serviceId]);
        $service_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$service_info) {
            error_log("❌ Service ID {$serviceId} not found or inactive in database");
            throw new Exception("Service ID {$serviceId} not found or inactive");
        }
        
        echo "✅ Service loaded from DB:\n";
        print_r($service_info);
        
        return [
            'service' => $service_info,
            'config' => [],
            'inclusions' => [],
            'extras' => [],
            'preferences' => []
        ];
        
    } catch (Exception $e) {
        error_log("Dynamic data error: " . $e->getMessage());
        // Retorna estrutura vazia em caso de erro
        return [
            'service' => null,
            'config' => [],
            'inclusions' => [],
            'extras' => [],
            'preferences' => []
        ];
    }
}

echo "🔥 TESTE ESPECÍFICO - DIAGNÓSTICO DO VALOR A$85.00\n";
echo "================================================\n\n";

// Simular exatamente o que booking3.php faz
$serviceId = isset($_GET['service_id']) && is_numeric($_GET['service_id']) ? 
    (int)$_GET['service_id'] : SERVICE_ID_HOUSE_CLEANING;

echo "1. Service ID usado: {$serviceId}\n";

// Carregar dados dinâmicos
$dynamicData = getDynamicServiceData($serviceId);

echo "\n2. Estrutura retornada:\n";
print_r($dynamicData);

// Simular exatamente a linha 2317 problemática
echo "\n3. TESTE DA LINHA 2317 PROBLEMÁTICA:\n";
echo "   \$dynamicData['service']['base_price'] = " . 
     ($dynamicData['service']['base_price'] ?? 'NULL/UNDEFINED') . "\n";

$fallbackValue = $dynamicData['service']['base_price'] ?? 0.00;
echo "   Valor com fallback: " . $fallbackValue . "\n";
echo "   Formatado: $" . number_format($fallbackValue, 2) . "\n";

// TESTE: Verificar se service é null
if (!$dynamicData['service']) {
    echo "\n❌ ERRO ENCONTRADO: \$dynamicData['service'] é NULL!\n";
    echo "   Isso significa que a função retornou com erro\n";
} else {
    echo "\n✅ \$dynamicData['service'] não é NULL\n";
    
    // Verificar se base_price existe
    if (!isset($dynamicData['service']['base_price'])) {
        echo "❌ ERRO ENCONTRADO: 'base_price' não existe no array!\n";
    } else {
        echo "✅ 'base_price' existe: " . $dynamicData['service']['base_price'] . "\n";
    }
}

echo "\n4. RESULTADO FINAL:\n";
echo "   O valor que seria exibido no frontend: $" . number_format($dynamicData['service']['base_price'] ?? 0.00, 2) . "\n";

?>
