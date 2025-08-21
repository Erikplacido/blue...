<?php
require_once 'context_complete.php';

try {
    $db = AustralianDatabase::getInstance();
    $connection = $db->getConnection();
    
    echo "=== VERIFICANDO PREÇOS ATUAIS EM SERVICE_INCLUSIONS ===\n";
    $stmt = $connection->prepare("SELECT id, name, price, is_active FROM service_inclusions WHERE service_id = 1 ORDER BY id");
    $stmt->execute();
    $inclusions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($inclusions)) {
        echo "Nenhum item encontrado em service_inclusions para service_id = 1\n";
    } else {
        echo "Total de itens encontrados: " . count($inclusions) . "\n\n";
        
        foreach ($inclusions as $item) {
            echo "ID: {$item['id']} | Nome: {$item['name']} | Preço: R$ " . number_format($item['price'], 2, ',', '.') . " | Ativo: " . ($item['is_active'] ? 'Sim' : 'Não') . "\n";
        }
    }
    
    echo "\n=== EXEMPLO DE ATUALIZAÇÃO DE PREÇO PARA BATHROOM ===\n";
    // Vamos procurar especificamente pelo item "bathroom" e atualizar o preço
    $stmt = $connection->prepare("SELECT id, name, price FROM service_inclusions WHERE service_id = 1 AND LOWER(name) LIKE '%bathroom%'");
    $stmt->execute();
    $bathroom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bathroom) {
        echo "Item encontrado: {$bathroom['name']} - Preço atual: R$ " . number_format($bathroom['price'], 2, ',', '.') . "\n";
        
        // Vamos atualizar o preço do bathroom para R$ 35,00 conforme a imagem
        $stmt = $connection->prepare("UPDATE service_inclusions SET price = 35.00 WHERE id = ?");
        $stmt->execute([$bathroom['id']]);
        
        echo "Preço atualizado para R$ 35,00\n";
    } else {
        echo "Item 'bathroom' não encontrado. Vamos ver todos os nomes:\n";
        $stmt = $connection->prepare("SELECT name FROM service_inclusions WHERE service_id = 1 ORDER BY name");
        $stmt->execute();
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($names as $name) {
            echo "- $name\n";
        }
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
