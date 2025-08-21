<?php
/**
 * Configurações de Produção - Blue Cleaning Services
 * Configure estas variáveis de acordo com seu servidor
 */

// Database Configuration - Updated for Hostinger
define('DB_HOST', $_ENV['DB_HOST'] ?? 'srv1417.hstgr.io');
define('DB_NAME', $_ENV['DB_DATABASE'] ?? 'u979853733_rose');
define('DB_USER', $_ENV['DB_USERNAME'] ?? 'u979853733_rose');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? 'BlueM@rketing33');

// Stripe Configuration
define('STRIPE_PUBLIC_KEY', $_ENV['STRIPE_PUBLIC_KEY'] ?? '');
define('STRIPE_SECRET_KEY', $_ENV['STRIPE_SECRET_KEY'] ?? '');
define('STRIPE_WEBHOOK_SECRET', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '');

// Site Configuration
define('SITE_URL', $_ENV['SITE_URL'] ?? 'https://localhost');
define('SITE_NAME', $_ENV['SITE_NAME'] ?? 'Blue Cleaning Services');
define('ADMIN_EMAIL', $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com');

// Security
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'change-this-in-production');
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'change-this-32-char-key-production');

// Debug Settings
// Debug Settings
$debug_env = $_ENV['DEBUG'] ?? 'false';
$show_errors_env = $_ENV['SHOW_ERRORS'] ?? 'false';

define('DEBUG', $debug_env === 'true' ? true : false);
define('SHOW_ERRORS', $show_errors_env === 'true' ? true : false);

// Error Reporting
if (!DEBUG) {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Timezone
date_default_timezone_set('Australia/Sydney');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    if (DEBUG) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact support.");
    }
}

/**
 * Utility Functions
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    // Australian phone validation
    return preg_match('/^(\+61|0)[2-478](?:[ -]?[0-9]){8}$/', $phone);
}

function validate_postcode($postcode) {
    // Australian postcode validation
    return preg_match('/^[0-9]{4}$/', $postcode);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function log_activity($user_id, $activity, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $activity, $details]);
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}
?>
