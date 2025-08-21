<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "📋 ESTRUTURA REAL DA TABELA BOOKINGS:\n\n";
    
    $result = $pdo->query('DESCRIBE bookings');
    $campos = [];
    
    while ($row = $result->fetch()) {
        $campos[] = $row['Field'];
        echo "- " . $row['Field'] . " (" . $row['Type'] . ") - " . ($row['Null'] == 'YES' ? 'NULL OK' : 'NOT NULL') . "\n";
    }
    
    echo "\n📝 CAMPOS DISPONÍVEIS: " . implode(', ', $campos) . "\n";
    
    // Verificar dados recentes
    echo "\n🔍 ÚLTIMOS 5 REGISTROS:\n";
    $stmt = $pdo->query('SELECT * FROM bookings ORDER BY id DESC LIMIT 5');
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($registros)) {
        echo "❌ Nenhum registro encontrado na tabela.\n";
    } else {
        foreach ($registros as $registro) {
            echo "\nID: " . $registro['id'] . "\n";
            foreach ($registro as $campo => $valor) {
                echo "  $campo: " . ($valor ?: '[VAZIO]') . "\n";
            }
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
