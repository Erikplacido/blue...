<?php
/**
 * Password Reset System
 * Blue Cleaning Services - Complete Authentication
 */

require_once __DIR__ . '/../../config/australian-environment.php';
require_once __DIR__ . '/../../config/australian-database.php';
require_once __DIR__ . '/../../config/email-config.php';
require_once __DIR__ . '/../../utils/security-helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

class PasswordResetManager {
    private PDO $db;
    private EmailService $emailService;
    
    public function __construct() {
        // Load Australian environment configuration
        AustralianEnvironmentConfig::load();
        
        // Use standardized database connection
        $this->db = AustralianDatabase::getInstance()->getConnection();
        $this->emailService = new EmailService();
    }
    
    public function requestPasswordReset(string $email, string $userType): array {
        try {
            // Validate user type
            if (!in_array($userType, ['customer', 'professional', 'admin'])) {
                return ['success' => false, 'message' => 'Invalid user type'];
            }
            
            // Get user from appropriate table
            $user = $this->findUser($email, $userType);
            if (!$user) {
                // Don't reveal if user exists - security measure
                return ['success' => true, 'message' => 'If the email exists, reset instructions have been sent'];
            }
            
            // Generate reset token
            $resetToken = $this->generateSecureToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset request
            $this->storeResetToken($user['id'], $userType, $resetToken, $expiresAt);
            
            // Send email
            $emailSent = $this->sendResetEmail($user, $resetToken, $userType);
            
            if ($emailSent) {
                $this->logSecurityEvent('password_reset_requested', $user['id'], $userType, [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                return ['success' => true, 'message' => 'Reset instructions have been sent to your email'];
            } else {
                return ['success' => false, 'message' => 'Failed to send reset email. Please try again later'];
            }
            
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again later'];
        }
    }
    
    public function resetPassword(string $token, string $newPassword): array {
        try {
            // Validate password strength
            $validation = $this->validatePasswordStrength($newPassword);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Find and validate reset token
            $resetRequest = $this->findValidResetToken($token);
            if (!$resetRequest) {
                return ['success' => false, 'message' => 'Invalid or expired reset token'];
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->updateUserPassword($resetRequest['user_id'], $resetRequest['user_type'], $hashedPassword);
            
            // Invalidate reset token and all user sessions
            $this->invalidateResetToken($token);
            $this->invalidateUserSessions($resetRequest['user_id'], $resetRequest['user_type']);
            
            // Log security event
            $this->logSecurityEvent('password_reset_completed', $resetRequest['user_id'], $resetRequest['user_type'], [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            // Send confirmation email
            $user = $this->getUserById($resetRequest['user_id'], $resetRequest['user_type']);
            $this->sendPasswordChangeConfirmation($user, $resetRequest['user_type']);
            
            return ['success' => true, 'message' => 'Password has been successfully reset'];
            
        } catch (Exception $e) {
            error_log("Password reset completion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again later'];
        }
    }
    
    public function verifyResetToken(string $token): array {
        try {
            $resetRequest = $this->findValidResetToken($token);
            
            return [
                'success' => true,
                'valid' => $resetRequest !== null,
                'expires_at' => $resetRequest ? $resetRequest['expires_at'] : null
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Token verification failed'];
        }
    }
    
    private function findUser(string $email, string $userType): ?array {
        $table = $this->getUserTable($userType);
        
        $stmt = $this->db->prepare("SELECT id, name, email FROM {$table} WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function getUserById(int $userId, string $userType): ?array {
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
    
    private function generateSecureToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    private function storeResetToken(int $userId, string $userType, string $token, string $expiresAt): void {
        // Clean old tokens for this user
        $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND user_type = ?");
        $stmt->execute([$userId, $userType]);
        
        // Insert new token
        $stmt = $this->db->prepare("
            INSERT INTO password_reset_tokens (user_id, user_type, token, expires_at, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $userType, $token, $expiresAt]);
    }
    
    private function findValidResetToken(string $token): ?array {
        $stmt = $this->db->prepare("
            SELECT user_id, user_type, expires_at
            FROM password_reset_tokens
            WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
        ");
        $stmt->execute([$token]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function invalidateResetToken(string $token): void {
        $stmt = $this->db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    private function updateUserPassword(int $userId, string $userType, string $hashedPassword): void {
        $table = $this->getUserTable($userType);
        
        $stmt = $this->db->prepare("UPDATE {$table} SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
    }
    
    private function invalidateUserSessions(int $userId, string $userType): void {
        // Invalidate all active sessions for this user
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET invalidated_at = NOW() 
            WHERE user_id = ? AND user_type = ? AND invalidated_at IS NULL
        ");
        $stmt->execute([$userId, $userType]);
    }
    
    private function validatePasswordStrength(string $password): array {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter'];
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one lowercase letter'];
        }
        
        if (!preg_match('/\d/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number'];
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one special character'];
        }
        
        return ['valid' => true, 'message' => 'Password meets all requirements'];
    }
    
    private function sendResetEmail(array $user, string $token, string $userType): bool {
        $resetUrl = AustralianEnvironmentConfig::get('APP_URL') . "/auth/reset-password.html?token={$token}&type={$userType}";
        
        $subject = 'Password Reset - Blue Cleaning Services';
        $template = $this->getResetEmailTemplate($user['name'], $resetUrl, $userType);
        
        return $this->emailService->sendEmail(
            $user['email'],
            $user['name'],
            $subject,
            $template
        );
    }
    
    private function sendPasswordChangeConfirmation(array $user, string $userType): bool {
        $subject = 'Password Changed - Blue Cleaning Services';
        $template = $this->getPasswordChangeTemplate($user['name'], $userType);
        
        return $this->emailService->sendEmail(
            $user['email'],
            $user['name'],
            $subject,
            $template
        );
    }
    
    private function getResetEmailTemplate(string $name, string $resetUrl, string $userType): string {
        $userTypeText = ucfirst($userType);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f8f9fa; }
                .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background: #343a40; color: white; padding: 20px; text-align: center; font-size: 12px; }
                .security-note { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                
                <div class='content'>
                    <h2>Hello {$name},</h2>
                    
                    <p>We received a request to reset your password for your <strong>{$userTypeText}</strong> account at Blue Cleaning Services.</p>
                    
                    <p>If you requested this password reset, click the button below to create a new password:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>Reset Your Password</a>
                    </div>
                    
                    <div class='security-note'>
                        <h3>🔒 Security Information:</h3>
                        <ul>
                            <li>This link will expire in <strong>1 hour</strong></li>
                            <li>If you didn't request this reset, you can safely ignore this email</li>
                            <li>Never share this link with anyone</li>
                            <li>Time sent: " . date('Y-m-d H:i:s T') . "</li>
                        </ul>
                    </div>
                    
                    <p>If the button doesn't work, copy and paste this URL into your browser:</p>
                    <p style='word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 3px;'>{$resetUrl}</p>
                    
                    <p>If you have any questions or concerns, please contact our support team.</p>
                    
                    <p>Best regards,<br>
                    The Blue Cleaning Services Team</p>
                </div>
                
                <div class='footer'>
                    <p>Blue Cleaning Services | Sydney, Australia</p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getPasswordChangeTemplate(string $name, string $userType): string {
        $userTypeText = ucfirst($userType);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Changed</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f8f9fa; }
                .footer { background: #343a40; color: white; padding: 20px; text-align: center; font-size: 12px; }
                .alert { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Password Successfully Changed</h1>
                </div>
                
                <div class='content'>
                    <h2>Hello {$name},</h2>
                    
                    <p>This email confirms that your password for your <strong>{$userTypeText}</strong> account has been successfully changed.</p>
                    
                    <div class='alert'>
                        <h3>🔐 Security Details:</h3>
                        <ul>
                            <li>Change completed: " . date('Y-m-d H:i:s T') . "</li>
                            <li>All your active sessions have been logged out for security</li>
                            <li>You'll need to log in again with your new password</li>
                        </ul>
                    </div>
                    
                    <p><strong>If you didn't make this change:</strong></p>
                    <ul>
                        <li>Contact our support team immediately</li>
                        <li>Your account may have been compromised</li>
                        <li>We'll help secure your account right away</li>
                    </ul>
                    
                    <p>For security reasons, if you have any concerns about your account, please contact us at support@bluecleaningservices.com.au</p>
                    
                    <p>Thank you for keeping your account secure!</p>
                    
                    <p>Best regards,<br>
                    The Blue Cleaning Services Team</p>
                </div>
                
                <div class='footer'>
                    <p>Blue Cleaning Services | Sydney, Australia</p>
                    <p>This is an automated security notification.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function logSecurityEvent(string $event, int $userId, string $userType, array $metadata): void {
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
    $passwordResetManager = new PasswordResetManager();
    
    // Rate limiting by IP
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!SecurityHelpers::checkRateLimit("password_reset_{$clientIP}", 10, 300)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many password reset attempts. Please try again later.']);
        exit;
    }
    
    switch ($method) {
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
                break;
            }
            
            $action = SecurityHelpers::getValidatedInput($input, 'action', 'string', '', 50, [
                'request_reset', 'reset_password', 'verify_token'
            ]);
            
            if (empty($action)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid or missing action']);
                break;
            }
            
            switch ($action) {
                case 'request_reset':
                    $email = SecurityHelpers::getValidatedInput($input, 'email', 'email');
                    $userType = SecurityHelpers::getValidatedInput($input, 'user_type', 'string', '', 20, [
                        'customer', 'professional', 'admin'
                    ]);
                    
                    if (!$email || !$userType) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Valid email and user type are required']);
                        break;
                    }
                    
                    $result = $passwordResetManager->requestPasswordReset($email, $userType);
                    echo json_encode($result);
                    break;
                    
                case 'reset_password':
                    $token = SecurityHelpers::getValidatedInput($input, 'token', 'string', '', 64);
                    $newPassword = SecurityHelpers::getValidatedInput($input, 'new_password', 'string', '', 128);
                    
                    if (!$token || !$newPassword) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Token and new password are required']);
                        break;
                    }
                    
                    // Additional validation for password length
                    if (strlen($newPassword) < 8 || strlen($newPassword) > 128) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Password must be between 8 and 128 characters']);
                        break;
                    }
                    
                    $result = $passwordResetManager->resetPassword($token, $newPassword);
                    echo json_encode($result);
                    break;
                    
                case 'verify_token':
                    $token = SecurityHelpers::getValidatedInput($input, 'token', 'string', '', 64);
                    
                    if (!$token) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Token is required']);
                        break;
                    }
                    
                    $result = $passwordResetManager->verifyResetToken($token);
                    echo json_encode($result);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    // Log security event for debugging
    SecurityHelpers::logSecurityEvent('password_reset_api_error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 'ERROR');
    
    error_log("Password reset API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
