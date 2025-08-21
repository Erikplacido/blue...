<?php
/**
 * Verificar dados específicos do booking ID 41
 */

try {
    require_once __DIR__ . '/config.php';
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "🔍 VERIFICANDO BOOKING ID 42\n";
    echo "============================\n\n";
    
    // Consultar booking específico
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = 42");
    $stmt->execute();
    $booking = $stmt->fetch();
    
    if ($booking) {
        echo "✅ BOOKING ID 42 ENCONTRADO:\n";
        echo "-----------------------------\n";
        echo "ID: " . $booking['id'] . "\n";
        echo "Customer Name: " . $booking['customer_name'] . "\n";
        echo "Email: " . $booking['email'] . "\n";
        echo "Phone: " . $booking['phone'] . "\n";
        echo "Service Date: " . $booking['service_date'] . "\n";
        echo "Service Time: " . $booking['service_time'] . "\n";
        echo "Total Amount: $" . number_format($booking['total_amount'], 2) . "\n";
        echo "Referral Code: '" . ($booking['referral_code'] ?? 'NULL') . "'\n";
        echo "Created At: " . $booking['created_at'] . "\n";
        echo "Status: " . $booking['status'] . "\n\n";
        
        // Verificar se referral_code está vazio
        if (empty($booking['referral_code'])) {
            echo "❌ PROBLEMA CONFIRMADO: referral_code está VAZIO/NULL\n";
        } else {
            echo "✅ referral_code preenchido: '" . $booking['referral_code'] . "'\n";
        }
        
    } else {
        echo "❌ BOOKING ID 42 NÃO ENCONTRADO\n";
        
        // Mostrar os últimos bookings
        echo "\n📋 ÚLTIMOS BOOKINGS:\n";
        echo "--------------------\n";
        $stmt = $pdo->query("SELECT id, customer_name, referral_code, created_at FROM bookings ORDER BY id DESC LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "ID {$row['id']}: {$row['customer_name']} | referral_code: '" . ($row['referral_code'] ?? 'NULL') . "' | {$row['created_at']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
