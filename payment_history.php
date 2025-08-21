<?php
/**
 * =========================================================
 * PROJETO BLUE V2 - PAYMENT HISTORY PAGE
 * =========================================================
 * 
 * @file payment_history.php
 * @description Página de histórico de pagamentos detalhada
 * @version 2.0
 * @date 2025-08-05
 * 
 * FUNCIONALIDADES:
 * - Histórico detalhado de pagamentos
 * - Agrupamento por mês
 * - Detalhes de cada booking pago
 * - Filtros e pesquisa
 * - Exportação de dados
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================
// CONFIGURAÇÃO DO BANCO DE DADOS DINÂMICO
// =========================================================
require_once __DIR__ . '/config/australian-database.php';

/**
 * Função para buscar dados dinâmicos do histórico de pagamentos
 * @param int $userId ID do usuário
 */
function getDynamicPaymentHistory($userId) {
    try {
        $db = AustralianDatabase::getInstance();
        $connection = $db->getConnection();
        
        // Log para debug
        error_log("Payment History: Carregando dados para user_id = $userId");
        
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
            error_log("Payment History: Usuário ID $userId não encontrado");
            throw new Exception("Usuário não encontrado");
        }
        
        error_log("Payment History: Usuário encontrado - " . $userData['name']);
        
        // BUSCAR HISTÓRICO DE PAGAMENTOS (REFERRALS COM STATUS PAGO)
        $stmt = $connection->prepare("
            SELECT r.*, 
                   DATE_FORMAT(r.created_at, '%Y-%m') as month_key,
                   DATE_FORMAT(r.created_at, '%M %Y') as month_name
            FROM referrals r
            WHERE r.referrer_id = ? 
            AND r.status IN ('paid', 'completed')
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
        $paidReferrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // AGRUPAR POR MÊS
        $paymentHistory = [];
        foreach ($paidReferrals as $referral) {
            $monthKey = $referral['month_key'];
            if (!isset($paymentHistory[$monthKey])) {
                $paymentHistory[$monthKey] = [
                    'month_name' => $referral['month_name'],
                    'total_amount' => 0,
                    'status' => 'paid',
                    'payment_date' => $referral['created_at'],
                    'bookings' => []
                ];
            }
            
            $paymentHistory[$monthKey]['total_amount'] += $referral['commission_earned'];
            $paymentHistory[$monthKey]['bookings'][] = [
                'id' => $referral['booking_id'],
                'customer_name' => $referral['customer_name'],
                'customer_email' => $referral['customer_email'],
                'service_type' => $referral['service_type'] ?: 'Cleaning Service',
                'booking_date' => $referral['booking_date'],
                'location' => $referral['city'] . (isset($referral['state']) ? ', ' . $referral['state'] : ''),
                'booking_value' => $referral['booking_value'],
                'commission_rate' => $userData['commission_percentage'],
                'commission_amount' => $referral['commission_earned'],
                'status' => $referral['status']
            ];
        }
        
        // BUSCAR TOTAL DE GANHOS (REFERRALS PAGOS)
        $stmt = $connection->prepare("
            SELECT SUM(commission_earned) as total_earned 
            FROM referrals 
            WHERE referrer_id = ? AND status IN ('paid', 'completed')
        ");
        $stmt->execute([$userId]);
        $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $userData['total_earned'] = $totalResult['total_earned'] ?: 0;
        
        return [
            'userData' => $userData,
            'paymentHistory' => $paymentHistory
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao carregar histórico de pagamentos: " . $e->getMessage());
        return null;
    }
}

// =========================================================
// CARREGAR DADOS DINÂMICOS
// =========================================================
// Pegar user_id da URL ou usar 1 como padrão (Erik Placido)
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;

// Validar se o user_id é válido
if ($userId <= 0) {
    $userId = 1; // Fallback para Erik Placido
}

$dynamicData = getDynamicPaymentHistory($userId);

if (!$dynamicData) {
    die("Erro ao carregar dados do sistema. Usuário não encontrado ou dados indisponíveis. Tente novamente em alguns minutos.");
}

$userData = $dynamicData['userData'];
$paymentHistory = $dynamicData['paymentHistory'];

// =========================================================
// DADOS AGORA CARREGADOS DINAMICAMENTE DO BANCO DE DADOS
// Os dados do histórico de pagamentos são carregados em tempo real
// através da função getDynamicPaymentHistory()
// =========================================================

// =========================================================
// FUNÇÕES UTILITÁRIAS
// =========================================================

/**
 * Formatar moeda australiana
 */
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
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
        default:
            return 'status-default';
    }
}

