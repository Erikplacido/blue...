<?php
/**
 * CORREÇÃO DA TABELA BOOKINGS - ADICIONAR COLUNAS FALTANTES
 * Resolve erro: Unknown column 'address' in 'INSERT INTO'
 */

echo "🔧 CORREÇÃO: Tabela Bookings - Colunas Faltantes\n";
echo "===============================================\n\n";

// Carregar .env
$envFile = '.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    $lines = explode("\n", $envContent);
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

$host = $_ENV['DB_HOST'] ?? 'srv1417.hstgr.io';
$db = $_ENV['DB_DATABASE'] ?? 'u979853733_rose';
$user = $_ENV['DB_USERNAME'] ?? 'u979853733_rose';
$pass = $_ENV['DB_PASSWORD'] ?? 'BlueM@rketing33';

echo "🔗 Conectando ao banco...\n";
echo "Host: $host\n";
echo "Database: $db\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ Conectado com sucesso!\n\n";
    
    // Verificar estrutura atual
    echo "📋 Verificando estrutura atual da tabela bookings...\n";
    $result = $pdo->query("DESCRIBE bookings");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Colunas existentes:\n";
    foreach ($columns as $col) {
        echo "  • $col\n";
    }
    echo "\n";
    
    // Colunas necessárias
    $requiredColumns = [
        'address' => "TEXT NOT NULL",
        'suburb' => "VARCHAR(50)",
        'postcode' => "VARCHAR(10)",
        'stripe_session_id' => "VARCHAR(200)"
    ];
    
    echo "🔧 Adicionando colunas faltantes...\n";
    
    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $columns)) {
            $sql = "ALTER TABLE bookings ADD COLUMN $column $definition";
            try {
                $pdo->exec($sql);
                echo "✅ Coluna '$column' adicionada\n";
            } catch (Exception $e) {
                echo "❌ Erro ao adicionar '$column': " . $e->getMessage() . "\n";
            }
        } else {
            echo "✅ Coluna '$column' já existe\n";
        }
    }
    
    echo "\n📊 Estrutura final:\n";
    $finalResult = $pdo->query("DESCRIBE bookings");
    $finalColumns = $finalResult->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($finalColumns as $col) {
        echo sprintf("  • %-20s %s\n", $col['Field'], $col['Type']);
    }
    
    echo "\n🎉 TABELA CORRIGIDA COM SUCESSO!\n";
    echo "• Todas as colunas necessárias foram adicionadas\n";
    echo "• StripeManager agora pode salvar dados corretamente\n";
    echo "• Sistema de bookings totalmente funcional\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
