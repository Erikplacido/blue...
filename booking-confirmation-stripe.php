<?php
/**
 * =========================================================
 * BOOKING CONFIRMATION - STRIPE INTEGRATION
 * =========================================================
 * 
 * @file booking-confirmation.php
 * @description Página de confirmação após checkout Stripe
 * @version 3.0 - STRIPE INTEGRATION
 * @date 2025-08-10
 */

session_start();

// Carregar dependências
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/australian-database.php';
require_once __DIR__ . '/vendor/autoload.php';

// Configurar Stripe
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? '');

// Obter session_id da URL
$sessionId = $_GET['session_id'] ?? null;

if (!$sessionId) {
    header('Location: booking3.php?error=no_session');
    exit();
}

try {
    // Buscar sessão do Stripe
    $session = \Stripe\Checkout\Session::retrieve($sessionId);
    
    if (!$session) {
        throw new Exception('Checkout session not found');
    }
    
    // Extrair dados da metadata
    $metadata = $session->metadata;
    $bookingCode = $metadata['booking_code'] ?? 'Unknown';
    
    // Conectar ao banco
    $db = AustralianDatabase::getInstance();
    $connection = $db->getConnection();
    
    // Buscar dados do booking
    $bookingStmt = $connection->prepare("
        SELECT b.*, s.name as service_name, s.description as service_description,
               CONCAT(p.first_name, ' ', p.last_name) as professional_name,
               p.phone as professional_phone, p.email as professional_email
        FROM bookings b
        LEFT JOIN services s ON b.service_id = s.id
        LEFT JOIN professionals p ON b.professional_id = p.id
        WHERE b.booking_code = ?
    ");
    $bookingStmt->execute([$bookingCode]);
    $booking = $bookingStmt->fetch();
    
    if (!$booking) {
        throw new Exception('Booking not found in database');
    }
    
} catch (Exception $e) {
    error_log("Booking confirmation error: " . $e->getMessage());
    $errorMessage = "Unable to retrieve booking information. Please contact support with reference: " . ($sessionId ?? 'N/A');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - Blue Cleaning Services</title>
    
    <!-- Meta tags -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Your booking has been confirmed with Blue Cleaning Services">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/blue.css">
    <link rel="stylesheet" href="assets/css/liquid-glass-components.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .confirmation-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .success-card {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.2);
        }
        
        .success-icon {
            font-size: 60px;
            margin-bottom: 20px;
            animation: successPulse 2s ease-in-out infinite;
        }
        
        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .success-title {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .success-subtitle {
            font-size: 1.2em;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .booking-reference {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px 25px;
            border-radius: 10px;
            display: inline-block;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 1.1em;
            margin-top: 10px;
        }
        
        .details-grid {
            display: grid;
            gap: 30px;
            margin-top: 30px;
        }
        
        .detail-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--glass-shadow);
        }
        
        .detail-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .detail-content {
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 500;
            opacity: 0.8;
        }
        
        .info-value {
            font-weight: bold;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 25px;
            border: none;
            border-radius: 12px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: var(--glass-border);
            color: var(--text-color);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .next-steps {
            background: linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%);
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
        }
        
        .next-steps h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .step-list {
            list-style: none;
            padding: 0;
        }
        
        .step-list li {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 12px;
            color: var(--text-color);
        }
        
        .step-number {
            background: var(--primary-color);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .confirmation-container {
                margin: 20px auto;
            }
            
            .success-card {
                padding: 30px 20px;
            }
            
            .success-title {
                font-size: 2em;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <?php if (isset($errorMessage)): ?>
            <!-- Error State -->
            <div class="error-card">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2>Unable to Load Booking</h2>
                <p><?= htmlspecialchars($errorMessage) ?></p>
                <a href="booking3.php" class="action-btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Return to Booking
                </a>
            </div>
        <?php else: ?>
            <!-- Success State -->
            <div class="success-card">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="success-title">Booking Confirmed!</h1>
                <p class="success-subtitle">Your cleaning service has been successfully scheduled</p>
                <div class="booking-reference">
                    Reference: <?= htmlspecialchars($booking['booking_code']) ?>
                </div>
            </div>
            
            <div class="details-grid">
                <!-- Service Details -->
                <div class="detail-card">
                    <div class="detail-header">
                        <i class="fas fa-broom"></i>
                        Service Details
                    </div>
                    <div class="detail-content">
                        <div class="info-row">
                            <span class="info-label">Service:</span>
                            <span class="info-value"><?= htmlspecialchars($booking['service_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date & Time:</span>
                            <span class="info-value">
                                <?= date('l, F j, Y', strtotime($booking['scheduled_date'])) ?> 
                                at <?= date('g:i A', strtotime($booking['scheduled_time'])) ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Duration:</span>
                            <span class="info-value"><?= $booking['duration_minutes'] ?> minutes</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value">
                                <?= htmlspecialchars($booking['street_address']) ?><br>
                                <?= htmlspecialchars($booking['suburb']) ?>, <?= htmlspecialchars($booking['state']) ?> <?= htmlspecialchars($booking['postcode']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Professional Details -->
                <div class="detail-card">
                    <div class="detail-header">
                        <i class="fas fa-user-tie"></i>
                        Your Professional
                    </div>
                    <div class="detail-content">
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?= htmlspecialchars($booking['professional_name'] ?? 'To be assigned') ?></span>
                        </div>
                        <?php if (!empty($booking['professional_phone'])): ?>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value">
                                <a href="tel:<?= htmlspecialchars($booking['professional_phone']) ?>">
                                    <?= htmlspecialchars($booking['professional_phone']) ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <i class="fas fa-check-circle" style="color: #10B981;"></i>
                                Confirmed & Assigned
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Details -->
                <div class="detail-card">
                    <div class="detail-header">
                        <i class="fas fa-credit-card"></i>
                        Payment Summary
                    </div>
                    <div class="detail-content">
                        <div class="info-row">
                            <span class="info-label">Service Price:</span>
                            <span class="info-value">$<?= number_format($booking['base_price'], 2) ?></span>
                        </div>
                        <?php if ($booking['extras_price'] > 0): ?>
                        <div class="info-row">
                            <span class="info-label">Extras:</span>
                            <span class="info-value">$<?= number_format($booking['extras_price'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['discount_amount'] > 0): ?>
                        <div class="info-row">
                            <span class="info-label">Discount:</span>
                            <span class="info-value" style="color: #10B981;">-$<?= number_format($booking['discount_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label">GST (10%):</span>
                            <span class="info-value">$<?= number_format($booking['gst_amount'], 2) ?></span>
                        </div>
                        <div class="info-row" style="border-top: 2px solid var(--primary-color); padding-top: 10px; margin-top: 10px;">
                            <span class="info-label" style="font-size: 1.1em;"><strong>Total Paid:</strong></span>
                            <span class="info-value" style="font-size: 1.2em; color: var(--primary-color);"><strong>$<?= number_format($booking['total_amount'], 2) ?> AUD</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Payment Status:</span>
                            <span class="info-value">
                                <i class="fas fa-check-circle" style="color: #10B981;"></i>
                                <?= ucfirst($session->payment_status) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="customer/dashboard.php" class="action-btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i>
                    My Dashboard
                </a>
                <a href="payment_history.php" class="action-btn btn-secondary">
                    <i class="fas fa-receipt"></i>
                    Payment History
                </a>
                <a href="mailto:support@bluecleaning.com.au" class="action-btn btn-outline">
                    <i class="fas fa-headset"></i>
                    Contact Support
                </a>
                <a href="booking3.php" class="action-btn btn-outline">
                    <i class="fas fa-plus"></i>
                    Book Another Service
                </a>
            </div>
            
            <!-- Next Steps -->
            <div class="next-steps">
                <h3>
                    <i class="fas fa-list-check"></i>
                    What Happens Next?
                </h3>
                <ul class="step-list">
                    <li>
                        <span class="step-number">1</span>
                        <span>You'll receive a confirmation email with all the details</span>
                    </li>
                    <li>
                        <span class="step-number">2</span>
                        <span>Our professional will contact you 24 hours before the service</span>
                    </li>
                    <li>
                        <span class="step-number">3</span>
                        <span>Payment was processed securely via Stripe</span>
                    </li>
                    <li>
                        <span class="step-number">4</span>
                        <span>You can manage your booking from your dashboard</span>
                    </li>
                    <li>
                        <span class="step-number">5</span>
                        <span>Rate your experience after the service completion</span>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Analytics -->
    <script>
        // Track successful booking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                'transaction_id': '<?= htmlspecialchars($booking['booking_code'] ?? '') ?>',
                'value': <?= $booking['total_amount'] ?? 0 ?>,
                'currency': 'AUD',
                'items': [{
                    'item_id': '<?= htmlspecialchars($booking['service_name'] ?? '') ?>',
                    'item_name': '<?= htmlspecialchars($booking['service_name'] ?? '') ?>',
                    'category': 'cleaning_service',
                    'quantity': 1,
                    'price': <?= $booking['total_amount'] ?? 0 ?>
                }]
            });
        }
        
        console.log('✅ Booking confirmed successfully:', {
            booking_code: '<?= htmlspecialchars($booking['booking_code'] ?? '') ?>',
            session_id: '<?= htmlspecialchars($sessionId) ?>',
            amount: <?= $booking['total_amount'] ?? 0 ?>
        });
    </script>
</body>
</html>
