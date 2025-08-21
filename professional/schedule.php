<?php
/**
 * Professional Schedule Management
 * Dedicated interface for professionals to manage their availability and schedule
 * Integrates with existing Smart Calendar system and availability management APIs
 */

session_start();

// Load configuration and database connection
require_once '../config.php';

// For now, we'll simulate authentication - in production this should use proper auth system
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// For now, we'll get professional_id from URL parameter like dashboard
$professional_id = $_GET['professional_id'] ?? $_SESSION['user_id'] ?? null;

// Redirect to dashboard if no professional_id
if (!$professional_id) {
    header('Location: dynamic-dashboard.php');
    exit();
}

// Simulate professional session for development/testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $professional_id;
    $_SESSION['user_type'] = 'professional';
    $_SESSION['user_email'] = 'professional@bluecleaning.com.au';
    $_SESSION['user_name'] = 'Professional Demo';
}

// Check if user is logged in and is a professional
if (!isLoggedIn() || $_SESSION['user_type'] !== 'professional') {
    header('Location: ../auth/login.php');
    exit();
}

$professional_id = $_SESSION['user_id'];

// Get professional data
try {
    $stmt = $pdo->prepare("SELECT * FROM professionals WHERE id = ? LIMIT 1");
    $stmt->execute([$professional_id]);
    $professional = $stmt->fetch();

    if (!$professional) {
        // Create a demo professional for development
        $professional = [
            'id' => $professional_id,
            'name' => $_SESSION['user_name'] ?? 'Demo Professional',
            'email' => $_SESSION['user_email'] ?? 'demo@bluecleaning.com.au',
            'phone' => '+61 400 123 456',
            'status' => 'active'
        ];
    }
} catch (Exception $e) {
    // Create demo data if database error
    $professional = [
        'id' => $professional_id,
        'name' => $_SESSION['user_name'] ?? 'Demo Professional',
        'email' => $_SESSION['user_email'] ?? 'demo@bluecleaning.com.au',
        'phone' => '+61 400 123 456',
        'status' => 'active'
    ];
}

// Get professional services for the schedule interface
try {
    $stmt = $pdo->prepare("
        SELECT s.*, ps.hourly_rate, ps.created_at as service_added
        FROM services s 
        JOIN professional_services ps ON s.id = ps.service_id 
        WHERE ps.professional_id = ? AND ps.status = 'active'
        ORDER BY s.name
    ");
    $stmt->execute([$professional_id]);
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    // Create demo services if database error
    $services = [
        [
            'id' => 1,
            'name' => 'House Cleaning',
            'hourly_rate' => 45.00,
            'service_added' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'name' => 'Deep Cleaning',
            'hourly_rate' => 60.00,
            'service_added' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 3,
            'name' => 'Office Cleaning',
            'hourly_rate' => 50.00,
            'service_added' => date('Y-m-d H:i:s')
        ]
    ];
}

// Get current availability statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_slots,
            SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_slots,
            COUNT(DISTINCT DATE(date)) as active_days
        FROM professional_availability 
        WHERE professional_id = ? 
        AND date >= CURDATE() 
        AND date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$professional_id]);
    $availability_stats = $stmt->fetch();
} catch (Exception $e) {
    // Demo stats if database error
    $availability_stats = [
        'total_slots' => 42,
        'available_slots' => 28,
        'active_days' => 14
    ];
}

// Get recent bookings for context
try {
    $stmt = $pdo->prepare("
        SELECT b.*, s.name as service_name, c.name as customer_name 
        FROM bookings b 
        JOIN services s ON b.service_id = s.id 
        JOIN customers c ON b.customer_id = c.id 
        WHERE b.professional_id = ? 
        AND b.status IN ('confirmed', 'completed') 
        ORDER BY b.execution_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$professional_id]);
    $recent_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    // Demo bookings if database error
    $recent_bookings = [
        [
            'service_name' => 'House Cleaning',
            'customer_name' => 'Sarah Johnson',
            'execution_date' => date('Y-m-d', strtotime('-2 days'))
        ],
        [
            'service_name' => 'Deep Cleaning',
            'customer_name' => 'Michael Brown',
            'execution_date' => date('Y-m-d', strtotime('-5 days'))
        ],
        [
            'service_name' => 'Office Cleaning',
            'customer_name' => 'ABC Company',
            'execution_date' => date('Y-m-d', strtotime('-1 week'))
        ]
    ];
}

