<?php
/**
 * Stripe Enterprise Configuration - Blue Cleaning Services
 * Sistema completo de assinaturas, pausas e referrals
 */

// Carregar configurações do .env
function loadStripeEnv() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value, '"\'');
            }
        }
    }
}

loadStripeEnv();

// Constantes Stripe
define('STRIPE_PUBLISHABLE_KEY', $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '');
define('STRIPE_SECRET_KEY', $_ENV['STRIPE_SECRET_KEY'] ?? '');
define('STRIPE_WEBHOOK_SECRET', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '');
define('STRIPE_CURRENCY', strtolower($_ENV['STRIPE_CURRENCY'] ?? 'AUD'));

// URLs de retorno baseadas no ambiente
$baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
define('STRIPE_SUCCESS_URL', $baseUrl . '/payment/subscription-success.php');
define('STRIPE_CANCEL_URL', $baseUrl . '/payment/subscription-cancel.php');

// Configurações de assinatura
define('BILLING_ADVANCE_HOURS', 48); // Cobrança 48h antes
define('MIN_PAUSE_NOTICE_HOURS', 48); // Aviso mínimo para pausa

// Faixas de pausa gratuita (configurável pelo admin)
define('PAUSE_QUOTAS', [
    ['min' => 0,  'max' => 15,  'period' => 26, 'free_pauses' => 2],
    ['min' => 15, 'max' => 26,  'period' => 26, 'free_pauses' => 4],
    ['min' => 26, 'max' => 52,  'period' => 52, 'free_pauses' => 8],
    ['min' => 52, 'max' => 999, 'period' => 52, 'free_pauses' => 12]
]);

// Tipos de recorrência e descontos
define('RECURRENCE_DISCOUNTS', [
    'one-time' => 0,      // Sem desconto
    'weekly' => 7,        // 7% desconto
    'fortnightly' => 5,   // 5% desconto  
    'monthly' => 10       // 10% desconto
]);

// Inicializar Stripe SDK
require_once __DIR__ . '/../vendor/autoload.php';

if (!empty(STRIPE_SECRET_KEY)) {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    \Stripe\Stripe::setApiVersion('2023-10-16'); // Versão estável
} else {
    throw new Exception('STRIPE_SECRET_KEY não configurada no .env');
}

// Verificar chaves essenciais
if (empty(STRIPE_PUBLISHABLE_KEY) || empty(STRIPE_SECRET_KEY)) {
    error_log("AVISO: Chaves Stripe não configuradas completamente");
}

// Log de inicialização
error_log("Stripe Enterprise Config carregado: " . date('Y-m-d H:i:s'));

/**
 * Função utilitária para formatar valor em centavos
 */
function stripeAmount($amount) {
    return (int) round($amount * 100);
}

/**
 * Função utilitária para formatar valor de centavos para decimal
 */
function fromStripeAmount($amount) {
    return $amount / 100;
}

/**
 * Obter configuração de desconto por recorrência
 */
function getRecurrenceDiscount($type) {
    $discounts = RECURRENCE_DISCOUNTS;
    return $discounts[$type] ?? 0;
}

/**
 * Calcular próxima data de cobrança baseada na recorrência
 */
function calculateNextBilling($startDate, $recurrenceType) {
    $start = new DateTime($startDate);
    $billing = clone $start;
    $billing->sub(new DateInterval('PT' . BILLING_ADVANCE_HOURS . 'H'));
    
    return $billing->format('Y-m-d H:i:s');
}

/**
 * Validar se ainda há tempo para pausar/cancelar (48h)
 */
function canPauseOrCancel($nextServiceDate) {
    $nextService = new DateTime($nextServiceDate);
    $now = new DateTime();
    $diff = $now->diff($nextService);
    
    $hoursUntilService = ($diff->days * 24) + $diff->h;
    return $hoursUntilService >= MIN_PAUSE_NOTICE_HOURS;
}
?>
