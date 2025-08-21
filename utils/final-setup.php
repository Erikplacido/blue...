<?php
echo "🔧 Blue Cleaning Services - Setup Final\n";
echo "======================================\n\n";

$host = 'srv1417.hstgr.io';
$port = 3306;
$database = 'u979853733_rose';
$username = 'u979853733_rose';
$password = 'BlueM@rketing33';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conectado ao banco de dados!\n\n";
    
    // Criar as tabelas principais uma por uma
    echo "🗄️  Criando tabela: customers\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive') DEFAULT 'active'
    )");
    
    echo "🗄️  Criando tabela: professionals\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS professionals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive', 'pending') DEFAULT 'pending'
    )");
    
    echo "🗄️  Criando tabela: admin_users\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        role VARCHAR(50) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive') DEFAULT 'active'
    )");
    
    echo "🗄️  Criando tabela: password_reset_tokens\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        user_type ENUM('customer', 'professional', 'admin') NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at TIMESTAMP NOT NULL,
        used BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "🗄️  Criando tabela: security_audit_log\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS security_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        user_type ENUM('customer', 'professional', 'admin', 'system'),
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        result ENUM('success', 'failure', 'error') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "🗄️  Criando tabela: login_attempts\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        success BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Inserir usuário admin
    echo "👤 Criando usuário admin...\n";
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_users (email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        'admin@bluecleaningservices.com.au',
        password_hash('password', PASSWORD_DEFAULT),
        'Sistema',
        'Admin',
        'super_admin'
    ]);
    
    // Verificar tabelas criadas
    echo "\n🔍 Verificando tabelas criadas...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "   ✅ $table\n";
    }
    
    echo "\n📊 Total de tabelas: " . count($tables) . "\n\n";
    
    // Log de instalação
    $stmt = $pdo->prepare("INSERT INTO security_audit_log (user_type, action, description, result) VALUES (?, ?, ?, ?)");
    $stmt->execute(['system', 'database_setup', 'Schema de autenticação instalado', 'success']);
    
    echo "🎉 SETUP COMPLETADO COM SUCESSO!\n";
    echo "================================\n\n";
    echo "✅ Sistema de autenticação configurado\n";
    echo "✅ Usuário admin criado\n";
    echo "   📧 Email: admin@bluecleaningservices.com.au\n";
    echo "   🔑 Senha: password (ALTERAR!)\n\n";
    echo "🚀 Sistema pronto para produção!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
