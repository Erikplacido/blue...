<?php
/**
 * Security Helper Functions
 * Blue Cleaning Services - Australian Standards
 * 
 * Secure input validation and sanitization functions
 * 
 * @version 1.0.0
 * @created 07/08/2025
 */

class SecurityHelpers {
    
    /**
     * Safely get and validate input from POST/GET data
     * 
     * @param array $source Input source ($_POST, $_GET, etc)
     * @param string $key Input key
     * @param string $type Expected data type (string, int, float, bool, email, url, array)
     * @param mixed $default Default value if not found or invalid
     * @param int $maxLength Maximum string length
     * @param array $allowedValues Whitelist of allowed values
     * @return mixed Validated and sanitized value
     */
    public static function getValidatedInput(
        array $source, 
        string $key, 
        string $type = 'string', 
        $default = null, 
        int $maxLength = 255,
        array $allowedValues = []
    ) {
        if (!isset($source[$key])) {
            return $default;
        }
        
        $value = $source[$key];
        
        // Apply whitelist if provided
        if (!empty($allowedValues) && !in_array($value, $allowedValues, true)) {
            return $default;
        }
        
        switch ($type) {
            case 'int':
                $filtered = filter_var($value, FILTER_VALIDATE_INT);
                return $filtered !== false ? $filtered : $default;
                
            case 'float':
                $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
                return $filtered !== false ? $filtered : $default;
                
            case 'bool':
                return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
                
            case 'email':
                $filtered = filter_var($value, FILTER_VALIDATE_EMAIL);
                return $filtered !== false ? $filtered : $default;
                
            case 'url':
                $filtered = filter_var($value, FILTER_VALIDATE_URL);
                return $filtered !== false ? $filtered : $default;
                
            case 'array':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
                }
                return is_array($value) ? $value : $default;
                
            case 'string':
            default:
                $sanitized = self::sanitizeString($value, $maxLength);
                return $sanitized !== false ? $sanitized : $default;
        }
    }
    
    /**
     * Sanitize string input
     * 
     * @param mixed $input Input value
     * @param int $maxLength Maximum length
     * @return string|false Sanitized string or false if invalid
     */
    private static function sanitizeString($input, int $maxLength): string|false {
        if (!is_scalar($input)) {
            return false;
        }
        
        $string = trim((string)$input);
        
        // Check length
        if (strlen($string) > $maxLength) {
            return false;
        }
        
        // Remove null bytes and control characters
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
        
        // Basic XSS protection
        $string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $string;
    }
    
    /**
     * Validate and sanitize POST input
     * 
     * @param string $key Input key
     * @param string $type Expected data type
     * @param mixed $default Default value
     * @param int $maxLength Maximum string length
     * @param array $allowedValues Whitelist of allowed values
     * @return mixed Validated value
     */
    public static function getPostInput(
        string $key, 
        string $type = 'string', 
        $default = null, 
        int $maxLength = 255,
        array $allowedValues = []
    ) {
        return self::getValidatedInput($_POST, $key, $type, $default, $maxLength, $allowedValues);
    }
    
    /**
     * Validate and sanitize GET input
     * 
     * @param string $key Input key
     * @param string $type Expected data type
     * @param mixed $default Default value
     * @param int $maxLength Maximum string length
     * @param array $allowedValues Whitelist of allowed values
     * @return mixed Validated value
     */
    public static function getGetInput(
        string $key, 
        string $type = 'string', 
        $default = null, 
        int $maxLength = 255,
        array $allowedValues = []
    ) {
        return self::getValidatedInput($_GET, $key, $type, $default, $maxLength, $allowedValues);
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length Token length
     * @return string Secure token
     */
    public static function generateSecureToken(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @param string $sessionToken Expected token from session
     * @return bool True if valid
     */
    public static function validateCSRFToken(string $token, string $sessionToken): bool {
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Rate limiting check
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if under limit
     */
    public static function checkRateLimit(string $identifier, int $maxAttempts = 10, int $timeWindow = 300): bool {
        $cacheKey = "rate_limit_{$identifier}";
        
        // Simple file-based rate limiting (can be upgraded to Redis)
        $cacheFile = sys_get_temp_dir() . '/blue_cleaning_' . md5($cacheKey);
        
        $attempts = [];
        if (file_exists($cacheFile)) {
            $attempts = json_decode(file_get_contents($cacheFile), true) ?? [];
        }
        
        $now = time();
        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        $attempts[] = $now;
        file_put_contents($cacheFile, json_encode($attempts), LOCK_EX);
        
        return true;
    }
    
    /**
     * Validate Australian mobile number
     * 
     * @param string $mobile Mobile number
     * @return string|false Formatted mobile or false if invalid
     */
    public static function validateAustralianMobile(string $mobile): string|false {
        // Remove all non-digits
        $mobile = preg_replace('/\D/', '', $mobile);
        
        // Check for Australian mobile patterns
        if (preg_match('/^(?:\+?61|0)?([4-5]\d{8})$/', $mobile, $matches)) {
            return '+61' . $matches[1];
        }
        
        return false;
    }
    
    /**
     * Validate Australian postcode
     * 
     * @param string $postcode Postcode
     * @return string|false Valid postcode or false if invalid
     */
    public static function validateAustralianPostcode(string $postcode): string|false {
        $postcode = trim($postcode);
        
        if (preg_match('/^\d{4}$/', $postcode)) {
            return $postcode;
        }
        
        return false;
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event type
     * @param array $details Event details
     * @param string $severity Severity level
     */
    public static function logSecurityEvent(string $event, array $details = [], string $severity = 'WARNING'): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'severity' => $severity,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        error_log(
            json_encode($logEntry) . PHP_EOL,
            3,
            $logFile
        );
    }
}
