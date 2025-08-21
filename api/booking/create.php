<?php
/**
 * API de Criação de Booking - Blue Project V2
 * Sistema completo de processamento de bookings com Stripe integrado
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

// Start session for tracking
session_start();

// Include required configurations
require_once '../../config/stripe-config.php';
require_once '../../config/email-system.php';

// Rate limiting
if (!checkRateLimit()) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'rate_limit_exceeded',
        'message' => 'Too many requests. Please wait before trying again.'
    ]);
    exit();
}

try {
    // Initialize Stripe configuration
    StripeConfig::initialize();
    
    // Get and validate input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('Invalid JSON format');
    }
    
    // Validate required fields
    $validation = validateBookingData($input);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation_failed',
            'message' => 'Invalid booking data',
            'details' => $validation['errors']
        ]);
        exit();
    }
    
    // Sanitize input data
    $bookingData = sanitizeBookingData($input);
    
    // Check service availability
    $availability = checkServiceAvailability($bookingData);
    if (!$availability['available']) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'service_unavailable',
            'message' => 'Selected time slot is not available',
            'alternatives' => $availability['alternatives'] ?? []
        ]);
        exit();
    }
    
    // Calculate pricing with discounts
    $pricing = calculateFinalPricing($bookingData);
    
    // Process payment with Stripe
    $paymentResult = processStripePayment($bookingData, $pricing);
    if (!$paymentResult['success']) {
        http_response_code(402);
        echo json_encode([
            'success' => false,
            'error' => 'payment_failed',
            'message' => $paymentResult['message'],
            'payment_intent' => $paymentResult['payment_intent'] ?? null,
            'user_message' => $paymentResult['user_message'] ?? 'Payment processing failed'
        ]);
        exit();
    }
    
    // Create customer profile
    $customer = createOrUpdateCustomer($bookingData, $paymentResult);
    
    // Create main booking record
    $booking = createBookingRecord($bookingData, $pricing, $customer, $paymentResult);
    
    // Setup recurring billing if applicable
    if ($bookingData['recurrence_pattern'] !== 'one-time') {
        $subscription = createStripeSubscription($booking, $paymentResult);
        updateBookingWithSubscription($booking, $subscription);
    }
    
    // Process referral system
    if (!empty($bookingData['referral_code'])) {
        processReferralReward($bookingData['referral_code'], $booking);
    }
    
    // Send confirmation emails
    sendBookingConfirmationEmails($booking, $customer);
    
    // Log successful booking
    logBookingCreation($booking);
    
    // Store booking ID in session for confirmation page
    $_SESSION['last_booking_id'] = $booking['booking_id'];
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'booking_id' => $booking['booking_id'],
        'customer_id' => $customer['customer_id'],
        'confirmation_url' => '/booking-confirmation.php?booking_id=' . $booking['booking_id'],
        'dashboard_url' => '/customer/dashboard.php',
        'total_amount' => $pricing['final_amount'],
        'payment_status' => 'confirmed',
        'next_billing_date' => $booking['next_billing_date'] ?? null,
        'subscription_id' => $booking['stripe_subscription_id'] ?? null,
        'message' => 'Booking created successfully!'
    ]);

} catch (Exception $e) {
    // Log error for debugging
    error_log('Booking creation error: ' . $e->getMessage());
    
    // Return appropriate error response
    $statusCode = $e instanceof InvalidArgumentException ? 400 : 500;
    http_response_code($statusCode);
    
    echo json_encode([
        'success' => false,
        'error' => 'booking_creation_failed',
        'message' => 'Failed to create booking. Please try again.',
        'debug_message' => StripeConfig::isProduction() ? null : $e->getMessage()
    ]);
}

/**
 * Rate limiting check
 */
