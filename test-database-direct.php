<?php
/**
 * TESTE DIRETO DO BANCO DE DADOS - BUSCAR ORIGEM DO A$85.00
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 TESTE DIRETO DO BANCO - BUSCAR ORIGEM DO A$85.00\n";
echo str_repeat("=", 60) . "\n";

try {
    // Usar as configurações padrão da classe
    $host = 'srv1417.hstgr.io';
    $port = '3306';
    $dbname = 'u979853733_rose';
    $username = 'u979853733_rose';
    $password = 'BlueM@rketing33';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    
    echo "🔗 Tentando conectar ao banco...\n";
    echo "   Host: $host:$port\n";
    echo "   Database: $dbname\n";
    echo "   User: $username\n\n";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
        PDO::ATTR_TIMEOUT => 30
    ];

    $connection = new PDO($dsn, $username, $password, $options);
    $connection->exec("SET time_zone = '+10:00'");
    
    echo "✅ CONEXÃO ESTABELECIDA COM SUCESSO!\n\n";

    // 1. VERIFICAR ESTRUTURA DA TABELA SERVICES
    echo "🔍 ESTRUTURA DA TABELA SERVICES:\n";
    $stmt = $connection->query("DESCRIBE services");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "   " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    echo "\n";

    // 2. BUSCAR TODOS OS SERVIÇOS
    echo "📋 TODOS OS SERVIÇOS NA TABELA:\n";
    $stmt = $connection->query("SELECT id, name, base_price, is_active FROM services ORDER BY id");
    $services = $stmt->fetchAll();
    
    if (empty($services)) {
        echo "   ❌ Nenhum serviço encontrado na tabela services\n";
    } else {
        foreach ($services as $service) {
            echo "   ID: " . $service['id'] . 
                 " | Nome: " . $service['name'] . 
                 " | Preço: $" . $service['base_price'] . 
                 " | Ativo: " . ($service['is_active'] ? 'Sim' : 'Não') . "\n";
                 
            // Marcar se este é o problema
            if ($service['id'] == 2 && $service['base_price'] == 85.00) {
                echo "      ❌ PROBLEMA ENCONTRADO! Serviço ID=2 tem preço $85.00\n";
                echo "      ❌ Deveria ser $265.00 conforme frontend\n";
            } elseif ($service['id'] == 2 && $service['base_price'] == 265.00) {
                echo "      ✅ Serviço ID=2 correto com $265.00\n";
            } elseif ($service['id'] == 2) {
                echo "      ⚠️ Serviço ID=2 tem preço inesperado: $" . $service['base_price'] . "\n";
            }
        }
    }
    echo "\n";

    // 3. BUSCAR ESPECIFICAMENTE O SERVIÇO ID=2
    echo "🎯 FOCO NO SERVIÇO ID=2:\n";
    $stmt = $connection->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([2]);
    $service2 = $stmt->fetch();
    
    if ($service2) {
        echo "   ✅ Serviço ID=2 encontrado:\n";
        foreach ($service2 as $key => $value) {
            echo "      $key: $value\n";
        }
        
        // Comparar com valor esperado
        if ($service2['base_price'] == 85.00) {
            echo "\n   ❌ PROBLEMA CONFIRMADO!\n";
            echo "      Database: $" . $service2['base_price'] . "\n";
            echo "      Frontend: $265.00\n";
            echo "      Diferença: Stripe recebe A$85.00 em vez de A$265.00\n";
        } elseif ($service2['base_price'] == 265.00) {
            echo "\n   ✅ BASE PRICE CORRETO NO BANCO!\n";
            echo "      Problema deve estar em outro lugar no fluxo\n";
        }
        
    } else {
        echo "   ❌ Serviço ID=2 não encontrado!\n";
    }
    echo "\n";

    // 4. VERIFICAR SE EXISTE TABELA COM CONFIGURAÇÕES DE PREÇO DIFERENTES
    echo "🔍 PROCURANDO OUTRAS TABELAS DE PREÇOS:\n";
    $stmt = $connection->query("SHOW TABLES LIKE '%price%' OR SHOW TABLES LIKE '%service%'");
    $tables = $stmt->fetchAll();
    
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "   Tabela encontrada: $tableName\n";
        
        // Se for uma tabela relacionada a preços, ver o conteúdo
        if (strpos($tableName, 'price') !== false || strpos($tableName, 'service') !== false) {
            try {
                $stmt2 = $connection->query("SELECT * FROM $tableName LIMIT 5");
                $rows = $stmt2->fetchAll();
                
                if (!empty($rows)) {
                    echo "      Primeiras linhas:\n";
                    foreach ($rows as $row) {
                        $preview = array_slice($row, 0, 3, true);
                        $previewStr = json_encode($preview);
                        echo "        " . substr($previewStr, 0, 80) . "\n";
                    }
                }
            } catch (Exception $e) {
                echo "      Erro ao consultar: " . $e->getMessage() . "\n";
            }
        }
    }

} catch (PDOException $e) {
    echo "❌ ERRO DE CONEXÃO: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "   ⚠️ Credenciais incorretas ou acesso negado\n";
    } elseif (strpos($e->getMessage(), 'Connection timed out') !== false) {
        echo "   ⚠️ Timeout na conexão - servidor pode estar offline\n";
    } elseif (strpos($e->getMessage(), "Can't connect") !== false) {
        echo "   ⚠️ Não foi possível conectar ao servidor\n";
    }
    
    echo "   💡 Possível que estejamos em ambiente local sem acesso ao Hostinger\n";
} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
}

echo "\n🎯 RESUMO:\n";
echo "Se base_price no banco = $85.00 → Este é o problema raiz\n";
echo "Se base_price no banco = $265.00 → Problema está no fluxo da API\n";
echo "Se sem conexão → Testar com dados de produção ou simular\n";

echo "\n" . str_repeat("=", 60) . "\n";
?>