/**
 * Obter ícone para status
 */
function getStatusIcon($status) {
    switch(strtolower($status)) {
        case 'paid':
            return '<i class="fas fa-check-circle"></i>';
        case 'pending':
            return '<i class="fas fa-clock"></i>';
        default:
            return '<i class="fas fa-question-circle"></i>';
    }
}

// =========================================================
// FILTRAR APENAS PAGAMENTOS PAGOS E PERÍODO ESPECÍFICO
// =========================================================

// Pegar o mês solicitado (via GET) ou usar o último mês pago
$requestedMonth = $_GET['month'] ?? null;

// Filtrar apenas meses pagos
$paidMonths = array_filter($paymentHistory, function($month) {
    return $month['status'] === 'paid';
});

// Se não tem mês solicitado, pegar o último pago
if (!$requestedMonth && !empty($paidMonths)) {
    $requestedMonth = array_key_first($paidMonths);
}

// Dados do mês específico
$currentMonth = null;
$monthlyBookings = [];
$monthlyTotal = 0;
$monthlyDate = '';

if ($requestedMonth && isset($paidMonths[$requestedMonth])) {
    $currentMonth = $paidMonths[$requestedMonth];
    $monthlyBookings = array_filter($currentMonth['bookings'], function($booking) {
        return $booking['status'] === 'paid';
    });
    $monthlyTotal = $currentMonth['total_amount'];
    $monthlyDate = $currentMonth['payment_date'];
}

