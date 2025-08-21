<?php
// Script temporário para verificar tabela cleaning_preferences
$host = 'localhost';
$dbname = 'blue_cleaning_au';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ESTRUTURA DA TABELA cleaning_preferences ===\n";
    $result = $pdo->query("DESCRIBE cleaning_preferences");
    foreach ($result as $row) {
        printf("%-20s %-15s %s\n", $row['Field'], $row['Type'], $row['Extra']);
    }
    
    echo "\n=== DADOS ATUAIS ===\n";
    $stmt = $pdo->query("SELECT * FROM cleaning_preferences ORDER BY sort_order");
    foreach ($stmt as $row) {
        printf("ID: %d | %s | Tipo: %s | Taxa: $%.2f | Status: %s\n", 
               $row['id'], $row['name'], $row['field_type'], $row['extra_fee'], $row['status']);
    }
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
