<?php
/**
 * Privacy & Compliance Management System
 * LGPD (Brazil) and GDPR (EU) Compliance
 * Blue Cleaning Services
 */

require_once __DIR__ . '/../config/australian-environment.php';
require_once __DIR__ . '/../utils/security-helpers.php';

class PrivacyComplianceSystem {
    private $db;
    private $logger;
    
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
        $this->logger = new Logger('privacy-compliance');
    }
    
    /**
     * Record user consent for data processing
     */
    public function recordConsent($userId, $consentData) {
        $stmt = $this->db->prepare("
            INSERT INTO privacy_consents 
            (user_id, consent_type, consent_version, consented_at, ip_address, user_agent, consent_data)
            VALUES (?, ?, ?, NOW(), ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $userId,
            $consentData['type'],
            $consentData['version'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            json_encode($consentData)
        ]);
        
        if ($result) {
            $this->logger->info("Consent recorded", [
                'user_id' => $userId,
                'consent_type' => $consentData['type'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get user's current consents
     */
    public function getUserConsents($userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM privacy_consents 
            WHERE user_id = ? 
            ORDER BY consented_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process data subject access request (LGPD Art. 18, GDPR Art. 15)
     */
    public function processDataAccessRequest($userId) {
        $userData = [];
        
        // User profile data
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData['profile'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Booking history
        $stmt = $this->db->prepare("
            SELECT b.*, s.name as service_name 
            FROM bookings b 
            LEFT JOIN services s ON b.service_id = s.id 
            WHERE b.customer_id = ?
        ");
        $stmt->execute([$userId]);
        $userData['bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Payment history
        $stmt = $this->db->prepare("
            SELECT p.* FROM payments p 
            JOIN bookings b ON p.booking_id = b.id 
            WHERE b.customer_id = ?
        ");
        $stmt->execute([$userId]);
        $userData['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Privacy consents
        $userData['consents'] = $this->getUserConsents($userId);
        
        // Analytics data
        $stmt = $this->db->prepare("
            SELECT event_type, event_data, created_at 
            FROM analytics_events 
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $userData['analytics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log the access request
        $this->logger->info("Data access request processed", [
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        return $userData;
    }
    
    /**
     * Process data erasure request (Right to be forgotten - LGPD Art. 18, GDPR Art. 17)
     */
    public function processErasureRequest($userId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Check if user has active bookings
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE customer_id = ? 
                AND status IN ('confirmed', 'pending') 
                AND date_time > NOW()
            ");
            $stmt->execute([$userId]);
            $activeBookings = $stmt->fetchColumn();
            
            if ($activeBookings > 0) {
                throw new Exception("Cannot delete user with active future bookings");
            }
            
            // Anonymize historical data instead of deleting (for legal/business reasons)
            $anonymizedEmail = 'deleted_user_' . $userId . '@anonymized.com';
            $anonymizedName = 'Deleted User ' . $userId;
            
            // Update user record
            $stmt = $this->db->prepare("
                UPDATE users SET 
                    name = ?, 
                    email = ?, 
                    phone = NULL, 
                    address = NULL,
                    date_of_birth = NULL,
                    deleted_at = NOW(),
                    deletion_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$anonymizedName, $anonymizedEmail, $reason, $userId]);
            
            // Remove sensitive data from bookings but keep for business records
            $stmt = $this->db->prepare("
                UPDATE bookings SET 
                    notes = 'Customer data removed upon request',
                    special_instructions = NULL
                WHERE customer_id = ?
            ");
            $stmt->execute([$userId]);
            
            // Delete analytics events
            $stmt = $this->db->prepare("DELETE FROM analytics_events WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete marketing preferences
            $stmt = $this->db->prepare("DELETE FROM marketing_preferences WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Record the erasure
            $stmt = $this->db->prepare("
                INSERT INTO data_erasures 
                (user_id, requested_at, processed_at, reason, ip_address)
                VALUES (?, NOW(), NOW(), ?, ?)
            ");
            $stmt->execute([$userId, $reason, $_SERVER['REMOTE_ADDR']]);
            
            $this->db->commit();
            
            $this->logger->info("Data erasure request processed", [
                'user_id' => $userId,
                'reason' => $reason,
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Data erasure failed", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Process data portability request (LGPD Art. 18, GDPR Art. 20)
     */
    public function processPortabilityRequest($userId, $format = 'json') {
        $userData = $this->processDataAccessRequest($userId);
        
        // Remove sensitive internal data
        unset($userData['profile']['password']);
        unset($userData['profile']['remember_token']);
        
        // Format data for portability
        $portableData = [
            'export_date' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'data' => $userData
        ];
        
        switch ($format) {
            case 'csv':
                return $this->exportToCSV($portableData);
            case 'xml':
                return $this->exportToXML($portableData);
            default:
                return json_encode($portableData, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Get current privacy policy version
     */
    public function getCurrentPrivacyPolicy() {
        $stmt = $this->db->query("
            SELECT * FROM privacy_policies 
            WHERE is_current = 1 
            ORDER BY version DESC 
            LIMIT 1
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if user needs to re-consent (policy updated)
     */
    public function needsReConsent($userId) {
        $currentPolicy = $this->getCurrentPrivacyPolicy();
        if (!$currentPolicy) return false;
        
        $stmt = $this->db->prepare("
            SELECT MAX(consent_version) as latest_consent
            FROM privacy_consents 
            WHERE user_id = ? AND consent_type = 'privacy_policy'
        ");
        $stmt->execute([$userId]);
        $latestConsent = $stmt->fetchColumn();
        
        return $currentPolicy['version'] > $latestConsent;
    }
    
    /**
     * Generate privacy compliance report
     */
    public function generateComplianceReport($startDate, $endDate) {
        $report = [];
        
        // Consent statistics
        $stmt = $this->db->prepare("
            SELECT 
                consent_type,
                COUNT(*) as total_consents,
                COUNT(DISTINCT user_id) as unique_users
            FROM privacy_consents 
            WHERE consented_at BETWEEN ? AND ?
            GROUP BY consent_type
        ");
        $stmt->execute([$startDate, $endDate]);
        $report['consents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Data requests
        $stmt = $this->db->prepare("
            SELECT 
                'access' as request_type,
                COUNT(*) as total_requests
            FROM data_access_requests 
            WHERE requested_at BETWEEN ? AND ?
            UNION ALL
            SELECT 
                'erasure' as request_type,
                COUNT(*) as total_requests
            FROM data_erasures 
            WHERE requested_at BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
        $report['data_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Policy updates
        $stmt = $this->db->prepare("
            SELECT * FROM privacy_policies 
            WHERE created_at BETWEEN ? AND ?
            ORDER BY version DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $report['policy_updates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $report;
    }
    
    /**
     * Cookie consent management
     */
    public function setCookieConsent($userId, $consentData) {
        $stmt = $this->db->prepare("
            INSERT INTO cookie_consents 
            (user_id, necessary, functional, analytics, marketing, consented_at, expires_at)
            VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 13 MONTH))
            ON DUPLICATE KEY UPDATE
            necessary = VALUES(necessary),
            functional = VALUES(functional),
            analytics = VALUES(analytics),
            marketing = VALUES(marketing),
            consented_at = NOW(),
            expires_at = DATE_ADD(NOW(), INTERVAL 13 MONTH)
        ");
        
        return $stmt->execute([
            $userId,
            $consentData['necessary'] ? 1 : 0,
            $consentData['functional'] ? 1 : 0,
            $consentData['analytics'] ? 1 : 0,
            $consentData['marketing'] ? 1 : 0
        ]);
    }
    
    /**
     * Get cookie consent status
     */
    public function getCookieConsent($userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM cookie_consents 
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY consented_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function exportToCSV($data) {
        $csv = "Blue Cleaning Services - Data Export\n";
        $csv .= "Generated: " . $data['export_date'] . "\n\n";
        
        // Profile data
        $csv .= "PROFILE INFORMATION\n";
        foreach ($data['data']['profile'] as $key => $value) {
            if ($key !== 'password' && $key !== 'remember_token') {
                $csv .= "$key,$value\n";
            }
        }
        
        // Add other data sections...
        return $csv;
    }
    
    private function exportToXML($data) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        $root = $xml->createElement('BlueCleaningDataExport');
        $xml->appendChild($root);
        
        $exportDate = $xml->createElement('ExportDate', $data['export_date']);
        $root->appendChild($exportDate);
        
        // Add profile data
        $profile = $xml->createElement('Profile');
        foreach ($data['data']['profile'] as $key => $value) {
            if ($key !== 'password' && $key !== 'remember_token') {
                $element = $xml->createElement($key, htmlspecialchars($value));
                $profile->appendChild($element);
            }
        }
        $root->appendChild($profile);
        
        return $xml->saveXML();
    }
}

// API Endpoints for privacy management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $privacySystem = new PrivacyComplianceSystem();
    
    // Secure input validation
    $action = SecurityHelpers::getPostInput('action', 'string', '', 50, [
        'record_consent', 'data_access_request', 'data_erasure_request', 'cookie_consent'
    ]);
    
    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing action']);
        exit;
    }
    
    // Rate limiting
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!SecurityHelpers::checkRateLimit("privacy_api_{$clientIP}", 30, 300)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }
    
    switch ($action) {
        case 'record_consent':
            $userId = SecurityHelpers::getPostInput('user_id', 'int');
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }
            
            $consentData = [
                'type' => SecurityHelpers::getPostInput('consent_type', 'string', '', 100),
                'version' => SecurityHelpers::getPostInput('version', 'string', '', 10),
                'consents' => SecurityHelpers::getPostInput('consents', 'array', [])
            ];
            
            $result = $privacySystem->recordConsent($userId, $consentData);
            echo json_encode(['success' => $result]);
            break;
            
        case 'data_access_request':
            $userId = SecurityHelpers::getPostInput('user_id', 'int');
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }
            
            $userData = $privacySystem->processDataAccessRequest($userId);
            echo json_encode($userData);
            break;
            
        case 'data_erasure_request':
            $userId = SecurityHelpers::getPostInput('user_id', 'int');
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }
            
            $reason = SecurityHelpers::getPostInput('reason', 'string', '', 500);
            
            try {
                $result = $privacySystem->processErasureRequest($userId, $reason);
                echo json_encode(['success' => $result]);
            } catch (Exception $e) {
                SecurityHelpers::logSecurityEvent('privacy_erasure_error', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                http_response_code(400);
                echo json_encode(['error' => 'Request processing failed']);
            }
            break;
            
        case 'cookie_consent':
            $userId = SecurityHelpers::getPostInput('user_id', 'int');
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }
            
            $consentData = [
                'necessary' => SecurityHelpers::getPostInput('necessary', 'bool', false),
                'functional' => SecurityHelpers::getPostInput('functional', 'bool', false),
                'analytics' => SecurityHelpers::getPostInput('analytics', 'bool', false),
                'marketing' => SecurityHelpers::getPostInput('marketing', 'bool', false)
            ];
            
            $result = $privacySystem->setCookieConsent($userId, $consentData);
            echo json_encode(['success' => $result]);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
            break;
    }
    exit;
}
?>

<!-- Privacy Settings UI -->
<div id="privacy-settings" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-96 overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Privacy Settings</h2>
                    <button onclick="closePrivacySettings()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="border-b pb-4">
                        <h3 class="font-medium mb-2">Cookie Preferences</h3>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" id="necessary-cookies" checked disabled class="mr-2">
                                <span>Necessary Cookies (Required)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="functional-cookies" class="mr-2">
                                <span>Functional Cookies</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="analytics-cookies" class="mr-2">
                                <span>Analytics Cookies</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="marketing-cookies" class="mr-2">
                                <span>Marketing Cookies</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="border-b pb-4">
                        <h3 class="font-medium mb-2">Your Rights</h3>
                        <div class="space-y-2 text-sm">
                            <button onclick="requestDataAccess()" class="text-blue-600 hover:text-blue-800 block">
                                Request My Data
                            </button>
                            <button onclick="requestDataErasure()" class="text-red-600 hover:text-red-800 block">
                                Delete My Account
                            </button>
                            <button onclick="requestDataPortability()" class="text-green-600 hover:text-green-800 block">
                                Export My Data
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button onclick="closePrivacySettings()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button onclick="savePrivacySettings()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Save Preferences
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class PrivacyManager {
    constructor() {
        this.userId = window.currentUserId || null;
    }
    
    showPrivacySettings() {
        document.getElementById('privacy-settings').classList.remove('hidden');
        this.loadCurrentSettings();
    }
    
    closePrivacySettings() {
        document.getElementById('privacy-settings').classList.add('hidden');
    }
    
    async loadCurrentSettings() {
        if (!this.userId) return;
        
        try {
            const response = await fetch('/api/privacy-compliance.php?action=get_cookie_consent', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: this.userId })
            });
            
            const consent = await response.json();
            if (consent) {
                document.getElementById('functional-cookies').checked = consent.functional;
                document.getElementById('analytics-cookies').checked = consent.analytics;
                document.getElementById('marketing-cookies').checked = consent.marketing;
            }
        } catch (error) {
            console.error('Failed to load privacy settings:', error);
        }
    }
    
    async savePrivacySettings() {
        if (!this.userId) {
            alert('Please log in to save privacy settings');
            return;
        }
        
        const consentData = {
            user_id: this.userId,
            action: 'cookie_consent',
            necessary: 'true',
            functional: document.getElementById('functional-cookies').checked.toString(),
            analytics: document.getElementById('analytics-cookies').checked.toString(),
            marketing: document.getElementById('marketing-cookies').checked.toString()
        };
        
        try {
            const response = await fetch('/api/privacy-compliance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(consentData)
            });
            
            const result = await response.json();
            if (result.success) {
                alert('Privacy settings saved successfully');
                this.closePrivacySettings();
                // Update cookie behavior based on consent
                this.updateCookieBehavior(consentData);
            } else {
                alert('Failed to save privacy settings');
            }
        } catch (error) {
            console.error('Failed to save privacy settings:', error);
            alert('An error occurred while saving your settings');
        }
    }
    
    updateCookieBehavior(consent) {
        // Disable Google Analytics if not consented
        if (consent.analytics === 'false' && window.gtag) {
            window.gtag('consent', 'update', {
                'analytics_storage': 'denied'
            });
        }
        
        // Disable marketing cookies if not consented
        if (consent.marketing === 'false') {
            // Remove marketing-related cookies and scripts
            this.removeMarketingCookies();
        }
    }
    
    removeMarketingCookies() {
        // Remove marketing-related cookies
        const marketingCookies = ['_fbp', '_fbc', 'fr', '_gcl_au'];
        marketingCookies.forEach(cookie => {
            document.cookie = `${cookie}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
        });
    }
    
    async requestDataAccess() {
        if (!this.userId) {
            alert('Please log in to request your data');
            return;
        }
        
        if (!confirm('This will generate a report of all your data. Continue?')) return;
        
        try {
            const response = await fetch('/api/privacy-compliance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'data_access_request',
                    user_id: this.userId
                })
            });
            
            const data = await response.json();
            
            // Create and download the data report
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `blue-cleaning-data-${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
            
            alert('Your data report has been downloaded');
        } catch (error) {
            console.error('Failed to request data access:', error);
            alert('Failed to generate data report');
        }
    }
    
    async requestDataErasure() {
        if (!this.userId) {
            alert('Please log in to delete your account');
            return;
        }
        
        const reason = prompt('Please provide a reason for account deletion (optional):');
        if (reason === null) return; // User cancelled
        
        const confirmed = confirm(
            'WARNING: This will permanently delete your account and anonymize your data. ' +
            'This action cannot be undone. Are you sure you want to proceed?'
        );
        
        if (!confirmed) return;
        
        try {
            const response = await fetch('/api/privacy-compliance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'data_erasure_request',
                    user_id: this.userId,
                    reason: reason
                })
            });
            
            const result = await response.json();
            if (result.success) {
                alert('Your account deletion request has been processed. You will be logged out.');
                window.location.href = '/logout.php';
            } else {
                alert('Account deletion failed: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Failed to request data erasure:', error);
            alert('Failed to process deletion request');
        }
    }
}

// Initialize privacy manager
const privacyManager = new PrivacyManager();

// Global functions for HTML onclick handlers
function showPrivacySettings() {
    privacyManager.showPrivacySettings();
}

function closePrivacySettings() {
    privacyManager.closePrivacySettings();
}

function savePrivacySettings() {
    privacyManager.savePrivacySettings();
}

function requestDataAccess() {
    privacyManager.requestDataAccess();
}

function requestDataErasure() {
    privacyManager.requestDataErasure();
}

function requestDataPortability() {
    privacyManager.requestDataAccess(); // Same as data access for now
}
</script>
