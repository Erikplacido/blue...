<?php
/**
 * =========================================================
 * STRIPE MANAGER - GERENCIADOR ÚNICO E CENTRALIZADO
 * ===========            // 5. CRIAR SESSÃO STRIPE COM EXPERIÊNCIA APRIMORADA
            $sessionParams = [
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $this->config['success_url'] . '?session_id={CHECKOUT_SESSION_ID}&booking_code=' . $bookingCode,
                'cancel_url' => $this->config['cancel_url'],
                'metadata' => $metadata,
                'customer_email' => $bookingData['email'] ?? null,
                'allow_promotion_codes' => false,
                'billing_address_collection' => 'required',
                'shipping_address_collection' => [
                    'allowed_countries' => ['AU']
                ],
                // ✅ CONFIGURAÇÃO DE TAXAS EXPLÍCITA E CENTRALIZADA
                'automatic_tax' => [
                    'enabled' => $this->config['automatic_tax']
                ],
                // ✅ EXPERIÊNCIA APRIMORADA DO USUÁRIO
                'custom_text' => [
                    'submit' => [
                        'message' => $this->buildSubmitMessage($bookingData, $pricing)
                    ]
                ],
                'phone_number_collection' => [
                    'enabled' => true
                ],
                'custom_fields' => $this->buildCustomFields($bookingData),
                'invoice_creation' => [
                    'enabled' => true,
                    'invoice_data' => [
                        'description' => $this->buildInvoiceDescription($bookingData),
                        'metadata' => $metadata,
                        'custom_fields' => [
                            [
                                'name' => 'Service Details',
                                'value' => $this->buildServiceSummary($bookingData, $pricing)
                            ],
                            [
                                'name' => 'Booking Information',
                                'value' => "Booking Code: {$bookingCode}\nScheduled: " . ($bookingData['date'] ?? 'TBD') . " at " . ($bookingData['time'] ?? 'TBD')
                            ]
                        ]
                    ]
                ]
            ];===========================
 * 
 * @file core/StripeManager.php
 * @description Gerenciador único para todas as operações Stripe
 * @version 1.0 - UNIFIED STRIPE
 * @date 2025-08-11
 * 
 * ELIMINA REDUNDÂNCIAS:
 * - 8 APIs Stripe diferentes
 * - 7 padrões de inicialização diferentes
 * - Configurações espalhadas em 5 locais
 * - Metadata inconsistente
 */

require_once __DIR__ . '/../config/stripe-enterprise.php';
require_once __DIR__ . '/PricingEngine.php';

class StripeManager 
{
    private static $instance = null;
    private $initialized = false;
    private $stripe = null;
    private $config = [];

    /**
     * Singleton pattern - uma única instância
     */
    public static function getInstance() 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado - força uso do singleton
     */
    private function __construct() 
    {
        $this->loadConfig();
        $this->initializeStripe();
    }

    /**
     * FONTE ÚNICA DE CONFIGURAÇÃO
     */
    private function loadConfig() 
    {
        $this->config = [
            'secret_key' => STRIPE_SECRET_KEY,
            'publishable_key' => STRIPE_PUBLISHABLE_KEY,
            'webhook_secret' => STRIPE_WEBHOOK_SECRET,
            'currency' => strtolower(STRIPE_CURRENCY),
            'success_url' => STRIPE_SUCCESS_URL,
            'cancel_url' => STRIPE_CANCEL_URL,
            // ✅ CONFIGURAÇÃO DE TAXAS CENTRALIZADA
            'automatic_tax' => false, // DEFINIDO: Não aplicar GST automaticamente
            'tax_behavior' => 'exclusive', // Preços são exclusive de tax
            'country_code' => 'AU' // País para cálculos de tax se necessário
        ];

        // Validar configuração
        if (empty($this->config['secret_key'])) {
            error_log("⚠️ StripeManager: Secret key not configured");
            return false;
        }

        error_log("✅ StripeManager: Configuration loaded successfully");
        error_log("📋 StripeManager: Tax policy - automatic_tax: " . ($this->config['automatic_tax'] ? 'enabled' : 'disabled'));
        return true;
    }

