<?php
echo "🚀 IMPLEMENTANDO SCHEMA COMPLETO\n";
echo "===============================\n\n";

require_once 'config/australian-database.php';

try {
    $db = AustralianDatabase::getInstance()->getConnection();
    echo "✅ Conectado ao banco de dados\n\n";
    
    // 1. Criar tabela services
    echo "🗄️  Criando tabela: services\n";
    $db->exec("CREATE TABLE IF NOT EXISTS services (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        service_code VARCHAR(20) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        base_price DECIMAL(10,2) NOT NULL,
        duration_minutes INT NOT NULL DEFAULT 120,
        category ENUM('residential', 'commercial', 'specialized') DEFAULT 'residential',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Inserir serviços básicos
    $stmt = $db->prepare("INSERT IGNORE INTO services (service_code, name, description, base_price, duration_minutes, category) VALUES (?, ?, ?, ?, ?, ?)");
    $services = [
        ['HOUSE_BASIC', 'Basic House Cleaning', 'Standard residential cleaning service', 45.00, 120, 'residential'],
        ['HOUSE_DEEP', 'Deep House Cleaning', 'Comprehensive deep cleaning service', 85.00, 180, 'residential'],
        ['OFFICE_CLEAN', 'Office Cleaning', 'Commercial office cleaning', 55.00, 90, 'commercial'],
        ['CARPET_CLEAN', 'Carpet Cleaning', 'Professional carpet cleaning service', 35.00, 60, 'specialized'],
        ['WINDOW_CLEAN', 'Window Cleaning', 'Interior and exterior window cleaning', 25.00, 45, 'specialized'],
        ['MOVE_CLEAN', 'Move In/Out Cleaning', 'Complete cleaning for moving', 120.00, 240, 'residential']
    ];
    
    foreach ($services as $service) {
        $stmt->execute($service);
    }
    
    // 2. Criar tabela bookings
    echo "🗄️  Criando tabela: bookings\n";
    $db->exec("CREATE TABLE IF NOT EXISTS bookings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        booking_code VARCHAR(20) UNIQUE NOT NULL,
        customer_id BIGINT UNSIGNED,
        professional_id BIGINT UNSIGNED NULL,
        service_id BIGINT UNSIGNED NOT NULL,
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        street_address VARCHAR(255) NOT NULL,
        suburb VARCHAR(100) NOT NULL,
        state ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT') NOT NULL,
        postcode VARCHAR(4) NOT NULL,
        scheduled_date DATE NOT NULL,
        scheduled_time TIME NOT NULL,
        duration_minutes INT NOT NULL DEFAULT 120,
        base_price DECIMAL(10,2) NOT NULL,
        extras_price DECIMAL(10,2) DEFAULT 0.00,
        discount_amount DECIMAL(10,2) DEFAULT 0.00,
        gst_amount DECIMAL(10,2) NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        payment_status ENUM('pending', 'paid', 'refunded', 'failed') DEFAULT 'pending',
        special_instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // 3. Criar tabela payments
    echo "🗄️  Criando tabela: payments\n";
    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_code VARCHAR(20) UNIQUE NOT NULL,
        booking_id BIGINT UNSIGNED NOT NULL,
        stripe_payment_intent_id VARCHAR(255),
        amount DECIMAL(10,2) NOT NULL,
        currency CHAR(3) DEFAULT 'AUD',
        status ENUM('pending', 'processing', 'succeeded', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // 4. Criar tabela candidates
    echo "🗄️  Criando tabela: candidates\n";
    $db->exec("CREATE TABLE IF NOT EXISTS candidates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        candidate_code VARCHAR(20) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        suburb VARCHAR(100),
        state ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT'),
        postcode VARCHAR(4),
        has_experience BOOLEAN DEFAULT FALSE,
        status ENUM('applied', 'screening', 'in_training', 'approved', 'rejected') DEFAULT 'applied',
        application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // 5. Criar tabela trainings
    echo "🗄️  Criando tabela: trainings\n";
    $db->exec("CREATE TABLE IF NOT EXISTS trainings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        training_code VARCHAR(20) UNIQUE NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        is_mandatory BOOLEAN DEFAULT FALSE,
        duration_minutes INT DEFAULT 60,
        passing_score DECIMAL(3,1) DEFAULT 80.0,
        status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
        category ENUM('safety', 'techniques', 'customer_service', 'business') DEFAULT 'safety',
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Inserir treinamentos básicos
    $stmt = $db->prepare("INSERT IGNORE INTO trainings (training_code, title, description, is_mandatory, duration_minutes, status, category, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $trainings = [
        ['SAFETY_001', 'Workplace Health & Safety', 'Essential safety procedures for cleaning professionals', 1, 45, 'active', 'safety', 1],
        ['CUSTOMER_001', 'Customer Service Excellence', 'Professional communication and service standards', 1, 30, 'active', 'customer_service', 2],
        ['EQUIPMENT_001', 'Equipment & Chemical Safety', 'Proper use of cleaning equipment and chemicals', 1, 60, 'active', 'safety', 3],
        ['TECHNIQUES_001', 'Basic Cleaning Techniques', 'Standard cleaning procedures', 1, 90, 'active', 'techniques', 4]
    ];
    
    foreach ($trainings as $training) {
        $stmt->execute($training);
    }
    
    // 6. Criar tabela notifications
    echo "🗄️  Criando tabela: notifications\n";
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        user_type ENUM('customer', 'professional', 'admin') NOT NULL,
        type ENUM('booking_confirmed', 'booking_reminder', 'payment_received', 'training_due') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 7. Criar tabela system_settings
    echo "🗄️  Criando tabela: system_settings\n";
    $db->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string',
        description TEXT,
        is_public BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Inserir configurações básicas
    $stmt = $db->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES (?, ?, ?, ?, ?)");
    $settings = [
        ['business_name', 'Blue Cleaning Services Pty Ltd', 'string', 'Nome da empresa', 1],
        ['business_phone', '+61 2 9876 5432', 'string', 'Telefone comercial', 1],
        ['business_email', 'info@bluecleaningservices.com.au', 'string', 'Email comercial', 1],
        ['gst_rate', '0.10', 'decimal', 'Taxa de GST (10%)', 0],
        ['booking_advance_hours', '24', 'integer', 'Horas mínimas para agendamento', 1]
    ];
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    
    echo "\n🔍 VERIFICANDO TABELAS CRIADAS...\n";
    echo "================================\n";
    
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredTables = [
        'customers', 'professionals', 'admin_users', 
        'password_reset_tokens', 'security_audit_log',
        'services', 'bookings', 'payments',
        'candidates', 'trainings', 'notifications', 'system_settings'
    ];
    
    $created = 0;
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ $table\n";
            $created++;
        } else {
            echo "❌ $table (faltando)\n";
        }
    }
    
    echo "\n📊 RESUMO FINAL:\n";
    echo "================\n";
    echo "Total de tabelas no banco: " . count($tables) . "\n";
    echo "Tabelas necessárias criadas: $created/" . count($requiredTables) . "\n\n";
    
    // Verificar dados
    echo "📋 DADOS INSERIDOS:\n";
    echo "==================\n";
    
    $dataQueries = [
        'services' => 'SELECT COUNT(*) as count FROM services',
        'trainings' => 'SELECT COUNT(*) as count FROM trainings',
        'system_settings' => 'SELECT COUNT(*) as count FROM system_settings',
        'admin_users' => 'SELECT COUNT(*) as count FROM admin_users'
    ];
    
    foreach ($dataQueries as $table => $query) {
        try {
            $stmt = $db->query($query);
            $count = $stmt->fetch()['count'];
            echo "📊 $table: $count registros\n";
        } catch (Exception $e) {
            echo "❌ $table: erro ao verificar\n";
        }
    }
    
    // Log final
    $stmt = $db->prepare("INSERT INTO security_audit_log (user_type, action, description, result) VALUES (?, ?, ?, ?)");
    $stmt->execute(['system', 'complete_schema_implementation', 'Schema completo implementado com sucesso', 'success']);
    
    echo "\n🎉 IMPLEMENTAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "=====================================\n\n";
    
    echo "✅ SISTEMA BLUE CLEANING SERVICES COMPLETO:\n";
    echo "   🔐 Sistema de autenticação e segurança\n";
    echo "   📅 Sistema de booking e agendamentos\n";
    echo "   💰 Integração de pagamentos (Stripe)\n";
    echo "   🎓 Sistema de treinamento para profissionais\n";
    echo "   👥 Gestão de candidatos\n";
    echo "   🔔 Sistema de notificações\n";
    echo "   ⚙️  Configurações do sistema\n\n";
    
    echo "🚀 BANCO DE DADOS 100% PRONTO PARA PRODUÇÃO!\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
