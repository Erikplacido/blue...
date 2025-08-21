<?php
/**
 * Sistema de Validação Completo - Blue Project V2
 * Validações server-side robustas para todos os inputs
 */

class ValidationSystem {
    
    private static $errors = [];
    private static $sanitizedData = [];
    
    // Regras de validação por campo
    private static $validationRules = [
        'email' => ['required', 'email', 'max:255'],
        'phone' => ['required', 'phone_au', 'min:10', 'max:15'],
        'postcode' => ['required', 'postcode_au', 'exact:4'],
        'name' => ['required', 'alpha_spaces', 'min:2', 'max:100'],
        'address' => ['required', 'address_format', 'min:10', 'max:200'],
        'date' => ['required', 'date_future', 'date_within:6months'],
        'service_type' => ['required', 'in:house-cleaning,deep-cleaning,office-cleaning,carpet-cleaning,window-cleaning'],
        'recurrence' => ['required', 'in:one-time,weekly,fortnightly,monthly']
    ];
    
    // Padrões regex para validação
    private static $patterns = [
        'phone_au' => '/^(\+61|0)[2-9]\d{8}$/',
        'postcode_au' => '/^[0-9]{4}$/',
        'alpha_spaces' => '/^[a-zA-Z\s\'-]+$/',
        'address_format' => '/^[a-zA-Z0-9\s,.\'-\/]+$/',
        'credit_card' => '/^[0-9]{13,19}$/',
        'cvv' => '/^[0-9]{3,4}$/',
        'suburb_format' => '/^[a-zA-Z\s\'-]+$/'
    ];
    
    // Listas de validação
    private static $validpostcodes = [
        // Sydney
        '2000', '2001', '2002', '2010', '2011', '2015', '2016', '2017', '2018', '2019', '2020',
        // Melbourne  
        '3000', '3001', '3002', '3003', '3004', '3006', '3008', '3010', '3011', '3012',
        // Brisbane
        '4000', '4001', '4005', '4006', '4007', '4008', '4009', '4010', '4011', '4012',
        // Perth
        '6000', '6001', '6002', '6003', '6004', '6005', '6006', '6007', '6008', '6009',
        // Adelaide
        '5000', '5001', '5002', '5003', '5004', '5005', '5006', '5007', '5008', '5009'
    ];
    
    /**
     * Validar dados de booking completos
     */
    public static function validateBookingData($data) {
        self::$errors = [];
        self::$sanitizedData = [];
        
        // Validações obrigatórias
        self::validateRequired($data, [
            'customer_name', 'customer_email', 'customer_phone', 
            'service_type', 'selected_date', 'service_address', 
            'suburb', 'postcode'
        ]);
        
        // Validar cada campo individualmente
        if (isset($data['customer_name'])) {
            self::validateName($data['customer_name'], 'customer_name');
        }
        
        if (isset($data['customer_email'])) {
            self::validateEmail($data['customer_email'], 'customer_email');
        }
        
        if (isset($data['customer_phone'])) {
            self::validatePhone($data['customer_phone'], 'customer_phone');
        }
        
        if (isset($data['service_address'])) {
            self::validateAddress($data['service_address'], 'service_address');
        }
        
        if (isset($data['suburb'])) {
            self::validateSuburb($data['suburb'], 'suburb');
        }
        
        if (isset($data['postcode'])) {
            self::validatePostcode($data['postcode'], 'postcode');
        }
        
        if (isset($data['selected_date'])) {
            self::validateServiceDate($data['selected_date'], 'selected_date');
        }
        
        if (isset($data['service_type'])) {
            self::validateServiceType($data['service_type'], 'service_type');
        }
        
        if (isset($data['recurrence_pattern'])) {
            self::validateRecurrence($data['recurrence_pattern'], 'recurrence_pattern');
        }
        
        // Validações opcionais
        if (!empty($data['special_instructions'])) {
            self::validateSpecialInstructions($data['special_instructions'], 'special_instructions');
        }
        
        if (!empty($data['discount_code'])) {
            self::validateDiscountCode($data['discount_code'], 'discount_code');
        }
        
        if (!empty($data['referral_code'])) {
            self::validateReferralCode($data['referral_code'], 'referral_code');
        }
        
        // Validar números
        self::validateNumber($data['bedrooms'] ?? 3, 'bedrooms', 1, 10);
        self::validateNumber($data['bathrooms'] ?? 2, 'bathrooms', 1, 8);
        
        // Validações cruzadas
        self::validateCrossReferences($data);
        
        return [
            'valid' => empty(self::$errors),
            'errors' => self::$errors,
            'sanitized_data' => self::$sanitizedData,
            'validation_summary' => self::getValidationSummary()
        ];
    }
    
