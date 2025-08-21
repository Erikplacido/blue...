<?php
require_once __DIR__ . '/../config/stripe-enterprise.php';
require_once __DIR__ . '/../config/australian-database.php';

$bookingId = $_GET['booking_id'] ?? null;
$sessionId = $_GET['session_id'] ?? null;
$bookingData = null;

if ($bookingId || $sessionId) {
    try {
        $db = AustralianDatabase::getInstance();
        $connection = $db->getConnection();
        
        if ($sessionId) {
            // Buscar por session_id
            $stmt = $connection->prepare("
                SELECT * FROM bookings 
                WHERE stripe_session_id = ?
            ");
            $stmt->execute([$sessionId]);
        } else {
            // Buscar por booking_id
            $stmt = $connection->prepare("
                SELECT * FROM bookings 
                WHERE id = ? OR booking_code = ?
            ");
            $stmt->execute([$bookingId, $bookingId]);
        }
        
        $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Marcar como cancelado se encontrado
        if ($bookingData && $bookingData['status'] !== 'cancelled') {
            $stmt = $connection->prepare("
                UPDATE bookings 
                SET status = 'cancelled', payment_status = 'cancelled', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$bookingData['id']]);
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar cancelamento: " . $e->getMessage());
    }
}

// Carregar configurações
loadStripeEnv();
?>
<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - Blue Cleaning Services</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #ff7b7b 0%, #ff416c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container { 
            max-width: 600px; 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .cancel-icon {
            width: 80px;
            height: 80px;
            background: #ffc107;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 18px; }
        .booking-info { 
            background: #fff3cd; 
            padding: 25px; 
            border-radius: 10px; 
            margin: 25px 0; 
            border: 1px solid #ffeaa7;
        }
        .booking-info h3 { color: #856404; margin-bottom: 15px; }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ffeaa7;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #856404; }
        .detail-value { color: #333; }
        .btn { 
            display: inline-block; 
            padding: 15px 30px; 
            background: #007bff; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            margin: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover { background: #0056b3; transform: translateY(-2px); }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .reasons {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        .reasons h3 { color: #333; margin-bottom: 15px; text-align: center; }
        .reasons ul { list-style: none; }
        .reasons li { 
            padding: 8px 0; 
            position: relative;
            padding-left: 25px;
        }
        .reasons li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #007bff;
            font-weight: bold;
        }
        .contact-info { 
            text-align: center; 
            margin-top: 30px; 
            padding: 20px; 
            background: #f8f9fa; 
            border-radius: 10px;
            color: #666; 
            font-size: 14px; 
        }
        .reassurance {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .reassurance strong { color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <div class="cancel-icon">⚠️</div>
        <h1>Payment Cancelled</h1>
        <p class="subtitle">Your payment was cancelled. No charges were made to your account.</p>
        
        <div class="reassurance">
            <strong>✅ No worries!</strong> Your payment was safely cancelled and your card was not charged.
        </div>
        
        <?php if ($bookingData): ?>
            <div class="booking-info">
                <h3>Your booking details are still saved:</h3>
                
                <div class="detail-row">
                    <span class="detail-label">Booking Code:</span>
                    <span class="detail-value"><?= htmlspecialchars($bookingData['booking_code']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Customer:</span>
                    <span class="detail-value"><?= htmlspecialchars($bookingData['customer_name']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Service:</span>
                    <span class="detail-value"><?= htmlspecialchars($bookingData['service_type'] ?? 'Cleaning Service') ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Total:</span>
                    <span class="detail-value"><strong>$<?= number_format($bookingData['total_amount'], 2) ?> AUD</strong></span>
                </div>
                
                <?php if (!empty($bookingData['referral_code'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Referral Code:</span>
                    <span class="detail-value"><?= htmlspecialchars($bookingData['referral_code']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <p style="color: #666; margin: 15px 0;">
                <em>You can complete the payment at any time. Your booking details won't be lost.</em>
            </p>
        <?php endif; ?>
        
        <div class="reasons">
            <h3>Common reasons for payment cancellation:</h3>
            <ul>
                <li>Changed mind about the service timing</li>
                <li>Want to review booking details again</li>
                <li>Need to use a different payment method</li>
                <li>Want to add more services before paying</li>
                <li>Technical issues during checkout</li>
            </ul>
        </div>
        
        <div style="margin: 30px 0;">
            <?php if ($bookingData): ?>
                <a href="/booking3.php?resume=<?= htmlspecialchars($bookingData['booking_code']) ?>" class="btn btn-success">
                    Complete This Booking
                </a>
            <?php endif; ?>
            <a href="/booking3.php" class="btn btn-warning">Start New Booking</a>
            <a href="/" class="btn">Back to Home</a>
        </div>
        
        <div style="text-align: center; margin: 20px 0;">
            <h4>Need Help?</h4>
            <p>Our customer service team is here to assist you!</p>
            <div style="margin: 15px 0;">
                <a href="tel:<?= $_ENV['BUSINESS_PHONE'] ?? '+61480123456' ?>" class="btn" style="background: #28a745;">
                    📞 Call Us Now
                </a>
                <a href="mailto:<?= $_ENV['BUSINESS_EMAIL'] ?? 'info@bluecleaning.com.au' ?>" class="btn">
                    📧 Send Email
                </a>
            </div>
        </div>
        
        <div class="contact-info">
            <h4><?= $_ENV['BUSINESS_NAME'] ?? 'Blue Cleaning Services Pty Ltd' ?></h4>
            <p>📞 <?= $_ENV['BUSINESS_PHONE'] ?? '+61 480 123 456' ?> | 
            📧 <?= $_ENV['BUSINESS_EMAIL'] ?? 'info@bluecleaning.com.au' ?></p>
            <p>🕐 Business Hours: Monday - Friday 8AM - 6PM AEST</p>
            <p style="margin-top: 15px; font-size: 12px; color: #999;">
                Your privacy is important to us. No payment information was processed or stored.
            </p>
        </div>
    </div>
</body>
</html>
