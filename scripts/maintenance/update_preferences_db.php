<?php
// Script para atualizar tabela cleaning_preferences com dados reais
$host = 'localhost';
$dbname = 'blue_cleaning_au';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Conexão falhou: " . $conn->connect_error);
    }
    
    echo "=== CONEXÃO ESTABELECIDA ===\n";
    
    // Verificar se a tabela existe
    $checkTable = $conn->query("SHOW TABLES LIKE 'cleaning_preferences'");
    if ($checkTable->num_rows === 0) {
        echo "Criando tabela cleaning_preferences...\n";
        
        $createTable = "
        CREATE TABLE cleaning_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT DEFAULT 1,
            name VARCHAR(255) NOT NULL,
            field_type ENUM('checkbox', 'select', 'text') NOT NULL,
            is_checked_default BOOLEAN DEFAULT FALSE,
            is_required BOOLEAN DEFAULT FALSE,
            extra_fee DECIMAL(10,2) DEFAULT 0.00,
            options TEXT,
            sort_order INT DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($createTable)) {
            echo "✓ Tabela criada com sucesso!\n";
        } else {
            throw new Exception("Erro ao criar tabela: " . $conn->error);
        }
    } else {
        echo "✓ Tabela cleaning_preferences existe.\n";
        
        // Verificar dados atuais
        $result = $conn->query("SELECT * FROM cleaning_preferences ORDER BY sort_order");
        echo "Registros atuais: " . $result->num_rows . "\n";
    }
    
    // Limpar dados existentes
    echo "\n=== LIMPANDO DADOS ANTIGOS ===\n";
    $conn->query("DELETE FROM cleaning_preferences");
    echo "✓ Dados antigos removidos.\n";
    
    // Inserir dados atualizados
    echo "\n=== INSERINDO NOVOS DADOS ===\n";
    
    $preferences = [
        // Preferências com taxas (checkboxes)
        [
            'name' => 'Eco-friendly products',
            'field_type' => 'checkbox',
            'extra_fee' => 5.00,
            'options' => 'Use only eco-friendly and non-toxic cleaning products',
            'sort_order' => 1
        ],
        [
            'name' => 'Professional chemicals',
            'field_type' => 'checkbox', 
            'extra_fee' => 15.00,
            'options' => 'Use professional-grade cleaning chemicals for enhanced results',
            'sort_order' => 2
        ],
        [
            'name' => 'Professional equipment',
            'field_type' => 'checkbox',
            'extra_fee' => 20.00,
            'options' => 'Use professional cleaning equipment and tools',
            'sort_order' => 3
        ],
        
        // Select com opções de preço
        [
            'name' => 'Service priority level',
            'field_type' => 'select',
            'extra_fee' => 0.00, // Será definido por opção
            'options' => '[\"Standard|0\", \"Priority|10.00\", \"Express|25.00\"]',
            'sort_order' => 4
        ],
        
        // Text com taxa para instruções especiais
        [
            'name' => 'Special cleaning instructions',
            'field_type' => 'text',
            'extra_fee' => 10.00,
            'options' => 'Additional fee for custom or special cleaning requests',
            'sort_order' => 5
        ],
        
        // Preferências sem taxa
        [
            'name' => 'Key collection method',
            'field_type' => 'select',
            'extra_fee' => 0.00,
            'options' => '[\"I will be home\", \"Hide key (specify location)\", \"Lockbox\", \"Property manager\", \"Spare key pickup\"]',
            'is_required' => true,
            'sort_order' => 6
        ],
        
        [
            'name' => 'Pet-friendly service',
            'field_type' => 'checkbox',
            'extra_fee' => 0.00,
            'options' => 'Our team is comfortable working around pets',
            'sort_order' => 7
        ],
        
        [
            'name' => 'Allergies or sensitivities',
            'field_type' => 'text',
            'extra_fee' => 0.00,
            'options' => 'Please specify any allergies or chemical sensitivities',
            'sort_order' => 8
        ]
    ];
    
    $insertStmt = $conn->prepare("
        INSERT INTO cleaning_preferences 
        (name, field_type, is_checked_default, is_required, extra_fee, options, sort_order, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    
    foreach ($preferences as $pref) {
        $is_checked_default = $pref['is_checked_default'] ?? false;
        $is_required = $pref['is_required'] ?? false;
        
        $insertStmt->bind_param(
            'ssddsis',
            $pref['name'],
            $pref['field_type'],
            $is_checked_default,
            $is_required,
            $pref['extra_fee'],
            $pref['options'],
            $pref['sort_order']
        );
        
        if ($insertStmt->execute()) {
            printf("✓ Inserido: %s (Taxa: $%.2f)\n", $pref['name'], $pref['extra_fee']);
        } else {
            printf("✗ Erro ao inserir: %s - %s\n", $pref['name'], $insertStmt->error);
        }
    }
    
    echo "\n=== VERIFICAÇÃO FINAL ===\n";
    $result = $conn->query("SELECT * FROM cleaning_preferences ORDER BY sort_order");
    while ($row = $result->fetch_assoc()) {
        printf("ID: %d | %s | Tipo: %s | Taxa: $%.2f\n", 
               $row['id'], $row['name'], $row['field_type'], $row['extra_fee']);
    }
    
    echo "\n✅ ATUALIZAÇÃO CONCLUÍDA COM SUCESSO!\n";
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
