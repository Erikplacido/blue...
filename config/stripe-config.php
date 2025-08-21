<?php
/**
 * Configurações Stripe - Blue Project V2
 * Gerenciamento centralizado de chaves e configurações do Stripe
 */

// Configurações do Stripe
class StripeConfig {
    
    // Ambiente de desenvolvimento/produção
    private static $isProduction = false;
    
    // Chaves do Stripe (configurar com suas chaves reais)
    private static $keys = [
        'test' => [
            'publishable_key' => 'pk_test_51QZFvKIRxWlLd6ZOKzQHg1234567890abcdef',
            'secret_key' => 'sk_test_51QZFvKIRxWlLd6ZOKzQHg1234567890abcdef',
            'webhook_secret' => 'whsec_1234567890abcdef'
        ],
        'live' => [
            'publishable_key' => 'pk_live_your_live_publishable_key',
            'secret_key' => 'sk_live_your_live_secret_key',
            'webhook_secret' => 'whsec_your_live_webhook_secret'
        ]
    ];
    
    // Configurações gerais
    private static $config = [
        'currency' => 'aud',
        'automatic_payment_methods' => true,
        'capture_method' => 'automatic',
        'confirmation_method' => 'manual',
        'return_url' => null, // Definido dinamicamente
        'business_name' => 'Blue Cleaning Services',
        'support_email' => 'support@bluecleaningservices.com.au',
        'webhook_tolerance' => 300, // 5 minutos
        
        // Configurações de retry para pagamentos falhados
        'retry_config' => [
            'max_attempts' => 3,
            'retry_intervals' => [1, 3, 7], // dias
            'suspend_after_failures' => true
        ]
    ];
    
    /**
     * Obtém a chave pública do Stripe
     */
    public static function getPublishableKey() {
        $env = self::$isProduction ? 'live' : 'test';
        return self::$keys[$env]['publishable_key'];
    }
    
    /**
     * Obtém a chave secreta do Stripe
     */
    public static function getSecretKey() {
        $env = self::$isProduction ? 'live' : 'test';
        return self::$keys[$env]['secret_key'];
    }
    
    /**
     * Obtém o webhook secret
     */
    public static function getWebhookSecret() {
        $env = self::$isProduction ? 'live' : 'test';
        return self::$keys[$env]['webhook_secret'];
    }
    
    /**
     * Obtém configuração específica
     */
    public static function getConfig($key = null) {
        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? null;
    }
    
    /**
     * Define ambiente de produção
     */
    public static function setProduction($isProduction = true) {
        self::$isProduction = $isProduction;
    }
    
    /**
     * Verifica se está em produção
     */
    public static function isProduction() {
        return self::$isProduction;
    }
    
    /**
     * Inicializa Stripe com as configurações
     */
    public static function initialize() {
        if (!class_exists('\\Stripe\\Stripe')) {
            throw new Exception('Stripe PHP library not found. Run: composer require stripe/stripe-php');
        }
        
        \Stripe\Stripe::setApiKey(self::getSecretKey());
        \Stripe\Stripe::setAppInfo(
            'Blue Cleaning Services',
            '2.0',
            'https://bluecleaningservices.com.au'
        );
        
        return true;
    }
    
    /**
     * Obtém metadata padrão para requests Stripe
     */
    public static function getDefaultMetadata($booking = null) {
        $metadata = [
            'platform' => 'blue_cleaning_system',
            'version' => '2.0',
            'environment' => self::$isProduction ? 'production' : 'test',
            'created_at' => date('c')
        ];
        
        if ($booking) {
            $metadata = array_merge($metadata, [
                'booking_id' => $booking['booking_id'] ?? '',
                'service_type' => $booking['service_type'] ?? '',
                'customer_email' => $booking['customer_email'] ?? ''
            ]);
        }
        
        return $metadata;
    }
}

// Configurações específicas para diferentes tipos de produtos/serviços
class StripeProducts {
    
