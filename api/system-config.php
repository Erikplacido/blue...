<?php
/**
 * API para Configurações do Sistema - Blue Project V2
 * Endpoint: /api/system-config
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit();
}

// Inclui configurações
require_once '../booking2.php';

try {
    // Retorna configurações públicas do sistema
    $publicConfig = [
        'pause' => [
            'enabled' => $pauseConfig['pause_enabled'] ?? true,
            'minimum_notice_hours' => $pauseConfig['minimum_notice_hours'] ?? 48,
            'max_duration_days' => $pauseConfig['max_pause_duration_days'] ?? 90,
            'fee' => $pauseConfig['pause_fee'] ?? 0,
            'tiers' => array_map(function($tier) {
                return [
                    'tier_id' => $tier['tier_id'],
                    'tier_name' => $tier['tier_name'],
                    'free_pauses' => $tier['free_pauses'],
                    'min_services' => $tier['min_services'],
                    'max_services' => $tier['max_services']
                ];
            }, $pauseConfig['pause_tiers'] ?? [])
        ],
        
        'cancellation' => [
            'enabled' => $cancellationConfig['cancellation_enabled'] ?? true,
            'free_cancellation_hours' => $cancellationConfig['free_cancellation_hours'] ?? 48,
            'immediate_cancellation' => $cancellationConfig['immediate_cancellation'] ?? true,
            'survey_enabled' => $cancellationConfig['cancellation_survey_enabled'] ?? true,
            'policies' => array_map(function($key, $policy) {
                return [
                    'recurrence_type' => $key,
                    'penalty_percentage' => $policy['penalty_percentage'],
                    'minimum_penalty' => $policy['minimum_penalty'],
                    'maximum_penalty' => $policy['maximum_penalty']
                ];
            }, array_keys($cancellationConfig['cancellation_policies'] ?? []), 
               array_values($cancellationConfig['cancellation_policies'] ?? []))
        ],
        
        'system' => [
            'minimum_booking_hours' => $systemConfig['minimum_booking_hours'] ?? 48,
            'currency' => 'AUD',
            'timezone' => 'Australia/Sydney',
            'version' => '2.0',
            'stripe_enabled' => $systemConfig['stripe_enabled'] ?? false,
            'email_notifications' => $systemConfig['email_notifications_enabled'] ?? true
        ],
        
        'features' => [
            'pause_system' => true,
            'cancellation_system' => true,
            'tier_based_pauses' => true,
            'penalty_calculation' => true,
            'stripe_integration' => $systemConfig['stripe_enabled'] ?? false,
            'email_confirmations' => $systemConfig['email_notifications_enabled'] ?? true,
            'admin_override' => $systemConfig['admin_editable_cancellation_policy'] ?? true
        ],
        
        'ui' => [
            'show_tier_badges' => true,
            'show_pause_history' => true,
            'show_penalty_breakdown' => true,
            'enable_animations' => true,
            'dark_mode_support' => true
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'config' => $publicConfig,
        'last_updated' => date('Y-m-d H:i:s'),
        'cache_duration' => 3600 // 1 hora
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor'
    ]);
    
    error_log("System Config API Error: " . $e->getMessage());
}
?>
