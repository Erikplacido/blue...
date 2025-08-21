<?php
/**
 * Australian Environment Configuration Manager
 * Blue Cleaning Services - Standardized Configuration System
 * 
 * This class provides unified access to environment variables with
 * Australian-specific defaults and validation.
 * 
 * @author Blue Cleaning Development Team
 * @version 2.0.0
 * @created 07/08/2025
 */

class AustralianEnvironmentConfig {
    private static $config = null;
    private static $loaded = false;
    
    /**
     * Load environment configuration
     */
    public static function load($envFile = '.env.australia') {
        if (self::$loaded) {
            return;
        }
        
        $envPath = __DIR__ . '/../' . $envFile;
        
        // Fallback to .env if australia file doesn't exist
        if (!file_exists($envPath)) {
            $envPath = __DIR__ . '/../.env';
        }
        
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Skip comments
                }
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, '"\'');
                    
                    if (!empty($key)) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }
        
        // Set Australian timezone
        date_default_timezone_set(self::get('APP_TIMEZONE', 'Australia/Sydney'));
        
        self::$loaded = true;
    }
    
    /**
     * Get configuration value with Australian defaults
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        // Load config if not loaded
        if (!self::$loaded) {
            self::load();
        }
        
        // Check environment first
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value !== false && $value !== null && $value !== '') {
            // Convert string booleans
            if (in_array(strtolower($value), ['true', 'false'])) {
                return strtolower($value) === 'true';
            }
            
            // Convert numeric strings
            if (is_numeric($value)) {
                return strpos($value, '.') !== false ? (float)$value : (int)$value;
            }
            
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Get database configuration
     * 
     * @return array Database configuration
     */
    public static function getDatabase() {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'port' => self::get('DB_PORT', 3306),
            'database' => self::get('DB_DATABASE', 'blue_cleaning_au'),
            'username' => self::get('DB_USERNAME'),
            'password' => self::get('DB_PASSWORD'),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
            'collation' => self::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'timezone' => '+10:00' // Australian Eastern Standard Time
        ];
    }
    
    /**
     * Get Australian regional settings
     * 
     * @return array Regional configuration
     */
    public static function getRegionalSettings() {
        return [
            'currency' => self::get('CURRENCY', 'AUD'),
            'currency_symbol' => self::get('CURRENCY_SYMBOL', '$'),
            'date_format' => self::get('DATE_FORMAT', 'd/m/Y'),
            'time_format' => self::get('TIME_FORMAT', 'H:i'),
            'datetime_format' => self::get('DATETIME_FORMAT', 'd/m/Y H:i'),
            'timezone' => self::get('TIMEZONE', 'Australia/Sydney'),
            'locale' => self::get('APP_LOCALE', 'en_AU'),
            'first_day_of_week' => self::get('FIRST_DAY_OF_WEEK', 1) // Monday
        ];
    }
    
    /**
     * Get Stripe payment configuration
     * 
     * @return array Stripe configuration
     */
    public static function getStripeConfig() {
        return [
            'publishable_key' => self::get('STRIPE_PUBLISHABLE_KEY'),
            'secret_key' => self::get('STRIPE_SECRET_KEY'),
            'webhook_secret' => self::get('STRIPE_WEBHOOK_SECRET'),
            'currency' => self::get('STRIPE_CURRENCY', 'AUD'),
            'country' => self::get('STRIPE_COUNTRY', 'AU')
        ];
    }
    
    /**
     * Get email configuration
     * 
     * @return array Email configuration
     */
    public static function getEmailConfig() {
        return [
            'mailer' => self::get('MAIL_MAILER', 'smtp'),
            'host' => self::get('MAIL_HOST'),
            'port' => self::get('MAIL_PORT', 587),
            'username' => self::get('MAIL_USERNAME'),
            'password' => self::get('MAIL_PASSWORD'),
            'encryption' => self::get('MAIL_ENCRYPTION', 'tls'),
            'from_address' => self::get('MAIL_FROM_ADDRESS'),
            'from_name' => self::get('MAIL_FROM_NAME'),
            'reply_to' => self::get('MAIL_REPLY_TO')
        ];
    }
    
    /**
     * Get SMS configuration
     * 
     * @return array SMS configuration
     */
    public static function getSMSConfig() {
        return [
            'provider' => self::get('SMS_PROVIDER', 'twilio'),
            'account_sid' => self::get('SMS_ACCOUNT_SID'),
            'auth_token' => self::get('SMS_AUTH_TOKEN'),
            'from_number' => self::get('SMS_FROM_NUMBER'),
            'webhook_url' => self::get('SMS_WEBHOOK_URL')
        ];
    }
    
    /**
     * Get business information
     * 
     * @return array Business configuration
     */
    public static function getBusinessInfo() {
        return [
            'name' => self::get('BUSINESS_NAME', 'Blue Cleaning Services Pty Ltd'),
            'abn' => self::get('BUSINESS_ABN'),
            'acn' => self::get('BUSINESS_ACN'),
            'phone' => self::get('BUSINESS_PHONE'),
            'email' => self::get('BUSINESS_EMAIL'),
            'address' => self::get('BUSINESS_ADDRESS')
        ];
    }
    
    /**
     * Get Redis configuration
     * 
     * @return array Redis configuration
     */
    public static function getRedisConfig() {
        return [
            'host' => self::get('REDIS_HOST', '127.0.0.1'),
            'port' => self::get('REDIS_PORT', 6379),
            'password' => self::get('REDIS_PASSWORD'),
            'database' => self::get('REDIS_DATABASE', 0)
        ];
    }
    
    /**
     * Check if we're in production environment
     * 
     * @return bool
     */
    public static function isProduction() {
        return self::get('APP_ENV') === 'production';
    }
    
    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    public static function isDebugMode() {
        return self::get('APP_DEBUG', false);
    }
    
    /**
     * Get application URL
     * 
     * @return string
     */
    public static function getAppUrl() {
        return self::get('APP_URL', 'https://bluecleaning.com.au');
    }
    
    /**
     * Format currency amount for Australian display
     * 
     * @param float $amount
     * @return string
     */
    public static function formatCurrency($amount) {
        $symbol = self::get('CURRENCY_SYMBOL', '$');
        return $symbol . number_format($amount, 2, '.', ',');
    }
    
    /**
     * Format date for Australian display
     * 
     * @param string|DateTime $date
     * @return string
     */
    public static function formatDate($date) {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        
        $format = self::get('DATE_FORMAT', 'd/m/Y');
        return $date->format($format);
    }
    
    /**
     * Format datetime for Australian display
     * 
     * @param string|DateTime $datetime
     * @return string
     */
    public static function formatDateTime($datetime) {
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }
        
        $format = self::get('DATETIME_FORMAT', 'd/m/Y H:i');
        return $datetime->format($format);
    }
    
    /**
     * Get all configuration as array (for debugging)
     * 
     * @return array
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        // Filter sensitive data in production
        $sensitiveKeys = ['DB_PASSWORD', 'STRIPE_SECRET_KEY', 'MAIL_PASSWORD', 'SMS_AUTH_TOKEN'];
        $config = [];
        
        foreach ($_ENV as $key => $value) {
            if (self::isProduction() && in_array($key, $sensitiveKeys)) {
                $config[$key] = '***HIDDEN***';
            } else {
                $config[$key] = $value;
            }
        }
        
        return $config;
    }
}

// Auto-load configuration
AustralianEnvironmentConfig::load();
?>
