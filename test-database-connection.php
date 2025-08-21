<?php
/**
 * Teste de Conectividade do Banco de Dados
 * Verifica se a conexão com o banco está funcionando
 */

echo "🔍 TESTANDO CONECTIVIDADE DO BANCO DE DADOS\n";
echo "==========================================\n\n";

try {
    // Teste 1: Configuração Principal (config.php)
    echo "📋 TESTE 1: Configuração Principal\n";
    echo "----------------------------------\n";
    
    require_once __DIR__ . '/config.php';
    
    $host = DB_HOST;
    $dbname = DB_NAME;
    $username = DB_USER;
    $password = DB_PASS;
    
    echo "Host: $host\n";
    echo "Database: $dbname\n";
    echo "Username: $username\n";
    echo "Password: " . (strlen($password) > 0 ? str_repeat('*', strlen($password)) : 'NÃO DEFINIDO') . "\n\n";
    
    // Tentativa de conexão
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "✅ CONEXÃO PRINCIPAL: SUCESSO\n";
    
    // Teste da estrutura básica
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Tabelas encontradas: " . count($tables) . "\n";
    if (count($tables) > 0) {
        echo "   • " . implode("\n   • ", array_slice($tables, 0, 10)) . "\n";
        if (count($tables) > 10) {
            echo "   • ... e mais " . (count($tables) - 10) . " tabelas\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO NA CONEXÃO PRINCIPAL: " . $e->getMessage() . "\n";
}

echo "\n";

try {
    // Teste 2: Configuração Australiana
    echo "📋 TESTE 2: Configuração Australiana\n";
    echo "------------------------------------\n";
    
    require_once __DIR__ . '/config/australian-database.php';
    
    $db = AustralianDatabase::getInstance();
    $connection = $db->getConnection();
    
    echo "✅ CONEXÃO AUSTRALIANA: SUCESSO\n";
    
    // Teste específico para tabelas do sistema de referência
    $referralTables = ['referral_users', 'referral_levels', 'bookings', 'professionals'];
    echo "📊 Verificando tabelas do sistema:\n";
    
    foreach ($referralTables as $table) {
        try {
            $stmt = $connection->query("SELECT COUNT(*) as count FROM $table LIMIT 1");
            $result = $stmt->fetch();
            echo "   ✅ $table: {$result['count']} registros\n";
        } catch (Exception $e) {
            echo "   ❌ $table: Erro - " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO NA CONEXÃO AUSTRALIANA: " . $e->getMessage() . "\n";
}

echo "\n";

try {
    // Teste 3: Verificação do arquivo .env
    echo "📋 TESTE 3: Arquivo .env\n";
    echo "------------------------\n";
    
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        echo "✅ Arquivo .env encontrado\n";
        
        $envContent = file_get_contents($envFile);
        $hasDbConfig = strpos($envContent, 'DB_HOST') !== false;
        
        if ($hasDbConfig) {
            echo "✅ Configurações do banco encontradas no .env\n";
        } else {
            echo "⚠️  Configurações do banco NÃO encontradas no .env\n";
        }
    } else {
        echo "❌ Arquivo .env NÃO encontrado\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO AO VERIFICAR .env: " . $e->getMessage() . "\n";
}

echo "\n📊 RESUMO DO TESTE:\n";
echo "===================\n";
echo "Status da conectividade será exibido acima.\n";
echo "Se houver ✅, a conexão está funcionando.\n";
echo "Se houver ❌, há problemas de conectividade.\n\n";

?>
