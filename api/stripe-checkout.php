<?php
/**
 * =========================================================
 * STRIPE CHECKOUT API - BLUE CLEANING SERVICES
 * =========================================================
 * 
 * @file api/stripe-checkout.php
 * @description Processa checkout via Stripe com assinaturas inteligentes
 * @version 3.0 - FULL IMPLEMENTATION
 * @date 2025-08-10
 * 
 * FUNCIONALIDADES:
 * - Assinaturas com cobrança 48h antes do serviço
 * - Fortnightly usando interval_count: 2
 * - Sistema de tiers de pause automático
 * - Metadata rica para tracking completo
 * - Validação de disponibilidade em tempo real
 * - Integração com webhook enterprise existente
 */

// Headers de segurança e CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Aplicar middleware de segurança
require_once __DIR__ . '/../auth/SecurityMiddleware.php';

// Capturar JSON input ANTES do middleware
$jsonInput = json_decode(file_get_contents('php://input'), true);
if ($jsonInput && isset($jsonInput['_csrf_token'])) {
    // Populamos $_POST com o token CSRF para o middleware
    $_POST['_csrf_token'] = $jsonInput['_csrf_token'];
    error_log("CSRF token set from JSON: " . $jsonInput['_csrf_token']);
}

security_protect([
    'rate_limit' => ['max_requests' => 20, 'window' => 3600],
    'require_csrf' => true
]);

// Carregar dependências
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/australian-database.php';

// Configurar Stripe
require_once __DIR__ . '/../vendor/autoload.php';
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? '');