    /**
     * Validar dados de pagamento
     */
    public static function validatePaymentData($data) {
        self::$errors = [];
        
        // Validar payment method ID (Stripe)
        if (empty($data['payment_method_id']) || !preg_match('/^pm_[a-zA-Z0-9]+$/', $data['payment_method_id'])) {
            self::$errors['payment_method_id'] = 'Valid payment method is required';
        }
        
        // Validar billing address se fornecido
        if (!empty($data['billing_address'])) {
            if (empty($data['billing_address']['line1'])) {
                self::$errors['billing_line1'] = 'Billing address line 1 is required';
            }
            
            if (empty($data['billing_address']['city'])) {
                self::$errors['billing_city'] = 'Billing city is required';
            }
            
            if (empty($data['billing_address']['postal_code']) || 
                !preg_match(self::$patterns['postcode_au'], $data['billing_address']['postal_code'])) {
                self::$errors['billing_postcode'] = 'Valid billing postcode is required';
            }
            
            if (empty($data['billing_address']['country']) || $data['billing_address']['country'] !== 'AU') {
                self::$errors['billing_country'] = 'Only Australian billing addresses are accepted';
            }
        }
        
        return [
            'valid' => empty(self::$errors),
            'errors' => self::$errors
        ];
    }
    
    /**
     * Validações individuais por campo
     */
    private static function validateName($name, $field) {
        $name = trim($name);
        
        if (empty($name)) {
            self::$errors[$field] = 'Name is required';
            return;
        }
        
        if (strlen($name) < 2) {
            self::$errors[$field] = 'Name must be at least 2 characters';
            return;
        }
        
        if (strlen($name) > 100) {
            self::$errors[$field] = 'Name cannot exceed 100 characters';
            return;
        }
        
        if (!preg_match(self::$patterns['alpha_spaces'], $name)) {
            self::$errors[$field] = 'Name can only contain letters, spaces, hyphens and apostrophes';
            return;
        }
        
        // Verificar palavras ofensivas
        if (self::containsProfanity($name)) {
            self::$errors[$field] = 'Name contains inappropriate content';
            return;
        }
        
        self::$sanitizedData[$field] = ucwords(strtolower($name));
    }
    
    private static function validateEmail($email, $field) {
        $email = trim(strtolower($email));
        
        if (empty($email)) {
            self::$errors[$field] = 'Email is required';
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::$errors[$field] = 'Invalid email format';
            return;
        }
        
        if (strlen($email) > 255) {
            self::$errors[$field] = 'Email address is too long';
            return;
        }
        
        // Verificar domínios suspeitos
        $suspiciousDomains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com'];
        $domain = substr(strrchr($email, "@"), 1);
        if (in_array($domain, $suspiciousDomains)) {
            self::$errors[$field] = 'Temporary email addresses are not allowed';
            return;
        }
        
        // Verificar MX record (opcional, pode ser lento)
        if (!self::isValidEmailDomain($domain)) {
            self::$errors[$field] = 'Email domain does not accept emails';
            return;
        }
        
        self::$sanitizedData[$field] = $email;
    }
    
    private static function validatePhone($phone, $field) {
        // Remove todos os caracteres não numéricos exceto +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        if (empty($cleanPhone)) {
            self::$errors[$field] = 'Phone number is required';
            return;
        }
        
