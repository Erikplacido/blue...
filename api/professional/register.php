<?php
/**
 * API de Registro de Profissionais - Blue Project V2
 * Sistema escalável para múltiplos tipos de profissionais
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'message' => 'Only POST requests are accepted'
    ]);
    exit();
}

// Start session
session_start();

// Rate limiting
if (!checkRateLimit()) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'rate_limit_exceeded',
        'message' => 'Too many registration attempts. Please wait before trying again.'
    ]);
    exit();
}

try {
    // Validate and process form data
    $registrationData = processRegistrationData();
    
    // Validate all required fields
    $validation = validateRegistrationData($registrationData);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation_failed',
            'message' => 'Registration data validation failed',
            'details' => $validation['errors']
        ]);
        exit();
    }
    
    // Process uploaded documents
    $documentResults = processUploadedDocuments();
    if (!$documentResults['success']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'document_upload_failed',
            'message' => $documentResults['message']
        ]);
        exit();
    }
    
    // Create professional profile
    $professional = createProfessionalProfile($registrationData, $documentResults);
    
    // Initialize verification process
    $verificationResult = initializeVerificationProcess($professional);
    
    // Send welcome email
    sendWelcomeEmail($professional);
    
    // Log registration
    logProfessionalRegistration($professional);
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'professional_id' => $professional['id'],
        'application_id' => $professional['application_id'],
        'verification_status' => $verificationResult['status'],
        'estimated_verification_time' => $verificationResult['estimated_time'],
        'next_steps' => $verificationResult['next_steps'],
        'dashboard_url' => '/professional/dashboard.php',
        'message' => 'Registration completed successfully! Check your email for verification updates.'
    ]);

} catch (Exception $e) {
    // Log error
    error_log('Professional registration error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'registration_failed',
        'message' => 'Registration failed. Please try again.',
        'debug' => $_ENV['APP_DEBUG'] ? $e->getMessage() : null
    ]);
}

/**
 * Process registration data from form
 */
