<?php
/**
 * Verificar estrutura das tabelas referral
 */
require_once __DIR__ . '/config/australian-database.php';

try {
    $db = AustralianDatabase::getInstance();
    $connection = $db->getConnection();
    
    $tables = ['referral_users', 'referral_levels', 'referrals', 'referral_config'];
    
    foreach ($tables as $table) {
        echo "🗂️  ESTRUTURA DA TABELA: {$table}\n";
        echo str_repeat('=', 50) . "\n";
        
        $stmt = $connection->query("DESCRIBE `{$table}`");
        $columns = $stmt->fetchAll();
        
        foreach ($columns as $column) {
            $nullable = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $column['Default'] !== null ? "DEFAULT '{$column['Default']}'" : '';
            $extra = $column['Extra'] ? " ({$column['Extra']})" : '';
            
            echo sprintf("   %-20s %-15s %-10s %-15s %s\n", 
                $column['Field'], 
                $column['Type'], 
                $nullable, 
                $default,
                $extra
            );
        }
        
        echo "\n📊 DADOS ATUAIS:\n";
        $stmt = $connection->query("SELECT * FROM `{$table}` LIMIT 3");
        $data = $stmt->fetchAll();
        
        if (empty($data)) {
            echo "   (Nenhum registro encontrado)\n\n";
        } else {
            $headers = array_keys($data[0]);
            echo "   " . implode(" | ", $headers) . "\n";
            echo "   " . str_repeat('-', count($headers) * 15) . "\n";
            
            foreach ($data as $row) {
                $values = array_map(function($v) { 
                    return strlen($v) > 12 ? substr($v, 0, 12) . '...' : $v; 
                }, array_values($row));
                echo "   " . implode(" | ", $values) . "\n";
            }
        }
        echo "\n" . str_repeat('=', 70) . "\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