        // Converter formato australiano
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = '+61' . substr($cleanPhone, 1);
        } elseif (substr($cleanPhone, 0, 3) !== '+61') {
            $cleanPhone = '+61' . $cleanPhone;
        }
        
        // Validar formato final
        if (!preg_match('/^\+61[2-9]\d{8}$/', $cleanPhone)) {
            self::$errors[$field] = 'Invalid Australian phone number format';
            return;
        }
        
        // Verificar se não é número conhecido como inválido
        $invalidPrefixes = ['+61111111', '+61000000', '+61999999'];
        foreach ($invalidPrefixes as $prefix) {
            if (substr($cleanPhone, 0, strlen($prefix)) === $prefix) {
                self::$errors[$field] = 'Invalid phone number';
                return;
            }
        }
        
        self::$sanitizedData[$field] = $cleanPhone;
    }
    
    private static function validateAddress($address, $field) {
        $address = trim($address);
        
        if (empty($address)) {
            self::$errors[$field] = 'Address is required';
            return;
        }
        
        if (strlen($address) < 10) {
            self::$errors[$field] = 'Address must be at least 10 characters';
            return;
        }
        
        if (strlen($address) > 200) {
            self::$errors[$field] = 'Address cannot exceed 200 characters';
            return;
        }
        
        if (!preg_match(self::$patterns['address_format'], $address)) {
            self::$errors[$field] = 'Address contains invalid characters';
            return;
        }
        
        // Verificar se contém número da casa/apartamento
        if (!preg_match('/\d/', $address)) {
            self::$errors[$field] = 'Address must include a street number';
            return;
        }
        
        self::$sanitizedData[$field] = ucwords(strtolower($address));
    }
    
    private static function validateSuburb($suburb, $field) {
        $suburb = trim($suburb);
        
        if (empty($suburb)) {
            self::$errors[$field] = 'Suburb is required';
            return;
        }
        
        if (strlen($suburb) < 2) {
            self::$errors[$field] = 'Suburb must be at least 2 characters';
            return;
        }
        
        if (!preg_match(self::$patterns['suburb_format'], $suburb)) {
            self::$errors[$field] = 'Suburb can only contain letters, spaces, hyphens and apostrophes';
            return;
        }
        
        self::$sanitizedData[$field] = ucwords(strtolower($suburb));
    }
    
    private static function validatePostcode($postcode, $field) {
        $postcode = trim($postcode);
        
        if (empty($postcode)) {
            self::$errors[$field] = 'Postcode is required';
            return;
        }
        
        if (!preg_match(self::$patterns['postcode_au'], $postcode)) {
            self::$errors[$field] = 'Invalid Australian postcode format (4 digits required)';
            return;
        }
        
        // Verificar se está na lista de postcodes atendidos
        if (!in_array($postcode, self::$validPostcodes)) {
            self::$errors[$field] = 'Sorry, we do not service this postcode yet';
            return;
        }
        
        self::$sanitizedData[$field] = $postcode;
    }
    
    private static function validateServiceDate($date, $field) {
        if (empty($date)) {
            self::$errors[$field] = 'Service date is required';
            return;
        }
        
        // Validar formato
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            self::$errors[$field] = 'Invalid date format (YYYY-MM-DD required)';
            return;
        }
        
        $now = new DateTime();
        $minDate = new DateTime('+2 days'); // Mínimo 48 horas
        $maxDate = new DateTime('+6 months'); // Máximo 6 meses
        
        if ($dateObj < $minDate) {
            self::$errors[$field] = 'Service date must be at least 48 hours in advance';
            return;
        }
        
        if ($dateObj > $maxDate) {
            self::$errors[$field] = 'Service date cannot be more than 6 months in advance';
            return;
        }
        
        // Verificar se não é domingo
        if ($dateObj->format('N') == 7) {
            self::$errors[$field] = 'Service not available on Sundays';
            return;
        }
        
        // Verificar feriados
        $holidays = ['2025-01-01', '2025-01-26', '2025-04-25', '2025-12-25', '2025-12-26'];
        if (in_array($date, $holidays)) {
            self::$errors[$field] = 'Service not available on public holidays';
            return;
        }
        
        self::$sanitizedData[$field] = $date;
    }
    
    private static function validateServiceType($serviceType, $field) {
        $validTypes = ['house-cleaning', 'deep-cleaning', 'office-cleaning', 'carpet-cleaning', 'window-cleaning'];
        
        if (empty($serviceType)) {
            self::$errors[$field] = 'Service type is required';
            return;
        }
        
        if (!in_array($serviceType, $validTypes)) {
            self::$errors[$field] = 'Invalid service type selected';
            return;
        }
        
        self::$sanitizedData[$field] = $serviceType;
    }
    
    private static function validateRecurrence($recurrence, $field) {
        $validPatterns = ['one-time', 'weekly', 'fortnightly', 'monthly'];
        
        if (empty($recurrence)) {
            self::$errors[$field] = 'Recurrence pattern is required';
            return;
        }
        
        if (!in_array($recurrence, $validPatterns)) {
            self::$errors[$field] = 'Invalid recurrence pattern';
            return;
        }
        
        self::$sanitizedData[$field] = $recurrence;
    }
    
    private static function validateSpecialInstructions($instructions, $field) {
        $instructions = trim($instructions);
        
        if (strlen($instructions) > 500) {
            self::$errors[$field] = 'Special instructions cannot exceed 500 characters';
            return;
        }
        
        // Verificar conteúdo inapropriado
        if (self::containsProfanity($instructions)) {
            self::$errors[$field] = 'Special instructions contain inappropriate content';
            return;
        }
        
        // Remover HTML tags
        $cleaned = strip_tags($instructions);
        
        self::$sanitizedData[$field] = $cleaned;
    }
    
    private static function validateDiscountCode($code, $field) {
        $code = trim(strtoupper($code));
        
        if (strlen($code) < 3 || strlen($code) > 20) {
            self::$errors[$field] = 'Invalid discount code format';
            return;
        }
        
        if (!preg_match('/^[A-Z0-9]+$/', $code)) {
            self::$errors[$field] = 'Discount code can only contain letters and numbers';
            return;
        }
        
        // Verificar se o código existe (simulado)
        $validCodes = ['WELCOME10', 'SAVE20', 'FIRST50', 'LOYAL15'];
        if (!in_array($code, $validCodes)) {
            self::$errors[$field] = 'Invalid discount code';
            return;
        }
        
        self::$sanitizedData[$field] = $code;
    }
    
    private static function validateReferralCode($code, $field) {
        $code = trim(strtoupper($code));
        
        if (strlen($code) < 5 || strlen($code) > 15) {
            self::$errors[$field] = 'Invalid referral code format';
            return;
        }
        
        if (!preg_match('/^[A-Z0-9]+$/', $code)) {
            self::$errors[$field] = 'Referral code can only contain letters and numbers';
            return;
        }
        
        self::$sanitizedData[$field] = $code;
    }
    
    private static function validateNumber($value, $field, $min = null, $max = null) {
        if (!is_numeric($value)) {
            self::$errors[$field] = ucfirst($field) . ' must be a number';
            return;
        }
        
        $value = (int)$value;
        
        if ($min !== null && $value < $min) {
            self::$errors[$field] = ucfirst($field) . " must be at least {$min}";
            return;
        }
        
        if ($max !== null && $value > $max) {
            self::$errors[$field] = ucfirst($field) . " cannot exceed {$max}";
            return;
        }
        
        self::$sanitizedData[$field] = $value;
    }
    
    /**
     * Validações cruzadas entre campos
     */
    private static function validateCrossReferences($data) {
        // Validar consistência postcode/suburb
        if (isset(self::$sanitizedData['postcode']) && isset(self::$sanitizedData['suburb'])) {
            if (!self::validatePostcodeSuburbMatch(self::$sanitizedData['postcode'], self::$sanitizedData['suburb'])) {
                self::$errors['postcode_suburb_mismatch'] = 'Postcode and suburb do not match';
            }
        }
        
        // Validar data de serviço vs recorrência
        if (isset(self::$sanitizedData['selected_date']) && isset(self::$sanitizedData['recurrence_pattern'])) {
            if (self::$sanitizedData['recurrence_pattern'] !== 'one-time') {
                $serviceDate = new DateTime(self::$sanitizedData['selected_date']);
                $dayOfWeek = $serviceDate->format('N');
                
                // Sábado tem limitações para serviços recorrentes
                if ($dayOfWeek == 6 && in_array(self::$sanitizedData['recurrence_pattern'], ['weekly', 'fortnightly'])) {
                    self::$errors['recurring_weekend'] = 'Weekly and fortnightly services not available on Saturdays';
                }
            }
        }
    }
    
    /**
     * Funções auxiliares
     */
    private static function validateRequired($data, $fields) {
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                self::$errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
    }
    
    private static function containsProfanity($text) {
        $profanityList = ['spam', 'scam', 'fake']; // Lista simplificada
        $text = strtolower($text);
        
        foreach ($profanityList as $word) {
            if (strpos($text, $word) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private static function isValidEmailDomain($domain) {
        // Verificar se o domínio tem MX record (simplificado)
        $validDomains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com'];
        return in_array($domain, $validDomains) || checkdnsrr($domain, 'MX');
    }
    
    private static function validatePostcodeSuburbMatch($postcode, $suburb) {
        // Simulação de validação postcode/suburb
        // Em implementação real, usar API de geocoding
        return true;
    }
    
    private static function getValidationSummary() {
        return [
            'total_fields_validated' => count(self::$sanitizedData),
            'errors_found' => count(self::$errors),
            'validation_passed' => empty(self::$errors),
            'sanitization_applied' => !empty(self::$sanitizedData)
        ];
    }
    
    /**
     * Sanitização geral de strings
     */
    public static function sanitizeString($input, $maxLength = null) {
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        if ($maxLength && strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        
        return $input;
    }
    
    /**
     * Validação de CSRF token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
            return false;
        }
        return true;
    }
    
    /**
     * Rate limiting por IP
     */
    public static function checkRateLimit($action = 'general', $maxAttempts = 10, $timeWindow = 3600) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit_{$action}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'start' => time()];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset counter se passou o tempo limite
        if (time() - $data['start'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'start' => time()];
            return true;
        }
        
        // Verificar se excedeu limite
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        $_SESSION[$key]['count']++;
        return true;
    }
}

// Função helper para validar dados rapidamente
function validateInput($data, $type = 'booking') {
    switch ($type) {
        case 'booking':
            return ValidationSystem::validateBookingData($data);
        case 'payment':
            return ValidationSystem::validatePaymentData($data);
        default:
            return ['valid' => false, 'errors' => ['Unknown validation type']];
    }
}

?>
