<?php
/**
 * Criar Professional de Teste para o Sistema
 */

require_once 'config.php';

try {
    // Verificar se já existe um professional com ID 1
    $stmt = $pdo->prepare("SELECT id FROM professionals WHERE id = 1");
    $stmt->execute();
    
    if ($stmt->fetch()) {
        echo "✅ Professional com ID 1 já existe!\n";
    } else {
        echo "🔧 Criando professional de teste...\n";
        
        // Criar professional de teste
        $stmt = $pdo->prepare("
            INSERT INTO professionals (id, email, password, first_name, last_name, phone, status) 
            VALUES (1, 'test@professional.com', ?, 'Test', 'Professional', '+61412345678', 'active')
        ");
        
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $stmt->execute([$hashedPassword]);
        
        echo "✅ Professional de teste criado com sucesso!\n";
        echo "   Email: test@professional.com\n";
        echo "   Password: password123\n";
        echo "   ID: 1\n";
    }
    
    // Verificar tabelas relacionadas e criar se necessário
    echo "\n🔍 Verificando tabelas relacionadas...\n";
    
    // Criar tabela professional_reviews se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS professional_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        professional_id INT NOT NULL,
        customer_id INT,
        rating DECIMAL(2,1) NOT NULL,
        review_text TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (professional_id) REFERENCES professionals(id)
    )");
    echo "✅ Tabela professional_reviews verificada\n";
    
    // Criar tabela bookings se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        professional_id INT,
        service_name VARCHAR(255),
        status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
        scheduled_date DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (professional_id) REFERENCES professionals(id)
    )");
    echo "✅ Tabela bookings verificada\n";
    
    // Inserir alguns dados de exemplo
    echo "\n📊 Inserindo dados de exemplo...\n";
    
    // Review de exemplo
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO professional_reviews (professional_id, rating, review_text) 
        VALUES (1, 4.5, 'Excellent service!')
    ");
    $stmt->execute();
    echo "✅ Review de exemplo adicionada\n";
    
    // Booking de exemplo
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM bookings WHERE professional_id = 1
    ");
    $stmt->execute();
    $bookingCount = $stmt->fetch()['count'];
    
    if ($bookingCount == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO bookings (
                booking_code, customer_name, customer_email, customer_phone,
                street_address, suburb, state, postcode,
                professional_id, service_id, 
                scheduled_date, scheduled_time, duration_minutes,
                base_price, gst_amount, total_amount, status
            ) 
            VALUES (
                'TEST001', 'Test Customer', 'test@customer.com', '+61412345678',
                '123 Test Street', 'Sydney', 'NSW', '2000',
                1, 1,
                DATE_SUB(NOW(), INTERVAL 7 DAY), '10:00:00', 120,
                150.00, 15.00, 165.00, 'completed'
            )
        ");
        $stmt->execute();
        echo "✅ Booking de exemplo adicionado\n";
    } else {
        echo "✅ Bookings já existem para professional ID 1\n";
    }
    
    echo "\n🎉 Setup completo! Agora você pode testar a URL:\n";
    echo "   /professional/dynamic-dashboard.php?professional_id=1\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
