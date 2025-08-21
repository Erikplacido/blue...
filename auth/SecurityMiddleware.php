<?php
/**
 * =========================================================
 * PROJETO BLUE V2 - MIDDLEWARE DE SEGURANÇA
 * =========================================================
 * 
 * @file auth/SecurityMiddleware.php
 * @description Middleware central de segurança para todas as páginas
 * @version 2.0
 * @date 2025-08-07
 */

require_once __DIR__ . '/AuthManager.php';
require_once __DIR__ . '/RateLimiter.php';

class SecurityMiddleware {
    private $auth;
    private $rateLimiter;
    private $config;
    
    public function __construct() {
        $this->auth = AuthManager::getInstance();
        $this->rateLimiter = RateLimiter::getInstance();
        $this->config = SECURITY_CONFIG;
    }
    
    /**
     * Aplica todas as proteções de segurança
     */
    public function apply(array $options = []): void {
        $this->setSecurityHeaders($options['csp'] ?? null);
        $this->validateRequest();
        $this->checkRateLimit($options['rate_limit'] ?? []);
        
        if ($options['require_auth'] ?? false) {
            $this->requireAuthentication($options['allowed_roles'] ?? []);
        }
        
        if ($options['require_csrf'] ?? false) {
            $this->validateCSRF();
        }
    }
    