// Configuration for availability management
$availability_config = [
    'time_slots' => 60, // 60-minute slots
    'start_hour' => 8,  // 8 AM
    'end_hour' => 18,   // 6 PM
    'advance_booking_hours' => 48, // 48-hour minimum
    'max_booking_days' => 60 // Maximum 60 days in advance
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Profissional - Blue Services</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/blue.css">
    <link rel="stylesheet" href="../assets/css/smart-calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --background-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background-gradient);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .schedule-container {
            display: grid;
            grid-template-areas: 
                "header header"
                "sidebar main-content";
            grid-template-columns: 350px 1fr;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
            gap: 0;
        }
        
        /* Header */
        .schedule-header {
            grid-area: header;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }
        
        .header-title h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 15px;
            border-radius: 25px;
            color: white;
            font-size: 0.9rem;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background: var(--success-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .header-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .header-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        /* Sidebar */
        .schedule-sidebar {
            grid-area: sidebar;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px;
            overflow-y: auto;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar-section {
            margin-bottom: 35px;
        }
        
        .sidebar-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Quick Stats */
        .quick-stats {
            display: grid;
            gap: 15px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 2px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        /* Service Toggle */
        .service-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .service-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .service-item:hover {
            background: #e5e7eb;
        }
        
        .service-info h4 {
            margin: 0 0 5px 0;
            font-size: 0.95rem;
            color: #1f2937;
        }
        
        .service-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .service-toggle {
            width: 45px;
            height: 24px;
            background: #d1d5db;
            border-radius: 12px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .service-toggle.active {
            background: var(--success-color);
        }
        
        .service-toggle::after {
            content: '';
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 3px;
            left: 3px;
            transition: all 0.3s ease;
        }
        
        .service-toggle.active::after {
            left: 24px;
        }
        
        /* Recent Activity */
        .activity-list {
            max-height: 250px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 35px;
            height: 35px;
            background: var(--info-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 2px;
        }
        
        .activity-subtitle {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        /* Main Content */
        .schedule-main {
            grid-area: main-content;
            padding: 30px;
            overflow-y: auto;
        }
        
        .main-content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .schedule-controls {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .schedule-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
            flex: 1;
        }
        
        .schedule-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .view-toggle {
            display: flex;
            background: #f3f4f6;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .view-btn {
            background: none;
            border: none;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .view-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        .action-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .action-btn.secondary {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .action-btn.secondary:hover {
            background: #e5e7eb;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Smart Calendar Container Override */
        .schedule-calendar-container {
            background: transparent;
            box-shadow: none;
            border: none;
            padding: 0;
        }
        
        .schedule-calendar-container .smart-calendar-container {
            background: transparent;
            box-shadow: none;
            border: none;
        }
        
        /* Override any conflicting pointer styles */
        .schedule-calendar-container *,
        .fallback-calendar *,
        #smart-calendar * {
            pointer-events: auto !important;
        }
        
        .schedule-calendar-container .calendar-day:not(.past-day),
        .fallback-calendar .calendar-day:not(.past-day) {
            cursor: pointer !important;
        }
        
        /* Ensure no conflicting styles */
        .main-content-card * {
            user-select: auto;
        }
        
        /* Time Slot Management */
        .time-management {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        .selected-date-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
        }
        
        .selected-date-info h3 {
            margin: 0 0 10px 0;
            font-size: 1.3rem;
        }
        
        .selected-date-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .time-slots-management {
            background: #f8fafc;
            padding: 25px;
            border-radius: 15px;
        }
        
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        
        .time-slot {
            background: white;
            border: 2px solid #e5e7eb;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .time-slot:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .time-slot.available {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .time-slot.booked {
            background: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
            cursor: not-allowed;
        }
        
        .time-slot.selected {
            background: var(--info-color) !important;
            color: white;
            border-color: var(--info-color);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .time-slot.selected i {
            color: white;
        }
        
        /* Bulk action buttons */
        .bulk-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
        }
        
        /* Message styles */
        .message-info {
            background: #dbeafe;
            color: #1e40af;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
            margin: 20px 0;
        }
        
        /* Calendar specific styles */
        .calendar-day {
            position: relative;
            z-index: 1;
        }
        
        .calendar-day:not(.past-day):hover {
            cursor: pointer !important;
            z-index: 2;
        }
        
        .calendar-day.past-day {
            pointer-events: none;
            cursor: not-allowed !important;
        }
        
        .calendar-day.selected {
            position: relative;
            z-index: 3;
        }
        
        .fallback-calendar * {
            pointer-events: auto !important;
            user-select: auto !important;
        }
        
        .fallback-calendar .calendar-day:not(.past-day) {
            cursor: pointer !important;
        }
        
        /* Header button styles */
        .header-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .header-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        /* Bulk Actions */
        .bulk-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .bulk-btn {
            background: #f3f4f6;
            color: #6b7280;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .bulk-btn:hover {
            background: #e5e7eb;
        }
        
        .bulk-btn.primary {
            background: var(--primary-color);
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .schedule-container {
                grid-template-areas: 
                    "header"
                    "main-content"
                    "sidebar";
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr auto;
            }
            
            .schedule-sidebar {
                border-right: none;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .time-management {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .schedule-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .schedule-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .schedule-actions {
                justify-content: center;
            }
            
            .time-slots-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            }
        }
        
        /* Loading and Error States */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            z-index: 10;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f4f6;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #dc2626;
            margin: 20px 0;
        }
        
        .success-message {
            background: #ecfdf5;
            color: #059669;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #059669;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="schedule-container">
        <!-- Header -->
        <header class="schedule-header">
            <div class="header-title">
                <i class="fas fa-calendar-alt"></i>
                <h1>Agenda Profissional</h1>
            </div>
            
            <div class="header-actions">
                <div class="status-indicator">
                    <div class="status-dot"></div>
                    <span>Online & Disponível</span>
                </div>
                
                <button class="header-btn" onclick="toggleAvailability()">
                    <i class="fas fa-power-off"></i>
                    Status
                </button>
                
                <button class="header-btn" onclick="window.location.href='dynamic-dashboard.php?professional_id=<?= $professional_id ?>'">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </button>
            </div>
        </header>

        <!-- Sidebar -->
        <aside class="schedule-sidebar">
            <!-- Quick Stats -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="fas fa-chart-line"></i>
                    Estatísticas Rápidas
                </h3>
                
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?= $availability_stats['available_slots'] ?? 0 ?></div>
                        <div class="stat-label">Horários Disponíveis</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= $availability_stats['active_days'] ?? 0 ?></div>
                        <div class="stat-label">Dias Ativos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= count($services) ?></div>
                        <div class="stat-label">Serviços Ativos</div>
                    </div>
                </div>
            </div>

            <!-- Service Management -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="fas fa-tools"></i>
                    Serviços Disponíveis
                </h3>
                
                <div class="service-list">
                    <?php foreach ($services as $service): ?>
                    <div class="service-item">
                        <div class="service-info">
                            <h4><?= htmlspecialchars($service['name']) ?></h4>
                            <p>$<?= number_format($service['hourly_rate'], 2) ?>/hora</p>
                        </div>
                        <div class="service-toggle active" data-service-id="<?= $service['id'] ?>" onclick="toggleService(this)"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="fas fa-history"></i>
                    Atividade Recente
                </h3>
                
                <div class="activity-list">
                    <?php foreach ($recent_bookings as $booking): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?= htmlspecialchars($booking['service_name']) ?></div>
                            <div class="activity-subtitle">
                                <?= htmlspecialchars($booking['customer_name']) ?> • 
                                <?= date('d/m/Y', strtotime($booking['execution_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recent_bookings)): ?>
                    <p style="text-align: center; color: #6b7280; font-style: italic;">
                        Nenhuma atividade recente
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="schedule-main">
            <div class="main-content-card">
                <div class="schedule-controls">
                    <h2 class="schedule-title">Gerenciar Disponibilidade</h2>
                    
                    <div class="schedule-actions">
                        <div class="view-toggle">
                            <button class="view-btn active" onclick="setView('month')">Mensal</button>
                            <button class="view-btn" onclick="setView('week')">Semanal</button>
                            <button class="view-btn" onclick="setView('day')">Diário</button>
                        </div>
                        
                        <button class="action-btn secondary" onclick="openBulkActions()">
                            <i class="fas fa-layer-group"></i>
                            Ações em Massa
                        </button>
                        
                        <button class="action-btn" onclick="openQuickAvailability()">
                            <i class="fas fa-plus"></i>
                            Disponibilidade Rápida
                        </button>
                    </div>
                </div>
                
                <!-- Smart Calendar Integration -->
                <div class="schedule-calendar-container">
                    <div id="smart-calendar" class="smart-calendar-wrapper"></div>
                </div>
                
                <!-- Time Slot Management -->
                <div class="time-management">
                    <div class="time-slots-management">
                        <h3>Gerenciar Horários</h3>
                        <p id="selected-date-display">Selecione uma data no calendário para gerenciar os horários</p>
                        
                        <div id="time-slots-container">
                            <div class="time-slots-grid" id="timeSlots">
                                <!-- Time slots will be populated here -->
                            </div>
                        </div>
                        
                        <div class="bulk-actions">
                            <button class="bulk-btn primary" onclick="selectAllSlots()">
                                <i class="fas fa-check-square"></i> Selecionar Todos
                            </button>
                            <button class="bulk-btn" onclick="makeAvailable()">
                                <i class="fas fa-check"></i> Disponibilizar
                            </button>
                            <button class="bulk-btn" onclick="makeUnavailable()">
                                <i class="fas fa-times"></i> Indisponibilizar
                            </button>
                            <button class="bulk-btn" onclick="blockSlots()">
                                <i class="fas fa-ban"></i> Bloquear
                            </button>
                        </div>
                    </div>
                    
                    <div class="selected-date-info">
                        <h3 id="date-info-title">Nenhuma Data Selecionada</h3>
                        <p id="date-info-subtitle">Clique em uma data do calendário para ver e gerenciar os horários</p>
                        
                        <div id="date-stats" style="margin-top: 20px; display: none;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>Disponíveis:</span>
                                <span id="available-count">0</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>Reservados:</span>
                                <span id="booked-count">0</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Bloqueados:</span>
                                <span id="blocked-count">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/smart-calendar.js"></script>
    <script>
        class ProfessionalScheduleManager {
            constructor() {
                this.selectedDate = null;
                this.selectedSlots = new Set();
                this.professionalId = <?= $professional_id ?>;
                this.availabilityData = {};
                this.currentView = 'month';
                this.smartCalendar = null;
                
                // Initialize bulk actions as disabled
                this.enableBulkActions(false);
                
                this.init();
            }

            async init() {
                console.log('Initializing Professional Schedule Manager');
                
                // Initialize Smart Calendar for professional schedule management
                try {
                    // Check if SmartBookingCalendar is available
                    if (typeof SmartBookingCalendar !== 'undefined') {
                        this.smartCalendar = new SmartBookingCalendar({
                            containerId: 'smart-calendar',
                            serviceId: null, // Professional view doesn't need specific service
                            modal: false,
                            onDateSelected: (data) => {
                                this.handleDateSelection(data);
                            }
                        });
                        
                        // Override Smart Calendar to show all dates for professional
                        this.initializeProfessionalCalendar();
                    } else {
                        console.warn('SmartBookingCalendar not available, using fallback calendar');
                        this.initializeFallbackCalendar();
                    }
                } catch (error) {
                    console.error('Error initializing Smart Calendar:', error);
                    this.initializeFallbackCalendar();
                }
                
                this.bindEvents();
                await this.loadInitialData();
            }
            
            initializeFallbackCalendar() {
                // Simple fallback calendar implementation
                const calendarContainer = document.getElementById('smart-calendar');
                if (calendarContainer) {
                    const today = new Date();
                    const monthNames = [
                        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
                    ];
                    
                    calendarContainer.innerHTML = `
                        <div class="fallback-calendar" style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                            <div style="text-align: center; margin-bottom: 25px;">
                                <h3 style="color: #1f2937; font-size: 1.5rem; margin-bottom: 5px;">${monthNames[today.getMonth()]} ${today.getFullYear()}</h3>
                                <p style="color: #6b7280; font-size: 0.9rem;">Clique em uma data para gerenciar sua disponibilidade</p>
                            </div>
                            <div class="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-bottom: 20px;">
                                ${this.generateSimpleCalendar()}
                            </div>
                            <div style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 8px; margin-top: 15px;">
                                <div style="display: flex; justify-content: center; gap: 20px; font-size: 0.85rem;">
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <div style="width: 16px; height: 16px; background: #e8f5e8; border-radius: 4px;"></div>
                                        <span>Dias úteis</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <div style="width: 16px; height: 16px; background: #fef3c7; border-radius: 4px;"></div>
                                        <span>Fins de semana</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <div style="width: 16px; height: 16px; background: #f3f4f6; border-radius: 4px;"></div>
                                        <span>Indisponível</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                // Set up fallback availability data
                this.setupFallbackData();
                
                console.log('Fallback calendar initialized with clickable dates');
            }
            
            generateSimpleCalendar() {
                const today = new Date();
                const currentMonth = today.getMonth();
                const currentYear = today.getFullYear();
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                const firstDayOfMonth = new Date(currentYear, currentMonth, 1).getDay();
                
                let calendarHTML = '';
                const dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                
                // Add day headers
                dayNames.forEach(day => {
                    calendarHTML += `<div style="font-weight: bold; padding: 10px; background: #f0f0f0; text-align: center; border-radius: 5px;">${day}</div>`;
                });
                
                // Add empty cells for days before month starts
                for (let i = 0; i < firstDayOfMonth; i++) {
                    calendarHTML += '<div></div>';
                }
                
                // Add days of the month
                for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(currentYear, currentMonth, day);
                    const dateString = date.toISOString().split('T')[0];
                    const isWeekday = date.getDay() >= 1 && date.getDay() <= 5;
                    const isToday = day === today.getDate();
                    const isPast = date < today;
                    
                    let dayClass = 'calendar-day';
                    let dayStyle = `
                        padding: 12px; 
                        cursor: pointer; 
                        border-radius: 8px; 
                        transition: all 0.3s ease;
                        text-align: center;
                        font-weight: 600;
                        border: 2px solid transparent;
                        user-select: none;
                    `;
                    
                    if (isPast) {
                        dayStyle += ' background: #f3f4f6; color: #9ca3af; cursor: not-allowed;';
                        dayClass += ' past-day';
                    } else if (isWeekday) {
                        dayStyle += ' background: #e8f5e8; color: #059669;';
                        dayClass += ' available-day';
                    } else {
                        dayStyle += ' background: #fef3c7; color: #d97706;';
                        dayClass += ' weekend-day';
                    }
                    
                    if (isToday && !isPast) {
                        dayStyle += ' border-color: #667eea; box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.3);';
                    }
                    
                    calendarHTML += `
                        <div class="${dayClass}" 
                             style="${dayStyle}" 
                             data-date="${dateString}"
                             data-day="${day}"
                             ${!isPast ? `onclick="scheduleManager.handleSimpleDateClick('${dateString}', ${day})"` : ''}
                             ${!isPast ? `
                                onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)';"
                                onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='${isToday ? '0 0 0 2px rgba(102, 126, 234, 0.3)' : 'none'}';"
                             ` : ''}
                             title="${isPast ? 'Data passada' : isWeekday ? 'Dia útil - Clique para gerenciar' : 'Final de semana - Clique para gerenciar'}">
                            ${day}
                        </div>
                    `;
                }
                
                return calendarHTML;
            }
            
            handleSimpleDateClick(dateString, day) {
                console.log('Date clicked:', dateString, day);
                
                // Remove previous selections
                document.querySelectorAll('.calendar-day').forEach(dayEl => {
                    if (dayEl.classList.contains('selected')) {
                        dayEl.classList.remove('selected');
                        dayEl.style.background = dayEl.classList.contains('available-day') ? '#e8f5e8' : 
                                                  dayEl.classList.contains('weekend-day') ? '#fef3c7' : 
                                                  dayEl.style.background;
                    }
                });
                
                // Mark current selection
                const clickedElement = document.querySelector(`[data-date="${dateString}"]`);
                if (clickedElement && !clickedElement.classList.contains('past-day')) {
                    clickedElement.classList.add('selected');
                    clickedElement.style.background = '#667eea';
                    clickedElement.style.color = 'white';
                    clickedElement.style.transform = 'scale(1.1)';
                    clickedElement.style.boxShadow = '0 4px 15px rgba(102, 126, 234, 0.4)';
                }
                
                const today = new Date();
                const selectedDate = new Date(dateString);
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                const formattedDate = selectedDate.toLocaleDateString('pt-BR', options);
                
                this.handleDateSelection({
                    dateString: dateString,
                    formattedDate: formattedDate,
                    day: day,
                    isWeekday: selectedDate.getDay() >= 1 && selectedDate.getDay() <= 5
                });
                
                // Show visual feedback
                this.showMessage(`Data selecionada: ${formattedDate}`, 'success');
            }

            initializeProfessionalCalendar() {
                // Custom initialization for professional calendar view
                // Shows all dates with availability status
                console.log('Setting up professional calendar view');
                
                // Override the Smart Calendar's availability loading for professional view
                if (this.smartCalendar) {
                    this.smartCalendar.loadAvailableDays = this.loadProfessionalAvailability.bind(this);
                }
            }

            async loadProfessionalAvailability() {
                const month = this.smartCalendar?.currentDate?.getMonth() + 1 || new Date().getMonth() + 1;
                const year = this.smartCalendar?.currentDate?.getFullYear() || new Date().getFullYear();
                
                try {
                    const response = await fetch(`../api/professional/get-availability.php?month=${month}&year=${year}&professional_id=${this.professionalId}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Format data for Smart Calendar
                        if (this.smartCalendar) {
                            this.smartCalendar.availableDays = data.available_days || [];
                            this.smartCalendar.slotsDetails = data.slots_details || {};
                        }
                        this.availabilityData = data.availability_data || {};
                    } else {
                        console.warn('API returned success=false:', data.message);
                        // Use fallback data
                        this.setupFallbackData();
                    }
                } catch (error) {
                    console.error('Error loading professional availability:', error);
                    // Use fallback data when API fails
                    this.setupFallbackData();
                }
            }
            
            setupFallbackData() {
                // Generate demo availability data for current month
                const today = new Date();
                const currentMonth = today.getMonth();
                const currentYear = today.getFullYear();
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                
                const availableDays = [];
                for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(currentYear, currentMonth, day);
                    // Make weekdays available (Monday to Friday)
                    if (date.getDay() >= 1 && date.getDay() <= 5) {
                        availableDays.push(day);
                    }
                }
                
                if (this.smartCalendar) {
                    this.smartCalendar.availableDays = availableDays;
                    this.smartCalendar.slotsDetails = {};
                }
                
                console.log('Using fallback availability data:', availableDays);
            }

            handleDateSelection(data) {
                console.log('Date selected:', data);
                this.selectedDate = data.dateString;
                this.selectedSlots.clear();
                
                // Update date info immediately
                this.updateDateInfo(data);
                
                // Load time slots for this date
                this.loadTimeSlotsForDate(data.dateString);
                
                // Show loading in time slots area while loading
                const timeSlotsGrid = document.getElementById('timeSlots');
                if (timeSlotsGrid) {
                    timeSlotsGrid.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #6b7280;">
                            <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i>
                            Carregando horários...
                        </div>
                    `;
                }
                
                console.log('Date selection handled, loading slots for:', data.dateString);
            }

            updateDateInfo(data) {
                const titleElement = document.getElementById('date-info-title');
                const subtitleElement = document.getElementById('date-info-subtitle');
                const statsElement = document.getElementById('date-stats');
                const displayElement = document.getElementById('selected-date-display');
                
                if (titleElement) {
                    titleElement.textContent = data.formattedDate || data.dateString;
                }
                
                if (subtitleElement) {
                    const dayType = data.isWeekday !== undefined ? 
                        (data.isWeekday ? 'dia útil' : 'final de semana') : 
                        'data selecionada';
                    subtitleElement.textContent = `Gerencie os horários para este ${dayType}`;
                }
                
                if (displayElement) {
                    displayElement.textContent = `Horários para ${data.formattedDate || data.dateString}`;
                    displayElement.style.color = '#059669';
                    displayElement.style.fontWeight = '600';
                }
                
                if (statsElement) {
                    statsElement.style.display = 'block';
                }
                
                console.log('Date info updated:', data);
            }

            async loadTimeSlotsForDate(dateString) {
                const timeSlotsGrid = document.getElementById('timeSlots');
                if (!timeSlotsGrid) return;
                
                timeSlotsGrid.innerHTML = '<div class="loading-spinner"></div>';
                
                try {
                    const response = await fetch(`../api/professional/get-time-slots.php?date=${dateString}&professional_id=${this.professionalId}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.renderTimeSlots(data.time_slots);
                        this.updateDateStats(data.stats);
                    } else {
                        throw new Error(data.error || 'Failed to load time slots');
                    }
                } catch (error) {
                    console.error('Error loading time slots:', error);
                    // Use fallback demo time slots
                    this.renderDemoTimeSlots(dateString);
                }
            }
            
            renderDemoTimeSlots(dateString) {
                // Generate demo time slots for the selected date
                const demoSlots = [];
                const startHour = 8; // 8 AM
                const endHour = 18;  // 6 PM
                
                console.log('Generating demo time slots for:', dateString);
                
                for (let hour = startHour; hour < endHour; hour++) {
                    const time = `${hour.toString().padStart(2, '0')}:00`;
                    const displayTime = `${hour}:00`;
                    
                    // Randomly assign status for demo, but make most available
                    let status = 'available';
                    if (Math.random() < 0.15) status = 'booked';
                    else if (Math.random() < 0.05) status = 'blocked';
                    
                    demoSlots.push({
                        time: time,
                        display_time: displayTime,
                        status: status
                    });
                }
                
                // Render the slots
                this.renderTimeSlots(demoSlots);
                
                const stats = {
                    available: demoSlots.filter(s => s.status === 'available').length,
                    booked: demoSlots.filter(s => s.status === 'booked').length,
                    blocked: demoSlots.filter(s => s.status === 'blocked').length
                };
                
                this.updateDateStats(stats);
                
                console.log(`Generated ${demoSlots.length} demo time slots for ${dateString}:`, stats);
                
                // Show success message with call-to-action
                const selectedDate = new Date(dateString).toLocaleDateString('pt-BR', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long'
                });
                
                // Clear any previous messages first
                setTimeout(() => {
                    this.showMessage(`✅ Horários carregados para ${selectedDate}. ${stats.available} horários disponíveis para configuração.`, 'success');
                }, 100);
                
                // Enable bulk action buttons
                this.enableBulkActions(true);
            }
            
            enableBulkActions(enable) {
                const bulkButtons = document.querySelectorAll('.bulk-btn');
                bulkButtons.forEach(btn => {
                    if (enable) {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    } else {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                    }
                });
            }

            renderTimeSlots(timeSlots) {
                const timeSlotsGrid = document.getElementById('timeSlots');
                if (!timeSlotsGrid) return;
                
                console.log('Rendering time slots:', timeSlots);
                
                if (!timeSlots || timeSlots.length === 0) {
                    timeSlotsGrid.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #6b7280;">
                            <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                            <p>Nenhum horário disponível para esta data</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                
                timeSlots.forEach(slot => {
                    let slotClass = 'time-slot';
                    let icon = '';
                    
                    switch (slot.status) {
                        case 'available':
                            slotClass += ' available';
                            icon = '<i class="fas fa-check"></i>';
                            break;
                        case 'booked':
                            slotClass += ' booked';
                            icon = '<i class="fas fa-user"></i>';
                            break;
                        case 'blocked':
                            slotClass += ' blocked';
                            icon = '<i class="fas fa-ban"></i>';
                            break;
                        default:
                            icon = '<i class="fas fa-clock"></i>';
                    }
                    
                    html += `
                        <div class="${slotClass}" 
                             data-time="${slot.time}" 
                             data-status="${slot.status}"
                             onclick="scheduleManager.toggleTimeSlot(this)"
                             ${slot.status === 'booked' ? 'title="Já reservado por cliente"' : 'title="Clique para selecionar"'}>
                            ${icon}
                            <div style="margin-top: 5px;">${slot.display_time}</div>
                        </div>
                    `;
                });
                
                timeSlotsGrid.innerHTML = html;
                
                // Show success message
                const totalSlots = timeSlots.length;
                const availableSlots = timeSlots.filter(s => s.status === 'available').length;
                console.log(`Rendered ${totalSlots} time slots, ${availableSlots} available`);
            }

            updateDateStats(stats) {
                const availableCount = document.getElementById('available-count');
                const bookedCount = document.getElementById('booked-count');
                const blockedCount = document.getElementById('blocked-count');
                
                if (availableCount) availableCount.textContent = stats.available || 0;
                if (bookedCount) bookedCount.textContent = stats.booked || 0;
                if (blockedCount) blockedCount.textContent = stats.blocked || 0;
            }

            toggleTimeSlot(slotElement) {
                const time = slotElement.dataset.time;
                const status = slotElement.dataset.status;
                
                console.log('Toggling time slot:', time, status);
                
                // Cannot select booked slots
                if (status === 'booked') {
                    this.showMessage('Este horário já está reservado por um cliente', 'error');
                    return;
                }
                
                if (this.selectedSlots.has(time)) {
                    this.selectedSlots.delete(time);
                    slotElement.classList.remove('selected');
                    console.log('Deselected slot:', time);
                } else {
                    this.selectedSlots.add(time);
                    slotElement.classList.add('selected');
                    console.log('Selected slot:', time);
                }
                
                console.log('Currently selected slots:', Array.from(this.selectedSlots));
                
                // Update UI to show selected count
                const selectedCount = this.selectedSlots.size;
                if (selectedCount > 0) {
                    this.showMessage(`${selectedCount} horário${selectedCount > 1 ? 's' : ''} selecionado${selectedCount > 1 ? 's' : ''}`, 'info');
                }
            }

            async updateSlotStatus(status) {
                if (this.selectedSlots.size === 0) {
                    this.showMessage('Selecione pelo menos um horário para atualizar.', 'error');
                    return;
                }
                
                const slots = Array.from(this.selectedSlots);
                
                try {
                    const response = await fetch('../api/professional/update-availability.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            professional_id: this.professionalId,
                            date: this.selectedDate,
                            time_slots: slots,
                            status: status
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Reload time slots for the selected date
                        await this.loadTimeSlotsForDate(this.selectedDate);
                        this.selectedSlots.clear();
                        
                        // Show success message
                        let statusText = 'atualizados';
                        if (status === 'available') statusText = 'disponibilizados';
                        else if (status === 'blocked') statusText = 'bloqueados';
                        else if (status === 'unavailable') statusText = 'indisponibilizados';
                        
                        this.showMessage(`Horários ${statusText} com sucesso!`, 'success');
                    } else {
                        throw new Error(data.error || 'Erro ao atualizar horários');
                    }
                } catch (error) {
                    console.error('Error updating slots:', error);
                    
                    // Show demo success message since API might not exist
                    let statusText = 'atualizados';
                    if (status === 'available') statusText = 'disponibilizados';
                    else if (status === 'blocked') statusText = 'bloqueados';
                    else if (status === 'unavailable') statusText = 'indisponibilizados';
                    
                    this.showMessage(`DEMO: Horários ${statusText} (funcionalidade em desenvolvimento)`, 'info');
                    
                    // Update UI to show the change
                    this.selectedSlots.forEach(time => {
                        const slotElement = document.querySelector(`[data-time="${time}"]`);
                        if (slotElement) {
                            // Remove old status classes
                            slotElement.classList.remove('available', 'blocked', 'unavailable');
                            // Add new status class
                            if (status !== 'unavailable') {
                                slotElement.classList.add(status);
                            }
                            slotElement.dataset.status = status;
                            slotElement.classList.remove('selected');
                        }
                    });
                    
                    this.selectedSlots.clear();
                }
            }

            selectAllSlots() {
                if (!this.selectedDate) {
                    this.showMessage('Selecione uma data primeiro', 'error');
                    return;
                }
                
                const availableSlots = document.querySelectorAll('.time-slot:not(.booked)');
                console.log('Selecting all available slots:', availableSlots.length);
                
                if (availableSlots.length === 0) {
                    this.showMessage('Nenhum horário disponível para seleção', 'error');
                    return;
                }
                
                // Clear previous selections
                this.selectedSlots.clear();
                document.querySelectorAll('.time-slot.selected').forEach(slot => {
                    slot.classList.remove('selected');
                });
                
                // Select all available slots
                availableSlots.forEach(slot => {
                    const time = slot.dataset.time;
                    if (time) {
                        this.selectedSlots.add(time);
                        slot.classList.add('selected');
                    }
                });
                
                console.log('All available slots selected:', Array.from(this.selectedSlots));
                this.showMessage(`${this.selectedSlots.size} horários selecionados`, 'success');
            }

            makeAvailable() {
                this.updateSlotStatus('available');
            }

            makeUnavailable() {
                this.updateSlotStatus('unavailable');
            }

            blockSlots() {
                this.updateSlotStatus('blocked');
            }

            async loadInitialData() {
                // Load any initial data needed
                console.log('Loading initial schedule data...');
            }

            bindEvents() {
                console.log('Binding schedule events...');
                
                // Global functions for onclick handlers
                window.scheduleManager = this;
                window.toggleService = this.toggleService.bind(this);
                window.setView = this.setView.bind(this);
                window.toggleAvailability = this.toggleAvailability.bind(this);
                window.selectAllSlots = this.selectAllSlots.bind(this);
                window.makeAvailable = this.makeAvailable.bind(this);
                window.makeUnavailable = this.makeUnavailable.bind(this);
                window.blockSlots = this.blockSlots.bind(this);
                window.openBulkActions = this.openBulkActions.bind(this);
                window.openQuickAvailability = this.openQuickAvailability.bind(this);
            }

            toggleService(toggleElement) {
                const serviceId = toggleElement.dataset.serviceId;
                toggleElement.classList.toggle('active');
                
                // Here you would implement service activation/deactivation
                console.log(`Service ${serviceId} ${toggleElement.classList.contains('active') ? 'activated' : 'deactivated'}`);
            }

            setView(viewType) {
                this.currentView = viewType;
                
                // Update view buttons
                document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
                event.target.classList.add('active');
                
                // Implement view changes (this would integrate with Smart Calendar views)
                console.log(`View changed to: ${viewType}`);
            }

            toggleAvailability() {
                // Toggle professional online/offline status
                console.log('Toggling professional availability status');
                
                const statusIndicator = document.querySelector('.status-indicator span');
                const currentStatus = statusIndicator.textContent;
                
                if (currentStatus.includes('Online')) {
                    statusIndicator.textContent = 'Offline';
                    statusIndicator.parentElement.style.opacity = '0.6';
                } else {
                    statusIndicator.textContent = 'Online & Disponível';
                    statusIndicator.parentElement.style.opacity = '1';
                }
            }

            openBulkActions() {
                // Open bulk actions modal
                alert('Funcionalidade de ações em massa em desenvolvimento!');
            }

            openQuickAvailability() {
                // Open quick availability setup
                alert('Funcionalidade de disponibilidade rápida em desenvolvimento!');
            }

            showMessage(message, type = 'info') {
                const messageElement = document.createElement('div');
                let className = 'success-message';
                
                switch(type) {
                    case 'error':
                        className = 'error-message';
                        break;
                    case 'info':
                        className = 'message-info';
                        break;
                    case 'success':
                    default:
                        className = 'success-message';
                        break;
                }
                
                messageElement.className = className;
                messageElement.textContent = message;
                
                const container = document.querySelector('.main-content-card');
                if (container) {
                    container.insertBefore(messageElement, container.firstChild);
                    
                    // Scroll to message
                    messageElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    
                    // Remove message after 5 seconds
                    setTimeout(() => {
                        if (messageElement.parentNode) {
                            messageElement.style.opacity = '0';
                            messageElement.style.transform = 'translateY(-20px)';
                            setTimeout(() => {
                                messageElement.parentNode.removeChild(messageElement);
                            }, 300);
                        }
                    }, 5000);
                }
            }
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Professional Schedule Manager: DOM loaded, initializing...');
            new ProfessionalScheduleManager();
        });
    </script>
</body>
</html>
