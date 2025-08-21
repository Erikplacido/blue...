<?php
/**
 * =========================================================
 * PROJETO BLUE V2 - CONFIGURAÇÕES DE SEGURANÇA
 * =========================================================
 * 
 * @file config/security.php
 * @description Configurações centralizadas de segurança
 * @version 2.0
 * @date 2025-08-07
 */

// Configurações de segurança
define('SECURITY_CONFIG', [
    'session' => [
        'name' => 'BLUE_SESSION',
        'lifetime' => 3600, // 1 hora
        'regenerate_interval' => 300, // 5 minutos
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ],
    'password' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special' => true,
        'hash_algo' => PASSWORD_ARGON2ID,
        'hash_options' => [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,
            'threads' => 3
        ]
    ],
    'csrf' => [
        'token_name' => '_csrf_token',
        'token_lifetime' => 3600,
        'regenerate_on_use' => true
    ],
    'rate_limiting' => [
        'login_attempts' => [
            'max_attempts' => 5,
            'window' => 900, // 15 minutos
            'lockout_duration' => 1800 // 30 minutos
        ],
        'api_requests' => [
            'max_requests' => 100,
            'window' => 3600 // 1 hora
        ]
    ],
    'encryption' => [
        'method' => 'AES-256-GCM',
        'key_length' => 32
    ]
]);

/**
 * Gera uma chave de criptografia segura
 */
function generateEncryptionKey(): string {
    return base64_encode(random_bytes(SECURITY_CONFIG['encryption']['key_length']));
}

/**
 * Obtém ou gera a chave de criptografia
 */
function getEncryptionKey(): string {
    $keyFile = __DIR__ . '/encryption.key';
    
    if (!file_exists($keyFile)) {
        $key = generateEncryptionKey();
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600);
        return $key;
    }
    
    return file_get_contents($keyFile);
}

/**
 * Criptografa dados sensíveis
 */
function encryptData(string $data): array {
    $key = base64_decode(getEncryptionKey());
    $iv = random_bytes(16);
    $tag = '';
    
    $encrypted = openssl_encrypt(
        $data,
        SECURITY_CONFIG['encryption']['method'],
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    
    return [
        'data' => base64_encode($encrypted),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag)
    ];
}

/**
 * Descriptografa dados
 */
function decryptData(array $encrypted): string|false {
    $key = base64_decode(getEncryptionKey());
    
    return openssl_decrypt(
        base64_decode($encrypted['data']),
        SECURITY_CONFIG['encryption']['method'],
        $key,
        OPENSSL_RAW_DATA,
        base64_decode($encrypted['iv']),
        base64_decode($encrypted['tag'])
    );
}

/**
 * Configurações iniciais de segurança
 */
function initializeSecurity(): void {
    // Headers de segurança
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // CSP será configurado por página
    
    // Configuração de sessão segura
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_lifetime', SECURITY_CONFIG['session']['lifetime']);
    
    session_name(SECURITY_CONFIG['session']['name']);
}

// Inicializa configurações de segurança
initializeSecurity();
?>
