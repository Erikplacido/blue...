<?php
/**
 * Dashboard Principal do Profissional - Blue Project V2
 * Interface estilo Uber para profissionais de serviços
 */

session_start();

// Simula dados do profissional (substituir por dados reais do banco)
$professional = [
    'id' => 'PROF_12345',
    'name' => 'Sarah Mitchell',
    'rating' => 4.9,
    'total_ratings' => 247,
    'services' => ['cleaning', 'gardening'],
    'status' => 'online',
    'profile_photo' => '/assets/images/default-avatar.jpg',
    'member_since' => '2024-01-15',
    'completion_rate' => 98.5,
    'response_rate' => 95.2,
    'on_time_rate' => 96.8
];

$todayStats = [
    'earnings' => 127.50,
    'jobs_completed' => 3,
    'jobs_pending' => 2,
    'hours_worked' => 5.5,
    'rating_today' => 4.8
];

$weeklyStats = [
    'earnings' => 680.25,
    'jobs_completed' => 22,
    'target_progress' => 78,
    'surge_earnings' => 85.40
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Dashboard - Blue Services</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/blue.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }
        
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: grid;
            grid-template-areas: 
                "header header"
                "status-bar status-bar"
                "main-content side-panel";
            grid-template-columns: 1fr 400px;
            grid-template-rows: auto auto 1fr;
            min-height: 100vh;
            gap: 0;
        }
        
        /* Header */
        .dashboard-header {
            grid-area: header;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .professional-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .profile-details h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .profile-details .rating {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* Status Bar */
        .status-bar {
            grid-area: status-bar;
            background: white;
            padding: 20px 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .status-controls {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #e5e7eb;
            border-radius: 15px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .toggle-switch.active {
            background: var(--success-color);
        }
        
        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }
        
        .toggle-switch.active::after {
            transform: translateX(30px);
        }
        
        .status-text {
            font-weight: 600;
            color: #1f2937;
        }
        
        .today-summary {
            display: flex;
            gap: 30px;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .summary-label {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        /* Main Content */
        .main-content {
            grid-area: main-content;
            padding: 25px;
            overflow-y: auto;
        }
        
        .content-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .tab-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .tab-btn.active {
            background: white;
            color: var(--primary-color);
            border-color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Map Container */
        .map-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: 400px;
            position: relative;
            margin-bottom: 25px;
        }
        
        .map-controls {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .map-control-btn {
            background: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        /* Job Cards */
        .jobs-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .jobs-filter {
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            background: #f3f4f6;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        .job-card {
            border: 2px solid #f3f4f6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .job-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.1);
        }
        
        .job-card.urgent {
            border-color: var(--warning-color);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(245, 158, 11, 0.1) 100%);
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .job-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success-color);
        }
        
        .job-duration {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .job-distance {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .job-details {
            margin-bottom: 15px;
        }
        
        .job-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .job-location {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        
        .job-time {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #6b7280;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .customer-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
        }
        
        .customer-details .customer-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .customer-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #f59e0b;
            font-size: 0.9rem;
        }
        
        .job-tags {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .job-tag {
            background: #f3f4f6;
            color: #6b7280;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .job-tag.urgent {
            background: #fef3c7;
            color: #92400e;
        }
        
        .job-tag.recurring {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .job-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-accept {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
            transition: all 0.3s ease;
        }
        
        .btn-accept:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .btn-decline {
            background: #f3f4f6;
            color: #6b7280;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-decline:hover {
            background: #e5e7eb;
        }
        
        .response-timer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #f3f4f6;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
        }
        
        .timer-bar {
            height: 100%;
            background: var(--warning-color);
            animation: countdown 60s linear forwards;
        }
        
        @keyframes countdown {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        /* Side Panel */
        .side-panel {
            grid-area: side-panel;
            background: white;
            padding: 25px;
            overflow-y: auto;
            border-left: 1px solid #e5e7eb;
        }
        
        .panel-section {
            margin-bottom: 30px;
        }
        
        .panel-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .earnings-chart {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
        }
        
        .upcoming-schedule {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
        }
        
        .schedule-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .schedule-item:last-child {
            border-bottom: none;
        }
        
        .schedule-time {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .schedule-service {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .notifications-panel {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
        }
        
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
        }
        
        .notification-icon.info {
            background: var(--info-color);
        }
        
        .notification-icon.success {
            background: var(--success-color);
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 2px;
        }
        
        .notification-text {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #9ca3af;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-container {
                grid-template-areas: 
                    "header"
                    "status-bar"
                    "main-content"
                    "side-panel";
                grid-template-columns: 1fr;
                grid-template-rows: auto auto 1fr auto;
            }
            
            .side-panel {
                border-left: none;
                border-top: 1px solid #e5e7eb;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 15px;
                flex-direction: column;
                gap: 15px;
            }
            
            .status-bar {
                padding: 15px;
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .status-controls {
                justify-content: center;
            }
            
            .today-summary {
                justify-content: space-around;
                gap: 15px;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .content-tabs {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .map-container {
                height: 300px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-gem"></i>
                    Blue Services
                </div>
                <span>Professional Dashboard</span>
            </div>
            
            <div class="professional-info">
                <div class="profile-details">
                    <h3><?= htmlspecialchars($professional['name']) ?></h3>
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        <?= $professional['rating'] ?> (<?= $professional['total_ratings'] ?> reviews)
                    </div>
                </div>
                <img src="<?= $professional['profile_photo'] ?>" alt="Profile" class="profile-avatar">
                <div class="header-actions">
                    <button class="btn-icon" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <button class="btn-icon" onclick="openSettings()">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-controls">
                <div class="status-toggle">
                    <div class="toggle-switch active" id="availabilityToggle" onclick="toggleAvailability()"></div>
                    <span class="status-text" id="statusText">Online & Available</span>
                </div>
                
                <div class="surge-indicator">
                    <i class="fas fa-bolt" style="color: var(--warning-color);"></i>
                    <span>High Demand Area - +40% Earnings</span>
                </div>
            </div>
            
            <div class="today-summary">
                <div class="summary-item">
                    <div class="summary-value">$<?= number_format($todayStats['earnings'], 2) ?></div>
                    <div class="summary-label">Today's Earnings</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?= $todayStats['jobs_completed'] ?></div>
                    <div class="summary-label">Jobs Completed</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?= $todayStats['jobs_pending'] ?></div>
                    <div class="summary-label">Pending Jobs</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?= $todayStats['hours_worked'] ?>h</div>
                    <div class="summary-label">Hours Worked</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Content Tabs -->
            <div class="content-tabs">
                <button class="tab-btn active" onclick="switchTab('map')">
                    <i class="fas fa-map"></i> Map View
                </button>
                <button class="tab-btn" onclick="switchTab('available')">
                    <i class="fas fa-list"></i> Available Jobs <span class="badge">5</span>
                </button>
                <button class="tab-btn" onclick="switchTab('scheduled')">
                    <i class="fas fa-calendar"></i> Scheduled <span class="badge">3</span>
                </button>
                <button class="tab-btn" onclick="switchTab('history')">
                    <i class="fas fa-history"></i> History
                </button>
            </div>

            <!-- Map View -->
            <div class="tab-content active" id="map-tab">
                <div class="map-container">
                    <div class="map-controls">
                        <button class="map-control-btn" onclick="toggleHeatMap()">
                            <i class="fas fa-layer-group"></i> Heat Map
                        </button>
                        <button class="map-control-btn" onclick="centerOnLocation()">
                            <i class="fas fa-crosshairs"></i> My Location
                        </button>
                    </div>
                    <div id="map"></div>
                </div>
                
                <div class="jobs-section">
                    <div class="section-header">
                        <h2 class="section-title">Nearby Opportunities</h2>
                        <div class="jobs-filter">
                            <button class="filter-btn active" onclick="filterJobs('all')">All</button>
                            <button class="filter-btn" onclick="filterJobs('urgent')">Urgent</button>
                            <button class="filter-btn" onclick="filterJobs('high-pay')">High Pay</button>
                        </div>
                    </div>
                    
                    <div id="nearbyJobs">
                        <!-- Populado dinamicamente -->
                    </div>
                </div>
            </div>

            <!-- Available Jobs -->
            <div class="tab-content" id="available-tab">
                <div class="jobs-section">
                    <div class="section-header">
                        <h2 class="section-title">Available Jobs</h2>
                        <div class="jobs-filter">
                            <button class="filter-btn active">All Services</button>
                            <button class="filter-btn">Cleaning</button>
                            <button class="filter-btn">Gardening</button>
                            <button class="filter-btn">Handyman</button>
                        </div>
                    </div>
                    
                    <div id="availableJobs">
                        <!-- Job cards will be populated here -->
                    </div>
                </div>
            </div>

            <!-- Scheduled Jobs -->
            <div class="tab-content" id="scheduled-tab">
                <div class="jobs-section">
                    <div class="section-header">
                        <h2 class="section-title">Your Schedule</h2>
                        <button class="btn-primary" onclick="openAvailabilitySettings()">
                            <i class="fas fa-calendar-plus"></i> Update Availability
                        </button>
                    </div>
                    
                    <div id="scheduledJobs">
                        <!-- Scheduled jobs will be populated here -->
                    </div>
                </div>
            </div>

            <!-- History -->
            <div class="tab-content" id="history-tab">
                <div class="jobs-section">
                    <div class="section-header">
                        <h2 class="section-title">Job History</h2>
                        <div class="jobs-filter">
                            <button class="filter-btn active">This Week</button>
                            <button class="filter-btn">This Month</button>
                            <button class="filter-btn">All Time</button>
                        </div>
                    </div>
                    
                    <div id="jobHistory">
                        <!-- Job history will be populated here -->
                    </div>
                </div>
            </div>
        </main>

        <!-- Side Panel -->
        <aside class="side-panel">
            <!-- Performance Stats -->
            <div class="panel-section">
                <h3 class="panel-title">Performance</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $professional['completion_rate'] ?>%</div>
                        <div class="stat-label">Completion Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $professional['response_rate'] ?>%</div>
                        <div class="stat-label">Response Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $professional['on_time_rate'] ?>%</div>
                        <div class="stat-label">On-Time Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $professional['rating'] ?></div>
                        <div class="stat-label">Average Rating</div>
                    </div>
                </div>
            </div>

            <!-- Earnings Overview -->
            <div class="panel-section">
                <h3 class="panel-title">Weekly Earnings</h3>
                <div class="earnings-chart">
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color); margin-bottom: 10px;">
                            $<?= number_format($weeklyStats['earnings'], 2) ?>
                        </div>
                        <div style="color: #6b7280;">
                            <?= $weeklyStats['target_progress'] ?>% of weekly target
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Schedule -->
            <div class="panel-section">
                <h3 class="panel-title">Next 24 Hours</h3>
                <div class="upcoming-schedule">
                    <div class="schedule-item">
                        <div>
                            <div class="schedule-time">10:00 AM</div>
                            <div class="schedule-service">House Cleaning</div>
                        </div>
                        <span style="color: var(--success-color);">$85</span>
                    </div>
                    <div class="schedule-item">
                        <div>
                            <div class="schedule-time">2:30 PM</div>
                            <div class="schedule-service">Garden Maintenance</div>
                        </div>
                        <span style="color: var(--success-color);">$120</span>
                    </div>
                    <div class="schedule-item">
                        <div>
                            <div class="schedule-time">4:00 PM</div>
                            <div class="schedule-service">Office Cleaning</div>
                        </div>
                        <span style="color: var(--success-color);">$95</span>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <div class="panel-section">
                <h3 class="panel-title">Recent Notifications</h3>
                <div class="notifications-panel">
                    <div class="notification-item">
                        <div class="notification-icon success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Payment Received</div>
                            <div class="notification-text">$85 for House Cleaning job</div>
                            <div class="notification-time">2 hours ago</div>
                        </div>
                    </div>
                    <div class="notification-item">
                        <div class="notification-icon info">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">New Review</div>
                            <div class="notification-text">Sarah gave you 5 stars!</div>
                            <div class="notification-time">4 hours ago</div>
                        </div>
                    </div>
                    <div class="notification-item">
                        <div class="notification-icon info">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Schedule Update</div>
                            <div class="notification-text">New job tomorrow at 10 AM</div>
                            <div class="notification-time">6 hours ago</div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        class ProfessionalDashboard {
            constructor() {
                this.map = null;
                this.currentPosition = null;
                this.availableJobs = [];
                this.isOnline = true;
                this.init();
            }

            init() {
                this.initMap();
                this.loadAvailableJobs();
                this.startLocationTracking();
                this.bindEvents();
                this.loadNotifications();
            }

            initMap() {
                // Initialize Leaflet map
                this.map = L.map('map').setView([-33.8688, 151.2093], 13); // Sydney coordinates
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(this.map);

                // Add current location marker
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition((position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        this.currentPosition = [lat, lng];
                        this.map.setView([lat, lng], 13);
                        
                        L.marker([lat, lng], {
                            icon: L.divIcon({
                                className: 'current-location-marker',
                                html: '<div style="background: #667eea; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3);"></div>',
                                iconSize: [20, 20]
                            })
                        }).addTo(this.map).bindPopup('Your Location');
                        
                        this.loadNearbyJobs(lat, lng);
                    });
                }
            }

            loadNearbyJobs(lat, lng) {
                // Simulate nearby jobs
                const sampleJobs = [
                    {
                        id: 'job_001',
                        type: 'cleaning',
                        title: 'House Cleaning',
                        location: { lat: lat + 0.01, lng: lng + 0.01 },
                        address: 'Bondi Beach, NSW',
                        price: 85,
                        duration: '2-3 hours',
                        distance: '1.2 km',
                        customer: { name: 'Sarah M.', rating: 4.8 },
                        tags: ['3 Bedrooms', '2 Bathrooms', 'Pet Friendly'],
                        urgent: false
                    },
                    {
                        id: 'job_002',
                        type: 'gardening',
                        title: 'Garden Maintenance',
                        location: { lat: lat - 0.015, lng: lng + 0.02 },
                        address: 'Paddington, NSW',
                        price: 120,
                        duration: '3-4 hours',
                        distance: '2.1 km',
                        customer: { name: 'Mike T.', rating: 4.9 },
                        tags: ['Large Garden', 'Hedge Trimming', 'Lawn Mowing'],
                        urgent: true
                    }
                ];

                // Add job markers to map
                sampleJobs.forEach(job => {
                    const markerColor = job.urgent ? '#f59e0b' : '#10b981';
                    
                    L.marker([job.location.lat, job.location.lng], {
                        icon: L.divIcon({
                            className: 'job-marker',
                            html: `<div style="background: ${markerColor}; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">$${job.price}</div>`,
                            iconSize: [30, 30]
                        })
                    }).addTo(this.map).bindPopup(`
                        <div style="text-align: center;">
                            <h4>${job.title}</h4>
                            <p>${job.address}</p>
                            <p><strong>$${job.price}</strong> • ${job.duration}</p>
                            <button onclick="dashboard.viewJobDetails('${job.id}')" style="background: #667eea; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">View Details</button>
                        </div>
                    `);
                });

                this.availableJobs = sampleJobs;
                this.renderJobCards();
            }

            renderJobCards() {
                const container = document.getElementById('nearbyJobs');
                container.innerHTML = '';

                this.availableJobs.forEach(job => {
                    const jobCard = this.createJobCard(job);
                    container.appendChild(jobCard);
                });
            }

            createJobCard(job) {
                const card = document.createElement('div');
                card.className = `job-card ${job.urgent ? 'urgent' : ''}`;
                card.innerHTML = `
                    <div class="job-header">
                        <div>
                            <div class="job-price">$${job.price}</div>
                            <div class="job-duration">${job.duration}</div>
                        </div>
                        <div class="job-distance">
                            <i class="fas fa-route"></i>
                            <span>${job.distance} • 8 min</span>
                        </div>
                    </div>

                    <div class="job-details">
                        <h3 class="job-title">${job.title}</h3>
                        <div class="job-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>${job.address}</span>
                        </div>
                        <div class="job-time">
                            <i class="fas fa-clock"></i>
                            <span>Tomorrow, 10:00 AM - 1:00 PM</span>
                        </div>
                    </div>

                    <div class="customer-info">
                        <img src="/assets/images/default-avatar.jpg" alt="Customer" class="customer-avatar">
                        <div class="customer-details">
                            <div class="customer-name">${job.customer.name}</div>
                            <div class="customer-rating">
                                <i class="fas fa-star"></i>
                                <span>${job.customer.rating}</span>
                            </div>
                        </div>
                    </div>

                    <div class="job-tags">
                        ${job.tags.map(tag => `<span class="job-tag ${job.urgent ? 'urgent' : ''}">${tag}</span>`).join('')}
                        ${job.urgent ? '<span class="job-tag urgent">URGENT</span>' : ''}
                    </div>

                    <div class="job-actions">
                        <button class="btn-decline" onclick="dashboard.declineJob('${job.id}')">Decline</button>
                        <button class="btn-accept" onclick="dashboard.acceptJob('${job.id}')">Accept Job</button>
                    </div>

                    ${job.urgent ? '<div class="response-timer"><div class="timer-bar"></div></div>' : ''}
                `;

                return card;
            }

            loadAvailableJobs() {
                // Load available jobs from API
                // Simulated for now
            }

            startLocationTracking() {
                if (navigator.geolocation) {
                    navigator.geolocation.watchPosition(
                        (position) => {
                            this.updateLocation(position.coords.latitude, position.coords.longitude);
                        },
                        (error) => console.error('Location tracking error:', error),
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
                    );
                }
            }

            updateLocation(lat, lng) {
                if (this.isOnline) {
                    // Send location update to server
                    fetch('/api/professional/location.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ latitude: lat, longitude: lng })
                    });
                }
            }

            bindEvents() {
                // Tab switching
                window.switchTab = (tabName) => {
                    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                    
                    event.target.classList.add('active');
                    document.getElementById(`${tabName}-tab`).classList.add('active');
                };

                // Availability toggle
                window.toggleAvailability = () => {
                    const toggle = document.getElementById('availabilityToggle');
                    const statusText = document.getElementById('statusText');
                    
                    this.isOnline = !this.isOnline;
                    toggle.classList.toggle('active', this.isOnline);
                    
                    if (this.isOnline) {
                        statusText.textContent = 'Online & Available';
                        this.startLocationTracking();
                    } else {
                        statusText.textContent = 'Offline';
                    }
                };

                // Job actions
                window.dashboard = this;
            }

            acceptJob(jobId) {
                if (confirm('Accept this job?')) {
                    fetch('/api/professional/accept-job.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ job_id: jobId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Job accepted! Check your schedule.');
                            this.removeJobFromList(jobId);
                        }
                    });
                }
            }

            declineJob(jobId) {
                this.removeJobFromList(jobId);
            }

            removeJobFromList(jobId) {
                this.availableJobs = this.availableJobs.filter(job => job.id !== jobId);
                this.renderJobCards();
            }

            viewJobDetails(jobId) {
                const job = this.availableJobs.find(j => j.id === jobId);
                if (job) {
                    alert(`Job Details:\n${job.title}\n${job.address}\n$${job.price} • ${job.duration}`);
                }
            }

            loadNotifications() {
                // Load recent notifications
                // Implemented in the HTML template
            }
        }

        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', () => {
            window.professionalDashboard = new ProfessionalDashboard();
        });
    </script>
</body>
</html>
