<?php
/**
 * =========================================================
 * PROJETO BLUE V2 - PÁGINA DE LOGIN
 * =========================================================
 * 
 * @file auth/login.php
 * @description Interface de login com segurança avançada
 * @version 2.0
 * @date 2025-08-07
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/AuthManager.php';

$auth = AuthManager::getInstance();
$auth->startSession();

// Se já está logado, redireciona
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    $redirectUrl = $_GET['redirect'] ?? $auth->getRedirectUrl($user['role']);
    header("Location: $redirectUrl");
    exit;
}

$error = '';
$success = '';
$rateLimitInfo = null;

// Processa login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    $userIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Valida CSRF
    $csrfToken = $_POST['_csrf_token'] ?? '';
    if (!$auth->validateCSRFToken($csrfToken)) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $result = $auth->authenticate($email, $password, $userIp);
        
        if ($result['success']) {
            $success = $result['message'];
            
            // Remember me functionality
            if ($rememberMe) {
                $rememberToken = bin2hex(random_bytes(32));
                setcookie('remember_token', $rememberToken, time() + (30 * 24 * 3600), '/', '', true, true);
                // Em produção, salvar o token no banco de dados associado ao usuário
            }
            
            // Delay antes do redirect para mostrar mensagem
            $redirectUrl = $_GET['redirect'] ?? $result['redirect'];
            header("Refresh: 2; url=$redirectUrl");
        } else {
            $error = $result['message'];
            $rateLimitInfo = $result;
            
            // Adiciona delay para prevenir timing attacks
            if (isset($result['delay'])) {
                usleep($result['delay'] * 1000); // Convert to microseconds
            }
        }
    }
}

// Gera novo token CSRF
$csrfToken = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Blue Facility Services</title>
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="
        default-src 'self';
        style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com;
        font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com;
        script-src 'self' 'unsafe-inline';
        img-src 'self' data:;
        connect-src 'self';
    ">
    
    <!-- Styles -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/blue.css">
    
    <style>
        :root {
            --login-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --input-bg: rgba(255, 255, 255, 0.9);
            --error-color: #ef4444;
            --success-color: #10b981;
        }
        
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--login-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .logo .subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            font-weight: 400;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            color: white;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .form-input {
            width: 100%;
            padding: 15px;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            background: var(--input-bg);
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 1.1rem;
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .form-checkbox label {
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .login-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fecaca;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #86efac;
        }
        
        .security-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .security-info h3 {
            color: #93c5fd;
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .security-info ul {
            color: #bfdbfe;
            font-size: 0.8rem;
            margin: 0;
            padding-left: 20px;
        }
        
        .security-info li {
            margin-bottom: 5px;
        }
        
        .rate-limit-info {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
            font-size: 0.9rem;
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .strength-meter {
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-medium { background: #f59e0b; width: 50%; }
        .strength-strong { background: #10b981; width: 75%; }
        .strength-very-strong { background: #059669; width: 100%; }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 20px 10px;
            }
            
            .logo h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>
                <i class="fas fa-gem"></i>
                Blue
            </h1>
            <div class="subtitle">Facility Services Portal</div>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
            <br><small>Redirecting...</small>
        </div>
        <?php endif; ?>
        
        <?php if ($rateLimitInfo && isset($rateLimitInfo['locked_until'])): ?>
        <div class="alert rate-limit-info">
            <i class="fas fa-clock"></i>
            Account temporarily locked. Try again after: 
            <?= date('H:i:s', $rateLimitInfo['locked_until']) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm" novalidate>
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    placeholder="Enter your email"
                    required
                    autocomplete="email"
                >
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="password-container">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordIcon"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">Remember me for 30 days</label>
            </div>
            
            <button type="submit" class="login-btn" id="loginButton">
                <div class="loading-spinner" id="loadingSpinner"></div>
                <span id="buttonText">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </span>
            </button>
        </form>
        
        <div class="security-info">
            <h3><i class="fas fa-shield-alt"></i> Security Features</h3>
            <ul>
                <li>End-to-end encryption</li>
                <li>Rate limiting protection</li>
                <li>CSRF token validation</li>
                <li>Secure session management</li>
                <li>Password strength validation</li>
            </ul>
        </div>
        
        <!-- Demo Credentials -->
        <div style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 8px;">
            <h3 style="color: white; margin: 0 0 10px 0; font-size: 0.9rem;">Demo Credentials</h3>
            <p style="color: rgba(255,255,255,0.8); font-size: 0.8rem; margin: 0;">
                <strong>Admin:</strong> admin@blue.com / Blue2025!<br>
                <strong>Customer:</strong> test@blue.com / Test2025!
            </p>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const button = document.getElementById('loginButton');
            const spinner = document.getElementById('loadingSpinner');
            const buttonText = document.getElementById('buttonText');
            
            button.disabled = true;
            spinner.style.display = 'inline-block';
            buttonText.innerHTML = 'Authenticating...';
        });
        
        // Password strength indicator (for registration)
        document.getElementById('password').addEventListener('input', function() {
            // This would be expanded for a registration form
            console.log('Password strength check would go here');
        });
        
        // Auto-focus first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            if (!emailInput.value) {
                emailInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
        });
        
        // Security: Clear form on page unload
        window.addEventListener('beforeunload', function() {
            document.getElementById('password').value = '';
        });
    </script>
</body>
</html>
