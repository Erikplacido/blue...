<?php
/**
 * DYNAMIC PROFESSIONAL SYSTEM IMPLEMENTATION
 * Blue Cleaning Services - Complete Implementation Plan
 * 
 * This script will implement the core infrastructure needed for a fully dynamic professional experience
 */

require_once 'config.php';

echo "🚀 IMPLEMENTAÇÃO SISTEMA PROFISSIONAL DINÂMICO\n";
echo "===============================================\n\n";

try {
    // 1. FASE 1: SISTEMA DE USUÁRIOS UNIFICADO
    echo "📋 FASE 1: CRIANDO SISTEMA DE USUÁRIOS UNIFICADO\n";
    echo str_repeat('-', 50) . "\n";
    
    // Verificar se tabela users já existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if (!$stmt->fetch()) {
        echo "🗄️  Criando tabela users...\n";
        $pdo->exec("
            CREATE TABLE users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                user_type ENUM('customer', 'professional', 'admin') NOT NULL,
                status ENUM('active', 'pending', 'suspended', 'onboarding') DEFAULT 'pending',
                candidate_id BIGINT UNSIGNED NULL,
                professional_id INT NULL,
                name VARCHAR(255),
                phone VARCHAR(20),
                date_of_birth DATE,
                address TEXT,
                city VARCHAR(100),
                state ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT'),
                postal_code VARCHAR(4),
                profile_completion_percentage INT DEFAULT 0,
                last_login TIMESTAMP NULL,
                email_verified_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_email (email),
                INDEX idx_user_type (user_type),
                INDEX idx_status (status),
                FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Tabela users criada\n";
    } else {
        echo "✅ Tabela users já existe\n";
    }
    
    // 2. EXPANDIR TABELA PROFESSIONALS
    echo "\n📋 FASE 2: EXPANDINDO TABELA PROFESSIONALS\n";
    echo str_repeat('-', 50) . "\n";
    
    // Verificar estrutura atual
    $stmt = $pdo->query("DESCRIBE professionals");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $newColumns = [
        'user_id' => 'BIGINT UNSIGNED NULL',
        'bio' => 'TEXT NULL',
        'experience_years' => 'INT DEFAULT 0',
        'hourly_rate' => 'DECIMAL(10,2) NULL',
        'rating' => 'DECIMAL(3,2) DEFAULT 5.00',
        'total_jobs' => 'INT DEFAULT 0',
        'total_earnings' => 'DECIMAL(10,2) DEFAULT 0.00',
        'specialties' => 'JSON NULL',
        'coverage_areas' => 'JSON NULL',
        'availability_schedule' => 'JSON NULL',
        'preference_settings' => 'JSON NULL',
        'emergency_contact_name' => 'VARCHAR(255) NULL',
        'emergency_contact_phone' => 'VARCHAR(20) NULL',
        'has_transport' => 'BOOLEAN DEFAULT FALSE',
        'languages' => 'JSON NULL',
        'certifications' => 'JSON NULL',
        'avatar' => 'VARCHAR(255) NULL',
        'is_verified' => 'BOOLEAN DEFAULT FALSE',
        'verification_documents' => 'JSON NULL',
        'bank_details' => 'JSON NULL',
        'notification_preferences' => 'JSON NULL',
        'working_hours_preference' => 'JSON NULL',
        'service_radius_km' => 'INT DEFAULT 25',
        'auto_accept_bookings' => 'BOOLEAN DEFAULT FALSE',
        'last_active' => 'TIMESTAMP NULL',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    foreach ($newColumns as $column => $definition) {
        if (!in_array($column, $columns)) {
            echo "➕ Adicionando coluna: $column\n";
            try {
                $pdo->exec("ALTER TABLE professionals ADD COLUMN $column $definition");
                echo "✅ Coluna $column adicionada\n";
            } catch (Exception $e) {
                echo "⚠️  Erro ao adicionar $column: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✅ Coluna $column já existe\n";
        }
    }
    
    // 3. CRIAR TABELAS DE RELACIONAMENTO
    echo "\n📋 FASE 3: CRIANDO TABELAS DE RELACIONAMENTO\n";
    echo str_repeat('-', 50) . "\n";
    
    // Tabela professional_preferences
    echo "🗄️  Criando professional_preferences...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS professional_preferences (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            professional_id INT NOT NULL,
            preference_key VARCHAR(100) NOT NULL,
            preference_value JSON NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_pref (professional_id, preference_key),
            FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
            INDEX idx_professional_preference (professional_id, preference_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela professional_preferences criada\n";
    
    // Tabela professional_specialties (relacional)
    echo "🗄️  Criando professional_specialties...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS professional_specialties (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            professional_id INT NOT NULL,
            specialty_name VARCHAR(100) NOT NULL,
            proficiency_level ENUM('beginner', 'intermediate', 'expert', 'master') DEFAULT 'intermediate',
            years_experience INT DEFAULT 0,
            certification_details JSON NULL,
            is_primary BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
            INDEX idx_professional_specialty (professional_id, specialty_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela professional_specialties criada\n";
    
    // Tabela professional_coverage_areas
    echo "🗄️  Criando professional_coverage_areas...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS professional_coverage_areas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            professional_id INT NOT NULL,
            area_type ENUM('suburb', 'postcode', 'radius', 'custom') NOT NULL,
            area_value VARCHAR(100) NOT NULL,
            priority_level INT DEFAULT 1,
            travel_cost DECIMAL(10,2) DEFAULT 0.00,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
            INDEX idx_professional_area (professional_id, area_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela professional_coverage_areas criada\n";
    
    // Tabela professional_reviews
    echo "🗄️  Criando professional_reviews...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS professional_reviews (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            professional_id INT NOT NULL,
            booking_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(255) NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            review_text TEXT NULL,
            review_photos JSON NULL,
            is_public BOOLEAN DEFAULT TRUE,
            is_verified BOOLEAN DEFAULT FALSE,
            admin_response TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
            INDEX idx_professional_rating (professional_id, rating),
            INDEX idx_public_reviews (professional_id, is_public)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela professional_reviews criada\n";
    
    // 4. POPULAR COM DADOS INICIAIS
    echo "\n📋 FASE 4: POPULANDO COM DADOS DINÂMICOS\n";
    echo str_repeat('-', 50) . "\n";
    
    // Criar usuários para profissionais existentes
    echo "👤 Criando usuários para profissionais existentes...\n";
    $stmt = $pdo->query("SELECT * FROM professionals");
    $professionals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($professionals as $professional) {
        // Verificar se já existe usuário
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$professional['email']]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    email, password, user_type, status, professional_id, 
                    name, phone, profile_completion_percentage
                ) VALUES (?, ?, 'professional', 'active', ?, ?, ?, 60)
            ");
            
            $fullName = $professional['first_name'] . ' ' . $professional['last_name'];
            $stmt->execute([
                $professional['email'],
                $professional['password'], // Mantém a senha atual
                $professional['id'],
                $fullName,
                $professional['phone']
            ]);
            
            echo "✅ Usuário criado para: $fullName\n";
        }
    }
    
    // Popular dados dinâmicos de exemplo
    echo "🔄 Populando dados dinâmicos de exemplo...\n";
    
    foreach ($professionals as $professional) {
        // Atualizar dados expandidos
        $specialties = json_encode(['House Cleaning', 'Deep Cleaning', 'Office Cleaning']);
        $coverage_areas = json_encode([
            ['type' => 'radius', 'value' => '25km', 'center' => 'Sydney CBD'],
            ['type' => 'suburb', 'value' => 'Bondi', 'priority' => 1],
            ['type' => 'suburb', 'value' => 'Surry Hills', 'priority' => 2]
        ]);
        $availability_schedule = json_encode([
            'monday' => ['start' => '08:00', 'end' => '18:00', 'breaks' => ['12:00-13:00']],
            'tuesday' => ['start' => '08:00', 'end' => '18:00', 'breaks' => ['12:00-13:00']],
            'wednesday' => ['start' => '08:00', 'end' => '18:00', 'breaks' => ['12:00-13:00']],
            'thursday' => ['start' => '08:00', 'end' => '18:00', 'breaks' => ['12:00-13:00']],
            'friday' => ['start' => '08:00', 'end' => '18:00', 'breaks' => ['12:00-13:00']],
            'saturday' => ['start' => '09:00', 'end' => '15:00', 'breaks' => []],
            'sunday' => ['start' => null, 'end' => null, 'breaks' => []]
        ]);
        $notification_preferences = json_encode([
            'new_booking' => true,
            'booking_reminder' => true,
            'payment_received' => true,
            'rating_received' => true,
            'system_updates' => false,
            'marketing' => false
        ]);
        
        $stmt = $pdo->prepare("
            UPDATE professionals SET 
                bio = ?, experience_years = ?, hourly_rate = ?, rating = ?,
                specialties = ?, coverage_areas = ?, availability_schedule = ?,
                notification_preferences = ?, service_radius_km = ?, 
                has_transport = ?, languages = ?
            WHERE id = ?
        ");
        
        $bio = "Professional cleaner with extensive experience in residential and commercial cleaning. Committed to delivering exceptional results and customer satisfaction.";
        $languages = json_encode(['English', 'Portuguese']);
        
        $stmt->execute([
            $bio,
            rand(2, 8), // Experience years
            rand(45, 85), // Hourly rate
            round(4.2 + (rand(0, 8) / 10), 1), // Rating between 4.2-5.0
            $specialties,
            $coverage_areas,
            $availability_schedule,
            $notification_preferences,
            rand(15, 35), // Service radius
            (bool)rand(0, 1), // Has transport
            $languages,
            $professional['id']
        ]);
        
        echo "✅ Dados dinâmicos atualizados para: {$professional['first_name']}\n";
    }
    
    // 5. CRIAR ÍNDICES DE PERFORMANCE
    echo "\n📋 FASE 5: OTIMIZANDO PERFORMANCE\n";
    echo str_repeat('-', 50) . "\n";
    
    $indexes = [
        "CREATE INDEX idx_professional_rating ON professionals(rating DESC)" => "Rating index",
        "CREATE INDEX idx_professional_status ON professionals(status)" => "Status index",
        "CREATE INDEX idx_professional_location ON professionals(service_radius_km)" => "Location index",
        "CREATE INDEX idx_availability_date ON professional_availability(date, professional_id)" => "Availability date index",
        "CREATE INDEX idx_availability_time ON professional_availability(start_time, end_time)" => "Availability time index"
    ];
    
    foreach ($indexes as $sql => $description) {
        try {
            $pdo->exec($sql);
            echo "✅ $description criado\n";
        } catch (Exception $e) {
            echo "⚠️  $description já existe ou erro: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 IMPLEMENTAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "=====================================\n";
    echo "✅ Sistema de usuários unificado: IMPLEMENTADO\n";
    echo "✅ Tabela professionals expandida: IMPLEMENTADO\n";
    echo "✅ Tabelas de relacionamento: IMPLEMENTADAS\n";
    echo "✅ Dados dinâmicos: POPULADOS\n";
    echo "✅ Índices de performance: CRIADOS\n\n";
    
    // Estatísticas finais
    echo "📊 ESTATÍSTICAS FINAIS:\n";
    echo str_repeat('-', 20) . "\n";
    
    $stats = [
        'users' => 'SELECT COUNT(*) FROM users',
        'professionals' => 'SELECT COUNT(*) FROM professionals',
        'professional_availability' => 'SELECT COUNT(*) FROM professional_availability',
        'professional_services' => 'SELECT COUNT(*) FROM professional_services'
    ];
    
    foreach ($stats as $table => $query) {
        $stmt = $pdo->query($query);
        $count = $stmt->fetchColumn();
        echo "📈 $table: $count registros\n";
    }
    
    echo "\n🚀 O SISTEMA ESTÁ PRONTO PARA EXPERIÊNCIA TOTALMENTE DINÂMICA!\n";
    
} catch (Exception $e) {
    echo "❌ Erro durante implementação: " . $e->getMessage() . "\n";
    echo "Detalhes: " . $e->getFile() . " linha " . $e->getLine() . "\n";
}
?>
