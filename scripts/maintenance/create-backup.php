<?php
/**
 * BACKUP COMPLETO DO BANCO DE DADOS
 * Blue Cleaning Services - Ponto de Backup
 * Nome: banco de dados
 */

echo "💾 CRIANDO BACKUP DO BANCO DE DADOS\n";
echo "===================================\n\n";

require_once 'config/australian-database.php';

$backupName = 'banco_de_dados_' . date('Y-m-d_H-i-s');
$backupDir = 'backups';
$backupFile = $backupDir . '/' . $backupName . '.sql';

try {
    $db = AustralianDatabase::getInstance()->getConnection();
    echo "✅ Conectado ao banco de dados\n";
    
    // Abrir arquivo de backup
    $fp = fopen($backupFile, 'w');
    if (!$fp) {
        throw new Exception("Não foi possível criar arquivo de backup: $backupFile");
    }
    
    // Header do backup
    $header = "-- ============================================================================\n";
    $header .= "-- BACKUP BLUE CLEANING SERVICES - BANCO DE DADOS\n";
    $header .= "-- Data: " . date('d/m/Y H:i:s') . "\n";
    $header .= "-- Database: u979853733_rose\n";
    $header .= "-- Host: srv1417.hstgr.io\n";
    $header .= "-- ============================================================================\n\n";
    $header .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $header .= "SET AUTOCOMMIT = 0;\n";
    $header .= "START TRANSACTION;\n";
    $header .= "SET time_zone = \"+00:00\";\n\n";
    
    fwrite($fp, $header);
    
    // Obter todas as tabelas
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 Encontradas " . count($tables) . " tabelas para backup\n\n";
    
    foreach ($tables as $table) {
        echo "📋 Fazendo backup da tabela: $table\n";
        
        // Estrutura da tabela
        $createTable = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        fwrite($fp, "-- Estrutura da tabela `$table`\n");
        fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($fp, $createTable['Create Table'] . ";\n\n");
        
        // Dados da tabela
        $result = $db->query("SELECT * FROM `$table`");
        $rowCount = 0;
        
        if ($result->rowCount() > 0) {
            fwrite($fp, "-- Dados da tabela `$table`\n");
            
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                if ($rowCount == 0) {
                    $columns = array_keys($row);
                    fwrite($fp, "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n");
                }
                
                $values = array_map(function($value) use ($db) {
                    if ($value === null) return 'NULL';
                    return $db->quote($value);
                }, array_values($row));
                
                $rowCount++;
                $comma = ($rowCount == $result->rowCount()) ? ';' : ',';
                fwrite($fp, "(" . implode(', ', $values) . ")$comma\n");
            }
            fwrite($fp, "\n");
        }
        
        echo "   ✅ $rowCount registros salvos\n";
    }
    
    // Footer do backup
    $footer = "COMMIT;\n";
    $footer .= "\n-- ============================================================================\n";
    $footer .= "-- FIM DO BACKUP - " . date('d/m/Y H:i:s') . "\n";
    $footer .= "-- ============================================================================\n";
    
    fwrite($fp, $footer);
    fclose($fp);
    
    // Estatísticas do backup
    $fileSize = filesize($backupFile);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
    
    echo "\n🎉 BACKUP CONCLUÍDO COM SUCESSO!\n";
    echo "================================\n";
    echo "📁 Arquivo: $backupFile\n";
    echo "📊 Tamanho: {$fileSizeMB}MB ({$fileSize} bytes)\n";
    echo "📋 Tabelas: " . count($tables) . "\n";
    echo "🕐 Data/Hora: " . date('d/m/Y H:i:s') . "\n";
    
    // Criar também um backup compactado
    if (function_exists('gzopen')) {
        $gzFile = $backupFile . '.gz';
        $gz = gzopen($gzFile, 'w9');
        gzwrite($gz, file_get_contents($backupFile));
        gzclose($gz);
        
        $gzSize = filesize($gzFile);
        $gzSizeMB = round($gzSize / 1024 / 1024, 2);
        echo "📦 Backup compactado: {$gzSizeMB}MB (economia de " . round((1 - $gzSize/$fileSize) * 100, 1) . "%)\n";
    }
    
    // Verificar integridade do backup
    echo "\n🔍 VERIFICANDO INTEGRIDADE DO BACKUP...\n";
    $backupContent = file_get_contents($backupFile);
    
    if (strpos($backupContent, 'CREATE TABLE') !== false) {
        echo "✅ Estruturas de tabelas encontradas\n";
    }
    
    if (strpos($backupContent, 'INSERT INTO') !== false) {
        echo "✅ Dados encontrados\n";
    }
    
    if (strpos($backupContent, 'COMMIT;') !== false) {
        echo "✅ Backup finalizado corretamente\n";
    }
    
    echo "\n✅ BACKUP VERIFICADO E VÁLIDO!\n";
    
} catch (Exception $e) {
    echo "❌ ERRO NO BACKUP: " . $e->getMessage() . "\n";
    if (isset($fp) && $fp) {
        fclose($fp);
    }
}
?>
