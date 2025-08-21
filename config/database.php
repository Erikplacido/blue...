<?php
/**
 * Legacy Database Compatibility Layer
 * Blue Cleaning Services - Backward Compatibility
 * 
 * This file provides backward compatibility for old database connections
 * while transitioning to the new Australian standardized system.
 * 
 * @author Blue Cleaning Development Team
 * @version 2.0.0
 * @created 07/08/2025
 */

// Load the new Australian system
require_once __DIR__ . '/australian-database.php';
require_once __DIR__ . '/australian-environment.php';

/**
 * Legacy Database class for backward compatibility
 * 
 * This maintains compatibility with existing code while redirecting
 * to the new Australian standardized database system.
 */
class Database {
    private static $instance = null;
    
    /**
     * Get database instance (legacy compatibility)
     * 
     * @return AustralianDatabase
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = AustralianDatabase::getInstance();
        }
        return self::$instance;
    }
}

/**
 * Legacy EnvironmentConfig class for backward compatibility
 */
class EnvironmentConfig {
    /**
     * Get configuration value (legacy compatibility)
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        return AustralianEnvironmentConfig::get($key, $default);
    }
    
    /**
     * Legacy database configuration mapping
     */
    public static function getDatabaseConfig() {
        $config = AustralianEnvironmentConfig::getDatabase();
        return [
            'database.host' => $config['host'],
            'database.port' => $config['port'],
            'database.name' => $config['database'],
            'database.username' => $config['username'],
            'database.password' => $config['password'],
            'database.charset' => $config['charset']
        ];
    }
}

// Legacy function for old hardcoded connections
function getLegacyDatabaseConnection() {
    try {
        return AustralianDatabase::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Legacy Database Connection Error: " . $e->getMessage());
        throw $e;
    }
}

// Legacy PDO connection for very old code
function createLegacyPDO() {
    $config = AustralianEnvironmentConfig::getDatabase();
    
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}, time_zone = '{$config['timezone']}'"
    ];
    
    return new PDO($dsn, $config['username'], $config['password'], $options);
}

// Legacy constants for old code
if (!defined('DB_HOST')) {
    define('DB_HOST', AustralianEnvironmentConfig::get('DB_HOST', 'localhost'));
    define('DB_PORT', AustralianEnvironmentConfig::get('DB_PORT', 3306));
    define('DB_NAME', AustralianEnvironmentConfig::get('DB_DATABASE', 'blue_cleaning_au'));
    define('DB_USER', AustralianEnvironmentConfig::get('DB_USERNAME'));
    define('DB_PASS', AustralianEnvironmentConfig::get('DB_PASSWORD'));
    define('DB_CHARSET', AustralianEnvironmentConfig::get('DB_CHARSET', 'utf8mb4'));
}

// Add warning for deprecated usage in debug mode
if (AustralianEnvironmentConfig::isDebugMode()) {
    error_log("DEPRECATION WARNING: Legacy database compatibility layer is being used. Please update to use AustralianDatabase class.");
}
?>
