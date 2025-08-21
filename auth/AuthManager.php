<?php
/**
 * =========================================================
 * PROJETO BLUE V2 - SISTEMA DE AUTENTICAÇÃO
 * =========================================================
 * 
 * @file auth/AuthManager.php
 * @description Sistema completo de autenticação e autorização
 * @version 2.0
 * @date 2025-08-07
 */

require_once __DIR__ . '/../config/security.php';

class AuthManager {
    private static $instance = null;
    private $isInitialized = false;
    
    // Simulação de banco de dados (substituir por conexão real)
    private $users = [
        'admin@blue.com' => [
            'id' => 1,
            'email' => 'admin@blue.com',
            'password_hash' => '', // Será gerado
            'role' => 'admin',
            'status' => 'active',
            'created_at' => '2025-08-07 00:00:00',
            'last_login' => null,
            'failed_attempts' => 0,
            'locked_until' => null
        ]
    ];
    
    private function __construct() {
        $this->initializeDefaultUsers();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializa usuários padrão
     */
    private function initializeDefaultUsers(): void {
        // Gera hash para usuário admin padrão (senha: Blue2025!)
        $this->users['admin@blue.com']['password_hash'] = $this->hashPassword('Blue2025!');
        
        // Adiciona usuário de teste
        $this->users['test@blue.com'] = [
            'id' => 2,
            'email' => 'test@blue.com',
            'password_hash' => $this->hashPassword('Test2025!'),
            'role' => 'customer',
            'status' => 'active',
            'created_at' => '2025-08-07 00:00:00',
            'last_login' => null,
            'failed_attempts' => 0,
            'locked_until' => null
        ];
    }
    
    /**
     * Inicia sessão segura
     */
    public function startSession(): void {
        if (!$this->isInitialized) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $this->regenerateSessionIfNeeded();
            $this->isInitialized = true;
        }
    }
    
