<?php
/**
 * =========================================================
 * PROJETO BLUE V2 - SISTEMA DE RATE LIMITING
 * =========================================================
 * 
 * @file auth/RateLimiter.php
 * @description Sistema avançado de rate limiting e proteção
 * @version 2.0
 * @date 2025-08-07
 */

class RateLimiter {
    private static $instance = null;
    private $storage = [];
    
    private function __construct() {
        $this->initializeStorage();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializa storage (em produção, usar Redis ou banco de dados)
     */
    private function initializeStorage(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['rate_limiter'])) {
            $_SESSION['rate_limiter'] = [];
        }
        
        $this->storage = &$_SESSION['rate_limiter'];
    }
    
    /**
     * Verifica se ação é permitida
     */
    public function isAllowed(string $key, int $maxAttempts, int $windowSeconds): bool {
        $this->cleanExpiredEntries();
        
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [
                'attempts' => [],
                'blocked_until' => null
            ];
        }
        
        $entry = &$this->storage[$key];
        $currentTime = time();
        
        // Verifica se ainda está bloqueado
        if ($entry['blocked_until'] && $currentTime < $entry['blocked_until']) {
            return false;
        }
        
        // Remove tentativas antigas
        $entry['attempts'] = array_filter(
            $entry['attempts'],
            fn($timestamp) => ($currentTime - $timestamp) < $windowSeconds
        );
        
        // Verifica se excedeu limite
        if (count($entry['attempts']) >= $maxAttempts) {
            // Calcula duração do bloqueio baseado em tentativas
            $blockDuration = $this->calculateBlockDuration(count($entry['attempts']));
            $entry['blocked_until'] = $currentTime + $blockDuration;
            return false;
        }
        
        return true;
    }
    
    /**
     * Registra tentativa
     */
    public function recordAttempt(string $key): void {
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [
                'attempts' => [],
                'blocked_until' => null
            ];
        }
        
        $this->storage[$key]['attempts'][] = time();
    }
    
    /**
     * Calcula duração do bloqueio baseado em tentativas
     */
    private function calculateBlockDuration(int $attempts): int {
        // Bloqueio progressivo: 1min, 5min, 15min, 30min, 1h
        $durations = [60, 300, 900, 1800, 3600];
        $index = min($attempts - 5, count($durations) - 1);
        return $durations[max(0, $index)];
    }
    
    /**
     * Obtém informações sobre rate limit
     */
    public function getInfo(string $key): array {
        if (!isset($this->storage[$key])) {
            return [
                'attempts' => 0,
                'blocked_until' => null,
                'is_blocked' => false,
                'reset_time' => null
            ];
        }
        
        $entry = $this->storage[$key];
        $currentTime = time();
        
        // Limpa tentativas antigas
        $entry['attempts'] = array_filter(
            $entry['attempts'],
            fn($timestamp) => ($currentTime - $timestamp) < 900 // 15 min window
        );
        
        return [
            'attempts' => count($entry['attempts']),
            'blocked_until' => $entry['blocked_until'],
            'is_blocked' => $entry['blocked_until'] && $currentTime < $entry['blocked_until'],
            'reset_time' => $entry['blocked_until']
        ];
    }
    
    /**
     * Reset rate limit para uma chave
     */
    public function reset(string $key): void {
        unset($this->storage[$key]);
    }
    
    /**
     * Limpa entradas expiradas
     */
    private function cleanExpiredEntries(): void {
        $currentTime = time();
        
        foreach ($this->storage as $key => $entry) {
            // Remove se não há bloqueio e sem tentativas recentes
            if (!$entry['blocked_until'] && empty($entry['attempts'])) {
                unset($this->storage[$key]);
                continue;
            }
            
            // Remove se bloqueio expirou e sem tentativas recentes
            if ($entry['blocked_until'] && $currentTime > $entry['blocked_until']) {
                $hasRecentAttempts = array_filter(
                    $entry['attempts'],
                    fn($timestamp) => ($currentTime - $timestamp) < 3600
                );
                
                if (empty($hasRecentAttempts)) {
                    unset($this->storage[$key]);
                }
            }
        }
    }
    
    /**
     * Middleware para proteção de APIs
     */
    public function protectAPI(string $endpoint, int $maxRequests = 100, int $windowSeconds = 3600): void {
        $clientIp = $this->getClientIp();
        $key = "api_{$endpoint}_{$clientIp}";
        
        if (!$this->isAllowed($key, $maxRequests, $windowSeconds)) {
            $info = $this->getInfo($key);
            
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . ($info['blocked_until'] - time()));
            
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests',
                'retry_after' => $info['blocked_until'] - time(),
                'reset_time' => date('Y-m-d H:i:s', $info['blocked_until'])
            ]);
            exit;
        }
        
        $this->recordAttempt($key);
    }
    
    /**
     * Proteção de login
     */
    public function protectLogin(string $identifier): bool {
        $clientIp = $this->getClientIp();
        $keys = [
            "login_ip_{$clientIp}",
            "login_user_{$identifier}",
            "login_global"
        ];
        
        $config = SECURITY_CONFIG['rate_limiting']['login_attempts'];
        
        foreach ($keys as $key) {
            if (!$this->isAllowed($key, $config['max_attempts'], $config['window'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Registra tentativa de login falhada
     */
    public function recordLoginAttempt(string $identifier): void {
        $clientIp = $this->getClientIp();
        $keys = [
            "login_ip_{$clientIp}",
            "login_user_{$identifier}",
            "login_global"
        ];
        
        foreach ($keys as $key) {
            $this->recordAttempt($key);
        }
    }
    
    /**
     * Obtém IP real do cliente
     */
    private function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancers
            'HTTP_X_REAL_IP',            // Nginx
            'HTTP_CLIENT_IP',            // Shared internet
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Proteção contra força bruta por fingerprint
     */
    public function generateFingerprint(): string {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $this->getClientIp()
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    /**
     * Verifica se fingerprint é suspeito
     */
    public function isSuspiciousFingerprint(string $fingerprint): bool {
        $key = "fingerprint_{$fingerprint}";
        
        // Máximo 10 tentativas de login por fingerprint em 1 hora
        return !$this->isAllowed($key, 10, 3600);
    }
    
    /**
     * Relatório de segurança
     */
    public function getSecurityReport(): array {
        $report = [
            'total_blocked_keys' => 0,
            'active_blocks' => [],
            'top_offenders' => [],
            'statistics' => [
                'total_attempts' => 0,
                'blocked_attempts' => 0,
                'success_rate' => 0
            ]
        ];
        
        $currentTime = time();
        
        foreach ($this->storage as $key => $entry) {
            $report['statistics']['total_attempts'] += count($entry['attempts']);
            
            if ($entry['blocked_until'] && $currentTime < $entry['blocked_until']) {
                $report['total_blocked_keys']++;
                $report['active_blocks'][] = [
                    'key' => $key,
                    'blocked_until' => date('Y-m-d H:i:s', $entry['blocked_until']),
                    'attempts' => count($entry['attempts'])
                ];
                
                $report['statistics']['blocked_attempts'] += count($entry['attempts']);
            }
        }
        
        // Calcula taxa de sucesso
        if ($report['statistics']['total_attempts'] > 0) {
            $successfulAttempts = $report['statistics']['total_attempts'] - $report['statistics']['blocked_attempts'];
            $report['statistics']['success_rate'] = round(
                ($successfulAttempts / $report['statistics']['total_attempts']) * 100,
                2
            );
        }
        
        return $report;
    }
}

/**
 * Função helper global para rate limiting
 */
function rateLimitCheck(string $key, int $maxAttempts, int $windowSeconds): bool {
    return RateLimiter::getInstance()->isAllowed($key, $maxAttempts, $windowSeconds);
}

/**
 * Função helper global para registrar tentativa
 */
function rateLimitRecord(string $key): void {
    RateLimiter::getInstance()->recordAttempt($key);
}
?>