// Calcular totais apenas dos bookings pagos do mês
$totalBookings = count($monthlyBookings);
$totalCommission = array_sum(array_column($monthlyBookings, 'commission_amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment History - Blue Project</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Histórico detalhado de pagamentos e comissões">
    <meta name="keywords" content="payment, history, commission, earnings, Blue Project">
    
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
    
    <style>
        /* =========================================================
         * SISTEMA DINÂMICO DE CORES - BASEADO NO NÍVEL DO USUÁRIO
         * =========================================================
         */
        :root {
            /* Cores dinâmicas baseadas no nível do usuário */
            --lg-primary: <?= $userData['color_primary'] ?: '#667eea' ?>;
            --lg-secondary: <?= $userData['color_secondary'] ?: '#764ba2' ?>;
            --lg-gradient-primary: linear-gradient(135deg, <?= $userData['color_primary'] ?: '#667eea' ?>, <?= $userData['color_secondary'] ?: '#764ba2' ?>);
        }
        
        /* Payment History - Minimalista como Invoice */
        body[data-page="payment-history"] {
            font-family: var(--lg-font-family);
            background: rgba(17, 40, 75, 0.75);
            margin: 0;
            padding: 0;
            color: var(--lg-text);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        .payment-history-page {
            padding: 120px var(--lg-space-6) var(--lg-space-6);
            min-height: 100vh;
        }
        
        .statement-container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--lg-surface);
            backdrop-filter: var(--lg-blur-strong);
            -webkit-backdrop-filter: var(--lg-blur-strong);
            border-radius: var(--lg-radius-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: var(--lg-shadow);
            overflow: hidden;
        }
        
        /* Header da Invoice */
        .statement-header {
            background: linear-gradient(135deg, var(--lg-navy), rgba(17, 40, 75, 0.95));
            color: var(--lg-text-white);
            padding: var(--lg-space-8) var(--lg-space-6);
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .statement-title {
            font-size: 2rem;
            font-weight: var(--lg-font-bold);
            margin-bottom: var(--lg-space-2);
            color: var(--lg-text-white);
            letter-spacing: -0.02em;
        }
        
        .statement-period {
            opacity: 0.8;
            font-size: var(--lg-text-small);
            color: var(--lg-text-white);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Informações da Conta */
        .account-info {
            padding: var(--lg-space-6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.02);
        }
        
        .account-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--lg-space-4);
            max-width: none;
        }
        
        .account-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--lg-space-2) 0;
            font-size: var(--lg-text-small);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .account-label {
            color: rgba(255, 255, 255, 0.7);
            font-weight: var(--lg-font-medium);
        }
        
        .account-value {
            color: var(--lg-text-white);
            font-weight: var(--lg-font-semibold);
        }
        
        /* Resumo Financeiro */
        .summary-section {
            padding: var(--lg-space-6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--lg-space-4);
        }
        
        .summary-item {
            text-align: center;
            padding: var(--lg-space-4);
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--lg-radius-button);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: var(--lg-transition);
        }
        
        .summary-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-1px);
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: var(--lg-font-bold);
            color: #ffc107;
            margin-bottom: var(--lg-space-1);
        }
        
        .summary-label {
            font-size: var(--lg-text-small);
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: var(--lg-font-medium);
        }
        
        /* Lista de Transações */
        .transactions-section {
            background: rgba(255, 255, 255, 0.01);
        }
        
        .month-group {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .month-header {
            background: rgba(255, 255, 255, 0.03);
            padding: var(--lg-space-3) var(--lg-space-6);
            font-weight: var(--lg-font-semibold);
            color: var(--lg-text-white);
            font-size: var(--lg-text-small);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .transaction-list {
            background: transparent;
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--lg-space-4) var(--lg-space-6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            transition: var(--lg-transition);
            background: transparent;
        }
        
        .transaction-item:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-details {
            flex: 1;
        }
        
        .transaction-description {
            font-weight: var(--lg-font-medium);
            color: var(--lg-text-white);
            margin-bottom: var(--lg-space-1);
            font-size: var(--lg-text-body);
        }
        
        .transaction-meta {
            font-size: var(--lg-text-small);
            color: rgba(255, 255, 255, 0.6);
        }
        
        .transaction-amount {
            text-align: right;
            min-width: 120px;
        }
        
        .amount-value {
            font-weight: var(--lg-font-bold);
            color: #ffc107;
            font-size: var(--lg-text-body);
            font-family: 'Monaco', 'Consolas', monospace;
        }
        
        .amount-date {
            font-size: var(--lg-text-small);
            color: rgba(255, 255, 255, 0.6);
            margin-top: var(--lg-space-1);
        }
        
        /* Footer da Invoice */
        .statement-footer {
            padding: var(--lg-space-6);
            background: rgba(255, 255, 255, 0.02);
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer-actions {
            display: flex;
            gap: var(--lg-space-3);
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .invoice-btn {
            display: flex;
            align-items: center;
            gap: var(--lg-space-2);
            padding: var(--lg-space-3) var(--lg-space-4);
            background: var(--lg-surface-hover);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--lg-text-white);
            border-radius: var(--lg-radius-button);
            text-decoration: none;
            font-size: var(--lg-text-small);
            font-weight: var(--lg-font-medium);
            transition: var(--lg-transition);
            cursor: pointer;
            backdrop-filter: var(--lg-blur-light);
            -webkit-backdrop-filter: var(--lg-blur-light);
        }
        
        .invoice-btn:hover {
            background: var(--lg-primary);
            color: var(--lg-navy);
            transform: translateY(-1px);
            box-shadow: var(--lg-shadow-hover);
        }
        
        .invoice-btn--primary {
            background: var(--lg-primary);
            color: var(--lg-navy);
            border-color: var(--lg-primary);
        }
        
        .invoice-btn--primary:hover {
            background: rgba(240, 215, 26, 0.9);
            box-shadow: var(--lg-shadow-hover);
        }
        
        /* Status Styles */
        .status-paid {
            color: #ffc107;
            font-weight: var(--lg-font-semibold);
        }
        
        .status-pending {
            color: #ffc107;
            font-weight: var(--lg-font-semibold);
        }
        
        /* Month Selector */
        .month-selector select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--lg-text-white);
            padding: var(--lg-space-2) var(--lg-space-3);
            border-radius: var(--lg-radius-button);
            font-size: var(--lg-text-small);
            width: 100%;
            max-width: 300px;
            cursor: pointer;
            transition: var(--lg-transition);
        }
        
        .month-selector select:hover {
            border-color: var(--lg-primary);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .month-selector select:focus {
            outline: none;
            border-color: var(--lg-primary);
            box-shadow: 0 0 0 2px rgba(240, 215, 26, 0.2);
        }
        
        .month-selector option {
            background: var(--lg-navy);
            color: var(--lg-text-white);
        }
        
        .no-data-message {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--lg-radius-button);
            margin: var(--lg-space-4);
        }
        
        /* Search and Filter Controls */
        .search-controls {
            background: rgba(255, 255, 255, 0.02);
            padding: var(--lg-space-6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .search-bar {
            display: flex;
            gap: var(--lg-space-3);
            margin-bottom: var(--lg-space-4);
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--lg-text-white);
            padding: var(--lg-space-3) var(--lg-space-4);
            border-radius: var(--lg-radius-button);
            font-size: var(--lg-text-small);
            transition: var(--lg-transition);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--lg-primary);
            box-shadow: 0 0 0 2px rgba(240, 215, 26, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .filter-controls {
            display: flex;
            gap: var(--lg-space-3);
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: var(--lg-space-1);
        }
        
        .filter-label {
            font-size: var(--lg-text-small);
            color: rgba(255, 255, 255, 0.7);
            font-weight: var(--lg-font-medium);
        }
        
        .filter-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--lg-text-white);
            padding: var(--lg-space-2) var(--lg-space-3);
            border-radius: var(--lg-radius-button);
            font-size: var(--lg-text-small);
            cursor: pointer;
            min-width: 120px;
            transition: var(--lg-transition);
        }
        
        .filter-select:hover {
            border-color: var(--lg-primary);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--lg-primary);
            box-shadow: 0 0 0 2px rgba(240, 215, 26, 0.2);
        }
        
        .filter-select option {
            background: var(--lg-navy);
            color: var(--lg-text-white);
        }
        
        .clear-filters-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--lg-text-white);
            padding: var(--lg-space-2) var(--lg-space-3);
            border-radius: var(--lg-radius-button);
            font-size: var(--lg-text-small);
            cursor: pointer;
            transition: var(--lg-transition);
        }
        
        .clear-filters-btn:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--lg-space-4);
            padding-top: var(--lg-space-4);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: var(--lg-text-small);
            color: rgba(255, 255, 255, 0.7);
        }
        
        .results-count {
            font-weight: var(--lg-font-semibold);
            color: #ffc107;
        }
        
        .hidden {
            display: none !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .payment-history-page {
                padding: 100px var(--lg-space-4) var(--lg-space-4);
            }
            
            .statement-container {
                margin: 0;
                border-radius: 0;
            }
            
            .account-details {
                grid-template-columns: 1fr;
                gap: var(--lg-space-2);
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
                gap: var(--lg-space-3);
            }
            
            .transaction-item {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--lg-space-2);
            }
            
            .transaction-amount {
                text-align: left;
                width: 100%;
            }
            
            .footer-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .invoice-btn {
                width: 100%;
                justify-content: center;
                max-width: 300px;
            }
        }
    </style>
