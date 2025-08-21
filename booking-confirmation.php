<?php
/**
 * Confirmação de Booking - Blue Project V2
 * Página exibida após confirmação bem-sucedida de reserva
 */

session_start();

// Inclui configurações
require_once 'booking2.php';

// Obtém ID do booking da URL ou sessão
$booking_id = $_GET['booking_id'] ?? $_SESSION['last_booking_id'] ?? null;

if (!$booking_id) {
    header('Location: booking2.php?error=no_booking_found');
    exit();
}

// Carrega dados da reserva
$booking = loadBookingConfirmationData($booking_id);
if (!$booking) {
    header('Location: booking2.php?error=booking_not_found');
    exit();
}

// Limpa ID da sessão após carregamento
unset($_SESSION['last_booking_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - Blue Cleaning Services</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/blue.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            text-align: center;
        }
        
        .success-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .success-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: checkmark 0.6s ease-in-out;
        }
        
        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .success-title {
            font-size: 2.5rem;
            margin: 0 0 10px;
            font-weight: 700;
        }
        
        .success-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .booking-details {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .details-header {
            background: #f8fafc;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 1.3rem;
            color: #1f2937;
        }
        
        .details-content {
            padding: 30px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            text-align: left;
        }
        
        .detail-section h3 {
            color: #667eea;
            font-size: 1.1rem;
            margin: 0 0 15px;
            font-weight: 600;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .detail-value {
            color: #1f2937;
            font-weight: 600;
        }
        
        .next-steps {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .steps-content {
            padding: 30px;
            text-align: left;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .step-item:last-child {
            border-bottom: none;
        }
        
        .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .step-content h4 {
            margin: 0 0 5px;
            color: #1f2937;
            font-weight: 600;
        }
        
        .step-content p {
            margin: 0;
            color: #6b7280;
        }
        
        .important-notes {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #f59e0b;
        }
        
        .important-notes h3 {
            color: #92400e;
            margin: 0 0 15px;
            font-weight: 600;
        }
        
        .important-notes ul {
            margin: 0;
            padding-left: 20px;
            color: #92400e;
        }
        
        .important-notes li {
            margin-bottom: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            min-width: 200px;
        }
        
        .referral-bonus {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .referral-bonus h3 {
            margin: 0 0 10px;
            font-size: 1.3rem;
        }
        
        .referral-bonus p {
            margin: 0;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .confirmation-container {
                padding: 15px;
            }
            
            .success-header {
                padding: 30px 20px;
            }
            
            .success-title {
                font-size: 2rem;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-buttons .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <!-- Success Header -->
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="success-title">Booking Confirmed!</h1>
            <p class="success-subtitle">
                Your <?= strtolower($booking['service_name']) ?> has been successfully scheduled
            </p>
        </div>

        <!-- Booking Details -->
        <div class="booking-details">
            <div class="details-header">
                <i class="fas fa-clipboard-list"></i> Booking Details
            </div>
            <div class="details-content">
                <div class="details-grid">
                    <!-- Service Information -->
                    <div class="detail-section">
                        <h3><i class="fas fa-broom"></i> Service Information</h3>
                        <div class="detail-item">
                            <span class="detail-label">Service Type</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['service_name']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Frequency</span>
                            <span class="detail-value"><?= ucfirst($booking['recurrence_pattern']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Duration</span>
                            <span class="detail-value"><?= $booking['estimated_duration'] ?> hours</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Amount per Service</span>
                            <span class="detail-value">$<?= number_format($booking['amount'], 2) ?> AUD</span>
                        </div>
                    </div>

                    <!-- Schedule Information -->
                    <div class="detail-section">
                        <h3><i class="fas fa-calendar"></i> Schedule</h3>
                        <div class="detail-item">
                            <span class="detail-label">First Service</span>
                            <span class="detail-value"><?= date('l, M j, Y', strtotime($booking['first_service_date'])) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Time Window</span>
                            <span class="detail-value"><?= $booking['time_window'] ?></span>
                        </div>
                        <?php if ($booking['recurrence_pattern'] !== 'one-time'): ?>
                        <div class="detail-item">
                            <span class="detail-label">Total Services</span>
                            <span class="detail-value"><?= $booking['total_services'] ?> services</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contract Duration</span>
                            <span class="detail-value"><?= $booking['contract_duration'] ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Location & Contact -->
                    <div class="detail-section">
                        <h3><i class="fas fa-map-marker-alt"></i> Service Location</h3>
                        <div class="detail-item">
                            <span class="detail-label">Address</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['service_address']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Suburb</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['suburb']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Postcode</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['postcode']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['customer_phone']) ?></span>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="detail-section">
                        <h3><i class="fas fa-credit-card"></i> Payment</h3>
                        <div class="detail-item">
                            <span class="detail-label">Booking ID</span>
                            <span class="detail-value"><?= htmlspecialchars($booking['booking_id']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Payment Method</span>
                            <span class="detail-value">Card ending in <?= $booking['card_last4'] ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Next Charge</span>
                            <span class="detail-value"><?= date('M j, Y', strtotime($booking['next_billing_date'])) ?></span>
                        </div>
                        <?php if ($booking['total_savings'] > 0): ?>
                        <div class="detail-item">
                            <span class="detail-label">Total Savings</span>
                            <span class="detail-value" style="color: #10b981;">$<?= number_format($booking['total_savings'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Referral Bonus (se aplicável) -->
        <?php if (!empty($booking['referral_code'])): ?>
        <div class="referral-bonus">
            <h3><i class="fas fa-gift"></i> Referral Bonus Applied!</h3>
            <p>Thanks for using referral code <strong><?= htmlspecialchars($booking['referral_code']) ?></strong>. You've earned additional savings!</p>
        </div>
        <?php endif; ?>

        <!-- Important Notes -->
        <div class="important-notes">
            <h3><i class="fas fa-exclamation-triangle"></i> Important Information</h3>
            <ul>
                <li><strong>Payment:</strong> Your card will be charged 48 hours before each service</li>
                <li><strong>Cancellation:</strong> Free cancellation up to 48 hours before service</li>
                <li><strong>Access:</strong> Please ensure someone is available during the time window</li>
                <?php if ($booking['recurrence_pattern'] !== 'one-time'): ?>
                <li><strong>Pausing:</strong> You can pause your subscription anytime with 48h notice</li>
                <?php endif; ?>
                <li><strong>Contact:</strong> Our cleaner will call 15 minutes before arrival</li>
            </ul>
        </div>

        <!-- Next Steps -->
        <div class="next-steps">
            <div class="details-header">
                <i class="fas fa-list-ol"></i> What Happens Next?
            </div>
            <div class="steps-content">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Confirmation Email</h4>
                        <p>Check your email for detailed booking confirmation and receipt</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Cleaner Assignment</h4>
                        <p>We'll assign a professional cleaner and notify you 24 hours before service</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Service Day</h4>
                        <p>Your cleaner will arrive within the time window and call 15 minutes before</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>Feedback</h4>
                        <p>After service completion, you'll receive a request to rate your experience</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="customer/dashboard.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> View Dashboard
            </a>
            <a href="booking2.php" class="btn btn-secondary">
                <i class="fas fa-plus"></i> Book Another Service
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Details
            </button>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Auto-scroll suave para o topo
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.booking-details, .next-steps, .important-notes');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, (index + 1) * 200);
            });
        });

        // Marca como booking visualizado
        if (localStorage) {
            localStorage.setItem('lastBookingViewed', '<?= $booking_id ?>');
        }
    </script>
</body>
</html>

<?php
/**
 * Função auxiliar para carregar dados de confirmação
 */
function loadBookingConfirmationData($booking_id) {
    // Simula carregamento de dados - substitua pela implementação real
    return [
        'booking_id' => $booking_id,
        'service_name' => 'Weekly House Cleaning',
        'recurrence_pattern' => 'weekly',
        'amount' => 150.00,
        'first_service_date' => date('Y-m-d', strtotime('+3 days')),
        'time_window' => '9:00 AM - 10:00 AM',
        'estimated_duration' => 2.5,
        'total_services' => 12,
        'contract_duration' => '3 months',
        'service_address' => '123 Main Street',
        'suburb' => 'Sydney CBD',
        'postcode' => '2000',
        'customer_phone' => '+61 400 000 000',
        'card_last4' => '4242',
        'next_billing_date' => date('Y-m-d', strtotime('+1 day')),
        'total_savings' => 45.00,
        'referral_code' => $_GET['ref'] ?? null
    ];
}
?>
