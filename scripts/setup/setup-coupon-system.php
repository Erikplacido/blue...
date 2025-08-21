<?php
/**
 * =========================================================
 * MIGRATION: SISTEMA DE CUPONS COMPLETO
 * =========================================================
 * 
 * @file setup-coupon-system.php
 * @description Cria tabelas e estrutura para sistema de cupons
 * @date 2025-08-11
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🚀 SETUP SISTEMA DE CUPONS - BANCO DE DADOS\n";
echo "==========================================\n\n";

// 1. CARREGAR CONFIGURAÇÕES DO .ENV
if (file_exists(__DIR__ . '/.env')) {
    $envContent = file_get_contents(__DIR__ . '/.env');
    $envLines = explode("\n", $envContent);
    
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
        }
    }
    echo "✅ Arquivo .env carregado\n";
} else {
    die("❌ Arquivo .env não encontrado\n");
}

// 2. CONECTAR AO BANCO
try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✅ Conectado ao banco: {$_ENV['DB_DATABASE']}\n\n";
    
} catch (PDOException $e) {
    die("❌ Erro de conexão: " . $e->getMessage() . "\n");
}

// 3. CRIAR TABELA DE CUPONS
echo "📋 CRIANDO TABELA DE CUPONS...\n";

$createCouponsTable = "
CREATE TABLE IF NOT EXISTS coupons (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    value DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) DEFAULT 0.00,
    maximum_discount DECIMAL(10,2) DEFAULT NULL,
    usage_limit INT(11) DEFAULT NULL,
    usage_count INT(11) DEFAULT 0,
    per_customer_limit INT(11) DEFAULT 1,
    first_time_only TINYINT(1) DEFAULT 0,
    valid_from DATETIME DEFAULT CURRENT_TIMESTAMP,
    valid_until DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_active (is_active),
    INDEX idx_valid (valid_from, valid_until),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($createCouponsTable);
    echo "✅ Tabela 'coupons' criada com sucesso!\n";
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'coupons': " . $e->getMessage() . "\n";
}

// 4. CRIAR TABELA DE USO DE CUPONS
echo "📊 CRIANDO TABELA DE TRACKING DE USO...\n";

$createCouponUsageTable = "
CREATE TABLE IF NOT EXISTS coupon_usage (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT(11) NOT NULL,
    coupon_code VARCHAR(50) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    booking_code VARCHAR(50),
    discount_amount DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    INDEX idx_coupon_id (coupon_id),
    INDEX idx_customer_email (customer_email),
    INDEX idx_booking_code (booking_code),
    INDEX idx_used_at (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($createCouponUsageTable);
    echo "✅ Tabela 'coupon_usage' criada com sucesso!\n";
} catch (PDOException $e) {
    echo "❌ Erro ao criar tabela 'coupon_usage': " . $e->getMessage() . "\n";
}

// 5. INSERIR CUPONS PADRÃO
echo "\n🎫 INSERINDO CUPONS PADRÃO...\n";

$defaultCoupons = [
    [
        'code' => 'WELCOME10',
        'name' => 'Welcome New Customer',
        'description' => '10% off your first booking',
        'type' => 'percentage',
        'value' => 10.00,
        'minimum_amount' => 50.00,
        'maximum_discount' => 50.00,
        'usage_limit' => NULL,
        'per_customer_limit' => 1,
        'first_time_only' => 1,
        'valid_until' => '2025-12-31 23:59:59'
    ],
    [
        'code' => 'SUMMER20',
        'name' => 'Summer Special',
        'description' => '20% Summer discount',
        'type' => 'percentage',
        'value' => 20.00,
        'minimum_amount' => 100.00,
        'maximum_discount' => 100.00,
        'usage_limit' => 1000,
        'per_customer_limit' => 1,
        'first_time_only' => 0,
        'valid_until' => '2025-09-30 23:59:59'
    ],
    [
        'code' => 'SAVE25',
        'name' => 'Fixed Discount $25',
        'description' => '$25 off your booking',
        'type' => 'fixed',
        'value' => 25.00,
        'minimum_amount' => 80.00,
        'maximum_discount' => 25.00,
        'usage_limit' => 500,
        'per_customer_limit' => 1,
        'first_time_only' => 0,
        'valid_until' => '2025-12-31 23:59:59'
    ],
    [
        'code' => 'LOYALTY15',
        'name' => 'Loyalty Reward',
        'description' => '15% loyalty discount',
        'type' => 'percentage',
        'value' => 15.00,
        'minimum_amount' => 60.00,
        'maximum_discount' => 75.00,
        'usage_limit' => NULL,
        'per_customer_limit' => 3,
        'first_time_only' => 0,
        'valid_until' => '2025-12-31 23:59:59'
    ],
    [
        'code' => 'FIRST50',
        'name' => 'First Time Customer',
        'description' => '$50 off your first service',
        'type' => 'fixed',
        'value' => 50.00,
        'minimum_amount' => 100.00,
        'maximum_discount' => 50.00,
        'usage_limit' => NULL,
        'per_customer_limit' => 1,
        'first_time_only' => 1,
        'valid_until' => '2025-12-31 23:59:59'
    ],
    [
        'code' => 'AUTUMN30',
        'name' => 'Autumn Special',
        'description' => '30% off for returning customers',
        'type' => 'percentage',
        'value' => 30.00,
        'minimum_amount' => 120.00,
        'maximum_discount' => 80.00,
        'usage_limit' => 200,
        'per_customer_limit' => 1,
        'first_time_only' => 0,
        'valid_until' => '2025-11-30 23:59:59'
    ]
];

$insertSQL = "
    INSERT INTO coupons (
        code, name, description, type, value, minimum_amount, 
        maximum_discount, usage_limit, per_customer_limit, 
        first_time_only, valid_until
    ) VALUES (
        :code, :name, :description, :type, :value, :minimum_amount,
        :maximum_discount, :usage_limit, :per_customer_limit,
        :first_time_only, :valid_until
    )
";

$stmt = $pdo->prepare($insertSQL);

$inserted = 0;
foreach ($defaultCoupons as $coupon) {
    try {
        $stmt->execute($coupon);
        $inserted++;
        echo "   ✅ {$coupon['code']} - {$coupon['name']} ({$coupon['type']}: ";
        if ($coupon['type'] === 'percentage') {
            echo "{$coupon['value']}%)";
        } else {
            echo "$" . number_format($coupon['value'], 2) . ")";
        }
        echo "\n";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "   ⚠️ {$coupon['code']} - Já existe (ignorado)\n";
        } else {
            echo "   ❌ {$coupon['code']} - Erro: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n📊 RESUMO DA INSTALAÇÃO:\n";
echo "========================\n";
echo "✅ Tabelas criadas: coupons, coupon_usage\n";
echo "✅ Cupons inseridos: $inserted\n";

// 6. VERIFICAR ESTRUTURA CRIADA
echo "\n🔍 VERIFICANDO ESTRUTURA CRIADA...\n";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM coupons WHERE is_active = 1");
$totalActive = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT type, COUNT(*) as count 
    FROM coupons 
    WHERE is_active = 1 
    GROUP BY type
");
$typeBreakdown = $stmt->fetchAll();

echo "📋 Cupons ativos: $totalActive\n";
foreach ($typeBreakdown as $type) {
    echo "   - {$type['type']}: {$type['count']} cupons\n";
}

// 7. LISTAR CUPONS ATIVOS
echo "\n🎯 CUPONS ATIVOS DISPONÍVEIS:\n";
echo "=============================\n";

$stmt = $pdo->query("
    SELECT code, name, type, value, minimum_amount, valid_until 
    FROM coupons 
    WHERE is_active = 1 
    ORDER BY type, value DESC
");

$coupons = $stmt->fetchAll();

foreach ($coupons as $coupon) {
    echo "🎫 {$coupon['code']} - {$coupon['name']}\n";
    echo "   Tipo: " . ucfirst($coupon['type']);
    if ($coupon['type'] === 'percentage') {
        echo " ({$coupon['value']}%)";
    } else {
        echo " ($" . number_format($coupon['value'], 2) . ")";
    }
    echo "\n   Mínimo: $" . number_format($coupon['minimum_amount'], 2);
    echo " | Válido até: {$coupon['valid_until']}\n\n";
}

echo "🎉 SISTEMA DE CUPONS INSTALADO COM SUCESSO!\n";
echo "===========================================\n";
echo "✅ Estrutura do banco criada\n";
echo "✅ Cupons padrão inseridos\n";
echo "✅ Sistema pronto para integração\n\n";

echo "📝 PRÓXIMOS PASSOS:\n";
echo "1. Implementar CouponManager.php\n";
echo "2. Integrar com PricingEngine.php\n";
echo "3. Atualizar StripeManager.php\n";
echo "4. Adicionar JavaScript no frontend\n";