    private static $serviceConfigs = [
        'house-cleaning' => [
            'name' => 'House Cleaning Service',
            'statement_descriptor' => 'BLUE HOUSE CLEAN',
            'unit_label' => 'service'
        ],
        'deep-cleaning' => [
            'name' => 'Deep Cleaning Service', 
            'statement_descriptor' => 'BLUE DEEP CLEAN',
            'unit_label' => 'service'
        ],
        'office-cleaning' => [
            'name' => 'Office Cleaning Service',
            'statement_descriptor' => 'BLUE OFFICE CLEAN',
            'unit_label' => 'service'
        ],
        'carpet-cleaning' => [
            'name' => 'Carpet Cleaning Service',
            'statement_descriptor' => 'BLUE CARPET CLEAN',
            'unit_label' => 'service'
        ],
        'window-cleaning' => [
            'name' => 'Window Cleaning Service',
            'statement_descriptor' => 'BLUE WINDOW CLEAN',
            'unit_label' => 'service'
        ]
    ];
    
    public static function getServiceConfig($serviceType) {
        return self::$serviceConfigs[$serviceType] ?? self::$serviceConfigs['house-cleaning'];
    }
    
    public static function getAllServices() {
        return self::$serviceConfigs;
    }
}

/**
 * Utilitários Stripe
 */
class StripeUtils {
    
    /**
     * Converte valor para centavos (formato Stripe)
     */
    public static function toCents($amount) {
        return (int)($amount * 100);
    }
    
    /**
     * Converte centavos para valor decimal
     */
    public static function fromCents($cents) {
        return $cents / 100;
    }
    
    /**
     * Valida webhook signature
     */
    public static function validateWebhookSignature($payload, $signature, $secret) {
        try {
            return \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch(\UnexpectedValueException $e) {
            throw new Exception('Invalid payload: ' . $e->getMessage());
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            throw new Exception('Invalid signature: ' . $e->getMessage());
        }
    }
    
    /**
     * Formata erros Stripe para display
     */
    public static function formatStripeError($exception) {
        if ($exception instanceof \Stripe\Exception\CardException) {
            return [
                'type' => 'card_error',
                'code' => $exception->getError()->code,
                'message' => $exception->getError()->message,
                'user_message' => self::getUserFriendlyMessage($exception->getError()->code)
            ];
        } else if ($exception instanceof \Stripe\Exception\RateLimitException) {
            return [
                'type' => 'rate_limit',
                'message' => 'Too many requests. Please try again later.',
                'user_message' => 'System is busy. Please try again in a moment.'
            ];
        } else if ($exception instanceof \Stripe\Exception\InvalidRequestException) {
            return [
                'type' => 'invalid_request',
                'message' => $exception->getMessage(),
                'user_message' => 'Invalid request. Please check your information.'
            ];
        } else {
            return [
                'type' => 'generic_error',
                'message' => $exception->getMessage(),
                'user_message' => 'An error occurred processing your payment. Please try again.'
            ];
        }
    }
    
    /**
     * Mensagens user-friendly para códigos de erro
     */
    private static function getUserFriendlyMessage($errorCode) {
        $messages = [
            'card_declined' => 'Your card was declined. Please try a different card.',
            'expired_card' => 'Your card has expired. Please use a different card.',
            'incorrect_cvc' => 'The security code is incorrect. Please check and try again.',
            'insufficient_funds' => 'Your card has insufficient funds. Please try a different card.',
            'processing_error' => 'An error occurred processing your card. Please try again.',
            'incorrect_number' => 'Your card number is incorrect. Please check and try again.'
        ];
        
        return $messages[$errorCode] ?? 'Your card was declined. Please try a different payment method.';
    }
    
    /**
     * Gera ID único para metadata
     */
    public static function generateUniqueId($prefix = 'blue') {
        return $prefix . '_' . date('Ymd') . '_' . uniqid();
    }
}

// Auto-inicializar se incluído diretamente
if (!defined('STRIPE_CONFIG_LOADED')) {
    define('STRIPE_CONFIG_LOADED', true);
    
    // Detectar ambiente baseado em URL/configurações
    $isProduction = (
        isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production'
    ) || (
        isset($_SERVER['HTTP_HOST']) && !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])
    );
    
    StripeConfig::setProduction($isProduction);
    
    // Log de inicialização
    error_log('Stripe Config loaded - Environment: ' . ($isProduction ? 'Production' : 'Development'));
}

?>