    /**
     * Regenera sessão periodicamente
     */
    private function regenerateSessionIfNeeded(): void {
        $regenerateInterval = SECURITY_CONFIG['session']['regenerate_interval'];
        
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        } elseif (time() - $_SESSION['last_regeneration'] > $regenerateInterval) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        }
    }
    
    /**
     * Hash seguro de senha
     */
    public function hashPassword(string $password): string {
        $config = SECURITY_CONFIG['password'];
        return password_hash($password, $config['hash_algo'], $config['hash_options']);
    }
    
    /**
     * Verifica senha
     */
    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    /**
     * Valida força da senha
     */
    public function validatePasswordStrength(string $password): array {
        $config = SECURITY_CONFIG['password'];
        $errors = [];
        
        if (strlen($password) < $config['min_length']) {
            $errors[] = "Password must be at least {$config['min_length']} characters long";
        }
        
        if ($config['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if ($config['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if ($config['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if ($config['require_special'] && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Autentica usuário
     */
    public function authenticate(string $email, string $password, string $userIp): array {
        $this->startSession();
        
        // Verifica rate limiting
        if (!$this->checkRateLimit($email, $userIp)) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please try again later.',
                'locked_until' => $this->getRateLimitExpiry($email)
            ];
        }
        
        // Busca usuário
        $user = $this->findUserByEmail($email);
        if (!$user) {
            $this->recordFailedAttempt($email, $userIp);
            return [
                'success' => false,
                'message' => 'Invalid email or password',
                'delay' => $this->getAuthDelay()
            ];
        }
        
        // Verifica se conta está bloqueada
        if ($this->isAccountLocked($user)) {
            return [
                'success' => false,
                'message' => 'Account is temporarily locked',
                'locked_until' => $user['locked_until']
            ];
        }
        
        // Verifica senha
        if (!$this->verifyPassword($password, $user['password_hash'])) {
            $this->recordFailedAttempt($email, $userIp);
            $this->incrementUserFailedAttempts($email);
            
            return [
                'success' => false,
                'message' => 'Invalid email or password',
                'delay' => $this->getAuthDelay()
            ];
        }
        
        // Login bem-sucedido
        $this->clearFailedAttempts($email);
        $this->createUserSession($user);
        $this->updateLastLogin($email);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $this->sanitizeUserData($user),
            'redirect' => $this->getRedirectUrl($user['role'], $user)
        ];
    }
    
    /**
     * Verifica rate limiting
     */
    private function checkRateLimit(string $email, string $ip): bool {
        $config = SECURITY_CONFIG['rate_limiting']['login_attempts'];
        $key = "login_attempts_{$email}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $attempts = $_SESSION[$key];
        
        // Reset counter if window expired
        if (time() - $attempts['first_attempt'] > $config['window']) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
            return true;
        }
        
        return $attempts['count'] < $config['max_attempts'];
    }
    
    /**
     * Registra tentativa falhada
     */
    private function recordFailedAttempt(string $email, string $ip): void {
        $key = "login_attempts_{$email}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $_SESSION[$key]['count']++;
        
        // Se excedeu limite, define bloqueio
        $config = SECURITY_CONFIG['rate_limiting']['login_attempts'];
        if ($_SESSION[$key]['count'] >= $config['max_attempts']) {
            $_SESSION[$key]['locked_until'] = time() + $config['lockout_duration'];
        }
    }
    
    /**
     * Obtém expiração do rate limit
     */
    private function getRateLimitExpiry(string $email): ?int {
        $key = "login_attempts_{$email}";
        return $_SESSION[$key]['locked_until'] ?? null;
    }
    
    /**
     * Busca usuário por email
     */
    private function findUserByEmail(string $email): ?array {
        return $this->users[$email] ?? null;
    }
    
    /**
     * Verifica se conta está bloqueada
     */
    private function isAccountLocked(array $user): bool {
        if (!$user['locked_until']) {
            return false;
        }
        
        return time() < strtotime($user['locked_until']);
    }
    
    /**
     * Incrementa tentativas falhadas do usuário
     */
    private function incrementUserFailedAttempts(string $email): void {
        if (isset($this->users[$email])) {
            $this->users[$email]['failed_attempts']++;
            
            // Bloqueia conta após muitas tentativas
            $maxAttempts = SECURITY_CONFIG['rate_limiting']['login_attempts']['max_attempts'];
            if ($this->users[$email]['failed_attempts'] >= $maxAttempts) {
                $lockDuration = SECURITY_CONFIG['rate_limiting']['login_attempts']['lockout_duration'];
                $this->users[$email]['locked_until'] = date('Y-m-d H:i:s', time() + $lockDuration);
            }
        }
    }
    
    /**
     * Limpa tentativas falhadas
     */
    private function clearFailedAttempts(string $email): void {
        $key = "login_attempts_{$email}";
        unset($_SESSION[$key]);
        
        if (isset($this->users[$email])) {
            $this->users[$email]['failed_attempts'] = 0;
            $this->users[$email]['locked_until'] = null;
        }
    }
    
    /**
     * Cria sessão do usuário
     */
    private function createUserSession(array $user): void {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'login_time' => time(),
            'csrf_token' => $this->generateCSRFToken()
        ];
    }
    
    /**
     * Atualiza último login
     */
    private function updateLastLogin(string $email): void {
        if (isset($this->users[$email])) {
            $this->users[$email]['last_login'] = date('Y-m-d H:i:s');
        }
    }
    
    /**
     * Sanitiza dados do usuário
     */
    private function sanitizeUserData(array $user): array {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status']
        ];
    }
    
    /**
     * Obtém URL de redirecionamento com parâmetros dinâmicos
     */
    private function getRedirectUrl(string $role, array $user = []): string {
        $baseUrls = [
            'admin' => '/admin/dashboard.php',
            'professional' => '/professional/dynamic-dashboard.php',
            'customer' => '/customer/dashboard.php',
            'default' => '/dashboard.php'
        ];
        
        $url = $baseUrls[$role] ?? $baseUrls['default'];
        
        // Add parameters for professional dashboard
        if ($role === 'professional' && !empty($user)) {
            $params = [
                'professional_id' => $user['id'] ?? $user['professional_id'] ?? null,
                'token' => $this->generateAuthToken($user)
            ];
            
            // Filter out null values
            $params = array_filter($params, function($value) {
                return $value !== null;
            });
            
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        }
        
        return $url;
    }
    
    /**
     * Gera token de autenticação para URLs
     */
    private function generateAuthToken(array $user): string {
        $payload = [
            'user_id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'role' => $user['role'] ?? null,
            'timestamp' => time(),
            'expires' => time() + 3600 // 1 hour
        ];
        
        return base64_encode(json_encode($payload));
    }
    
    /**
     * Delay para prevenir timing attacks
     */
    private function getAuthDelay(): int {
        return random_int(100, 500); // 100-500ms
    }
    
    /**
     * Gera token CSRF
     */
    public function generateCSRFToken(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$token] = time();
        
        // Limpa tokens expirados
        $this->cleanExpiredCSRFTokens();
        
        return $token;
    }
    
    /**
     * Valida token CSRF
     */
    public function validateCSRFToken(string $token): bool {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        $tokenTime = $_SESSION['csrf_tokens'][$token];
        $lifetime = SECURITY_CONFIG['csrf']['token_lifetime'];
        
        if (time() - $tokenTime > $lifetime) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        // Remove token se configurado para regenerar
        if (SECURITY_CONFIG['csrf']['regenerate_on_use']) {
            unset($_SESSION['csrf_tokens'][$token]);
        }
        
        return true;
    }
    
    /**
     * Limpa tokens CSRF expirados
     */
    private function cleanExpiredCSRFTokens(): void {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
            return;
        }
        
        $lifetime = SECURITY_CONFIG['csrf']['token_lifetime'];
        $currentTime = time();
        
        foreach ($_SESSION['csrf_tokens'] as $token => $time) {
            if ($currentTime - $time > $lifetime) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }
    
    /**
     * Verifica se usuário está logado
     */
    public function isLoggedIn(): bool {
        $this->startSession();
        return isset($_SESSION['user']) && $this->isSessionValid();
    }
    
    /**
     * Valida sessão
     */
    private function isSessionValid(): bool {
        if (!isset($_SESSION['user']['login_time'])) {
            return false;
        }
        
        $sessionLifetime = SECURITY_CONFIG['session']['lifetime'];
        return (time() - $_SESSION['user']['login_time']) < $sessionLifetime;
    }
    
    /**
     * Obtém usuário atual
     */
    public function getCurrentUser(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $_SESSION['user'];
    }
    
    /**
     * Verifica permissão
     */
    public function hasPermission(string $permission): bool {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // Sistema básico de permissões
        $permissions = [
            'admin' => ['*'], // Admin tem todas as permissões
            'professional' => ['booking.view', 'booking.update', 'availability.manage'],
            'customer' => ['booking.create', 'booking.view', 'subscription.manage']
        ];
        
        $userPermissions = $permissions[$user['role']] ?? [];
        
        return in_array('*', $userPermissions) || in_array($permission, $userPermissions);
    }
    
    /**
     * Logout
     */
    public function logout(): void {
        $this->startSession();
        
        // Limpa dados da sessão
        $_SESSION = [];
        
        // Destrói cookie de sessão
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destrói sessão
        session_destroy();
    }
    
    /**
     * Middleware de autenticação
     */
    public function requireAuth(array $allowedRoles = []): void {
        if (!$this->isLoggedIn()) {
            $this->redirectToLogin();
        }
        
        if (!empty($allowedRoles)) {
            $user = $this->getCurrentUser();
            if (!in_array($user['role'], $allowedRoles)) {
                http_response_code(403);
                die('Access Denied');
            }
        }
    }
    
    /**
     * Redireciona para login
     */
    private function redirectToLogin(): void {
        header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    
    /**
     * Middleware CSRF
     */
    public function requireCSRF(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            
            if (!$this->validateCSRFToken($token)) {
                http_response_code(419);
                die('CSRF Token Mismatch');
            }
        }
    }
}
?>
