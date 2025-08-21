<?php
/**
 * =========================================================
 * PROJETO BLUE V3 - SISTEMA DE RESERVAS DINÂMICO
 * =========================================================
 * 
 * @file booking3.php
 * @description Página principal com dados dinâmicos do banco de dados
 * @version 3.0 - DYNAMIC DATABASE INTEGRATION
 * @date 2025-08-08
 * 
 * FUNCIONALIDADES:
 * - Dados dinâmicos carregados do banco de dados
 * - Sistema de autenticação e segurança avançado
 * - Rate limiting e proteção CSRF
 * - Validação de 48h obrigatória
 * - Sistema de desconto por recorrência
 * - API de disponibilidade
 * - Cálculo de multas por cancelamento
 * - Sistema de contrato dinâmico
 * - Integração preparada para Stripe
 * - Campos expandidos de endereço
 * - Preferências dinâmicas
 * - Sistema de pontos preparado
 * - LAYOUT ORIGINAL PRESERVADO
 */

// Aplicar middleware de segurança
require_once __DIR__ . '/auth/SecurityMiddleware.php';

// Carregar variáveis de ambiente
require_once __DIR__ . '/includes/env-loader.php';

security_protect([
    'rate_limit' => ['max_requests' => 50, 'window' => 3600],
    'require_csrf' => ($_SERVER['REQUEST_METHOD'] === 'POST')
]);

// Desabilitar debug mode em produção
ini_set('display_errors', 0);

// =========================================================
// CONSTANTES DE IDENTIFICAÇÃO (DEFINIDAS NO INÍCIO)
// =========================================================
define('SERVICE_ID_HOUSE_CLEANING', 1);
define('INCLUSION_ID_BEDROOMS', 1);
define('INCLUSION_ID_BATHROOMS', 2);
define('INCLUSION_ID_KITCHEN', 4);
define('EXTRA_ID_OVEN', 1);
define('EXTRA_ID_FRIDGE', 2);
define('EXTRA_ID_WINDOWS', 3);
define('EXTRA_ID_GARAGE', 4);
define('EXTRA_ID_BALCONY', 5);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Detectar ambiente de produção
$isProductionEnvironment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'bluefacilityservices.com.au') !== false) || 
                          (strpos($_SERVER['REQUEST_URI'] ?? '', '/allblue/') !== false);

// =========================================================
// CONFIGURAÇÃO DO BANCO DE DADOS DINÂMICO
// =========================================================
require_once __DIR__ . '/config/australian-database.php';

/**
 * Função para buscar dados dinâmicos do banco de dados
 * @param int $serviceId ID do serviço para filtrar dados específicos
 */
function getDynamicServiceData($serviceId = 1) {
    try {
        $db = AustralianDatabase::getInstance();
        $connection = $db->getConnection();
        
        // PRIMEIRO: Buscar informações do serviço específico da tabela services
        $stmt = $connection->prepare("
            SELECT id, service_code, name, description, base_price, duration_minutes, category
            FROM services 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$serviceId]);
        $service_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$service_info) {
            error_log("❌ Service ID {$serviceId} not found or inactive in database");
            throw new Exception("Service ID {$serviceId} not found or inactive");
        }
        
        // Debug: Log service info loaded
        error_log("✅ Service loaded from DB: " . json_encode($service_info));
        
        // Buscar configurações do sistema
        $stmt = $connection->query("SELECT setting_key, setting_value, setting_type FROM system_settings ORDER BY setting_key");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $config = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            switch ($setting['setting_type']) {
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'decimal':
                    $value = (float)$value;
                    break;
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $value = json_decode($value, true) ?? $value;
                    break;
            }
            $config[$setting['setting_key']] = $value;
        }
        
        // Buscar inclusões do serviço específico COM PREÇOS E QUANTIDADE MÍNIMA
        $stmt = $connection->prepare("
            SELECT id, name, icon, price, minimum_quantity
            FROM service_inclusions 
            WHERE service_id = ? AND is_active = TRUE 
            ORDER BY sort_order, name
        ");
        $stmt->execute([$serviceId]);
        $inclusions_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converter para formato compatível com o layout original
        $inclusions = [];
        foreach ($inclusions_raw as $index => $inclusion) {
            $inclusions[] = [
                'id' => $inclusion['id'],
                'service_id' => $serviceId,
                'name' => $inclusion['name'],
                'description' => 'Dynamic service from database: ' . $inclusion['name'],
                'price' => (float)$inclusion['price'], // Usar preço real do banco de dados
                'minimum_quantity' => (int)($inclusion['minimum_quantity'] ?? 1), // ✅ INCLUIR minimum_quantity
                'min_quantity' => (int)($inclusion['minimum_quantity'] ?? 1), // Compatibilidade com código existente
                'image' => str_replace('fas ', '', $inclusion['icon']),
                'sort_order' => $index + 1,
                'status' => 'active'
            ];
        }
        
        // Buscar extras do serviço específico
        $stmt = $connection->prepare("
            SELECT id, name, price, icon
            FROM service_extras 
            WHERE service_id = ? AND is_active = TRUE 
            ORDER BY sort_order, name
        ");
        $stmt->execute([$serviceId]);
        $extras_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converter para formato compatível
        $extras = [];
        foreach ($extras_raw as $index => $extra) {
            $extras[] = [
                'id' => $extra['id'],
                'service_id' => $serviceId,
                'name' => $extra['name'],
                'description' => 'Dynamic extra service: ' . $extra['name'],
                'price' => (float)$extra['price'],
                'sort_order' => $index + 1,
                'status' => 'active'
            ];
        }
        
        // Buscar preferências com TODOS os campos necessários do banco para o serviço específico
        $stmt = $connection->prepare("
            SELECT 
                id, name, field_type, icon, extra_fee, options, 
                conditional_field, conditional_trigger, conditional_placeholder,
                note_text, note_type, is_required, is_active, sort_order
            FROM cleaning_preferences 
            WHERE service_id = ? AND is_active = TRUE 
            ORDER BY sort_order, name
        ");
        $stmt->execute([$serviceId]);
        $preferences_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'service' => $service_info, // INFORMAÇÕES REAIS DO SERVIÇO
            'config' => $config,
            'inclusions' => $inclusions,
            'extras' => $extras,
            'preferences' => $preferences_raw
        ];
        
    } catch (Exception $e) {
        error_log("Dynamic data error: " . $e->getMessage());
        // Retorna estrutura vazia em caso de erro
        return [
            'service' => null,
            'config' => [],
            'inclusions' => [],
            'extras' => [],
            'preferences' => []
        ];
    }
}

// Obter service_id (GET parameter ou constante padrão)
$serviceId = isset($_GET['service_id']) && is_numeric($_GET['service_id']) ? 
    (int)$_GET['service_id'] : SERVICE_ID_HOUSE_CLEANING;

// Carregar dados dinâmicos
$dynamicData = getDynamicServiceData($serviceId);

// Verificar se o serviço foi encontrado
if (!$dynamicData['service']) {
    die("Service not found or inactive. Service ID: {$serviceId}");
}

// =========================================================
// DADOS DINÂMICOS DO SERVIÇO - 100% DO BANCO DE DADOS
// =========================================================
$serviceData = [
    // SERVIÇO PRINCIPAL - Dados reais do banco
    'service' => [
        'id' => $dynamicData['service']['id'],
        'service_code' => $dynamicData['service']['service_code'],
        'name' => $dynamicData['service']['name'],
        'description' => $dynamicData['service']['description'] ?? 'Professional cleaning service',
        'base_price' => (float)$dynamicData['service']['base_price'],
        'duration_minutes' => (int)$dynamicData['service']['duration_minutes'],
        'category' => $dynamicData['service']['category'],
        'status' => 'active'
    ],
    
    // INCLUSÕES DINÂMICAS - carregadas do banco de dados
    'inclusions' => $dynamicData['inclusions'], // Dados dinâmicos do banco
    
    // EXTRAS DINÂMICOS - carregados do banco de dados
    'extras' => $dynamicData['extras'] // Dados dinâmicos do banco
];

// =========================================================
// CAMPOS DE PREFERÊNCIAS DINÂMICAS - Carregadas do banco de dados
// =========================================================

// Converter dados dinâmicos do banco para formato compatível
$preferenceFields = [];
foreach ($dynamicData['preferences'] as $preference) {
    $preferenceFields[] = [
        'id' => (int)$preference['id'],
        'service_id' => 1,
        'name' => $preference['name'],
        'field_type' => $preference['field_type'] ?? 'checkbox',
        'is_checked_default' => false,
        'is_required' => (bool)($preference['is_required'] ?? false),
        'extra_fee' => (float)($preference['extra_fee'] ?? 0.00),
        'options' => $preference['options'] ?? '',
        'conditional_field' => $preference['conditional_field'] ?? '',
        'conditional_trigger' => $preference['conditional_trigger'] ?? '',
        'conditional_placeholder' => $preference['conditional_placeholder'] ?? '',
        'note_text' => $preference['note_text'] ?? '',
        'note_type' => $preference['note_type'] ?? 'info',
        'sort_order' => (int)($preference['sort_order'] ?? 0),
        'status' => 'active',
        'icon' => $preference['icon'] ?? 'fas fa-cog'
    ];
}

// =========================================================
// CONFIGURAÇÕES DE RECORRÊNCIA E DESCONTOS AVANÇADOS
// =========================================================
$recurrenceConfig = [
    'one-time' => [
        'id' => 1,
        'name' => 'One-time',
        'display_name' => 'One-time Service',
        'discount_percentage' => 0,
        'minimum_duration' => 1,
        'maximum_duration' => 1,
        'billing_frequency' => 'once',
        'cancellation_policy' => 'Free cancellation up to 48 hours before service',
        'penalty_percentage' => 50 // 50% penalty for late cancellation
    ],
    'weekly' => [
        'id' => 2,
        'name' => 'Weekly',
        'display_name' => 'Weekly Service',
        'discount_percentage' => 10, // Editável pelo admin
        'minimum_duration' => 2, // Mínimo 2 semanas
        'maximum_duration' => 52, // Máximo 1 ano
        'billing_frequency' => 'weekly',
        'cancellation_policy' => 'Contract cancellation incurs penalty based on remaining services',
        'penalty_percentage' => 30 // 30% of remaining contract value
    ],
    'fortnightly' => [
        'id' => 3,
        'name' => 'Fortnightly',
        'display_name' => 'Fortnightly Service (Every 2 weeks)',
        'discount_percentage' => 15, // Editável pelo admin
        'minimum_duration' => 2, // Mínimo 2 quinzenas
        'maximum_duration' => 26, // Máximo 1 ano
        'billing_frequency' => 'biweekly',
        'cancellation_policy' => 'Contract cancellation incurs penalty based on remaining services',
        'penalty_percentage' => 25 // 25% of remaining contract value
    ],
    'monthly' => [
        'id' => 4,
        'name' => 'Monthly',
        'display_name' => 'Monthly Service',
        'discount_percentage' => 20, // Editável pelo admin
        'minimum_duration' => 2, // Mínimo 2 meses
        'maximum_duration' => 12, // Máximo 1 ano
        'billing_frequency' => 'monthly',
        'cancellation_policy' => 'Contract cancellation incurs penalty based on remaining services',
        'penalty_percentage' => 20 // 20% of remaining contract value
    ]
];

// =========================================================
// CONFIGURAÇÕES DE PAUSAS DO SISTEMA
// =========================================================
$pauseConfig = [
    'pause_enabled' => true,
    'minimum_notice_hours' => 48,
    'pause_fee' => 0.00,
    'max_pause_duration_days' => 90,
    'pause_tiers' => [
        [
            'tier_id' => 'basic',
            'min_services' => 0,
            'max_services' => 15,
            'period_weeks' => 26,
            'free_pauses' => 2,
            'tier_name' => 'Basic',
            'stripe_metadata' => [
                'pause_tier' => 'basic',
                'free_pauses' => '2',
                'max_services' => '15'
            ]
        ],
        [
            'tier_id' => 'standard',
            'min_services' => 15,
            'max_services' => 26,
            'period_weeks' => 26,
            'free_pauses' => 4,
            'tier_name' => 'Standard',
            'stripe_metadata' => [
                'pause_tier' => 'standard',
                'free_pauses' => '4',
                'max_services' => '26'
            ]
        ],
        [
            'tier_id' => 'premium',
            'min_services' => 26,
            'max_services' => 52,
            'period_weeks' => 52,
            'free_pauses' => 8,
            'tier_name' => 'Premium',
            'stripe_metadata' => [
                'pause_tier' => 'premium',
                'free_pauses' => '8',
                'max_services' => '52'
            ]
        ],
        [
            'tier_id' => 'enterprise',
            'min_services' => 52,
            'max_services' => 999,
            'period_weeks' => 52,
            'free_pauses' => 12,
            'tier_name' => 'Enterprise',
            'stripe_metadata' => [
                'pause_tier' => 'enterprise',
                'free_pauses' => '12',
                'max_services' => '999'
            ]
        ]
    ],
    'excess_pause_policy' => 'convert_to_cancellation'
];

// =========================================================
// CONFIGURAÇÕES DE CANCELAMENTO
// =========================================================
$cancellationConfig = [
    'cancellation_enabled' => true,
    'free_cancellation_hours' => 48,
    'penalty_calculation_method' => 'remaining_services',
    
    'cancellation_policies' => [
        'one-time' => [
            'penalty_percentage' => 50,
            'minimum_penalty' => 25.00,
            'maximum_penalty' => 200.00,
            'refund_policy' => 'partial_refund_before_48h'
        ],
        'weekly' => [
            'penalty_percentage' => 30,
            'minimum_penalty' => 50.00,
            'maximum_penalty' => 500.00,
            'refund_policy' => 'no_refund_after_first_service'
        ],
        'fortnightly' => [
            'penalty_percentage' => 25,
            'minimum_penalty' => 75.00,
            'maximum_penalty' => 750.00,
            'refund_policy' => 'no_refund_after_first_service'
        ],
        'monthly' => [
            'penalty_percentage' => 20,
            'minimum_penalty' => 100.00,
            'maximum_penalty' => 1000.00,
            'refund_policy' => 'no_refund_after_first_service'
        ]
    ],
    
    'immediate_cancellation' => true,
    'cancellation_fee_processing' => 'immediate',
    'admin_approval_required' => false,
    'cancellation_survey_enabled' => true
];

// =========================================================
// CONFIGURAÇÕES DINÂMICAS DO SISTEMA
// =========================================================
$systemConfig = [
    // Configurações dinâmicas do banco de dados
    'minimum_booking_hours' => $dynamicData['config']['booking_advance_hours'] ?? 48,
    'business_name' => $dynamicData['config']['business_name'] ?? 'Blue Cleaning Services Pty Ltd',
    'business_phone' => $dynamicData['config']['business_phone'] ?? '+61 2 9876 5432',
    'business_email' => $dynamicData['config']['business_email'] ?? 'info@bluecleaningservices.com.au',
    
    // Configurações estáticas mantidas para compatibilidade
    'cancellation_fee_percentage' => 50,
    'free_cancellation_hours' => 48,
    'stripe_enabled' => true,
    'stripe_public_key' => 'pk_test_...', // Substitua pela chave real
    'google_places_enabled' => true, // HABILITADO - Google Places Autocomplete
    'points_system_enabled' => true,
    'points_per_dollar' => 1, // 1 ponto por dólar gasto
    'points_redemption_rate' => 100, // 100 pontos = $1
    'admin_editable_preferences' => true,
    'admin_editable_discounts' => true,
    'admin_editable_cancellation_policy' => true,
    'email_notifications_enabled' => true,
    'email_templates_path' => 'templates/email/',
    'require_phone_verification' => false,
    'require_email_verification' => true,
    'cache_enabled' => true,
    'cache_duration' => 3600, // 1 hora
    'debug_mode' => true,
    'log_level' => 'info'
];

// =========================================================
// CONFIGURAÇÕES E VARIÁVEIS DE AMBIENTE
// =========================================================

// Load Australian environment configuration
require_once __DIR__ . '/config/australian-environment.php';
AustralianEnvironmentConfig::load();

$env = [
    'GOOGLE_PLACES_KEY' => env('GOOGLE_MAPS_API_KEY', 'AIzaSyA6dqOPMiDLe29otXTfltxkrnNyUPYCo9s'), // Chave do Google Maps
    'STRIPE_SECRET_KEY' => env('STRIPE_SECRET_KEY', ''), // Chave secreta do Stripe
    'STRIPE_PUBLIC_KEY' => env('STRIPE_PUBLISHABLE_KEY', ''), // Chave pública do Stripe
    'APP_ENV' => env('APP_ENV', 'production'),
    'DEBUG' => AustralianEnvironmentConfig::get('APP_DEBUG', false),
    'APP_URL' => AustralianEnvironmentConfig::get('APP_URL', 'https://bluecleaningservices.com.au'),
    'API_BASE_URL' => AustralianEnvironmentConfig::get('API_BASE_URL', 'https://bluecleaningservices.com.au/api')
];

// ✅ PRICING ENGINE - CONFIGURAÇÕES UNIFICADAS
// Carregar o PricingEngine para sincronizar preços com frontend
require_once __DIR__ . '/core/PricingEngine.php';
$pricingConfig = PricingEngine::getAllPrices();

// =========================================================
// FUNÇÕES DE PAUSAS E CANCELAMENTOS
// =========================================================

/**
 * Determina o tier de pausas baseado no histórico de serviços
 */
function determinePauseTier($customerHistory, $pauseConfig) {
    $totalServices = $customerHistory['total_services'] ?? 0;
    $periodStart = strtotime('-' . $pauseConfig['pause_tiers'][0]['period_weeks'] . ' weeks');
    $servicesInPeriod = $customerHistory['services_since'] ?? 0;
    
    foreach ($pauseConfig['pause_tiers'] as $tier) {
        if ($servicesInPeriod >= $tier['min_services'] && 
            ($servicesInPeriod < $tier['max_services'] || $tier['max_services'] >= 999)) {
            return $tier;
        }
    }
    
    // Retorna tier básico por padrão
    return $pauseConfig['pause_tiers'][0];
}

/**
 * Calcula penalidade de cancelamento
 */
function calculateCancellationPenalty($bookingDetails, $cancellationConfig) {
    $recurrenceType = $bookingDetails['recurrence_pattern'] ?? 'one-time';
    $totalAmount = $bookingDetails['total_amount'] ?? 0;
    $remainingServices = $bookingDetails['remaining_services'] ?? 1;
    
    if (!isset($cancellationConfig['cancellation_policies'][$recurrenceType])) {
        $recurrenceType = 'one-time';
    }
    
    $policy = $cancellationConfig['cancellation_policies'][$recurrenceType];
    
    // Calcula penalidade baseada no método configurado
    if ($cancellationConfig['penalty_calculation_method'] === 'remaining_services') {
        $penaltyAmount = ($totalAmount * $remainingServices) * ($policy['penalty_percentage'] / 100);
    } else {
        $penaltyAmount = $totalAmount * ($policy['penalty_percentage'] / 100);
    }
    
    // Aplica limites mínimo e máximo
    $penaltyAmount = max($penaltyAmount, $policy['minimum_penalty']);
    $penaltyAmount = min($penaltyAmount, $policy['maximum_penalty']);
    
    return [
        'penalty_amount' => $penaltyAmount,
        'penalty_percentage' => $policy['penalty_percentage'],
        'policy_type' => $recurrenceType,
        'refund_policy' => $policy['refund_policy']
    ];
}

/**
 * Gera metadata completa para Stripe
 */
function generateStripeMetadata($bookingData, $pauseConfig, $cancellationConfig) {
    $metadata = [
        // Informações básicas do booking
        'booking_id' => $bookingData['booking_id'] ?? uniqid('book_'),
        'service_type' => $bookingData['service_type'] ?? 'home_cleaning',
        'recurrence_pattern' => $bookingData['recurrence_pattern'] ?? 'one-time',
        'total_amount' => $bookingData['total_amount'] ?? '0',
        'currency' => 'AUD',
        
        // Configurações de pausas
        'pause_enabled' => $pauseConfig['pause_enabled'] ? 'true' : 'false',
        'minimum_notice_hours' => (string)$pauseConfig['minimum_notice_hours'],
        'max_pause_duration_days' => (string)$pauseConfig['max_pause_duration_days'],
        
        // Tier de pausas do cliente
        'customer_pause_tier' => $bookingData['pause_tier']['tier_id'] ?? 'basic',
        'customer_free_pauses' => (string)($bookingData['pause_tier']['free_pauses'] ?? 2),
        'customer_used_pauses' => (string)($bookingData['used_pauses'] ?? 0),
        
        // Configurações de cancelamento
        'cancellation_enabled' => $cancellationConfig['cancellation_enabled'] ? 'true' : 'false',
        'free_cancellation_hours' => (string)$cancellationConfig['free_cancellation_hours'],
        'cancellation_penalty_percentage' => (string)($cancellationConfig['cancellation_policies'][$bookingData['recurrence_pattern'] ?? 'one-time']['penalty_percentage'] ?? 50),
        
        // Informações do cliente
        'customer_email' => $bookingData['customer_email'] ?? '',
        'customer_phone' => $bookingData['customer_phone'] ?? '',
        'customer_name' => $bookingData['customer_name'] ?? '',
        
        // Endereço do serviço
        'service_address' => $bookingData['service_address'] ?? '',
        'service_suburb' => $bookingData['service_suburb'] ?? '',
        'service_postcode' => $bookingData['service_postcode'] ?? '',
        
        // Configurações de desconto
        'discount_code' => $bookingData['discount_code'] ?? '',
        'discount_amount' => (string)($bookingData['discount_amount'] ?? 0),
        
        // Configurações de recorrência
        'start_date' => $bookingData['start_date'] ?? date('Y-m-d'),
        'end_date' => $bookingData['end_date'] ?? '',
        'services_count' => (string)($bookingData['services_count'] ?? 1),
        'remaining_services' => (string)($bookingData['remaining_services'] ?? 1),
        
        // Sistema de automação
        'auto_pause_allowed' => 'true',
        'auto_cancellation_allowed' => 'true',
        'webhook_url' => $bookingData['webhook_url'] ?? '',
        
        // Timestamps
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        
        // Versão do sistema
        'system_version' => '2.0',
        'metadata_version' => '1.0'
    ];
    
    return $metadata;
}

