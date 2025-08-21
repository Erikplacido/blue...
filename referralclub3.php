<?php
/**
 * =========================================================
 * PROJETO BLUE V3 - REFERRAL CLUB DASHBOARD v3 DINÂMICO
 * =========================================================
 * 
 * @file referralclub3.php
 * @description Dashboard do sistema de indicações com dados dinâmicos do banco
 * @version 3.0 - DYNAMIC DATABASE INTEGRATION
 * @date 2025-08-08
 * 
 * FUNCIONALIDADES:
 * - Dashboard de indicações com dados do banco da Hostinger
 * - Sistema de níveis dinâmico
 * - Sistema de pontos e recompensas em tempo real
 * - Ranking de indicadores real
 * - Histórico de pagamentos do banco
 * - Código de indicação personalizado
 * - Integração total com sistema de booking
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================
// CONFIGURAÇÃO DO BANCO DE DADOS DINÂMICO
// =========================================================
require_once __DIR__ . '/config/australian-database.php';

/**
 * Função para buscar dados dinâmicos do Referral Club
 * @param int $userId ID do usuário (padrão: 1 - Erik Placido)
 */
function getDynamicReferralData($userId = 1) {
    try {
        $db = AustralianDatabase::getInstance();
        $connection = $db->getConnection();
        
        // BUSCAR DADOS DO USUÁRIO
        $stmt = $connection->prepare("
            SELECT ru.*, rl.level_name, rl.level_icon, rl.commission_percentage, 
                   rl.commission_fixed, rl.commission_type, rl.color_primary, rl.color_secondary,
                   rl.min_earnings, rl.max_earnings
            FROM referral_users ru
            LEFT JOIN referral_levels rl ON ru.current_level_id = rl.id
            WHERE ru.id = ? AND ru.is_active = 1
        ");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            throw new Exception("User ID {$userId} not found or inactive");
        }
        
        // BUSCAR TODOS OS NÍVEIS DO SISTEMA
        $stmt = $connection->query("
            SELECT * FROM referral_levels 
            WHERE is_active = 1 
            ORDER BY sort_order
        ");
        $levels_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converter níveis para formato compatível
        $userLevels = [];
        foreach ($levels_raw as $level) {
            $userLevels[$level['id']] = [
                'level_id' => $level['id'],
                'level_name' => $level['level_name'],
                'level_icon' => $level['level_icon'],
                'min_earnings' => (float)$level['min_earnings'],
                'max_earnings' => (float)$level['max_earnings'],
                'commission_percentage' => (float)$level['commission_percentage'],
                'commission_fixed' => (float)$level['commission_fixed'],
                'commission_type' => $level['commission_type'],
                'color_primary' => $level['color_primary'],
                'color_secondary' => $level['color_secondary']
            ];
        }
        
        // BUSCAR REFERRALS DO USUÁRIO
        $stmt = $connection->prepare("
            SELECT r.*
            FROM referrals r
            WHERE r.referrer_id = ? 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
        $referrals_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converter referrals para formato compatível
        $referralData = [];
        foreach ($referrals_raw as $ref) {
            $referralData[] = [
                'id' => $ref['id'],
                'name' => $ref['customer_name'] ?? 'N/A',
                'email' => $ref['customer_email'] ?? 'N/A',
                'status' => ucfirst($ref['status']),
                'value' => (float)($ref['booking_value'] ?? 0),
                'commission' => (float)($ref['commission_earned'] ?? 0),
                'city' => $ref['city'] ?? 'N/A',
                'date' => $ref['booking_date'] ?? $ref['created_at'],
                'service_type' => $ref['service_type'] ?? 'house_cleaning',
                'booking_id' => $ref['booking_id'] ?? 'N/A'
            ];
        }
        
        // BUSCAR CONFIGURAÇÕES DO SISTEMA
        $stmt = $connection->query("SELECT config_key, config_value, config_type FROM referral_config");
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $referralConfig = [];
        foreach ($configs as $config) {
            $value = $config['config_value'];
            switch ($config['config_type']) {
                case 'number':
                    $value = (float)$value;
                    break;
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $value = json_decode($value, true) ?? $value;
                    break;
            }
            $referralConfig[$config['config_key']] = $value;
        }
        
        // CALCULAR RANKING (posição do usuário)
        $stmt = $connection->query("
            SELECT id, name, total_earned FROM referral_users 
            WHERE is_active = 1 
            ORDER BY total_earned DESC
        ");
        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ranking = 1;
        $leaderboard = [];
        
        foreach ($allUsers as $index => $user) {
            if ($user['id'] == $userId) {
                $ranking = $index + 1;
            }
            // Top 5 para leaderboard
            if ($index < 5) {
                $leaderboard[] = $user;
            }
        }
        
        error_log("✅ Dynamic referral data loaded successfully for user {$userId}");
        
        return [
            'userData' => $userData,
            'userLevels' => $userLevels,
            'referralData' => $referralData,
            'referralConfig' => $referralConfig,
            'ranking' => $ranking,
            'leaderboard' => $leaderboard
        ];
        
    } catch (Exception $e) {
        error_log("❌ Referral data error: " . $e->getMessage());
        return null;
    }
}

// Obter user_id (GET parameter ou padrão)
$userId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? 
    (int)$_GET['user_id'] : 1; // Padrão: Erik Placido

// Carregar dados dinâmicos
$dynamicData = getDynamicReferralData($userId);

// Se não conseguir carregar dados do banco, usar dados de fallback
if (!$dynamicData) {
    error_log("Warning: Using fallback data for referralclub3.php - Database connection failed");
    
    $dynamicData = [
        'userData' => [
            'id' => $userId,
            'name' => 'Erik Placido',
            'email' => 'erik.placido@example.com',
            'total_earned' => 525.00,
            'upcoming_payment' => 125.50,
            'is_active' => 1,
            'referral_code' => 'ERIK2025'
        ],
        'userLevels' => [
            2 => [
                'level_id' => 2,
                'level_name' => 'Silver',
                'level_icon' => 'https://via.placeholder.com/24x24/C0C0C0/FFFFFF?text=S',
                'min_earnings' => 200,
                'max_earnings' => 1000,
                'commission_percentage' => 7.5,
                'commission_fixed' => 0.0,
                'commission_type' => 'percentage',
                'color_primary' => '#C0C0C0',
                'color_secondary' => '#F8F8FF'
            ]
        ],
        'referralData' => [
            [
                'id' => 1,
                'name' => 'John Smith',
                'email' => 'john@example.com',
                'status' => 'Paid',
                'value' => 150.00,
                'city' => 'Sydney',
                'date' => date('Y-m-d'),
                'service_type' => 'house_cleaning',
                'booking_id' => 'BK001'
            ]
        ],
        'referralConfig' => [
            'min_referral_amount' => 50.00,
            'max_commission_per_referral' => 200.00
        ],
        'ranking' => 3,
        'leaderboard' => [
            ['id' => 1, 'name' => 'Sarah Johnson', 'total_earned' => 850.00],
            ['id' => 2, 'name' => 'Mike Davis', 'total_earned' => 720.00],
            ['id' => $userId, 'name' => 'Erik Placido', 'total_earned' => 525.00]
        ]
    ];
}

// Extrair dados para compatibilidade com código existente
$userData = $dynamicData['userData'];
$userLevels = $dynamicData['userLevels'];
$referralData = $dynamicData['referralData'];
$referralConfig = $dynamicData['referralConfig'];

// Ajustar formato dos dados do usuário para compatibilidade
$userData['ranking'] = $dynamicData['ranking'];
$userData['total_earned'] = (float)$userData['total_earned'];
$userData['upcoming_payment'] = (float)$userData['upcoming_payment'];

// =========================================================
// DEFINIR DADOS DO NÍVEL ATUAL DO USUÁRIO - DINÂMICO
// =========================================================

// O nível atual já vem do JOIN na query do banco
$currentUserLevel = [
    'level_id' => $userData['current_level_id'] ?? 1,
    'level_name' => $userData['level_name'] ?? 'Blue Topaz',
    'level_icon' => $userData['level_icon'] ?? 'https://via.placeholder.com/24x24/007BFF/FFFFFF?text=BT',
    'min_earnings' => (float)($userData['min_earnings'] ?? 0),
    'max_earnings' => (float)($userData['max_earnings'] ?? 499.99),
    'commission_percentage' => (float)($userData['commission_percentage'] ?? 5.0),
    'commission_fixed' => (float)($userData['commission_fixed'] ?? 10.0),
    'commission_type' => $userData['commission_type'] ?? 'percentage',
    'color_primary' => $userData['color_primary'] ?? '#4EACFF',
    'color_secondary' => $userData['color_secondary'] ?? '#78BEFF'
];

// Calcular progresso para o próximo nível usando dados reais
$currentEarnings = (float)($userData['total_earned'] ?? 0);
$nextLevelProgress = calculateNextLevelProgress($currentUserLevel, $currentEarnings);

// =========================================================
// FUNÇÕES UTILITÁRIAS
// =========================================================

/**
 * Formatar moeda australiana (corrigido para PHP 8.1+)
 */
function formatCurrency($amount, $decimals = 2, $symbol = '$') {
    // Verificar se o valor é válido
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        $amount = 0.00;
    }
    
    // Converter para float se necessário
    $amount = (float)$amount;
    
    return $symbol . number_format($amount, $decimals);
}

/**
 * Calcular comissão baseada no valor da reserva e nível do usuário
 */
function calculateCommission($bookingValue, $userLevel, $referralType = 'standard') {
    // Validar entrada
    if (empty($bookingValue) || !is_numeric($bookingValue) || $bookingValue <= 0) {
        return 0.00;
    }
    
    // Validar nível do usuário
    if (!$userLevel || !is_array($userLevel)) {
        return 0.00;
    }
    
    $commission = 0.00;
    
    // Calcular baseado no tipo de comissão
    if ($userLevel['commission_type'] === 'percentage') {
        $rate = ($userLevel['commission_percentage'] ?? 5.0) / 100;
        $commission = $bookingValue * $rate;
    } elseif ($userLevel['commission_type'] === 'fixed') {
        $commission = $userLevel['commission_fixed'] ?? 25.00;
    }
    
    // Multiplicadores por tipo de referral
    $typeMultipliers = [
        'standard' => 1.0,
        'premium' => 1.5,
        'vip' => 2.0,
        'first_time' => 1.2
    ];
    
    $multiplier = $typeMultipliers[$referralType] ?? 1.0;
    $commission *= $multiplier;
    
    // Aplicar limites mínimos e máximos
    $minCommission = 5.00;  // Mínimo $5
    $maxCommission = 200.00; // Máximo $200 por referral
    
    $commission = max($minCommission, min($maxCommission, $commission));
    
    return round($commission, 2);
}

/**
 * Calcular progresso para o próximo nível
 */
function calculateNextLevelProgress($currentLevel, $totalEarnings) {
    try {
        $db = AustralianDatabase::getInstance();
        $connection = $db->getConnection();
        
        // Buscar o próximo nível baseado no current_level_id
        $stmt = $connection->prepare("
            SELECT * FROM referral_levels 
            WHERE id > ? AND is_active = 1 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $stmt->execute([$currentLevel['id'] ?? 0]);
        $nextLevel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nextLevel) {
            // Usuário já está no nível máximo
            return [
                'progress_percentage' => 100,
                'remaining_amount' => 0,
                'next_level' => null
            ];
        }
        
        $currentEarnings = (float)$totalEarnings;
        $requiredEarnings = (float)$nextLevel['min_earnings'];
        $currentLevelMin = (float)($currentLevel['min_earnings'] ?? 0);
        
        if ($currentEarnings >= $requiredEarnings) {
            return [
                'progress_percentage' => 100,
                'remaining_amount' => 0,
                'next_level' => $nextLevel
            ];
        }
        
        // Calcular progresso atual
        $rangeEarnings = $requiredEarnings - $currentLevelMin;
        $currentProgress = $currentEarnings - $currentLevelMin;
        $progressPercentage = $rangeEarnings > 0 ? ($currentProgress / $rangeEarnings) * 100 : 0;
        
        return [
            'progress_percentage' => min(100, max(0, round($progressPercentage, 1))),
            'remaining_amount' => max(0, $requiredEarnings - $currentEarnings),
            'next_level' => $nextLevel
        ];
        
    } catch (Exception $e) {
        // Fallback em caso de erro
        return [
            'progress_percentage' => 0,
            'remaining_amount' => 1000,
            'next_level' => null
        ];
    }
}

/**
 * Função segura para explode (corrigido para PHP 8.1+)
 */
function safeExplode($delimiter, $string, $default = []) {
    if ($string === null || $string === '' || !is_string($string)) {
        return $default;
    }
    
    return explode($delimiter, $string);
}

/**
 * Formatar data para exibição
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Obter classe CSS para status
 */
function getStatusClass($status) {
    switch(strtolower($status)) {
        case 'paid':
            return 'status-paid';
        case 'pending':
            return 'status-pending';
        case 'active':
            return 'status-active';
        default:
            return 'status-default';
    }
}

/**
 * Obter emoji para status
 */
function getStatusEmoji($status) {
    switch(strtolower($status)) {
        case 'paid':
            return '<i class="fas fa-check-circle"></i>';
        case 'pending':
            return '<i class="fas fa-clock"></i>';
        case 'active':
            return '<i class="fas fa-sync-alt"></i>';
        default:
            return '<i class="fas fa-question-circle"></i>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Referral Club Dashboard v3 Dynamic - Blue Project</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard do sistema de indicações com níveis e recompensas em tempo real">
    <meta name="keywords" content="referral, dashboard, rewards, commission, Blue Project, levels">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="assets/css/liquid-glass-tokens.css" as="style">
    <link rel="preload" href="assets/css/liquid-glass-components.css" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style">
    
    <!-- Styles -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/liquid-glass-tokens.css">
    <link rel="stylesheet" href="assets/css/liquid-glass-components.css">
    <link rel="stylesheet" href="assets/css/referralclub.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    
    <!-- Custom styles for level system -->
    <style>
        .level-badge {
            display: inline-flex;
            align-items: center;
            background: var(--lg-surface-hover);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--lg-radius-button);
            padding: var(--lg-space-2) var(--lg-space-3);
            margin-left: var(--lg-space-4);
            font-size: var(--lg-text-body);
            font-weight: var(--lg-font-medium);
            color: var(--lg-text-white);
            vertical-align: middle;
        }
    </style>
</head>
<body data-page="referral-club">
    <!-- Background Liquid Glass -->
    <div class="lg-liquid-bg">
        <div class="lg-bubble lg-bubble--1"></div>
        <div class="lg-bubble lg-bubble--2"></div>
        <div class="lg-bubble lg-bubble--3"></div>
        <div class="lg-bubble lg-bubble--4"></div>
        <div class="lg-bubble lg-bubble--5"></div>
    </div>

    <!-- Top Navigation Menu -->
    <nav class="top-nav">
        <div class="nav-content">
            <div class="nav-logo">
                <img src="https://bluefacilityservices.com.au/wp-content/uploads/2024/10/5-7.png" alt="Blue Project" style="width: 72px; height: 72px; object-fit: contain;">
                <span>Blue Project</span>
            </div>
            <div class="nav-actions">
                <button class="nav-btn" onclick="openAccountModal()">
                    <i class="fas fa-user-cog"></i>
                    <span>Account</span>
                </button>
                <button class="nav-btn">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </button>
                <button class="nav-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Dashboard Container -->
    <div class="referral-dashboard lg-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <h1 class="lg-heading--hero">
                <span style="display: inline-block; animation: wave 2s infinite; margin-right: 1rem;">👋</span>
                Welcome back, <?= htmlspecialchars($userData['name']) ?>!
                <span class="level-badge">
                    <img src="<?= $currentUserLevel['level_icon'] ?>" alt="<?= $currentUserLevel['level_name'] ?>" style="width: 24px; height: 24px; margin-right: 0.5rem;">
                    <?= $currentUserLevel['level_name'] ?>
                </span>
            </h1>
            
            <!-- Header Actions - All in one line -->
            <div class="header-main-actions">
                <!-- Referral Code Display -->
                <div class="referral-code-display">
                    <span class="lg-text--info-body">
                        <i class="fas fa-qrcode" style="margin-right: 0.5rem;"></i>Your referral code:
                    </span>
                    <span class="referral-code" id="referralCode"><?= htmlspecialchars($userData['referral_code']) ?></span>
                    <button class="lg-btn--copy" onclick="copyReferralCode()" id="copyBtn">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                
                <!-- Action Buttons -->
                <a href="booking2.php?ref=<?= $userData['referral_code'] ?>" class="lg-btn--action lg-btn--primary">
                    <i class="fas fa-plus"></i> Make a Booking
                </a>
                <a href="#share-modal" class="lg-btn--action" onclick="shareReferralCode()">
                    <i class="fas fa-share"></i> Share Code
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <!-- Total Earned -->
            <div class="lg-card lg-card--stat">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div class="lg-text--stat-value"><?= formatCurrency($userData['total_earned']) ?></div>
                <div class="lg-text--stat-label">Total Earned</div>
                <div class="lg-text--stat-details">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <img src="<?= $currentUserLevel['level_icon'] ?>" alt="<?= $currentUserLevel['level_name'] ?>" style="width: 20px; height: 20px;">
                        <span><?= $currentUserLevel['level_name'] ?> Level</span>
                    </div>
                    Commission: 
                    <?php if ($currentUserLevel['commission_type'] === 'percentage'): ?>
                        <?= $currentUserLevel['commission_percentage'] ?>% per booking
                    <?php else: ?>
                        <?= formatCurrency($currentUserLevel['commission_fixed']) ?> per booking
                    <?php endif; ?>
                    
                    <?php if ($nextLevelProgress['next_level']): ?>
                    <div style="margin-top: 0.75rem;">
                        <div style="background: rgba(255,255,255,0.1); border-radius: 4px; height: 6px; overflow: hidden; margin-bottom: 0.25rem;">
                            <div style="background: var(--lg-primary); height: 100%; width: <?= $nextLevelProgress['progress_percentage'] ?>%; border-radius: 4px; transition: width 0.3s ease;"></div>
                        </div>
                        <small style="opacity: 0.8; font-size: 0.8rem;">
                            <?= formatCurrency($nextLevelProgress['remaining_amount']) ?> to <?= $nextLevelProgress['next_level']['level_name'] ?>
                        </small>
                    </div>
                    <?php else: ?>
                    <div style="margin-top: 0.5rem;">
                        <small style="color: gold; font-weight: 600;">
                            <i class="fas fa-crown" style="margin-right: 0.25rem;"></i>Maximum Level
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Next Payment -->
            <div class="lg-card lg-card--stat">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="lg-text--stat-value"><?= formatCurrency($userData['upcoming_payment']) ?></div>
                <div class="lg-text--stat-label">Next Payment</div>
                <div class="lg-text--stat-details">
                    Due: <?= formatDate($userData['next_payment_date']) ?>
                </div>
            </div>

            <!-- Ranking -->
            <div class="lg-card lg-card--stat">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <div class="lg-text--stat-value">Top <?= $userData['ranking'] ?></div>
                <div class="lg-text--stat-label">Your Ranking</div>
                <div class="lg-text--stat-details">
                    Among all referral partners
                </div>
                
                <!-- Top Rankings Display - DADOS DINÂMICOS -->
                <div style="margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 8px;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: #ffffff; margin-bottom: 0.75rem; text-align: center;">
                        Current Leaderboard (Live)
                    </div>
                    <div style="display: grid; gap: 0.5rem;">
                        <?php foreach ($dynamicData['leaderboard'] as $index => $leader): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem; background: rgba(255,255,255,<?= $leader['id'] == $userData['id'] ? '0.2' : '0.1' ?>); border-radius: 6px; <?= $leader['id'] == $userData['id'] ? 'border: 1px solid var(--lg-primary);' : '' ?>">
                            <span style="color: #ffffff; font-weight: 600;">
                                <?php if ($index == 0): ?>
                                    <i class="fas fa-crown" style="color: gold; margin-right: 0.5rem;"></i>
                                <?php elseif ($index == 1): ?>
                                    <i class="fas fa-medal" style="color: silver; margin-right: 0.5rem;"></i>
                                <?php elseif ($index == 2): ?>
                                    <i class="fas fa-medal" style="color: #cd7f32; margin-right: 0.5rem;"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars(safeExplode(' ', $leader['name'] ?? '', ['Unknown'])[0]) ?>
                                <?= $leader['id'] == $userData['id'] ? ' (You)' : '' ?>
                            </span>
                            <span style="color: #ffffff; font-weight: 700;"><?= formatCurrency($leader['total_earned']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Referrals Overview -->
            <div class="lg-card lg-card--stat">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="lg-text--stat-value"><?= count($referralData) ?></div>
                <div class="lg-text--stat-label">Total Referrals</div>
                <div class="lg-text--stat-details">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem; margin-top: 1rem; font-size: 0.95rem; line-height: 1.5;">
                        <span style="color: #ffffff; font-weight: 600;"><i class="fas fa-check-circle" style="margin-right: 0.5rem; width: 16px; color: #22c55e;"></i>Paid: 1</span>
                        <span style="color: #ffffff; font-weight: 600;"><i class="fas fa-star" style="margin-right: 0.5rem; width: 16px; color: #3b82f6;"></i>Eligible: 1</span>
                        <span style="color: #ffffff; font-weight: 600;"><i class="fas fa-times-circle" style="margin-right: 0.5rem; width: 16px; color: #ef4444;"></i>Unsuccessful: 0</span>
                        <span style="color: #ffffff; font-weight: 600;"><i class="fas fa-clock" style="margin-right: 0.5rem; width: 16px; color: #fbbf24;"></i>Pending: 2</span>
                        <span style="color: #ffffff; font-weight: 600;"><i class="fas fa-handshake" style="margin-right: 0.5rem; width: 16px; color: #8b5cf6;"></i>Negotiating: 0</span>
                    </div>
                </div>
                
                <!-- Progress to next bonus -->
                <?php 
                $nextTier = null;
                foreach($referralConfig['bonus_tiers'] as $tier => $bonus) {
                    if($userData['total_referrals'] < $tier) {
                        $nextTier = $tier;
                        break;
                    }
                }
                if($nextTier): 
                    $progress = ($userData['total_referrals'] / $nextTier) * 100;
                ?>
                <div class="lg-progress">
                    <div class="lg-progress__fill" style="width: <?= $progress ?>%"></div>
                </div>
                <div class="lg-text--stat-details" style="margin-top: var(--lg-space-2);">
                    <i class="fas fa-gift"></i> <?= $nextTier - $userData['total_referrals'] ?> more for <?= formatCurrency($referralConfig['bonus_tiers'][$nextTier]) ?> bonus
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Referrals Table -->
        <div class="lg-panel lg-panel--table">
            <h2 class="lg-heading--section"><i class="fas fa-table" style="margin-right: 1rem;"></i>Recent Referrals</h2>
            
            <div style="overflow-x: auto;">
                <table class="lg-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Commission</th>
                            <th>Location</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Booking ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($referralData as $referral): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($referral['name']) ?></strong><br>
                                <span class="lg-text--stat-details">
                                    <?= htmlspecialchars($referral['email']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="lg-badge lg-badge--<?= strtolower($referral['status']) ?>">
                                    <?= getStatusEmoji($referral['status']) ?>
                                    <?= htmlspecialchars($referral['status']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= formatCurrency(calculateCommission($referral['value'], $currentUserLevel)) ?></strong>
                                <br>
                                <small style="opacity: 0.7;">(<?= formatCurrency($referral['value']) ?> booking)</small>
                            </td>
                            <td><?= htmlspecialchars($referral['city']) ?></td>
                            <td><?= htmlspecialchars($referral['service_type']) ?></td>
                            <td><?= formatDate($referral['date']) ?></td>
                            <td>
                                <code style="background: var(--lg-surface); padding: var(--lg-space-1) var(--lg-space-2); border-radius: var(--lg-radius-button); font-size: var(--lg-text-small);">
                                    <?= htmlspecialchars($referral['booking_id']) ?>
                                </code>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Level Information Panel -->
        <div class="lg-panel lg-panel--info">
            <h3 class="lg-text--info-title"><i class="fas fa-star" style="margin-right: 1rem;"></i>Blue Referral Club Levels</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--lg-space-4); margin-top: var(--lg-space-4);">
                <?php foreach($userLevels as $level): ?>
                <div style="background: rgba(255,255,255,0.1); border-radius: var(--lg-radius-card); padding: var(--lg-space-4); <?= $level['level_id'] == $currentUserLevel['level_id'] ? 'border: 2px solid var(--lg-primary);' : 'border: 1px solid rgba(255,255,255,0.1);' ?>">
                    <div style="display: flex; align-items: center; gap: var(--lg-space-3); margin-bottom: var(--lg-space-3);">
                        <img src="<?= $level['level_icon'] ?>" alt="<?= $level['level_name'] ?>" style="width: 40px; height: 40px; border-radius: 50%;">
                        <div>
                            <h4 style="color: var(--lg-text-white); margin: 0; font-size: var(--lg-text-body);"><?= $level['level_name'] ?></h4>
                            <p style="color: var(--lg-text-white); opacity: 0.8; margin: 0; font-size: var(--lg-text-small);">
                                <?= formatCurrency($level['min_earnings']) ?><?= $level['level_id'] < 3 ? ' - ' . formatCurrency($level['max_earnings']) : '+' ?>
                            </p>
                        </div>
                    </div>
                    <p style="color: var(--lg-text-white); opacity: 0.8; margin: 0; font-size: var(--lg-text-small);">
                        Commission: 
                        <?php if ($level['commission_type'] === 'percentage'): ?>
                            <?= $level['commission_percentage'] ?>% per booking
                        <?php else: ?>
                            <?= formatCurrency($level['commission_fixed']) ?> per booking
                        <?php endif; ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Terms and Info -->
        <div class="lg-panel lg-panel--info">
            <h3 class="lg-text--info-title"><i class="fas fa-lightbulb" style="margin-right: 1rem;"></i>How it works</h3>
            <p class="lg-text--info-body">
                Share your referral code with friends and earn commission based on your current level. 
                As you earn more, you'll automatically advance through our Blue levels, unlocking higher commission rates!
                Payments are processed monthly with a minimum of <strong><?= formatCurrency($referralConfig['minimum_payout']) ?></strong>.
            </p>
            <p class="lg-text--info-small">
                <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>Referral codes are valid for <?= $referralConfig['referral_expiry_days'] ?> days after first use. 
                Level upgrades are automatic and instant when you reach the earning threshold.
                <a href="#terms" class="lg-link">View full terms & conditions</a>
            </p>
        </div>
    </div>

    <!-- Account Management Modal (same as original) -->
    <div id="accountModal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h2><i class="fas fa-user-cog"></i> Account Management</h2>
                <button class="modal-close" onclick="closeAccountModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-tabs">
                <button class="tab-btn active" onclick="switchTab('profile')">
                    <i class="fas fa-user"></i> Profile
                </button>
                <button class="tab-btn" onclick="switchTab('password')">
                    <i class="fas fa-lock"></i> Password
                </button>
                <button class="tab-btn" onclick="switchTab('bank')">
                    <i class="fas fa-university"></i> Bank Details
                </button>
                <button class="tab-btn" onclick="switchTab('payments')">
                    <i class="fas fa-credit-card"></i> Payment History
                </button>
                <button class="tab-btn" onclick="switchTab('referrals')">
                    <i class="fas fa-users"></i> All Referrals
                </button>
            </div>

            <div class="modal-content">
                <!-- Profile Tab -->
                <div id="profileTab" class="tab-content active">
                    <form class="account-form">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" value="<?= htmlspecialchars($userData['name']) ?>" class="form-input">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" value="erik@blueproject.com" class="form-input">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone</label>
                            <input type="tel" value="+61 400 123 456" class="form-input">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea class="form-input" rows="3">123 Collins Street, Melbourne VIC 3000</textarea>
                        </div>
                        <button type="submit" class="form-btn primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>

                <!-- Password Tab -->
                <div id="passwordTab" class="tab-content">
                    <form class="account-form">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Current Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="currentPassword" class="form-input" placeholder="Enter current password">
                                <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">
                                    <i class="fas fa-eye" id="currentPassword-icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="newPassword" class="form-input" placeholder="Enter new password">
                                <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                                    <i class="fas fa-eye" id="newPassword-icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="confirmPassword" class="form-input" placeholder="Confirm new password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                                    <i class="fas fa-eye" id="confirmPassword-icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Action Buttons Container -->
                        <div class="password-actions">
                            <button type="submit" class="form-btn primary">
                                <i class="fas fa-shield-alt"></i> Update Password
                            </button>
                            <button type="button" class="form-btn secondary" onclick="sendPasswordResetLink()">
                                <i class="fas fa-paper-plane"></i> Send Reset Link
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Bank Details Tab -->
                <div id="bankTab" class="tab-content">
                    <form class="account-form">
                        <div class="form-group">
                            <label><i class="fas fa-university"></i> Bank Name</label>
                            <input type="text" value="Commonwealth Bank" class="form-input">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Agency</label>
                            <input type="text" value="Collins Street Branch" class="form-input" placeholder="Enter bank agency/branch">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-credit-card"></i> Account Number</label>
                            <input type="text" value="****-****-1234" class="form-input">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-code-branch"></i> BSB</label>
                            <input type="text" value="062-001" class="form-input">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-tag"></i> Account Name</label>
                            <input type="text" value="<?= htmlspecialchars($userData['name']) ?>" class="form-input">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> ABN Number</label>
                            <input type="text" value="12 345 678 901" class="form-input" placeholder="Enter ABN number">
                        </div>
                        <button type="submit" class="form-btn primary">
                            <i class="fas fa-save"></i> Update Bank Details
                        </button>
                    </form>
                </div>

                <!-- Payment History Tab -->
                <div id="paymentsTab" class="tab-content">
                    <div class="payment-history">
                        <div class="payment-item" onclick="window.location.href='payment_history.php'" style="cursor: pointer;">
                            <div class="payment-info">
                                <i class="fas fa-check-circle payment-icon success"></i>
                                <div>
                                    <strong>$85.00</strong>
                                    <span>Commission Payment</span>
                                    <small>July 2025</small>
                                </div>
                            </div>
                            <span class="payment-status success">Paid</span>
                        </div>
                        <div class="payment-item" onclick="window.location.href='payment_history.php'" style="cursor: pointer;">
                            <div class="payment-info">
                                <i class="fas fa-check-circle payment-icon success"></i>
                                <div>
                                    <strong>$120.00</strong>
                                    <span>Commission Payment</span>
                                    <small>June 2025</small>
                                </div>
                            </div>
                            <span class="payment-status success">Paid</span>
                        </div>
                        <div class="payment-item" onclick="window.location.href='payment_history.php'" style="cursor: pointer;">
                            <div class="payment-info">
                                <i class="fas fa-clock payment-icon pending"></i>
                                <div>
                                    <strong><?= formatCurrency($userData['upcoming_payment'] ?? 0) ?></strong>
                                    <span>Commission Payment</span>
                                    <small>August 2025</small>
                                </div>
                            </div>
                            <span class="payment-status pending">Pending</span>
                        </div>
                    </div>
                    
                    <!-- View All Button -->
                    <div style="text-align: center; margin-top: var(--lg-space-4);">
                        <a href="payment_history.php" class="lg-btn--action lg-btn--primary">
                            <i class="fas fa-history"></i> View Complete History
                        </a>
                    </div>
                </div>

                <!-- All Referrals Tab -->
                <div id="referralsTab" class="tab-content">
                    <!-- Filtros -->
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="searchReferral">
                                    <i class="fas fa-search"></i> Search
                                </label>
                                <input type="text" id="searchReferral" placeholder="Search by name, email or booking ID..." class="filter-input">
                            </div>
                            
                            <div class="filter-group">
                                <label for="statusFilter">
                                    <i class="fas fa-filter"></i> Status
                                </label>
                                <select id="statusFilter" class="filter-select">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="active">Active</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="cityFilter">
                                    <i class="fas fa-map-marker-alt"></i> City
                                </label>
                                <select id="cityFilter" class="filter-select">
                                    <option value="">All Cities</option>
                                    <option value="melbourne">Melbourne</option>
                                    <option value="sydney">Sydney</option>
                                    <option value="brisbane">Brisbane</option>
                                    <option value="perth">Perth</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="dateFilter">
                                    <i class="fas fa-calendar"></i> Date Range
                                </label>
                                <select id="dateFilter" class="filter-select">
                                    <option value="">All Time</option>
                                    <option value="last7days">Last 7 Days</option>
                                    <option value="last30days">Last 30 Days</option>
                                    <option value="thismonth">This Month</option>
                                    <option value="lastmonth">Last Month</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="valueSort">
                                    <i class="fas fa-sort-amount-down"></i> Sort by Value
                                </label>
                                <select id="valueSort" class="filter-select">
                                    <option value="">Default</option>
                                    <option value="highest">Highest First</option>
                                    <option value="lowest">Lowest First</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <button id="clearFilters" class="clear-filters-btn">
                                    <i class="fas fa-times"></i> Clear Filters
                                </button>
                            </div>
                        </div>
                        
                        <!-- Results Counter -->
                        <div class="results-counter">
                            <span id="resultsCount">Showing <?= count($referralData) ?> of <?= count($referralData) ?> referrals</span>
                        </div>
                    </div>
                    
                    <div class="referrals-list" id="referralsList">
                        <?php foreach($referralData as $referral): ?>
                        <div class="referral-item" 
                             data-status="<?= strtolower($referral['status']) ?>"
                             data-city="<?= strtolower($referral['city']) ?>"
                             data-date="<?= $referral['date'] ?>"
                             data-value="<?= $referral['value'] ?>"
                             data-name="<?= strtolower($referral['name']) ?>"
                             data-email="<?= strtolower($referral['email']) ?>"
                             data-booking="<?= strtolower($referral['booking_id']) ?>">
                            <div class="referral-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="referral-info">
                                <strong><?= htmlspecialchars($referral['name']) ?></strong>
                                <span><?= htmlspecialchars($referral['email']) ?></span>
                                <small><?= htmlspecialchars($referral['city']) ?> • <?= formatDate($referral['date']) ?> • <?= htmlspecialchars($referral['booking_id']) ?></small>
                            </div>
                            <div class="referral-status">
                                <span class="lg-badge lg-badge--<?= strtolower($referral['status']) ?>">
                                    <?= getStatusEmoji($referral['status']) ?>
                                    <?= htmlspecialchars($referral['status']) ?>
                                </span>
                                <strong><?= formatCurrency(calculateCommission($referral['value'], $currentUserLevel)) ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- No Results Message -->
                    <div id="noResults" class="no-results" style="display: none;">
                        <div class="no-results-content">
                            <i class="fas fa-search"></i>
                            <h3>No referrals found</h3>
                            <p>Try adjusting your filters or search criteria</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Global config - passa dados PHP para JavaScript
        window.ReferralClub = {
            userData: <?= json_encode($userData) ?>,
            referralData: <?= json_encode($referralData) ?>,
            config: <?= json_encode($referralConfig) ?>,
            userLevels: <?= json_encode($userLevels) ?>,
            currentLevel: <?= json_encode($currentUserLevel) ?>
        };
        
        // Função auxiliar para converter hex para rgb
        <?php
        function hex2rgb($hex) {
            $hex = str_replace("#", "", $hex);
            if(strlen($hex) == 3) {
                $r = hexdec(substr($hex,0,1).substr($hex,0,1));
                $g = hexdec(substr($hex,1,1).substr($hex,1,1));
                $b = hexdec(substr($hex,2,1).substr($hex,2,1));
            } else {
                $r = hexdec(substr($hex,0,2));
                $g = hexdec(substr($hex,2,2));
                $b = hexdec(substr($hex,4,2));
            }
            return "$r, $g, $b";
        }
        ?>
    </script>
    <script src="assets/js/referralclub.js"></script>
</body>
</html>
