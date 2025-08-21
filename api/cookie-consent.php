<?php
/**
 * Cookie Consent Management System
 * LGPD/GDPR Compliant Cookie Banner
 * Blue Cleaning Services
 */

require_once __DIR__ . '/../config/australian-environment.php';
require_once __DIR__ . '/../utils/security-helpers.php';

class CookieConsentManager {
    private $db;
    
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
    }
    
    public function hasValidConsent($userId = null) {
        if (!$userId && isset($_COOKIE['consent_id'])) {
            $consentId = $_COOKIE['consent_id'];
            $stmt = $this->db->prepare("
                SELECT * FROM cookie_consents 
                WHERE consent_id = ? AND expires_at > NOW()
            ");
            $stmt->execute([$consentId]);
        } elseif ($userId) {
            $stmt = $this->db->prepare("
                SELECT * FROM cookie_consents 
                WHERE user_id = ? AND expires_at > NOW()
                ORDER BY consented_at DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
        } else {
            return false;
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
    
    public function saveConsent($consentData, $userId = null) {
        $consentId = uniqid('consent_', true);
        
        $stmt = $this->db->prepare("
            INSERT INTO cookie_consents 
            (consent_id, user_id, necessary, functional, analytics, marketing, 
             consented_at, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 13 MONTH), ?, ?)
        ");
        
        $result = $stmt->execute([
            $consentId,
            $userId,
            1, // Necessary cookies always enabled
            $consentData['functional'] ? 1 : 0,
            $consentData['analytics'] ? 1 : 0,
            $consentData['marketing'] ? 1 : 0,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        if ($result) {
            // Set consent cookie
            setcookie('consent_id', $consentId, time() + (13 * 30 * 24 * 60 * 60), '/', '', true, true);
            setcookie('cookie_consent', json_encode($consentData), time() + (13 * 30 * 24 * 60 * 60), '/', '', true, false);
        }
        
        return $result;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manager = new CookieConsentManager();
    
    // Secure input validation
    $action = SecurityHelpers::getPostInput('action', 'string', '', 50, [
        'save_consent', 'check_consent'
    ]);
    
    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }
    
    // Rate limiting
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!SecurityHelpers::checkRateLimit("cookie_api_{$clientIP}", 20, 300)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }
    
    switch ($action) {
        case 'save_consent':
            $consentData = [
                'functional' => SecurityHelpers::getPostInput('functional', 'bool', false),
                'analytics' => SecurityHelpers::getPostInput('analytics', 'bool', false),
                'marketing' => SecurityHelpers::getPostInput('marketing', 'bool', false)
            ];
            $userId = $_SESSION['user_id'] ?? null;
            
            $result = $manager->saveConsent($consentData, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        case 'check_consent':
            $userId = $_SESSION['user_id'] ?? null;
            $hasConsent = $manager->hasValidConsent($userId);
            echo json_encode(['has_consent' => $hasConsent]);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
            break;
    }
    exit;
}

$manager = new CookieConsentManager();
$userId = $_SESSION['user_id'] ?? null;
$needsConsent = !$manager->hasValidConsent($userId);
?>

<?php if ($needsConsent): ?>
<!-- Cookie Consent Banner -->
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 bg-gray-900 text-white z-50 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div class="flex-1 mb-4 lg:mb-0">
                <h3 class="text-lg font-semibold mb-2">🍪 Cookie Settings</h3>
                <p class="text-sm text-gray-300 mb-3">
                    We use cookies to enhance your experience, analyze traffic, and personalize content. 
                    You can customize your preferences or accept all cookies.
                </p>
                <div class="space-y-1 text-xs">
                    <p><strong>Necessary:</strong> Essential for website functionality (always enabled)</p>
                    <p><strong>Functional:</strong> Remember your preferences and settings</p>
                    <p><strong>Analytics:</strong> Help us understand website usage with Google Analytics</p>
                    <p><strong>Marketing:</strong> Show relevant ads and measure campaign effectiveness</p>
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                <button onclick="showCookieDetails()" 
                        class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-600 transition-colors">
                    Customize
                </button>
                <button onclick="acceptAllCookies()" 
                        class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                    Accept All
                </button>
                <button onclick="acceptNecessaryOnly()" 
                        class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-500 transition-colors">
                    Necessary Only
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cookie Preferences Modal -->
<div id="cookie-preferences-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-60">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-96 overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Cookie Preferences</h2>
                    <button onclick="closeCookieModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <!-- Necessary Cookies -->
                    <div class="border-b pb-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-medium">Necessary Cookies</h3>
                            <input type="checkbox" checked disabled class="toggle">
                        </div>
                        <p class="text-sm text-gray-600">
                            Essential for website functionality. These cannot be disabled as they are required 
                            for basic website operations like security, authentication, and form submissions.
                        </p>
                        <div class="mt-2 text-xs text-gray-500">
                            <strong>Examples:</strong> Session cookies, CSRF tokens, authentication cookies
                        </div>
                    </div>
                    
                    <!-- Functional Cookies -->
                    <div class="border-b pb-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-medium">Functional Cookies</h3>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="functional-toggle" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <p class="text-sm text-gray-600">
                            Remember your preferences, language settings, and provide enhanced functionality.
                        </p>
                        <div class="mt-2 text-xs text-gray-500">
                            <strong>Examples:</strong> Language preferences, theme settings, form auto-fill
                        </div>
                    </div>
                    
                    <!-- Analytics Cookies -->
                    <div class="border-b pb-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-medium">Analytics Cookies</h3>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="analytics-toggle" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <p class="text-sm text-gray-600">
                            Help us understand how visitors interact with our website through Google Analytics.
                        </p>
                        <div class="mt-2 text-xs text-gray-500">
                            <strong>Provider:</strong> Google Analytics 4
                            <br><strong>Data:</strong> Page views, session duration, traffic sources (anonymized)
                        </div>
                    </div>
                    
                    <!-- Marketing Cookies -->
                    <div class="pb-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-medium">Marketing Cookies</h3>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="marketing-toggle" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <p class="text-sm text-gray-600">
                            Show relevant advertisements and measure campaign effectiveness.
                        </p>
                        <div class="mt-2 text-xs text-gray-500">
                            <strong>Providers:</strong> Google Ads, Facebook Pixel
                            <br><strong>Data:</strong> Ad interactions, conversion tracking, audience building
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center pt-4">
                        <a href="/privacy-policy.html" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                            Read our Privacy Policy
                        </a>
                        <div class="space-x-3">
                            <button onclick="closeCookieModal()" 
                                    class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                Cancel
                            </button>
                            <button onclick="saveCustomPreferences()" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Save Preferences
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class CookieConsentManager {
    constructor() {
        this.initializeGoogleConsent();
    }
    
    initializeGoogleConsent() {
        // Initialize Google Consent Mode
        if (window.gtag) {
            window.gtag('consent', 'default', {
                'analytics_storage': 'denied',
                'ad_storage': 'denied',
                'functionality_storage': 'denied',
                'personalization_storage': 'denied'
            });
        }
    }
    
    showCookieDetails() {
        document.getElementById('cookie-preferences-modal').classList.remove('hidden');
    }
    
    closeCookieModal() {
        document.getElementById('cookie-preferences-modal').classList.add('hidden');
    }
    
    hideCookieBanner() {
        document.getElementById('cookie-banner').style.display = 'none';
    }
    
    async saveConsent(consentData) {
        try {
            const response = await fetch('/api/cookie-consent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'save_consent',
                    functional: consentData.functional.toString(),
                    analytics: consentData.analytics.toString(),
                    marketing: consentData.marketing.toString()
                })
            });
            
            const result = await response.json();
            if (result.success) {
                this.updateGoogleConsent(consentData);
                this.hideCookieBanner();
                this.closeCookieModal();
                
                // Reload page to apply cookie preferences
                setTimeout(() => window.location.reload(), 1000);
            }
        } catch (error) {
            console.error('Failed to save cookie consent:', error);
        }
    }
    
    updateGoogleConsent(consent) {
        if (window.gtag) {
            window.gtag('consent', 'update', {
                'analytics_storage': consent.analytics ? 'granted' : 'denied',
                'ad_storage': consent.marketing ? 'granted' : 'denied',
                'functionality_storage': consent.functional ? 'granted' : 'denied',
                'personalization_storage': consent.marketing ? 'granted' : 'denied'
            });
        }
    }
    
    acceptAllCookies() {
        const consentData = {
            functional: true,
            analytics: true,
            marketing: true
        };
        this.saveConsent(consentData);
    }
    
    acceptNecessaryOnly() {
        const consentData = {
            functional: false,
            analytics: false,
            marketing: false
        };
        this.saveConsent(consentData);
    }
    
    saveCustomPreferences() {
        const consentData = {
            functional: document.getElementById('functional-toggle').checked,
            analytics: document.getElementById('analytics-toggle').checked,
            marketing: document.getElementById('marketing-toggle').checked
        };
        this.saveConsent(consentData);
    }
    
    // Load existing preferences
    loadExistingConsent() {
        const consentCookie = this.getCookie('cookie_consent');
        if (consentCookie) {
            try {
                const consent = JSON.parse(consentCookie);
                document.getElementById('functional-toggle').checked = consent.functional;
                document.getElementById('analytics-toggle').checked = consent.analytics;
                document.getElementById('marketing-toggle').checked = consent.marketing;
                
                this.updateGoogleConsent(consent);
            } catch (error) {
                console.error('Failed to parse consent cookie:', error);
            }
        }
    }
    
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
}

// Initialize cookie consent manager
const cookieConsentManager = new CookieConsentManager();

// Global functions for HTML onclick handlers
function showCookieDetails() {
    cookieConsentManager.showCookieDetails();
    cookieConsentManager.loadExistingConsent();
}

function closeCookieModal() {
    cookieConsentManager.closeCookieModal();
}

function acceptAllCookies() {
    cookieConsentManager.acceptAllCookies();
}

function acceptNecessaryOnly() {
    cookieConsentManager.acceptNecessaryOnly();
}

function saveCustomPreferences() {
    cookieConsentManager.saveCustomPreferences();
}

// Load existing consent on page load
document.addEventListener('DOMContentLoaded', function() {
    cookieConsentManager.loadExistingConsent();
});

// Add privacy settings link to footer
document.addEventListener('DOMContentLoaded', function() {
    const footer = document.querySelector('footer');
    if (footer) {
        const privacyLink = document.createElement('a');
        privacyLink.href = '#';
        privacyLink.onclick = showCookieDetails;
        privacyLink.className = 'text-blue-600 hover:text-blue-800 text-sm';
        privacyLink.textContent = 'Cookie Preferences';
        
        // Add to existing footer links
        const footerLinks = footer.querySelector('.space-x-4') || footer;
        if (footerLinks) {
            footerLinks.appendChild(privacyLink);
        }
    }
});
</script>

<style>
/* Cookie banner animations */
#cookie-banner {
    animation: slideUp 0.5s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Toggle switch styling */
.toggle {
    appearance: none;
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background-color: #e5e7eb;
    position: relative;
    cursor: pointer;
    transition: background-color 0.2s;
}

.toggle:checked {
    background-color: #3b82f6;
}

.toggle::before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: white;
    top: 2px;
    left: 2px;
    transition: transform 0.2s;
}

.toggle:checked::before {
    transform: translateX(20px);
}
</style>
<?php endif; ?>
