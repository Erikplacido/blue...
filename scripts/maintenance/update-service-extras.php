<?php
/**
 * Script para atualizar service_extras com dados reais
 */

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "=== ATUALIZANDO SERVICE_EXTRAS ===\n\n";
    
    // 1. Verificar estrutura da tabela
    echo "📋 Estrutura atual da tabela service_extras:\n";
    $stmt = $pdo->query("DESCRIBE service_extras");
    $columns = $stmt->fetchAll();
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']}\n";
    }
    echo "\n";
    
    // 2. Ver dados atuais
    echo "📋 Dados atuais:\n";
    $stmt = $pdo->query("SELECT * FROM service_extras ORDER BY service_id, price DESC");
    $currentExtras = $stmt->fetchAll();
    
    foreach ($currentExtras as $extra) {
        echo "  - {$extra['name']}: \${$extra['price']} (service_id: {$extra['service_id']})\n";
    }
    echo "\n";
    
    // 3. Limpar extras existentes do service_id 1
    echo "🗑️ Removendo extras antigos do service_id 1...\n";
    $stmt = $pdo->prepare("DELETE FROM service_extras WHERE service_id = 1");
    $stmt->execute();
    echo "✅ Extras antigos removidos.\n\n";
    
    // 4. Dados reais do print fornecido
    $realExtras = [
        [
            'service_id' => 1,
            'name' => 'Extra hour (house keeping per hour)',
            'price' => 72.00,
            'icon' => 'fas fa-clock',
            'sort_order' => 1
        ],
        [
            'service_id' => 1,
            'name' => 'Inside Kitchen Cupboards (Empty & Cleaned)',
            'price' => 72.00,
            'icon' => 'fas fa-door-open',
            'sort_order' => 2
        ],
        [
            'service_id' => 1,
            'name' => 'Pantry Cleaning',
            'price' => 72.00,
            'icon' => 'fas fa-boxes',
            'sort_order' => 3
        ],
        [
            'service_id' => 1,
            'name' => 'Deep cleaning (per area)',
            'price' => 54.00,
            'icon' => 'fas fa-broom',
            'sort_order' => 4
        ],
        [
            'service_id' => 1,
            'name' => 'Clean Inside the Fridge',
            'price' => 54.00,
            'icon' => 'fas fa-snowflake',
            'sort_order' => 5
        ],
        [
            'service_id' => 1,
            'name' => 'Inside Oven Cleaning',
            'price' => 54.00,
            'icon' => 'fas fa-fire',
            'sort_order' => 6
        ],
        [
            'service_id' => 1,
            'name' => 'Wash dirty dishes',
            'price' => 36.00,
            'icon' => 'fas fa-utensils',
            'sort_order' => 7
        ],
        [
            'service_id' => 1,
            'name' => 'After-Party Cleaning (per room)',
            'price' => 36.00,
            'icon' => 'fas fa-glass-cheers',
            'sort_order' => 8
        ],
        [
            'service_id' => 1,
            'name' => 'Dusting of Blinds (per set)',
            'price' => 18.00,
            'icon' => 'fas fa-window-restore',
            'sort_order' => 9
        ],
        [
            'service_id' => 1,
            'name' => 'Internal Window Glass (each)',
            'price' => 18.00,
            'icon' => 'fas fa-window-minimize',
            'sort_order' => 10
        ],
        [
            'service_id' => 1,
            'name' => 'Garage Clean-Up (single garage)',
            'price' => 18.00,
            'icon' => 'fas fa-warehouse',
            'sort_order' => 11
        ],
        [
            'service_id' => 1,
            'name' => 'Outdoor/Patio Area (Up to 30m²)',
            'price' => 18.00,
            'icon' => 'fas fa-tree',
            'sort_order' => 12
        ],
        [
            'service_id' => 1,
            'name' => 'One load of laundry',
            'price' => 18.00,
            'icon' => 'fas fa-tshirt',
            'sort_order' => 13
        ],
        [
            'service_id' => 1,
            'name' => 'Change bed Linen (per bed)',
            'price' => 18.00,
            'icon' => 'fas fa-bed',
            'sort_order' => 14
        ]
    ];
    
    // 5. Inserir novos extras
    echo "📝 Inserindo extras reais...\n";
    
    $insertQuery = "INSERT INTO service_extras (service_id, name, price, icon, is_active, sort_order, created_at) 
                    VALUES (?, ?, ?, ?, 1, ?, NOW())";
    $stmt = $pdo->prepare($insertQuery);
    
    $inserted = 0;
    foreach ($realExtras as $extra) {
        try {
            $stmt->execute([
                $extra['service_id'],
                $extra['name'],
                $extra['price'],
                $extra['icon'],
                $extra['sort_order']
            ]);
            $inserted++;
            echo "  ✅ {$extra['name']} - \${$extra['price']}\n";
        } catch (Exception $e) {
            echo "  ❌ Erro ao inserir {$extra['name']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📊 Resumo:\n";
    echo "  - Extras inseridos: $inserted\n";
    echo "  - Total de extras reais: " . count($realExtras) . "\n";
    
    // 6. Verificar resultado final
    echo "\n📋 Extras finais do service_id 1:\n";
    $stmt = $pdo->query("SELECT name, price, icon, sort_order FROM service_extras WHERE service_id = 1 ORDER BY price DESC");
    $finalExtras = $stmt->fetchAll();
    
    foreach ($finalExtras as $extra) {
        echo "  - {$extra['name']}: \${$extra['price']} (icon: {$extra['icon']}) - ordem: {$extra['sort_order']}\n";
    }
    
    echo "\n✅ ATUALIZAÇÃO CONCLUÍDA!\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