</head>
<body data-page="payment-history">
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
                <img src="https://bluefacilityservices.com.au/wp-content/uploads/2024/10/topaz_icon-1-150x150.png" alt="Blue Project Topaz" style="width: 72px; height: 72px; object-fit: contain;">
                <span>Blue Project</span>
            </div>
            <div class="nav-actions">
                <a href="referralclub3.php<?= $userId !== 1 ? "?user_id=$userId" : '' ?>" class="nav-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Statement Container -->
    <div class="payment-history-page lg-container">
        <div class="statement-container">
            <!-- Statement Header -->
            <div class="statement-header">
                <div class="statement-title">Commission Statement</div>
                <div class="statement-period">
                    <?php if ($currentMonth): ?>
                        Period: <?= $currentMonth['month_name'] ?> • Paid on <?= formatDate($monthlyDate) ?>
                    <?php else: ?>
                        No paid commissions available
                    <?php endif; ?>
                </div>
            </div>

            <!-- Account Information -->
            <div class="account-info">
                <!-- Month Selector -->
                <?php if (!empty($paidMonths)): ?>
                <div style="margin-bottom: var(--lg-space-4); padding-bottom: var(--lg-space-4); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <label style="display: block; margin-bottom: var(--lg-space-2); color: rgba(255, 255, 255, 0.7); font-size: var(--lg-text-small); font-weight: var(--lg-font-medium);">Select Statement Period:</label>
                    <select onchange="window.location.href='payment_history.php?user_id=<?= $userId ?>&month=' + this.value" style="
                        background: rgba(255, 255, 255, 0.05);
                        border: 1px solid rgba(255, 255, 255, 0.2);
                        color: var(--lg-text-white);
                        padding: var(--lg-space-2) var(--lg-space-3);
                        border-radius: var(--lg-radius-button);
                        font-size: var(--lg-text-small);
                        width: 100%;
                        max-width: 300px;
                    ">
                        <?php foreach($paidMonths as $monthKey => $monthData): ?>
                        <option value="<?= $monthKey ?>" <?= ($monthKey === $requestedMonth) ? 'selected' : '' ?> style="background: var(--lg-navy); color: var(--lg-text-white);">
                            <?= $monthData['month_name'] ?> - <?= formatCurrency($monthData['total_amount']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="account-details">
                    <div class="account-item">
                        <span class="account-label">Account Holder:</span>
                        <span class="account-value"><?= htmlspecialchars($userData['name']) ?></span>
                    </div>
                    <div class="account-item">
                        <span class="account-label">Referral Code:</span>
                        <span class="account-value"><?= htmlspecialchars($userData['referral_code']) ?></span>
                    </div>
                    <div class="account-item">
                        <span class="account-label">Member Since:</span>
                        <span class="account-value"><?= formatDate($userData['member_since']) ?></span>
                    </div>
                    <div class="account-item">
                        <span class="account-label">Statement Date:</span>
                        <span class="account-value"><?= date('d/m/Y') ?></span>
                    </div>
                    <div class="account-item">
                        <span class="account-label">Payment Status:</span>
                        <span class="account-value status-paid">PAID</span>
                    </div>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="summary-section">
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-value"><?= formatCurrency($monthlyTotal) ?></div>
                        <div class="summary-label">Total Paid</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?= $totalBookings ?></div>
                        <div class="summary-label">Total Bookings</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value"><?= formatCurrency($totalCommission) ?></div>
                        <div class="summary-label">Commission Total</div>
                    </div>
                </div>
            </div>

            <!-- Transactions -->
            <div class="transactions-section">
                <?php if ($currentMonth && !empty($monthlyBookings)): ?>
                
                <!-- Search and Filter Controls -->
                <div class="search-controls">
                    <div class="search-bar">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search by customer name, email, service type, booking ID, or location..." />
                        <div class="filter-controls">
                            <div class="filter-group">
                                <label class="filter-label">Sort by</label>
                                <select id="sortSelect" class="filter-select">
                                    <option value="date-desc">Latest First</option>
                                    <option value="date-asc">Oldest First</option>
                                    <option value="amount-desc">Highest Commission</option>
                                    <option value="amount-asc">Lowest Commission</option>
                                    <option value="customer-asc">Customer A-Z</option>
                                    <option value="customer-desc">Customer Z-A</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Service Type</label>
                                <select id="serviceFilter" class="filter-select">
                                    <option value="">All Services</option>
                                    <option value="House Cleaning">House Cleaning</option>
                                    <option value="Office Cleaning">Office Cleaning</option>
                                    <option value="Carpet Cleaning">Carpet Cleaning</option>
                                    <option value="Window Cleaning">Window Cleaning</option>
                                    <option value="Deep Cleaning">Deep Cleaning</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Commission Range</label>
                                <select id="amountFilter" class="filter-select">
                                    <option value="">All Amounts</option>
                                    <option value="0-25">$0 - $25</option>
                                    <option value="25-50">$25 - $50</option>
                                    <option value="50-75">$50 - $75</option>
                                    <option value="75-100">$75+</option>
                                </select>
                            </div>
                            
                            <button type="button" id="clearFilters" class="clear-filters-btn">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                    
                    <div class="results-info">
                        <span id="resultsCount" class="results-count">Showing all <?= count($monthlyBookings) ?> bookings</span>
                        <span id="totalCommission">Total Commission: <?= formatCurrency($totalCommission) ?></span>
                    </div>
                </div>
                
                <div class="month-group">
                    <div class="month-header">
                        <span><?= $currentMonth['month_name'] ?> - Commission Details</span>
                        <span class="status-paid">PAID</span>
                    </div>
                    <div class="transaction-list" id="transactionList">
                        <?php foreach($monthlyBookings as $index => $booking): ?>
                        <div class="transaction-item" data-booking='<?= json_encode($booking) ?>'>
                            <div class="transaction-details">
                                <div class="transaction-description">
                                    Commission - <?= htmlspecialchars($booking['service_type']) ?>
                                </div>
                                <div class="transaction-meta">
                                    <?= htmlspecialchars($booking['id']) ?> • <?= htmlspecialchars($booking['customer_name']) ?> • <?= htmlspecialchars($booking['location']) ?>
                                </div>
                            </div>
                            <div class="transaction-amount">
                                <div class="amount-value">+<?= formatCurrency($booking['commission_amount']) ?></div>
                                <div class="amount-date"><?= formatDate($booking['booking_date']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="no-data-message">
                    <div style="text-align: center; padding: var(--lg-space-8); color: rgba(255, 255, 255, 0.6);">
                        <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: var(--lg-space-4); opacity: 0.3;"></i>
                        <h3 style="margin: 0; font-weight: var(--lg-font-medium);">No paid commissions found</h3>
                        <p style="margin: var(--lg-space-2) 0 0 0; font-size: var(--lg-text-small);">No payment records available for the selected period.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Statement Footer -->
            <div class="statement-footer">
            <div class="footer-actions">
                <button class="invoice-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Statement
                </button>
                <button class="invoice-btn" onclick="exportToCSV()">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="invoice-btn" onclick="exportToPDF()">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <a href="referralclub3.php<?= $userId !== 1 ? "?user_id=$userId" : '' ?>" class="invoice-btn invoice-btn--primary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Dados dos bookings para manipulação JavaScript
        const bookingsData = <?= json_encode($monthlyBookings) ?>;
        let filteredBookings = [...bookingsData];
        
        // Elementos DOM
        const searchInput = document.getElementById('searchInput');
        const sortSelect = document.getElementById('sortSelect');
        const serviceFilter = document.getElementById('serviceFilter');
        const amountFilter = document.getElementById('amountFilter');
        const clearFiltersBtn = document.getElementById('clearFilters');
        const transactionList = document.getElementById('transactionList');
        const resultsCount = document.getElementById('resultsCount');
        const totalCommission = document.getElementById('totalCommission');
        
        // Event Listeners
        searchInput?.addEventListener('input', applyFilters);
        sortSelect?.addEventListener('change', applyFilters);
        serviceFilter?.addEventListener('change', applyFilters);
        amountFilter?.addEventListener('change', applyFilters);
        clearFiltersBtn?.addEventListener('click', clearFilters);
        
        // Função para aplicar filtros
        function applyFilters() {
            let filtered = [...bookingsData];
            
            // Aplicar pesquisa por texto
            const searchTerm = searchInput?.value.toLowerCase() || '';
            if (searchTerm) {
                filtered = filtered.filter(booking => 
                    booking.customer_name.toLowerCase().includes(searchTerm) ||
                    booking.customer_email.toLowerCase().includes(searchTerm) ||
                    booking.service_type.toLowerCase().includes(searchTerm) ||
                    booking.location.toLowerCase().includes(searchTerm) ||
                    booking.id.toLowerCase().includes(searchTerm)
                );
            }
            
            // Aplicar filtro por tipo de serviço
            const serviceType = serviceFilter?.value || '';
            if (serviceType) {
                filtered = filtered.filter(booking => booking.service_type === serviceType);
            }
            
            // Aplicar filtro por valor da comissão
            const amountRange = amountFilter?.value || '';
            if (amountRange) {
                const [min, max] = amountRange.split('-').map(Number);
                filtered = filtered.filter(booking => {
                    const amount = booking.commission_amount;
                    if (max) {
                        return amount >= min && amount <= max;
                    } else {
                        return amount >= min; // Para o caso "75+"
                    }
                });
            }
            
            // Aplicar ordenação
            const sortBy = sortSelect?.value || 'date-desc';
            filtered.sort((a, b) => {
                switch (sortBy) {
                    case 'date-asc':
                        return new Date(a.booking_date) - new Date(b.booking_date);
                    case 'date-desc':
                        return new Date(b.booking_date) - new Date(a.booking_date);
                    case 'amount-asc':
                        return a.commission_amount - b.commission_amount;
                    case 'amount-desc':
                        return b.commission_amount - a.commission_amount;
                    case 'customer-asc':
                        return a.customer_name.localeCompare(b.customer_name);
                    case 'customer-desc':
                        return b.customer_name.localeCompare(a.customer_name);
                    default:
                        return 0;
                }
            });
            
            filteredBookings = filtered;
            renderBookings();
            updateResultsInfo();
        }
        
        // Função para renderizar os bookings
        function renderBookings() {
            if (!transactionList) return;
            
            if (filteredBookings.length === 0) {
                transactionList.innerHTML = `
                    <div style="text-align: center; padding: var(--lg-space-8); color: rgba(255, 255, 255, 0.6);">
                        <i class="fas fa-search" style="font-size: 3rem; margin-bottom: var(--lg-space-4); opacity: 0.3;"></i>
                        <h3 style="margin: 0; font-weight: var(--lg-font-medium);">No bookings found</h3>
                        <p style="margin: var(--lg-space-2) 0 0 0; font-size: var(--lg-text-small);">Try adjusting your search criteria or filters.</p>
                    </div>
                `;
                return;
            }
            
            transactionList.innerHTML = filteredBookings.map(booking => `
                <div class="transaction-item">
                    <div class="transaction-details">
                        <div class="transaction-description">
                            Commission - ${escapeHtml(booking.service_type)}
                        </div>
                        <div class="transaction-meta">
                            ${escapeHtml(booking.id)} • ${escapeHtml(booking.customer_name)} • ${escapeHtml(booking.location)}
                        </div>
                    </div>
                    <div class="transaction-amount">
                        <div class="amount-value">+$${booking.commission_amount.toFixed(2)}</div>
                        <div class="amount-date">${formatDate(booking.booking_date)}</div>
                    </div>
                </div>
            `).join('');
        }
        
        // Função para atualizar informações dos resultados
        function updateResultsInfo() {
            if (!resultsCount || !totalCommission) return;
            
            const count = filteredBookings.length;
            const total = filteredBookings.reduce((sum, booking) => sum + booking.commission_amount, 0);
            
            resultsCount.textContent = count === bookingsData.length 
                ? `Showing all ${count} bookings`
                : `Showing ${count} of ${bookingsData.length} bookings`;
                
            totalCommission.textContent = `Total Commission: $${total.toFixed(2)}`;
        }
        
        // Função para limpar filtros
        function clearFilters() {
            if (searchInput) searchInput.value = '';
            if (sortSelect) sortSelect.value = 'date-desc';
            if (serviceFilter) serviceFilter.value = '';
            if (amountFilter) amountFilter.value = '';
            
            filteredBookings = [...bookingsData];
            renderBookings();
            updateResultsInfo();
        }
        
        // Função para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Função para formatar data
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-AU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }
        
        // Funções de exportação
        function exportToPDF() {
            // Filtrar apenas os dados visíveis para exportação
            const exportData = {
                period: '<?= $currentMonth ? $currentMonth["month_name"] : "" ?>',
                bookings: filteredBookings,
                total: filteredBookings.reduce((sum, booking) => sum + booking.commission_amount, 0)
            };
            
            console.log('Exporting PDF with filtered data:', exportData);
            alert(`PDF export will include ${filteredBookings.length} filtered bookings`);
        }
        
        function exportToCSV() {
            // Criar CSV dos dados filtrados
            const headers = ['Booking ID', 'Customer Name', 'Email', 'Service Type', 'Location', 'Booking Date', 'Commission Amount'];
            const csvContent = [
                headers.join(','),
                ...filteredBookings.map(booking => [
                    booking.id,
                    `"${booking.customer_name}"`,
                    booking.customer_email,
                    `"${booking.service_type}"`,
                    `"${booking.location}"`,
                    booking.booking_date,
                    booking.commission_amount
                ].join(','))
            ].join('\n');
            
            // Download do arquivo
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `commission_statement_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
        }
        
        // Inicializar filtros quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            updateResultsInfo();
        });
    </script>
</body>
</html>