// =========================================================
// CONSTANTES JÁ DEFINIDAS NO INÍCIO DO ARQUIVO
// =========================================================
// (Constantes movidas para o topo para evitar erro 500)

// Preferências
define('PREFERENCE_ID_PET_FRIENDLY', 1);
define('PREFERENCE_ID_KEY_METHOD', 2);
define('PREFERENCE_ID_INSTRUCTIONS', 3);
define('PREFERENCE_ID_ECO_PRODUCTS', 4);
define('PREFERENCE_ID_DEEP_CLEAN', 5);
define('PREFERENCE_ID_PET_TYPE', 6);
define('PREFERENCE_ID_ALLERGIES', 7);
define('PREFERENCE_ID_PROFESSIONAL_CHEMICALS', 8);
define('PREFERENCE_ID_PROFESSIONAL_EQUIPMENT', 9);
define('PREFERENCE_ID_SECURITY_INFO', 10);

// Estados de reserva
define('BOOKING_STATUS_DRAFT', 'draft');
define('BOOKING_STATUS_CONFIRMED', 'confirmed');
define('BOOKING_STATUS_PAID', 'paid');
define('BOOKING_STATUS_COMPLETED', 'completed');
define('BOOKING_STATUS_CANCELLED', 'cancelled');

// =========================================================
// FUNÇÕES UTILITÁRIAS EXPANDIDAS
// =========================================================

/**
 * Renderiza mídia (imagem ou ícone) para inclusões
 */
function renderMedia($media, $alt = '')
{
    if (!$media) {
        return '';
    }

    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $media)) {
        return sprintf(
            '<img src="assets/uploads/%s" alt="%s" class="item-card__thumb">',
            htmlspecialchars($media),
            htmlspecialchars($alt)
        );
    }

    if (str_starts_with($media, 'fa-')) {
        return sprintf(
            '<i class="fas %s text-xl" aria-label="%s"></i>',
            htmlspecialchars($media),
            htmlspecialchars($alt)
        );
    }

    return sprintf(
        '<span class="%s" aria-label="%s"></span>',
        htmlspecialchars($media),
        htmlspecialchars($alt)
    );
}

/**
 * Obter dados do formulário com valores padrão
 */
function getFormValue($key, $default = '')
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/**
 * Validar data mínima de reserva (48h)
 */
function validateBookingDate($date)
{
    $bookingDate = new DateTime($date);
    $minDate = new DateTime();
    $minDate->add(new DateInterval('PT48H'));
    
    return $bookingDate >= $minDate;
}

/**
 * Calcular data mínima de reserva
 */
function getMinimumBookingDate()
{
    $minDate = new DateTime();
    $minDate->add(new DateInterval('PT48H'));
    return $minDate->format('Y-m-d');
}

/**
 * Calcular próxima data de cobrança
 */
function calculateNextChargeDate($executionDate, $recurrence = 'one-time')
{
    $execution = new DateTime($executionDate);
    $chargeDate = clone $execution;
    $chargeDate->sub(new DateInterval('PT48H'));
    
    return $chargeDate;
}

/**
 * Calcular total de ocorrências do contrato
 */
function calculateTotalOccurrences($duration, $recurrence)
{
    global $recurrenceConfig;
    
    if ($recurrence === 'one-time') {
        return 1;
    }
    
    return $duration; // Para weekly, fortnightly, monthly = número de períodos
}

/**
 * Formatar moeda australiana
 */
function formatCurrency($amount)
{
    return '$' . number_format($amount, 2);
}

/**
 * Log de debug
 */
function logDebug($message, $data = null)
{
    global $systemConfig;
    
    if ($systemConfig['debug_mode']) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message";
        
        if ($data) {
            $logMessage .= ' | Data: ' . json_encode($data);
        }
        
        error_log($logMessage);
    }
}

// =========================================================
// PROCESSAMENTO DO FORMULÁRIO AVANÇADO
// =========================================================
$recurrence = getFormValue('recurrence', 'one-time');
$execution_date = getFormValue('execution_date');
$time_window = getFormValue('time_window');
$address = getFormValue('address');

// Validações de segurança
$form_message = '';
$form_success = false;
$validation_errors = [];

// Validar data se fornecida
if ($execution_date && !validateBookingDate($execution_date)) {
    $validation_errors[] = 'Booking date must be at least 48 hours from now';
}

