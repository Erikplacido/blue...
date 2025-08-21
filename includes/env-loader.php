<?php
/**
 * Simple .env Loader
 * Carrega variáveis do arquivo .env de forma direta
 */

function loadEnv($envPath = null) {
    static $loaded = false;
    
    if ($loaded) {
        return;
    }
    
    $envPath = $envPath ?: dirname(__DIR__) . '/.env';
    
    if (!file_exists($envPath)) {
        error_log("Warning: .env file not found at: $envPath");
        return;
    }
    
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\''); // Remove quotes
            
            // Set in $_ENV and $_SERVER
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
    
    $loaded = true;
}

// Helper function to get env variable
function env($key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

// Auto-load .env on include
loadEnv();
?>