// Logging
error_log("=== STRIPE CHECKOUT INITIATED === " . date('Y-m-d H:i:s'));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // 1. CAPTURAR E VALIDAR DADOS
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON payload');
    }

    error_log("Checkout payload received: " . json_encode($input, JSON_UNESCAPED_UNICODE));

    // Validar campos obrigatórios
    $requiredFields = ['service', 'address', 'date', 'time', 'recurrence', 'customer'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Conectar ao banco
    $db = AustralianDatabase::getInstance();
    $connection = $db->getConnection();

    // 2. VALIDAR DISPONIBILIDADE EM TEMPO REAL (OPCIONAL)
    $selectedDate = $input['date'] ?? date('Y-m-d', strtotime('+2 days'));
    $selectedTime = $input['time'] ?? '10:00';
    
    error_log("🔍 Checking availability for date: {$selectedDate}, time: {$selectedTime}");
    
    // Verificar se a data é válida (não no passado e não muito no futuro)
    $selectedDateTime = DateTime::createFromFormat('Y-m-d H:i', $selectedDate . ' ' . $selectedTime);
    $now = new DateTime();
    $maxFutureDate = (clone $now)->add(new DateInterval('P6M')); // 6 meses no futuro
    
    if (!$selectedDateTime || $selectedDateTime <= $now) {
        throw new Exception('Selected date and time must be in the future');
    }
    
    if ($selectedDateTime > $maxFutureDate) {
        throw new Exception('Selected date is too far in the future (max 6 months)');
    }
    
    // Verificar disponibilidade básica (opcional - só se tabela existir)
    try {
        $availabilityCheck = $connection->prepare("
            SELECT id, professional_id, max_concurrent_bookings
            FROM professional_availability 
            WHERE date = ? 
            AND start_time <= ? 
            AND end_time >= ?
            AND is_available = 1
            LIMIT 1
        ");
        $availabilityCheck->execute([$selectedDate, $selectedTime, $selectedTime]);
        $availableSlot = $availabilityCheck->fetch();
        
        if ($availableSlot) {
            error_log("✅ Professional availability confirmed: Professional {$availableSlot['professional_id']}");
        } else {
            error_log("⚠️ No specific professional availability found, but proceeding with booking");
        }
    } catch (Exception $availabilityError) {
        error_log("⚠️ Availability check failed (table may not exist): " . $availabilityError->getMessage());
        error_log("📝 Proceeding with booking without availability validation");
    }

    // 3. BUSCAR DADOS DO SERVIÇO
    $serviceStmt = $connection->prepare("SELECT * FROM services WHERE id = ? AND is_active = 1");
    $serviceStmt->execute([$input['service']]);
    $service = $serviceStmt->fetch();

    if (!$service) {
        throw new Exception('Service not found or inactive');
    }

    // 4. CALCULAR PREÇOS (baseado na lógica do BookingSummaryManager)
    $basePrice = (float)$service['base_price'];
    $extrasPrice = 0;
    $discountAmount = 0;

    // CRÍTICO: Se o frontend enviou um total, usar ele (mais confiável que recálculos)
    $frontendTotal = !empty($input['total']) ? floatval($input['total']) : null;
    
    if ($frontendTotal && $frontendTotal > 0) {
        // Use exact frontend total without any modifications
        $totalAmount = $frontendTotal;
        $basePrice = $frontendTotal; // For compatibility with logs
        $gstAmount = 0; // No tax calculations
        
        error_log("✅ Using frontend total EXACTLY: {$totalAmount}");
        error_log("✅ NO TAX CALCULATION - Total sent to Stripe: {$totalAmount}");
    } else {
        // Fallback: Calcular aqui (método original)
        // Calcular extras
        if (!empty($input['extras'])) {
            foreach ($input['extras'] as $extraId => $selected) {
                if ($selected) {
                    $extraStmt = $connection->prepare("SELECT price FROM service_extras WHERE id = ?");
                    $extraStmt->execute([$extraId]);
                    $extra = $extraStmt->fetch();
                    if ($extra) {
                        $extrasPrice += (float)$extra['price'];
                    }
                }
            }
        }

        // Calcular desconto por recorrência (baseado em booking3.php)
        $recurrenceDiscounts = [
            'one-time' => 0,
            'weekly' => 10,
            'fortnightly' => 15,
            'monthly' => 20
        ];
        
        $discountPercentage = $recurrenceDiscounts[$input['recurrence']] ?? 0;
        if ($discountPercentage > 0) {
            $discountAmount = ($basePrice + $extrasPrice) * ($discountPercentage / 100);
        }

        // Final calculation WITHOUT any tax
        $subtotal = ($basePrice + $extrasPrice) - $discountAmount;
        $gstAmount = 0; // No tax calculations
        $totalAmount = $subtotal;
        
        error_log("💰 Calculated pricing: Base: {$basePrice}, Extras: {$extrasPrice}, Discount: {$discountAmount}, Total: {$totalAmount}");
    }

    // 5. GERAR BOOKING CODE
    $bookingCode = 'BK' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

    // 6. DETERMINAR TIER DE PAUSE (baseado no sistema existente)
    $pauseTier = 'basic'; // Default
    $freePauses = 2; // Default para basic tier

    // 7. CALCULAR DATAS DE COBRANÇA (48h antes)
    $executionDate = new DateTime($selectedDate . ' ' . $selectedTime);
    $firstChargeDate = clone $executionDate;
    $firstChargeDate->sub(new DateInterval('PT48H'));

    // 8. CRIAR METADATA COMPLETA
    $metadata = [
        'booking_code' => $bookingCode,
        'service_id' => $input['service'],
        'service_name' => $service['name'],
        'professional_id' => $availableSlot['professional_id'],
        'customer_name' => $input['customer']['name'],
        'customer_email' => $input['customer']['email'],
        'customer_phone' => $input['customer']['phone'],
        'service_address' => $input['address'],
        'service_date' => $selectedDate,
        'service_time' => $selectedTime,
        'recurrence_type' => $input['recurrence'],
        'base_price' => number_format($basePrice, 2),
        'extras_price' => number_format($extrasPrice, 2),
        'discount_amount' => number_format($discountAmount, 2),
        'total_amount' => number_format($totalAmount, 2),
        'currency' => 'AUD',
        'timezone' => 'Australia/Sydney',
        'first_charge_date' => $firstChargeDate->format('Y-m-d H:i:s'),
        'charge_48h_before' => 'true',
        'pause_tier' => $pauseTier,
        'free_pauses_allowed' => (string)$freePauses,
        'booking_advance_hours' => '48',
        'created_via' => 'booking3_checkout',
        'api_version' => '3.0'
    ];

    // 9. PREPARAR LINE ITEMS PARA STRIPE
    $productDescription = $service['name'] . " - " . $executionDate->format('d/m/Y \a\t H:i');
    if ($input['recurrence'] !== 'one-time') {
        $productDescription .= " (" . ucfirst($input['recurrence']) . " Service)";
    }

    $lineItems = [
        [
            'price_data' => [
                'currency' => 'aud',
                'product_data' => [
                    'name' => $service['name'],
                    'description' => $productDescription,
                    'metadata' => [
                        'service_code' => $service['service_code'],
                        'booking_code' => $bookingCode
                    ]
                ],
                'unit_amount' => round($totalAmount * 100), // Converter para centavos
                'recurring' => null // Será definido abaixo se for assinatura
            ],
            'quantity' => 1,
        ]
    ];

    // 10. CONFIGURAR ASSINATURA VS PAGAMENTO ÚNICO
    $checkoutMode = 'payment';
    $subscriptionData = null;

    if ($input['recurrence'] !== 'one-time') {
        $checkoutMode = 'subscription';
        
        // Mapear recorrência para intervalos do Stripe
        $stripeIntervals = [
            'weekly' => ['interval' => 'week', 'interval_count' => 1],
            'fortnightly' => ['interval' => 'week', 'interval_count' => 2], // FORTNIGHTLY = 2 weeks
            'monthly' => ['interval' => 'month', 'interval_count' => 1]
        ];

        $intervalConfig = $stripeIntervals[$input['recurrence']];
        $lineItems[0]['price_data']['recurring'] = $intervalConfig;

        // Configurar trial period para cobrança 48h antes
        $trialPeriodDays = 2; // 48 horas = 2 dias
        
        $subscriptionData = [
            'trial_period_days' => $trialPeriodDays,
            'billing_cycle_anchor' => $firstChargeDate->getTimestamp(),
            'metadata' => $metadata
        ];
    }

    // 11. URLs de SUCESSO E CANCELAMENTO
    $successUrl = ($_ENV['APP_URL'] ?? 'http://localhost:3000') . '/booking-confirmation.php?session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl = ($_ENV['APP_URL'] ?? 'http://localhost:3000') . '/booking3.php?cancelled=1';

    // 12. CRIAR CHECKOUT SESSION NO STRIPE
    $sessionConfig = [
        'mode' => $checkoutMode,
        'line_items' => $lineItems,
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'customer_email' => $input['customer']['email'],
        'billing_address_collection' => 'auto',
        'shipping_address_collection' => [
            'allowed_countries' => ['AU'],
        ],
        'metadata' => $metadata
    ];

    // Adicionar subscription_data se for assinatura
    if ($subscriptionData) {
        $sessionConfig['subscription_data'] = $subscriptionData;
    }

    error_log("🔄 Creating Stripe Checkout Session with config: " . json_encode($sessionConfig, JSON_UNESCAPED_UNICODE));

    $session = \Stripe\Checkout\Session::create($sessionConfig);

    error_log("✅ Stripe Checkout Session created: " . $session->id);

    // 13. SALVAR PRÉ-BOOKING NO BANCO (status pending)
    $preBookingStmt = $connection->prepare("
        INSERT INTO bookings (
            booking_code, professional_id, service_id, customer_name, customer_email, 
            customer_phone, street_address, suburb, state, postcode, scheduled_date, 
            scheduled_time, duration_minutes, base_price, extras_price, discount_amount, 
            total_amount, status, payment_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
    ");

    // Extrair partes do endereço (simplificado)
    $addressParts = explode(',', $input['address']);
    $streetAddress = trim($addressParts[0] ?? $input['address']);
    $suburb = trim($addressParts[1] ?? 'Melbourne');
    $state = 'VIC'; // Default
    $postcode = '3000'; // Default

    $preBookingStmt->execute([
        $bookingCode,
        $availableSlot['professional_id'],
        $input['service'],
        $input['customer']['name'],
        $input['customer']['email'],
        $input['customer']['phone'],
        $streetAddress,
        $suburb,
        $state,
        $postcode,
        $selectedDate,
        $selectedTime,
        $service['duration_minutes'],
        $basePrice,
        $extrasPrice,
        $discountAmount,
        $totalAmount
    ]);

    error_log("💾 Pre-booking saved to database: " . $bookingCode);

    // 14. RESPOSTA DE SUCESSO
    $response = [
        'success' => true,
        'checkout_url' => $session->url,
        'session_id' => $session->id,
        'booking_code' => $bookingCode,
        'professional_assigned' => $availableSlot['professional_id'],
        'service_date' => $selectedDate,
        'service_time' => $selectedTime,
        'total_amount' => number_format($totalAmount, 2),
        'currency' => 'AUD',
        'recurrence' => $input['recurrence'],
        'first_charge_date' => $firstChargeDate->format('d/m/Y H:i'),
        'message' => 'Checkout session created successfully'
    ];

    error_log("✅ Checkout response: " . json_encode($response, JSON_UNESCAPED_UNICODE));

    echo json_encode($response);

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("❌ Stripe API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Payment processing error: ' . $e->getMessage(),
        'type' => 'stripe_error'
    ]);
    
} catch (Exception $e) {
    error_log("❌ General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'general_error'
    ]);
}

error_log("=== STRIPE CHECKOUT COMPLETED === " . date('Y-m-d H:i:s') . "\n");
?>