function checkRateLimit() {
    $key = 'rate_limit_' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
    $max_requests = 10; // requests per minute
    $window = 60; // seconds
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    if (time() - $data['start'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    if ($data['count'] >= $max_requests) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Validate booking data
 */
function validateBookingData($data) {
    $errors = [];
    $required_fields = [
        'service_type',
        'recurrence_pattern',
        'selected_date',
        'time_window',
        'customer_name',
        'customer_email',
        'customer_phone',
        'service_address',
        'suburb',
        'postcode',
        'payment_method_id'
    ];
    
    // Check required fields
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "Field '{$field}' is required";
        }
    }
    
    // Validate email format
    if (!empty($data['customer_email']) && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Validate phone format (Australian)
    if (!empty($data['customer_phone']) && !preg_match('/^(\+61|0)[2-9]\d{8}$/', $data['customer_phone'])) {
        $errors[] = 'Invalid Australian phone number format';
    }
    
    // Validate date format and future date
    if (!empty($data['selected_date'])) {
        $date = DateTime::createFromFormat('Y-m-d', $data['selected_date']);
        if (!$date || $date->format('Y-m-d') !== $data['selected_date']) {
            $errors[] = 'Invalid date format (YYYY-MM-DD required)';
        } elseif ($date <= new DateTime()) {
            $errors[] = 'Service date must be in the future';
        }
    }
    
    // Validate postcode (Australian)
    if (!empty($data['postcode']) && !preg_match('/^[0-9]{4}$/', $data['postcode'])) {
        $errors[] = 'Invalid Australian postcode (4 digits required)';
    }
    
    // Validate service type
    $valid_services = ['weekly-cleaning', 'fortnightly-cleaning', 'monthly-cleaning', 'one-time-cleaning', 'deep-cleaning'];
    if (!empty($data['service_type']) && !in_array($data['service_type'], $valid_services)) {
        $errors[] = 'Invalid service type';
    }
    
    // Validate recurrence pattern
    $valid_patterns = ['one-time', 'weekly', 'fortnightly', 'monthly'];
    if (!empty($data['recurrence_pattern']) && !in_array($data['recurrence_pattern'], $valid_patterns)) {
        $errors[] = 'Invalid recurrence pattern';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Sanitize booking data
 */
function sanitizeBookingData($data) {
    return [
        'service_type' => trim($data['service_type']),
        'recurrence_pattern' => trim($data['recurrence_pattern']),
        'selected_date' => trim($data['selected_date']),
        'time_window' => trim($data['time_window']),
        'customer_name' => trim($data['customer_name']),
        'customer_email' => strtolower(trim($data['customer_email'])),
        'customer_phone' => preg_replace('/[^0-9+]/', '', $data['customer_phone']),
        'service_address' => trim($data['service_address']),
        'suburb' => trim($data['suburb']),
        'postcode' => trim($data['postcode']),
        'special_instructions' => trim($data['special_instructions'] ?? ''),
        'bedrooms' => (int)($data['bedrooms'] ?? 2),
        'bathrooms' => (int)($data['bathrooms'] ?? 1),
        'payment_method_id' => trim($data['payment_method_id']),
        'discount_code' => trim($data['discount_code'] ?? ''),
        'referral_code' => trim($data['referral_code'] ?? ''),
        'total_duration' => (int)($data['total_duration'] ?? 12),
        'additional_services' => $data['additional_services'] ?? []
    ];
}

/**
 * Check service availability
 */
function checkServiceAvailability($data) {
    // Implementação real deve verificar no banco de dados
    // Por agora, simula verificação básica
    
    $selected_datetime = new DateTime($data['selected_date'] . ' ' . explode(' - ', $data['time_window'])[0]);
    $now = new DateTime();
    
    // Check if booking is at least 48 hours in advance
    $diff = $selected_datetime->diff($now);
    $hours_diff = ($diff->days * 24) + $diff->h;
    
    if ($hours_diff < 48) {
        return [
            'available' => false,
            'reason' => 'minimum_notice',
            'message' => 'Bookings must be made at least 48 hours in advance'
        ];
    }
    
    // Simulate availability check (replace with real database query)
    $available_slots = getAvailableTimeSlots($data['selected_date'], $data['suburb']);
    
    if (!in_array($data['time_window'], $available_slots)) {
        return [
            'available' => false,
            'reason' => 'slot_unavailable',
            'alternatives' => array_slice($available_slots, 0, 3)
        ];
    }
    
    return ['available' => true];
}

/**
 * Calculate final pricing with all discounts
 */
function calculateFinalPricing($data) {
    // Base pricing calculation
    $base_price = calculateBasePricing($data);
    
    // Apply discounts
    $discounts = [];
    
    // Volume discount for recurring services
    if ($data['recurrence_pattern'] !== 'one-time') {
        $volume_discount = calculateVolumeDiscount($data['total_duration'], $base_price);
        if ($volume_discount > 0) {
            $discounts['volume'] = $volume_discount;
        }
    }
    
    // Discount code
    if (!empty($data['discount_code'])) {
        $code_discount = validateAndApplyDiscountCode($data['discount_code'], $base_price);
        if ($code_discount > 0) {
            $discounts['code'] = $code_discount;
        }
    }
    
    // Referral discount
    if (!empty($data['referral_code'])) {
        $referral_discount = calculateReferralDiscount($data['referral_code'], $base_price);
        if ($referral_discount > 0) {
            $discounts['referral'] = $referral_discount;
        }
    }
    
    $total_discount = array_sum($discounts);
    $final_amount = max(0, $base_price - $total_discount);
    
    return [
        'base_price' => $base_price,
        'discounts' => $discounts,
        'total_discount' => $total_discount,
        'final_amount' => $final_amount,
        'tax_amount' => $final_amount * 0.1, // 10% GST
        'currency' => 'AUD'
    ];
}

/**
 * Process Stripe payment
 */
function processStripePayment($bookingData, $pricing) {
    try {
        // Create or retrieve customer
        $stripe_customer = \Stripe\Customer::create([
            'name' => $bookingData['customer_name'],
            'email' => $bookingData['customer_email'],
            'phone' => $bookingData['customer_phone'],
            'metadata' => StripeConfig::getDefaultMetadata([
                'suburb' => $bookingData['suburb'],
                'postcode' => $bookingData['postcode'],
                'service_type' => $bookingData['service_type']
            ])
        ]);
        
        // Attach payment method to customer
        $payment_method = \Stripe\PaymentMethod::retrieve($bookingData['payment_method_id']);
        $payment_method->attach(['customer' => $stripe_customer->id]);
        
        // For one-time services, create immediate payment intent
        if ($bookingData['recurrence_pattern'] === 'one-time') {
            $serviceConfig = StripeProducts::getServiceConfig($bookingData['service_type']);
            
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => StripeUtils::toCents($pricing['final_amount']),
                'currency' => strtolower(StripeConfig::getConfig('currency')),
                'customer' => $stripe_customer->id,
                'payment_method' => $bookingData['payment_method_id'],
                'confirmation_method' => 'manual',
                'confirm' => true,
                'statement_descriptor' => $serviceConfig['statement_descriptor'],
                'metadata' => StripeConfig::getDefaultMetadata([
                    'booking_type' => 'one_time_service',
                    'service_type' => $bookingData['service_type'],
                    'service_date' => $bookingData['selected_date'],
                    'customer_email' => $bookingData['customer_email']
                ])
            ]);
            
            return [
                'success' => true,
                'stripe_customer_id' => $stripe_customer->id,
                'payment_intent_id' => $payment_intent->id,
                'payment_method_id' => $bookingData['payment_method_id'],
                'amount_charged' => $pricing['final_amount']
            ];
        }
        
        // For recurring services, setup for subscription (cobrança acontece depois)
        return [
            'success' => true,
            'stripe_customer_id' => $stripe_customer->id,
            'payment_method_id' => $bookingData['payment_method_id'],
            'setup_for_subscription' => true
        ];
        
    } catch (\Stripe\Exception\CardException $e) {
        $errorInfo = StripeUtils::formatStripeError($e);
        return [
            'success' => false,
            'message' => $errorInfo['message'],
            'user_message' => $errorInfo['user_message'],
            'error_code' => $errorInfo['code'] ?? null
        ];
    } catch (\Stripe\Exception\RateLimitException $e) {
        $errorInfo = StripeUtils::formatStripeError($e);
        return [
            'success' => false,
            'message' => $errorInfo['message'],
            'user_message' => $errorInfo['user_message']
        ];
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        $errorInfo = StripeUtils::formatStripeError($e);
        return [
            'success' => false,
            'message' => $errorInfo['message'],
            'user_message' => $errorInfo['user_message']
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Payment processing failed: ' . $e->getMessage(),
            'user_message' => 'Payment processing failed. Please check your payment details and try again.'
        ];
    }
}

/**
 * Create or update customer record
 */
function createOrUpdateCustomer($bookingData, $paymentResult) {
    // Em implementação real, verificar se cliente já existe
    return [
        'customer_id' => StripeUtils::generateUniqueId('CUST'),
        'stripe_customer_id' => $paymentResult['stripe_customer_id'],
        'name' => $bookingData['customer_name'],
        'email' => $bookingData['customer_email'],
        'phone' => $bookingData['customer_phone'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Create booking record
 */
function createBookingRecord($bookingData, $pricing, $customer, $paymentResult) {
    return [
        'booking_id' => StripeUtils::generateUniqueId('BOOK'),
        'customer_id' => $customer['customer_id'],
        'service_type' => $bookingData['service_type'],
        'recurrence_pattern' => $bookingData['recurrence_pattern'],
        'first_service_date' => $bookingData['selected_date'],
        'time_window' => $bookingData['time_window'],
        'service_address' => $bookingData['service_address'],
        'suburb' => $bookingData['suburb'],
        'postcode' => $bookingData['postcode'],
        'special_instructions' => $bookingData['special_instructions'],
        'bedrooms' => $bookingData['bedrooms'],
        'bathrooms' => $bookingData['bathrooms'],
        'base_price' => $pricing['base_price'],
        'total_discount' => $pricing['total_discount'],
        'final_amount' => $pricing['final_amount'],
        'stripe_customer_id' => $paymentResult['stripe_customer_id'],
        'payment_method_id' => $paymentResult['payment_method_id'],
        'payment_intent_id' => $paymentResult['payment_intent_id'] ?? null,
        'status' => $bookingData['recurrence_pattern'] === 'one-time' ? 'confirmed' : 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'next_billing_date' => calculateNextBillingDate($bookingData),
        'customer_name' => $bookingData['customer_name'],
        'customer_email' => $bookingData['customer_email'],
        'customer_phone' => $bookingData['customer_phone']
    ];
}

/**
 * Create Stripe subscription for recurring services
 */
function createStripeSubscription($booking, $paymentResult) {
    if ($booking['recurrence_pattern'] === 'one-time') {
        return null;
    }
    
    $interval_mapping = [
        'weekly' => ['interval' => 'week', 'interval_count' => 1],
        'fortnightly' => ['interval' => 'week', 'interval_count' => 2],
        'monthly' => ['interval' => 'month', 'interval_count' => 1]
    ];
    
    $interval_config = $interval_mapping[$booking['recurrence_pattern']];
    $serviceConfig = StripeProducts::getServiceConfig($booking['service_type']);
    
    try {
        // Create product if not exists
        $product = \Stripe\Product::create([
            'name' => $serviceConfig['name'],
            'metadata' => StripeConfig::getDefaultMetadata($booking)
        ]);
        
        // Create price for the product
        $price = \Stripe\Price::create([
            'unit_amount' => StripeUtils::toCents($booking['final_amount']),
            'currency' => strtolower(StripeConfig::getConfig('currency')),
            'recurring' => [
                'interval' => $interval_config['interval'],
                'interval_count' => $interval_config['interval_count']
            ],
            'product' => $product->id,
            'metadata' => StripeConfig::getDefaultMetadata($booking)
        ]);
        
        // Calculate billing date (2 days before service)
        $billingDate = new DateTime($booking['first_service_date']);
        $billingDate->modify('-2 days');
        
        // Create subscription
        $subscription = \Stripe\Subscription::create([
            'customer' => $paymentResult['stripe_customer_id'],
            'items' => [['price' => $price->id]],
            'default_payment_method' => $paymentResult['payment_method_id'],
            'billing_cycle_anchor' => $billingDate->getTimestamp(),
            'metadata' => StripeConfig::getDefaultMetadata([
                'booking_id' => $booking['booking_id'],
                'service_type' => $booking['service_type'],
                'pause_tier' => '1',
                'total_pauses_used' => '0',
                'last_pause_date' => '',
                'cancellation_penalty' => calculateCancellationPenalty($booking),
                'original_start_date' => $booking['first_service_date']
            ])
        ]);
        
        return [
            'stripe_subscription_id' => $subscription->id,
            'stripe_product_id' => $product->id,
            'stripe_price_id' => $price->id,
            'billing_cycle_anchor' => $billingDate->format('Y-m-d'),
            'status' => $subscription->status,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to create subscription: ' . $e->getMessage());
    }
}

/**
 * Helper functions
 */
function processReferralReward($referral_code, $booking) {
    // Process referral rewards
    error_log("Processing referral reward for code: $referral_code - Booking: {$booking['booking_id']}");
}

function generateUniqueId($prefix) {
    return StripeUtils::generateUniqueId($prefix);
}

function calculateNextBillingDate($bookingData) {
    if ($bookingData['recurrence_pattern'] === 'one-time') {
        return null;
    }
    
    $date = new DateTime($bookingData['selected_date']);
    $date->sub(new DateInterval('P2D')); // Charge 2 days before service
    
    return $date->format('Y-m-d');
}

function calculateBasePricing($data) {
    // Base rates per service type and frequency
    $base_rates = [
        'weekly-cleaning' => ['weekly' => 120, 'fortnightly' => 140, 'monthly' => 160, 'one-time' => 180],
        'fortnightly-cleaning' => ['weekly' => 120, 'fortnightly' => 140, 'monthly' => 160, 'one-time' => 180],
        'monthly-cleaning' => ['weekly' => 120, 'fortnightly' => 140, 'monthly' => 160, 'one-time' => 180],
        'one-time-cleaning' => ['weekly' => 120, 'fortnightly' => 140, 'monthly' => 160, 'one-time' => 180],
        'deep-cleaning' => ['weekly' => 180, 'fortnightly' => 200, 'monthly' => 220, 'one-time' => 250],
        'house-cleaning' => ['weekly' => 120, 'fortnightly' => 140, 'monthly' => 160, 'one-time' => 180],
        'office-cleaning' => ['weekly' => 150, 'fortnightly' => 170, 'monthly' => 190, 'one-time' => 220],
        'carpet-cleaning' => ['weekly' => 200, 'fortnightly' => 220, 'monthly' => 240, 'one-time' => 280],
        'window-cleaning' => ['weekly' => 80, 'fortnightly' => 100, 'monthly' => 120, 'one-time' => 150]
    ];
    
    $service_type = $data['service_type'];
    $recurrence = $data['recurrence_pattern'];
    $base_price = $base_rates[$service_type][$recurrence] ?? $base_rates['house-cleaning'][$recurrence];
    
    // Room-based adjustments
    if ($data['bedrooms'] > 3) {
        $base_price += ($data['bedrooms'] - 3) * 15;
    }
    
    if ($data['bathrooms'] > 2) {
        $base_price += ($data['bathrooms'] - 2) * 10;
    }
    
    // Additional services
    $extras_total = 0;
    if (!empty($data['additional_services'])) {
        $extras_prices = [
            'inside-windows' => 25,
            'inside-cupboards' => 20,
            'inside-fridge' => 15,
            'inside-oven' => 20,
            'garage' => 30,
            'balcony' => 15
        ];
        
        foreach ($data['additional_services'] as $service) {
            if (isset($extras_prices[$service])) {
                $extras_total += $extras_prices[$service];
            }
        }
    }
    
    return $base_price + $extras_total;
}

function getAvailableTimeSlots($date, $suburb) {
    // Simulate available slots
    return [
        '8:00 AM - 10:00 AM',
        '10:00 AM - 12:00 PM',
        '12:00 PM - 2:00 PM',
        '2:00 PM - 4:00 PM'
    ];
}

function getStripeSecretKey() {
    return StripeConfig::getConfig('secret_key');
}

function calculateVolumeDiscount($duration, $base_price) {
    if ($duration >= 12) return $base_price * 0.15; // 15% for 12+ months
    if ($duration >= 6) return $base_price * 0.10;  // 10% for 6+ months
    if ($duration >= 3) return $base_price * 0.05;  // 5% for 3+ months
    return 0;
}

function validateAndApplyDiscountCode($code, $base_price) {
    $valid_codes = [
        'WELCOME10' => 0.10,
        'SAVE20' => 0.20,
        'FIRST50' => 50
    ];
    
    if (isset($valid_codes[$code])) {
        $discount = $valid_codes[$code];
        return $discount < 1 ? $base_price * $discount : min($discount, $base_price);
    }
    
    return 0;
}

function calculateReferralDiscount($code, $base_price) {
    // Simplified referral validation
    return $base_price * 0.10; // 10% referral discount
}

function calculateCancellationPenalty($booking) {
    // Penalty calculation logic
    $base_penalty = $booking['final_amount'] * 0.5;
    return min($base_penalty, 100); // Max $100 penalty
}

function sendBookingConfirmationEmails($booking, $customer) {
    // Send booking confirmation email to customer
    $emailResult = EmailSystem::sendBookingConfirmation($booking, $customer);
    
    if (!$emailResult['success']) {
        error_log("Failed to send booking confirmation to customer: " . $emailResult['message']);
    }
    
    // Send internal notification if in production
    if (StripeConfig::isProduction()) {
        $adminEmail = 'bookings@bluecleaningservices.com.au';
        $subject = "New Booking: {$booking['service_type']} - {$booking['booking_id']}";
        $html = "
            <h3>New Booking Received</h3>
            <p><strong>Booking ID:</strong> {$booking['booking_id']}</p>
            <p><strong>Customer:</strong> {$customer['name']} ({$customer['email']})</p>
            <p><strong>Service:</strong> {$booking['service_type']}</p>
            <p><strong>Date:</strong> {$booking['first_service_date']}</p>
            <p><strong>Amount:</strong> $" . number_format($booking['final_amount'], 2) . "</p>
        ";
        
        EmailSystem::send($adminEmail, $subject, $html);
    }
}

function logBookingCreation($booking) {
    $logData = [
        'booking_id' => $booking['booking_id'],
        'customer_email' => $booking['customer_email'],
        'service_type' => $booking['service_type'],
        'amount' => $booking['final_amount'],
        'created_at' => $booking['created_at']
    ];
    
    error_log("Booking created successfully: " . json_encode($logData));
}

function updateBookingWithSubscription($booking, $subscription) {
    // In real implementation, update database record
    error_log("Booking {$booking['booking_id']} updated with subscription: {$subscription['stripe_subscription_id']}");
}
?>
