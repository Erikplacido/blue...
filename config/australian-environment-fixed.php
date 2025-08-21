<?php
/**
 * Australian Environment Configuration Manager - Simple Version
 * Blue Cleaning Services - Standardized Configuration System
 * 
 * This class provides unified access to environment variables with
 * Australian-specific defaults and validation.
 * 
 * @author Blue Cleaning Development Team
 * @version 2.0.1
 * @created 07/08/2025
 */

class AustralianEnvironmentConfig {
    private static $config = [];
    private static $loaded = false;
    
    /**
     * Load environment configuration
     */
    public static function load($envFile = '.env') {
        if (self::$loaded) {
            return;
        }
        
        $envPath = __DIR__ . '/../' . $envFile;
        
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip comments and empty lines
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                
                // Parse key=value pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, '"\'');
                    
                    if (!empty($key)) {
                        self::$config[$key] = $value;
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }
        
        // Set Australian timezone
        $timezone = self::$config['APP_TIMEZONE'] ?? self::$config['TIMEZONE'] ?? 'Australia/Sydney';
        date_default_timezone_set($timezone);
        
        self::$loaded = true;
    }
    
    /**
     * Get configuration value
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
        
        // Check our config first
        if (isset(self::$config[$key])) {
            $value = self::$config[$key];
        } else {
            // Fallback to environment
            $value = $_ENV[$key] ?? getenv($key);
            if ($value === false) {
                $value = null;
            }
        }
        
        if ($value !== null && $value !== '') {
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
            'timezone' => '+10:00'
        ];
    }
    
    /**
     * Get Australian regional settings
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
            'first_day_of_week' => self::get('FIRST_DAY_OF_WEEK', 1)
        ];
    }
    
    /**
     * Get Stripe payment configuration
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
     * Get business information
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
     * Check if we're in production environment
     */
    public static function isProduction() {
        return self::get('APP_ENV') === 'production';
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebugMode() {
        return self::get('APP_DEBUG', false);
    }
    
    /**
     * Get application URL
     */
    public static function getAppUrl() {
        return self::get('APP_URL', 'https://bluecleaning.com.au');
    }
    
    /**
     * Format currency amount for Australian display
     */
    public static function formatCurrency($amount) {
        $symbol = self::get('CURRENCY_SYMBOL', '$');
        return $symbol . number_format($amount, 2, '.', ',');
    }
    
    /**
     * Format date for Australian display
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
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config;
    }
}

// Auto-load configuration
AustralianEnvironmentConfig::load();
?>
