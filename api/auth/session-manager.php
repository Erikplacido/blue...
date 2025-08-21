/**
 * Advanced Session Management System
 * Blue Cleaning Services - Redis-based Session Management
 */

<?php
require_once __DIR__ . '/../../config/australian-environment.php';
require_once __DIR__ . '/../../config/australian-database.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // For Redis

class SessionManager {
    private $redis;
    private $pdo;
    private $sessionTimeout;
    private $maxConcurrentSessions;
    private $securitySettings;
    
    public function __construct() {
        // Load Australian configuration
        AustralianEnvironmentConfig::load();
        
        $this->initializeRedis();
        $this->initializeDatabase();
        $this->sessionTimeout = 3600; // 1 hour default
        $this->maxConcurrentSessions = 5; // Max concurrent sessions per user
        
        $this->securitySettings = [
            'regenerate_interval' => 300, // 5 minutes
            'idle_timeout' => 1800, // 30 minutes
            'absolute_timeout' => 7200, // 2 hours
            'ip_check' => true,
            'user_agent_check' => true,
            'fingerprint_check' => true
        ];
    }
    
    private function initializeDatabase() {
        try {
            $dbConfig = AustralianEnvironmentConfig::getDatabase();
            $this->pdo = new PDO(
                "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    private function initializeRedis() {
        try {
            $this->redis = new Redis();
            $redisHost = AustralianEnvironmentConfig::get('REDIS_HOST', '127.0.0.1');
            $redisPort = AustralianEnvironmentConfig::get('REDIS_PORT', 6379);
            $redisPassword = AustralianEnvironmentConfig::get('REDIS_PASSWORD');
            
            $this->redis->connect($redisHost, $redisPort);
            
            if ($redisPassword) {
                $this->redis->auth($redisPassword);
            }
            
            // Test connection
            $this->redis->ping();
            
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            // Fallback to database sessions
            $this->redis = null;
        }
    }
    
    /**
     * Start or resume session
     */
    public function startSession($userId = null, $userType = null) {
        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc']
        );
        
        // Enhanced session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', $this->sessionTimeout);
        
        session_start();
        
        // Initialize session data for new sessions
        if (empty($_SESSION['initialized'])) {
            $this->initializeNewSession($userId, $userType);
        }
        
        // Validate existing session
        if (!$this->validateSession()) {
            $this->destroySession();
            return false;
        }
        
        // Update activity timestamp
        $this->updateActivity();
        
        // Check for session regeneration
        if ($this->shouldRegenerateId()) {
            $this->regenerateSessionId();
        }
        
        return true;
    }
    
    /**
     * Initialize new session
     */
    private function initializeNewSession($userId, $userType) {
        $sessionId = session_id();
        $fingerprint = $this->generateFingerprint();
        
        $_SESSION = [
            'initialized' => true,
            'user_id' => $userId,
            'user_type' => $userType,
            'created_at' => time(),
            'last_activity' => time(),
            'last_regeneration' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'fingerprint' => $fingerprint,
            'csrf_token' => bin2hex(random_bytes(32)),
            'concurrent_sessions' => 1
        ];
        
        // Check concurrent session limits
        if ($userId) {
            $this->enforceConcurrentSessionLimits($userId);
        }
        
        // Log session creation
        $this->logSessionEvent('session_created', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'user_type' => $userType,
            'ip_address' => $_SESSION['ip_address'],
            'user_agent' => substr($_SESSION['user_agent'], 0, 255)
        ]);
    }
    
