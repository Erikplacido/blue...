<?php
/**
 * Two-Factor Authentication System
 * Blue Cleaning Services - Enhanced Security
 */

require_once __DIR__ . '/../../config/australian-environment.php';
require_once __DIR__ . '/../../config/email-config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

class TwoFactorAuthManager {
    private PDO $db;
    private EmailService $emailService;
    
    public function __construct() {
        // Load Australian environment configuration
        AustralianEnvironmentConfig::load();
        
        $dbConfig = AustralianEnvironmentConfig::getDatabase();
        $this->db = new PDO(
            "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        $this->emailService = new EmailService();
    }
    
    public function enableTwoFactor(int $userId, string $userType): array {
        try {
            // Generate TOTP secret
            $secret = $this->generateTOTPSecret();
            $qrCode = $this->generateQRCode($userId, $userType, $secret);
            
            // Store temporary secret (not activated yet)
            $stmt = $this->db->prepare("
                INSERT INTO two_factor_auth (user_id, user_type, secret_key, enabled, created_at)
                VALUES (?, ?, ?, 0, NOW())
                ON DUPLICATE KEY UPDATE secret_key = VALUES(secret_key), enabled = 0, created_at = NOW()
            ");
            $stmt->execute([$userId, $userType, $secret]);
            
            return [
                'success' => true,
                'secret' => $secret,
                'qr_code' => $qrCode,
                'manual_entry_key' => $this->formatSecretForManualEntry($secret),
                'message' => 'Scan QR code with your authenticator app'
            ];
            
        } catch (Exception $e) {
            error_log("2FA enable error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to enable 2FA'];
        }
    }
    
    public function verifyAndActivateTwoFactor(int $userId, string $userType, string $code): array {
        try {
            // Get pending 2FA setup
            $stmt = $this->db->prepare("
                SELECT secret_key FROM two_factor_auth 
                WHERE user_id = ? AND user_type = ? AND enabled = 0
            ");
            $stmt->execute([$userId, $userType]);
            $twoFA = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$twoFA) {
                return ['success' => false, 'message' => '2FA setup not found'];
            }
            
            // Verify TOTP code
            if (!$this->verifyTOTPCode($twoFA['secret_key'], $code)) {
                return ['success' => false, 'message' => 'Invalid verification code'];
            }
            
            // Activate 2FA
            $stmt = $this->db->prepare("
                UPDATE two_factor_auth 
                SET enabled = 1, activated_at = NOW()
                WHERE user_id = ? AND user_type = ?
            ");
            $stmt->execute([$userId, $userType]);
            
            // Generate backup codes
            $backupCodes = $this->generateBackupCodes($userId, $userType);
            
            // Log security event
            $this->logSecurityEvent('2fa_activated', $userId, $userType);
            
            // Send confirmation email
            $user = $this->getUser($userId, $userType);
            $this->send2FAActivationEmail($user, $userType, $backupCodes);
            
            return [
                'success' => true,
                'message' => '2FA has been successfully activated',
                'backup_codes' => $backupCodes
            ];
            
        } catch (Exception $e) {
            error_log("2FA activation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to activate 2FA'];
        }
    }
    
    public function verifyTwoFactorCode(int $userId, string $userType, string $code): array {
        try {
            // Get user's 2FA settings
            $stmt = $this->db->prepare("
                SELECT secret_key FROM two_factor_auth 
                WHERE user_id = ? AND user_type = ? AND enabled = 1
            ");
            $stmt->execute([$userId, $userType]);
            $twoFA = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$twoFA) {
                return ['success' => false, 'message' => '2FA not enabled for this account'];
            }
            
            // Check if it's a backup code first
            if ($this->verifyBackupCode($userId, $userType, $code)) {
                return ['success' => true, 'message' => 'Backup code verified'];
            }
            
            // Verify TOTP code
            if ($this->verifyTOTPCode($twoFA['secret_key'], $code)) {
                return ['success' => true, 'message' => 'Verification successful'];
            }
            
            return ['success' => false, 'message' => 'Invalid verification code'];
            
        } catch (Exception $e) {
            error_log("2FA verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }
    
    public function disableTwoFactor(int $userId, string $userType, string $password): array {
        try {
            // Verify password before disabling
            if (!$this->verifyUserPassword($userId, $userType, $password)) {
                return ['success' => false, 'message' => 'Invalid password'];
            }
            
            // Disable 2FA
            $stmt = $this->db->prepare("
                UPDATE two_factor_auth 
                SET enabled = 0, disabled_at = NOW()
                WHERE user_id = ? AND user_type = ?
            ");
            $stmt->execute([$userId, $userType]);
            
            // Invalidate all backup codes
            $stmt = $this->db->prepare("
                UPDATE two_factor_backup_codes 
                SET used_at = NOW()
                WHERE user_id = ? AND user_type = ? AND used_at IS NULL
            ");
            $stmt->execute([$userId, $userType]);
            
            // Log security event
            $this->logSecurityEvent('2fa_disabled', $userId, $userType);
            
            // Send notification email
            $user = $this->getUser($userId, $userType);
            $this->send2FADisabledEmail($user, $userType);
            
            return ['success' => true, 'message' => '2FA has been disabled'];
            
        } catch (Exception $e) {
            error_log("2FA disable error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to disable 2FA'];
        }
    }
    
    public function getTwoFactorStatus(int $userId, string $userType): array {
        try {
            $stmt = $this->db->prepare("
                SELECT enabled, activated_at FROM two_factor_auth 
                WHERE user_id = ? AND user_type = ?
            ");
            $stmt->execute([$userId, $userType]);
            $twoFA = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$twoFA) {
                return [
                    'success' => true,
                    'enabled' => false,
                    'setup_required' => true
                ];
            }
            
            return [
                'success' => true,
                'enabled' => (bool)$twoFA['enabled'],
                'activated_at' => $twoFA['activated_at'],
                'setup_required' => false
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to get 2FA status'];
        }
    }
    
    public function generateNewBackupCodes(int $userId, string $userType): array {
        try {
            // Invalidate old backup codes
            $stmt = $this->db->prepare("
                UPDATE two_factor_backup_codes 
                SET used_at = NOW()
                WHERE user_id = ? AND user_type = ? AND used_at IS NULL
            ");
            $stmt->execute([$userId, $userType]);
            
            // Generate new backup codes
            $backupCodes = $this->generateBackupCodes($userId, $userType);
            
            // Log security event
            $this->logSecurityEvent('2fa_backup_codes_regenerated', $userId, $userType);
            
            return [
                'success' => true,
                'backup_codes' => $backupCodes,
                'message' => 'New backup codes generated'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to generate backup codes'];
        }
    }
    
    private function generateTOTPSecret(): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    private function generateQRCode(int $userId, string $userType, string $secret): string {
        $user = $this->getUser($userId, $userType);
        $issuer = 'Blue Cleaning Services';
        $label = $user['email'] . " ({$userType})";
        
        $otpauthUrl = "otpauth://totp/{$label}?secret={$secret}&issuer=" . urlencode($issuer);
        
        // Using a simple QR code generation - in production, use a proper QR library
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauthUrl);
        
        return $qrUrl;
    }
    
    private function formatSecretForManualEntry(string $secret): string {
        return implode(' ', str_split($secret, 4));
    }
    
    private function verifyTOTPCode(string $secret, string $code): bool {
        $timeSlice = floor(time() / 30);
        
        // Check current time slice and adjacent ones for clock drift tolerance
        for ($i = -1; $i <= 1; $i++) {
            if ($this->generateTOTPCode($secret, $timeSlice + $i) === $code) {
                return true;
            }
        }
        
        return false;
    }
    
    private function generateTOTPCode(string $secret, int $timeSlice): string {
        $secretBinary = $this->base32Decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretBinary, true);
        $offset = ord($hash[19]) & 0x0f;
        
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    private function base32Decode(string $secret): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $decoded = '';
        
        for ($i = 0; $i < strlen($secret); $i += 8) {
            $chunk = substr($secret, $i, 8);
            $binary = '';
            
            for ($j = 0; $j < strlen($chunk); $j++) {
                $val = strpos($chars, $chunk[$j]);
                $binary .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
            }
            
            while (strlen($binary) >= 8) {
                $decoded .= chr(bindec(substr($binary, 0, 8)));
                $binary = substr($binary, 8);
            }
        }
        
        return $decoded;
    }
    
    private function generateBackupCodes(int $userId, string $userType): array {
        $codes = [];
        
        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = $code;
            
            $stmt = $this->db->prepare("
                INSERT INTO two_factor_backup_codes (user_id, user_type, code, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $userType, $code]);
        }
        
        return $codes;
    }
    
    private function verifyBackupCode(int $userId, string $userType, string $code): bool {
        $stmt = $this->db->prepare("
            SELECT id FROM two_factor_backup_codes 
            WHERE user_id = ? AND user_type = ? AND code = ? AND used_at IS NULL
        ");
        $stmt->execute([$userId, $userType, strtoupper($code)]);
        $backupCode = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$backupCode) {
            return false;
        }
        
        // Mark backup code as used
        $stmt = $this->db->prepare("
            UPDATE two_factor_backup_codes 
            SET used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$backupCode['id']]);
        
        // Log backup code usage
        $this->logSecurityEvent('2fa_backup_code_used', $userId, $userType);
        
        return true;
    }
    
    private function verifyUserPassword(int $userId, string $userType, string $password): bool {
        $table = $this->getUserTable($userType);
        
        $stmt = $this->db->prepare("SELECT password FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        $hashedPassword = $stmt->fetchColumn();
        
        return $hashedPassword && password_verify($password, $hashedPassword);
    }
    
    private function getUser(int $userId, string $userType): ?array {
        $table = $this->getUserTable($userType);
        
        $stmt = $this->db->prepare("SELECT id, name, email FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function getUserTable(string $userType): string {
        return match($userType) {
            'customer' => 'customers',
            'professional' => 'professionals',
            'admin' => 'admin_users',
            default => throw new InvalidArgumentException("Invalid user type: {$userType}")
        };
    }
    
    private function send2FAActivationEmail(array $user, string $userType, array $backupCodes): void {
        $subject = '2FA Activated - Blue Cleaning Services';
        $backupCodesHtml = implode('<br>', array_map(fn($code) => "<code>{$code}</code>", $backupCodes));
        
        $template = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>2FA Activated</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f8f9fa; }
                .backup-codes { background: #fff; border: 2px solid #007bff; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                code { background: #e9ecef; padding: 5px 8px; border-radius: 3px; font-family: monospace; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Two-Factor Authentication Activated</h1>
                </div>
                
                <div class='content'>
                    <h2>Hello {$user['name']},</h2>
                    
                    <p>Great news! Two-factor authentication has been successfully activated for your <strong>" . ucfirst($userType) . "</strong> account.</p>
                    
                    <p>Your account is now more secure with an additional layer of protection.</p>
                    
                    <div class='backup-codes'>
                        <h3>🔑 Backup Codes</h3>
                        <p>Save these backup codes in a safe place. You can use them to access your account if you lose your authenticator device:</p>
                        <p style='font-family: monospace; font-size: 16px;'>{$backupCodesHtml}</p>
                    </div>
                    
                    <div class='warning'>
                        <h3>⚠️ Important Security Notes:</h3>
                        <ul>
                            <li>Each backup code can only be used once</li>
                            <li>Store these codes securely and separately from your device</li>
                            <li>You can generate new backup codes anytime in your account settings</li>
                            <li>If you suspect your account is compromised, contact support immediately</li>
                        </ul>
                    </div>
                    
                    <p><strong>Next time you log in:</strong></p>
                    <ol>
                        <li>Enter your email and password</li>
                        <li>Open your authenticator app</li>
                        <li>Enter the 6-digit code</li>
                        <li>You're securely logged in!</li>
                    </ol>
                    
                    <p>If you have any questions about 2FA, please contact our support team.</p>
                    
                    <p>Stay secure!<br>
                    The Blue Cleaning Services Team</p>
                </div>
            </div>
        </body>
        </html>";
        
        $this->emailService->sendEmail($user['email'], $user['name'], $subject, $template);
    }
    
    private function send2FADisabledEmail(array $user, string $userType): void {
        $subject = '2FA Disabled - Blue Cleaning Services';
        
        $template = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>2FA Disabled</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f8f9fa; }
                .warning { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔓 Two-Factor Authentication Disabled</h1>
                </div>
                
                <div class='content'>
                    <h2>Hello {$user['name']},</h2>
                    
                    <p>This email confirms that two-factor authentication has been <strong>disabled</strong> for your " . ucfirst($userType) . " account.</p>
                    
                    <div class='warning'>
                        <h3>⚠️ Security Notice:</h3>
                        <p>Your account is now less secure without 2FA. We strongly recommend re-enabling it as soon as possible.</p>
                    </div>
                    
                    <p><strong>If you didn't disable 2FA:</strong></p>
                    <ul>
                        <li>Your account may be compromised</li>
                        <li>Contact our support team immediately</li>
                        <li>Change your password right away</li>
                        <li>Review your recent account activity</li>
                    </ul>
                    
                    <p>You can re-enable 2FA anytime in your account security settings.</p>
                    
                    <p>Stay safe!<br>
                    The Blue Cleaning Services Team</p>
                </div>
            </div>
        </body>
        </html>";
        
        $this->emailService->sendEmail($user['email'], $user['name'], $subject, $template);
    }
    
    private function logSecurityEvent(string $event, int $userId, string $userType, array $metadata = []): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_audit_log (user_id, user_type, event_type, metadata, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $userType,
                $event,
                json_encode($metadata),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
}

// Handle API requests
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $twoFactorManager = new TwoFactorAuthManager();
    
    switch ($method) {
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            $userId = $input['user_id'] ?? 0;
            $userType = $input['user_type'] ?? '';
            
            if (!$userId || !$userType) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID and type are required']);
                exit;
            }
            
            switch ($action) {
                case 'enable':
                    $result = $twoFactorManager->enableTwoFactor($userId, $userType);
                    echo json_encode($result);
                    break;
                    
                case 'verify_setup':
                    $code = $input['code'] ?? '';
                    if (!$code) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Verification code is required']);
                        break;
                    }
                    
                    $result = $twoFactorManager->verifyAndActivateTwoFactor($userId, $userType, $code);
                    echo json_encode($result);
                    break;
                    
                case 'verify':
                    $code = $input['code'] ?? '';
                    if (!$code) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Verification code is required']);
                        break;
                    }
                    
                    $result = $twoFactorManager->verifyTwoFactorCode($userId, $userType, $code);
                    echo json_encode($result);
                    break;
                    
                case 'disable':
                    $password = $input['password'] ?? '';
                    if (!$password) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Password is required']);
                        break;
                    }
                    
                    $result = $twoFactorManager->disableTwoFactor($userId, $userType, $password);
                    echo json_encode($result);
                    break;
                    
                case 'generate_backup_codes':
                    $result = $twoFactorManager->generateNewBackupCodes($userId, $userType);
                    echo json_encode($result);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;
            
        case 'GET':
            $userId = $_GET['user_id'] ?? 0;
            $userType = $_GET['user_type'] ?? '';
            
            if (!$userId || !$userType) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID and type are required']);
                exit;
            }
            
            $result = $twoFactorManager->getTwoFactorStatus($userId, $userType);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("2FA API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