    /**
     * Define headers de segurança
     */
    private function setSecurityHeaders(?string $customCSP = null): void {
        // Headers básicos de segurança
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        
        // Content Security Policy
        $csp = $customCSP ?? $this->generateDefaultCSP();
        header("Content-Security-Policy: $csp");
        
        // HSTS (apenas em HTTPS)
        if ($this->isHTTPS()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Cache control para páginas sensíveis
        if ($this->isSensitivePage()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
    
    /**
     * Gera CSP padrão
     */
    private function generateDefaultCSP(): string {
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://js.stripe.com https://maps.googleapis.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: https:",
            "connect-src 'self' https://api.stripe.com",
            "frame-src https://js.stripe.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        
        return implode('; ', $policies);
    }
    
    /**
     * Valida requisição HTTP
     */
    private function validateRequest(): void {
        // Verifica método HTTP
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            $this->denyRequest('Invalid HTTP method', 405);
        }
        
        // Valida headers críticos
        $this->validateHeaders();
        
        // Verifica tamanho da requisição
        $this->validateRequestSize();
        
        // Detecta ataques comuns
        $this->detectAttacks();
    }
    
    /**
     * Valida headers HTTP
     */
    private function validateHeaders(): void {
        // Verifica Content-Type para POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $allowedTypes = [
                'application/x-www-form-urlencoded',
                'multipart/form-data',
                'application/json'
            ];
            
            $isValid = false;
            foreach ($allowedTypes as $type) {
                if (strpos($contentType, $type) === 0) {
                    $isValid = true;
                    break;
                }
            }
            
            if (!$isValid) {
                $this->denyRequest('Invalid Content-Type', 400);
            }
        }
        
        // Verifica User-Agent suspeito
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($userAgent) || $this->isSuspiciousUserAgent($userAgent)) {
            $this->logSecurity('Suspicious User-Agent', ['user_agent' => $userAgent]);
        }
    }
    
    /**
     * Valida tamanho da requisição
     */
    private function validateRequestSize(): void {
        $maxSize = 10 * 1024 * 1024; // 10MB
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        
        if ($contentLength > $maxSize) {
            $this->denyRequest('Request too large', 413);
        }
    }
    
    /**
     * Detecta ataques comuns
     */
    private function detectAttacks(): void {
        $suspicious = false;
        $allInput = array_merge($_GET, $_POST, $_COOKIE);
        
        // Padrões de ataque
        $attackPatterns = [
            'sql_injection' => '/(\bunion\b|\bselect\b|\binsert\b|\bdelete\b|\bdrop\b|\bupdate\b).*(from|into|where)/i',
            'xss' => '/<script|javascript:|on\w+\s*=/i',
            'path_traversal' => '/\.\.[\/\\\\]/i',
            'command_injection' => '/[;&|`$(){}]/i',
            'ldap_injection' => '/[*)(|&=!><]/i'
        ];
        
        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                foreach ($attackPatterns as $attack => $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurity("Potential $attack attack", [
                            'field' => $key,
                            'value' => substr($value, 0, 100),
                            'ip' => $this->getClientIP(),
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        $suspicious = true;
                    }
                }
            }
        }
        
        if ($suspicious) {
            $this->rateLimiter->recordAttempt('suspicious_' . $this->getClientIP());
        }
    }
    
    /**
     * Verifica rate limiting
     */
    private function checkRateLimit(array $config): void {
        $defaultConfig = $this->config['rate_limiting']['api_requests'];
        $maxRequests = $config['max_requests'] ?? $defaultConfig['max_requests'];
        $window = $config['window'] ?? $defaultConfig['window'];
        
        $clientIP = $this->getClientIP();
        $key = 'page_' . $clientIP;
        
        if (!$this->rateLimiter->isAllowed($key, $maxRequests, $window)) {
            $info = $this->rateLimiter->getInfo($key);
            
            header('Retry-After: ' . ($info['blocked_until'] - time()));
            $this->denyRequest('Rate limit exceeded', 429);
        }
        
        $this->rateLimiter->recordAttempt($key);
    }
    
    /**
     * Requer autenticação
     */
    private function requireAuthentication(array $allowedRoles = []): void {
        $this->auth->startSession();
        
        if (!$this->auth->isLoggedIn()) {
            $this->redirectToLogin();
        }
        
        if (!empty($allowedRoles)) {
            $user = $this->auth->getCurrentUser();
            if (!in_array($user['role'], $allowedRoles)) {
                $this->denyRequest('Access denied', 403);
            }
        }
    }
    
    /**
     * Valida token CSRF
     */
    private function validateCSRF(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $token = $_POST['_csrf_token'] ?? 
                     $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 
                     $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
            
            if (!$this->auth->validateCSRFToken($token)) {
                $this->denyRequest('CSRF token validation failed', 419);
            }
        }
    }
    
    /**
     * Obtém IP do cliente
     */
    private function getClientIP(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
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
     * Verifica se User-Agent é suspeito
     */
    private function isSuspiciousUserAgent(string $userAgent): bool {
        $suspiciousPatterns = [
            'curl/', 'wget/', 'python', 'bot', 'crawler', 'spider',
            'scanner', 'nikto', 'sqlmap', 'nmap'
        ];
        
        $userAgent = strtolower($userAgent);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica se é HTTPS
     */
    private function isHTTPS(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    /**
     * Verifica se é página sensível
     */
    private function isSensitivePage(): bool {
        $sensitivePaths = [
            '/auth/', '/admin/', '/api/', '/customer/', '/professional/'
        ];
        
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($sensitivePaths as $path) {
            if (strpos($currentPath, $path) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Redireciona para login
     */
    private function redirectToLogin(): void {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: /auth/login.php?redirect=" . urlencode($currentUrl));
        exit;
    }
    
    /**
     * Nega acesso
     */
    private function denyRequest(string $message, int $code = 403): void {
        http_response_code($code);
        
        $this->logSecurity('Access denied', [
            'message' => $message,
            'code' => $code,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
        
        // Resposta JSON para APIs
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => $message,
                'code' => $code,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Página de erro para web
            include __DIR__ . '/error_page.php';
        }
        
        exit;
    }
    
    /**
     * Log de segurança
     */
    private function logSecurity(string $event, array $data = []): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'data' => $data
        ];
        
        // Em produção, salvar em arquivo ou banco de dados
        $logFile = __DIR__ . '/../logs/security.log';
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Função estática para uso fácil
     */
    public static function protect(array $options = []): void {
        $middleware = new self();
        $middleware->apply($options);
    }
}

// Função helper global
function security_protect(array $options = []): void {
    SecurityMiddleware::protect($options);
}
?>
