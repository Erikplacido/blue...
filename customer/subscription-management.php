<?php
/**
 * Gerenciamento de Assinaturas - Blue Project V2
 * Página para gerenciar detalhes de uma assinatura específica
 */

session_start();

// Inclui configurações
require_once '../booking3.php';

// Verifica autenticação
$customer_id = $_SESSION['customer_id'] ?? 123; // Simulado

// Obtém ID da assinatura
$booking_id = $_GET['id'] ?? null;
if (!$booking_id) {
    header('Location: dashboard.php');
    exit();
}

// Carrega dados da assinatura
$subscription = loadSubscriptionDetails($booking_id, $customer_id);
if (!$subscription) {
    header('Location: dashboard.php?error=subscription_not_found');
    exit();
}

$pauseHistory = getPauseHistory($subscription['customer_email']);
$paymentHistory = getPaymentHistory($booking_id);
$serviceHistory = getServiceHistory($booking_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscription - <?= htmlspecialchars($subscription['service_name']) ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/blue.css">
    <link rel="stylesheet" href="../assets/css/pause-cancellation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .subscription-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .subscription-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .subscription-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .subscription-title h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .subscription-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .meta-value {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .main-content, .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-content {
            padding: 20px;
        }
        
        .subscription-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .subscription-actions .btn {
            flex: 1;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .detail-row:last-child {
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
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-left: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -27px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3b82f6;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #3b82f6;
        }
        
        .timeline-item.completed::before {
            background: #10b981;
            box-shadow: 0 0 0 2px #10b981;
        }
        
        .timeline-item.paused::before {
            background: #f59e0b;
            box-shadow: 0 0 0 2px #f59e0b;
        }
        
        .timeline-item.cancelled::before {
            background: #ef4444;
            box-shadow: 0 0 0 2px #ef4444;
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 5px;
        }
        
        .timeline-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .timeline-description {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .next-billing-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .next-billing-card .card-header {
            border-bottom-color: rgba(255, 255, 255, 0.2);
        }
        
        .billing-amount {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .billing-date {
            opacity: 0.9;
        }
        
        .pause-tier-card {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
        }
        
        .pause-tier-card .card-header {
            border-bottom-color: rgba(255, 255, 255, 0.2);
        }
        
        .tier-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .pause-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .pause-stat {
            text-align: center;
        }
        
        .pause-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .pause-stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .subscription-title {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .subscription-actions {
                flex-direction: column;
            }
            
            .subscription-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="subscription-container">
        <!-- Header -->
        <div class="subscription-header">
            <div class="subscription-title">
                <h1><?= htmlspecialchars($subscription['service_name']) ?></h1>
                <span class="status-badge" data-booking-status="<?= $subscription['status'] ?>">
                    <?= ucfirst($subscription['status']) ?>
                </span>
            </div>
            
            <div class="subscription-meta">
                <div class="meta-item">
                    <span class="meta-label">Frequency</span>
                    <span class="meta-value"><?= ucfirst($subscription['recurrence_pattern']) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Next Service</span>
                    <span class="meta-value"><?= date('M j, Y', strtotime($subscription['next_service_date'])) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Amount per Service</span>
                    <span class="meta-value">$<?= number_format($subscription['amount'], 2) ?> AUD</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Services Remaining</span>
                    <span class="meta-value"><?= $subscription['remaining_services'] ?></span>
                </div>
            </div>
        </div>

        <!-- Breadcrumb -->
        <nav style="margin-bottom: 20px;">
            <a href="dashboard.php" style="color: #6b7280; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </nav>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-cog"></i> Manage Subscription
                    </div>
                    <div class="card-content">
                        <div class="subscription-actions">
                            <?php if ($subscription['status'] === 'active'): ?>
                                <button class="btn pause-btn" 
                                        data-action="pause-subscription" 
                                        data-booking-id="<?= $subscription['booking_id'] ?>">
                                    <i class="fas fa-pause"></i> Pause Subscription
                                </button>
                                <button class="btn cancel-btn" 
                                        data-action="cancel-subscription" 
                                        data-booking-id="<?= $subscription['booking_id'] ?>">
                                    <i class="fas fa-times"></i> Cancel Subscription
                                </button>
                            <?php elseif ($subscription['status'] === 'paused'): ?>
                                <button class="btn btn-primary" 
                                        data-action="resume-subscription" 
                                        data-booking-id="<?= $subscription['booking_id'] ?>">
                                    <i class="fas fa-play"></i> Resume Subscription
                                </button>
                                <button class="btn cancel-btn" 
                                        data-action="cancel-subscription" 
                                        data-booking-id="<?= $subscription['booking_id'] ?>">
                                    <i class="fas fa-times"></i> Cancel Subscription
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Booking ID</span>
                            <span class="detail-value"><?= htmlspecialchars($subscription['booking_id']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Service Address</span>
                            <span class="detail-value"><?= htmlspecialchars($subscription['service_address']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Time Window</span>
                            <span class="detail-value"><?= htmlspecialchars($subscription['time_window']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Started</span>
                            <span class="detail-value"><?= date('M j, Y', strtotime($subscription['created_at'])) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Services Booked</span>
                            <span class="detail-value"><?= $subscription['total_services'] ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Services Completed</span>
                            <span class="detail-value"><?= $subscription['completed_services'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Service History -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i> Service History
                    </div>
                    <div class="card-content">
                        <div class="timeline">
                            <?php foreach ($serviceHistory as $service): ?>
                                <div class="timeline-item <?= $service['status'] ?>">
                                    <div class="timeline-date"><?= date('M j, Y', strtotime($service['service_date'])) ?></div>
                                    <div class="timeline-title"><?= htmlspecialchars($service['title']) ?></div>
                                    <div class="timeline-description"><?= htmlspecialchars($service['description']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Next Billing -->
                <div class="card next-billing-card">
                    <div class="card-header">
                        <i class="fas fa-credit-card"></i> Next Billing
                    </div>
                    <div class="card-content">
                        <div class="billing-amount">$<?= number_format($subscription['amount'], 2) ?></div>
                        <div class="billing-date">
                            Charges on <?= date('M j, Y', strtotime($subscription['next_billing_date'])) ?>
                        </div>
                        <p style="margin-top: 15px; opacity: 0.9; font-size: 0.9rem;">
                            Payment is automatically processed 48 hours before your service date.
                        </p>
                    </div>
                </div>

                <!-- Pause Tier Information -->
                <div class="card pause-tier-card">
                    <div class="card-header">
                        <i class="fas fa-star"></i> Your Pause Tier
                    </div>
                    <div class="card-content">
                        <div class="tier-name"><?= htmlspecialchars($subscription['pause_tier']['tier_name']) ?></div>
                        <div class="pause-stats">
                            <div class="pause-stat">
                                <div class="pause-stat-number"><?= $subscription['pause_tier']['free_pauses'] ?></div>
                                <div class="pause-stat-label">Total Free</div>
                            </div>
                            <div class="pause-stat">
                                <div class="pause-stat-number"><?= $subscription['used_pauses'] ?></div>
                                <div class="pause-stat-label">Used</div>
                            </div>
                            <div class="pause-stat">
                                <div class="pause-stat-number"><?= max(0, $subscription['pause_tier']['free_pauses'] - $subscription['used_pauses']) ?></div>
                                <div class="pause-stat-label">Remaining</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-receipt"></i> Recent Payments
                    </div>
                    <div class="card-content">
                        <?php if (empty($paymentHistory)): ?>
                            <p style="text-align: center; color: #6b7280;">No payments yet.</p>
                        <?php else: ?>
                            <?php foreach ($paymentHistory as $payment): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><?= date('M j', strtotime($payment['payment_date'])) ?></span>
                                    <span class="detail-value">$<?= number_format($payment['amount'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/pause-cancellation-manager.js"></script>
    <script>
        window.pauseCancellationConfig = {
            apiBaseUrl: '../api',
            debug: true
        };
    </script>
</body>
</html>

<?php
/**
 * Funções auxiliares
 */

function loadSubscriptionDetails($booking_id, $customer_id) {
    // Simula carregamento de detalhes - substitua pela implementação real
    return [
        'booking_id' => $booking_id,
        'customer_id' => $customer_id,
        'customer_email' => 'customer@example.com',
        'service_name' => 'Weekly House Cleaning',
        'recurrence_pattern' => 'weekly',
        'next_service_date' => date('Y-m-d', strtotime('+3 days')),
        'next_billing_date' => date('Y-m-d', strtotime('+1 day')),
        'amount' => 150.00,
        'remaining_services' => 8,
        'total_services' => 12,
        'completed_services' => 4,
        'status' => 'active',
        'service_address' => '123 Main St, Sydney NSW 2000',
        'time_window' => '9:00 AM - 10:00 AM',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 months')),
        'pause_tier' => [
            'tier_name' => 'Standard',
            'free_pauses' => 4
        ],
        'used_pauses' => 1
    ];
}

function getPaymentHistory($booking_id) {
    // Simula histórico de pagamentos - substitua pela implementação real
    return [
        [
            'payment_date' => date('Y-m-d', strtotime('-1 week')),
            'amount' => 150.00,
            'status' => 'completed'
        ],
        [
            'payment_date' => date('Y-m-d', strtotime('-2 weeks')),
            'amount' => 150.00,
            'status' => 'completed'
        ],
        [
            'payment_date' => date('Y-m-d', strtotime('-3 weeks')),
            'amount' => 150.00,
            'status' => 'completed'
        ]
    ];
}

function getServiceHistory($booking_id) {
    // Simula histórico de serviços - substitua pela implementação real
    return [
        [
            'service_date' => date('Y-m-d', strtotime('+3 days')),
            'title' => 'Upcoming Service',
            'description' => 'Scheduled for 9:00 AM - 10:00 AM',
            'status' => 'upcoming'
        ],
        [
            'service_date' => date('Y-m-d', strtotime('-4 days')),
            'title' => 'Service Completed',
            'description' => 'Cleaned by Maria S. - Rated 5/5 stars',
            'status' => 'completed'
        ],
        [
            'service_date' => date('Y-m-d', strtotime('-11 days')),
            'title' => 'Service Completed',
            'description' => 'Cleaned by John D. - Rated 5/5 stars',
            'status' => 'completed'
        ],
        [
            'service_date' => date('Y-m-d', strtotime('-18 days')),
            'title' => 'Service Paused',
            'description' => 'Customer requested 1-week pause',
            'status' => 'paused'
        ]
    ];
}
?>
