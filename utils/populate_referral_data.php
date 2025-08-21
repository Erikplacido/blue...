<?php
/**
 * Script para popular dados do Referral Club
 */

try {
    // Conectar ao banco da Hostinger
    $pdo = new PDO('mysql:host=srv1417.hstgr.io;port=3306;dbname=u979853733_rose;charset=utf8mb4', 'u979853733_rose', 'BlueM@rketing33', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "🎯 POPULANDO DADOS DO REFERRAL CLUB\n";
    echo "===================================\n\n";
    
    // 1. INSERIR NÍVEIS DO REFERRAL CLUB
    echo "🏆 1. INSERINDO NÍVEIS...\n";
    
    $levels = [
        [
            'id' => 1,
            'level_name' => 'Blue Topaz',
            'level_icon' => 'https://bluefacilityservices.com.au/wp-content/uploads/2024/10/topaz_icon-1-150x150.png',
            'min_earnings' => 0.00,
            'max_earnings' => 499.99,
            'commission_percentage' => 5.00,
            'commission_fixed' => 10.00,
            'commission_type' => 'percentage',
            'color_primary' => '#4EACFF',
            'color_secondary' => '#78BEFF',
            'sort_order' => 1
        ],
        [
            'id' => 2,
            'level_name' => 'Blue Tanzanite',
            'level_icon' => 'https://bluefacilityservices.com.au/wp-content/uploads/2024/10/tanzanite_icon-1-150x150.png',
            'min_earnings' => 500.00,
            'max_earnings' => 1999.99,
            'commission_percentage' => 10.00,
            'commission_fixed' => 15.00,
            'commission_type' => 'percentage',
            'color_primary' => '#6366F1',
            'color_secondary' => '#8B5CF6',
            'sort_order' => 2
        ],
        [
            'id' => 3,
            'level_name' => 'Blue Sapphire',
            'level_icon' => 'https://bluefacilityservices.com.au/wp-content/uploads/2024/10/sapphire_icon-1-150x150.png',
            'min_earnings' => 2000.00,
            'max_earnings' => 999999.99,
            'commission_percentage' => 15.00,
            'commission_fixed' => 20.00,
            'commission_type' => 'percentage',
            'color_primary' => '#1E40AF',
            'color_secondary' => '#3B82F6',
            'sort_order' => 3
        ]
    ];
    
    foreach ($levels as $level) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO referral_levels (id, level_name, level_icon, min_earnings, max_earnings, commission_percentage, commission_fixed, commission_type, color_primary, color_secondary, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $level['id'], $level['level_name'], $level['level_icon'], $level['min_earnings'],
            $level['max_earnings'], $level['commission_percentage'], $level['commission_fixed'],
            $level['commission_type'], $level['color_primary'], $level['color_secondary'], $level['sort_order']
        ]);
    }
    echo "  ✅ 3 níveis inseridos\n";
    
    // 2. INSERIR USUÁRIOS DO REFERRAL CLUB
    echo "👥 2. INSERINDO USUÁRIOS...\n";
    
    $users = [
        [
            'name' => 'Erik Placido',
            'email' => 'erik@blueproject.com',
            'phone' => '+61 400 123 456',
            'address' => '123 Collins Street, Melbourne VIC 3000',
            'referral_code' => 'ERIK2025',
            'total_earned' => 525.00,
            'upcoming_payment' => 120.00,
            'total_referrals' => 17,
            'paid_referrals' => 10,
            'pending_referrals' => 4,
            'active_referrals' => 3,
            'current_level_id' => 2,
            'member_since' => '2024-01-15',
            'next_payment_date' => '2025-08-15',
            'bank_name' => 'Commonwealth Bank',
            'bank_agency' => 'Collins Street Branch',
            'account_number' => '1234-5678-9012',
            'bsb' => '062-001',
            'account_name' => 'Erik Placido',
            'abn_number' => '12 345 678 901'
        ],
        [
            'name' => 'Mayza Silva',
            'email' => 'mayza@blueproject.com',
            'phone' => '+61 400 987 654',
            'address' => '456 Flinders Street, Sydney NSW 2000',
            'referral_code' => 'MAYZA2025',
            'total_earned' => 85.00,
            'upcoming_payment' => 45.00,
            'total_referrals' => 3,
            'paid_referrals' => 2,
            'pending_referrals' => 1,
            'active_referrals' => 0,
            'current_level_id' => 1,
            'member_since' => '2024-03-20',
            'next_payment_date' => '2025-08-15',
            'bank_name' => 'ANZ Bank',
            'bank_agency' => 'Sydney Central',
            'account_number' => '9876-5432-1098',
            'bsb' => '012-345',
            'account_name' => 'Mayza Silva',
            'abn_number' => '98 765 432 109'
        ],
        [
            'name' => 'John Smith',
            'email' => 'john@blueproject.com',
            'phone' => '+61 400 555 123',
            'address' => '789 Queen Street, Brisbane QLD 4000',
            'referral_code' => 'JOHN2025',
            'total_earned' => 2150.00,
            'upcoming_payment' => 320.00,
            'total_referrals' => 25,
            'paid_referrals' => 18,
            'pending_referrals' => 5,
            'active_referrals' => 2,
            'current_level_id' => 3,
            'member_since' => '2023-11-10',
            'next_payment_date' => '2025-08-15',
            'bank_name' => 'Westpac Bank',
            'bank_agency' => 'Brisbane CBD',
            'account_number' => '5555-4444-3333',
            'bsb' => '032-123',
            'account_name' => 'John Smith',
            'abn_number' => '55 444 333 222'
        ]
    ];
    
    foreach ($users as $user) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO referral_users (name, email, phone, address, referral_code, total_earned, upcoming_payment, total_referrals, paid_referrals, pending_referrals, active_referrals, current_level_id, member_since, next_payment_date, bank_name, bank_agency, account_number, bsb, account_name, abn_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user['name'], $user['email'], $user['phone'], $user['address'], $user['referral_code'],
            $user['total_earned'], $user['upcoming_payment'], $user['total_referrals'], $user['paid_referrals'],
            $user['pending_referrals'], $user['active_referrals'], $user['current_level_id'], $user['member_since'],
            $user['next_payment_date'], $user['bank_name'], $user['bank_agency'], $user['account_number'],
            $user['bsb'], $user['account_name'], $user['abn_number']
        ]);
    }
    echo "  ✅ 3 usuários inseridos\n";
    
    // 3. INSERIR REFERÊNCIAS/INDICAÇÕES
    echo "🎯 3. INSERINDO REFERÊNCIAS...\n";
    
    $referrals = [
        // Referências do Erik (ID: 1)
        ['referrer_id' => 1, 'customer_name' => 'Maria Silva', 'customer_email' => 'maria@email.com', 'customer_phone' => '+61 400 111 111', 'status' => 'pending', 'booking_value' => 350.00, 'commission_earned' => 35.00, 'city' => 'Melbourne', 'service_type' => 'House Cleaning', 'booking_id' => 'BK001', 'booking_date' => '2025-08-05'],
        ['referrer_id' => 1, 'customer_name' => 'João Santos', 'customer_email' => 'joao@email.com', 'customer_phone' => '+61 400 222 222', 'status' => 'paid', 'booking_value' => 500.00, 'commission_earned' => 50.00, 'city' => 'Sydney', 'service_type' => 'Deep House Cleaning', 'booking_id' => 'BK002', 'booking_date' => '2025-08-03', 'payment_date' => '2025-08-03'],
        ['referrer_id' => 1, 'customer_name' => 'Ana Costa', 'customer_email' => 'ana@email.com', 'customer_phone' => '+61 400 333 333', 'status' => 'active', 'booking_value' => 400.00, 'commission_earned' => 40.00, 'city' => 'Brisbane', 'service_type' => 'Office Cleaning', 'booking_id' => 'BK003', 'booking_date' => '2025-08-01'],
        ['referrer_id' => 1, 'customer_name' => 'Carlos Lima', 'customer_email' => 'carlos@email.com', 'customer_phone' => '+61 400 444 444', 'status' => 'pending', 'booking_value' => 300.00, 'commission_earned' => 30.00, 'city' => 'Perth', 'service_type' => 'Carpet Cleaning', 'booking_id' => 'BK004', 'booking_date' => '2025-07-30'],
        
        // Referências da Mayza (ID: 2)
        ['referrer_id' => 2, 'customer_name' => 'Peter Johnson', 'customer_email' => 'peter@email.com', 'customer_phone' => '+61 400 555 555', 'status' => 'paid', 'booking_value' => 250.00, 'commission_earned' => 12.50, 'city' => 'Sydney', 'service_type' => 'Window Cleaning', 'booking_id' => 'BK005', 'booking_date' => '2025-07-25', 'payment_date' => '2025-07-25'],
        ['referrer_id' => 2, 'customer_name' => 'Sarah Wilson', 'customer_email' => 'sarah@email.com', 'customer_phone' => '+61 400 666 666', 'status' => 'pending', 'booking_value' => 180.00, 'commission_earned' => 9.00, 'city' => 'Sydney', 'service_type' => 'House Cleaning', 'booking_id' => 'BK006', 'booking_date' => '2025-07-28'],
        
        // Referências do John (ID: 3)
        ['referrer_id' => 3, 'customer_name' => 'Michael Brown', 'customer_email' => 'michael@email.com', 'customer_phone' => '+61 400 777 777', 'status' => 'paid', 'booking_value' => 450.00, 'commission_earned' => 67.50, 'city' => 'Brisbane', 'service_type' => 'Deep House Cleaning', 'booking_id' => 'BK007', 'booking_date' => '2025-07-20', 'payment_date' => '2025-07-20'],
        ['referrer_id' => 3, 'customer_name' => 'Lisa Davis', 'customer_email' => 'lisa@email.com', 'customer_phone' => '+61 400 888 888', 'status' => 'active', 'booking_value' => 380.00, 'commission_earned' => 57.00, 'city' => 'Brisbane', 'service_type' => 'Office Cleaning', 'booking_id' => 'BK008', 'booking_date' => '2025-07-18'],
    ];
    
    foreach ($referrals as $referral) {
        $stmt = $pdo->prepare("INSERT INTO referrals (referrer_id, customer_name, customer_email, customer_phone, status, booking_value, commission_earned, city, service_type, booking_id, booking_date, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $referral['referrer_id'], $referral['customer_name'], $referral['customer_email'], $referral['customer_phone'],
            $referral['status'], $referral['booking_value'], $referral['commission_earned'], $referral['city'],
            $referral['service_type'], $referral['booking_id'], $referral['booking_date'], 
            $referral['payment_date'] ?? null
        ]);
    }
    echo "  ✅ 8 referências inseridas\n";
    
    // 4. INSERIR CONFIGURAÇÕES DO SISTEMA
    echo "⚙️  4. INSERINDO CONFIGURAÇÕES...\n";
    
    $configs = [
        ['config_key' => 'minimum_payout', 'config_value' => '50.00', 'config_type' => 'number', 'description' => 'Minimum amount for payout'],
        ['config_key' => 'payout_frequency', 'config_value' => 'monthly', 'config_type' => 'string', 'description' => 'Payout frequency'],
        ['config_key' => 'referral_expiry_days', 'config_value' => '30', 'config_type' => 'number', 'description' => 'Referral code expiry in days'],
        ['config_key' => 'bonus_tiers', 'config_value' => '{"5": 50.00, "10": 100.00, "20": 250.00}', 'config_type' => 'json', 'description' => 'Bonus amounts for referral tiers'],
        ['config_key' => 'system_active', 'config_value' => 'true', 'config_type' => 'boolean', 'description' => 'Referral system status']
    ];
    
    foreach ($configs as $config) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO referral_config (config_key, config_value, config_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$config['config_key'], $config['config_value'], $config['config_type'], $config['description']]);
    }
    echo "  ✅ 5 configurações inseridas\n";
    
    // 5. VERIFICAÇÃO FINAL
    echo "\n📊 VERIFICAÇÃO FINAL:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM referral_levels");
    $levels_count = $stmt->fetch()['total'];
    echo "  🏆 Níveis: $levels_count\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM referral_users");
    $users_count = $stmt->fetch()['total'];
    echo "  👥 Usuários: $users_count\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM referrals");
    $referrals_count = $stmt->fetch()['total'];
    echo "  🎯 Referências: $referrals_count\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM referral_config");
    $config_count = $stmt->fetch()['total'];
    echo "  ⚙️  Configurações: $config_count\n";
    
    echo "\n✅ DADOS POPULADOS COM SUCESSO!\n";
    echo "Agora você pode testar http://localhost:8001/referralclub3.php\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
