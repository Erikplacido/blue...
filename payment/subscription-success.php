<?php
require_once __DIR__ . '/../config/stripe-enterprise.php';
require_once __DIR__ . '/../config/australian-database.php';

$sessionId = $_GET['session_id'] ?? null;
$bookingData = null;
$subscriptionData = null;
$error = null;

if ($sessionId) {
    try {
        // Recuperar sessão do Stripe
        $session = \Stripe\Checkout\Session::retrieve($sessionId);
        
        if ($session->payment_status === 'paid') {
            $bookingId = $session->metadata['booking_id'] ?? null;
            
            if ($bookingId) {
                // Buscar dados do booking
                $db = AustralianDatabase::getInstance();
                $connection = $db->getConnection();
                
                $stmt = $connection->prepare("
                    SELECT b.*, bs.stripe_subscription_id, bs.recurrence_type
                    FROM bookings b
                    LEFT JOIN booking_subscriptions bs ON b.id = bs.booking_id
                    WHERE b.id = ? OR b.booking_code = ?
                ");
                $stmt->execute([$bookingId, $session->metadata['booking_code'] ?? $bookingId]);
                $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Se for assinatura, buscar dados adicionais
                if (!empty($bookingData['stripe_subscription_id'])) {
                    $subscription = \Stripe\Subscription::retrieve($bookingData['stripe_subscription_id']);
                    $subscriptionData = $subscription;
                }
            }
        }
        
    } catch (Exception $e) {
        $error = "Erro ao processar pagamento: " . $e->getMessage();
        error_log($error);
    }
}

// Carregar configurações do .env para exibição
loadStripeEnv();
?>
<!DOCTYPE html>
<html lang="en-AU">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - Blue Cleaning Services</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            background: #dc3545;
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
        .booking-details { 
            background: #f8f9fa; 
            padding: 25px; 
            border-radius: 10px; 
            margin: 25px 0; 
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #555; }
        .detail-value { color: #333; }
        .subscription-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }
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
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .contact-info { 
            text-align: center; 
            margin-top: 30px; 
            padding: 20px; 
            background: #f8f9fa; 
            border-radius: 10px;
            color: #666; 
            font-size: 14px; 
        }
        .next-steps {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        .next-steps h3 { color: #856404; margin-bottom: 15px; }
        .next-steps ul { list-style: none; }
        .next-steps li { 
            padding: 5px 0; 
            position: relative;
            padding-left: 25px;
        }
        .next-steps li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="error-icon">❌</div>
            <h1>Payment Error</h1>
            <p class="subtitle"><?= htmlspecialchars($error) ?></p>
            
        <?php elseif ($bookingData): ?>
            <div class="success-icon">✅</div>
            <h1>Booking Confirmed!</h1>
            <p class="subtitle">Thank you for choosing Blue Cleaning Services</p>
            
            <div class="booking-details">
                <h3 style="margin-bottom: 15px; color: #333;">Booking Details</h3>
                
                <div class="detail-row">
                    <span class="detail-label">Booking Code:</span>
                    <span class="detail-value"><strong><?= htmlspecialchars($bookingData['booking_code']) ?></strong></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Customer:</span>
                    <span class="detail-value"><?= htmlspecialchars($bookingData['customer_name']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Service:</span>
                    <span class="detail-value"><?= htmlspecialchars($bookingData['service_type'] ?? 'Regular Cleaning') ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value"><strong>$<?= number_format($bookingData['total_amount'], 2) ?> AUD</strong></span>
                </div>
                
                <?php if (!empty($bookingData['booking_date'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Service Date:</span>
                    <span class="detail-value"><?= date('d/m/Y', strtotime($bookingData['booking_date'])) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($bookingData['referral_code'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Referral Code:</span>
                    <span class="detail-value"><?= htmlspecialchars($bookingData['referral_code']) ?> 🎯</span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($subscriptionData): ?>
            <div class="subscription-info">
                <h3 style="margin-bottom: 10px; color: #1976d2;">🔄 Recurring Service Active</h3>
                <p><strong>Frequency:</strong> <?= ucfirst($bookingData['recurrence_type'] ?? 'Weekly') ?></p>
                <p><strong>Next Billing:</strong> 48 hours before each service</p>
                <p><strong>Subscription ID:</strong> <?= substr($subscriptionData->id, 0, 20) ?>...</p>
                
                <?php if (!empty($bookingData['referral_code'])): ?>
                <p style="margin-top: 10px; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 5px;">
                    <strong>🎉 Great news!</strong> Your referrer will receive commission on each recurring payment!
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="next-steps">
                <h3>What happens next?</h3>
                <ul>
                    <li>You'll receive a confirmation email shortly</li>
                    <li>Our team will contact you to confirm service details</li>
                    <?php if ($subscriptionData): ?>
                    <li>Future payments will be charged automatically 48 hours before each service</li>
                    <li>You can manage your subscription through your customer portal</li>
                    <?php else: ?>
                    <li>Your one-time service will be scheduled as requested</li>
                    <?php endif; ?>
                    <li>A professional cleaner will arrive at your scheduled time</li>
                </ul>
            </div>
            
            <div style="margin: 30px 0;">
                <?php if ($subscriptionData): ?>
                <a href="/customer-portal.php?customer_id=<?= htmlspecialchars($bookingData['stripe_customer_id'] ?? '') ?>" class="btn btn-success">
                    Manage Subscription
                </a>
                <?php endif; ?>
                <a href="/booking3.php" class="btn">Book Another Service</a>
                <a href="/" class="btn">Back to Home</a>
            </div>
            
        <?php else: ?>
            <div class="error-icon">⚠️</div>
            <h1>Session Not Found</h1>
            <p class="subtitle">No valid payment session found.</p>
            <a href="/" class="btn">Back to Home</a>
        <?php endif; ?>
        
        <div class="contact-info">
            <h4><?= $_ENV['BUSINESS_NAME'] ?? 'Blue Cleaning Services Pty Ltd' ?></h4>
            <p>📞 <?= $_ENV['BUSINESS_PHONE'] ?? '+61 480 123 456' ?> | 
            📧 <?= $_ENV['BUSINESS_EMAIL'] ?? 'info@bluecleaning.com.au' ?></p>
            <p>ABN: <?= $_ENV['BUSINESS_ABN'] ?? '12345678901' ?></p>
            <p style="margin-top: 10px; font-size: 12px;">
                Payment processed securely by Stripe. Your card details are never stored on our servers.
            </p>
        </div>
    </div>
</body>
</html>