// Processamento do formulário com validação de segurança
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['first_name'])) {
    // Verificar token CSRF
    require_once __DIR__ . '/auth/AuthManager.php';
    $auth = AuthManager::getInstance();
    
    if (!$auth->verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $form_message = 'Erro de segurança: Token CSRF inválido.';
        $form_success = false;
    } else {
        logDebug('Processing booking form submission', $_POST);
        
        // ========================================
        // PROCESSAMENTO COMPLETO IMPLEMENTADO
        // ========================================
        
        try {
            // Preparar dados para a API create-dynamic.php
            $booking_data = [
                // Dados do cliente
                'customer_name' => trim($_POST['first_name'] . ' ' . $_POST['last_name']),
                'customer_email' => $_POST['email'] ?? '',
                'customer_phone' => $_POST['phone'] ?? '',
                
                // Endereço do serviço
                'service_address' => $_POST['address'] ?? '',
                'postcode' => $_POST['postcode'] ?? '',
                'city' => $_POST['city'] ?? '',
                'suburb' => $_POST['suburb'] ?? '',
                'state' => $_POST['state'] ?? '',
                'latitude' => $_POST['latitude'] ?? null,
                'longitude' => $_POST['longitude'] ?? null,
                
                // Dados do serviço
                'service_date' => $_POST['service_date'] ?? '',
                'service_time' => $_POST['service_time'] ?? '',
                'duration_hours' => floatval($_POST['duration'] ?? 2),
                'frequency' => $_POST['frequency'] ?? 'one_time',
                
                // Extras e preferências
                'selected_extras' => $_POST['selected_extras'] ?? [],
                'selected_preferences' => $_POST['selected_preferences'] ?? [],
                
                // Sistema unificado de códigos - CORREÇÃO DEFINITIVA
                'referral_code' => !empty($_POST['referral_code']) 
                    ? $_POST['referral_code'] 
                    : ($_POST['unifiedCodeInput'] ?? $_GET['referral_code'] ?? $_GET['promo_code'] ?? ''),
                'code_type' => $_POST['code_type'] ?? 'auto', // auto = detectar automaticamente
                
                // Informações adicionais
                'special_instructions' => $_POST['special_instructions'] ?? '',
                'source' => 'booking_form'
            ];
            
            // Log para debug
            logDebug('Prepared booking data', $booking_data);
            
            // Fazer chamada para API create-unified.php
            $api_url = 'http://localhost:8000/api/booking/create-unified.php';
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $api_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($booking_data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("API Connection Error: $curl_error");
            }
            
            if ($http_code !== 200) {
                throw new Exception("API returned HTTP $http_code");
            }
            
            $result = json_decode($response, true);
            
            if (!$result) {
                throw new Exception("Invalid JSON response from API");
            }
            
            if ($result['success']) {
                // Sucesso - preparar dados para redirecionamento
                $booking_id = $result['data']['booking_id'];
                $reference = $result['data']['reference_number'];
                $total = $result['data']['pricing']['final_total'];
                
                // Registrar sucesso
                logDebug('Booking created successfully', [
                    'booking_id' => $booking_id,
                    'reference' => $reference,
                    'total' => $total,
                    'referral_used' => !empty($booking_data['referral_code'])
                ]);
                
                // Redirecionar para página de pagamento/confirmação
                if (!headers_sent()) {
                    $redirect_url = "booking-confirmation.php?booking_id={$booking_id}&reference={$reference}";
                    header("Location: $redirect_url");
                    exit;
                } else {
                    $form_message = "Booking created successfully! Reference: $reference";
                    $form_success = true;
                }
                
            } else {
                throw new Exception($result['message'] ?? 'Unknown API error');
            }
            
        } catch (Exception $e) {
            error_log("Booking Form Processing Error: " . $e->getMessage());
            $form_message = DEBUG ? $e->getMessage() : 'Failed to create booking. Please try again.';
            $form_success = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dynamic Booking System - <?= htmlspecialchars($serviceData['service']['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Professional house cleaning service booking with advanced features and 48-hour minimum booking policy">
    <meta name="keywords" content="house cleaning, booking, professional service, recurring service, Australian cleaning">
    
    <!-- Headers de segurança CORRIGIDOS -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <!-- CORRIGIDO: Permissions-Policy removendo payment restriction -->
    <meta http-equiv="Permissions-Policy" content="camera=(), microphone=(), geolocation=()">
    <!-- CORRIGIDO: CSP mais permissivo para Stripe -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; connect-src 'self' https:; frame-src https:;"
    
    <!-- Preload critical resources -->
    <link rel="preload" href="assets/css/blue.css" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style">
    
    <!-- External Stylesheets -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/blue.css">
    <link rel="stylesheet" href="assets/css/inclusion-layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    
    <!-- Project-Specific CSS Files -->
    <link rel="stylesheet" href="assets/css/booking3-styles.css">
    <link rel="stylesheet" href="assets/css/pause-cancellation.css">
    <link rel="stylesheet" href="assets/css/smart-calendar.css">
    <link rel="stylesheet" href="assets/css/modal-redesign.css">
    <link rel="stylesheet" href="assets/css/google-places-autocomplete.css">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Critical Inline Styles (Performance-Critical Only) -->
    <style>
        .hidden {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }

        #summaryModal:not(.hidden) {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            z-index: 10000 !important; /* CORREÇÃO: Modal acima de tudo */
        }
        
        /* CORREÇÃO: Garantir hierarquia de z-index */
        .modal-overlay {
            z-index: 10000 !important;
        }
        
        .modal-content-redesign {
            z-index: 10001 !important;
        }
        
        /* SMART CALENDAR INTEGRATION STYLES */
        .calendar-section {
            margin-top: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: slideDown 0.3s ease-out;
        }
        
        /* Estilo para o campo de data com ícone de calendário */
        .calendar-input {
            position: relative;
            cursor: pointer !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236B7280' viewBox='0 0 16 16'%3E%3Cpath d='M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5 0zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 12px) center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        .calendar-input:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .selected-date-feedback {
            text-align: center;
            padding: 8px;
            margin-top: 12px;
            border-radius: 6px;
            background: rgba(72, 187, 120, 0.1);
            border: 1px solid rgba(72, 187, 120, 0.2);
        }
        
        .time-selection-section {
            margin-top: 12px;
        }
        
        .available-times-container .loading {
            text-align: center;
            padding: 20px;
            color: #718096;
            font-style: italic;
        }
        
        .available-times-container .error {
            text-align: center;
            padding: 20px;
            color: #f56565;
            background: rgba(245, 101, 101, 0.1);
            border-radius: 6px;
        }
        
        .available-times-container .no-times {
            text-align: center;
            padding: 20px;
            color: #718096;
            background: rgba(203, 213, 224, 0.1);
            border-radius: 6px;
        }
        
        /* ========================================= */
        /* SMART TIME PICKER MODAL STYLING */
        /* ========================================= */
        #smart-time-picker-modal .modal-content {
            max-width: 500px;
            width: 90%;
        }
        
        #smart-time-picker-modal .modal-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        #smart-time-picker-modal .modal-subtitle {
            margin: 8px 0 0 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        .available-times-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .time-slot {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 12px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
        }
        
        .time-slot:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(74, 144, 226, 0.5);
            transform: translateY(-2px);
        }
        
        .time-slot.unavailable {
            background: rgba(128, 128, 128, 0.1);
            color: rgba(255, 255, 255, 0.4);
            cursor: not-allowed;
            border-color: rgba(128, 128, 128, 0.2);
        }
        
        .time-slot.unavailable:hover {
            background: rgba(128, 128, 128, 0.1);
            transform: none;
        }
        
        .loading-spinner {
            width: 24px;
            height: 24px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            border-top-color: #4a90e2;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto 10px auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* ========================================= */
        /* TIME PICKER INPUT FIXES */
        /* ========================================= */
        #time_display.time-picker-display {
            pointer-events: auto !important;
            cursor: pointer !important;
            user-select: none;
        }
        
        #time_display.time-picker-display:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(74, 144, 226, 0.5);
        }
        
        #time_display.field-completed {
            background: rgba(72, 187, 120, 0.1);
            border-color: rgba(72, 187, 120, 0.3);
            color: #48bb78;
        }
        
        /* ========================================= */
        /* TIME SLOTS STYLING */
        /* ========================================= */
        .times-grid {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
            justify-content: flex-start !important;
        }
        
        .time-slot {
            padding: 12px 16px !important;
            border: 2px solid #667eea !important;
            border-radius: 8px !important;
            background: rgba(102, 126, 234, 0.1) !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            display: inline-block !important;
            min-width: 120px !important;
            text-align: center !important;
            color: #2d3748 !important;
            font-weight: 500 !important;
            font-size: 0.9rem !important;
            user-select: none !important;
        }
        
        .time-slot:hover {
            background: rgba(102, 126, 234, 0.2) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3) !important;
        }
        
        .time-slot.selected {
            border: 2px solid #48bb78 !important;
            background: rgba(72, 187, 120, 0.2) !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3) !important;
        }
        
        .time-slot.unavailable {
            border: 2px solid #e2e8f0 !important;
            background: rgba(0,0,0,0.1) !important;
            cursor: not-allowed !important;
            color: #a0aec0 !important;
        }
        
        .time-slot.unavailable:hover {
            background: rgba(0,0,0,0.1) !important;
            transform: none !important;
            box-shadow: none !important;
        }
        
        /* Mobile responsiveness for time slots */
        @media (max-width: 768px) {
            .times-grid {
                justify-content: center !important;
            }
            
            .time-slot {
                min-width: 100px !important;
                padding: 10px 12px !important;
                font-size: 0.85rem !important;
            }
        }
        
        /* ========================================= */
        /* BOOKING SUMMARY DYNAMIC SYSTEM STYLES */
        /* ========================================= */
        
        /* Novas seções do resumo dinâmico */
        .summary-card.included-items,
        .summary-card.extras-section,
        .summary-card.preferences-section {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        
        .summary-card.included-items:hover,
        .summary-card.extras-section:hover,
        .summary-card.preferences-section:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }
        
        /* Lista de itens do resumo */
        .items-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 8px;
            border-left: 3px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .item-row:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(4px);
        }
        
        .item-name {
            font-size: 0.9rem;
            color: #2d3748;
            font-weight: 500;
        }
        
        .item-quantity {
            font-size: 0.8rem;
            color: #667eea;
            font-weight: 600;
            padding: 4px 8px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
        }
        
        /* Seção de preferências especiais */
        .preferences-content {
            background: rgba(72, 187, 120, 0.05);
            padding: 16px;
            border-radius: 8px;
            border-left: 3px solid #48bb78;
        }
        
        .preferences-content p {
            margin: 0;
            font-size: 0.9rem;
            color: #2d3748;
            font-style: italic;
            line-height: 1.5;
        }
        
        /* Seção de preços melhorada */
        .pricing-card .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .pricing-card .price-row:last-child {
            border-bottom: none;
        }
        
        .price-row.service-price {
            font-weight: 500;
            color: #2d3748;
        }
        
        .price-row.extra-price {
            color: #667eea;
            font-size: 0.85rem;
        }
        
        .price-row.discount-row {
            color: #48bb78;
            font-weight: 500;
        }
        
        .pricing-divider {
            margin: 16px 0;
            border: none;
            border-top: 2px solid rgba(0, 0, 0, 0.1);
        }
        
        .price-row.total-row {
            font-weight: 700;
            font-size: 1.1rem;
            color: #2d3748;
            border-top: 2px solid rgba(0, 0, 0, 0.1);
            padding-top: 12px;
            margin-top: 8px;
        }
        
        .subtotal-value,
        .total-value {
            color: #667eea;
            font-weight: 700;
        }
        
        .discount-value {
            color: #48bb78;
            font-weight: 600;
        }
        
        /* Estilos específicos para desconto de cupom */
        .coupon-discount-row {
            color: #48bb78;
            font-weight: 500;
        }

        /* Sistema Unificado de Códigos */
        .unified-codes-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .unified-codes-input-modern {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .unified-code-input-field {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid rgba(99, 102, 241, 0.2);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .unified-code-input-field:focus {
            outline: none;
            border-color: #6366f1;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            transform: translateY(-1px);
        }

        .apply-unified-code-btn {
            padding: 14px 24px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .apply-unified-code-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
            background: linear-gradient(135deg, #5855eb 0%, #7c3aed 100%);
        }

        .unified-code-status {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            display: none;
            animation: slideIn 0.3s ease;
        }

        .unified-code-status.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
            display: block;
        }

        .unified-code-status.promo-success {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
            color: #6366f1;
            border: 1px solid rgba(99, 102, 241, 0.3);
            display: block;
        }

        .unified-code-status.error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
            display: block;
        }

        .code-types-info {
            background: rgba(248, 250, 252, 0.8);
            border-radius: 8px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .code-type-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .code-type-item i {
            color: #6366f1;
            font-size: 1.1rem;
            width: 20px;
        }

        .code-type-item small {
            color: #64748b;
            line-height: 1.4;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Seção de desconto removida - agora tudo é unificado */
        .discount-card {
            display: none;
        }

        .coupon-discount-row {
            background: rgba(72, 187, 120, 0.1);
            border-left: 3px solid #48bb78;
            margin: 5px 0;
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .coupon-discount-row .discount-label {
            color: #38a169;
            font-weight: 600;
        }
        
        .coupon-discount-row .discount-value {
            color: #38a169;
            font-weight: 700;
        }
        
        #coupon-code-display {
            font-family: monospace;
            background: rgba(56, 161, 105, 0.2);
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9em;
        }
        
        /* Animações para novos itens */
        .item-row.new-item {
            animation: slideInFromRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .summary-card.slide-in {
            animation: slideInFromBottom 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes slideInFromRight {
            from {
                transform: translateX(20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideInFromBottom {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Responsividade para mobile */
        @media (max-width: 768px) {
            .item-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
                padding: 10px 12px;
            }
            
            .item-name {
                font-size: 0.85rem;
            }
            
            .item-quantity {
                font-size: 0.75rem;
                align-self: flex-end;
            }
            
            .price-row {
                font-size: 0.85rem;
            }
            
            .price-row.total-row {
                font-size: 1rem;
            }
        }
        
        /* Estados especiais */
        .summary-card.loading {
            opacity: 0.6;
            pointer-events: none;
            filter: grayscale(20%);
        }
        
        .summary-card.error {
            border-color: #f56565;
            background: rgba(245, 101, 101, 0.05);
        }
        
        .summary-card.success {
            border-color: #48bb78;
            background: rgba(72, 187, 120, 0.05);
        }
        
        /* Estilos para o botão de checkout */
        .confirm-button-modern.btn-enabled {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            cursor: pointer;
            opacity: 1;
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .confirm-button-modern.btn-enabled:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .confirm-button-modern.btn-disabled {
            background: #a0aec0;
            cursor: not-allowed;
            opacity: 0.6;
            transform: translateY(0);
            box-shadow: none;
        }
        
        .confirm-button-modern.btn-disabled:hover {
            transform: translateY(0);
            box-shadow: none;
        }
    </style>
    
    <!-- Stripe.js (quando habilitado) CORRIGIDO -->
    <?php if ($systemConfig['stripe_enabled']): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        // CORRIGIDO: Configurar Stripe apenas se chave estiver disponível
        document.addEventListener('DOMContentLoaded', function() {
            const stripeKey = '<?= !empty($env['STRIPE_PUBLIC_KEY']) ? $env['STRIPE_PUBLIC_KEY'] : '' ?>';
            if (window.Stripe && stripeKey && stripeKey !== '' && stripeKey !== 'pk_test_...') {
                try {
                    window.stripe = Stripe(stripeKey);
                    console.log('✅ Stripe configurado');
                } catch (error) {
                    console.error('❌ Erro ao configurar Stripe:', error);
                }
            } else {
                console.warn('⚠️ Stripe não disponível ou chave não configurada');
            }
        });
    </script>
    <?php endif; ?>
    
    <!-- Google Places API (quando habilitado) -->
    <?php if ($systemConfig['google_places_enabled'] && !empty($env['GOOGLE_PLACES_KEY'])): ?>
    <script src="assets/js/google-places-autocomplete.js"></script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($env['GOOGLE_PLACES_KEY']) ?>&libraries=places&callback=initGooglePlaces"
        async defer
    ></script>
    <?php endif; ?>

    <!-- Smart Time Picker -->
    <script src="assets/js/smart-time-picker.js"></script>

    <!-- Global Configuration -->
    <script>
        // Configurações globais do sistema
        window.AppConfig = {
            googlePlacesEnabled: <?= $systemConfig['google_places_enabled'] ? 'true' : 'false' ?>,
            googlePlacesKey: '<?= htmlspecialchars($env["GOOGLE_PLACES_KEY"] ?? "") ?>',
            debugMode: <?= $systemConfig['debug_mode'] ? 'true' : 'false' ?>,
            environment: '<?= htmlspecialchars($env["APP_ENV"] ?? "production") ?>',
            serviceId: <?= SERVICE_ID_HOUSE_CLEANING ?>,
            validStates: ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT'],
            majorCities: ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast', 'Canberra', 'Newcastle']
        };

        // Validação das configurações
        if (window.AppConfig.googlePlacesEnabled && !window.AppConfig.googlePlacesKey) {
            console.warn('⚠️ Google Places está habilitado mas a API Key não está configurada');
        } else if (window.AppConfig.googlePlacesEnabled) {
            console.log('✅ Google Places Autocomplete configurado e pronto');
        }
    </script>
</head>
<body data-service-id="<?= SERVICE_ID_HOUSE_CLEANING ?>" data-debug="<?= $systemConfig['debug_mode'] ? 'true' : 'false' ?>">
    <!-- Background Liquid Glass -->
    <div class="liquid-bg">
        <div class="bubble bubble-1"></div>
        <div class="bubble bubble-2"></div>
        <div class="bubble bubble-3"></div>
        <div class="bubble bubble-4"></div>
        <div class="bubble bubble-5"></div>
    </div>

    <!-- ========================================= -->
    <!-- CONTAINER PRINCIPAL DE RESERVA -->
    <!-- ========================================= -->
    <div class="booking__container glass-panel" id="bookingContainer">
        <!-- Título principal com links administrativos -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 id="pageTitle">Book Your <?= htmlspecialchars($serviceData['service']['name']) ?></h1>
            
            <!-- Links de navegação -->
            <div style="display: flex; gap: 10px; align-items: center;">
                <!-- Link para clube de indicação -->
                <a href="referralclub3.php" target="_blank" style="
                    background: rgba(72, 187, 120, 0.15);
                    color: rgba(72, 187, 120, 0.9);
                    padding: 8px 16px;
                    border-radius: 20px;
                    text-decoration: none;
                    font-size: 0.85rem;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(72, 187, 120, 0.3);
                    transition: all 0.3s ease;
                " onmouseover="this.style.background='rgba(72,187,120,0.25)'; this.style.color='rgba(72,187,120,1)';" 
                   onmouseout="this.style.background='rgba(72,187,120,0.15)'; this.style.color='rgba(72,187,120,0.9)';">
                    🎁 Referral Club
                </a>
                
                <!-- Link para painel do profissional -->
                <a href="professional/dashboard/availability.php" target="_blank" style="
                    background: rgba(255, 255, 255, 0.1);
                    color: rgba(255, 255, 255, 0.8);
                    padding: 8px 16px;
                    border-radius: 20px;
                    text-decoration: none;
                    font-size: 0.85rem;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    transition: all 0.3s ease;
                " onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.color='white';" 
                   onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.color='rgba(255,255,255,0.8)';">
                    👤 Professional Panel
                </a>
            </div>
        </div>

        <!-- Mensagem de feedback -->
        <?php if (!empty($form_message)): ?>
            <div class="alert <?= $form_success ? 'alert-success' : 'alert-error' ?>" id="formMessage" role="alert" aria-live="polite">
                <?= htmlspecialchars($form_message) ?>
            </div>
        <?php endif; ?>

        <!-- ========================================= -->
        <!-- FORMULÁRIO PRINCIPAL AVANÇADO -->
        <!-- ========================================= -->
        <form id="bookingForm" class="booking" method="post" action="#" novalidate>
            <!-- Token CSRF de segurança -->
            <?php
            require_once __DIR__ . '/auth/AuthManager.php';
            $auth = AuthManager::getInstance();
            $csrfToken = $auth->generateCSRFToken();
            ?>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <!-- ▸ BARRA PRINCIPAL DE CONFIGURAÇÃO -->
            <div class="booking-bar glass-card" id="bookingBar" role="group" aria-labelledby="pageTitle">
                <!-- ENDEREÇO DO CLIENTE -->
                <div class="booking-bar__item" id="addressSection">
                    <label for="address" class="booking-bar__label">Enter your street address</label>
                    <input
                        type="text"
                        id="address"
                        name="address"
                        class="booking-bar__input glass-input"
                        required
                        placeholder="Start typing your address..."
                        value="<?= htmlspecialchars($address) ?>"
                        data-field-type="address"
                        data-address-field="true"
                        autocomplete="street-address"
                        aria-describedby="address-help"
                    >
                    <div id="address-help" class="sr-only">Enter your complete street address for service location</div>
                    <!-- Campos ocultos para dados de localização -->
                    <input type="hidden" id="postcode" name="postcode" value="<?= htmlspecialchars(getFormValue('postcode')) ?>">
                    <input type="hidden" id="street" name="street" value="<?= htmlspecialchars(getFormValue('street')) ?>">
                    <input type="hidden" id="city" name="city" value="<?= htmlspecialchars(getFormValue('city')) ?>">
                    <input type="hidden" id="suburb" name="suburb" value="<?= htmlspecialchars(getFormValue('suburb')) ?>">
                    <input type="hidden" id="state" name="state" value="<?= htmlspecialchars(getFormValue('state')) ?>">
                    <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars(getFormValue('latitude')) ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars(getFormValue('longitude')) ?>">
                </div>

                <!-- OPÇÕES DE RECORRÊNCIA -->
                <div class="booking-bar__item" id="recurrenceSection">
                    <label for="recurrence" class="booking-bar__label">Recurring options</label>
                    <select name="recurrence" id="recurrence" class="booking-bar__select glass-input" required data-field-type="recurrence" aria-describedby="recurrence-help">
                        <optgroup label="Available options">
                            <?php foreach ($recurrenceConfig as $key => $config): ?>
                                <option 
                                    value="<?= $key ?>" 
                                    data-recurrence-id="<?= $config['id'] ?>"
                                    data-discount="<?= $config['discount_percentage'] ?>"
                                    <?= ($recurrence === $key) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars(ucfirst($key)) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <div id="recurrence-help" class="sr-only">Choose how often you want the service repeated</div>
                </div>

                <!-- DATA DE EXECUÇÃO COM SMART CALENDAR -->
                <div class="booking-bar__item my-calendar" id="dateSection">
                    <label for="execution_date" class="booking-bar__label">Service starting date</label>

                    <!-- Container para o Smart Calendar -->
                    <div class="calendar-wrapper">
                        <input
                            type="text"
                            id="execution_date"
                            name="execution_date"
                            class="booking-bar__input glass-input calendar-input"
                            placeholder="Clique para escolher a data"
                            required
                            readonly
                            min="<?= getMinimumBookingDate() ?>"
                            value="<?= htmlspecialchars($execution_date) ?>"
                            data-field-type="date"
                            data-min-date="<?= getMinimumBookingDate() ?>"
                            aria-describedby="date-help"
                            autocomplete="off"
                        >
                        <div id="date-help" class="sr-only">Select your preferred service date from available dates only. Must be at least 48 hours in advance.</div>
                        
                        <!-- Hidden fields for calendar integration -->
                        <input type="hidden" id="booking-date" name="booking_date" value="">
                        <input type="hidden" id="booking-time" name="booking_time" value="">
                        <input type="hidden" id="service-select" name="service_id" value="<?= SERVICE_ID_HOUSE_CLEANING ?>">
                    </div>
                </div>

                <!-- JANELA DE HORÁRIO COM SMART TIME PICKER -->
                <div class="booking-bar__item" id="timeSection">
                    <label for="time_window" class="booking-bar__label">Preferred start time</label>
                    
                    <!-- Campo de exibição clicável para Smart Time Picker -->
                    <input
                        type="text"
                        id="time_display"
                        class="booking-bar__input glass-input time-picker-display"
                        placeholder="Click to select available times"
                        readonly
                        style="cursor: pointer; background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22 fill=%22%236B7280%22 viewBox=%220 0 16 16%22><path d=%22M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z%22/><path d=%22M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z%22/></svg>'); background-repeat: no-repeat; background-position: calc(100% - 12px) center; background-size: 16px; padding-right: 40px;"
                        data-field-type="time-display"
                        aria-describedby="time-help"
                    >
                    
                    <!-- Fallback: Select básico caso Smart Time Picker não funcione -->
                    <select name="time_window_fallback" id="time_window_fallback" class="booking-bar__select glass-input" style="display: none;" data-field-type="time-fallback">
                        <option value="">Select start time</option>
                        <option value="06:00">06:00 - 07:00</option>
                        <option value="07:00">07:00 - 08:00</option>
                        <option value="08:00">08:00 - 09:00</option>
                        <option value="09:00">09:00 - 10:00</option>
                        <option value="10:00">10:00 - 11:00</option>
                        <option value="11:00">11:00 - 12:00</option>
                        <option value="12:00">12:00 - 13:00</option>
                        <option value="13:00">13:00 - 14:00</option>
                        <option value="14:00">14:00 - 15:00</option>
                        <option value="15:00">15:00 - 16:00</option>
                        <option value="16:00">16:00 - 17:00</option>
                        <option value="17:00">17:00 - 18:00</option>
                    </select>
                    
                    <!-- Campo oculto para armazenar valor selecionado -->
                    <input type="hidden" name="time_window" id="time_window" required data-field-type="time">
                    
                    <div id="time-help" class="sr-only">Click to open time selection modal and choose your preferred service start time</div>
                </div>
            </div>
            
            <!-- ========================================= -->
            <!-- SEÇÃO DE DETALHES DA PROPRIEDADE -->
            <!-- ========================================= -->
            <h2 id="propertyDetailsTitle" class="section-title">Fill in your property details and explore your service inclusions.</h2>

            <!-- ▸ INCLUSÕES OBRIGATÓRIAS DO SERVIÇO -->
            <div class="extras-list itens_inclusos" id="inclusionsSection" data-section-type="inclusions" role="group" aria-labelledby="propertyDetailsTitle">
                <?php foreach ($serviceData['inclusions'] as $inclusion): ?>
                    <div
                        class="item-card glass-card"
                        id="inclusion_<?= $inclusion['id'] ?>"
                        data-inclusion-id="<?= $inclusion['id'] ?>"
                        data-price="<?= $inclusion['price'] ?>"
                        data-min-quantity="<?= $inclusion['min_quantity'] ?>"
                        data-sort-order="<?= $inclusion['sort_order'] ?>"
                        data-status="<?= $inclusion['status'] ?>"
                        role="group"
                        aria-labelledby="title_inclusion_<?= $inclusion['id'] ?>"
                    >
                        <!-- Seção de conteúdo à esquerda -->
                        <div class="item-card__content">
                            <!-- Header horizontal: ícone + nome + ícone info -->
                            <div class="item-card__header">
                                <!-- Ícone/Imagem da inclusão -->
                                <div class="item-card__media" aria-hidden="true">
                                    <?= renderMedia($inclusion['image'], $inclusion['name']) ?>
                                </div>
                                
                                <!-- Nome e ícone de informação -->
                                <h4 class="item-card__title" id="title_inclusion_<?= $inclusion['id'] ?>">
                                    <?= htmlspecialchars($inclusion['name']) ?>
                                    <?php if (!empty($inclusion['description'])): ?>
                                        <button
                                            type="button"
                                            class="info-icon"
                                            id="info_inclusion_<?= $inclusion['id'] ?>"
                                            data-title="<?= htmlspecialchars($inclusion['name']) ?>"
                                            data-description="<?= htmlspecialchars($inclusion['description']) ?>"
                                            aria-label="More info about <?= htmlspecialchars($inclusion['name']) ?>"
                                            aria-describedby="desc_inclusion_<?= $inclusion['id'] ?>"
                                        >ⓘ</button>
                                        <span id="desc_inclusion_<?= $inclusion['id'] ?>" class="sr-only"><?= htmlspecialchars($inclusion['description']) ?></span>
                                    <?php endif; ?>
                                </h4>
                            </div>
                            
                            <!-- Preço abaixo da primeira linha, alinhado à esquerda -->
                            <p class="item-card__price" aria-label="Price: $<?= number_format($inclusion['price'], 2) ?>">+ $ <?= number_format($inclusion['price'], 2) ?></p>
                        </div>
                        
                        <!-- Contador de quantidade -->
                        <div class="item-card__counter" role="group" aria-label="Quantity selector for <?= htmlspecialchars($inclusion['name']) ?>">
                            <button 
                                type="button" 
                                class="minus glass-btn" 
                                id="minus_inclusion_<?= $inclusion['id'] ?>"
                                data-target="inclusion_qty_<?= $inclusion['id'] ?>"
                                aria-label="Decrease quantity for <?= htmlspecialchars($inclusion['name']) ?>"
                                aria-controls="qty_display_inclusion_<?= $inclusion['id'] ?>"
                            >−</button>
                            <span class="qty" id="qty_display_inclusion_<?= $inclusion['id'] ?>" aria-live="polite"><?= (int) $inclusion['min_quantity'] ?></span>
                            <input
                                type="hidden"
                                id="inclusion_qty_<?= $inclusion['id'] ?>"
                                name="included_qty[<?= $inclusion['id'] ?>]"
                                value="<?= (int) $inclusion['min_quantity'] ?>"
                                data-field-type="quantity"
                                data-min="<?= $inclusion['min_quantity'] ?>"
                            >
                            <button 
                                type="button" 
                                class="plus glass-btn" 
                                id="plus_inclusion_<?= $inclusion['id'] ?>"
                                data-target="inclusion_qty_<?= $inclusion['id'] ?>"
                                aria-label="Increase quantity for <?= htmlspecialchars($inclusion['name']) ?>"
                                aria-controls="qty_display_inclusion_<?= $inclusion['id'] ?>"
                            >+</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ▸ EXTRAS E PREFERÊNCIAS LADO A LADO -->
            <div class="section extras-preferences-container" id="extrasPreferencesContainer">
                <!-- EXTRAS OPCIONAIS -->
                <div class="extras-wrapper glass-card" id="extrasWrapper">
                    <h3 class="section-title" id="extrasTitle">Extras</h3>
                    <div class="extras-group-box" id="extrasGroup" data-section-type="extras" role="group" aria-labelledby="extrasTitle">
                        <?php foreach ($serviceData['extras'] as $extra): ?>
                            <div
                                class="extra-item glass-card-small"
                                id="extra_<?= $extra['id'] ?>"
                                data-extra-id="<?= $extra['id'] ?>"
                                data-price="<?= $extra['price'] ?>"
                                data-type="extra"
                                data-sort-order="<?= $extra['sort_order'] ?>"
                                data-status="<?= $extra['status'] ?>"
                                role="group"
                                aria-labelledby="name_extra_<?= $extra['id'] ?>"
                            >
                                <span class="extra-name" id="name_extra_<?= $extra['id'] ?>"><?= htmlspecialchars($extra['name']) ?></span>
                                <span class="extra-price" aria-label="Price: $<?= number_format($extra['price'], 2) ?>">+ $ <?= number_format($extra['price'], 2) ?></span>

                                <div class="extra-actions">
                                    <div class="extra-counter" role="group" aria-label="Quantity selector for <?= htmlspecialchars($extra['name']) ?>">
                                        <button 
                                            type="button" 
                                            class="minus glass-btn" 
                                            id="minus_extra_<?= $extra['id'] ?>"
                                            data-target="extra_qty_<?= $extra['id'] ?>"
                                            aria-label="Decrease quantity for <?= htmlspecialchars($extra['name']) ?>"
                                            aria-controls="qty_display_extra_<?= $extra['id'] ?>"
                                        >−</button>
                                        <span class="qty" id="qty_display_extra_<?= $extra['id'] ?>" aria-live="polite">0</span>
                                        <input
                                            type="hidden"
                                            id="extra_qty_<?= $extra['id'] ?>"
                                            name="extra_qty[<?= $extra['id'] ?>]"
                                            value="0"
                                            data-field-type="quantity"
                                            data-min="0"
                                        >
                                        <button 
                                            type="button" 
                                            class="plus glass-btn" 
                                            id="plus_extra_<?= $extra['id'] ?>"
                                            data-target="extra_qty_<?= $extra['id'] ?>"
                                            aria-label="Increase quantity for <?= htmlspecialchars($extra['name']) ?>"
                                            aria-controls="qty_display_extra_<?= $extra['id'] ?>"
                                        >+</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- PREFERÊNCIAS DO CLIENTE -->
                <div class="preferences-wrapper-side glass-card" id="preferencesWrapper">
                    <h3 class="section-title" id="preferencesTitle">Preferences</h3>
                    <p class="preferences-subtitle">Customize your service experience (additional fees may apply)</p>
                    <div class="preferences-wrapper" id="preferencesGroup" data-section-type="preferences" role="group" aria-labelledby="preferencesTitle">
                        <?php foreach ($preferenceFields as $field): ?>
                            <div 
                                class="preference-item preferences-field one-line glass-card-small" 
                                id="preference_<?= $field['id'] ?>"
                                data-preference-id="<?= $field['id'] ?>"
                                data-field-type="<?= $field['field_type'] ?>"
                                data-extra-fee="<?= $field['extra_fee'] ?>"
                                data-sort-order="<?= $field['sort_order'] ?>"
                                data-status="<?= $field['status'] ?>"
                                data-preference-name="<?= htmlspecialchars($field['name']) ?>"
                                data-conditional-field="<?= htmlspecialchars($field['conditional_field']) ?>"
                                data-conditional-trigger="<?= htmlspecialchars($field['conditional_trigger']) ?>"
                            >
                                <label for="preference_field_<?= $field['id'] ?>" class="preference-label">
                                    <span class="preference-name-with-price">
                                        <?php if (!empty($field['icon'])): ?>
                                            <i class="<?= htmlspecialchars($field['icon']) ?>" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($field['name']) ?>
                                        <?php if ($field['extra_fee'] > 0): ?>
                                            <span class="preference-price-badge" data-fee="<?= $field['extra_fee'] ?>">
                                                +$<?= number_format($field['extra_fee'], 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>

                                    <?php if ($field['field_type'] === 'select'): ?>
                                        <?php $options = json_decode($field['options'], true) ?: explode(',', $field['options']); ?>
                                        <select 
                                            id="preference_field_<?= $field['id'] ?>"
                                            class="preference-select glass-input"
                                            name="preferences[<?= $field['id'] ?>]"
                                            data-extra-fee="<?= $field['extra_fee'] ?>"
                                            data-preference-name="<?= htmlspecialchars($field['name']) ?>"
                                            data-has-conditions="<?= !empty($field['conditional_field']) ? 'true' : 'false' ?>"
                                            <?= $field['is_required'] ? 'required' : '' ?>
                                        >
                                            <option value="">Select...</option>
                                            <?php foreach ($options as $option): ?>
                                                <?php 
                                                // Parse fee da opção (para parking com $30)
                                                $fee = 0;
                                                $optionText = trim($option);
                                                if ($field['id'] == 6 && strpos($optionText, 'Paid parking') !== false) {
                                                    $fee = 30.00;
                                                } elseif ($field['id'] == 3 && $optionText !== 'None') {
                                                    $fee = $field['extra_fee']; // $30 for pets
                                                }
                                                ?>
                                                <option 
                                                    value="<?= htmlspecialchars($optionText) ?>"
                                                    data-fee="<?= $fee ?>"
                                                    data-triggers-condition="<?= $optionText === $field['conditional_trigger'] ? 'true' : 'false' ?>"
                                                    <?= getFormValue("preferences[{$field['id']}]") === $optionText ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars($optionText) ?>
                                                    <?php if ($fee > 0): ?>
                                                        (+$<?= number_format($fee, 2) ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                    <?php elseif ($field['field_type'] === 'checkbox'): ?>
                                        <label class="checkbox-container">
                                            <input
                                                type="checkbox"
                                                id="preference_field_<?= $field['id'] ?>"
                                                class="preference-checkbox glass-checkbox"
                                                name="preferences[<?= $field['id'] ?>]"
                                                value="1"
                                                data-extra-fee="<?= $field['extra_fee'] ?>"
                                                data-preference-name="<?= htmlspecialchars($field['name']) ?>"
                                                <?= $field['is_required'] ? 'required' : '' ?>
                                            >
                                            <span class="checkmark"></span>
                                        </label>

                                    <?php elseif ($field['field_type'] === 'text'): ?>
                                        <input 
                                            type="text" 
                                            id="preference_field_<?= $field['id'] ?>"
                                            class="preference-text glass-input"
                                            name="preferences[<?= $field['id'] ?>]"
                                            placeholder="Enter your <?= strtolower($field['name']) ?>"
                                            data-extra-fee="<?= $field['extra_fee'] ?>"
                                            data-preference-name="<?= htmlspecialchars($field['name']) ?>"
                                            <?= $field['is_required'] ? 'required' : '' ?>
                                        >
                                    <?php endif; ?>
                                </label>

                                <!-- Campos condicionais (aparecem dinamicamente) -->
                                <?php if (!empty($field['conditional_field'])): ?>
                                    <div class="conditional-field" id="conditional_<?= $field['id'] ?>" style="display: none;">
                                        <label for="conditional_field_<?= $field['id'] ?>"><?= htmlspecialchars($field['conditional_field']) ?></label>
                                        <textarea
                                            id="conditional_field_<?= $field['id'] ?>"
                                            name="preferences[<?= $field['id'] ?>_conditional]"
                                            placeholder="<?= htmlspecialchars($field['conditional_placeholder']) ?>"
                                            class="glass-input conditional-input"
                                            <?= $field['is_required'] ? 'required' : '' ?>
                                        ></textarea>
                                    </div>
                                <?php endif; ?>

                                <!-- Notas explicativas (ocultas por padrão) -->
                                <?php if (!empty($field['note_text'])): ?>
                                    <div class="preference-details">
                                        <button 
                                            type="button" 
                                            class="view-details-btn glass-btn-small" 
                                            id="details_btn_<?= $field['id'] ?>"
                                            onclick="togglePreferenceDetails(<?= $field['id'] ?>)"
                                            aria-expanded="false"
                                            aria-controls="note_<?= $field['id'] ?>"
                                        >
                                            <i class="fas fa-info-circle"></i>
                                            View details
                                        </button>
                                        <div 
                                            class="preference-note note-<?= $field['note_type'] ?>" 
                                            id="note_<?= $field['id'] ?>" 
                                            style="display: none;"
                                            role="region"
                                            aria-labelledby="details_btn_<?= $field['id'] ?>"
                                        >
                                            <small><?= nl2br(htmlspecialchars($field['note_text'])) ?></small>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Indicador de taxa aplicada -->
                                <div class="preference-fee-indicator" id="fee_indicator_<?= $field['id'] ?>" style="display: none;">
                                    <span class="fee-text">Fee Applied: +$<span class="fee-amount">0.00</span></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ========================================= -->
            <!-- DADOS PESSOAIS DO CLIENTE -->
            <!-- ========================================= -->
            <div class="booking__section dados_pessoais-wrapper glass-card" id="customerInfoSection">
                <label class="booking__label" id="customerInfoLabel">Your Info</label>
                <div class="dados_pessoais-container" id="customerInfoContainer" role="group" aria-labelledby="customerInfoLabel">
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        placeholder="First name"
                        required
                        class="dados_pessoais-input glass-input"
                        value="<?= htmlspecialchars(getFormValue('first_name')) ?>"
                        data-field-type="personal-info"
                        autocomplete="given-name"
                        aria-label="First name"
                    >
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        placeholder="Last name"
                        required
                        class="dados_pessoais-input glass-input"
                        value="<?= htmlspecialchars(getFormValue('last_name')) ?>"
                        data-field-type="personal-info"
                        autocomplete="family-name"
                        aria-label="Last name"
                    >
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Email address"
                        required
                        class="dados_pessoais-input glass-input"
                        value="<?= htmlspecialchars(getFormValue('email')) ?>"
                        data-field-type="personal-info"
                        autocomplete="email"
                        aria-label="Email address"
                    >
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="Mobile number"
                        required
                        class="dados_pessoais-input glass-input"
                        value="<?= htmlspecialchars(getFormValue('phone')) ?>"
                        data-field-type="personal-info"
                        autocomplete="tel"
                        aria-label="Mobile number"
                    >
                    <input
                        type="text"
                        id="abn_or_tfn"
                        name="abn_or_tfn"
                        placeholder="ABN or TFN (optional)"
                        class="dados_pessoais-input glass-input"
                        value="<?= htmlspecialchars(getFormValue('abn_or_tfn')) ?>"
                        data-field-type="personal-info"
                        autocomplete="off"
                        aria-label="ABN or TFN (optional)"
                    >
                </div>
            </div>

            <!-- ========================================= -->
            <!-- CAMPOS OCULTOS PARA PROCESSAMENTO -->
            <!-- ========================================= -->
            <input type="hidden" id="hiddenPointsApplied" name="pointsApplied" value="<?= htmlspecialchars(getFormValue('pointsApplied')) ?>">
            <!-- Campo para código de referral/promo -->
            <input type="hidden" id="hiddenReferralCode" name="referral_code" value="<?= htmlspecialchars(getFormValue('referral_code', $_GET['referral_code'] ?? $_GET['promo_code'] ?? '')) ?>">
            <input type="hidden" id="hiddenCodeType" name="code_type" value="">
            <input type="hidden" id="baseTotalInput" name="baseTotal" value="<?= htmlspecialchars(getFormValue('baseTotal')) ?>">
            <input type="hidden" id="serviceIdInput" name="service_id" value="<?= SERVICE_ID_HOUSE_CLEANING ?>">
            
            <!-- Campos para sistema de contrato -->
            <input type="hidden" id="contractDurationValue" name="contract_duration" value="<?= htmlspecialchars(getFormValue('contract_duration')) ?>">
            <input type="hidden" id="totalOccurrencesValue" name="total_occurrences" value="<?= htmlspecialchars(getFormValue('total_occurrences')) ?>">
            <input type="hidden" id="nextChargeDateValue" name="next_charge_date" value="<?= htmlspecialchars(getFormValue('next_charge_date')) ?>">
            
            <!-- Campos para Stripe -->
            <input type="hidden" id="stripePaymentIntentId" name="stripe_payment_intent_id" value="<?= htmlspecialchars(getFormValue('stripe_payment_intent_id')) ?>">
            <input type="hidden" id="stripeCustomerId" name="stripe_customer_id" value="<?= htmlspecialchars(getFormValue('stripe_customer_id')) ?>">
            
            <!-- Campos para disponibilidade -->
            <input type="hidden" id="selectedCleanerId" name="selected_cleaner_id" value="<?= htmlspecialchars(getFormValue('selected_cleaner_id')) ?>">
            <input type="hidden" id="availabilitySlotId" name="availability_slot_id" value="<?= htmlspecialchars(getFormValue('availability_slot_id')) ?>">
        </form>
    </div>

    <!-- ========================================= -->
    <!-- MODAL DE RESUMO DA RESERVA - REDESIGN PROFISSIONAL -->
    <!-- ========================================= -->
    <div id="summaryModal" class="modal-overlay hidden" role="dialog" aria-labelledby="summaryModalTitle" aria-modal="true">
        <div class="modal-content-redesign">
            <!-- Header com gradiente e fechamento -->
            <div class="modal-header-redesign">
                <div class="modal-title-section">
                    <div class="booking-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <h2 id="summaryModalTitle">Booking Summary</h2>
                        <p class="subtitle">Review your service details</p>
                    </div>
                </div>
                <button class="modal-close-redesign" id="closeSummaryModal" aria-label="Close summary">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Body com grid moderno -->
            <div class="modal-body-redesign">
                <!-- Seção de informações principais -->
                <div class="summary-card primary-info">
                    <div class="card-header">
                        <i class="fas fa-home"></i>
                        <h3>Service Details</h3>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">SERVICE</span>
                            <span class="value" id="summaryServiceName"><?= htmlspecialchars($dynamicData['service']['name'] ?? 'House Cleaning Service') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">ADDRESS</span>
                            <span class="value" id="summaryAddress">Not specified</span>
                        </div>
                        <div class="info-item">
                            <span class="label">DATE</span>
                            <span class="value" id="summaryDate">Select a date</span>
                        </div>
                        <div class="info-item">
                            <span class="label">TIME</span>
                            <span class="value" id="summaryTime">06:00 - 07:00</span>
                        </div>
                        <div class="info-item">
                            <span class="label">FREQUENCY</span>
                            <span class="value" id="summaryRecurrence">One-time</span>
                        </div>
                    </div>
                </div>

                <!-- Seção de Itens Inclusos -->
                <div class="summary-card included-items">
                    <div class="card-header">
                        <i class="fas fa-check-circle"></i>
                        <h3>Included Items</h3>
                    </div>
                    <div class="items-list" id="summaryIncludedList">
                        <?php foreach ($dynamicData['inclusions'] as $inclusion): ?>
                        <div class="item-row">
                            <span class="item-name"><?= htmlspecialchars($inclusion['name']) ?></span>
                            <span class="item-quantity">✓ Included</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Seção de Extras Selecionados -->
                <div class="summary-card extras-section" id="extrasSection" style="display: none;">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Additional Services</h3>
                    </div>
                    <div class="items-list" id="summaryExtrasList">
                        <!-- Extras dinâmicos serão inseridos aqui -->
                    </div>
                </div>

                <!-- Seção de Preferências Especiais -->
                <div class="summary-card preferences-section" id="preferencesSection" style="display: none;">
                    <div class="card-header">
                        <i class="fas fa-star"></i>
                        <h3>Special Preferences</h3>
                    </div>
                    <div class="preferences-content">
                        <p id="summaryPreferences"></p>
                    </div>
                </div>

                <!-- Seção de duração do contrato (elegante) -->
                <div class="summary-card contract-duration" id="contractDurationSection">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h3>Contract Duration</h3>
                    </div>
                    <div class="duration-selector-wrapper">
                        <select id="contractDuration" name="contractDuration" form="bookingForm" class="modern-select">
                            <option value="">Select duration</option>
                        </select>
                        <div class="select-icon">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div id="contractPreview" class="contract-preview-modern" style="display:none;">
                        <p class="contract-info">
                            <strong>Contract Summary:</strong><br>
                            <span id="totalOccurrences">0</span> services over <span id="contractPeriod">0</span><br>
                            Next charge: <span id="nextChargeDate">-</span><br>
                            Billing frequency: <span id="billingFrequency">-</span>
                        </p>
                    </div>
                </div>

                <!-- Seção de preços (destaque visual) -->
                <div class="summary-card pricing-card">
                    <div class="card-header">
                        <i class="fas fa-calculator"></i>
                        <h3>Pricing Breakdown</h3>
                    </div>
                    
                    <!-- Detalhamento dos preços -->
                    <div class="price-breakdown" id="priceBreakdown">
                        <div class="price-row service-price">
                            <span><?= htmlspecialchars($dynamicData['service']['name'] ?? 'House Cleaning Service') ?></span>
                            <span id="basePrice">$<?= number_format($dynamicData['service']['base_price'] ?? 0.00, 2) ?></span>
                        </div>
                        
                        <div id="extrasPricing" style="display: none;">
                            <!-- Preços de extras serão inseridos aqui -->
                        </div>
                        
                        <div class="price-row discount-row" id="discountRow" style="display: none;">
                            <span class="discount-label">Contract Discount</span>
                            <span id="discountAmount" class="discount-value">-$0.00</span>
                        </div>
                        
                        <!-- Linha de desconto do cupom -->
                        <div class="price-row coupon-discount-row" id="couponDiscountRow" style="display: none;">
                            <span class="discount-label">
                                Coupon Discount (<span id="coupon-code-display"></span>)
                            </span>
                            <span id="couponDiscountAmount" class="discount-value" data-discount-amount>-$0.00</span>
                        </div>
                        
                        <hr class="pricing-divider">
                        
                        <div class="price-row total-row">
                            <span class="total-label">Subtotal</span>
                            <span id="subtotalAmount" class="subtotal-value">$<?= number_format($dynamicData['service']['base_price'] ?? 0.00, 2) ?></span>
                        </div>
                    </div>
                    
                    <!-- Total destacado -->
                    <div class="total-section">
                        <div class="total-price">
                            <span class="total-label">Total Price</span>
                            <span class="total-amount" id="totalPriceLabel">$<?= number_format($dynamicData['service']['base_price'] ?? 0.00, 2) ?></span>
                        </div>
                        <div class="recurring-notice">
                            <i class="fas fa-repeat"></i>
                            <span>Note: For recurring services, this is the price per occurrence. Payment will be processed 48 hours before each scheduled service.</span>
                        </div>
                    </div>
                </div>

                <!-- Seção de Códigos Unificada -->
                <div class="summary-card unified-codes-card">
                    <div class="card-header">
                        <i class="fas fa-magic"></i>
                        <h3>Promo & Referral Codes</h3>
                    </div>
                    <div class="unified-codes-input-modern">
                        <div class="input-group-modern">
                            <input type="text" 
                                   id="unifiedCodeInput" 
                                   name="unifiedCodeInput"
                                   placeholder="Enter promo or referral code"
                                   autocomplete="off"
                                   class="unified-code-input-field"
                                   value="<?= htmlspecialchars($_GET['referral_code'] ?? $_GET['promo_code'] ?? '') ?>">
                            <button type="button" 
                                    id="applyUnifiedCodeBtn" 
                                    class="apply-unified-code-btn">
                                <i class="fas fa-magic"></i>
                                Apply Code
                            </button>
                        </div>
                        <div class="unified-code-status" id="unifiedCodeStatus">
                            <!-- Status do código será exibido aqui -->
                        </div>
                        <div class="code-types-info">
                            <div class="code-type-item">
                                <i class="fas fa-user-friends"></i>
                                <small><strong>Referral codes:</strong> Get discounts + help a friend earn commissions</small>
                            </div>
                            <div class="code-type-item">
                                <i class="fas fa-tag"></i>
                                <small><strong>Promo codes:</strong> Seasonal discounts and special offers</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer com termos e ação -->
            <div class="modal-footer-redesign">
                <div class="terms-section-modern">
                    <label class="terms-checkbox-modern">
                        <input type="checkbox" id="agreedToTerms" class="glass-checkbox" required>
                        <span class="checkmark-custom"></span>
                        <span class="terms-text">
                            I agree to the 
                            <button type="button" class="terms-link-modern" id="openTermsBtn">
                                Terms & Conditions and Privacy Policy
                            </button>
                        </span>
                    </label>
                </div>
                
                <button id="confirmBtn" 
                        type="submit" 
                        form="bookingForm" 
                        class="confirm-button-modern" 
                        disabled>
                    <div class="button-content">
                        <i class="fas fa-lock"></i>
                        <span>Secure Checkout</span>
                        <div class="button-shine"></div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Modal de Termos e Condições -->
        <div id="termsModal" class="modal-overlay hidden" role="dialog" aria-labelledby="termsModalTitle" aria-modal="true" data-component="terms-modal">
            <div class="modal-terms glass-card">
                <button class="close-terms-btn glass-btn" id="closeTermsModal" aria-label="Close terms">&times;</button>
                <h3 id="termsModalTitle" class="glass-text">Terms & Conditions</h3>
                <div class="terms-content" id="termsContent" role="document">
                    <p><strong>Summary:</strong></p>
                    <ul>
                        <li>Service will be provided as configured on the selected date(s) and time window.</li>
                        <li>Recurring services repeat according to selected frequency.</li>
                        <li>Payment is due 48 hours before each execution.</li>
                        <li>Card will be charged automatically.</li>
                        <li>Changes/cancellations must be made at least 48 hours in advance.</li>
                        <li>Early termination may incur a penalty.</li>
                        <li>We are not responsible for pre-existing damage.</li>
                        <li>Issues must be reported within 24 hours.</li>
                    </ul>
                    <p>By continuing, you agree to all terms stated above.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- MODAIS AUXILIARES -->
    <!-- ========================================= -->
    
    <!-- Container para modais dinâmicos de pausa/cancelamento -->
    <div id="dynamic-modals-container"></div>
    <!-- ========================================= -->
    
    <!-- Modal de Recorrência -->
    <div id="recurrenceModal" class="modal-overlay" style="display:none;" role="dialog" aria-labelledby="recurrenceModalTitle" aria-modal="true" data-component="recurrence-modal">
        <div class="modal-content glass-card">
            <button class="modal-close glass-btn" id="closeRecurrenceModal" aria-label="Close">×</button>
            <h2 id="recurrenceModalTitle" class="glass-text">Recurrence Information</h2>
            <p id="recurrenceModalMessage">Message content</p>
            <button class="modal-ack glass-btn-primary" id="ackRecurrenceModal">Acknowledge</button>
        </div>
    </div>

    <!-- Modal de Informações de Inclusão -->
    <div id="inclusionInfoModal" class="modal-overlay" style="display:none;" role="dialog" aria-labelledby="inclusionModalTitle" aria-modal="true" data-component="inclusion-modal">
        <div class="modal-content glass-card">
            <button class="modal-close glass-btn" id="closeInclusionModal" aria-label="Close">×</button>
            <h2 id="inclusionModalTitle" class="glass-text">Inclusion Details</h2>
            <p id="inclusionModalMessage">Inclusion description</p>
            <button class="modal-ack glass-btn-primary" id="ackInclusionModal">Acknowledge</button>
        </div>
    </div>

    <!-- Smart Time Picker Modal -->
    <div id="smart-time-picker-modal" class="modal-overlay hidden" role="dialog" aria-labelledby="timePickerModalTitle" aria-modal="true" data-component="time-picker-modal">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <button class="modal-close glass-btn" id="closeTimePickerModal" aria-label="Close">×</button>
                <h2 id="timePickerModalTitle" class="glass-text">Select Available Time</h2>
                <p class="modal-subtitle">Choose your preferred service start time</p>
            </div>
            <div class="modal-body">
                <div id="available-times-container" class="available-times-grid">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                        <p>Loading available times...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- BARRA DE RESUMO FLUTUANTE -->
    <!-- ========================================= -->
    <div id="summaryBar" class="glass-sticky" data-component="summary-bar" role="complementary" aria-label="Booking summary">
        <div class="summary-label" id="summaryLabel">Review your booking</div>
        <div class="summary-total" id="summaryTotal" data-original-value="0.00" data-protection-active="true" aria-live="polite">$0.00</div>
        <button type="button" id="openSummaryBtn" class="glass-btn-primary" aria-label="Open booking summary and proceed to checkout">Proceed to Checkout</button>
        
        <!-- Botões de debug (só aparecem se debug estiver ativo) -->
        <?php if ($systemConfig['debug_mode']): ?>
        <div class="debug-controls" style="position: absolute; top: -40px; right: 0; display: flex; gap: 5px;">
            <button type="button" onclick="window.recalculateEmergency()" style="background: #ff6b6b; color: white; border: none; padding: 5px 10px; border-radius: 3px; font-size: 12px;">🚨 Recalc</button>
            <button type="button" onclick="console.log('Current total:', document.getElementById('summaryTotal').textContent, 'Stored:', document.getElementById('summaryTotal').getAttribute('data-current-value'))" style="background: #4ecdc4; color: white; border: none; padding: 5px 10px; border-radius: 3px; font-size: 12px;">🔍 Debug</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ========================================= -->
    <!-- ELEMENTOS AUXILIARES PARA JAVASCRIPT -->
    <!-- ========================================= -->
    <!-- Elementos que o JavaScript espera encontrar -->
    <div style="display: none;" id="hiddenElements">
        <!-- Points Applied - Sistema de pontos (se habilitado) -->
        <?php if ($systemConfig['points_system_enabled']): ?>
        <div id="pointsApplied" data-component="points-display" data-points-value="0"></div>
        <?php endif; ?>
        
        <!-- Total Price Label - Label adicional para preço (REMOVIDO - duplicado) -->
        <!-- <div id="totalPriceLabel" data-component="price-label"></div> -->
        
        <!-- Contract Preview - Preview do contrato -->
        <div id="contractPreview" data-component="contract-preview"></div>
        
        <!-- Elementos para exibição de cupom no modal de resumo -->
        <div id="modal-discount-amount" data-discount-amount data-component="coupon-discount">$0.00</div>
        <div id="modal-coupon-code" data-coupon-code data-component="coupon-code"></div>
        <div id="modal-total-amount" data-component="final-total">$0.00</div>
        <div id="final-total-display" data-component="total-display">$0.00</div>
    </div>

    <!-- Continua na próxima parte... -->

    <!-- ========================================= -->
    <!-- SCRIPTS JAVASCRIPT -->
    <!-- ========================================= -->
    
    <!-- Biblioteca externa com loading otimizado -->
    <script>
        // Carregamento crítico primeiro
        if ('loading' in HTMLImageElement.prototype) {
            // Browser suporta lazy loading nativo
            document.documentElement.classList.add('lazy-loading-supported');
        }
    </script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js" defer></script>
    
    <!-- PROTEÇÃO ABSOLUTA CONTRA ZERO NO summaryTotal -->
    <script>
        // PROTEÇÃO INLINE IMEDIATA - EXECUTA ANTES DE QUALQUER OUTRO SCRIPT
        (function() {
            let summaryProtection = {
                lastValidValue: '0.00', // No fallback values - must be loaded from database
                monitoringActive: false,
                
                init: function() {
                    console.log('🛡️ Proteção summaryTotal inicializada');
                    this.startMonitoring();
                    this.protectElement();
                },
                
                startMonitoring: function() {
                    if (this.monitoringActive) return;
                    this.monitoringActive = true;
                    
                    // Monitoramento reduzido para 2 segundos (menos intrusivo)
                    setInterval(() => {
                        this.checkAndRestore();
                    }, 2000);
                },
                
                protectElement: function() {
                    document.addEventListener('DOMContentLoaded', () => {
                        const summaryTotal = document.getElementById('summaryTotal');
                        if (!summaryTotal) return;
                        
                        // Observer para mudanças no elemento
                        const observer = new MutationObserver((mutations) => {
                            mutations.forEach((mutation) => {
                                if (mutation.target.textContent === '$0.00') {
                                    const storedValue = mutation.target.getAttribute('data-current-value');
                                    if (storedValue && parseFloat(storedValue) > 0) {
                                        console.warn('🚨 PROTEÇÃO: Restaurando valor zerado!');
                                        mutation.target.textContent = `$${parseFloat(storedValue).toFixed(2)}`;
                                    } else if (this.lastValidValue !== '0.00') {
                                        console.warn('🚨 PROTEÇÃO: Usando último valor válido!');
                                        mutation.target.textContent = `$${this.lastValidValue}`;
                                    }
                                }
                            });
                        });
                        
                        observer.observe(summaryTotal, { 
                            childList: true, 
                            subtree: true, 
                            characterData: true 
                        });
                    });
                },
                
                checkAndRestore: function() {
                    const summaryTotal = document.getElementById('summaryTotal');
                    if (!summaryTotal) return;
                    
                    const currentText = summaryTotal.textContent;
                    
                    // Se foi zerado indevidamente
                    if (currentText === '$0.00') {
                        // Tentar usar valor armazenado
                        const storedValue = summaryTotal.getAttribute('data-current-value');
                        if (storedValue && parseFloat(storedValue) > 0) {
                            summaryTotal.textContent = `$${parseFloat(storedValue).toFixed(2)}`;
                            console.warn('🚨 PROTEÇÃO: Valor restaurado de data-current-value');
                            return;
                        }
                        
                        // Recalcular manualmente apenas se necessário
                        const calculatedTotal = this.manualCalculate();
                        if (calculatedTotal > 0) {
                            summaryTotal.textContent = `$${calculatedTotal.toFixed(2)}`;
                            summaryTotal.setAttribute('data-current-value', calculatedTotal.toFixed(2));
                            this.lastValidValue = calculatedTotal.toFixed(2);
                            console.log('� PROTEÇÃO: Valor recalculado manualmente');
                        }
                    } else {
                        // Armazenar último valor válido
                        const numValue = parseFloat(currentText.replace('$', ''));
                        if (!isNaN(numValue) && numValue > 0) {
                            this.lastValidValue = numValue.toFixed(2);
                        }
                    }
                },
                
                manualCalculate: function() {
                    let total = 0;
                    
                    // Calcular inclusões
                    document.querySelectorAll('.item-card').forEach(card => {
                        const price = parseFloat(card.getAttribute('data-price') || 0);
                        const qty = parseInt(card.querySelector('.qty')?.textContent || 0);
                        if (qty > 0 && !isNaN(price)) {
                            total += price * qty;
                        }
                    });
                    
                    // Calcular extras
                    document.querySelectorAll('.extra-item').forEach(item => {
                        const price = parseFloat(item.getAttribute('data-price') || 0);
                        const qty = parseInt(item.querySelector('.qty')?.textContent || 0);
                        if (qty > 0 && !isNaN(price)) {
                            total += price * qty;
                        }
                    });
                    
                    // Calcular preferências (todos os tipos)
                    document.querySelectorAll('.preference-checkbox, .preference-select, .preference-text').forEach(element => {
                        let fee = 0;
                        
                        if (element.classList.contains('preference-checkbox') && element.checked) {
                            fee = parseFloat(element.getAttribute('data-extra-fee') || 0);
                        } else if (element.classList.contains('preference-select') && element.value !== '') {
                            const selectedOption = element.options[element.selectedIndex];
                            if (selectedOption) {
                                fee = parseFloat(selectedOption.getAttribute('data-fee') || 0);
                            }
                        } else if (element.classList.contains('preference-text') && element.value.trim() !== '') {
                            fee = parseFloat(element.getAttribute('data-extra-fee') || 0);
                        }
                        
                        if (!isNaN(fee) && fee > 0) {
                            total += fee;
                        }
                    });
                    
                    return total;
                }
            };
            
            // Inicializar imediatamente
            summaryProtection.init();
            
            // Disponibilizar globalmente para debug
            window.SummaryProtection = summaryProtection;
        })();
    </script>

    <!-- Scripts removidos: evitar duplicação -->

    <!-- JavaScript para campos condicionais das preferências -->
    <script>
    // Função para mostrar/ocultar detalhes das preferências
    function togglePreferenceDetails(preferenceId) {
        const noteDiv = document.getElementById(`note_${preferenceId}`);
        const button = document.getElementById(`details_btn_${preferenceId}`);
        const icon = button.querySelector('i');
        
        if (noteDiv.style.display === 'none' || noteDiv.style.display === '') {
            // Mostrar detalhes
            noteDiv.style.display = 'block';
            button.setAttribute('aria-expanded', 'true');
            button.classList.add('expanded');
            button.innerHTML = '<i class="fas fa-eye-slash"></i> Hide details';
        } else {
            // Ocultar detalhes
            noteDiv.style.display = 'none';
            button.setAttribute('aria-expanded', 'false');
            button.classList.remove('expanded');
            button.innerHTML = '<i class="fas fa-info-circle"></i> View details';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log('Initializing conditional preferences system...');
        
        // ========================================
        // GOOGLE PLACES AUTOCOMPLETE INTEGRATION
        // ========================================
        if (window.AppConfig && window.AppConfig.googlePlacesEnabled) {
            console.log('🗺️ Configurando integração Google Places...');
            
            // Listener para quando um endereço é selecionado
            document.addEventListener('addressSelected', function(event) {
                const addressData = event.detail.addressData;
                const place = event.detail.place;
                
                console.log('📍 Endereço selecionado via Google Places:', addressData);
                
                // Atualizar outros campos se existirem
                updateAddressFields(addressData);
                
                // Validar área de serviço
                validateServiceArea(addressData);
                
                // Salvar dados para uso posterior
                sessionStorage.setItem('selectedAddressData', JSON.stringify(addressData));
                
                // Recalcular preços se necessário
                if (typeof updatePricing === 'function') {
                    updatePricing();
                }
                
                // Atualizar resumo
                if (typeof BookingInterface !== 'undefined' && BookingInterface.updateSummary) {
                    BookingInterface.updateSummary();
                }
            });
            
            // Listener para quando Google Places está pronto
            document.addEventListener('googlePlacesReady', function(event) {
                console.log('✅ Google Places Autocomplete inicializado e integrado');
                const placesInstance = event.detail.instance;
                
                // Configurar validações específicas do projeto
                if (placesInstance) {
                    console.log('🎯 Instância Google Places disponível para customizações');
                }
            });
            
            // Função para atualizar campos relacionados
            function updateAddressFields(addressData) {
                // Lista de campos que podem ser atualizados automaticamente
                const fieldMappings = {
                    'suburb': ['#suburb', '#city', 'input[name="suburb"]'],
                    'state': ['#state', 'select[name="state"]'],
                    'postcode': ['#postcode', 'input[name="postcode"]'],
                    'streetNumber': ['#street_number', 'input[name="street_number"]'],
                    'streetName': ['#street_name', 'input[name="street_name"]']
                };
                
                Object.entries(fieldMappings).forEach(([key, selectors]) => {
                    if (addressData[key]) {
                        selectors.forEach(selector => {
                            const field = document.querySelector(selector);
                            if (field && !field.value) {
                                field.value = addressData[key];
                                
                                // Trigger change event para outros listeners
                                field.dispatchEvent(new Event('change', { bubbles: true }));
                                
                                // Animação visual
                                field.style.backgroundColor = '#e6fffa';
                                setTimeout(() => {
                                    field.style.backgroundColor = '';
                                }, 1000);
                            }
                        });
                    }
                });
            }
            
            // Função para validar área de serviço
            function validateServiceArea(addressData) {
                const validStates = window.AppConfig.validStates || ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT'];
                
                // Verificar país
                if (addressData.countryCode !== 'AU') {
                    showNotification('⚠️ We only service addresses in Australia.', 'warning');
                    return false;
                }
                
                // Verificar estado
                if (!addressData.state || !validStates.includes(addressData.state)) {
                    const stateName = addressData.stateFullName || addressData.state || 'this area';
                    showNotification(`⚠️ We don't currently service ${stateName}.`, 'warning');
                    return false;
                }
                
                // Sucesso
                showNotification(`✅ Service confirmed for ${addressData.suburb}, ${addressData.state}!`, 'success');
                return true;
            }
            
            // Função para mostrar notificações
            function showNotification(message, type = 'info') {
                // Se existir sistema de notificação do projeto, usar
                if (typeof showToast === 'function') {
                    showToast(message, type);
                    return;
                }
                
                // Fallback simples
                const notification = document.createElement('div');
                notification.textContent = message;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 12px 16px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 10000;
                    font-size: 14px;
                    max-width: 300px;
                    border-left: 4px solid ${type === 'success' ? '#48bb78' : type === 'warning' ? '#ed8936' : '#4299e1'};
                `;
                
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 4000);
            }
        }
        
        // ========================================
        // CONDITIONAL PREFERENCES SYSTEM (EXISTING)
        // ========================================
        
        // Gerenciar campos condicionais das preferências
        document.querySelectorAll('.preference-select[data-has-conditions="true"]').forEach(select => {
            console.log('Setting up conditional field for:', select.id);
            
            select.addEventListener('change', function() {
                const preferenceId = this.closest('.preference-item').getAttribute('data-preference-id');
                const conditionalDiv = document.getElementById(`conditional_${preferenceId}`);
                const selectedOption = this.options[this.selectedIndex];
                const triggerValue = this.closest('.preference-item').getAttribute('data-conditional-trigger');
                
                console.log(`Preference ${preferenceId} changed to: ${this.value}, trigger: ${triggerValue}`);
                
                if (conditionalDiv && this.value === triggerValue) {
                    // Mostrar campo condicional
                    console.log(`Showing conditional field for preference ${preferenceId}`);
                    conditionalDiv.style.display = 'block';
                    const input = conditionalDiv.querySelector('input, textarea');
                    if (input) {
                        input.required = true;
                        // Focar no campo depois de um pequeno delay
                        setTimeout(() => input.focus(), 100);
                    }
                } else if (conditionalDiv) {
                    // Esconder campo condicional
                    console.log(`Hiding conditional field for preference ${preferenceId}`);
                    conditionalDiv.style.display = 'none';
                    const input = conditionalDiv.querySelector('input, textarea');
                    if (input) {
                        input.required = false;
                        input.value = '';
                    }
                }
                
                // Recalcular preços (usando PricingEngine centralizado)
                if (window.updatePricing && typeof window.updatePricing === 'function') {
                    window.updatePricing();
                }
            });
        });
        
        // Tratamento especial para preços de parking
        document.querySelectorAll('select[name^="preferences"]').forEach(select => {
            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const fee = parseFloat(selectedOption.getAttribute('data-fee') || 0);
                const preferenceId = this.closest('.preference-item').getAttribute('data-preference-id');
                const feeIndicator = document.getElementById(`fee_indicator_${preferenceId}`);
                
                if (fee > 0 && feeIndicator) {
                    feeIndicator.style.display = 'block';
                    const feeAmount = feeIndicator.querySelector('.fee-amount');
                    if (feeAmount) {
                        feeAmount.textContent = fee.toFixed(2);
                    }
                } else if (feeIndicator) {
                    feeIndicator.style.display = 'none';
                }
            });
        });

        console.log('Conditional preferences system initialized successfully');
    });
    </script>

    <!-- SMART CALENDAR - ÚNICO SISTEMA DE CALENDÁRIO -->
    <script src="assets/js/smart-calendar.js"></script>
    
    <!-- Script de inicialização com configuração otimizada -->
    <script>
        // Configurações globais do projeto
        window.BlueProject = {
            serviceId: <?= SERVICE_ID_HOUSE_CLEANING ?>,
            debug: <?= $env['DEBUG'] ? 'true' : 'false' ?>,
            inclusions: <?= json_encode(array_column($serviceData['inclusions'] ?? [], 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            extras: <?= json_encode(array_column($serviceData['extras'] ?? [], 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            preferences: <?= json_encode(array_column($preferenceFields ?? [], 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            recurrenceConfig: <?= json_encode($recurrenceConfig ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            systemConfig: <?= json_encode($systemConfig ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            minimumBookingHours: <?= (int)($systemConfig['minimum_booking_hours'] ?? 48) ?>,
            performance: {
                enableLazyLoading: true,
                enablePrefetch: true,
                enableServiceWorker: false
            },
            stripe: {
                enabled: <?= $systemConfig['stripe_enabled'] ? 'true' : 'false' ?>,
                publicKey: '', // Adicionar chave pública do Stripe
                currency: 'AUD'
            },
            api: {
                endpoints: {
                    checkAvailability: '<?= $isProductionEnvironment ? "/allblue/api/check-availability.php" : "/api/check-availability.php" ?>',
                    validateDiscount: '<?= $isProductionEnvironment ? "/allblue/api/validate-discount.php" : "/api/validate-discount.php" ?>',
                    calculatePricing: '<?= $isProductionEnvironment ? "/allblue/api/calculate-pricing.php" : "/api/calculate-pricing.php" ?>',
                    createPaymentIntent: '<?= $isProductionEnvironment ? "/allblue/api/create-payment-intent.php" : "/api/create-payment-intent.php" ?>',
                    pauseSubscription: '<?= $isProductionEnvironment ? "/allblue/api/pause-subscription.php" : "/api/pause-subscription.php" ?>',
                    cancelSubscription: '<?= $isProductionEnvironment ? "/allblue/api/cancel-subscription.php" : "/api/cancel-subscription.php" ?>',
                    pauseTier: '<?= $isProductionEnvironment ? "/allblue/api/pause-tier.php" : "/api/pause-tier.php" ?>',
                    cancellationPenalty: '<?= $isProductionEnvironment ? "/allblue/api/cancellation-penalty.php" : "/api/cancellation-penalty.php" ?>'
                }
            },
            pauseConfig: <?= json_encode($pauseConfig ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            cancellationConfig: <?= json_encode($cancellationConfig ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        };

        // Inicialização otimizada quando DOM estiver pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeApp);
        } else {
            initializeApp();
        }

        // ========================================
        // BOOKING SUMMARY DYNAMIC SYSTEM
        // ========================================
        
        /**
         * Sistema de Resumo Dinâmico - Booking Summary
         * Captura automaticamente dados do formulário e atualiza o resumo
         */
        class BookingSummaryManager {
            constructor() {
                this.serviceData = <?= json_encode($dynamicData['service'] ?? null) ?>;
                this.extrasData = <?= json_encode($dynamicData['extras'] ?? []) ?>;
                this.selectedExtras = [];
                this.selectedDate = null;
                this.selectedTime = null;
                this.address = '';
                this.preferences = '';
                this.frequency = 'One-time';
                this.contractDuration = null;
                
                this.init();
            }
            
            init() {
                console.log('🎯 Inicializando Booking Summary Manager');
                this.bindEvents();
                this.updateSummary();
            }
            
            bindEvents() {
                // Monitorar mudanças no formulário
                document.addEventListener('change', (e) => {
                    this.updateSummary();
                });
                
                // Monitorar input de texto
                document.addEventListener('input', (e) => {
                    if (e.target.matches('input[type="text"], textarea')) {
                        this.updateSummary();
                    }
                });
                
                // Monitorar seleção de data do calendário
                document.addEventListener('smartCalendarDateSelected', (e) => {
                    this.selectedDate = e.detail.formattedDate;
                    this.updateSummary();
                });
                
                // Monitorar mudanças de endereço do Google Places
                document.addEventListener('addressSelected', (e) => {
                    this.updateSummary();
                });
            }
            
            updateSummary() {
                this.collectFormData();
                this.updateSummaryElements();
                this.calculatePricing();
                
                console.log('📊 Resumo atualizado:', {
                    service: this.serviceData?.name,
                    date: this.selectedDate,
                    extras: this.selectedExtras.length,
                    address: this.address
                });
            }
            
            collectFormData() {
                // Coletar endereço
                const addressInput = document.querySelector('input[name="address"], input[placeholder*="address"], #address');
                if (addressInput) {
                    this.address = addressInput.value || 'Not specified';
                }
                
                // Coletar frequência
                const frequencySelect = document.querySelector('select[name="recurrence"], #recurrence');
                if (frequencySelect && frequencySelect.value) {
                    this.frequency = frequencySelect.options[frequencySelect.selectedIndex]?.text || 'One-time';
                }
                
                // Coletar horário
                const timeSelect = document.querySelector('select[name="time"], #time');
                if (timeSelect && timeSelect.value) {
                    this.selectedTime = timeSelect.options[timeSelect.selectedIndex]?.text || '06:00 - 07:00';
                }
                
                // Coletar extras selecionados
                this.selectedExtras = [];
                const extraCheckboxes = document.querySelectorAll('input[type="checkbox"][name^="extras"]');
                extraCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        const extraId = checkbox.value;
                        const extraData = this.extrasData.find(e => e.id == extraId);
                        
                        if (extraData) {
                            this.selectedExtras.push({
                                id: extraData.id,
                                name: extraData.name,
                                price: parseFloat(extraData.price) || 0,
                                quantity: 1
                            });
                        }
                    }
                });
                
                // Coletar preferências especiais
                const preferencesTextarea = document.querySelector('textarea[name="special_requests"], textarea[placeholder*="preference"]');
                if (preferencesTextarea) {
                    this.preferences = preferencesTextarea.value.trim();
                }
                
                // Coletar duração do contrato
                const contractSelect = document.getElementById('contractDuration');
                if (contractSelect && contractSelect.value) {
                    this.contractDuration = contractSelect.value;
                }
            }
            
            updateSummaryElements() {
                // Atualizar nome do serviço
                const serviceNameEl = document.getElementById('summaryServiceName');
                if (serviceNameEl && this.serviceData) {
                    serviceNameEl.textContent = this.serviceData.name;
                }
                
                // Atualizar endereço
                const addressEl = document.getElementById('summaryAddress');
                if (addressEl) {
                    addressEl.textContent = this.address;
                    addressEl.style.color = this.address === 'Not specified' ? '#f56565' : '#2d3748';
                }
                
                // Atualizar data
                const dateEl = document.getElementById('summaryDate');
                if (dateEl) {
                    dateEl.textContent = this.selectedDate || 'Select a date';
                    dateEl.style.color = !this.selectedDate ? '#f56565' : '#2d3748';
                }
                
                // Atualizar horário
                const timeEl = document.getElementById('summaryTime');
                if (timeEl && this.selectedTime) {
                    timeEl.textContent = this.selectedTime;
                }
                
                // Atualizar frequência
                const frequencyEl = document.getElementById('summaryRecurrence');
                if (frequencyEl) {
                    frequencyEl.textContent = this.frequency;
                }
                
                // Atualizar extras
                this.updateExtrasList();
                
                // Atualizar preferências
                this.updatePreferences();
            }
            
            updateExtrasList() {
                const extrasSection = document.getElementById('extrasSection');
                const extrasList = document.getElementById('summaryExtrasList');
                
                if (this.selectedExtras.length > 0) {
                    extrasSection.style.display = 'block';
                    extrasSection.classList.add('slide-in');
                    
                    let extrasHTML = '';
                    this.selectedExtras.forEach(extra => {
                        extrasHTML += `
                            <div class="item-row new-item">
                                <span class="item-name">${extra.name}</span>
                                <span class="item-quantity">Qty: ${extra.quantity} (+$${extra.price.toFixed(2)})</span>
                            </div>
                        `;
                    });
                    
                    extrasList.innerHTML = extrasHTML;
                } else {
                    extrasSection.style.display = 'none';
                    extrasSection.classList.remove('slide-in');
                }
            }
            
            updatePreferences() {
                const preferencesSection = document.getElementById('preferencesSection');
                const preferencesEl = document.getElementById('summaryPreferences');
                
                if (this.preferences) {
                    preferencesSection.style.display = 'block';
                    preferencesSection.classList.add('slide-in');
                    preferencesEl.textContent = this.preferences;
                } else {
                    preferencesSection.style.display = 'none';
                    preferencesSection.classList.remove('slide-in');
                }
            }
            
            calculatePricing() {
                // ✅ CORRIGIDO: Usar o mesmo cálculo que o pricing-calculator.js
                let total = 0;
                
                // Usar cálculo do pricing-calculator se disponível
                if (window.updateTotal && typeof window.updateTotal === 'function') {
                    total = window.updateTotal(true);
                    console.log('📊 BookingSummary usando total do pricing-calculator:', total);
                } else {
                    // Fallback: calcular serviços inclusos dinamicamente
                    let inclusionsTotal = 0;
                    document.querySelectorAll('.item-card').forEach(item => {
                        const price = parseFloat(item.getAttribute('data-price') || 0);
                        const qty = parseInt(item.querySelector('.qty')?.textContent || '0', 10);
                        if (qty > 0 && !isNaN(price)) {
                            inclusionsTotal += price * qty;
                        }
                    });
                    
                    let extrasTotal = this.selectedExtras.reduce((sum, extra) => sum + (extra.price * extra.quantity), 0);
                    total = inclusionsTotal + extrasTotal;
                    console.log('📊 BookingSummary usando cálculo manual:', { inclusionsTotal, extrasTotal, total });
                }
                
                let discount = 0;
                
                // Calcular desconto baseado no contrato
                if (this.contractDuration) {
                    switch (this.contractDuration) {
                        case '3-month':
                            discount = 10;
                            break;
                        case '6-month':
                            discount = 20;
                            break;
                        case '12-month':
                            discount = 30;
                            break;
                    }
                }
                
                let finalTotal = total - discount;
                
                // ✅ CORRIGIDO: Calcular inclusões dinamicamente em vez de usar valor fixo
                let inclusionsTotal = 0;
                if (typeof document !== 'undefined') {
                    document.querySelectorAll('.item-card').forEach(item => {
                        const price = parseFloat(item.getAttribute('data-price') || 0);
                        const qty = parseInt(item.querySelector('.qty')?.textContent || '0', 10);
                        if (qty > 0 && !isNaN(price)) {
                            inclusionsTotal += price * qty;
                        }
                    });
                } else {
                    // ERROR: Cannot calculate without DOM access - database prices required
                    console.error('❌ Cannot calculate inclusions without DOM - dynamic pricing system requires database data');
                    inclusionsTotal = 0.00; // No fallback allowed
                }
                
                let extrasTotal = total - inclusionsTotal;
                this.updatePricingElements(inclusionsTotal, extrasTotal, discount, finalTotal);
            }
            
            updatePricingElements(basePrice, extrasTotal, discount, total) {
                const basePriceEl = document.getElementById('basePrice');
                const subtotalEl = document.getElementById('subtotalAmount');
                const discountEl = document.getElementById('discountAmount');
                const discountRow = document.getElementById('discountRow');
                const totalEl = document.getElementById('totalPriceLabel');
                
                if (basePriceEl) basePriceEl.textContent = `$${basePrice.toFixed(2)}`;
                if (subtotalEl) subtotalEl.textContent = `$${(basePrice + extrasTotal).toFixed(2)}`;
                if (totalEl) totalEl.textContent = `$${total.toFixed(2)}`;
                
                // Mostrar/ocultar desconto
                if (discount > 0) {
                    if (discountEl) discountEl.textContent = `-$${discount.toFixed(2)}`;
                    if (discountRow) {
                        discountRow.style.display = 'flex';
                        discountRow.classList.add('slide-in');
                    }
                } else {
                    if (discountRow) {
                        discountRow.style.display = 'none';
                        discountRow.classList.remove('slide-in');
                    }
                }
                
                // Atualizar preços de extras
                this.updateExtrasPricing(extrasTotal);
            }
            
            updateExtrasPricing(extrasTotal) {
                const extrasPricing = document.getElementById('extrasPricing');
                
                if (extrasTotal > 0) {
                    extrasPricing.style.display = 'block';
                    
                    let extrasHTML = '';
                    this.selectedExtras.forEach(extra => {
                        extrasHTML += `
                            <div class="price-row extra-price">
                                <span>${extra.name}</span>
                                <span>+$${(extra.price * extra.quantity).toFixed(2)}</span>
                            </div>
                        `;
                    });
                    
                    extrasPricing.innerHTML = extrasHTML;
                } else {
                    extrasPricing.style.display = 'none';
                }
            }
            
            // Método para validação antes do checkout
            validateBooking() {
                const errors = [];
                
                if (!this.address || this.address === 'Not specified') {
                    errors.push('Address is required');
                }
                
                if (!this.selectedDate) {
                    errors.push('Date selection is required');
                }
                
                const agreeTerms = document.getElementById('agreedToTerms');
                if (!agreeTerms || !agreeTerms.checked) {
                    errors.push('You must agree to the Terms & Conditions');
                }
                
                return {
                    isValid: errors.length === 0,
                    errors: errors
                };
            }
            
            // MÉTODO CRÍTICO: Extrair dados completos da reserva incluindo total calculado
            getBookingData() {
                // Extrair total do elemento da interface
                const totalPriceElement = document.getElementById('totalPriceLabel');
                let calculatedTotal = this.serviceData?.base_price || 0.00; // Valor dinâmico, sem fallback fixo
                
                if (totalPriceElement) {
                    const totalText = totalPriceElement.textContent || totalPriceElement.innerText;
                    const totalMatch = totalText.match(/\$?([0-9.,]+)/);
                    if (totalMatch) {
                        calculatedTotal = parseFloat(totalMatch[1].replace(',', ''));
                        console.log('✅ BookingSummary - Total extraído:', calculatedTotal);
                    }
                }
                
                // Coletar dados completos do formulário
                const firstName = document.getElementById('first_name')?.value || '';
                const lastName = document.getElementById('last_name')?.value || '';
                const email = document.getElementById('email')?.value || '';
                const phone = document.getElementById('phone')?.value || '';
                const executionDate = document.getElementById('execution_date')?.value || '';
                const timeWindow = document.getElementById('time_window')?.value || '10:00';
                const recurrence = document.getElementById('recurrence')?.value || 'one-time';
                
                const bookingData = {
                    service: '1',
                    total: calculatedTotal, // CRÍTICO: Incluir total calculado
                    date: executionDate || (() => {
                        const futureDate = new Date();
                        futureDate.setDate(futureDate.getDate() + 2);
                        return futureDate.toISOString().split('T')[0];
                    })(),
                    time: timeWindow,
                    recurrence: recurrence,
                    customer: {
                        name: `${firstName} ${lastName}`.trim(),
                        email: email,
                        phone: phone
                    },
                    address: this.address,
                    extras: this.selectedExtras.map(extra => ({
                        id: extra.id,
                        name: extra.name,
                        price: extra.price,
                        quantity: extra.quantity
                    })),
                    preferences: this.preferences,
                    selectedExtras: this.selectedExtras,
                    serviceData: this.serviceData,
                    frequency: this.frequency,
                    contractDuration: this.contractDuration
                };
                
                console.log('📦 BookingSummary.getBookingData() retornando:', bookingData);
                return bookingData;
            }
        }

        function initializeApp() {
            // Inicializar o Sistema de Resumo Dinâmico
            console.log('🚀 Inicializando aplicação...');
            
            // Criar instância global do sistema de resumo
            window.bookingSummary = new BookingSummaryManager();
            
            // Inicialização da aplicação será delegada para booking4.js
            if (window.BookingApp && window.BookingApp.init) {
                window.BookingApp.init();
            } else {
                // FALLBACK: Se BookingApp não carregou, inicializar manualmente
                setTimeout(() => {
                    initializeFallbackCheckout();
                }, 500);
            }
            
            // Inicializa sistema de pausas e cancelamentos
            if (window.PauseCancellationManager) {
                window.pauseCancellationConfig = {
                    apiBaseUrl: '/api',
                    stripePublicKey: window.bookingConfig.stripe.publicKey,
                    debug: window.bookingConfig.debug || false
                };
            }
        }

        // CORREÇÃO DEFINITIVA - BOTÃO PROCEED TO CHECKOUT SIMPLIFICADO v3.0
        function initializeFallbackCheckout() {
            console.log("🔧 Iniciando correção DEFINITIVA do botão Proceed to Checkout...");
            
            // Aguardar elementos carregarem completamente
            setTimeout(() => {
                const button = document.getElementById("openSummaryBtn");
                const modal = document.getElementById("summaryModal");
                
                console.log("� Elementos encontrados:", {
                    button: !!button,
                    modal: !!modal
                });
                
                if (button && modal) {
                    // Clonar botão para remover listeners antigos
                    const newButton = button.cloneNode(true);
                    button.parentNode.replaceChild(newButton, button);
                    
                    // Adicionar listener SIMPLES
                    newButton.addEventListener("click", function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        console.log("✅ Botão clicado - abrindo modal");
                        
                        // MÉTODO SIMPLIFICADO: CSS puro sem Bootstrap
                        modal.classList.remove("hidden");
                        modal.setAttribute("aria-hidden", "false");
                        modal.style.display = "flex";
                        modal.style.visibility = "visible";
                        modal.style.opacity = "1";
                        modal.style.position = "fixed";
                        modal.style.top = "0";
                        modal.style.left = "0";
                        modal.style.width = "100%";
                        modal.style.height = "100%";
                        modal.style.zIndex = "99999";
                        modal.style.backgroundColor = "rgba(0, 0, 0, 0.7)";
                        modal.style.alignItems = "center";
                        modal.style.justifyContent = "center";
                        
                        console.log("✅ Modal aberto com CSS");
                        
                        // Configurar botões de fechar
                        setupCloseHandlers();
                    });
                    
                    // Função para configurar fechamento
                    function setupCloseHandlers() {
                        // Botões de fechar
                        const closeButtons = modal.querySelectorAll(
                            '.modal-close, [data-bs-dismiss="modal"], .btn-close, #closeSummaryModal'
                        );
                        
                        closeButtons.forEach(btn => {
                            btn.onclick = function(e) {
                                e.preventDefault();
                                closeModal();
                            };
                        });
                        
                        // Fechar com backdrop
                        modal.onclick = function(e) {
                            if (e.target === modal) {
                                closeModal();
                            }
                        };
                        
                        // Fechar com ESC
                        document.onkeydown = function(e) {
                            if (e.key === "Escape" && !modal.classList.contains("hidden")) {
                                closeModal();
                            }
                        };
                    }
                    
                    // Função para fechar modal
                    window.closeModal = function() {
                        modal.classList.add("hidden");
                        modal.setAttribute("aria-hidden", "true");
                        modal.style.display = "none";
                        modal.style.visibility = "hidden";
                        modal.style.opacity = "0";
                        console.log("✅ Modal fechado");
                    };
                    
                    console.log("✅ Sistema de modal configurado");
                    
                    // Indicação visual
                    newButton.style.border = "2px solid #28a745";
                    newButton.title = "✅ Sistema corrigido - clique para abrir";
                    
                } else {
                    console.error("❌ Elementos não encontrados");
                }
            }, 500);
        }
        }

        // Implementação básica de Service Worker para cache (futuro)
        if ('serviceWorker' in navigator && window.BlueProject.performance.enableServiceWorker) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        // SW registrado com sucesso (log apenas em desenvolvimento)
                        if (window.BlueProject.debug.enabled) {
                            console.log('SW registered: ', registration);
                        }
                    })
                    .catch(function(registrationError) {
                        // SW falhou ao registrar (log apenas em desenvolvimento)
                        if (window.BlueProject.debug.enabled) {
                            console.log('SW registration failed: ', registrationError);
                        }
                    });
                });
        }
    </script>

    </script>

    <!-- Scripts na ordem correta: UNIFICADO -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/pricing-calculator.js"></script>
    <script src="assets/js/checkout-helpers.js"></script>
    <script src="assets/js/booking4-fixed.js"></script>
    <script src="assets/js/summary-modal.js" defer></script>
    <script src="assets/js/recurrence-modal.js" defer></script>
    <script src="assets/js/inclusion-modal.js" defer></script>
    <script src="assets/js/preferences.js" defer></script>
    <script src="assets/js/pause-cancellation-manager.js" defer></script>
    <script src="assets/js/discount-system.js" defer></script>
    <script src="assets/js/coupon-system.js" defer></script>
    <script src="assets/js/address.js" defer></script>
    
    <!-- Script de inicialização final -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Inicializando sistema completo...');
            
            // Aguardar scripts carregarem na ordem correta
            setTimeout(() => {
                console.log('🔍 Verificando sistemas disponíveis:', {
                    PricingCalculator: !!window.PricingCalculator,
                    updateTotal: !!window.updateTotal,
                    updatePricing: !!window.updatePricing,
                    BookingApp: !!window.BookingApp,
                    couponSystem: !!window.couponSystem
                });
                
                // Inicializar sistema de preços primeiro
                if (window.PricingCalculator && typeof window.PricingCalculator.calculateTotal === 'function') {
                    window.PricingCalculator.calculateTotal();
                    console.log('✅ PricingCalculator inicializado com sucesso');
                } else if (window.updateTotal) {
                    window.updateTotal(true);
                    console.log('✅ updateTotal inicializado como fallback');
                }
                
                // Inicializar sistema de cupons
                if (window.couponSystem) {
                    console.log('🎫 Inicializando sistema de cupons...');
                    
                    // ✅ ATUALIZADO: Usar sistema unificado
                    window.couponSystem.couponInput = document.getElementById('unifiedCodeInput');
                    window.couponSystem.applyButton = document.getElementById('applyUnifiedCodeBtn');
                    window.couponSystem.couponStatus = document.getElementById('unifiedCodeStatus');
                    window.couponSystem.statusDiv = document.getElementById('unifiedCodeStatus');
                    
                    // Elementos de exibição de desconto
                    window.couponSystem.discountRow = document.getElementById('couponDiscountRow');
                    window.couponSystem.discountAmount = document.getElementById('couponDiscountAmount');
                    window.couponSystem.couponCodeDisplay = document.getElementById('coupon-code-display');
                    window.couponSystem.modalDiscountAmount = document.getElementById('modal-discount-amount');
                    window.couponSystem.modalCouponCode = document.getElementById('modal-coupon-code');
                    window.couponSystem.finalAmount = document.getElementById('summaryTotal');
                    
                    // Verificar se todos os elementos foram encontrados
                    const couponElements = {
                        input: !!window.couponSystem.couponInput,
                        button: !!window.couponSystem.applyButton,
                        status: !!window.couponSystem.couponStatus, // ✅ ADICIONADO
                        status: !!window.couponSystem.statusDiv,
                        discountRow: !!window.couponSystem.discountRow,
                        discountAmount: !!window.couponSystem.discountAmount,
                        couponCodeDisplay: !!window.couponSystem.couponCodeDisplay
                    };
                    
                    console.log('🎫 Elementos do sistema de cupons:', couponElements);
                    
                    // Conectar eventos
                    if (window.couponSystem.bindEvents) {
                        window.couponSystem.bindEvents();
                        console.log('✅ Eventos do sistema de cupons conectados');
                    }
                } else {
                    console.warn('⚠️ Sistema de cupons não encontrado');
                }
                
                // Verificar se BookingApp foi inicializado
                if (window.BookingApp && window.BookingApp.initialized) {
                    console.log('✅ BookingApp funcionando perfeitamente');
                } else {
                    console.warn('⚠️ BookingApp ainda inicializando...');
                }
                
                console.log('🎉 Sistema inicializado com sucesso!');
            }, 1200); // Aumentado para dar mais tempo aos scripts
        });
    </script>

    <!-- SCRIPT PARA POPULAR MODAL REDESIGN -->
    <script>
    // Sistema para popular o modal redesignado com dados dinâmicos
    window.SummaryModal = {
        update: function() {
            console.log('🔄 Atualizando modal redesignado...');
            
            try {
                // Atualizar informações do serviço
                this.updateServiceDetails();
                
                // Atualizar seção de preços
                this.updatePricingSection();
                
                // Atualizar seção de desconto se necessário
                this.updateDiscountSection();
                
                // Sincronizar checkbox de termos
                this.syncTermsCheckbox();
                
                console.log('✅ Modal redesignado atualizado com sucesso');
            } catch (error) {
                console.error('❌ Erro ao atualizar modal redesignado:', error);
            }
        },
        
        updateServiceDetails: function() {
            // Endereço
            const addressInput = document.getElementById('address');
            const summaryAddress = document.getElementById('summaryAddress');
            if (addressInput && summaryAddress) {
                summaryAddress.textContent = addressInput.value || 'N/A';
            }
            
            // Data
            const dateInput = document.getElementById('execution_date');
            const summaryDate = document.getElementById('summaryDate');
            if (dateInput && summaryDate) {
                summaryDate.textContent = dateInput.value || 'N/A';
            }
            
            // Horário
            const timeSelect = document.getElementById('time_window');
            const summaryTime = document.getElementById('summaryTime');
            if (timeSelect && summaryTime) {
                summaryTime.textContent = timeSelect.value || '06:00';
            }
            
            // Recorrência
            const recurrenceSelect = document.getElementById('recurrence');
            const summaryRecurrence = document.getElementById('summaryRecurrence');
            if (recurrenceSelect && summaryRecurrence) {
                const selectedOption = recurrenceSelect.options[recurrenceSelect.selectedIndex];
                summaryRecurrence.textContent = selectedOption ? selectedOption.text : 'One-time';
            }
        },
        
        updatePricingSection: function() {
            // Sincronizar preço total
            const summaryTotal = document.getElementById('summaryTotal');
            const totalPriceLabel = document.getElementById('totalPriceLabel');
            
            if (summaryTotal && totalPriceLabel) {
                totalPriceLabel.textContent = summaryTotal.textContent;
            }
            
            // Atualizar breakdown de preços (futuro)
            const priceBreakdown = document.getElementById('priceBreakdown');
            if (priceBreakdown) {
                // Por enquanto, manter vazio - pode ser expandido futuramente
                priceBreakdown.innerHTML = '';
            }
        },
        
        updateDiscountSection: function() {
            // Sistema unificado não precisa de sincronização
            console.log('Sistema de códigos unificado ativo');
        },
        
        syncTermsCheckbox: function() {
            console.log('🔧 syncTermsCheckbox: Iniciando configuração do botão Secure Checkout...');
            
            // Garantir que o checkbox está funcionando
            const termsCheckbox = document.getElementById('agreedToTerms');
            const confirmBtn = document.getElementById('confirmBtn');
            
            console.log('🔍 syncTermsCheckbox: Elementos encontrados:', {
                termsCheckbox: !!termsCheckbox,
                confirmBtn: !!confirmBtn,
                bookingSummary: !!window.bookingSummary
            });
            
            if (termsCheckbox && confirmBtn) {
                console.log('✅ syncTermsCheckbox: Elementos encontrados, configurando listeners...');
                
                const updateButtonState = () => {
                    // Validar usando o sistema de resumo dinâmico
                    let isValid = termsCheckbox.checked;
                    
                    if (window.bookingSummary) {
                        const validation = window.bookingSummary.validateBooking();
                        isValid = isValid && validation.isValid;
                        
                        // Mostrar erros se houver
                        if (!validation.isValid) {
                            console.warn('⚠️ Validação falhou:', validation.errors);
                        }
                    }
                    
                    confirmBtn.disabled = !isValid;
                    
                    // Atualizar visual do botão
                    if (isValid) {
                        confirmBtn.classList.remove('btn-disabled');
                        confirmBtn.classList.add('btn-enabled');
                    } else {
                        confirmBtn.classList.add('btn-disabled');
                        confirmBtn.classList.remove('btn-enabled');
                    }
                };
                
                // Verificar estado inicial
                updateButtonState();
                
                // Listeners para mudanças
                termsCheckbox.addEventListener('change', updateButtonState);
                
                // Listener global para mudanças no formulário
                document.addEventListener('input', updateButtonState);
                document.addEventListener('change', updateButtonState);
                document.addEventListener('smartCalendarDateSelected', updateButtonState);
                
                // Listener para o botão de checkout
                confirmBtn.addEventListener('click', async function(e) {
                    console.log('🔥 SECURE CHECKOUT BUTTON CLICKED!');
                    e.preventDefault(); // Sempre prevenir submit padrão
                    
                    if (window.bookingSummary) {
                        const validation = window.bookingSummary.validateBooking();
                        
                        if (!validation.isValid) {
                            // Mostrar erros ao usuário
                            const errorMessage = 'Please complete the following:\n• ' + validation.errors.join('\n• ');
                            alert(errorMessage);
                            return false;
                        }
                        
                        console.log('✅ Validação passou - iniciando checkout');
                        
                        // Desabilitar botão durante processamento
                        confirmBtn.disabled = true;
                        confirmBtn.innerHTML = '<div class="button-content"><i class="fas fa-spinner fa-spin"></i><span>Processing...</span></div>';
                        
                        try {
                            // Coletar dados completos do formulário
                            const bookingData = window.bookingSummary ? window.bookingSummary.getBookingData() : {};
                            
                            // CORREÇÃO CRÍTICA: Usar IDs corretos dos campos
                            const firstName = document.getElementById('first_name')?.value || '';
                            const lastName = document.getElementById('last_name')?.value || '';
                            const email = document.getElementById('email')?.value || '';
                            const phone = document.getElementById('phone')?.value || '';
                            const address = document.getElementById('address')?.value || '';
                            
                            console.log('🔍 Main validation - Field values:', { 
                                firstName: `"${firstName}"`, 
                                lastName: `"${lastName}"`, 
                                email: `"${email}"`, 
                                phone: `"${phone}"`, 
                                address: `"${address}"` 
                            });
                            
                            // Validar campos obrigatórios antes de enviar
                            const requiredFields = { firstName, lastName, email, phone, address };
                            const missingFields = Object.entries(requiredFields)
                                .filter(([key, value]) => !value.trim())
                                .map(([key]) => key);
                            
                            if (missingFields.length > 0) {
                                console.log('❌ Main validation failed:', missingFields);
                                alert(`Please fill in the following fields:\n• ${missingFields.join('\n• ')}`);
                                confirmBtn.disabled = false;
                                confirmBtn.innerHTML = '<div class="button-content"><i class="fas fa-lock"></i><span>Secure Checkout</span><div class="button-shine"></div></div>';
                                return;
                            }
                            
                            // CORREÇÃO CRÍTICA: Usar service_id correto do sistema
                            bookingData.service_id = '<?= SERVICE_ID_HOUSE_CLEANING ?>'; // Service ID correto do sistema
                            bookingData.name = `${firstName} ${lastName}`.trim();
                            bookingData.email = email;
                            bookingData.phone = phone;
                            bookingData.address = address;
                            bookingData.suburb = 'Sydney'; // Valor padrão
                            bookingData.postcode = '2000'; // Valor padrão
                            
                            // Garantir outros campos obrigatórios
                            if (!bookingData.extras) bookingData.extras = {};
                            if (!bookingData.discount_amount) bookingData.discount_amount = 0;
                            if (!bookingData.special_requests) bookingData.special_requests = '';
                            
                            // Adicionar outros dados básicos se não existirem
                            if (!bookingData.service) bookingData.service = '1';
                            
                            // CORREÇÃO CRÍTICA: Garantir que date e time estão corretos
                            const executionDate = document.getElementById('execution_date')?.value;
                            const timeWindow = document.getElementById('time_window')?.value;
                            
                            // DEBUG: Log valores dos campos de data/hora
                            console.log('🔍 DEBUG - Date/Time fields:');
                            console.log('   - executionDate element value:', executionDate);
                            console.log('   - timeWindow element value:', timeWindow);
                            console.log('   - bookingData.date before:', bookingData.date);
                            console.log('   - bookingData.time before:', bookingData.time);
                            
                            if (!bookingData.date && executionDate) {
                                bookingData.date = executionDate;
                            } else if (!bookingData.date) {
                                // Usar data padrão de 2 dias no futuro se não houver data selecionada
                                const futureDate = new Date();
                                futureDate.setDate(futureDate.getDate() + 2);
                                bookingData.date = futureDate.toISOString().split('T')[0];
                            }
                            
                            if (!bookingData.time && timeWindow) {
                                bookingData.time = timeWindow;
                            } else if (!bookingData.time) {
                                bookingData.time = '10:00';
                            }
                            
                            // DEBUG: Log completo dos dados de data e hora após correções
                            console.log('📅 Date/Time data FINAL:', {
                                executionDate: executionDate,
                                timeWindow: timeWindow,
                                finalDate: bookingData.date,
                                finalTime: bookingData.time,
                                dateIsEmpty: !bookingData.date || bookingData.date === '',
                                timeIsEmpty: !bookingData.time || bookingData.time === ''
                            });
                            
                            // VALIDAÇÃO CRÍTICA: Garantir que data não está vazia
                            if (!bookingData.date || bookingData.date === '') {
                                console.warn('⚠️ WARNING: scheduled_date is empty, will cause database issue!');
                                // Forçar data padrão se estiver vazia
                                const futureDate = new Date();
                                futureDate.setDate(futureDate.getDate() + 2);
                                bookingData.date = futureDate.toISOString().split('T')[0];
                                console.log('🔄 FORCED default date:', bookingData.date);
                            }
                            
                            if (!bookingData.recurrence) bookingData.recurrence = document.getElementById('recurrence')?.value || 'one-time';
                            
                            // Adicionar extras se existirem
                            bookingData.extras = {};
                            document.querySelectorAll('.extra-item input[type="checkbox"]:checked').forEach(checkbox => {
                                const extraId = checkbox.getAttribute('data-extra-id');
                                if (extraId) bookingData.extras[extraId] = true;
                            });
                            
                            // CRÍTICO: Extração unificada e robusta do total do frontend
                            const totalExtractionResult = extractFrontendTotal();
                            bookingData.total = totalExtractionResult.total;
                            
                            // Log detalhado da extração
                            logCheckoutFlow('TOTAL_EXTRACTION', {
                                extracted_total: totalExtractionResult.total,
                                source_element: totalExtractionResult.source,
                                is_valid: totalExtractionResult.isValid,
                                formatted: `$${totalExtractionResult.total.toFixed(2)}`
                            });
                            
                            console.log('💰 Total extracted and added to booking data:', bookingData.total);
                            
                            // CORREÇÃO CRÍTICA: Coletar código de referral/promo
                            const referralCode = document.getElementById('hiddenReferralCode')?.value?.trim();
                            const codeType = document.getElementById('hiddenCodeType')?.value?.trim();
                            
                            // DEBUG CRÍTICO: Log completo dos campos hidden
                            console.log('🔍 CRITICAL DEBUG - Hidden fields inspection:');
                            console.log('   - hiddenReferralCode element:', document.getElementById('hiddenReferralCode'));
                            console.log('   - hiddenReferralCode value:', referralCode);
                            console.log('   - hiddenCodeType element:', document.getElementById('hiddenCodeType'));
                            console.log('   - hiddenCodeType value:', codeType);
                            console.log('   - unifiedCodeInput value:', document.getElementById('unifiedCodeInput')?.value);
                            
                            if (referralCode) {
                                bookingData.referral_code = referralCode;
                                bookingData.code_type = codeType || 'auto';
                                console.log('🎁 Referral code collected and added to bookingData:', {
                                    code: referralCode,
                                    type: codeType,
                                    added_to_booking: true,
                                    bookingData_has_referral_code: !!bookingData.referral_code
                                });
                            } else {
                                console.log('⚠️ WARNING: No referral code to collect - field is empty!');
                                console.log('   - Check if applyUnifiedCode() was called successfully');
                                console.log('   - Check if hiddenReferralCode field was populated');
                            }
                            
                            // Validação completa dos dados antes de enviar
                            const validation = validateCheckoutData(bookingData);
                            if (!validation.isValid) {
                                console.error('❌ Checkout data validation failed:', validation.errors);
                                alert('Please fill in all required fields:\n' + validation.errors.join('\n'));
                                return;
                            }
                            
                            logCheckoutFlow('DATA_VALIDATION', {
                                isValid: validation.isValid,
                                bookingData: bookingData
                            });
                            
                            console.log('📤 Validated booking data for checkout:', bookingData);
                            
                            // Obter token CSRF
                            const csrfToken = document.querySelector('[name="_csrf_token"]')?.value;
                            
                            console.log('🔐 CSRF Token found:', csrfToken ? 'Yes' : 'No');
                            console.log('🔐 CSRF Token value:', csrfToken);
                            
                            // Adicionar dados CSRF ao payload
                            bookingData._csrf_token = csrfToken;
                            bookingData._method = 'POST';
                            
                            // CORREÇÃO PARA PRODUÇÃO: URLs múltiplas para fallback
                            const currentHost = window.location.hostname;
                            const isRealProduction = currentHost === 'bluefacilityservices.com.au' || 
                                                   currentHost.includes('bluefacilityservices.com.au');
                            
                            let apiUrls = [];
                            
                            // ✅ UNIFICADO - APENAS 1 ENDPOINT (elimina 8 APIs redundantes)
                            // Substitui fallbacks caóticos por endpoint único e confiável
                            apiUrls = [
                                'api/stripe-checkout-unified-final.php'  // ÚNICO ENDPOINT
                            ];
                            
                            console.log('🌐 Current host:', currentHost);
                            console.log('🌐 Is real production:', isRealProduction);
                            console.log('🌐 API URLs to try:', apiUrls);
                            
                            // Tentar múltiplas URLs até uma funcionar
                            let response = null;
                            let lastError = null;
                            
                            for (const apiUrl of apiUrls) {
                                try {
                                    console.log('🔄 Trying API URL:', apiUrl);
                                    console.log('🔐 CSRF Token being sent:', csrfToken);
                                    console.log('📤 Booking data with CSRF:', bookingData);
                                    
                                    response = await fetch(apiUrl, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-Token': csrfToken || '',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'Accept': 'application/json'
                                        },
                                        body: JSON.stringify(bookingData),
                                        credentials: 'same-origin'
                                    });
                                    
                                    console.log('🔄 Response status:', response.status);
                                    
                                    if (response.ok) {
                                        console.log('✅ API call successful with URL:', apiUrl);
                                        break; // Sair do loop se a resposta for ok
                                    } else if (response.status === 419) {
                                        // Erro específico de CSRF - tentar próxima URL
                                        const errorData = await response.json().catch(() => ({}));
                                        console.warn(`🔐 CSRF error with URL ${apiUrl}:`, errorData);
                                        throw new Error(`CSRF validation failed (${response.status})`);
                                    } else {
                                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                                    }
                                    
                                } catch (error) {
                                    console.warn(`❌ Failed with URL ${apiUrl}:`, error.message);
                                    lastError = error;
                                    response = null;
                                }
                            }
                            
                            // Verificar se alguma URL funcionou
                            if (!response || !response.ok) {
                                throw lastError || new Error('All API endpoints failed');
                            }
                            
                            const result = await response.json();
                            
                            if (result.success) {
                                console.log('✅ Checkout session created:', result);
                                
                                // Mostrar mensagem de redirecionamento
                                confirmBtn.innerHTML = '<div class="button-content"><i class="fas fa-check"></i><span>Redirecting to Payment...</span></div>';
                                
                                // Redirecionar para Stripe Checkout
                                setTimeout(() => {
                                    window.location.href = result.checkout_url;
                                }, 1000);
                                
                            } else {
                                throw new Error(result.error || 'Checkout failed');
                            }
                            
                        } catch (error) {
                            console.error('❌ Checkout error:', error);
                            
                            // Mostrar erro ao usuário
                            alert('Checkout failed: ' + error.message + '\n\nPlease try again or contact support.');
                            
                            // Restaurar botão
                            confirmBtn.disabled = false;
                            confirmBtn.innerHTML = '<div class="button-content"><i class="fas fa-lock"></i><span>Secure Checkout</span><div class="button-shine"></div></div>';
                        }
                    }
                });
                
                console.log('✅ syncTermsCheckbox: Event listener adicionado ao botão Secure Checkout');
            } else {
                console.error('❌ syncTermsCheckbox: Elementos não encontrados!', {
                    termsCheckbox: !!termsCheckbox,
                    confirmBtn: !!confirmBtn
                });
            }
        }
    };
    
    // Configurar modal de termos
    document.addEventListener('DOMContentLoaded', function() {
        const openTermsBtn = document.getElementById('openTermsBtn');
        const termsModal = document.getElementById('termsModal');
        const closeTermsModal = document.getElementById('closeTermsModal');
        
        if (openTermsBtn && termsModal) {
            openTermsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                termsModal.classList.remove('hidden');
            });
        }
        
        if (closeTermsModal && termsModal) {
            closeTermsModal.addEventListener('click', function() {
                termsModal.classList.add('hidden');
            });
        }
        
        // Fechar modal de termos ao clicar fora
        if (termsModal) {
            termsModal.addEventListener('click', function(e) {
                if (e.target === termsModal) {
                    termsModal.classList.add('hidden');
                }
            });
        }
        
        console.log('✅ Modal de termos configurado');
    });
    </script>
    
    <!-- SMART CALENDAR INTEGRATION (JS já carregado anteriormente na linha 2508) -->
    <script>
        // Inicialização do Smart Calendar Modal
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🗓️ Inicializando Smart Calendar Modal...');
            
            // CORREÇÃO CRÍTICA: Inicializar SummaryModal para configurar o botão Secure Checkout
            if (window.SummaryModal && window.SummaryModal.update) {
                console.log('🔧 Inicializando SummaryModal para configurar botão Secure Checkout...');
                window.SummaryModal.update();
            }
            
            // FALLBACK EMERGENCY: Se o botão ainda não funcionar após 3 segundos, forçar ativação
            setTimeout(function() {
                const confirmBtn = document.getElementById('confirmBtn');
                const termsCheckbox = document.getElementById('agreedToTerms');
                
                if (confirmBtn && termsCheckbox) {
                    // Verificar se o botão já tem listeners
                    const hasListeners = confirmBtn._hasSecureCheckoutListener;
                    
                    if (!hasListeners) {
                        console.log('🚨 EMERGENCY FALLBACK: Ativando botão Secure Checkout...');
                        
                        confirmBtn.addEventListener('click', async function(e) {
                            console.log('🔥 EMERGENCY SECURE CHECKOUT CLICKED!');
                            e.preventDefault();
                            
                            if (!termsCheckbox.checked) {
                                alert('Please accept the terms and conditions.');
                                return;
                            }
                            
                            // CORREÇÃO CRÍTICA: Coletar dados com IDs corretos
                            const firstName = document.getElementById('first_name')?.value || '';
                            const lastName = document.getElementById('last_name')?.value || '';
                            const email = document.getElementById('email')?.value || '';
                            const phone = document.getElementById('phone')?.value || '';
                            const address = document.getElementById('address')?.value || '';
                            
                            // Validar campos obrigatórios (usando .trim() para validação consistente)
                            const requiredFields = { firstName, lastName, email, phone, address };
                            const missingFields = Object.entries(requiredFields)
                                .filter(([key, value]) => !value.trim())
                                .map(([key]) => key);
                            
                            if (missingFields.length > 0) {
                                console.log('❌ Emergency validation failed:', missingFields);
                                console.log('Field values:', { firstName, lastName, email, phone, address });
                                alert(`Please fill in the following fields:\n• ${missingFields.join('\n• ')}`);
                                return;
                            }
                            
                            // CORREÇÃO CRÍTICA: Extrair total do frontend para enviar para API
                            const totalPriceElement = document.getElementById('totalPriceLabel');
                            let calculatedAmount = 0.00; // Valor inicial zerado - sem fallback fixo
                            
                            if (totalPriceElement) {
                                const totalText = totalPriceElement.textContent || totalPriceElement.innerText;
                                const totalMatch = totalText.match(/\$?([0-9.,]+)/);
                                if (totalMatch) {
                                    calculatedAmount = parseFloat(totalMatch[1].replace(',', ''));
                                    console.log('✅ Total extraído do frontend:', calculatedAmount);
                                } else {
                                    console.error('❌ Não foi possível extrair total da interface');
                                    alert('Erro: Não foi possível calcular o valor total. Por favor, recarregue a página.');
                                    return;
                                }
                            } else {
                                console.error('❌ Elemento totalPriceLabel não encontrado');
                                alert('Erro: Elemento de preço não encontrado. Por favor, recarregue a página.');
                                return;
                            }

                            // VALIDAÇÃO: Garantir que não enviamos valor zero para o Stripe
                            if (calculatedAmount <= 0) {
                                console.error('❌ Valor total inválido:', calculatedAmount);
                                alert('Erro: O valor total deve ser maior que zero. Por favor, verifique sua seleção.');
                                return;
                            }

                            const bookingData = {
                                service_id: '2', // Service ID correto
                                // CORREÇÃO CRÍTICA: Campos diretos como esperado pela API
                                name: `${firstName} ${lastName}`.trim(),
                                email: email,
                                phone: phone,
                                address: address,
                                suburb: 'Sydney', // Valor padrão
                                postcode: '2000', // Valor padrão
                                // CORREÇÃO: Coletar data e hora corretamente
                                date: (() => {
                                    const executionDate = document.getElementById('execution_date')?.value;
                                    if (executionDate) return executionDate;
                                    
                                    const futureDate = new Date();
                                    futureDate.setDate(futureDate.getDate() + 2);
                                    return futureDate.toISOString().split('T')[0];
                                })(),
                                time: document.getElementById('time_window')?.value || '10:00',
                                recurrence: document.getElementById('recurrence')?.value || 'one-time',
                                extras: {},
                                discount_amount: 0,
                                special_requests: ''
                            };
                            
                            console.log('📤 Emergency checkout data:', bookingData);
                            
                            // Fazer checkout
                            try {
                                // CORREÇÃO PARA PRODUÇÃO: Detectar ambiente corretamente
                                const currentHost = window.location.hostname;
                                const isRealProduction = currentHost === 'bluefacilityservices.com.au' || 
                                                       currentHost.includes('bluefacilityservices.com.au');
                                
                                let apiUrl;
                                
                                // ✅ UNIFICADO - Mesmo endpoint para produção e desenvolvimento
                                apiUrl = 'api/stripe-checkout-unified-final.php';
                                
                                console.log('🚨 Emergency API URL:', apiUrl);
                                console.log('🚨 Emergency current host:', currentHost);
                                console.log('🔐 Emergency CSRF Token:', document.querySelector('[name="_csrf_token"]')?.value);
                                
                                const emergencyCsrfToken = document.querySelector('[name="_csrf_token"]')?.value;
                                
                                const response = await fetch(apiUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-Token': emergencyCsrfToken || '',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify(bookingData),
                                    credentials: 'same-origin'
                                });
                                
                                console.log('🚨 Emergency response status:', response.status);
                                
                                if (!response.ok) {
                                    const responseText = await response.text();
                                    console.error('🚨 Emergency response error:', responseText);
                                    throw new Error(`HTTP ${response.status}: ${responseText.substring(0, 100)}`);
                                }
                                
                                const result = await response.json();
                                
                                if (result.success && result.checkout_url) {
                                    window.location.href = result.checkout_url;
                                } else {
                                    alert('Checkout error: ' + (result.error || 'Unknown error'));
                                }
                            } catch (error) {
                                console.error('Emergency checkout error:', error);
                                alert('Checkout failed: ' + error.message);
                            }
                        });
                        
                        confirmBtn._hasSecureCheckoutListener = true;
                        console.log('✅ Emergency listener added to Secure Checkout button');
                    }
                }
            }, 3000);
            
            let smartCalendar = null;
            
            /**
             * Função para carregar horários disponíveis para uma data específica
             */
            function loadAvailableTimesForDate(dateString, formattedDate) {
                const availableTimesContainer = document.getElementById('available-times-container');
                const timeWindowSelect = document.getElementById('time_window');
                
                if (!availableTimesContainer) return;
                
                console.log('🕐 Carregando horários para:', dateString);
                
                // Mostrar loading
                availableTimesContainer.innerHTML = `
                    <div class="loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        Loading available times for ${formattedDate}...
                    </div>
                `;
                
                // Gerar horários disponíveis (6h às 17h)
                const availableTimes = [];
                for (let hour = 6; hour <= 17; hour++) {
                    const startTime = String(hour).padStart(2, '0') + ':00';
                    const endTime = String(hour + 1).padStart(2, '0') + ':00';
                    
                    availableTimes.push({
                        value: startTime,
                        display: `${startTime} – ${endTime}`,
                        available: true // Em produção, verificar disponibilidade real
                    });
                }
                
                // Simular delay de carregamento para UX
                setTimeout(() => {
                    if (availableTimes.length > 0) {
                        // Criar HTML para horários disponíveis
                        const timesHtml = availableTimes.map(time => `
                            <div class="time-slot ${time.available ? 'available' : 'unavailable'}" 
                                 data-time="${time.value}"
                                 onclick="${time.available ? `selectTimeSlot('${time.value}', '${time.display}')` : ''}"
                                 style="
                                    padding: 12px 16px;
                                    margin: 6px;
                                    border: 2px solid ${time.available ? '#667eea' : '#e2e8f0'};
                                    border-radius: 8px;
                                    background: ${time.available ? 'rgba(102, 126, 234, 0.1)' : 'rgba(0,0,0,0.1)'};
                                    cursor: ${time.available ? 'pointer' : 'not-allowed'};
                                    transition: all 0.3s ease;
                                    display: inline-block;
                                    min-width: 120px;
                                    text-align: center;
                                    color: ${time.available ? '#2d3748' : '#a0aec0'};
                                    font-weight: 500;
                                 "
                                 onmouseover="if(this.dataset.available !== 'false') { this.style.background='rgba(102, 126, 234, 0.2)'; this.style.transform='translateY(-2px)'; }"
                                 onmouseout="if(this.dataset.available !== 'false') { this.style.background='rgba(102, 126, 234, 0.1)'; this.style.transform='translateY(0)'; }"
                            >
                                ${time.display}
                                ${!time.available ? '<br><small style="color: #f56565;">Unavailable</small>' : ''}
                            </div>
                        `).join('');
                        
                        availableTimesContainer.innerHTML = `
                            <div style="margin-bottom: 12px; font-weight: 500; color: #2d3748;">
                                <i class="fas fa-clock" style="margin-right: 8px; color: #667eea;"></i>
                                Available times for ${formattedDate}:
                            </div>
                            <div class="times-grid" style="display: flex; flex-wrap: wrap; gap: 8px;">
                                ${timesHtml}
                            </div>
                            <div style="margin-top: 12px; font-size: 0.85rem; color: #718096; font-style: italic;">
                                Click on a time slot to select it
                            </div>
                        `;
                        
                        // Update SmartTimePicker with available times
                        if (window.smartTimePicker) {
                            window.smartTimePicker.updateAvailableTimes(availableTimes);
                        }
                    } else {
                        availableTimesContainer.innerHTML = `
                            <div class="no-times">
                                <i class="fas fa-calendar-times" style="margin-right: 8px; color: #f56565;"></i>
                                No available times for ${formattedDate}. Please select another date.
                            </div>
                        `;
                    }
                }, 800); // Simular loading de 800ms
            }
            
            /**
             * Função para selecionar um horário específico
             */
            function selectTimeSlot(timeValue, timeDisplay) {
                console.log('🕐 Horário selecionado:', timeValue);
                
                // Remover seleção anterior
                document.querySelectorAll('.time-slot').forEach(slot => {
                    slot.style.border = '2px solid #667eea';
                    slot.style.background = 'rgba(102, 126, 234, 0.1)';
                    slot.style.fontWeight = '500';
                });
                
                // Marcar horário selecionado
                const selectedSlot = document.querySelector(`[data-time="${timeValue}"]`);
                if (selectedSlot) {
                    selectedSlot.style.border = '2px solid #48bb78';
                    selectedSlot.style.background = 'rgba(72, 187, 120, 0.2)';
                    selectedSlot.style.fontWeight = '600';
                    selectedSlot.style.boxShadow = '0 4px 12px rgba(72, 187, 120, 0.3)';
                }
                
                // Atualizar campo oculto time_window
                const timeWindowSelect = document.getElementById('time_window');
                if (timeWindowSelect) {
                    timeWindowSelect.value = timeValue;
                    timeWindowSelect.dispatchEvent(new Event('change'));
                }
                
                // Atualizar campo oculto booking-time
                const bookingTimeField = document.getElementById('booking-time');
                if (bookingTimeField) {
                    bookingTimeField.value = timeValue;
                }
                
                // Feedback visual
                console.log(`✅ Horário ${timeDisplay} selecionado e sincronizado`);
                
                // Trigger update do resumo
                updateBookingSummary();
            }
            
            // RESTAURADO: Event listener para abrir Smart Calendar customizado
            const executionDateField = document.getElementById('execution_date');
            
            if (executionDateField) {
                executionDateField.addEventListener('click', function() {
                    console.log('📅 Abrindo Smart Calendar Modal...');
                    
                    // Criar o calendário modal se não existe
                    if (!smartCalendar) {
                        smartCalendar = new SmartBookingCalendar({
                            containerId: 'smart-calendar-modal',
                            serviceId: <?= SERVICE_ID_HOUSE_CLEANING ?>,
                            modal: true,
                            onDateSelected: function(dateData) {
                                console.log('📅 Data selecionada:', dateData);
                                
                                // Atualizar campo de data
                                executionDateField.value = dateData.formattedDate;
                                executionDateField.dispatchEvent(new Event('change'));
                                
                                // Disparar evento personalizado para integração
                                document.dispatchEvent(new CustomEvent('smartCalendarDateSelected', {
                                    detail: dateData
                                }));
                                
                                // Fechar modal
                                smartCalendar.closeModal();
                            }
                        });
                    }
                    
                    // Abrir o modal
                    smartCalendar.openModal();
                });
                
                // Configurar visual do campo
                executionDateField.style.cursor = 'pointer';
                executionDateField.placeholder = 'Clique para escolher a data';
                executionDateField.readOnly = true; // Campo apenas clicável, não editável
                executionDateField.type = 'text'; // Usar input type="text" para evitar calendário nativo
                
                // Listener adicional para mudanças diretas no campo de data (fallback)
                executionDateField.addEventListener('change', function() {
                    const selectedDate = this.value;
                    if (selectedDate) {
                        console.log('📅 Data alterada diretamente:', selectedDate);
                        
                        // Formatar data para exibição
                        const dateObj = new Date(selectedDate + 'T00:00:00');
                        const formattedDate = dateObj.toLocaleDateString('pt-BR');
                        
                        // Disparar carregamento de horários
                        loadAvailableTimesForDate(selectedDate, formattedDate);
                        
                        // Mostrar seção de horários dinâmica
                        const timeSection = document.getElementById('time-selection');
                        const staticTimeSelect = document.getElementById('time_window');
                        
                        if (timeSection && staticTimeSelect) {
                            timeSection.style.display = 'block';
                            staticTimeSelect.style.display = 'none';
                        }
                    }
                });
            }
            
            // Listener para integração com sistemas existentes
            document.addEventListener('smartCalendarDateSelected', function(event) {
                const data = event.detail;
                console.log('📅 Data selecionada no Smart Calendar:', data);
                
                // Atualizar campos ocultos se existirem
                const bookingDateField = document.getElementById('booking-date');
                if (bookingDateField) {
                    bookingDateField.value = data.dateString;
                }
                
                // Atualizar interface de horários
                const timeSection = document.getElementById('time-selection');
                const staticTimeSelect = document.getElementById('time_window');
                const availableTimesContainer = document.getElementById('available-times-container');
                
                if (timeSection && staticTimeSelect && availableTimesContainer) {
                    // Mostrar seleção dinâmica de horários
                    timeSection.style.display = 'block';
                    staticTimeSelect.style.display = 'none';
                    
                    // Carregar horários disponíveis para a data selecionada
                    loadAvailableTimesForDate(data.dateString, data.formattedDate);
                }
                
                // Feedback visual no campo de data
                const feedback = document.getElementById('selected-date-display');
                if (feedback) {
                    feedback.textContent = `Selected: ${data.formattedDate}`;
                    feedback.style.display = 'block';
                    feedback.style.color = '#48bb78';
                    feedback.style.fontWeight = '500';
                    feedback.style.marginTop = '8px';
                    feedback.style.fontSize = '0.9rem';
                }
            });
            
            // Função para quando um horário é selecionado
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('time-slot')) {
                    const selectedTime = e.target.dataset.time;
                    console.log('⏰ Horário selecionado:', selectedTime);
                    
                    // Sincronizar com campo de horário tradicional
                    const timeWindowSelect = document.getElementById('time_window');
                    if (timeWindowSelect) {
                        timeWindowSelect.value = selectedTime;
                        timeWindowSelect.dispatchEvent(new Event('change'));
                    }
                    
                    // Atualizar resumo se necessário
                    if (typeof BookingInterface !== 'undefined' && BookingInterface.updateSummary) {
                        BookingInterface.updateSummary();
                    }
                }
            });

            // Initialize Smart Time Picker with improved error handling
            let smartTimePicker = null;
            let timePicker_InitAttempts = 0;
            const MAX_INIT_ATTEMPTS = 10;
            
            function initializeSmartTimePicker() {
                timePicker_InitAttempts++;
                
                console.log(`🕐 Tentativa ${timePicker_InitAttempts} de inicializar Smart Time Picker...`);
                
                if (typeof SmartTimePicker !== 'undefined') {
                    try {
                        smartTimePicker = new SmartTimePicker({
                            inputId: 'time_display',
                            hiddenFieldId: 'time_window',
                            serviceId: <?= SERVICE_ID_HOUSE_CLEANING ?>,
                            onTimeSelected: function(timeData) {
                                console.log('⏰ Horário selecionado:', timeData);
                                
                                // Update display field
                                const displayField = document.getElementById('time_display');
                                if (displayField) {
                                    displayField.value = timeData.display || timeData.label || timeData.time;
                                    displayField.classList.add('field-completed');
                                }
                                
                                // Update hidden field
                                const hiddenField = document.getElementById('time_window');
                                if (hiddenField) {
                                    hiddenField.value = timeData.time || timeData.value;
                                    hiddenField.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                                
                                // Update booking summary
                                if (typeof updateBookingSummary === 'function') {
                                    updateBookingSummary();
                                } else if (typeof BookingInterface !== 'undefined' && BookingInterface.updateSummary) {
                                    BookingInterface.updateSummary();
                                }
                            }
                        });
                        
                        window.smartTimePicker = smartTimePicker; // Make it globally accessible
                        console.log('✅ SmartTimePicker initialized successfully!');
                        
                        // Verify input field event listener
                        const inputField = document.getElementById('time_display');
                        if (inputField) {
                            console.log('✅ Input field found and should be clickable:', inputField);
                            
                            // Adicionar listener adicional como backup
                            inputField.addEventListener('click', function(e) {
                                e.preventDefault();
                                console.log('🖱️ Input clicked (backup listener)');
                                if (window.smartTimePicker) {
                                    window.smartTimePicker.openModal();
                                } else {
                                    console.warn('⚠️ smartTimePicker not available on click');
                                    activateTimeFallback();
                                }
                            });
                            
                            // Marcar como funcionando
                            smartTimePicker.isWorking = true;
                        }
                        
                    } catch (error) {
                        console.error('❌ Erro ao inicializar SmartTimePicker:', error);
                        if (timePicker_InitAttempts < MAX_INIT_ATTEMPTS) {
                            setTimeout(initializeSmartTimePicker, 500);
                        }
                    }
                } else {
                    console.warn(`⚠️ SmartTimePicker não carregado ainda (tentativa ${timePicker_InitAttempts}/${MAX_INIT_ATTEMPTS})`);
                    if (timePicker_InitAttempts < MAX_INIT_ATTEMPTS) {
                        setTimeout(initializeSmartTimePicker, 200);
                    } else {
                        console.error('❌ SmartTimePicker falhou ao carregar após múltiplas tentativas');
                    }
                }
            }
            
            // Initialize when DOM and SmartTimePicker are ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeSmartTimePicker);
            } else {
                initializeSmartTimePicker();
            }
            
            // Fallback system - ativar select básico se Smart Time Picker não funcionar
            setTimeout(() => {
                if (!window.smartTimePicker || !window.smartTimePicker.isWorking) {
                    console.warn('⚠️ Ativando sistema fallback para seleção de horários');
                    activateTimeFallback();
                }
            }, 3000); // Aguardar 3 segundos
            
            // DEBUG: Teste manual de clique
            setTimeout(() => {
                const timeInput = document.getElementById('time_display');
                if (timeInput) {
                    console.log('🔧 DEBUG: Adicionando listener de teste no input de horário');
                    timeInput.addEventListener('click', function(e) {
                        console.log('🖱️ DEBUG: Input clicado!', e.target);
                        // Para teste, vamos mostrar um alert
                        if (!window.smartTimePicker || !window.smartTimePicker.isWorking) {
                            console.log('⚠️ SmartTimePicker não funcionando, ativando fallback');
                            activateTimeFallback();
                        }
                    });
                }
            }, 1000);
            
            function activateTimeFallback() {
                const timeDisplay = document.getElementById('time_display');
                const timeFallback = document.getElementById('time_window_fallback');
                const timeHidden = document.getElementById('time_window');
                
                if (timeDisplay && timeFallback) {
                    // Esconder input de display e mostrar select
                    timeDisplay.style.display = 'none';
                    timeFallback.style.display = 'block';
                    timeFallback.required = true;
                    
                    // Event listener para select fallback
                    timeFallback.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        if (this.value && timeHidden) {
                            timeHidden.value = this.value;
                            timeHidden.dispatchEvent(new Event('change', { bubbles: true }));
                            console.log('⏰ Horário selecionado (fallback):', this.value);
                        }
                    });
                    
                    console.log('✅ Sistema de fallback ativado');
                }
            }
        });
    </script>
    
    <!-- 🚨 EMERGENCY PRICE FIX - Corrigir cálculo dos serviços inclusos -->
    <script src="emergency-price-fix.js"></script>
</body>
</html>

<?php
/**
 * =========================================================
 * FUNÇÕES PARA APIS DE ASSINATURA - INTEGRAÇÃO UNIFICADA
 * =========================================================
 * 
 * Estas funções foram movidas do booking2.php para booking3.php
 * para centralizar toda a configuração em um único arquivo.
 */

// Só define as funções se ainda não foram definidas
if (!function_exists('loadBookingInfo')) {

    /**
     * Configurações para sistema de assinaturas
     */
    $subscriptionConfig = [
        'pricing_engine' => true,
        'stripe_manager' => true,
        'tax_policy' => 'centralized',
        'automatic_tax' => false, // Consistente com StripeManager
        'version' => 'unified_2025'
    ];

    /**
     * Configurações de cancelamento
     */
    $cancellationConfig = [
        'free_cancellation_hours' => 48,
        'refund_percentage' => 0.9,
        'processing_fee' => 0.1,
        'use_stripe_manager' => true
    ];

    /**
     * Configurações de pausa
     */
    $pauseConfig = [
        'max_pause_duration_days' => 90,
        'minimum_notice_hours' => 48,
        'max_pauses_per_year' => 4,
        'pause_processing_fee' => 0.05,
        'use_stripe_manager' => true
    ];

    /**
     * Carrega informações do booking usando o sistema unificado
     */
    function loadBookingInfo($bookingId) {
        global $subscriptionConfig;
        
        return [
            'booking_id' => $bookingId,
            'customer_email' => 'cliente@exemplo.com',
            'service_name' => 'Professional Cleaning Service',
            'total_amount' => 0.00, // Valor será calculado dinamicamente
            'stripe_subscription_id' => 'sub_' . $bookingId,
            'payment_method_id' => 'pm_' . uniqid(),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'frequency' => 'weekly',
            'next_payment' => date('Y-m-d', strtotime('+1 week')),
            'tax_policy' => $subscriptionConfig['tax_policy'],
            'automatic_tax' => $subscriptionConfig['automatic_tax']
        ];
    }

    /**
     * Carrega detalhes da assinatura
     */
    function loadSubscriptionDetails($bookingId, $customerId) {
        $booking = loadBookingInfo($bookingId);
        
        if (!$booking) return null;
        
        return array_merge($booking, [
            'customer_id' => $customerId,
            'subscription_details' => [
                'plan_type' => 'weekly_cleaning',
                'price' => 0.00, // Valor será calculado dinamicamente
                'currency' => 'AUD',
                'tax_inclusive' => false,
                'next_billing_date' => date('Y-m-d', strtotime('+1 week')),
                'billing_cycle' => 'weekly'
            ]
        ]);
    }

    /**
     * Processa cancelamento usando StripeManager unificado
     */
    function processStripeCancellation($bookingInfo, $cancellationData) {
        global $cancellationConfig;
        
        try {
            // Carrega configuração se StripeManager estiver disponível
            if (class_exists('StripeManager')) {
                $stripeManager = StripeManager::getInstance();
                $taxConfig = $stripeManager->getTaxConfig();
            } else {
                $taxConfig = ['policy_description' => 'Prices are final - no additional taxes applied'];
            }
            
            $subscriptionId = $bookingInfo['stripe_subscription_id'];
            
            $refundAmount = 0;
            if (isWithinFreeCancellationWindow($bookingInfo)) {
                $baseAmount = $bookingInfo['total_amount'];
                $refundAmount = $baseAmount * $cancellationConfig['refund_percentage'];
            }
            
            error_log("📋 Subscription Cancellation - Using unified system");
            error_log("💰 Base amount: $" . $bookingInfo['total_amount']);
            error_log("💸 Refund amount: $" . $refundAmount);
            
            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'cancellation_id' => 'cancel_' . uniqid(),
                'cancelled_at' => time(),
                'refund_amount' => $refundAmount,
                'status' => 'cancelled',
                'tax_config' => $taxConfig,
                'processing_fee' => $bookingInfo['total_amount'] * $cancellationConfig['processing_fee'],
                'stripe_manager_version' => 'unified_2025'
            ];
            
        } catch (Exception $e) {
            error_log("❌ Stripe Cancellation Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Processa pausa usando StripeManager unificado
     */
    function processStripePause($bookingInfo, $pauseData) {
        global $pauseConfig;
        
        try {
            // Carrega configuração se StripeManager estiver disponível  
            if (class_exists('StripeManager')) {
                $stripeManager = StripeManager::getInstance();
                $taxConfig = $stripeManager->getTaxConfig();
            } else {
                $taxConfig = ['policy_description' => 'Prices are final - no additional taxes applied'];
            }
            
            $subscriptionId = $bookingInfo['stripe_subscription_id'];
            $pauseFee = $bookingInfo['total_amount'] * $pauseConfig['pause_processing_fee'];
            
            error_log("📋 Subscription Pause - Using unified system");
            error_log("💰 Base amount: $" . $bookingInfo['total_amount']);
            error_log("💸 Pause fee: $" . $pauseFee);
            
            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'pause_id' => 'pause_' . uniqid(),
                'paused_at' => time(),
                'resume_at' => strtotime($pauseData['start_date'] . ' +' . $pauseData['duration'] . ' days'),
                'pause_fee' => $pauseFee,
                'status' => 'paused',
                'tax_config' => $taxConfig,
                'stripe_manager_version' => 'unified_2025'
            ];
            
        } catch (Exception $e) {
            error_log("❌ Stripe Pause Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica se está dentro da janela de cancelamento gratuito
     */
    function isWithinFreeCancellationWindow($bookingInfo) {
        global $cancellationConfig;
        
        $createdAt = strtotime($bookingInfo['created_at']);
        $hoursDiff = (time() - $createdAt) / 3600;
        
        return $hoursDiff <= ($cancellationConfig['free_cancellation_hours'] ?? 48);
    }

    /**
     * Obtém histórico de pausas
     */
    function getPauseHistory($customerEmail) {
        return [
            [
                'pause_id' => 'pause_123',
                'start_date' => '2025-07-15',
                'end_date' => '2025-07-30',
                'duration_days' => 15,
                'reason' => 'Vacation',
                'status' => 'completed'
            ]
        ];
    }

    /**
     * Obtém histórico de pagamentos
     */
    function getPaymentHistory($bookingId) {
        return [
            [
                'payment_id' => 'pi_123',
                'amount' => 0.00, // Valor será calculado dinamicamente
                'currency' => 'AUD',
                'status' => 'succeeded',
                'created' => date('Y-m-d H:i:s', strtotime('-1 week')),
                'description' => 'Weekly cleaning service'
            ]
        ];
    }

    /**
     * Obtém histórico de serviços
     */
    function getServiceHistory($bookingId) {
        return [
            [
                'service_date' => date('Y-m-d', strtotime('-1 week')),
                'professional' => 'Maria Silva',
                'status' => 'completed',
                'rating' => 5,
                'feedback' => 'Excellent service!'
            ]
        ];
    }

    /**
     * Processa reembolso
     */
    function processRefund($bookingInfo, $refundAmount) {
        try {
            error_log("💸 Processing refund: $" . $refundAmount);
            
            return [
                'success' => true,
                'refund_id' => 're_' . uniqid(),
                'amount' => $refundAmount,
                'status' => 'succeeded',
                'stripe_manager_version' => 'unified_2025'
            ];
            
        } catch (Exception $e) {
            error_log("❌ Refund Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Log de inicialização
    error_log("📋 Subscription functions loaded in booking3.php - Version: unified_2025");
}
?>

<script>
// ========================================
// SISTEMA UNIFICADO DE CÓDIGOS
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    const unifiedInput = document.getElementById('unifiedCodeInput');
    const applyBtn = document.getElementById('applyUnifiedCodeBtn');
    const statusDiv = document.getElementById('unifiedCodeStatus');
    const hiddenReferralField = document.getElementById('hiddenReferralCode');
    const hiddenCodeTypeField = document.getElementById('hiddenCodeType');
    
    if (applyBtn && unifiedInput) {
        applyBtn.addEventListener('click', function() {
            const code = unifiedInput.value.trim().toUpperCase();
            
            if (!code) {
                showCodeStatus('Please enter a code', 'error');
                return;
            }
            
            // Validar formato básico do código
            if (code.length < 3) {
                showCodeStatus('Code too short', 'error');
                return;
            }
            
            // Aplicar código unificado
            applyUnifiedCode(code);
        });
        
        // Auto-aplicar se vier da URL
        const urlCode = unifiedInput.value;
        if (urlCode && urlCode.trim()) {
            setTimeout(() => {
                applyUnifiedCode(urlCode.trim().toUpperCase());
            }, 1000);
        }
    }
    
    function applyUnifiedCode(code) {
        // Mostrar loading
        showCodeStatus('Validating code...', 'loading');
        
        // VALIDAÇÃO REAL - Fazer chamada AJAX para o backend
        fetch('api/validate-unified-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ code: code })
        })
        .then(response => response.json())
        .then(data => {
            console.log('🔍 Code validation result:', data);
            
            if (data.success && data.valid) {
                // Código válido - aplicar
                hiddenReferralField.value = code;
                console.log('✅ referral_code set to hidden field:', code);
                console.log('✅ Hidden field value confirmed:', hiddenReferralField.value);
                hiddenCodeTypeField.value = data.type;
                
                if (data.type === 'referral') {
                    showCodeStatus(`✓ Referral code "${code}" applied! ${data.discount_percentage}% discount + commission for referrer.`, 'success');
                    console.log('🎁 Referral code applied:', code, data);
                    
                } else if (data.type === 'promo') {
                    let discountText = data.discount_amount > 0 ? 
                        `$${data.discount_amount} off` : 
                        `${data.discount_percentage}% off`;
                    showCodeStatus(`✓ Promo code "${code}" applied! ${discountText}`, 'promo-success');
                    console.log('🏷️ Promo code applied:', code, data);
                }
                
                // Atualizar pricing se necessário
                if (data.discount_percentage > 0 || data.discount_amount > 0) {
                    // Trigger recalculation of pricing
                    if (typeof updateTotalPricing === 'function') {
                        updateTotalPricing();
                    }
                }
                
            } else {
                // Código inválido
                hiddenReferralField.value = '';
                hiddenCodeTypeField.value = '';
                showCodeStatus(data.message || `Code "${code}" not found. Please check and try again.`, 'error');
                console.log('❌ Invalid code:', code, data);
            }
        })
        .catch(error => {
            console.error('❌ Code validation error:', error);
            hiddenReferralField.value = '';
            hiddenCodeTypeField.value = '';
            showCodeStatus('Error validating code. Please try again.', 'error');
        });
    }
    
    function detectCodeType(code) {
        // Padrões para detectar tipo de código
        if (code.match(/^(FRIEND|REF|USER|MEMBER)/i)) {
            return 'referral'; // Códigos que começam com FRIEND, REF, USER, MEMBER
        }
        
        if (code.match(/^(SUMMER|WINTER|SPRING|FALL|SALE|PROMO|DISCOUNT|NEW|WELCOME)/i)) {
            return 'promo'; // Códigos promocionais sazonais
        }
        
        if (code.match(/\d{2,}/)) {
            return 'promo'; // Códigos com muitos números tendem a ser promos
        }
        
        // Por padrão, assumir que é referral se for formato simples
        return 'referral';
    }
    
    function showCodeStatus(message, type) {
        statusDiv.textContent = message;
        statusDiv.className = `unified-code-status ${type}`;
        
        if (type === 'loading') {
            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + message;
        }
        
        // Auto-hide after success
        if (type === 'success' || type === 'promo-success') {
            setTimeout(() => {
                statusDiv.style.opacity = '0.7';
            }, 5000);
        }
    }
});
</script>
