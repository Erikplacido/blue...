<?php
/**
 * Dashboard Principal do Cliente - Blue Project V2
 * Painel de controle para gerenciamento de reservas e assinaturas
 */

session_start();

// Inclui configurações
require_once '../booking2.php';

// Verifica autenticação (implementar depois)
$customer_id = $_SESSION['customer_id'] ?? 123; // Simulado
$customer_email = $_SESSION['customer_email'] ?? 'customer@example.com';

// Carrega dados do dashboard
$dashboardData = loadCustomerDashboardData($customer_id);
$activeBookings = getActiveBookings($customer_id);
$upcomingServices = getUpcomingServices($customer_id);
$recentActivity = getRecentActivity($customer_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Blue Cleaning Services</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/blue.css">
    <link rel="stylesheet" href="../assets/css/pause-cancellation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
        }
        
        .welcome-section h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .welcome-section p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        
        .dashboard-actions {
            display: flex;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
        }
        
        .stat-card.active { border-left-color: #10b981; }
        .stat-card.upcoming { border-left-color: #3b82f6; }
        .stat-card.total { border-left-color: #8b5cf6; }
        .stat-card.savings { border-left-color: #f59e0b; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .content-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .booking-card {
            padding: 20px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .booking-card:last-child {
            border-bottom: none;
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .service-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 2px;
        }
        
        .detail-value {
            font-weight: 500;
            color: #1f2937;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
        }
        
        .quick-actions .btn {
            font-size: 0.9rem;
            padding: 8px 16px;
        }
        
        .upcoming-service {
            padding: 15px 20px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .service-date {
            font-weight: 600;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        
        .service-time {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .activity-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .activity-icon.booking { background: #dcfce7; color: #166534; }
        .activity-icon.pause { background: #fef3c7; color: #92400e; }
        .activity-icon.payment { background: #dbeafe; color: #1d4ed8; }
        
        .activity-content h4 {
            margin: 0 0 5px;
            font-size: 0.9rem;
        }
        
        .activity-content p {
            margin: 0;
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .booking-details {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Welcome back, <?= htmlspecialchars($dashboardData['customer_name']) ?>!</h1>
                <p>Manage your cleaning services and subscriptions</p>
            </div>
            <div class="dashboard-actions">
                <a href="../booking2.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Booking
                </a>
                <a href="profile.php" class="btn btn-secondary">
                    <i class="fas fa-user"></i> Profile
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card active">
                <div class="stat-number text-green-600"><?= $dashboardData['active_subscriptions'] ?></div>
                <div class="stat-label">Active Subscriptions</div>
            </div>
            <div class="stat-card upcoming">
                <div class="stat-number text-blue-600"><?= count($upcomingServices) ?></div>
                <div class="stat-label">Upcoming Services</div>
            </div>
            <div class="stat-card total">
                <div class="stat-number text-purple-600"><?= $dashboardData['total_services'] ?></div>
                <div class="stat-label">Services Completed</div>
            </div>
            <div class="stat-card savings">
                <div class="stat-number text-yellow-600">$<?= number_format($dashboardData['total_savings'], 2) ?></div>
                <div class="stat-label">Total Savings</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Active Bookings -->
            <div class="content-section">
                <div class="section-header">
                    <i class="fas fa-calendar-check"></i> Active Subscriptions
                </div>
                
                <?php if (empty($activeBookings)): ?>
                    <div style="padding: 40px; text-align: center; color: #6b7280;">
                        <i class="fas fa-calendar-plus" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>No active subscriptions found.</p>
                        <a href="../booking2.php" class="btn btn-primary">Create Your First Booking</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeBookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <div class="service-name"><?= htmlspecialchars($booking['service_name']) ?></div>
                                <span class="status-badge" data-booking-status="<?= $booking['status'] ?>">
                                    <?= ucfirst($booking['status']) ?>
                                </span>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-item">
                                    <span class="detail-label">Frequency</span>
                                    <span class="detail-value"><?= ucfirst($booking['recurrence_pattern']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Next Service</span>
                                    <span class="detail-value"><?= date('M j, Y', strtotime($booking['next_service_date'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Amount</span>
                                    <span class="detail-value">$<?= number_format($booking['amount'], 2) ?> AUD</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Services Left</span>
                                    <span class="detail-value"><?= $booking['remaining_services'] ?></span>
                                </div>
                            </div>
                            
                            <div class="quick-actions">
                                <button class="btn pause-btn" 
                                        data-action="pause-subscription" 
                                        data-booking-id="<?= $booking['booking_id'] ?>">
                                    <i class="fas fa-pause"></i> Pause
                                </button>
                                <a href="subscription-management.php?id=<?= $booking['booking_id'] ?>" 
                                   class="btn btn-secondary">
                                    <i class="fas fa-cog"></i> Manage
                                </a>
                                <button class="btn cancel-btn" 
                                        data-action="cancel-subscription" 
                                        data-booking-id="<?= $booking['booking_id'] ?>">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="sidebar-content">
                <!-- Upcoming Services -->
                <div class="content-section">
                    <div class="section-header">
                        <i class="fas fa-clock"></i> Upcoming Services
                    </div>
                    
                    <?php if (empty($upcomingServices)): ?>
                        <div style="padding: 20px; text-align: center; color: #6b7280;">
                            <p>No upcoming services scheduled.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingServices as $service): ?>
                            <div class="upcoming-service">
                                <div class="service-date"><?= date('M j, Y', strtotime($service['service_date'])) ?></div>
                                <div class="service-time"><?= $service['time_window'] ?> • <?= $service['service_type'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="content-section">
                    <div class="section-header">
                        <i class="fas fa-history"></i> Recent Activity
                    </div>
                    
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?= $activity['type'] ?>">
                                <i class="fas fa-<?= $activity['icon'] ?>"></i>
                            </div>
                            <div class="activity-content">
                                <h4><?= htmlspecialchars($activity['title']) ?></h4>
                                <p><?= htmlspecialchars($activity['description']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/pause-cancellation-manager.js"></script>
    <script>
        // Configuração para o sistema de pausas/cancelamentos
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

function loadCustomerDashboardData($customer_id) {
    // Simula carregamento de dados - substitua pela implementação real
    return [
        'customer_id' => $customer_id,
        'customer_name' => 'John Doe',
        'customer_email' => 'john@example.com',
        'active_subscriptions' => 2,
        'total_services' => 15,
        'total_savings' => 245.50,
        'loyalty_tier' => 'standard'
    ];
}

function getActiveBookings($customer_id) {
    // Simula dados de bookings ativos - substitua pela implementação real
    return [
        [
            'booking_id' => 'book_001',
            'service_name' => 'Weekly House Cleaning',
            'recurrence_pattern' => 'weekly',
            'next_service_date' => date('Y-m-d', strtotime('+3 days')),
            'amount' => 150.00,
            'remaining_services' => 8,
            'status' => 'active'
        ],
        [
            'booking_id' => 'book_002',
            'service_name' => 'Monthly Deep Clean',
            'recurrence_pattern' => 'monthly',
            'next_service_date' => date('Y-m-d', strtotime('+2 weeks')),
            'amount' => 250.00,
            'remaining_services' => 3,
            'status' => 'active'
        ]
    ];
}

function getUpcomingServices($customer_id) {
    // Simula próximos serviços - substitua pela implementação real
    return [
        [
            'service_date' => date('Y-m-d', strtotime('+3 days')),
            'time_window' => '9:00 AM - 10:00 AM',
            'service_type' => 'House Cleaning'
        ],
        [
            'service_date' => date('Y-m-d', strtotime('+10 days')),
            'time_window' => '2:00 PM - 3:00 PM',
            'service_type' => 'House Cleaning'
        ]
    ];
}

function getRecentActivity($customer_id) {
    // Simula atividade recente - substitua pela implementação real
    return [
        [
            'type' => 'booking',
            'icon' => 'calendar-plus',
            'title' => 'New booking created',
            'description' => 'Weekly cleaning service started'
        ],
        [
            'type' => 'payment',
            'icon' => 'credit-card',
            'title' => 'Payment processed',
            'description' => '$150.00 charged for next service'
        ],
        [
            'type' => 'pause',
            'icon' => 'pause',
            'title' => 'Service paused',
            'description' => 'Monthly service paused for 2 weeks'
        ]
    ];
}
?>