function processRegistrationData() {
    $data = [
        // Personal Information
        'personal_info' => [
            'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
            'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
            'email' => filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL),
            'mobile' => sanitizePhone($_POST['mobile'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'languages' => sanitizeInput($_POST['languages'] ?? ''),
            'years_experience' => $_POST['years_experience'] ?? ''
        ],
        
        // Address Information
        'address_info' => [
            'street_address' => sanitizeInput($_POST['street_address'] ?? ''),
            'suburb' => sanitizeInput($_POST['suburb'] ?? ''),
            'state' => $_POST['state'] ?? '',
            'postcode' => $_POST['postcode'] ?? '',
            'service_radius' => (int)($_POST['service_radius'] ?? 10)
        ],
        
        // Services
        'services' => $_POST['services'] ?? [],
        
        // Legal Information
        'legal_info' => [
            'abn_number' => sanitizeInput($_POST['abn_number'] ?? ''),
            'tfn_number' => encryptSensitiveData($_POST['tfn_number'] ?? '')
        ],
        
        // Banking Information
        'banking_info' => [
            'bank_name' => $_POST['bank_name'] ?? '',
            'bsb' => sanitizeInput($_POST['bsb'] ?? ''),
            'account_number' => encryptSensitiveData($_POST['account_number'] ?? ''),
            'account_name' => sanitizeInput($_POST['account_name'] ?? '')
        ],
        
        // Availability
        'availability' => [
            'available_days' => $_POST['available_days'] ?? [],
            'max_jobs_per_day' => (int)($_POST['max_jobs_per_day'] ?? 3),
            'minimum_notice' => (int)($_POST['minimum_notice'] ?? 24),
            'transport_type' => $_POST['transport_type'] ?? '',
            'own_equipment' => $_POST['own_equipment'] ?? '',
            'emergency_available' => isset($_POST['emergency_available'])
        ],
        
        // Schedule details for available days
        'schedule' => []
    ];
    
    // Process schedule for each available day
    if (!empty($data['availability']['available_days'])) {
        foreach ($data['availability']['available_days'] as $day) {
            $startTime = $_POST[$day . '_start'] ?? '';
            $endTime = $_POST[$day . '_end'] ?? '';
            
            if ($startTime && $endTime) {
                $data['schedule'][$day] = [
                    'start' => $startTime,
                    'end' => $endTime,
                    'enabled' => true
                ];
            }
        }
    }
    
    return $data;
}

/**
 * Validate registration data
 */
function validateRegistrationData($data) {
    $errors = [];
    
    // Validate personal information
    $personalInfo = $data['personal_info'];
    
    if (empty($personalInfo['first_name'])) {
        $errors[] = 'First name is required';
    }
    
    if (empty($personalInfo['last_name'])) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($personalInfo['email']) || !filter_var($personalInfo['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }
    
    if (empty($personalInfo['mobile']) || !preg_match('/^(\+61|0)[2-9]\d{8}$/', $personalInfo['mobile'])) {
        $errors[] = 'Valid Australian mobile number is required';
    }
    
    if (empty($personalInfo['date_of_birth'])) {
        $errors[] = 'Date of birth is required';
    } else {
        $birthDate = new DateTime($personalInfo['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        
        if ($age < 18) {
            $errors[] = 'You must be at least 18 years old to register';
        }
    }
    
    // Validate address information
    $addressInfo = $data['address_info'];
    
    if (empty($addressInfo['street_address'])) {
        $errors[] = 'Street address is required';
    }
    
    if (empty($addressInfo['suburb'])) {
        $errors[] = 'Suburb is required';
    }
    
    if (empty($addressInfo['state'])) {
        $errors[] = 'State is required';
    }
    
    if (empty($addressInfo['postcode']) || !preg_match('/^[0-9]{4}$/', $addressInfo['postcode'])) {
        $errors[] = 'Valid 4-digit postcode is required';
    }
    
    // Validate services
    if (empty($data['services']) || !is_array($data['services'])) {
        $errors[] = 'At least one service must be selected';
    }
    
    // Validate legal information
    $legalInfo = $data['legal_info'];
    
    if (empty($legalInfo['abn_number']) || !validateABN($legalInfo['abn_number'])) {
        $errors[] = 'Valid ABN number is required';
    }
    
    if (empty($legalInfo['tfn_number'])) {
        $errors[] = 'Tax File Number is required';
    }
    
    // Validate banking information
    $bankingInfo = $data['banking_info'];
    
    if (empty($bankingInfo['bank_name'])) {
        $errors[] = 'Bank name is required';
    }
    
    if (empty($bankingInfo['bsb']) || !preg_match('/^\d{3}-?\d{3}$/', $bankingInfo['bsb'])) {
        $errors[] = 'Valid BSB is required (format: XXX-XXX)';
    }
    
    if (empty($bankingInfo['account_number'])) {
        $errors[] = 'Account number is required';
    }
    
    if (empty($bankingInfo['account_name'])) {
        $errors[] = 'Account name is required';
    }
    
    // Validate availability
    $availability = $data['availability'];
    
    if (empty($availability['available_days']) || !is_array($availability['available_days'])) {
        $errors[] = 'At least one available day must be selected';
    }
    
    if (empty($availability['transport_type'])) {
        $errors[] = 'Transportation method is required';
    }
    
    if (empty($availability['own_equipment'])) {
        $errors[] = 'Equipment information is required';
    }
    
    // Check for duplicate email
    if (checkEmailExists($personalInfo['email'])) {
        $errors[] = 'An account with this email already exists';
    }
    
    // Check for duplicate ABN
    if (checkABNExists($legalInfo['abn_number'])) {
        $errors[] = 'This ABN is already registered';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Process uploaded documents
 */
function processUploadedDocuments() {
    $uploadDir = '../uploads/professionals/' . date('Y/m/d') . '/';
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadedFiles = [];
    $requiredFiles = ['profile_photo', 'drivers_license', 'police_check', 'insurance_certificate'];
    
    try {
        foreach ($requiredFiles as $fileKey) {
            if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                return [
                    'success' => false,
                    'message' => "Required document '{$fileKey}' is missing or failed to upload"
                ];
            }
            
            $file = $_FILES[$fileKey];
            
            // Validate file
            $validation = validateUploadedFile($file, $fileKey);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['error']
                ];
            }
            
            // Generate unique filename
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = generateUniqueId() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => false,
                    'message' => "Failed to save {$fileKey}"
                ];
            }
            
            $uploadedFiles[$fileKey] = [
                'original_name' => $file['name'],
                'file_path' => $filePath,
                'file_size' => $file['size'],
                'mime_type' => $file['type'],
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Process optional files
        $optionalFiles = ['certifications', 'work_visa'];
        foreach ($optionalFiles as $fileKey) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fileKey];
                $validation = validateUploadedFile($file, $fileKey);
                
                if ($validation['valid']) {
                    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName = generateUniqueId() . '.' . $fileExtension;
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        $uploadedFiles[$fileKey] = [
                            'original_name' => $file['name'],
                            'file_path' => $filePath,
                            'file_size' => $file['size'],
                            'mime_type' => $file['type'],
                            'uploaded_at' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'files' => $uploadedFiles
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Document upload failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Create professional profile
 */
function createProfessionalProfile($data, $documentResults) {
    $professionalId = generateUniqueId('PROF');
    $applicationId = generateUniqueId('APP');
    
    $professional = [
        'id' => $professionalId,
        'application_id' => $applicationId,
        'personal_info' => $data['personal_info'],
        'address_info' => $data['address_info'],
        'services' => $data['services'],
        'legal_info' => $data['legal_info'],
        'banking_info' => $data['banking_info'],
        'availability' => $data['availability'],
        'schedule' => $data['schedule'],
        'documents' => $documentResults['files'],
        'status' => 'pending_verification',
        'verification_stage' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Save to database (simulated - replace with actual database implementation)
    saveProfessionalToDatabase($professional);
    
    return $professional;
}

/**
 * Initialize verification process
 */
function initializeVerificationProcess($professional) {
    // Create verification record
    $verification = [
        'professional_id' => $professional['id'],
        'application_id' => $professional['application_id'],
        'current_stage' => 1,
        'stages' => [
            1 => [
                'name' => 'Document Verification',
                'status' => 'pending',
                'automated' => false,
                'estimated_time' => '2-5 business days',
                'started_at' => date('Y-m-d H:i:s')
            ],
            2 => [
                'name' => 'Background Check',
                'status' => 'not_started',
                'automated' => true,
                'estimated_time' => '1-3 business days'
            ],
            3 => [
                'name' => 'Skills Assessment',
                'status' => 'not_started',
                'automated' => true,
                'estimated_time' => '1 week'
            ],
            4 => [
                'name' => 'Trial Period',
                'status' => 'not_started',
                'automated' => false,
                'estimated_time' => '2 weeks'
            ]
        ],
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Start automated checks
    startAutomatedVerification($professional);
    
    return [
        'status' => 'verification_initiated',
        'current_stage' => 1,
        'estimated_time' => '1-2 weeks',
        'next_steps' => [
            'We will review your documents within 2-5 business days',
            'You will receive email updates at each verification stage',
            'Complete any additional requirements we may request',
            'Once approved, you can start accepting jobs'
        ]
    ];
}

/**
 * Start automated verification checks
 */
function startAutomatedVerification($professional) {
    // ABN verification
    $abnVerification = verifyABN($professional['legal_info']['abn_number']);
    
    // Basic document analysis
    $documentAnalysis = analyzeDocuments($professional['documents']);
    
    // Email verification
    sendEmailVerification($professional['personal_info']['email']);
    
    // Phone verification
    sendSMSVerification($professional['personal_info']['mobile']);
    
    // Log verification attempts
    logVerificationAttempts($professional['id'], [
        'abn_verification' => $abnVerification,
        'document_analysis' => $documentAnalysis
    ]);
}

/**
 * Utility functions
 */
function checkRateLimit() {
    $key = 'registration_limit_' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
    $maxAttempts = 3;
    $window = 3600; // 1 hour
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    if (time() - $data['start'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    if ($data['count'] >= $maxAttempts) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizePhone($phone) {
    return preg_replace('/[^0-9+]/', '', $phone);
}

function encryptSensitiveData($data) {
    // Implement proper encryption for sensitive data
    return base64_encode($data); // Simplified - use proper encryption in production
}

function validateUploadedFile($file, $fileType) {
    $maxSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = [
        'profile_photo' => ['image/jpeg', 'image/png'],
        'drivers_license' => ['image/jpeg', 'image/png', 'application/pdf'],
        'police_check' => ['application/pdf', 'image/jpeg', 'image/png'],
        'insurance_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
        'certifications' => ['application/pdf', 'image/jpeg', 'image/png'],
        'work_visa' => ['application/pdf', 'image/jpeg', 'image/png']
    ];
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File size exceeds 5MB limit'];
    }
    
    if (!in_array($file['type'], $allowedTypes[$fileType] ?? [])) {
        return ['valid' => false, 'error' => 'Invalid file type for ' . $fileType];
    }
    
    return ['valid' => true];
}

function validateABN($abn) {
    // Simplified ABN validation - implement proper ABN algorithm
    return preg_match('/^\d{11}$/', preg_replace('/[^0-9]/', '', $abn));
}

function generateUniqueId($prefix = '') {
    return $prefix . '_' . date('YmdHis') . '_' . random_int(1000, 9999);
}

function checkEmailExists($email) {
    // Check if email already exists in database
    // Return false for now - implement actual check
    return false;
}

function checkABNExists($abn) {
    // Check if ABN already exists in database
    // Return false for now - implement actual check
    return false;
}

function saveProfessionalToDatabase($professional) {
    // Save professional data to database
    // Implement actual database save operation
}

function verifyABN($abn) {
    // Verify ABN with government API
    // Return verification result
    return ['status' => 'pending', 'verified' => false];
}

function analyzeDocuments($documents) {
    // Analyze uploaded documents
    // Return analysis results
    return ['status' => 'pending', 'issues' => []];
}

function sendEmailVerification($email) {
    // Send email verification link
    // Implement email sending
}

function sendSMSVerification($mobile) {
    // Send SMS verification code
    // Implement SMS sending
}

function sendWelcomeEmail($professional) {
    // Send welcome email with next steps
    // Implement email sending
}

function logProfessionalRegistration($professional) {
    error_log("Professional registered: " . $professional['id'] . " - " . $professional['personal_info']['email']);
}

function logVerificationAttempts($professionalId, $attempts) {
    error_log("Verification started for professional: " . $professionalId);
}
?>
