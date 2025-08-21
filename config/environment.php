<?php
/**
 * Gerenciador de Configurações de Ambiente - Blue Project V2
 * Sistema centralizado para gerenciar configurações por ambiente
 */

/**
 * Classe principal para gerenciamento de configurações
 */
class EnvironmentConfig {
    
    private static $config = [];
    private static $environment = null;
    private static $configFiles = [
        'development' => '.env.development',
        'staging' => '.env.staging', 
        // 'production' => '.env.production', // Arquivo removido - usar .env principal
        'testing' => '.env.testing'
    ];
    
    /**
     * Inicializar configurações
     */
    public static function init($forcedEnv = null) {
        // Detectar ambiente
        self::$environment = $forcedEnv ?? self::detectEnvironment();
        
        // Carregar configurações base
        self::loadBaseConfig();
        
        // Carregar configurações específicas do ambiente
        self::loadEnvironmentConfig();
        
        // Validar configurações obrigatórias
        self::validateRequiredConfig();
        
        // Configurar timezone
        date_default_timezone_set(self::get('app.timezone', 'Australia/Melbourne'));
        
        return true;
    }
    
    /**
     * Obter configuração
     */
    public static function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Definir configuração
     */
    public static function set($key, $value) {
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * Obter ambiente atual
     */
    public static function getEnvironment() {
        return self::$environment;
    }
    
    /**
     * Verificar se está em produção
     */
    public static function isProduction() {
        return self::$environment === 'production';
    }
    
    /**
     * Verificar se está em desenvolvimento
     */
    public static function isDevelopment() {
        return self::$environment === 'development';
    }
    
    /**
     * Verificar se está em staging
     */
    public static function isStaging() {
        return self::$environment === 'staging';
    }
    
    /**
     * Verificar se está em teste
     */
    public static function isTesting() {
        return self::$environment === 'testing';
    }
    
    /**
     * Detectar ambiente automaticamente
     */
    private static function detectEnvironment() {
        // Verificar variável de ambiente
        if ($env = getenv('APP_ENV')) {
            return $env;
        }
        
        // Verificar $_ENV
        if (isset($_ENV['APP_ENV'])) {
            return $_ENV['APP_ENV'];
        }
        
        // Verificar arquivo .env
        if (file_exists('.env')) {
            $envContent = file_get_contents('.env');
            if (preg_match('/^APP_ENV=(.+)$/m', $envContent, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // Detectar baseado no hostname/URL
        $host = $_SERVER['HTTP_HOST'] ?? gethostname();
        
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return 'development';
        } elseif (strpos($host, 'staging') !== false || strpos($host, 'test') !== false) {
            return 'staging';
        } else {
            return 'production';
        }
    }
    
    /**
     * Carregar configurações base
     */
    private static function loadBaseConfig() {
        self::$config = [
            'app' => [
                'name' => 'Blue Cleaning Services',
                'version' => '2.0.0',
                'url' => self::getAppUrl(),
                'timezone' => 'Australia/Melbourne',
                'locale' => 'en_AU',
                'currency' => 'AUD',
                'debug' => false,
                'maintenance_mode' => false
            ],
            'database' => [
                'driver' => 'mysql',
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'blue_cleaning',
                'username' => 'blue_user',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            ],
            'cache' => [
                'driver' => 'file',
                'path' => './cache',
                'ttl' => 3600,
                'prefix' => 'blue_'
            ],
            'session' => [
                'driver' => 'file',
                'path' => './sessions',
                'lifetime' => 7200,
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ],
            'mail' => [
                'driver' => 'smtp',
                'host' => '',
                'port' => 587,
                'username' => '',
                'password' => '',
                'encryption' => 'tls',
                'from' => [
                    'address' => 'noreply@bluecleaningservices.com.au',
                    'name' => 'Blue Cleaning Services'
                ]
            ],
            'stripe' => [
                'publishable_key' => '',
                'secret_key' => '',
                'webhook_secret' => '',
                'currency' => 'aud'
            ],
            'logging' => [
                'level' => 'info',
                'path' => './logs',
                'format' => 'json',
                'max_files' => 10,
                'max_size' => '10MB'
            ],
            'monitoring' => [
                'sentry_dsn' => '',
                'error_reporting' => true,
                'performance_monitoring' => true
            ],
            'security' => [
                'encryption_key' => '',
                'hash_algorithm' => 'sha256',
                'password_min_length' => 8,
                'rate_limiting' => [
                    'enabled' => true,
                    'max_attempts' => 60,
                    'decay_minutes' => 1
                ],
                'csrf_protection' => true,
                'xss_protection' => true,
                'content_security_policy' => true
            ],
            'features' => [
                'registration_enabled' => true,
                'booking_enabled' => true,
                'payments_enabled' => true,
                'notifications_enabled' => true,
                'loyalty_program_enabled' => true,
                'tracking_enabled' => true,
                'api_enabled' => true
            ],
            'services' => [
                'geocoding' => [
                    'provider' => 'google',
                    'api_key' => ''
                ],
                'sms' => [
                    'provider' => 'twilio',
                    'account_sid' => '',
                    'auth_token' => '',
                    'from_number' => ''
                ],
                'push_notifications' => [
                    'provider' => 'firebase',
                    'server_key' => '',
                    'sender_id' => ''
                ]
            ]
        ];
    }
    
    /**
     * Carregar configurações específicas do ambiente
     */
    private static function loadEnvironmentConfig() {
        $envFile = self::$configFiles[self::$environment] ?? null;
        
        // Carregar arquivo .env principal
        if (file_exists('.env')) {
            self::loadEnvFile('.env');
        }
        
        // Carregar arquivo específico do ambiente
        if ($envFile && file_exists($envFile)) {
            self::loadEnvFile($envFile);
        }
        
        // Aplicar configurações específicas por ambiente
        switch (self::$environment) {
            case 'development':
                self::applyDevelopmentConfig();
                break;
            case 'staging':
                self::applyStagingConfig();
                break;
            case 'production':
                self::applyProductionConfig();
                break;
            case 'testing':
                self::applyTestingConfig();
                break;
        }
    }
    
    /**
     * Carregar arquivo .env
     */
    private static function loadEnvFile($filePath) {
        if (!file_exists($filePath)) {
            return;
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue; // Ignorar comentários
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                // Converter valores especiais
                $value = self::convertEnvValue($value);
                
                // Mapear para configuração
                self::mapEnvToConfig($key, $value);
            }
        }
    }
    
    /**
     * Converter valores do .env
     */
    private static function convertEnvValue($value) {
        if ($value === 'true' || $value === 'TRUE') {
            return true;
        }
        if ($value === 'false' || $value === 'FALSE') {
            return false;
        }
        if ($value === 'null' || $value === 'NULL') {
            return null;
        }
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        return $value;
    }
    
    /**
     * Mapear variáveis de ambiente para configuração
     */
    private static function mapEnvToConfig($key, $value) {
        $mapping = [
            'APP_ENV' => 'app.environment',
            'APP_NAME' => 'app.name',
            'APP_URL' => 'app.url',
            'APP_DEBUG' => 'app.debug',
            'APP_TIMEZONE' => 'app.timezone',
            
            'DB_HOST' => 'database.host',
            'DB_PORT' => 'database.port',
            'DB_DATABASE' => 'database.database',
            'DB_USERNAME' => 'database.username',
            'DB_PASSWORD' => 'database.password',
            
            'CACHE_DRIVER' => 'cache.driver',
            'CACHE_PATH' => 'cache.path',
            
            'SESSION_DRIVER' => 'session.driver',
            'SESSION_LIFETIME' => 'session.lifetime',
            
            'MAIL_HOST' => 'mail.host',
            'MAIL_PORT' => 'mail.port',
            'MAIL_USERNAME' => 'mail.username',
            'MAIL_PASSWORD' => 'mail.password',
            'MAIL_ENCRYPTION' => 'mail.encryption',
            
            'STRIPE_PUBLISHABLE_KEY' => 'stripe.publishable_key',
            'STRIPE_SECRET_KEY' => 'stripe.secret_key',
            'STRIPE_WEBHOOK_SECRET' => 'stripe.webhook_secret',
            
            'SENTRY_DSN' => 'monitoring.sentry_dsn',
            
            'LOG_LEVEL' => 'logging.level',
            'LOG_PATH' => 'logging.path',
            
            'ENCRYPTION_KEY' => 'security.encryption_key'
        ];
        
        if (isset($mapping[$key])) {
            self::set($mapping[$key], $value);
        }
    }
    
    /**
     * Aplicar configurações de desenvolvimento
     */
    private static function applyDevelopmentConfig() {
        self::set('app.debug', true);
        self::set('logging.level', 'debug');
        self::set('monitoring.error_reporting', true);
        self::set('security.rate_limiting.enabled', false);
        self::set('cache.ttl', 300); // 5 minutos
    }
    
    /**
     * Aplicar configurações de staging
     */
    private static function applyStagingConfig() {
        self::set('app.debug', false);
        self::set('logging.level', 'info');
        self::set('monitoring.error_reporting', true);
        self::set('security.rate_limiting.enabled', true);
        self::set('session.secure', true);
    }
    
    /**
     * Aplicar configurações de produção
     */
    private static function applyProductionConfig() {
        self::set('app.debug', false);
        self::set('logging.level', 'warning');
        self::set('monitoring.error_reporting', true);
        self::set('monitoring.performance_monitoring', true);
        self::set('security.rate_limiting.enabled', true);
        self::set('security.csrf_protection', true);
        self::set('session.secure', true);
        self::set('session.httponly', true);
        self::set('cache.ttl', 3600); // 1 hora
    }
    
    /**
     * Aplicar configurações de teste
     */
    private static function applyTestingConfig() {
        self::set('app.debug', true);
        self::set('database.database', 'blue_cleaning_test');
        self::set('logging.level', 'error');
        self::set('monitoring.error_reporting', false);
        self::set('security.rate_limiting.enabled', false);
        self::set('cache.driver', 'memory');
        self::set('mail.driver', 'log');
    }
    
    /**
     * Obter URL da aplicação
     */
    private static function getAppUrl() {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $protocol = 'https';
        } else {
            $protocol = 'http';
        }
        
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return "{$protocol}://{$host}";
    }
    
    /**
     * Validar configurações obrigatórias
     */
    private static function validateRequiredConfig() {
        $required = [];
        
        if (self::isProduction()) {
            $required = [
                'database.host',
                'database.database', 
                'database.username',
                'database.password',
                'stripe.publishable_key',
                'stripe.secret_key',
                'security.encryption_key',
                'mail.host',
                'mail.username',
                'mail.password'
            ];
        }
        
        foreach ($required as $key) {
            if (empty(self::get($key))) {
                throw new Exception("Required configuration '{$key}' is missing for {$environment} environment");
            }
        }
    }
    
    /**
     * Obter todas as configurações (para debug)
     */
    public static function getAllConfig($hideSensitive = true) {
        $config = self::$config;
        
        if ($hideSensitive) {
            // Ocultar dados sensíveis
            $sensitive = [
                'database.password',
                'stripe.secret_key',
                'stripe.webhook_secret',
                'mail.password',
                'security.encryption_key',
                'monitoring.sentry_dsn'
            ];
            
            foreach ($sensitive as $key) {
                $keys = explode('.', $key);
                $current = &$config;
                
                foreach ($keys as $k) {
                    if (!isset($current[$k])) {
                        break;
                    }
                    if ($k === end($keys)) {
                        $current[$k] = '***HIDDEN***';
                    } else {
                        $current = &$current[$k];
                    }
                }
            }
        }
        
        return $config;
    }
    
    /**
     * Gerar template de arquivo .env
     */
    public static function generateEnvTemplate($environment = 'development') {
        $template = [
            '# Blue Cleaning Services - Environment Configuration',
            '# Environment: ' . $environment,
            '',
            '# Application',
            'APP_ENV=' . $environment,
            'APP_NAME="Blue Cleaning Services"',
            'APP_URL=http://localhost',
            'APP_DEBUG=' . ($environment === 'development' ? 'true' : 'false'),
            'APP_TIMEZONE=Australia/Melbourne',
            '',
            '# Database',
            'DB_HOST=localhost',
            'DB_PORT=3306',
            'DB_DATABASE=blue_cleaning',
            'DB_USERNAME=blue_user',
            'DB_PASSWORD=',
            '',
            '# Cache',
            'CACHE_DRIVER=file',
            'CACHE_PATH=./cache',
            '',
            '# Mail',
            'MAIL_HOST=',
            'MAIL_PORT=587',
            'MAIL_USERNAME=',
            'MAIL_PASSWORD=',
            'MAIL_ENCRYPTION=tls',
            '',
            '# Stripe',
            'STRIPE_PUBLISHABLE_KEY=',
            'STRIPE_SECRET_KEY=',
            'STRIPE_WEBHOOK_SECRET=',
            '',
            '# Monitoring',
            'SENTRY_DSN=',
            '',
            '# Security',
            'ENCRYPTION_KEY='
        ];
        
        return implode(PHP_EOL, $template);
    }
}

// Auto-inicializar se incluído diretamente
if (!defined('ENV_CONFIG_LOADED')) {
    define('ENV_CONFIG_LOADED', true);
    EnvironmentConfig::init();
}

?>