    /**
     * Validate session integrity
     */
    private function validateSession() {
        if (empty($_SESSION['initialized'])) {
            return false;
        }
        
        $now = time();
        
        // Check absolute timeout
        if ($now - $_SESSION['created_at'] > $this->securitySettings['absolute_timeout']) {
            $this->logSessionEvent('session_expired_absolute');
            return false;
        }
        
        // Check idle timeout
        if ($now - $_SESSION['last_activity'] > $this->securitySettings['idle_timeout']) {
            $this->logSessionEvent('session_expired_idle');
            return false;
        }
        
        // Security checks
        if ($this->securitySettings['ip_check']) {
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($currentIp !== $_SESSION['ip_address']) {
                $this->logSessionEvent('session_ip_mismatch', [
                    'expected_ip' => $_SESSION['ip_address'],
                    'actual_ip' => $currentIp
                ]);
                return false;
            }
        }
        
        if ($this->securitySettings['user_agent_check']) {
            $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($currentUserAgent !== $_SESSION['user_agent']) {
                $this->logSessionEvent('session_user_agent_mismatch');
                return false;
            }
        }
        
        if ($this->securitySettings['fingerprint_check']) {
            $currentFingerprint = $this->generateFingerprint();
            if ($currentFingerprint !== $_SESSION['fingerprint']) {
                $this->logSessionEvent('session_fingerprint_mismatch');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate browser fingerprint
     */
    private function generateFingerprint() {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['HTTP_ACCEPT'] ?? ''
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    /**
     * Update session activity
     */
    private function updateActivity() {
        $_SESSION['last_activity'] = time();
        
        // Update Redis if available
        if ($this->redis) {
            $sessionData = json_encode($_SESSION);
            $this->redis->setex('session:' . session_id(), $this->sessionTimeout, $sessionData);
        }
    }
    
    /**
     * Check if session ID should be regenerated
     */
    private function shouldRegenerateId() {
        $now = time();
        $lastRegeneration = $_SESSION['last_regeneration'] ?? 0;
        
        return ($now - $lastRegeneration) > $this->securitySettings['regenerate_interval'];
    }
    
    /**
     * Regenerate session ID safely
     */
    private function regenerateSessionId() {
        $oldSessionId = session_id();
        
        // Generate new session ID
        session_regenerate_id(true);
        $newSessionId = session_id();
        
        $_SESSION['last_regeneration'] = time();
        
        // Update Redis
        if ($this->redis) {
            // Copy data to new key
            $sessionData = json_encode($_SESSION);
            $this->redis->setex('session:' . $newSessionId, $this->sessionTimeout, $sessionData);
            
            // Remove old key
            $this->redis->del('session:' . $oldSessionId);
        }
        
        $this->logSessionEvent('session_regenerated', [
            'old_session_id' => $oldSessionId,
            'new_session_id' => $newSessionId
        ]);
    }
    
    /**
     * Enforce concurrent session limits
     */
    private function enforceConcurrentSessionLimits($userId) {
        if (!$this->redis) return;
        
        $userSessionsKey = "user_sessions:{$userId}";
        $currentSessions = $this->redis->smembers($userSessionsKey);
        
        // Clean up expired sessions
        foreach ($currentSessions as $sessionId) {
            if (!$this->redis->exists("session:{$sessionId}")) {
                $this->redis->srem($userSessionsKey, $sessionId);
            }
        }
        
        // Get current active sessions
        $activeSessions = $this->redis->smembers($userSessionsKey);
        
        // If we exceed the limit, remove oldest sessions
        if (count($activeSessions) >= $this->maxConcurrentSessions) {
            $sessionsToRemove = array_slice($activeSessions, 0, -($this->maxConcurrentSessions - 1));
            
            foreach ($sessionsToRemove as $sessionId) {
                $this->redis->del("session:{$sessionId}");
                $this->redis->srem($userSessionsKey, $sessionId);
                
                $this->logSessionEvent('session_terminated_limit', [
                    'terminated_session_id' => $sessionId,
                    'user_id' => $userId
                ]);
            }
        }
        
        // Add current session
        $this->redis->sadd($userSessionsKey, session_id());
        $this->redis->expire($userSessionsKey, $this->sessionTimeout * 2);
    }
    
    /**
     * Destroy session completely
     */
    public function destroySession() {
        $sessionId = session_id();
        $userId = $_SESSION['user_id'] ?? null;
        
        // Clear session data
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Remove from Redis
        if ($this->redis && $sessionId) {
            $this->redis->del("session:{$sessionId}");
            
            if ($userId) {
                $this->redis->srem("user_sessions:{$userId}", $sessionId);
            }
        }
        
        // Destroy PHP session
        session_destroy();
        
        $this->logSessionEvent('session_destroyed', [
            'session_id' => $sessionId,
            'user_id' => $userId
        ]);
    }
    
    /**
     * Get all active sessions for a user
     */
    public function getUserActiveSessions($userId) {
        if (!$this->redis) {
            return [];
        }
        
        $userSessionsKey = "user_sessions:{$userId}";
        $sessionIds = $this->redis->smembers($userSessionsKey);
        
        $activeSessions = [];
        foreach ($sessionIds as $sessionId) {
            $sessionData = $this->redis->get("session:{$sessionId}");
            if ($sessionData) {
                $data = json_decode($sessionData, true);
                $activeSessions[] = [
                    'session_id' => $sessionId,
                    'created_at' => $data['created_at'],
                    'last_activity' => $data['last_activity'],
                    'ip_address' => $data['ip_address'],
                    'user_agent' => substr($data['user_agent'], 0, 100) . '...'
                ];
            }
        }
        
        return $activeSessions;
    }
    
    /**
     * Terminate specific session
     */
    public function terminateSession($sessionId, $userId = null) {
        if ($this->redis) {
            $this->redis->del("session:{$sessionId}");
            
            if ($userId) {
                $this->redis->srem("user_sessions:{$userId}", $sessionId);
            }
        }
        
        $this->logSessionEvent('session_terminated_manual', [
            'terminated_session_id' => $sessionId,
            'user_id' => $userId
        ]);
        
        return true;
    }
    
    /**
     * Terminate all sessions for a user (except current)
     */
    public function terminateAllUserSessions($userId, $exceptCurrent = true) {
        if (!$this->redis) return false;
        
        $currentSessionId = $exceptCurrent ? session_id() : null;
        $userSessionsKey = "user_sessions:{$userId}";
        $sessionIds = $this->redis->smembers($userSessionsKey);
        
        $terminatedCount = 0;
        foreach ($sessionIds as $sessionId) {
            if ($sessionId !== $currentSessionId) {
                $this->redis->del("session:{$sessionId}");
                $this->redis->srem($userSessionsKey, $sessionId);
                $terminatedCount++;
            }
        }
        
        $this->logSessionEvent('sessions_terminated_all', [
            'user_id' => $userId,
            'terminated_count' => $terminatedCount,
            'current_session_preserved' => $exceptCurrent
        ]);
        
        return $terminatedCount;
    }
    
    /**
     * Get CSRF token for current session
     */
    public function getCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCsrfToken($token) {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Session save handlers for Redis
     */
    public function open($savePath, $sessionName) {
        return true;
    }
    
    public function close() {
        return true;
    }
    
    public function read($sessionId) {
        if ($this->redis) {
            $data = $this->redis->get("session:{$sessionId}");
            return $data ? json_decode($data, true) : '';
        }
        
        // Fallback to database
        $stmt = $this->pdo->prepare("
            SELECT session_data 
            FROM user_sessions 
            WHERE session_id = ? AND expires > NOW()
        ");
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch();
        
        return $result ? $result['session_data'] : '';
    }
    
    public function write($sessionId, $sessionData) {
        $expires = time() + $this->sessionTimeout;
        
        if ($this->redis) {
            $this->redis->setex("session:{$sessionId}", $this->sessionTimeout, $sessionData);
            return true;
        }
        
        // Fallback to database
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (session_id, session_data, expires) 
            VALUES (?, ?, FROM_UNIXTIME(?))
            ON DUPLICATE KEY UPDATE 
            session_data = VALUES(session_data), 
            expires = VALUES(expires)
        ");
        
        return $stmt->execute([$sessionId, $sessionData, $expires]);
    }
    
    public function destroy($sessionId) {
        if ($this->redis) {
            $this->redis->del("session:{$sessionId}");
        }
        
        // Also remove from database
        $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        return $stmt->execute([$sessionId]);
    }
    
    public function gc($maxLifetime) {
        if ($this->redis) {
            // Redis handles TTL automatically
            return true;
        }
        
        // Clean up database
        $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires < NOW()");
        return $stmt->execute();
    }
    
    /**
     * Log session events for audit
     */
    private function logSessionEvent($event, $data = []) {
        try {
            $logData = [
                'event' => $event,
                'session_id' => session_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => $data
            ];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO session_audit_log 
                (event_type, session_id, ip_address, user_agent, event_data, created_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $event,
                $logData['session_id'],
                $logData['ip_address'],
                substr($logData['user_agent'], 0, 255),
                json_encode($data),
                $logData['timestamp']
            ]);
            
        } catch (Exception $e) {
            error_log("Session audit log failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get session statistics
     */
    public function getSessionStats() {
        $stats = [
            'active_sessions' => 0,
            'redis_available' => $this->redis !== null,
            'total_users_online' => 0
        ];
        
        if ($this->redis) {
            // Count active sessions
            $keys = $this->redis->keys('session:*');
            $stats['active_sessions'] = count($keys);
            
            // Count unique users
            $userKeys = $this->redis->keys('user_sessions:*');
            $stats['total_users_online'] = count($userKeys);
        } else {
            // Database fallback
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as active_sessions,
                       COUNT(DISTINCT JSON_EXTRACT(session_data, '$.user_id')) as total_users_online
                FROM user_sessions 
                WHERE expires > NOW()
            ");
            $result = $stmt->fetch();
            $stats['active_sessions'] = $result['active_sessions'];
            $stats['total_users_online'] = $result['total_users_online'];
        }
        
        return $stats;
    }
}

// Create session manager instance
$sessionManager = new SessionManager();

// Helper functions for global use
function startSecureSession($userId = null, $userType = null) {
    global $sessionManager;
    return $sessionManager->startSession($userId, $userType);
}

function destroySecureSession() {
    global $sessionManager;
    return $sessionManager->destroySession();
}

function getCsrfToken() {
    global $sessionManager;
    return $sessionManager->getCsrfToken();
}

function validateCsrfToken($token) {
    global $sessionManager;
    return $sessionManager->validateCsrfToken($token);
}
?>
