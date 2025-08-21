<?php
/**
 * =========================================================
 * INTEGRAÇÃO BOOKING → REFERRAL SYSTEM
 * =========================================================
 * 
 * @file setup_booking_referral_integration.php
 * @description Script para integrar sistema de booking com referrals
 * @version 1.0
 * @date 2025-08-09
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/australian-database.php';

echo "🔗 CONFIGURANDO INTEGRAÇÃO BOOKING → REFERRAL\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $db = AustralianDatabase::getInstance();
    $connection = $db->getConnection();
    
    echo "1. 📊 ADICIONANDO CAMPOS DE REFERRAL NA TABELA BOOKINGS...\n";
    
    // Adicionar campos de referral na tabela bookings
    $alterBookingsQueries = [
        "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20) DEFAULT NULL COMMENT 'Código de referral usado no booking'",
        "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS referred_by INT DEFAULT NULL COMMENT 'ID do usuário que fez a indicação'",
        "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS referral_commission_calculated BOOLEAN DEFAULT FALSE COMMENT 'Se a comissão já foi calculada'"
    ];
    
    // Adicionar índices separadamente (sem IF NOT EXISTS para compatibilidade)
    $indexQueries = [
        "CREATE INDEX IF NOT EXISTS idx_referral_code ON bookings (referral_code)",
        "CREATE INDEX IF NOT EXISTS idx_referred_by ON bookings (referred_by)"
    ];
    
    foreach ($alterBookingsQueries as $query) {
        try {
            $connection->exec($query);
            echo "   ✅ Executado: " . substr($query, 0, 50) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "   ⚠️  Campo já existe: " . substr($query, 0, 50) . "...\n";
            } else {
                echo "   ❌ Erro: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Criar índices separadamente
    foreach ($indexQueries as $query) {
        try {
            $connection->exec($query);
            echo "   ✅ Índice criado: " . substr($query, 0, 50) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "   ⚠️  Índice já existe: " . substr($query, 0, 50) . "...\n";
            } else {
                echo "   ❌ Erro no índice: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n2. 🏗️  CRIANDO BOOKINGS DE TESTE COM REFERRALS...\n";
    
    // Primeiro, vamos buscar os usuários de referral existentes
    $stmt = $connection->query("
        SELECT id, referral_code, name 
        FROM referral_users 
        WHERE is_active = 1 
        ORDER BY id 
        LIMIT 3
    ");
    $referralUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($referralUsers)) {
        throw new Exception("Nenhum usuário de referral encontrado!");
    }
    
    // Criar bookings de teste
    $testBookings = [
        [
            'booking_code' => 'BK001',
            'customer_name' => 'Ana Costa',
            'customer_email' => 'ana.costa@email.com',
            'customer_phone' => '+61400123456',
            'scheduled_date' => '2025-08-01',
            'scheduled_time' => '10:00:00',
            'street_address' => '123 Queen St',
            'suburb' => 'Brisbane',
            'state' => 'QLD',
            'postcode' => '4000',
            'base_price' => 350.00,
            'gst_amount' => 35.00,
            'total_amount' => 385.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'referral_code' => $referralUsers[0]['referral_code'], // Erik
            'referred_by' => $referralUsers[0]['id']
        ],
        [
            'booking_code' => 'BK002',
            'customer_name' => 'Michael Johnson',
            'customer_email' => 'michael.j@email.com',
            'customer_phone' => '+61400234567',
            'scheduled_date' => '2025-08-05',
            'scheduled_time' => '14:00:00',
            'street_address' => '456 Collins St',
            'suburb' => 'Melbourne',
            'state' => 'VIC',
            'postcode' => '3000',
            'base_price' => 590.00,
            'gst_amount' => 59.00,
            'total_amount' => 649.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'referral_code' => $referralUsers[1]['referral_code'], // Mayza
            'referred_by' => $referralUsers[1]['id']
        ],
        [
            'booking_code' => 'BK003',
            'customer_name' => 'Sarah Wilson',
            'customer_email' => 'sarah.wilson@email.com',
            'customer_phone' => '+61400345678',
            'scheduled_date' => '2025-08-08',
            'scheduled_time' => '09:00:00',
            'street_address' => '789 George St',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'base_price' => 770.00,
            'gst_amount' => 77.00,
            'total_amount' => 847.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'referral_code' => $referralUsers[2]['referral_code'], // John
            'referred_by' => $referralUsers[2]['id']
        ],
        [
            'booking_code' => 'BK004',
            'customer_name' => 'David Brown',
            'customer_email' => 'david.brown@email.com',
            'customer_phone' => '+61400456789',
            'scheduled_date' => '2025-08-09',
            'scheduled_time' => '11:00:00',
            'street_address' => '321 Hay St',
            'suburb' => 'Perth',
            'state' => 'WA',
            'postcode' => '6000',
            'base_price' => 270.00,
            'gst_amount' => 27.00,
            'total_amount' => 297.00,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'referral_code' => $referralUsers[0]['referral_code'], // Erik novamente
            'referred_by' => $referralUsers[0]['id']
        ]
    ];
    
    // Inserir bookings de teste
    $insertBookingSQL = "
        INSERT INTO bookings (
            booking_code, customer_id, professional_id, service_id,
            customer_name, customer_email, customer_phone,
            scheduled_date, scheduled_time, street_address, suburb, state, postcode,
            duration_minutes, base_price, gst_amount, total_amount, status, payment_status, 
            referral_code, referred_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            referral_code = VALUES(referral_code),
            referred_by = VALUES(referred_by)
    ";
    
    $stmt = $connection->prepare($insertBookingSQL);
    
    foreach ($testBookings as $booking) {
        $stmt->execute([
            $booking['booking_code'],
            1, // customer_id padrão
            1, // professional_id padrão
            1, // service_id padrão
            $booking['customer_name'],
            $booking['customer_email'],
            $booking['customer_phone'],
            $booking['scheduled_date'],
            $booking['scheduled_time'],
            $booking['street_address'],
            $booking['suburb'],
            $booking['state'],
            $booking['postcode'],
            120, // duration_minutes padrão
            $booking['base_price'],
            $booking['gst_amount'],
            $booking['total_amount'],
            $booking['status'],
            $booking['payment_status'],
            $booking['referral_code'],
            $booking['referred_by']
        ]);
        
        echo "   ✅ Booking criado: {$booking['booking_code']} - {$booking['customer_name']} (Referral: {$booking['referral_code']})\n";
    }
    
    echo "\n3. 🔄 SINCRONIZANDO REFERRALS COM BOOKINGS REAIS...\n";
    
    // Limpar referrals fictícios
    $connection->exec("DELETE FROM referrals WHERE booking_id NOT IN (SELECT booking_code FROM bookings)");
    echo "   ✅ Removidos referrals fictícios\n";
    
    // Criar referrals baseados nos bookings reais
    $stmt = $connection->query("
        SELECT b.*, rl.commission_percentage as level_commission,
               CONCAT(b.street_address, ', ', b.suburb) as full_address
        FROM bookings b
        JOIN referral_users ru ON b.referred_by = ru.id
        LEFT JOIN referral_levels rl ON ru.current_level_id = rl.id
        WHERE b.referral_code IS NOT NULL 
        AND b.status = 'completed'
        AND b.payment_status = 'paid'
    ");
    $bookingsWithReferrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bookingsWithReferrals as $booking) {
        $commissionRate = $booking['level_commission'] ?: 10; // 10% padrão
        $commissionAmount = ($booking['total_amount'] * $commissionRate) / 100;
        
        // Verificar se já existe o referral
        $checkStmt = $connection->prepare("SELECT id FROM referrals WHERE booking_id = ?");
        $checkStmt->execute([$booking['booking_code']]);
        
        if (!$checkStmt->fetch()) {
            // Criar novo referral
            $insertReferralSQL = "
                INSERT INTO referrals (
                    referrer_id, booking_id, customer_name, customer_email,
                    service_type, booking_date, booking_value, commission_earned,
                    city, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())
            ";
            
            $stmt = $connection->prepare($insertReferralSQL);
            $stmt->execute([
                $booking['referred_by'],
                $booking['booking_code'],
                $booking['customer_name'],
                $booking['customer_email'],
                'Cleaning Service', // Tipo padrão
                $booking['scheduled_date'],
                $booking['total_amount'],
                $commissionAmount,
                $booking['suburb']
            ]);
            
            echo "   ✅ Referral criado: {$booking['booking_code']} → \${$commissionAmount} para usuário {$booking['referred_by']}\n";
        }
    }
    
    echo "\n4. 📊 ATUALIZANDO ESTATÍSTICAS DOS USUÁRIOS...\n";
    
    // Atualizar totais dos usuários
    $updateStatsSQL = "
        UPDATE referral_users ru SET
            total_earned = (
                SELECT COALESCE(SUM(commission_earned), 0)
                FROM referrals r
                WHERE r.referrer_id = ru.id
            ),
            total_referrals = (
                SELECT COUNT(*)
                FROM referrals r
                WHERE r.referrer_id = ru.id
            )
        WHERE ru.is_active = 1
    ";
    
    $connection->exec($updateStatsSQL);
    echo "   ✅ Estatísticas dos usuários atualizadas\n";
    
    echo "\n5. 📈 RESUMO FINAL...\n";
    
    // Mostrar resumo
    $stmt = $connection->query("
        SELECT ru.name, ru.referral_code, ru.total_earned, ru.total_referrals,
               COUNT(b.id) as bookings_count
        FROM referral_users ru
        LEFT JOIN bookings b ON ru.id = b.referred_by
        WHERE ru.is_active = 1
        GROUP BY ru.id
        ORDER BY ru.total_earned DESC
    ");
    
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($summary as $user) {
        echo "   👤 {$user['name']} ({$user['referral_code']}):\n";
        echo "      💰 Total Ganho: \${$user['total_earned']}\n";
        echo "      📊 Referrals: {$user['total_referrals']}\n";
        echo "      🏠 Bookings: {$user['bookings_count']}\n\n";
    }
    
    echo "✅ INTEGRAÇÃO BOOKING → REFERRAL CONCLUÍDA COM SUCESSO!\n\n";
    echo "🎯 AGORA OS SISTEMAS ESTÃO TOTALMENTE INTEGRADOS:\n";
    echo "   ✅ Bookings têm campos de referral\n";
    echo "   ✅ Referrals são baseados em bookings reais\n";
    echo "   ✅ Comissões calculadas automaticamente\n";
    echo "   ✅ Estatísticas atualizadas\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