    /**
     * INICIALIZAÇÃO ÚNICA E PADRONIZADA
     */
    private function initializeStripe() 
    {
        try {
            // Verificar se Stripe SDK está disponível
            if (!class_exists('\Stripe\Stripe')) {
                // Tentar carregar via autoload
                if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                    require_once __DIR__ . '/../vendor/autoload.php';
                } else {
                    throw new Exception('Stripe SDK not found. Run: composer require stripe/stripe-php');
                }
            }

            // Configurar Stripe - MÉTODO ÚNICO
            \Stripe\Stripe::setApiKey($this->config['secret_key']);
            \Stripe\Stripe::setApiVersion('2023-10-16');

            $this->initialized = true;
            error_log("✅ StripeManager: Initialized successfully");

        } catch (Exception $e) {
            error_log("❌ StripeManager initialization failed: " . $e->getMessage());
            $this->initialized = false;
        }
    }

    /**
     * MÉTODO ÚNICO PARA CRIAR CHECKOUT SESSION
     * 
     * @param array $bookingData Dados do booking
     * @return array Resultado da operação
     */
    public function createCheckoutSession($bookingData) 
    {
        if (!$this->initialized) {
            throw new Exception('StripeManager not initialized');
        }

        error_log("💳 StripeManager: Creating checkout session");

        try {
            // 1. USE FRONTEND TOTAL IF PROVIDED (FIXES PRICE DISCREPANCY)
            if (isset($bookingData['frontend_total']) && $bookingData['frontend_total'] > 0) {
                error_log("💰 StripeManager: Using frontend-calculated total: $" . $bookingData['frontend_total']);
                
                // Create simplified pricing structure with frontend total
                $pricing = [
                    'base_price' => $bookingData['frontend_total'],
                    'extras_price' => 0.00,
                    'subtotal' => $bookingData['frontend_total'],
                    'total_discount' => 0.00,
                    'final_amount' => $bookingData['frontend_total'],
                    'stripe_amount_cents' => intval($bookingData['frontend_total'] * 100),
                    'currency' => 'AUD',
                    'source' => 'frontend_calculated'
                ];
            } else {
                // Fallback: Calculate using PricingEngine (old behavior)
                error_log("⚠️ StripeManager: No frontend total provided, using PricingEngine");
                $pricing = PricingEngine::calculate(
                    $bookingData['service_id'] ?? '2',
                    $bookingData['extras'] ?? [],
                    $bookingData['recurrence'] ?? 'one-time',
                    $bookingData['discount_amount'] ?? 0,
                    $bookingData['coupon_code'] ?? '',
                    $bookingData['email'] ?? ''
                );
            }

            // 2. GERAR BOOKING CODE ÚNICO
            $bookingCode = 'BCS-' . strtoupper(uniqid());

            // 3. CONSTRUIR METADATA PADRONIZADA
            $metadata = $this->buildStandardMetadata($bookingData, $pricing, $bookingCode);

            // 4. CONSTRUIR LINE ITEMS COM CLAREZA DE ASSINATURA
            $lineItems = $this->buildLineItems($pricing, $bookingData);

            // 5. DETERMINAR MODO BASEADO NA RECORRÊNCIA
            $recurrence = $bookingData['recurrence'] ?? 'one-time';
            $isSubscription = ($recurrence !== 'one-time');
            $mode = $isSubscription ? 'subscription' : 'payment';

            // 6. CRIAR SESSÃO STRIPE - MODO DINÂMICO BASEADO EM RECORRÊNCIA
            $sessionParams = [
                'payment_method_types' => ['card'],
                'line_items' => $this->buildLineItemsForMode($pricing, $bookingData, $mode),
                'mode' => $mode,
                'success_url' => $this->config['success_url'] . '?session_id={CHECKOUT_SESSION_ID}&booking_code=' . $bookingCode,
                'cancel_url' => $this->config['cancel_url'],
                'metadata' => $metadata,
                'customer_email' => $bookingData['email'] ?? null,
                'allow_promotion_codes' => false,
                'billing_address_collection' => 'required',
                'shipping_address_collection' => [
                    'allowed_countries' => ['AU']
                ],
                // ✅ CONFIGURAÇÃO DE TAXAS EXPLÍCITA E CENTRALIZADA
                'automatic_tax' => [
                    'enabled' => $this->config['automatic_tax']
                ],
                // ✅ TEXTO CUSTOMIZADO PARA CLAREZA DE ASSINATURA
                'custom_text' => $this->buildCustomText($bookingData)
            ];

            // ✅ INVOICE CREATION APENAS PARA PAYMENT MODE (Stripe cria automaticamente para subscription)
            if ($mode === 'payment') {
                $sessionParams['invoice_creation'] = [
                    'enabled' => true,
                    'invoice_data' => [
                        'description' => $this->buildInvoiceDescription($bookingData),
                        'footer' => $this->buildInvoiceFooter($bookingData)
                    ]
                ];
            }

            $session = \Stripe\Checkout\Session::create($sessionParams);

            // 6. SALVAR NO BANCO - MÉTODO ÚNICO
            $this->saveBookingRecord($bookingData, $pricing, $session, $bookingCode);

            error_log("✅ StripeManager: Session created successfully - {$session->id}");

            return [
                'success' => true,
                'session_id' => $session->id,
                'checkout_url' => $session->url,
                'booking_code' => $bookingCode,
                'pricing' => $pricing,
                'metadata' => $metadata
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("❌ Stripe API Error: " . $e->getMessage());
            throw new Exception("Stripe payment processing failed: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("❌ StripeManager Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * METADATA PADRONIZADA - FONTE ÚNICA
     */
    private function buildStandardMetadata($bookingData, $pricing, $bookingCode) 
    {
        return [
            'booking_code' => $bookingCode,
            'service_id' => $bookingData['service_id'] ?? '2',
            'customer_name' => $bookingData['name'] ?? 'Unknown',
            'customer_email' => $bookingData['email'] ?? 'unknown@email.com',
            'customer_phone' => $bookingData['phone'] ?? '',
            'address' => $bookingData['address'] ?? '',
            'suburb' => $bookingData['suburb'] ?? '',
            'postcode' => $bookingData['postcode'] ?? '',
            'schedule_date' => $bookingData['date'] ?? '',
            'schedule_time' => $bookingData['time'] ?? '',
            'recurrence' => $bookingData['recurrence'] ?? 'one-time',
            'extras' => json_encode($bookingData['extras'] ?? []),
            'base_price' => $pricing['base_price'],
            'extras_price' => $pricing['extras_price'],
            'total_discount' => $pricing['total_discount'],
            'final_amount' => $pricing['final_amount'],
            'created_at' => date('Y-m-d H:i:s'),
            'source' => 'booking_system_v3'
        ];
    }

    /**
     * LINE ITEMS PADRONIZADOS COM CLAREZA DE ASSINATURA
     */
    private function buildLineItems($pricing, $bookingData = []) 
    {
        $recurrence = $bookingData['recurrence'] ?? 'one-time';
        $isSubscription = ($recurrence !== 'one-time');
        
        // Nome do produto com clareza de assinatura
        $productName = $isSubscription 
            ? '🔄 Weekly Cleaning Subscription' 
            : 'Professional Cleaning Service';
            
        // Descrição detalhada com informações de cobrança
        $description = $this->buildSubscriptionDescription($pricing, $bookingData, $isSubscription);
        
        return [[
            'price_data' => [
                'currency' => $this->config['currency'],
                'product_data' => [
                    'name' => $productName,
                    'description' => $description
                ],
                'unit_amount' => $pricing['stripe_amount_cents'],
                // ✅ CONFIGURAÇÃO EXPLÍCITA DE COMPORTAMENTO DE TAX
                'tax_behavior' => $this->config['tax_behavior'] // exclusive = preço não inclui tax
            ],
            'quantity' => 1
        ]];
    }

    /**
     * LINE ITEMS PADRONIZADOS BASEADOS NO MODO (PAYMENT vs SUBSCRIPTION)
     */
    private function buildLineItemsForMode($pricing, $bookingData = [], $mode = 'payment') 
    {
        $recurrence = $bookingData['recurrence'] ?? 'one-time';
        $isSubscription = ($recurrence !== 'one-time');
        
        // Nome do produto com clareza de assinatura
        $productName = $isSubscription 
            ? '🔄 Weekly Cleaning Subscription' 
            : 'Professional Cleaning Service';
            
        // Descrição detalhada com informações de cobrança
        $description = $this->buildSubscriptionDescription($pricing, $bookingData, $isSubscription);
        
        if ($mode === 'subscription') {
            // Para SUBSCRIPTION mode, precisa de price com recurring
            $interval = $this->getStripeInterval($recurrence);
            $intervalCount = ($recurrence === 'fortnightly') ? 2 : 1;
            
            return [[
                'price_data' => [
                    'currency' => $this->config['currency'],
                    'product_data' => [
                        'name' => $productName,
                        'description' => $description
                    ],
                    'unit_amount' => $pricing['stripe_amount_cents'],
                    'recurring' => [
                        'interval' => $interval,
                        'interval_count' => $intervalCount
                    ],
                    'tax_behavior' => $this->config['tax_behavior']
                ],
                'quantity' => 1
            ]];
        } else {
            // Para PAYMENT mode (one-time)
            return [[
                'price_data' => [
                    'currency' => $this->config['currency'],
                    'product_data' => [
                        'name' => $productName,
                        'description' => $description
                    ],
                    'unit_amount' => $pricing['stripe_amount_cents'],
                    'tax_behavior' => $this->config['tax_behavior']
                ],
                'quantity' => 1
            ]];
        }
    }

    /**
     * CONVERTER RECORRÊNCIA PARA INTERVALO STRIPE
     */
    private function getStripeInterval($recurrence) 
    {
        $intervals = [
            'weekly' => 'week',
            'fortnightly' => 'week', // interval_count: 2 para quinzenal
            'monthly' => 'month'
        ];
        
        return $intervals[$recurrence] ?? 'month';
    }

    /**
     * TEXTO CUSTOMIZADO PARA CLAREZA DE ASSINATURA
     */
    private function buildCustomText($bookingData) 
    {
        $recurrence = $bookingData['recurrence'] ?? 'one-time';
        $isSubscription = ($recurrence !== 'one-time');
        $serviceDate = $bookingData['date'] ?? 'TBD';
        $firstBillingDate = $this->calculateFirstBillingDate($serviceDate);
        
        if ($isSubscription) {
            return [
                'submit' => [
                    'message' => '⚡ You are subscribing to a WEEKLY cleaning service. You will be charged $97.65 automatically 48 HOURS BEFORE each service until you cancel. First billing: ' . $firstBillingDate . ' (48h before your first service).'
                ],
                'after_submit' => [
                    'message' => '✅ Subscription activated! You will be charged 48h before each service. First billing: ' . $firstBillingDate . '. Next billing: every ' . $this->getRecurrenceDisplayName($recurrence) . ' (always 48h before service). You can cancel anytime.'
                ]
            ];
        } else {
            return [
                'submit' => [
                    'message' => '✅ Complete your one-time cleaning service payment.'
                ]
            ];
        }
    }

    /**
     * DESCRIÇÃO DETALHADA PARA INVOICE
     */
    private function buildInvoiceDescription($bookingData) 
    {
        $recurrence = $bookingData['recurrence'] ?? 'one-time';
        $isSubscription = ($recurrence !== 'one-time');
        
        if ($isSubscription) {
            return '⚡ WEEKLY CLEANING SUBSCRIPTION (48H BILLING) - Service Date: ' . ($bookingData['date'] ?? 'TBD') . 
                   ' | Billing: 48h before service | Address: ' . ($bookingData['address'] ?? 'TBD') . 
                   ' | Duration: ' . ($bookingData['duration'] ?? '3.5') . ' hours';
        } else {
            return '🏠 ONE-TIME CLEANING SERVICE - Service Date: ' . ($bookingData['date'] ?? 'TBD') . 
                   ' | Address: ' . ($bookingData['address'] ?? 'TBD') . 
                   ' | Duration: ' . ($bookingData['duration'] ?? '3.5') . ' hours';
        }
    }

    /**
     * FOOTER PARA INVOICE COM INFORMAÇÕES DE ASSINATURA
     */
    private function buildInvoiceFooter($bookingData) 
    {
        $recurrence = $bookingData['recurrence'] ?? 'one-time';
        $isSubscription = ($recurrence !== 'one-time');
        
        if ($isSubscription) {
            return '⚡ RECURRING SUBSCRIPTION (48H BILLING): You will be charged automatically 48 HOURS BEFORE each ' . 
                   $this->getRecurrenceDisplayName($recurrence) . 
                   ' service until cancelled. This gives you time to update payment or reschedule. Manage your subscription at our customer portal.';
        } else {
            return '✅ One-time payment completed. Thank you for choosing our cleaning services!';
        }
    }

    /**
     * DESCRIÇÃO CLARA PARA ASSINATURAS E PAGAMENTOS ÚNICOS
     */
    private function buildSubscriptionDescription($pricing, $bookingData, $isSubscription) 
    {
        $recurrence = $bookingData['recurrence'] ?? 'one-time';
        $serviceDate = $bookingData['date'] ?? 'To be scheduled';
        $firstBillingDate = $this->calculateFirstBillingDate($serviceDate);
        $nextBillingDate = $this->calculateNextBillingDate($serviceDate, $recurrence);
        
        if ($isSubscription) {
            $description = "🔄 RECURRING SUBSCRIPTION (48H BILLING)\n";
            $description .= "📅 First service: " . $serviceDate . "\n";
            $description .= "💳 First billing: " . $firstBillingDate . " (48h before first service)\n";
            $description .= "💳 Next billing: " . $nextBillingDate . " (48h before next service)\n";
            $description .= "⏰ Billing cycle: Every " . $this->getRecurrenceDisplayName($recurrence) . " (always 48h before service)\n";
            $description .= "💰 Amount per cycle: $" . number_format($pricing['final_amount'], 2) . "\n";
            
            if ($pricing['total_discount'] > 0) {
                $description .= "🎯 Weekly discount: -$" . number_format($pricing['total_discount'], 2) . "\n";
            }
            
            $description .= "\n⚡ You will be charged 48h before each service";
            $description .= "\n📧 Billing reminders will be sent 96h before each service (4 days)";
            $description .= "\n🛡️ This gives you time to update payment or reschedule if needed";
            
        } else {
            $description = "🏠 ONE-TIME CLEANING SERVICE\n";
            $description .= "📅 Service date: " . $serviceDate . "\n";
            $description .= "💰 Total amount: $" . number_format($pricing['final_amount'], 2) . "\n";
            $description .= "✅ Single payment - no recurring charges";
        }
        
        return $description;
    }

    /**
     * CALCULAR PRÓXIMA DATA DE COBRANÇA - SISTEMA 48H
     */
    private function calculateNextBillingDate($serviceDate, $recurrence) 
    {
        if ($recurrence === 'one-time') {
            return 'N/A';
        }
        
        try {
            $date = new DateTime($serviceDate);
            
            // ⚡ COBRANÇA 48H ANTES DO SERVIÇO
            $date->modify('-48 hours');
            
            switch ($recurrence) {
                case 'weekly':
                    $date->modify('+1 week');
                    break;
                case 'fortnightly':
                    $date->modify('+2 weeks');
                    break;
                case 'monthly':
                    $date->modify('+1 month');
                    break;
                default:
                    return 'To be determined';
            }
            
            return $date->format('M j, Y');
            
        } catch (Exception $e) {
            return 'To be determined';
        }
    }

    /**
     * CALCULAR DATA DA PRIMEIRA COBRANÇA (48H ANTES DO PRIMEIRO SERVIÇO)
     */
    private function calculateFirstBillingDate($serviceDate) 
    {
        try {
            $date = new DateTime($serviceDate);
            // ⚡ PRIMEIRA COBRANÇA 48H ANTES DO PRIMEIRO SERVIÇO
            $date->modify('-48 hours');
            return $date->format('M j, Y');
        } catch (Exception $e) {
            return 'To be determined';
        }
    }

    /**
     * NOME AMIGÁVEL DA RECORRÊNCIA
     */
    private function getRecurrenceDisplayName($recurrence) 
    {
        $names = [
            'weekly' => 'week',
            'fortnightly' => '2 weeks',
            'monthly' => 'month'
        ];
        
        return $names[$recurrence] ?? $recurrence;
    }

    /**
     * SALVAR BOOKING - MÉTODO ÚNICO
     */
    private function saveBookingRecord($bookingData, $pricing, $session, $bookingCode) 
    {
        // Carregar configurações do .env se não estiverem definidas como constantes
        $dbHost = defined('DB_HOST') ? DB_HOST : ($_ENV['DB_HOST'] ?? null);
        $dbName = defined('DB_NAME') ? DB_NAME : ($_ENV['DB_DATABASE'] ?? null);
        $dbUser = defined('DB_USER') ? DB_USER : ($_ENV['DB_USERNAME'] ?? null);
        $dbPass = defined('DB_PASS') ? DB_PASS : ($_ENV['DB_PASSWORD'] ?? null);
        
        // Verificar se dados de DB estão disponíveis
        if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($dbPass)) {
            error_log("⚠️ Database configuration not found - checking .env file...");
            
            // Tentar carregar .env se ainda não foi carregado
            if (file_exists(__DIR__ . '/../.env')) {
                $envContent = file_get_contents(__DIR__ . '/../.env');
                $envLines = explode("\n", $envContent);
                
                foreach ($envLines as $line) {
                    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value, '"\'');
                        $_ENV[$key] = $value;
                    }
                }
                
                // Tentar novamente após carregar .env
                $dbHost = $_ENV['DB_HOST'] ?? null;
                $dbName = $_ENV['DB_DATABASE'] ?? null;
                $dbUser = $_ENV['DB_USERNAME'] ?? null;
                $dbPass = $_ENV['DB_PASSWORD'] ?? null;
            }
            
            if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($dbPass)) {
                error_log("❌ Database configuration missing even after loading .env - skipping booking record save");
                return;
            }
            
            error_log("✅ Database configuration loaded from .env");
        }
        
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $sql = "INSERT INTO bookings (
                        booking_code, service_id, customer_name, customer_email, 
                        customer_phone, address, street_address, suburb, postcode, 
                        scheduled_date, scheduled_time, special_instructions,
                        base_price, extras_price, discount_amount, total_amount, 
                        stripe_session_id, status, referral_code
                    ) VALUES (
                        :booking_code, :service_id, :customer_name, :customer_email,
                        :customer_phone, :address, :street_address, :suburb, :postcode,
                        :scheduled_date, :scheduled_time, :special_instructions,
                        :base_price, :extras_price, :discount_amount, :total_amount,
                        :stripe_session_id, :status, :referral_code
                    )";

            $stmt = $pdo->prepare($sql);
            
            // DEBUG: Log dos dados críticos para investigar problemas
            error_log("🔍 StripeManager DEBUG - Critical fields:");
            error_log("   - scheduled_date: '" . ($bookingData['date'] ?? 'EMPTY') . "'");
            error_log("   - scheduled_time: '" . ($bookingData['time'] ?? 'EMPTY') . "'");
            error_log("   - referral_code: '" . ($bookingData['referral_code'] ?? 'EMPTY') . "'");
            error_log("   - street_address: '" . ($bookingData['address'] ?? 'EMPTY') . "'");
            error_log("🔍 StripeManager DEBUG - Full bookingData keys: " . implode(', ', array_keys($bookingData)));
            
            $finalReferralCode = $bookingData['referral_code'] ?? '';
            error_log("🎯 StripeManager - FINAL referral_code to be inserted: '$finalReferralCode'");
            
            $stmt->execute([
                ':booking_code' => $bookingCode,
                ':service_id' => $bookingData['service_id'] ?? '2',
                ':customer_name' => $bookingData['name'] ?? 'Unknown',
                ':customer_email' => $bookingData['email'] ?? 'unknown@email.com',
                ':customer_phone' => $bookingData['phone'] ?? '',
                ':address' => $bookingData['address'] ?? '',
                ':street_address' => $bookingData['address'] ?? '',
                ':suburb' => $bookingData['suburb'] ?? '',
                ':postcode' => $bookingData['postcode'] ?? '',
                ':scheduled_date' => $bookingData['date'] ?? '',
                ':scheduled_time' => $bookingData['time'] ?? '',
                ':special_instructions' => $bookingData['special_requests'] ?? '',
                ':base_price' => $pricing['base_price'],
                ':extras_price' => $pricing['extras_price'],
                ':discount_amount' => $pricing['total_discount'],
                ':total_amount' => $pricing['final_amount'],
                ':stripe_session_id' => $session->id,
                ':status' => 'pending',
                ':referral_code' => $bookingData['referral_code'] ?? ''
            ]);

            error_log("✅ StripeManager: Booking record saved - $bookingCode");
            
            // REGISTRAR USO DO CUPOM SE APLICADO
            $this->registerCouponUsage($bookingData, $pricing, $bookingCode, $pdo);

        } catch (PDOException $e) {
            error_log("❌ Database error: " . $e->getMessage());
            throw new Exception("Failed to save booking record");
        }
    }

    /**
     * REGISTRAR USO DO CUPOM
     */
    private function registerCouponUsage($bookingData, $pricing, $bookingCode, $pdo = null) 
    {
        $couponCode = $pricing['coupon_code'] ?? '';
        $couponDiscount = $pricing['coupon_discount'] ?? 0;
        
        // Só registrar se houve cupom aplicado com desconto
        if (empty($couponCode) || $couponDiscount <= 0) {
            return;
        }
        
        try {
            // Load CouponManager if not already loaded
            if (!class_exists('CouponManager')) {
                require_once __DIR__ . '/CouponManager.php';
            }
            
            $couponManager = createCouponManager(false);
            $customerEmail = $bookingData['email'] ?? '';
            $subtotal = $pricing['subtotal'] ?? 0;
            
            $success = $couponManager->registerUsage(
                $couponCode, 
                $customerEmail, 
                $bookingCode, 
                $couponDiscount, 
                $subtotal
            );
            
            if ($success) {
                error_log("🎫 StripeManager: Cupom '$couponCode' uso registrado para $customerEmail (desconto: $$couponDiscount)");
            } else {
                error_log("⚠️ StripeManager: Falha ao registrar uso do cupom '$couponCode'");
            }
            
        } catch (Exception $e) {
            error_log("❌ StripeManager: Erro ao registrar cupom '$couponCode': " . $e->getMessage());
        }
    }

    /**
     * VERIFICAR STATUS DA CONFIGURAÇÃO
     */
    public function isConfigured() 
    {
        return $this->initialized && !empty($this->config['secret_key']);
    }

    /**
     * OBTER CONFIGURAÇÃO PÚBLICA (para frontend)
     */
    public function getPublicConfig() 
    {
        return [
            'publishable_key' => $this->config['publishable_key'],
            'currency' => $this->config['currency'],
            'configured' => $this->isConfigured(),
            // ✅ INFORMAÇÕES DE TAXAS PARA DEBUG/FRONTEND
            'tax_config' => [
                'automatic_tax' => $this->config['automatic_tax'],
                'tax_behavior' => $this->config['tax_behavior'],
                'country_code' => $this->config['country_code']
            ]
        ];
    }

    /**
     * ✅ MÉTODO PARA VERIFICAR CONFIGURAÇÃO DE TAXAS
     */
    public function getTaxConfig() 
    {
        return [
            'automatic_tax_enabled' => $this->config['automatic_tax'],
            'tax_behavior' => $this->config['tax_behavior'],
            'country_code' => $this->config['country_code'],
            'policy_description' => $this->config['automatic_tax'] 
                ? 'Stripe will calculate and apply taxes automatically'
                : 'Prices are final - no additional taxes applied'
        ];
    }

    /**
     * RECUPERAR SESSÃO STRIPE
     */
    public function retrieveSession($sessionId) 
    {
        if (!$this->initialized) {
            throw new Exception('StripeManager not initialized');
        }

        try {
            return \Stripe\Checkout\Session::retrieve($sessionId);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("❌ Failed to retrieve session: " . $e->getMessage());
            return null;
        }
    }

    /**
     * PROCESSAR WEBHOOK - MÉTODO ÚNICO
     */
    public function processWebhook($payload, $signature) 
    {
        if (!$this->initialized) {
            throw new Exception('StripeManager not initialized');
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->config['webhook_secret']
            );

            error_log("📨 StripeManager: Processing webhook - " . $event->type);

            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event->data->object);
                    break;
                
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;
                
                default:
                    error_log("🔄 StripeManager: Unhandled webhook type - " . $event->type);
            }

            return ['success' => true];
        } catch (Exception $e) {
            error_log("❌ StripeManager Webhook Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * =========================================================
     * MÉTODOS PARA EXPERIÊNCIA APRIMORADA DO USUÁRIO
     * =========================================================
     */
    
    /**
     * Constrói mensagem de confirmação personalizada
     */
    private function buildSubmitMessage($bookingData, $pricing) 
    {
        $recurrence = $bookingData['recurrence'] ?? 'one-time';
        $nextPayment = '';
        
        // Explicar lógica de recorrência
        switch ($recurrence) {
            case 'weekly':
                $nextPayment = 'Next payment will be charged automatically in 1 week.';
                break;
            case 'fortnightly':
                $nextPayment = 'Next payment will be charged automatically in 2 weeks.';
                break;
            case 'monthly':
                $nextPayment = 'Next payment will be charged automatically in 1 month.';
                break;
            case 'one-time':
            default:
                $nextPayment = 'This is a one-time payment. No future charges will occur.';
                break;
        }
        
        return "You're about to confirm a {$pricing['currency']} " . number_format($pricing['final_amount'], 2) . " payment for your cleaning service. {$nextPayment}";
    }
    
    /**
     * Constrói campos customizados para experiência aprimorada
     */
    private function buildCustomFields($bookingData) 
    {
        $fields = [];
        
        // Campo para instruções especiais
        $fields[] = [
            'key' => 'special_instructions',
            'label' => [
                'type' => 'custom',
                'custom' => 'Special Instructions for Our Team'
            ],
            'type' => 'text',
            'text' => [
                'maximum_length' => 500,
                'minimum_length' => 0
            ],
            'optional' => true
        ];
        
        // Campo para confirmação de acesso à propriedade
        $fields[] = [
            'key' => 'property_access',
            'label' => [
                'type' => 'custom', 
                'custom' => 'How should our team access your property?'
            ],
            'type' => 'dropdown',
            'dropdown' => [
                'options' => [
                    [
                        'label' => 'I will be home to let the team in',
                        'value' => 'homeowner_present'
                    ],
                    [
                        'label' => 'Key under mat/pot (please specify in instructions)',
                        'value' => 'hidden_key'
                    ],
                    [
                        'label' => 'Lockbox with code (please specify in instructions)',
                        'value' => 'lockbox'
                    ],
                    [
                        'label' => 'Please call when you arrive',
                        'value' => 'call_on_arrival'
                    ],
                    [
                        'label' => 'Other (please specify in instructions)',
                        'value' => 'other'
                    ]
                ]
            ]
        ];
        
        return $fields;
    }
    
    /**
     * Constrói resumo detalhado do serviço
     */
    private function buildServiceSummary($bookingData, $pricing) 
    {
        $summary = "Service: " . $this->getServiceName($bookingData['service_id'] ?? 2);
        $summary .= "\nDuration: " . ($bookingData['duration'] ?? '3.5') . " hours";
        $summary .= "\nBase Price: $" . number_format($pricing['base_price'], 2);
        
        if ($pricing['extras_price'] > 0) {
            $summary .= "\nAdd-ons: $" . number_format($pricing['extras_price'], 2);
        }
        
        if ($pricing['total_discount'] > 0) {
            $summary .= "\nDiscount: -$" . number_format($pricing['total_discount'], 2);
        }
        
        $summary .= "\nTotal: $" . number_format($pricing['final_amount'], 2);
        
        $recurrence = $bookingData['recurrence'] ?? 'one-time';
        if ($recurrence !== 'one-time') {
            $summary .= "\nBilling: Every " . $this->getRecurrenceInterval($recurrence);
        }
        
        return $summary;
    }
    
    /**
     * Obtém nome do serviço baseado no ID
     */
    private function getServiceName($serviceId) 
    {
        $services = [
            1 => 'Basic Cleaning',
            2 => 'Standard Cleaning', 
            3 => 'Deep Cleaning',
            4 => 'Premium Cleaning'
        ];
        
        return $services[$serviceId] ?? 'Professional Cleaning';
    }
    
    /**
     * Obtém intervalo de recorrência em formato legível
     */
    private function getRecurrenceInterval($recurrence) 
    {
        switch ($recurrence) {
            case 'weekly': return 'week';
            case 'fortnightly': return '2 weeks';
            case 'monthly': return 'month';
            default: return 'occurrence';
        }
    }

    /**
     * LIDAR COM CHECKOUT COMPLETADO
     */
    private function handleCheckoutCompleted($session) 
    {
        error_log("✅ StripeManager: Checkout completed - " . $session->id);
        
        // Atualizar status do booking
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $sql = "UPDATE bookings SET status = 'confirmed', confirmed_at = NOW() 
                    WHERE stripe_session_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$session->id]);

            error_log("✅ StripeManager: Booking confirmed for session " . $session->id);

        } catch (PDOException $e) {
            error_log("❌ Failed to update booking status: " . $e->getMessage());
        }
    }

    /**
     * LIDAR COM PAGAMENTO SUCEDIDO
     */
    private function handlePaymentSucceeded($paymentIntent) 
    {
        error_log("💰 StripeManager: Payment succeeded - " . $paymentIntent->id);
        // Lógica adicional para pagamento confirmado
    }
}
