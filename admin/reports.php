<?php
/**
 * Admin Reports - Financial and Business Analytics
 * Blue Cleaning Services
 */

session_start();
require_once __DIR__ . '/../config/australian-environment.php';
require_once __DIR__ . '/../config/australian-database.php';

// Load Australian environment configuration
AustralianEnvironmentConfig::load();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

// Get standardized database connection
$db = AustralianDatabase::getInstance()->getConnection();

// Date range handling
$dateRange = $_GET['range'] ?? '30';
$customStart = $_GET['start'] ?? null;
$customEnd = $_GET['end'] ?? null;

if ($customStart && $customEnd) {
    $whereClause = "WHERE created_at BETWEEN '$customStart' AND '$customEnd'";
    $dateLabel = "Custom Range";
} else {
    $whereClause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$dateRange} DAY)";
    $dateLabel = $dateRange . " Days";
}

// Revenue Analytics
function getRevenueAnalytics($db, $whereClause) {
    $analytics = [];
    
    // Total revenue
    $stmt = $db->query("
        SELECT 
            SUM(amount) as total_revenue,
            COUNT(*) as total_transactions,
            AVG(amount) as avg_transaction
        FROM payments 
        {$whereClause} 
        AND status = 'completed'
    ");
    $analytics['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Revenue by service type
    $stmt = $db->query("
        SELECT 
            s.name as service_name,
            SUM(p.amount) as revenue,
            COUNT(p.id) as transactions
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN services s ON b.service_id = s.id
        {$whereClause}
        AND p.status = 'completed'
        GROUP BY s.id
        ORDER BY revenue DESC
    ");
    $analytics['by_service'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily revenue trend
    $stmt = $db->query("
        SELECT 
            DATE(created_at) as date,
            SUM(amount) as daily_revenue,
            COUNT(*) as daily_transactions
        FROM payments 
        {$whereClause} 
        AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $analytics['daily_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $analytics;
}

// Customer Analytics
function getCustomerAnalytics($db, $whereClause) {
    $analytics = [];
    
    // Customer acquisition
    $stmt = $db->query("
        SELECT COUNT(*) as new_customers
        FROM users 
        {$whereClause} 
        AND user_type = 'customer'
    ");
    $analytics['new_customers'] = $stmt->fetchColumn();
    
    // Customer retention (customers who made multiple bookings)
    $stmt = $db->query("
        SELECT COUNT(*) as returning_customers
        FROM (
            SELECT customer_id, COUNT(*) as booking_count
            FROM bookings b
            WHERE EXISTS (
                SELECT 1 FROM bookings b2 
                WHERE b2.customer_id = b.customer_id 
                AND b2.created_at < b.created_at
            )
            {str_replace('WHERE', 'AND', $whereClause)}
            GROUP BY customer_id
        ) as returning
    ");
    $analytics['returning_customers'] = $stmt->fetchColumn();
    
    // Top customers by value
    $stmt = $db->query("
        SELECT 
            u.name,
            u.email,
            SUM(p.amount) as total_spent,
            COUNT(b.id) as total_bookings
        FROM users u
        JOIN bookings b ON u.id = b.customer_id
        JOIN payments p ON b.id = p.booking_id
        {$whereClause}
        AND p.status = 'completed'
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $analytics['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $analytics;
}

// Professional Analytics
function getProfessionalAnalytics($db, $whereClause) {
    $analytics = [];
    
    // Professional performance
    $stmt = $db->query("
        SELECT 
            p.name,
            COUNT(b.id) as total_bookings,
            AVG(r.rating) as avg_rating,
            SUM(pay.amount) as total_revenue
        FROM professionals p
        LEFT JOIN bookings b ON p.id = b.professional_id
        LEFT JOIN ratings r ON b.id = r.booking_id
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE 1=1
        " . str_replace('WHERE', 'AND b.', $whereClause) . "
        AND (pay.status = 'completed' OR pay.status IS NULL)
        GROUP BY p.id
        ORDER BY total_bookings DESC
    ");
    $analytics['performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Booking distribution
    $stmt = $db->query("
        SELECT 
            HOUR(b.date_time) as hour,
            COUNT(*) as booking_count
        FROM bookings b
        {$whereClause}
        GROUP BY HOUR(b.date_time)
        ORDER BY hour
    ");
    $analytics['booking_hours'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $analytics;
}

$revenueData = getRevenueAnalytics($db, $whereClause);
$customerData = getCustomerAnalytics($db, $whereClause);
$professionalData = getProfessionalAnalytics($db, $whereClause);

// Calculate growth rates
function calculateGrowthRate($current, $previous) {
    if ($previous == 0) return 0;
    return (($current - $previous) / $previous) * 100;
}

// Get previous period data for comparison
$prevWhereClause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . ($dateRange * 2) . " DAY) AND created_at < DATE_SUB(NOW(), INTERVAL {$dateRange} DAY)";
$prevRevenueData = getRevenueAnalytics($db, $prevWhereClause);

$revenueGrowth = calculateGrowthRate(
    $revenueData['revenue']['total_revenue'], 
    $prevRevenueData['revenue']['total_revenue']
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Blue Cleaning Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Include sidebar from dashboard.php -->
        <div class="w-64 bg-blue-800 text-white">
            <div class="p-4">
                <h1 class="text-xl font-bold">Blue Admin</h1>
            </div>
            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 hover:bg-blue-700">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 bg-blue-700 border-r-4 border-blue-500">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Reports
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-md p-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-semibold text-gray-800">Financial Reports</h2>
                    <div class="flex items-center space-x-4">
                        <!-- Date Range Selector -->
                        <form method="GET" class="flex items-center space-x-2">
                            <select name="range" class="border rounded px-3 py-2" onchange="this.form.submit()">
                                <option value="7" <?= $dateRange == '7' ? 'selected' : '' ?>>Last 7 Days</option>
                                <option value="30" <?= $dateRange == '30' ? 'selected' : '' ?>>Last 30 Days</option>
                                <option value="90" <?= $dateRange == '90' ? 'selected' : '' ?>>Last 90 Days</option>
                                <option value="365" <?= $dateRange == '365' ? 'selected' : '' ?>>Last Year</option>
                            </select>
                        </form>
                        <button onclick="exportReport()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
            </header>
            
            <!-- Key Metrics -->
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-800">$<?= number_format($revenueData['revenue']['total_revenue'] ?? 0, 2) ?></p>
                        </div>
                        <div class="text-right">
                            <span class="text-<?= $revenueGrowth >= 0 ? 'green' : 'red' ?>-600 text-sm">
                                <i class="fas fa-arrow-<?= $revenueGrowth >= 0 ? 'up' : 'down' ?>"></i>
                                <?= number_format(abs($revenueGrowth), 1) ?>%
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Average Transaction</p>
                            <p class="text-2xl font-bold text-gray-800">$<?= number_format($revenueData['revenue']['avg_transaction'] ?? 0, 2) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">New Customers</p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($customerData['new_customers']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Customer Retention</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php 
                                $total_customers = max($customerData['new_customers'] + $customerData['returning_customers'], 1);
                                echo number_format(($customerData['returning_customers'] / $total_customers) * 100, 1) . '%'; 
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Revenue Trend -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-4">Revenue Trend</h3>
                    <canvas id="revenueTrendChart"></canvas>
                </div>
                
                <!-- Service Performance -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-4">Revenue by Service</h3>
                    <canvas id="serviceChart"></canvas>
                </div>
                
                <!-- Booking Hours Distribution -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-4">Booking Hours Distribution</h3>
                    <canvas id="bookingHoursChart"></canvas>
                </div>
                
                <!-- Professional Performance -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-4">Professional Performance</h3>
                    <div class="overflow-y-auto max-h-64">
                        <?php foreach (array_slice($professionalData['performance'], 0, 5) as $prof): ?>
                            <div class="flex justify-between items-center py-2 border-b">
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars($prof['name']) ?></p>
                                    <p class="text-sm text-gray-500">
                                        <?= $prof['total_bookings'] ?> bookings • 
                                        <?= number_format($prof['avg_rating'], 1) ?>★ rating
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold">$<?= number_format($prof['total_revenue'] ?? 0, 2) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Tables -->
            <div class="p-6">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">Top Customers</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-left py-3 px-4">Customer</th>
                                    <th class="text-left py-3 px-4">Email</th>
                                    <th class="text-left py-3 px-4">Total Spent</th>
                                    <th class="text-left py-3 px-4">Bookings</th>
                                    <th class="text-left py-3 px-4">Avg Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customerData['top_customers'] as $customer): ?>
                                    <tr class="border-b">
                                        <td class="py-3 px-4"><?= htmlspecialchars($customer['name']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($customer['email']) ?></td>
                                        <td class="py-3 px-4 font-semibold">$<?= number_format($customer['total_spent'], 2) ?></td>
                                        <td class="py-3 px-4"><?= $customer['total_bookings'] ?></td>
                                        <td class="py-3 px-4">$<?= number_format($customer['total_spent'] / $customer['total_bookings'], 2) ?></td>
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
        // Revenue Trend Chart
        const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
        new Chart(revenueTrendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($revenueData['daily_trend'], 'date')) ?>,
                datasets: [{
                    label: 'Daily Revenue',
                    data: <?= json_encode(array_column($revenueData['daily_trend'], 'daily_revenue')) ?>,
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
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Service Performance Chart
        const serviceCtx = document.getElementById('serviceChart').getContext('2d');
        new Chart(serviceCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($revenueData['by_service'], 'service_name')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($revenueData['by_service'], 'revenue')) ?>,
                    backgroundColor: [
                        '#3B82F6',
                        '#10B981',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6',
                        '#06B6D4'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Booking Hours Chart
        const hoursCtx = document.getElementById('bookingHoursChart').getContext('2d');
        new Chart(hoursCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($h) { return $h['hour'] . ':00'; }, $professionalData['booking_hours'])) ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?= json_encode(array_column($professionalData['booking_hours'], 'booking_count')) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Export functionality
        function exportReport() {
            const data = {
                revenue: <?= json_encode($revenueData) ?>,
                customers: <?= json_encode($customerData) ?>,
                professionals: <?= json_encode($professionalData) ?>,
                dateRange: '<?= $dateLabel ?>'
            };
            
            const csvContent = generateCSV(data);
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `blue-cleaning-report-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        function generateCSV(data) {
            let csv = 'Blue Cleaning Services Report\n';
            csv += `Date Range: ${data.dateRange}\n\n`;
            
            csv += 'Revenue Summary\n';
            csv += `Total Revenue,$${data.revenue.revenue.total_revenue}\n`;
            csv += `Total Transactions,${data.revenue.revenue.total_transactions}\n`;
            csv += `Average Transaction,$${data.revenue.revenue.avg_transaction}\n\n`;
            
            csv += 'Revenue by Service\n';
            csv += 'Service,Revenue,Transactions\n';
            data.revenue.by_service.forEach(service => {
                csv += `${service.service_name},$${service.revenue},${service.transactions}\n`;
            });
            
            return csv;
        }
    </script>
</body>
</html>
