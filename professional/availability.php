<?php
/**
 * Sistema de Gestão de Disponibilidade - Blue Project V2
 * Interface para profissionais gerenciarem seus horários
 */

session_start();

// Simula dados do profissional (substituir por dados reais)
$professional = [
    'id' => 'PROF_12345',
    'name' => 'Sarah Mitchell',
    'services' => ['cleaning', 'gardening'],
    'service_radius' => 25,
    'current_availability' => 'online'
];

// Configurações de disponibilidade
$availabilityConfig = [
    'time_slots' => 30, // minutos
    'advance_booking' => 48, // horas mínimas
    'max_advance_booking' => 720, // 30 dias
    'break_duration' => 30, // minutos entre jobs
    'emergency_slots' => true,
    'surge_multiplier' => 1.4
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Management - Blue Services</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/blue.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        }
        
        .availability-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-info h1 {
            margin: 0 0 10px;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .header-info p {
            margin: 0;
            opacity: 0.9;
        }
        
        .quick-status {
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
            width: 60px;
            height: 30px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            cursor: pointer;
            position: relative;
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
        
        .earnings-potential {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
        }
        
        .earnings-amount {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .earnings-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 25px;
        }
        
        .calendar-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .calendar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .calendar-controls {
            display: flex;
            gap: 10px;
        }
        
        .calendar-nav {
            background: #f3f4f6;
            border: none;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .calendar-nav:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .view-toggle {
            display: flex;
            background: #f3f4f6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .view-btn {
            background: none;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-bottom: 25px;
        }
        
        .calendar-day-header {
            background: #f8fafc;
            padding: 15px 5px;
            text-align: center;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .calendar-day {
            background: #f8fafc;
            border: 2px solid transparent;
            min-height: 120px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .calendar-day:hover {
            border-color: var(--primary-color);
        }
        
        .calendar-day.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
        }
        
        .calendar-day.available {
            background: rgba(16, 185, 129, 0.05);
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .calendar-day.busy {
            background: rgba(245, 158, 11, 0.05);
            border-color: rgba(245, 158, 11, 0.3);
        }
        
        .calendar-day.blocked {
            background: rgba(239, 68, 68, 0.05);
            border-color: rgba(239, 68, 68, 0.3);
            cursor: not-allowed;
        }
        
        .day-number {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .day-status {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .day-status.available {
            color: var(--success-color);
        }
        
        .day-status.busy {
            color: var(--warning-color);
        }
        
        .day-status.blocked {
            color: var(--danger-color);
        }
        
        .day-earnings {
            font-size: 0.8rem;
            color: var(--success-color);
            font-weight: 600;
        }
        
        .day-jobs {
            font-size: 0.7rem;
            color: #6b7280;
        }
        
        .surge-indicator {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--warning-color);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .time-slots-section {
            margin-top: 30px;
        }
        
        .time-slots-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .selected-date {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .time-slot {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .time-slot:hover {
            border-color: var(--primary-color);
        }
        
        .time-slot.available {
            border-color: var(--success-color);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .time-slot.busy {
            border-color: var(--warning-color);
            background: rgba(245, 158, 11, 0.05);
            cursor: not-allowed;
        }
        
        .time-slot.blocked {
            border-color: var(--danger-color);
            background: rgba(239, 68, 68, 0.05);
            cursor: not-allowed;
        }
        
        .slot-time {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .slot-status {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .slot-rate {
            font-size: 0.8rem;
            color: var(--success-color);
            font-weight: 600;
        }
        
        .side-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .panel-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .panel-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
        }
        
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .action-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .action-btn.secondary {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .action-btn.secondary:hover {
            background: #e5e7eb;
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
        
        .zone-selector {
            margin-bottom: 20px;
        }
        
        .zone-option {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .zone-option:hover,
        .zone-option.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
        }
        
        .zone-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .zone-details {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 5px;
        }
        
        .preferences-section {
            margin-bottom: 20px;
        }
        
        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .preference-item:last-child {
            border-bottom: none;
        }
        
        .preference-label {
            font-weight: 500;
            color: #1f2937;
        }
        
        .preference-input {
            width: 80px;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
        }
        
        .preference-toggle {
            width: 40px;
            height: 20px;
            background: #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            position: relative;
            transition: background 0.3s ease;
        }
        
        .preference-toggle.active {
            background: var(--success-color);
        }
        
        .preference-toggle::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }
        
        .preference-toggle.active::after {
            transform: translateX(20px);
        }
        
        .notification-panel {
            background: #fef3c7;
            border-left: 4px solid var(--warning-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .notification-title {
            font-weight: 600;
            color: #92400e;
            margin-bottom: 5px;
        }
        
        .notification-text {
            font-size: 0.9rem;
            color: #92400e;
        }
        
        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .side-panel {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .availability-container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .quick-status {
                flex-direction: column;
                gap: 15px;
            }
            
            .calendar-grid {
                grid-template-columns: 1fr;
            }
            
            .time-slots-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="availability-container">
        <!-- Header -->
        <div class="header">
            <div class="header-info">
                <h1>Availability Management</h1>
                <p>Manage your schedule and maximize your earnings potential</p>
            </div>
            
            <div class="quick-status">
                <div class="status-toggle">
                    <div class="toggle-switch active" id="availabilityToggle" onclick="toggleAvailability()"></div>
                    <span>Available Now</span>
                </div>
                
                <div class="earnings-potential">
                    <div class="earnings-amount">$850 - $1,200</div>
                    <div class="earnings-label">Weekly Potential</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Calendar Section -->
            <div class="calendar-section">
                <!-- Calendar Header -->
                <div class="calendar-header">
                    <h2 class="calendar-title">August 2025</h2>
                    
                    <div class="calendar-controls">
                        <button class="calendar-nav" onclick="previousMonth()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="calendar-nav" onclick="nextMonth()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        
                        <div class="view-toggle">
                            <button class="view-btn active" onclick="setView('month')">Month</button>
                            <button class="view-btn" onclick="setView('week')">Week</button>
                            <button class="view-btn" onclick="setView('day')">Day</button>
                        </div>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="calendar-grid" id="calendarGrid">
                    <!-- Day headers -->
                    <div class="calendar-day-header">Sun</div>
                    <div class="calendar-day-header">Mon</div>
                    <div class="calendar-day-header">Tue</div>
                    <div class="calendar-day-header">Wed</div>
                    <div class="calendar-day-header">Thu</div>
                    <div class="calendar-day-header">Fri</div>
                    <div class="calendar-day-header">Sat</div>
                    
                    <!-- Days will be populated by JavaScript -->
                </div>

                <!-- Time Slots Section -->
                <div class="time-slots-section">
                    <div class="time-slots-header">
                        <h3 class="selected-date">Select a date to manage time slots</h3>
                        <button class="action-btn secondary" onclick="bulkUpdateSlots()">
                            <i class="fas fa-edit"></i>
                            Bulk Edit
                        </button>
                    </div>
                    
                    <div class="time-slots-grid" id="timeSlotsGrid">
                        <!-- Time slots will be populated when a date is selected -->
                    </div>
                </div>
            </div>

            <!-- Side Panel -->
            <div class="side-panel">
                <!-- Quick Actions -->
                <div class="panel-card">
                    <h3 class="panel-title">Quick Actions</h3>
                    
                    <div class="quick-actions">
                        <button class="action-btn" onclick="setAvailableNow()">
                            <i class="fas fa-bolt"></i>
                            Available Now
                        </button>
                        <button class="action-btn" onclick="copyLastWeek()">
                            <i class="fas fa-copy"></i>
                            Copy Last Week
                        </button>
                        <button class="action-btn" onclick="blockDay()">
                            <i class="fas fa-ban"></i>
                            Block Day Off
                        </button>
                        <button class="action-btn secondary" onclick="clearSchedule()">
                            <i class="fas fa-trash"></i>
                            Clear Schedule
                        </button>
                    </div>
                </div>

                <!-- Availability Stats -->
                <div class="panel-card">
                    <h3 class="panel-title">This Week</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">28h</div>
                            <div class="stat-label">Available Hours</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">5</div>
                            <div class="stat-label">Jobs Booked</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">85%</div>
                            <div class="stat-label">Utilization</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">$680</div>
                            <div class="stat-label">Expected Earnings</div>
                        </div>
                    </div>
                </div>

                <!-- Service Zones -->
                <div class="panel-card">
                    <h3 class="panel-title">Service Areas</h3>
                    
                    <div class="zone-selector">
                        <div class="zone-option selected">
                            <div class="zone-name">Primary Zone</div>
                            <div class="zone-details">0-10km • No travel fee • High demand</div>
                        </div>
                        <div class="zone-option">
                            <div class="zone-name">Secondary Zone</div>
                            <div class="zone-details">10-25km • $15 travel fee • Medium demand</div>
                        </div>
                        <div class="zone-option">
                            <div class="zone-name">Extended Zone</div>
                            <div class="zone-details">25-50km • $30 travel fee • Low demand</div>
                        </div>
                    </div>
                </div>

                <!-- Preferences -->
                <div class="panel-card">
                    <h3 class="panel-title">Preferences</h3>
                    
                    <div class="preferences-section">
                        <div class="preference-item">
                            <span class="preference-label">Max jobs per day</span>
                            <input type="number" class="preference-input" value="3" min="1" max="8">
                        </div>
                        <div class="preference-item">
                            <span class="preference-label">Minimum notice (hours)</span>
                            <input type="number" class="preference-input" value="24" min="2" max="48">
                        </div>
                        <div class="preference-item">
                            <span class="preference-label">Emergency jobs</span>
                            <div class="preference-toggle active" onclick="togglePreference(this)"></div>
                        </div>
                        <div class="preference-item">
                            <span class="preference-label">Weekend work</span>
                            <div class="preference-toggle active" onclick="togglePreference(this)"></div>
                        </div>
                        <div class="preference-item">
                            <span class="preference-label">Auto-accept familiar customers</span>
                            <div class="preference-toggle" onclick="togglePreference(this)"></div>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="panel-card">
                    <div class="notification-panel">
                        <div class="notification-title">High Demand Alert</div>
                        <div class="notification-text">Surge pricing is active in your area. Consider extending your hours to maximize earnings.</div>
                    </div>
                    
                    <div class="notification-panel" style="background: #d1fae5; border-color: var(--success-color);">
                        <div class="notification-title" style="color: #065f46;">Optimization Tip</div>
                        <div class="notification-text" style="color: #065f46;">You have 3 available slots tomorrow that could earn you an extra $180.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        class AvailabilityManager {
            constructor() {
                this.currentMonth = 7; // August (0-indexed)
                this.currentYear = 2025;
                this.selectedDate = null;
                this.availabilityData = {};
                this.init();
            }

            init() {
                this.generateCalendar();
                this.loadAvailabilityData();
                this.bindEvents();
            }

            generateCalendar() {
                const calendar = document.getElementById('calendarGrid');
                const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
                const firstDay = new Date(this.currentYear, this.currentMonth, 1).getDay();
                
                // Clear existing days (keep headers)
                const existingDays = calendar.querySelectorAll('.calendar-day');
                existingDays.forEach(day => day.remove());
                
                // Add empty cells for days before the first day of the month
                for (let i = 0; i < firstDay; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.className = 'calendar-day';
                    calendar.appendChild(emptyDay);
                }
                
                // Add days of the month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayElement = this.createDayElement(day);
                    calendar.appendChild(dayElement);
                }
            }

            createDayElement(day) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                dayElement.onclick = () => this.selectDate(day);
                
                const dayData = this.getAvailabilityForDay(day);
                
                dayElement.innerHTML = `
                    <div class="day-number">${day}</div>
                    <div class="day-status ${dayData.status}">${dayData.statusText}</div>
                    ${dayData.earnings ? `<div class="day-earnings">$${dayData.earnings}</div>` : ''}
                    ${dayData.jobs ? `<div class="day-jobs">${dayData.jobs} jobs</div>` : ''}
                    ${dayData.surge ? '<div class="surge-indicator">SURGE</div>' : ''}
                `;
                
                dayElement.classList.add(dayData.status);
                
                return dayElement;
            }

            getAvailabilityForDay(day) {
                // Simulate availability data
                const isWeekend = new Date(this.currentYear, this.currentMonth, day).getDay() % 6 === 0;
                const random = Math.random();
                
                if (random < 0.3) {
                    return {
                        status: 'available',
                        statusText: 'Available',
                        earnings: Math.floor(Math.random() * 200) + 100,
                        jobs: Math.floor(Math.random() * 3) + 1,
                        surge: random < 0.1
                    };
                } else if (random < 0.6) {
                    return {
                        status: 'busy',
                        statusText: 'Partially Booked',
                        earnings: Math.floor(Math.random() * 150) + 50,
                        jobs: Math.floor(Math.random() * 2) + 1,
                        surge: false
                    };
                } else if (random < 0.8) {
                    return {
                        status: 'blocked',
                        statusText: 'Unavailable',
                        earnings: null,
                        jobs: null,
                        surge: false
                    };
                } else {
                    return {
                        status: 'available',
                        statusText: 'Open',
                        earnings: null,
                        jobs: null,
                        surge: isWeekend && random < 0.95
                    };
                }
            }

            selectDate(day) {
                // Remove previous selection
                document.querySelectorAll('.calendar-day.selected').forEach(el => {
                    el.classList.remove('selected');
                });
                
                // Select new date
                event.target.closest('.calendar-day').classList.add('selected');
                this.selectedDate = day;
                
                // Update selected date display
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'];
                const selectedDateElement = document.querySelector('.selected-date');
                selectedDateElement.textContent = `${monthNames[this.currentMonth]} ${day}, ${this.currentYear}`;
                
                // Load time slots for selected date
                this.loadTimeSlotsForDate(day);
            }

            loadTimeSlotsForDate(day) {
                const timeSlotsGrid = document.getElementById('timeSlotsGrid');
                timeSlotsGrid.innerHTML = '';
                
                // Generate time slots from 8 AM to 8 PM
                const startHour = 8;
                const endHour = 20;
                const slotDuration = <?= $availabilityConfig['time_slots'] ?>; // minutes
                
                for (let hour = startHour; hour < endHour; hour++) {
                    for (let minute = 0; minute < 60; minute += slotDuration) {
                        const timeSlot = this.createTimeSlotElement(hour, minute, day);
                        timeSlotsGrid.appendChild(timeSlot);
                    }
                }
            }

            createTimeSlotElement(hour, minute, day) {
                const timeSlot = document.createElement('div');
                timeSlot.className = 'time-slot';
                
                const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                const endMinute = minute + <?= $availabilityConfig['time_slots'] ?>;
                const endHour = endMinute >= 60 ? hour + 1 : hour;
                const adjustedEndMinute = endMinute >= 60 ? endMinute - 60 : endMinute;
                const endTimeString = `${endHour.toString().padStart(2, '0')}:${adjustedEndMinute.toString().padStart(2, '0')}`;
                
                // Simulate slot availability
                const random = Math.random();
                let status, statusText, rate;
                
                if (random < 0.4) {
                    status = 'available';
                    statusText = 'Available';
                    rate = 25 + (random < 0.1 ? 10 : 0); // Surge pricing
                } else if (random < 0.7) {
                    status = 'busy';
                    statusText = 'Booked';
                    rate = null;
                } else {
                    status = 'blocked';
                    statusText = 'Blocked';
                    rate = null;
                }
                
                timeSlot.classList.add(status);
                timeSlot.onclick = () => this.toggleTimeSlot(timeSlot, timeString);
                
                timeSlot.innerHTML = `
                    <div class="slot-time">${timeString} - ${endTimeString}</div>
                    <div class="slot-status">${statusText}</div>
                    ${rate ? `<div class="slot-rate">$${rate}/hour</div>` : ''}
                `;
                
                return timeSlot;
            }

            toggleTimeSlot(element, timeString) {
                if (element.classList.contains('busy')) {
                    return; // Can't modify booked slots
                }
                
                if (element.classList.contains('available')) {
                    element.classList.remove('available');
                    element.classList.add('blocked');
                    element.querySelector('.slot-status').textContent = 'Blocked';
                    element.querySelector('.slot-rate')?.remove();
                } else {
                    element.classList.remove('blocked');
                    element.classList.add('available');
                    element.querySelector('.slot-status').textContent = 'Available';
                    if (!element.querySelector('.slot-rate')) {
                        element.innerHTML += '<div class="slot-rate">$25/hour</div>';
                    }
                }
                
                this.saveAvailabilityChange();
            }

            loadAvailabilityData() {
                // Load availability data from server
                // For now, simulate with local data
            }

            saveAvailabilityChange() {
                // Save changes to server
                console.log('Availability updated');
            }

            bindEvents() {
                // Global functions for navigation and actions
                window.previousMonth = () => {
                    if (this.currentMonth === 0) {
                        this.currentMonth = 11;
                        this.currentYear--;
                    } else {
                        this.currentMonth--;
                    }
                    this.generateCalendar();
                };

                window.nextMonth = () => {
                    if (this.currentMonth === 11) {
                        this.currentMonth = 0;
                        this.currentYear++;
                    } else {
                        this.currentMonth++;
                    }
                    this.generateCalendar();
                };

                window.setView = (view) => {
                    document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
                    event.target.classList.add('active');
                    // Implement view changes
                };

                window.toggleAvailability = () => {
                    const toggle = document.getElementById('availabilityToggle');
                    toggle.classList.toggle('active');
                };

                window.togglePreference = (element) => {
                    element.classList.toggle('active');
                };

                window.setAvailableNow = () => {
                    if (confirm('Set yourself as available for immediate bookings?')) {
                        // Implement immediate availability
                        alert('You are now available for immediate bookings!');
                    }
                };

                window.copyLastWeek = () => {
                    if (confirm('Copy last week\'s availability to this week?')) {
                        // Implement copy functionality
                        alert('Last week\'s schedule copied successfully!');
                        this.generateCalendar();
                    }
                };

                window.blockDay = () => {
                    if (this.selectedDate) {
                        if (confirm(`Block ${this.selectedDate} as unavailable?`)) {
                            // Implement block day functionality
                            alert('Day blocked successfully!');
                            this.generateCalendar();
                        }
                    } else {
                        alert('Please select a date first.');
                    }
                };

                window.clearSchedule = () => {
                    if (confirm('Clear all availability for the selected period? This cannot be undone.')) {
                        // Implement clear schedule
                        alert('Schedule cleared!');
                        this.generateCalendar();
                    }
                };

                window.bulkUpdateSlots = () => {
                    if (this.selectedDate) {
                        // Open bulk edit modal (implement modal)
                        alert('Bulk edit feature coming soon!');
                    } else {
                        alert('Please select a date first.');
                    }
                };
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', () => {
            new AvailabilityManager();
        });
    </script>
</body>
</html>
