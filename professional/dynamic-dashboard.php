<?php
/**
 * Dynamic Professional Dashboard - Blue Cleaning Services
 * URL: /professional/dynamic-dashboard.php?professional_id={ID}&token={AUTH_TOKEN}
 */

session_start();

// Get professional ID from URL parameters or session
$professional_id = $_GET['professional_id'] ?? $_SESSION['user_id'] ?? null;
$auth_token = $_GET['token'] ?? $_SESSION['auth_token'] ?? null;

// Validate professional access
if (!$professional_id) {
    // Redirect to login if no professional ID
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Optional: Validate auth token for additional security
$page_title = "Professional Dashboard - ID: " . htmlspecialchars($professional_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Blue Cleaning Services</title>
    
    <!-- External Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/smart-calendar.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .professional-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .professional-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .professional-details h1 {
            color: #2d3748;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .professional-details p {
            color: #718096;
            margin-bottom: 3px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #10b981;
            color: white;
        }

        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .card-title {
            color: #2d3748;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #718096;
            font-weight: 500;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        .recent-bookings {
            max-height: 400px;
            overflow-y: auto;
        }

        .booking-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 12px;
            background: rgba(102, 126, 234, 0.05);
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .booking-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }

        .booking-service {
            flex: 1;
            font-weight: 600;
            color: #2d3748;
        }

        .booking-customer {
            color: #718096;
            font-size: 0.9rem;
        }

        .booking-date {
            color: #667eea;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .settings-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .preference-group {
            margin-bottom: 25px;
        }

        .preference-group h3 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #ccc;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-switch.active {
            background: #667eea;
        }

        .toggle-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .toggle-switch.active .toggle-slider {
            transform: translateX(30px);
        }

        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: #718096;
        }

        .loading i {
            margin-right: 10px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .notification-badge {
            position: relative;
        }

        .notification-badge::after {
            content: '';
            position: absolute;
            top: -5px;
            right: -5px;
            width: 12px;
            height: 12px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .professional-info {
                flex-direction: column;
                text-align: center;
            }
            
            .quick-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="professional-info">
                <div class="professional-avatar" id="professionalAvatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="professional-details">
                    <h1 id="professionalName">Loading...</h1>
                    <p id="professionalEmail">Loading...</p>
                    <p>
                        <span class="status-badge status-active" id="professionalStatus">Active</span>
                        <span id="professionalRating">⭐ 4.9 (127 reviews)</span>
                    </p>
                </div>
            </div>
            
            <div class="quick-actions">
                <button class="action-btn" onclick="openScheduleManager()">
                    <i class="fas fa-calendar-alt"></i> Manage Schedule
                </button>
                <button class="action-btn" onclick="viewAvailableJobs()">
                    <i class="fas fa-briefcase"></i> View Jobs
                    <span class="notification-badge"></span>
                </button>
                <button class="action-btn" onclick="openSettings()">
                    <i class="fas fa-cog"></i> Settings
                </button>
                <button class="action-btn" onclick="viewReports()">
                    <i class="fas fa-chart-line"></i> Reports
                </button>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Performance Stats -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-title">Performance Overview</div>
                </div>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="completedJobs">0</div>
                        <div class="stat-label">Completed Jobs</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="activeJobs">0</div>
                        <div class="stat-label">Active Jobs</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="avgRating">0.0</div>
                        <div class="stat-label">Average Rating</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalReviews">0</div>
                        <div class="stat-label">Total Reviews</div>
                    </div>
                </div>
            </div>

            <!-- Earnings Summary -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="card-title">Earnings Summary</div>
                </div>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="currentMonthEarnings">$0</div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="lastMonthEarnings">$0</div>
                        <div class="stat-label">Last Month</div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="earningsChart"></canvas>
                </div>
            </div>

            <!-- Availability Status -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-title">Availability Status</div>
                </div>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="availableSlots">0</div>
                        <div class="stat-label">Available Slots</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="activeDays">0</div>
                        <div class="stat-label">Active Days</div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="card-title">Recent Bookings</div>
                </div>
                <div class="recent-bookings" id="recentBookingsList">
                    <div class="loading">
                        <i class="fas fa-spinner"></i>
                        Loading bookings...
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Section -->
        <div class="settings-section" id="settingsSection" style="display: none;">
            <h2 style="margin-bottom: 30px; color: #2d3748;">
                <i class="fas fa-cog"></i> Professional Settings
            </h2>
            
            <div class="preference-group">
                <h3>Notification Preferences</h3>
                <div class="preference-item">
                    <span>New Booking Notifications</span>
                    <div class="toggle-switch active" data-preference="new_booking">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
                <div class="preference-item">
                    <span>Booking Reminders</span>
                    <div class="toggle-switch active" data-preference="booking_reminder">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
                <div class="preference-item">
                    <span>Payment Notifications</span>
                    <div class="toggle-switch active" data-preference="payment_received">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
                <div class="preference-item">
                    <span>Marketing Communications</span>
                    <div class="toggle-switch" data-preference="marketing">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
            </div>

            <div class="preference-group">
                <h3>Booking Preferences</h3>
                <div class="preference-item">
                    <span>Auto-Accept Bookings</span>
                    <div class="toggle-switch" data-preference="auto_accept_bookings">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        class DynamicProfessionalDashboard {
            constructor() {
                // Get professional ID from PHP or URL parameters
                this.professionalId = <?= json_encode($professional_id) ?>;
                this.authToken = <?= json_encode($auth_token) ?>;
                
                // Validate professional ID
                if (!this.professionalId) {
                    this.redirectToLogin();
                    return;
                }
                
                this.apiBase = '../api/professional/dynamic-management.php';
                this.charts = {};
                this.settings = {};
                this.init();
            }

            redirectToLogin() {
                window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            }

            async init() {
                try {
                    await this.loadProfessionalProfile();
                    await this.loadDashboardData();
                    this.initializeEventListeners();
                    this.startRealTimeUpdates();
                } catch (error) {
                    console.error('Dashboard initialization failed:', error);
                    this.showErrorMessage('Failed to initialize dashboard. Please refresh the page.');
                }
            }

            async loadProfessionalProfile() {
                try {
                    const url = `${this.apiBase}?action=profile&professional_id=${this.professionalId}`;
                    const headers = {
                        'Content-Type': 'application/json'
                    };
                    
                    // Add auth token to headers if available
                    if (this.authToken) {
                        headers['Authorization'] = `Bearer ${this.authToken}`;
                    }
                    
                    const response = await fetch(url, { headers });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.updateProfileDisplay(data.profile);
                    } else {
                        throw new Error(data.message || 'Failed to load profile');
                    }
                } catch (error) {
                    console.error('Error loading profile:', error);
                    this.showErrorMessage('Failed to load professional profile');
                }
            }

            async loadDashboardData() {
                try {
                    const url = `${this.apiBase}?action=dashboard-data&professional_id=${this.professionalId}`;
                    const headers = {
                        'Content-Type': 'application/json'
                    };
                    
                    // Add auth token to headers if available
                    if (this.authToken) {
                        headers['Authorization'] = `Bearer ${this.authToken}`;
                    }
                    
                    const response = await fetch(url, { headers });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.updateDashboardDisplay(data.dashboard);
                    } else {
                        throw new Error(data.message || 'Failed to load dashboard data');
                    }
                } catch (error) {
                    console.error('Error loading dashboard data:', error);
                    this.showErrorMessage('Failed to load dashboard data');
                }
            }

            updateProfileDisplay(profile) {
                document.getElementById('professionalName').textContent = 
                    profile.first_name + ' ' + profile.last_name;
                document.getElementById('professionalEmail').textContent = profile.email;
                
                if (profile.avg_rating) {
                    document.getElementById('professionalRating').textContent = 
                        `⭐ ${parseFloat(profile.avg_rating).toFixed(1)} (${profile.total_reviews} reviews)`;
                }

                // Update avatar with initials
                const initials = (profile.first_name?.[0] || '') + (profile.last_name?.[0] || '');
                if (initials) {
                    document.getElementById('professionalAvatar').textContent = initials;
                }
            }

            updateDashboardDisplay(dashboard) {
                // Update stats
                const stats = dashboard.stats;
                document.getElementById('completedJobs').textContent = stats.completed_jobs || 0;
                document.getElementById('activeJobs').textContent = stats.active_jobs || 0;
                document.getElementById('avgRating').textContent = 
                    stats.avg_rating ? parseFloat(stats.avg_rating).toFixed(1) : '0.0';
                document.getElementById('totalReviews').textContent = stats.total_reviews || 0;

                // Update earnings
                const earnings = dashboard.earnings_summary;
                document.getElementById('currentMonthEarnings').textContent = 
                    `$${parseFloat(earnings.current_month || 0).toFixed(0)}`;
                document.getElementById('lastMonthEarnings').textContent = 
                    `$${parseFloat(earnings.last_month || 0).toFixed(0)}`;

                // Update availability
                const availability = dashboard.availability_summary;
                document.getElementById('availableSlots').textContent = availability.available_slots || 0;
                document.getElementById('activeDays').textContent = availability.active_days || 0;

                // Update recent bookings
                this.updateRecentBookings(dashboard.recent_bookings);

                // Create earnings chart
                this.createEarningsChart(earnings);
            }

            updateRecentBookings(bookings) {
                const container = document.getElementById('recentBookingsList');
                
                if (!bookings || bookings.length === 0) {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #718096;">
                            <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>No recent bookings</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = bookings.map(booking => `
                    <div class="booking-item">
                        <div style="flex: 1;">
                            <div class="booking-service">${booking.service_name || 'Service'}</div>
                            <div class="booking-customer">${booking.customer_name || 'Customer'}</div>
                        </div>
                        <div class="booking-date">
                            ${new Date(booking.scheduled_date).toLocaleDateString()}
                        </div>
                    </div>
                `).join('');
            }

            createEarningsChart(earnings) {
                const ctx = document.getElementById('earningsChart');
                if (!ctx) return;

                if (this.charts.earnings) {
                    this.charts.earnings.destroy();
                }

                this.charts.earnings = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Last Month', 'This Month'],
                        datasets: [{
                            label: 'Earnings',
                            data: [earnings.last_month || 0, earnings.current_month || 0],
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value;
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            initializeEventListeners() {
                // Toggle switches
                document.querySelectorAll('.toggle-switch').forEach(toggle => {
                    toggle.addEventListener('click', (e) => {
                        e.currentTarget.classList.toggle('active');
                        const preference = e.currentTarget.dataset.preference;
                        const isActive = e.currentTarget.classList.contains('active');
                        this.updatePreference(preference, isActive);
                    });
                });
            }

            async updatePreference(key, value) {
                try {
                    const headers = {
                        'Content-Type': 'application/json',
                        'X-Professional-ID': this.professionalId
                    };
                    
                    // Add auth token to headers if available
                    if (this.authToken) {
                        headers['Authorization'] = `Bearer ${this.authToken}`;
                    }
                    
                    const response = await fetch(`${this.apiBase}?action=preferences`, {
                        method: 'PUT',
                        headers: headers,
                        body: JSON.stringify({
                            professional_id: this.professionalId,
                            [key]: value
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        console.log('Preference updated:', key, value);
                        this.showSuccessMessage(`Preference "${key}" updated successfully`);
                    } else {
                        throw new Error(data.message || 'Failed to update preference');
                    }
                } catch (error) {
                    console.error('Error updating preference:', error);
                    this.showErrorMessage('Failed to update preference');
                }
            }

            startRealTimeUpdates() {
                // Update dashboard data every 30 seconds
                setInterval(() => {
                    this.loadDashboardData();
                }, 30000);
            }

            showErrorMessage(message) {
                // Create toast notification for errors
                this.showNotification(message, 'error');
            }
            
            showSuccessMessage(message) {
                // Create toast notification for success
                this.showNotification(message, 'success');
            }
            
            showNotification(message, type = 'info') {
                // Simple toast notification system
                const toast = document.createElement('div');
                toast.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 8px;
                    color: white;
                    font-weight: 500;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                    max-width: 300px;
                    word-wrap: break-word;
                `;
                
                switch (type) {
                    case 'error':
                        toast.style.background = '#ef4444';
                        break;
                    case 'success':
                        toast.style.background = '#10b981';
                        break;
                    default:
                        toast.style.background = '#667eea';
                }
                
                toast.textContent = message;
                document.body.appendChild(toast);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    toast.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }, 5000);
            }
        }

        // URL Helper Functions
        function getUrlParameter(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }
        
        function updateUrlParameter(key, value) {
            const url = new URL(window.location);
            url.searchParams.set(key, value);
            window.history.replaceState({}, '', url);
        }

        // Global functions for button clicks
        function openScheduleManager() {
            const professionalId = <?= json_encode($professional_id) ?>;
            window.location.href = `schedule.php?professional_id=${professionalId}`;
        }

        function viewAvailableJobs() {
            // Implement job matching interface
            console.log('Opening job matching interface...');
        }

        function openSettings() {
            const settingsSection = document.getElementById('settingsSection');
            settingsSection.style.display = settingsSection.style.display === 'none' ? 'block' : 'none';
        }

        function viewReports() {
            // Implement reports interface
            console.log('Opening reports interface...');
        }

        // Initialize dashboard when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new DynamicProfessionalDashboard();
        });
    </script>
</body>
</html>
