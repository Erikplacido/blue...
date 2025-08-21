<?php
/**
 * Legal Document Management System
 * Dynamic Terms of Service & Privacy Policy Management
 * Blue Cleaning Services
 */

require_once __DIR__ . '/../config/australian-environment.php';
require_once __DIR__ . '/../config/australian-database.php';

class LegalDocumentManager {
    private $db;
    private $logger;
    
    public function __construct() {
        // Load Australian environment configuration
        AustralianEnvironmentConfig::load();
        
        // Use standardized database connection
        $this->db = AustralianDatabase::getInstance()->getConnection();
        $this->logger = new Logger('legal-documents');
    }
    
    /**
     * Get current version of a legal document
     */
    public function getCurrentDocument($type) {
        $stmt = $this->db->prepare("
            SELECT * FROM legal_documents 
            WHERE document_type = ? AND is_current = 1 
            ORDER BY version DESC 
            LIMIT 1
        ");
        $stmt->execute([$type]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new version of legal document
     */
    public function createNewVersion($type, $content, $summary = '') {
        try {
            $this->db->beginTransaction();
            
            // Mark current version as not current
            $stmt = $this->db->prepare("
                UPDATE legal_documents 
                SET is_current = 0 
                WHERE document_type = ? AND is_current = 1
            ");
            $stmt->execute([$type]);
            
            // Get next version number
            $stmt = $this->db->prepare("
                SELECT MAX(version) + 1 as next_version 
                FROM legal_documents 
                WHERE document_type = ?
            ");
            $stmt->execute([$type]);
            $nextVersion = $stmt->fetchColumn() ?: 1;
            
            // Insert new version
            $stmt = $this->db->prepare("
                INSERT INTO legal_documents 
                (document_type, version, content, summary, is_current, created_at, effective_date)
                VALUES (?, ?, ?, ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
            ");
            $stmt->execute([$type, $nextVersion, $content, $summary]);
            
            $documentId = $this->db->lastInsertId();
            
            // Log the document creation
            $this->logger->info("Legal document updated", [
                'type' => $type,
                'version' => $nextVersion,
                'document_id' => $documentId
            ]);
            
            $this->db->commit();
            return $documentId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to create legal document", [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all versions of a document
     */
    public function getDocumentHistory($type) {
        $stmt = $this->db->prepare("
            SELECT id, version, summary, is_current, created_at, effective_date 
            FROM legal_documents 
            WHERE document_type = ? 
            ORDER BY version DESC
        ");
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if user has accepted current terms
     */
    public function hasUserAcceptedCurrent($userId, $documentType) {
        $currentDoc = $this->getCurrentDocument($documentType);
        if (!$currentDoc) return false;
        
        $stmt = $this->db->prepare("
            SELECT * FROM legal_acceptances 
            WHERE user_id = ? 
            AND document_type = ? 
            AND document_version = ?
        ");
        $stmt->execute([$userId, $documentType, $currentDoc['version']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
    
    /**
     * Record user acceptance of terms
     */
    public function recordAcceptance($userId, $documentType, $ipAddress = null) {
        $currentDoc = $this->getCurrentDocument($documentType);
        if (!$currentDoc) {
            throw new Exception("No current document of type: $documentType");
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO legal_acceptances 
            (user_id, document_type, document_version, accepted_at, ip_address, user_agent)
            VALUES (?, ?, ?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE
            accepted_at = NOW(),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent)
        ");
        
        return $stmt->execute([
            $userId,
            $documentType,
            $currentDoc['version'],
            $ipAddress ?: $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    /**
     * Generate HTML version of document with current branding
     */
    public function generateHTMLDocument($type) {
        $doc = $this->getCurrentDocument($type);
        if (!$doc) return null;
        
        $template = $this->getDocumentTemplate($type);
        $content = $this->processDocumentContent($doc['content']);
        
        return str_replace([
            '{{CONTENT}}',
            '{{VERSION}}',
            '{{EFFECTIVE_DATE}}',
            '{{LAST_UPDATED}}'
        ], [
            $content,
            $doc['version'],
            date('F j, Y', strtotime($doc['effective_date'])),
            date('F j, Y', strtotime($doc['created_at']))
        ], $template);
    }
    
    private function getDocumentTemplate($type) {
        $title = ucfirst(str_replace('_', ' ', $type));
        
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . ' - Blue Cleaning Services</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">' . $title . '</h1>
                <div class="text-sm text-gray-600">
                    <p>Version {{VERSION}} • Effective {{EFFECTIVE_DATE}} • Last Updated {{LAST_UPDATED}}</p>
                </div>
            </div>
            
            <div class="prose prose-lg max-w-none">
                {{CONTENT}}
            </div>
            
            <div class="mt-8 p-4 bg-blue-50 border-l-4 border-blue-400">
                <p class="text-sm text-blue-800">
                    <strong>Contact Information:</strong><br>
                    Blue Cleaning Services<br>
                    Email: legal@bluecleaning.com.au<br>
                    Phone: 1300 BLUE CLEAN<br>
                    Address: Sydney, NSW, Australia
                </p>
            </div>
        </div>
    </div>
</body>
</html>';
    }
    
    private function processDocumentContent($content) {
        // Replace placeholders with actual company information
        $replacements = [
            '{{COMPANY_NAME}}' => 'Blue Cleaning Services Pty Ltd',
            '{{COMPANY_ABN}}' => 'ABN: 12 345 678 901',
            '{{CONTACT_EMAIL}}' => 'legal@bluecleaning.com.au',
            '{{CONTACT_PHONE}}' => '1300 BLUE CLEAN',
            '{{CONTACT_ADDRESS}}' => 'Sydney, NSW, Australia',
            '{{WEBSITE_URL}}' => 'https://bluecleaning.com.au',
            '{{CURRENT_DATE}}' => date('F j, Y'),
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}

// API endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manager = new LegalDocumentManager();
    $action = $_POST['action'] ?? $_GET['action'];
    
    switch ($action) {
        case 'accept_terms':
            $userId = $_POST['user_id'];
            $documentType = $_POST['document_type'];
            $result = $manager->recordAcceptance($userId, $documentType);
            echo json_encode(['success' => $result]);
            break;
            
        case 'check_acceptance':
            $userId = $_POST['user_id'];
            $documentType = $_POST['document_type'];
            $accepted = $manager->hasUserAcceptedCurrent($userId, $documentType);
            echo json_encode(['accepted' => $accepted]);
            break;
            
        case 'create_document':
            // Admin only
            if (!isset($_SESSION['admin_logged_in'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized']);
                break;
            }
            
            $type = $_POST['type'];
            $content = $_POST['content'];
            $summary = $_POST['summary'] ?? '';
            
            try {
                $docId = $manager->createNewVersion($type, $content, $summary);
                echo json_encode(['success' => true, 'document_id' => $docId]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
    }
    exit;
}

// Generate document page
if (isset($_GET['type'])) {
    $manager = new LegalDocumentManager();
    $documentType = $_GET['type'];
    
    $html = $manager->generateHTMLDocument($documentType);
    if ($html) {
        echo $html;
    } else {
        http_response_code(404);
        echo "Document not found";
    }
    exit;
}

// Default templates for initial setup
function initializeDefaultDocuments() {
    $manager = new LegalDocumentManager();
    
    // Privacy Policy Template
    $privacyPolicyContent = '
<h2>Privacy Policy</h2>

<p>Last updated: {{CURRENT_DATE}}</p>

<p>{{COMPANY_NAME}} ({{COMPANY_ABN}}) operates the {{WEBSITE_URL}} website. This page informs you of our policies regarding the collection, use, and disclosure of personal data when you use our service.</p>

<h3>1. Information Collection and Use</h3>
<p>We collect several different types of information for various purposes to provide and improve our service to you:</p>
<ul>
    <li><strong>Personal Data:</strong> Email address, first name, last name, phone number, address</li>
    <li><strong>Usage Data:</strong> Information on how the service is accessed and used</li>
    <li><strong>Location Data:</strong> Service address for cleaning appointments</li>
    <li><strong>Payment Information:</strong> Credit card details processed securely through Stripe</li>
</ul>

<h3>2. Use of Data</h3>
<p>{{COMPANY_NAME}} uses the collected data for various purposes:</p>
<ul>
    <li>To provide and maintain our service</li>
    <li>To notify you about changes to our service</li>
    <li>To provide customer support</li>
    <li>To gather analysis or valuable information to improve our service</li>
    <li>To monitor the usage of our service</li>
    <li>To detect, prevent and address technical issues</li>
</ul>

<h3>3. Legal Basis for Processing (LGPD/GDPR)</h3>
<p>Our legal basis for collecting and using personal information depends on the information and context:</p>
<ul>
    <li><strong>Contract Performance:</strong> Processing necessary to perform our cleaning services</li>
    <li><strong>Consent:</strong> Where you have given clear consent for processing</li>
    <li><strong>Legitimate Interest:</strong> For business operations and service improvement</li>
    <li><strong>Legal Obligation:</strong> To comply with applicable laws and regulations</li>
</ul>

<h3>4. Your Data Protection Rights</h3>
<p>You have the following data protection rights:</p>
<ul>
    <li><strong>Right of Access:</strong> Request copies of your personal data</li>
    <li><strong>Right of Rectification:</strong> Request correction of inaccurate data</li>
    <li><strong>Right of Erasure:</strong> Request deletion of your personal data</li>
    <li><strong>Right to Restrict Processing:</strong> Request restriction of processing</li>
    <li><strong>Right to Data Portability:</strong> Transfer your data to another service</li>
    <li><strong>Right to Object:</strong> Object to processing of your personal data</li>
</ul>

<h3>5. Data Retention</h3>
<p>We retain personal data only as long as necessary for the purposes outlined in this privacy policy, unless a longer retention period is required by law.</p>

<h3>6. Data Security</h3>
<p>We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction.</p>

<h3>7. International Transfers</h3>
<p>Your data may be processed in countries other than Australia. We ensure appropriate safeguards are in place for such transfers.</p>

<h3>8. Cookies</h3>
<p>We use cookies and similar technologies to enhance your experience. You can control cookie preferences through our cookie consent manager.</p>

<h3>9. Contact Information</h3>
<p>If you have questions about this Privacy Policy, please contact us:</p>
<ul>
    <li>Email: {{CONTACT_EMAIL}}</li>
    <li>Phone: {{CONTACT_PHONE}}</li>
    <li>Address: {{CONTACT_ADDRESS}}</li>
</ul>
';

    // Terms of Service Template
    $termsOfServiceContent = '
<h2>Terms of Service</h2>

<p>Last updated: {{CURRENT_DATE}}</p>

<p>Please read these Terms of Service ("Terms", "Terms of Service") carefully before using the {{WEBSITE_URL}} website operated by {{COMPANY_NAME}} ({{COMPANY_ABN}}).</p>

<h3>1. Acceptance of Terms</h3>
<p>By accessing and using this website, you accept and agree to be bound by the terms and provision of this agreement.</p>

<h3>2. Service Description</h3>
<p>{{COMPANY_NAME}} provides residential and commercial cleaning services throughout Australia. Our services include but are not limited to:</p>
<ul>
    <li>Regular house cleaning</li>
    <li>Deep cleaning</li>
    <li>End of lease cleaning</li>
    <li>Office cleaning</li>
    <li>Specialized cleaning services</li>
</ul>

<h3>3. Booking and Payment</h3>
<p>All bookings are subject to availability and confirmation. Payment is required at the time of booking through our secure payment processor.</p>

<h3>4. Cancellation Policy</h3>
<p>Cancellations must be made at least 24 hours before the scheduled service time to avoid cancellation fees.</p>

<h3>5. Liability</h3>
<p>{{COMPANY_NAME}} is fully insured and will take responsibility for any damages caused by our negligence during the provision of services.</p>

<h3>6. Privacy</h3>
<p>Your privacy is important to us. Please review our Privacy Policy, which also governs your use of the service.</p>

<h3>7. Modification of Terms</h3>
<p>We reserve the right to modify these terms at any time. Users will be notified of significant changes.</p>

<h3>8. Governing Law</h3>
<p>These Terms shall be interpreted and governed by the laws of Australia.</p>

<h3>9. Contact Information</h3>
<p>For questions about these Terms of Service, please contact us:</p>
<ul>
    <li>Email: {{CONTACT_EMAIL}}</li>
    <li>Phone: {{CONTACT_PHONE}}</li>
    <li>Address: {{CONTACT_ADDRESS}}</li>
</ul>
';

    try {
        $manager->createNewVersion('privacy_policy', $privacyPolicyContent, 'Initial privacy policy with LGPD/GDPR compliance');
        $manager->createNewVersion('terms_of_service', $termsOfServiceContent, 'Initial terms of service');
        echo "Default legal documents created successfully.";
    } catch (Exception $e) {
        echo "Error creating documents: " . $e->getMessage();
    }
}

// Terms acceptance widget for forms
function renderTermsAcceptanceWidget($userId = null) {
    $manager = new LegalDocumentManager();
    $needsAcceptance = false;
    
    if ($userId) {
        $needsAcceptance = !$manager->hasUserAcceptedCurrent($userId, 'terms_of_service') || 
                          !$manager->hasUserAcceptedCurrent($userId, 'privacy_policy');
    }
    
    return '
<div id="terms-acceptance-widget" class="' . ($needsAcceptance ? '' : 'hidden') . '">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
        <h3 class="text-lg font-semibold text-blue-900 mb-2">Legal Agreement Required</h3>
        <p class="text-sm text-blue-800 mb-4">
            To continue using our services, please review and accept our updated legal documents.
        </p>
        
        <div class="space-y-2 mb-4">
            <label class="flex items-start">
                <input type="checkbox" id="accept-terms" class="mt-1 mr-2" required>
                <span class="text-sm">
                    I have read and agree to the 
                    <a href="/legal-documents.php?type=terms_of_service" target="_blank" class="text-blue-600 underline">Terms of Service</a>
                </span>
            </label>
            <label class="flex items-start">
                <input type="checkbox" id="accept-privacy" class="mt-1 mr-2" required>
                <span class="text-sm">
                    I have read and agree to the 
                    <a href="/legal-documents.php?type=privacy_policy" target="_blank" class="text-blue-600 underline">Privacy Policy</a>
                </span>
            </label>
        </div>
        
        <button onclick="acceptLegalTerms()" id="accept-legal-btn" 
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50" 
                disabled>
            Accept and Continue
        </button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const termsCheckbox = document.getElementById("accept-terms");
    const privacyCheckbox = document.getElementById("accept-privacy");
    const acceptBtn = document.getElementById("accept-legal-btn");
    
    function updateButtonState() {
        acceptBtn.disabled = !(termsCheckbox.checked && privacyCheckbox.checked);
    }
    
    termsCheckbox.addEventListener("change", updateButtonState);
    privacyCheckbox.addEventListener("change", updateButtonState);
});

async function acceptLegalTerms() {
    const userId = ' . json_encode($userId) . ';
    if (!userId) {
        alert("Please log in to accept terms");
        return;
    }
    
    try {
        const responses = await Promise.all([
            fetch("/api/legal-documents.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "accept_terms",
                    user_id: userId,
                    document_type: "terms_of_service"
                })
            }),
            fetch("/api/legal-documents.php", {
                method: "POST", 
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "accept_terms",
                    user_id: userId,
                    document_type: "privacy_policy"
                })
            })
        ]);
        
        const results = await Promise.all(responses.map(r => r.json()));
        
        if (results.every(r => r.success)) {
            document.getElementById("terms-acceptance-widget").classList.add("hidden");
            alert("Terms accepted successfully");
        } else {
            alert("Failed to record acceptance");
        }
    } catch (error) {
        console.error("Error accepting terms:", error);
        alert("An error occurred");
    }
}
</script>
';
}
?>
