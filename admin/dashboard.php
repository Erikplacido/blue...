<?php
/**
 * Admin Dashboard - Blue Cleaning Services
 * Complete administration panel
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

require_once __DIR__ . '/../config/australian-environment.php';
require_once __DIR__ . '/../config/australian-database.php';
require_once '../api/analytics.php';

// Load Australian environment configuration
AustralianEnvironmentConfig::load();

$analyticsService = new AnalyticsService();
$dashboardData = $analyticsService->getDashboardData('30 days');

// Get standardized database connection
$db = AustralianDatabase::getInstance()->getConnection();

// Fetch dashboard statistics
function getDashboardStats($db) {
    $stats = [];
    
    // Total bookings
    $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['bookings_30_days'] = $stmt->fetchColumn();
    
    // Total revenue
    $stmt = $db->query("SELECT SUM(amount) FROM payments WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['revenue_30_days'] = $stmt->fetchColumn() ?: 0;
    
    // Active customers
    $stmt = $db->query("SELECT COUNT(DISTINCT customer_id) FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_customers'] = $stmt->fetchColumn();
    
    // Pending bookings
    $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
    $stats['pending_bookings'] = $stmt->fetchColumn();
    
    // Professional utilization
    $stmt = $db->query("
        SELECT AVG(utilization) as avg_utilization FROM (
            SELECT 
                p.id,
                (COUNT(b.id) * 2.5 / 40) * 100 as utilization
            FROM professionals p
            LEFT JOIN bookings b ON p.id = b.professional_id 
                AND b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY p.id
        ) as util
    ");
    $stats['avg_utilization'] = $stmt->fetchColumn() ?: 0;
    
    return $stats;
}

$stats = getDashboardStats($db);

// Get recent bookings
$stmt = $db->prepare("
    SELECT b.*, u.name as customer_name, p.name as professional_name 
    FROM bookings b 
    LEFT JOIN users u ON b.customer_id = u.id
    LEFT JOIN professionals p ON b.professional_id = p.id
    ORDER BY b.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system alerts
function getSystemAlerts($db) {
    $alerts = [];
    
    // Check for failed payments
    $stmt = $db->query("SELECT COUNT(*) FROM payments WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $failedPayments = $stmt->fetchColumn();
    if ($failedPayments > 0) {
        $alerts[] = [
            'type' => 'danger',
            'message' => "{$failedPayments} failed payments in the last 24 hours",
            'action_url' => 'payments.php?status=failed'
        ];
    }
    
    // Check for unassigned bookings
    $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE professional_id IS NULL AND status = 'confirmed'");
    $unassigned = $stmt->fetchColumn();
    if ($unassigned > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "{$unassigned} bookings need professional assignment",
            'action_url' => 'bookings.php?status=unassigned'
        ];
    }
    
    // Check for upcoming bookings without confirmation
    $stmt = $db->query("
        SELECT COUNT(*) FROM bookings 
        WHERE status = 'pending' 
        AND date_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
    ");
    $pendingUrgent = $stmt->fetchColumn();
    if ($pendingUrgent > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "{$pendingUrgent} bookings need confirmation within 24 hours",
            'action_url' => 'bookings.php?urgent=1'
        ];
    }
    
    return $alerts;
}

$alerts = getSystemAlerts($db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Blue Cleaning Services</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="flex h-screen">
        <div class="w-64 bg-blue-800 text-white">
            <div class="p-4">
                <h1 class="text-xl font-bold">Blue Admin</h1>
            </div>
            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 bg-blue-700 border-r-4 border-blue-500">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="bookings.php" class="flex items-center px-4 py-3 hover:bg-blue-700">
                    <i class="fas fa-calendar mr-3"></i>
                    Bookings
                    <?php if ($stats['pending_bookings'] > 0): ?>
                        <span class="ml-auto bg-red-500 text-xs px-2 py-1 rounded"><?= $stats['pending_bookings'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="customers.php" class="flex items-center px-4 py-3 hover:bg-blue-700">
                    <i class="fas fa-users mr-3"></i>
                    Customers
                </a>
                <a href="professionals.php" class="flex items-center px-4 py-3 hover:bg-blue-700">
                    <i class="fas fa-user-tie mr-3"></i>
                    Professionals
                </a>
                <a href="payments.php" class="flex items-center px-4 py-3 hover:bg-blue-700">
                    <i class="fas fa-credit-card mr-3"></i>
                    Payments
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 hover:bg-blue-700">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Reports
                </a>
                <a href="settings.php" class="flex items-center px-4 py-3 hover:bg-blue-700">
                    <i class="fas fa-cog mr-3"></i>
                    Settings
                </a>
                <a href="support.php" class="flex items-center px-4 py-3 hover:bg-blue-700">
                    <i class="fas fa-headset mr-3"></i>
                    Support
                </a>
                <a href="logout.php" class="flex items-center px-4 py-3 hover:bg-red-700 mt-8">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-md p-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-semibold text-gray-800">Dashboard</h2>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <i class="fas fa-bell text-gray-500"></i>
                            <?php if (count($alerts) > 0): ?>
                                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                    <?= count($alerts) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-gray-700">Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Alerts -->
            <?php if (!empty($alerts)): ?>
                <div class="p-4">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="bg-<?= $alert['type'] === 'danger' ? 'red' : 'yellow' ?>-100 border border-<?= $alert['type'] === 'danger' ? 'red' : 'yellow' ?>-400 text-<?= $alert['type'] === 'danger' ? 'red' : 'yellow' ?>-700 px-4 py-3 rounded mb-2">
                            <div class="flex justify-between">
                                <span><?= htmlspecialchars($alert['message']) ?></span>
                                <a href="<?= htmlspecialchars($alert['action_url']) ?>" class="underline">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-calendar text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-gray-500 text-sm">Bookings (30 days)</p>
                            <p class="text-2xl font-semibold text-gray-800"><?= number_format($stats['bookings_30_days']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-dollar-sign text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-gray-500 text-sm">Revenue (30 days)</p>
                            <p class="text-2xl font-semibold text-gray-800">$<?= number_format($stats['revenue_30_days'], 2) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-users text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-gray-500 text-sm">Active Customers</p>
                            <p class="text-2xl font-semibold text-gray-800"><?= number_format($stats['active_customers']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 rounded-full">
                            <i class="fas fa-percentage text-orange-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-gray-500 text-sm">Staff Utilization</p>
                            <p class="text-2xl font-semibold text-gray-800"><?= number_format($stats['avg_utilization'], 1) ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Tables -->
            <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Revenue Chart -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-4">Revenue Trend</h3>
                    <canvas id="revenueChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Traffic Sources -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-4">Traffic Sources</h3>
                    <div class="space-y-2">
                        <?php foreach ($dashboardData['traffic_sources'] as $source): ?>
                            <div class="flex justify-between items-center">
                                <span class="capitalize"><?= htmlspecialchars($source['source']) ?></span>
                                <span class="font-semibold"><?= number_format($source['sessions']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recent Bookings -->
                <div class="bg-white p-6 rounded-lg shadow lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Recent Bookings</h3>
                        <a href="bookings.php" class="text-blue-600 hover:text-blue-800">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2">ID</th>
                                    <th class="text-left py-2">Customer</th>
                                    <th class="text-left py-2">Professional</th>
                                    <th class="text-left py-2">Date</th>
                                    <th class="text-left py-2">Status</th>
                                    <th class="text-left py-2">Amount</th>
                                    <th class="text-left py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $booking): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-2">#<?= htmlspecialchars($booking['id']) ?></td>
                                        <td class="py-2"><?= htmlspecialchars($booking['customer_name'] ?? 'Unknown') ?></td>
                                        <td class="py-2"><?= htmlspecialchars($booking['professional_name'] ?? 'Unassigned') ?></td>
                                        <td class="py-2"><?= date('M j, Y', strtotime($booking['date_time'])) ?></td>
                                        <td class="py-2">
                                            <span class="px-2 py-1 rounded text-xs 
                                                <?= $booking['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : 
                                                   ($booking['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                                <?= ucfirst($booking['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-2">$<?= number_format($booking['amount'] ?? 0, 2) ?></td>
                                        <td class="py-2">
                                            <a href="booking-details.php?id=<?= $booking['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2">View</a>
                                            <a href="booking-edit.php?id=<?= $booking['id'] ?>" class="text-green-600 hover:text-green-800">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Revenue ($)',
                    data: [1200, 1900, 3000, 2500],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
