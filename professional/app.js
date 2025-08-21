/**
 * Professional Mobile App - PWA Advanced
 * Blue Cleaning Services - Professional Interface
 */

class ProfessionalApp {
    constructor() {
        this.currentUser = null;
        this.currentBooking = null;
        this.locationWatcher = null;
        this.websocket = null;
        this.notificationPermission = false;
        this.isOnline = navigator.onLine;
        this.offlineQueue = [];
        this.init();
    }

    async init() {
        console.log('📱 Initializing Professional App...');
        
        await this.checkAuthentication();
        this.setupEventListeners();
        this.initializeWebSocket();
        this.setupLocationTracking();
        this.requestNotificationPermission();
        this.initializeServiceWorker();
        this.loadOfflineData();
        
        console.log('📱 Professional App initialized successfully');
    }

    // Authentication & Profile Management
    async checkAuthentication() {
        const token = localStorage.getItem('professional_token');
        if (!token) {
            this.redirectToLogin();
            return;
        }

        try {
            const response = await fetch('/api/professional/profile.php', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            if (response.ok) {
                this.currentUser = await response.json();
                this.updateProfileUI();
            } else {
                this.redirectToLogin();
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            this.redirectToLogin();
        }
    }

    // Real-time Location Tracking
    setupLocationTracking() {
        if (!navigator.geolocation) {
            console.warn('Geolocation not supported');
            return;
        }

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 30000
        };

        this.locationWatcher = navigator.geolocation.watchPosition(
            (position) => this.updateLocation(position),
            (error) => console.error('Location error:', error),
            options
        );
    }

    async updateLocation(position) {
        const { latitude, longitude, accuracy } = position.coords;
        
        const locationData = {
            latitude,
            longitude,
            accuracy,
            timestamp: Date.now(),
            booking_id: this.currentBooking?.id
        };

        if (this.isOnline) {
            try {
                await fetch('/api/professional/location.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('professional_token')}`
                    },
                    body: JSON.stringify(locationData)
                });

                // Send real-time update via WebSocket
                if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                    this.websocket.send(JSON.stringify({
                        type: 'location_update',
                        ...locationData
                    }));
                }
            } catch (error) {
                console.error('Failed to update location:', error);
                this.addToOfflineQueue('location_update', locationData);
            }
        } else {
            this.addToOfflineQueue('location_update', locationData);
        }
    }

    // Job Management
    async getAvailableJobs() {
        try {
            const response = await fetch('/api/professional/available-jobs.php', {
                headers: { 'Authorization': `Bearer ${localStorage.getItem('professional_token')}` }
            });
            
            const jobs = await response.json();
            this.renderAvailableJobs(jobs);
            
            // Cache for offline access
            localStorage.setItem('cached_jobs', JSON.stringify(jobs));
        } catch (error) {
            console.error('Failed to fetch jobs:', error);
            // Load from cache if offline
            const cachedJobs = localStorage.getItem('cached_jobs');
            if (cachedJobs) {
                this.renderAvailableJobs(JSON.parse(cachedJobs));
            }
        }
    }

    async acceptJob(jobId) {
        const acceptData = { job_id: jobId };

        if (this.isOnline) {
            try {
                const response = await fetch('/api/professional/accept-job.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('professional_token')}`
                    },
                    body: JSON.stringify(acceptData)
                });

                if (response.ok) {
                    const booking = await response.json();
                    this.currentBooking = booking;
                    this.showJobDetails(booking);
                    this.sendNotification('Job Accepted', 'You have accepted a new cleaning job');
                    
                    // Join WebSocket room for this booking
                    if (this.websocket) {
                        this.websocket.send(JSON.stringify({
                            type: 'join_room',
                            room_id: booking.id
                        }));
                    }
                }
            } catch (error) {
                console.error('Failed to accept job:', error);
                this.addToOfflineQueue('accept_job', acceptData);
                this.showOfflineMessage('Job acceptance queued for when you\'re back online');
            }
        } else {
            this.addToOfflineQueue('accept_job', acceptData);
            this.showOfflineMessage('Job acceptance queued for when you\'re back online');
        }
    }

    // Job Progress Tracking
    async updateJobStatus(status, notes = '') {
        if (!this.currentBooking) return;

        const updateData = {
            booking_id: this.currentBooking.id,
            status,
            notes,
            timestamp: Date.now()
        };

        if (this.isOnline) {
            try {
                const response = await fetch('/api/professional/update-job.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('professional_token')}`
                    },
                    body: JSON.stringify(updateData)
                });

                if (response.ok) {
                    this.currentBooking.status = status;
                    this.updateJobUI();
                    
                    // Send real-time update to customer
                    if (this.websocket) {
                        this.websocket.send(JSON.stringify({
                            type: 'job_update',
                            ...updateData,
                            professional_id: this.currentUser.id
                        }));
                    }
                }
            } catch (error) {
                console.error('Failed to update job status:', error);
                this.addToOfflineQueue('job_update', updateData);
            }
        } else {
            this.addToOfflineQueue('job_update', updateData);
            this.currentBooking.status = status; // Update locally
            this.updateJobUI();
        }
    }

    // Chat System
    initializeChat(bookingId) {
        const chatContainer = document.getElementById('chat-container');
        chatContainer.innerHTML = `
            <div id="chat-messages" class="chat-messages">
                <div class="loading">Loading chat history...</div>
            </div>
            <div class="chat-input">
                <input type="text" id="message-input" placeholder="Type a message..." />
                <button onclick="app.sendMessage()" class="send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
                <button onclick="app.sendQuickUpdate()" class="quick-btn">
                    <i class="fas fa-bolt"></i>
                </button>
            </div>
        `;

        this.loadChatHistory(bookingId);
        
        // Setup input event listeners
        const input = document.getElementById('message-input');
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });

        // Typing indicators
        let typingTimer;
        input.addEventListener('input', () => {
            clearTimeout(typingTimer);
            this.sendTypingIndicator(true);
            
            typingTimer = setTimeout(() => {
                this.sendTypingIndicator(false);
            }, 1000);
        });
    }

    async loadChatHistory(bookingId) {
        try {
            const response = await fetch(`/api/chat/history.php?booking_id=${bookingId}`, {
                headers: { 'Authorization': `Bearer ${localStorage.getItem('professional_token')}` }
            });
            
            const messages = await response.json();
            this.renderChatMessages(messages);
            
            // Cache messages for offline
            localStorage.setItem(`chat_${bookingId}`, JSON.stringify(messages));
        } catch (error) {
            console.error('Failed to load chat history:', error);
            
            // Load from cache
            const cachedMessages = localStorage.getItem(`chat_${bookingId}`);
            if (cachedMessages) {
                this.renderChatMessages(JSON.parse(cachedMessages));
            }
        }
    }

    async sendMessage() {
        const input = document.getElementById('message-input');
        const message = input.value.trim();
        
        if (!message || !this.currentBooking) return;

        const messageData = {
            booking_id: this.currentBooking.id,
            message,
            sender_type: 'professional',
            timestamp: Date.now()
        };

        // Add message to UI immediately (optimistic update)
        this.addMessageToChat(message, true, messageData.timestamp);
        input.value = '';

        if (this.isOnline) {
            try {
                const response = await fetch('/api/chat/send.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('professional_token')}`
                    },
                    body: JSON.stringify(messageData)
                });

                if (response.ok) {
                    // Send via WebSocket for real-time delivery
                    if (this.websocket) {
                        this.websocket.send(JSON.stringify({
                            type: 'chat_message',
                            ...messageData,
                            sender_id: this.currentUser.id
                        }));
                    }
                } else {
                    throw new Error('Failed to send message');
                }
            } catch (error) {
                console.error('Failed to send message:', error);
                this.addToOfflineQueue('chat_message', messageData);
                this.markMessageAsPending(messageData.timestamp);
            }
        } else {
            this.addToOfflineQueue('chat_message', messageData);
            this.markMessageAsPending(messageData.timestamp);
        }
    }

    sendQuickUpdate() {
        const updates = [
            "I'm on my way to your location 🚗",
            "I've arrived and starting the cleaning 🏠",
            "Cleaning in progress - halfway done 🧹",
            "Cleaning completed! Please check and confirm ✅",
            "I've finished and locked up securely 🔐"
        ];

        this.showQuickUpdateModal(updates);
    }

    showQuickUpdateModal(updates) {
        const modal = document.createElement('div');
        modal.className = 'quick-update-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Send Quick Update</h3>
                    <button onclick="app.closeModal()" class="close-btn">×</button>
                </div>
                <div class="quick-updates">
                    ${updates.map((update, i) => 
                        `<button class="quick-update-btn" onclick="app.sendQuickMessage('${update}'); app.closeModal()">
                            ${update}
                        </button>`
                    ).join('')}
                </div>
                <button onclick="app.closeModal()" class="cancel-btn">Cancel</button>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Add backdrop click to close
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal();
            }
        });
    }

    async sendQuickMessage(message) {
        const input = document.getElementById('message-input');
        input.value = message;
        await this.sendMessage();
    }

    sendTypingIndicator(isTyping) {
        if (this.websocket && this.currentBooking) {
            this.websocket.send(JSON.stringify({
                type: 'typing_indicator',
                booking_id: this.currentBooking.id,
                is_typing: isTyping
            }));
        }
    }

    // Push Notifications
    async requestNotificationPermission() {
        if ('Notification' in window) {
            const permission = await Notification.requestPermission();
            this.notificationPermission = permission === 'granted';
            
            if (this.notificationPermission) {
                console.log('✅ Notification permission granted');
            }
        }
    }

    sendNotification(title, body, options = {}) {
        if (this.notificationPermission && document.hidden) {
            const notification = new Notification(title, {
                body,
                icon: '/assets/icons/icon-192x192.png',
                badge: '/assets/icons/badge-72x72.png',
                tag: 'blue-cleaning',
                requireInteraction: true,
                ...options
            });

            notification.onclick = () => {
                window.focus();
                notification.close();
            };

            // Auto close after 10 seconds
            setTimeout(() => notification.close(), 10000);
        }
    }

    // WebSocket Connection
    initializeWebSocket() {
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${wsProtocol}//${window.location.host}/ws/professional`;
        
        console.log('🔌 Connecting to WebSocket:', wsUrl);
        
        this.websocket = new WebSocket(wsUrl);

        this.websocket.onopen = () => {
            console.log('📡 WebSocket connected');
            
            // Authenticate WebSocket connection
            this.websocket.send(JSON.stringify({
                type: 'authenticate',
                token: localStorage.getItem('professional_token'),
                user_type: 'professional'
            }));
        };

        this.websocket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleWebSocketMessage(data);
            } catch (error) {
                console.error('Failed to parse WebSocket message:', error);
            }
        };

        this.websocket.onclose = (event) => {
            console.log('📡 WebSocket disconnected:', event.code, event.reason);
            
            // Attempt reconnection after delay
            setTimeout(() => {
                if (this.isOnline) {
                    console.log('🔄 Attempting WebSocket reconnection...');
                    this.initializeWebSocket();
                }
            }, 5000);
        };

        this.websocket.onerror = (error) => {
            console.error('📡 WebSocket error:', error);
        };

        // Send periodic ping to keep connection alive
        setInterval(() => {
            if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                this.websocket.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000);
    }

    handleWebSocketMessage(data) {
        console.log('📨 WebSocket message received:', data.type);
        
        switch (data.type) {
            case 'authenticated':
                console.log('✅ WebSocket authenticated');
                break;
                
            case 'new_job':
                this.handleNewJobNotification(data);
                break;
                
            case 'job_cancelled':
                this.handleJobCancellation(data);
                break;
                
            case 'chat_message':
                if (data.sender_id !== this.currentUser.id) {
                    this.handleIncomingMessage(data);
                }
                break;
                
            case 'typing_indicator':
                this.handleTypingIndicator(data);
                break;
                
            case 'job_update_request':
                this.handleUpdateRequest(data);
                break;
                
            case 'pong':
                // Keep-alive response
                break;
                
            default:
                console.log('🤷 Unknown WebSocket message type:', data.type);
        }
    }

    handleNewJobNotification(data) {
        this.sendNotification(
            'New Job Available! 🎉',
            `${data.service_type} at ${data.address}`,
            {
                actions: [
                    { action: 'view', title: 'View Details' },
                    { action: 'accept', title: 'Accept Job' }
                ],
                data: { jobId: data.job_id }
            }
        );
        
        // Refresh job list
        this.getAvailableJobs();
        
        // Play notification sound
        this.playNotificationSound();
    }

    handleJobCancellation(data) {
        if (this.currentBooking && this.currentBooking.id === data.booking_id) {
            this.sendNotification(
                'Job Cancelled ❌',
                'Your current job has been cancelled by the customer'
            );
            
            this.currentBooking = null;
            this.showMainScreen();
        }
    }

    handleIncomingMessage(data) {
        this.addMessageToChat(data.message, false, data.timestamp);
        
        // Send notification if app is in background
        if (document.hidden) {
            this.sendNotification(
                'New Message 💬',
                data.message.length > 50 ? data.message.substring(0, 50) + '...' : data.message
            );
        }
        
        // Play message sound
        this.playMessageSound();
    }

    handleTypingIndicator(data) {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            if (data.is_typing) {
                indicator.textContent = 'Customer is typing...';
                indicator.style.display = 'block';
            } else {
                indicator.style.display = 'none';
            }
        }
    }

    // Offline Support
    addToOfflineQueue(action, data) {
        const queueItem = {
            id: Date.now(),
            action,
            data,
            timestamp: Date.now()
        };
        
        this.offlineQueue.push(queueItem);
        localStorage.setItem('offline_queue', JSON.stringify(this.offlineQueue));
        
        console.log('📦 Added to offline queue:', action);
    }

    async processOfflineQueue() {
        if (this.offlineQueue.length === 0) return;
        
        console.log('🔄 Processing offline queue:', this.offlineQueue.length, 'items');
        
        const processed = [];
        
        for (const item of this.offlineQueue) {
            try {
                await this.processOfflineAction(item);
                processed.push(item.id);
                console.log('✅ Processed offline action:', item.action);
            } catch (error) {
                console.error('❌ Failed to process offline action:', item.action, error);
            }
        }
        
        // Remove processed items
        this.offlineQueue = this.offlineQueue.filter(item => !processed.includes(item.id));
        localStorage.setItem('offline_queue', JSON.stringify(this.offlineQueue));
        
        if (processed.length > 0) {
            this.showSuccessMessage(`Synced ${processed.length} offline actions`);
        }
    }

    async processOfflineAction(item) {
        const endpoints = {
            'location_update': '/api/professional/location.php',
            'job_update': '/api/professional/update-job.php',
            'chat_message': '/api/chat/send.php',
            'accept_job': '/api/professional/accept-job.php'
        };

        const endpoint = endpoints[item.action];
        if (!endpoint) {
            throw new Error(`Unknown offline action: ${item.action}`);
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('professional_token')}`
            },
            body: JSON.stringify(item.data)
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    loadOfflineData() {
        const savedQueue = localStorage.getItem('offline_queue');
        if (savedQueue) {
            this.offlineQueue = JSON.parse(savedQueue);
        }
        
        const savedBooking = localStorage.getItem('current_booking');
        if (savedBooking) {
            this.currentBooking = JSON.parse(savedBooking);
        }
    }

    saveOfflineData() {
        if (this.currentBooking) {
            localStorage.setItem('current_booking', JSON.stringify(this.currentBooking));
        }
        
        localStorage.setItem('offline_queue', JSON.stringify(this.offlineQueue));
    }

    // UI Rendering Methods
    renderAvailableJobs(jobs) {
        const container = document.getElementById('available-jobs');
        if (!container) return;

        if (jobs.length === 0) {
            container.innerHTML = `
                <div class="no-jobs">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Available Jobs</h3>
                    <p>Check back later for new cleaning opportunities</p>
                </div>
            `;
            return;
        }

        container.innerHTML = jobs.map(job => `
            <div class="job-card ${job.priority === 'high' ? 'high-priority' : ''}" data-job-id="${job.id}">
                <div class="job-header">
                    <h3>${job.service_type}</h3>
                    <span class="job-pay">$${job.total_amount}</span>
                    ${job.priority === 'high' ? '<span class="priority-badge">🔥 Urgent</span>' : ''}
                </div>
                <div class="job-details">
                    <p><i class="fas fa-map-marker-alt"></i> ${job.address}</p>
                    <p><i class="fas fa-clock"></i> ${this.formatDate(job.scheduled_date)} at ${job.scheduled_time}</p>
                    <p><i class="fas fa-home"></i> ${job.property_type} • ${job.bedrooms} bed, ${job.bathrooms} bath</p>
                    <p><i class="fas fa-route"></i> ${this.formatDistance(job.distance)} away</p>
                    ${job.special_requirements ? `<p><i class="fas fa-exclamation-triangle"></i> ${job.special_requirements}</p>` : ''}
                </div>
                <div class="job-actions">
                    <button onclick="app.viewJobDetails('${job.id}')" class="btn-secondary">
                        <i class="fas fa-eye"></i> Details
                    </button>
                    <button onclick="app.acceptJob('${job.id}')" class="btn-primary">
                        <i class="fas fa-check"></i> Accept Job
                    </button>
                </div>
            </div>
        `).join('');
    }

    addMessageToChat(message, isOwn, timestamp) {
        const container = document.getElementById('chat-messages');
        if (!container) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isOwn ? 'own' : 'other'}`;
        messageDiv.dataset.timestamp = timestamp;
        
        messageDiv.innerHTML = `
            <div class="message-content">${this.escapeHtml(message)}</div>
            <div class="message-time">${this.formatTime(timestamp)}</div>
            ${isOwn ? '<div class="message-status"><i class="fas fa-check"></i></div>' : ''}
        `;
        
        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;

        // Add to local storage for offline access
        if (this.currentBooking) {
            const messages = JSON.parse(localStorage.getItem(`chat_${this.currentBooking.id}`) || '[]');
            messages.push({
                message,
                sender_type: isOwn ? 'professional' : 'customer',
                created_at: new Date(timestamp).toISOString()
            });
            localStorage.setItem(`chat_${this.currentBooking.id}`, JSON.stringify(messages));
        }
    }

    markMessageAsPending(timestamp) {
        const message = document.querySelector(`[data-timestamp="${timestamp}"]`);
        if (message) {
            const status = message.querySelector('.message-status i');
            if (status) {
                status.className = 'fas fa-clock';
                status.style.color = '#fbbf24';
            }
        }
    }

    renderChatMessages(messages) {
        const container = document.getElementById('chat-messages');
        if (!container) return;

        container.innerHTML = '';
        
        messages.forEach(msg => {
            this.addMessageToChat(
                msg.message, 
                msg.sender_type === 'professional',
                new Date(msg.created_at).getTime()
            );
        });

        // Add typing indicator
        const typingDiv = document.createElement('div');
        typingDiv.id = 'typing-indicator';
        typingDiv.className = 'typing-indicator';
        typingDiv.style.display = 'none';
        container.appendChild(typingDiv);
    }

    updateJobUI() {
        if (!this.currentBooking) return;

        const statusContainer = document.getElementById('job-status');
        if (!statusContainer) return;

        const statusSteps = [
            { key: 'accepted', label: 'Job Accepted', icon: 'fas fa-check' },
            { key: 'traveling', label: 'On the Way', icon: 'fas fa-route' },
            { key: 'arrived', label: 'Arrived', icon: 'fas fa-map-marker-alt' },
            { key: 'in_progress', label: 'Cleaning', icon: 'fas fa-broom' },
            { key: 'completed', label: 'Completed', icon: 'fas fa-check-circle' }
        ];

        const currentIndex = statusSteps.findIndex(step => step.key === this.currentBooking.status);

        statusContainer.innerHTML = statusSteps.map((step, index) => `
            <div class="status-step ${index <= currentIndex ? 'completed' : ''} ${index === currentIndex ? 'current' : ''}">
                <div class="step-icon">
                    <i class="${step.icon}"></i>
                </div>
                <div class="step-label">${step.label}</div>
                ${index === currentIndex ? '<div class="step-pulse"></div>' : ''}
            </div>
        `).join('');

        // Update action buttons based on status
        this.updateActionButtons();
    }

    updateActionButtons() {
        const actionContainer = document.getElementById('job-actions');
        if (!actionContainer || !this.currentBooking) return;

        const buttons = {
            'accepted': [
                { text: 'Start Traveling', action: () => this.updateJobStatus('traveling'), class: 'primary' },
                { text: 'Cancel Job', action: () => this.cancelJob(), class: 'danger' }
            ],
            'traveling': [
                { text: 'Arrived', action: () => this.updateJobStatus('arrived'), class: 'primary' },
                { text: 'Still Traveling', action: () => this.sendQuickMessage('Still on my way, running a bit late'), class: 'secondary' }
            ],
            'arrived': [
                { text: 'Start Cleaning', action: () => this.updateJobStatus('in_progress'), class: 'primary' },
                { text: 'Contact Customer', action: () => this.openChat(), class: 'secondary' }
            ],
            'in_progress': [
                { text: 'Complete Job', action: () => this.updateJobStatus('completed'), class: 'primary' },
                { text: 'Send Update', action: () => this.sendQuickUpdate(), class: 'secondary' }
            ],
            'completed': [
                { text: 'Mark as Done', action: () => this.finalizeJob(), class: 'success' },
                { text: 'Report Issue', action: () => this.reportIssue(), class: 'warning' }
            ]
        };

        const statusButtons = buttons[this.currentBooking.status] || [];
        
        actionContainer.innerHTML = statusButtons.map(btn => `
            <button onclick="(${btn.action.toString()})()" class="btn btn-${btn.class}">
                ${btn.text}
            </button>
        `).join('');
    }

    updateProfileUI() {
        const nameEl = document.getElementById('professional-name');
        const ratingEl = document.getElementById('professional-rating');
        const photoEl = document.getElementById('professional-photo');
        
        if (nameEl) nameEl.textContent = this.currentUser.name;
        if (ratingEl) ratingEl.textContent = `${this.currentUser.rating}⭐`;
        if (photoEl) photoEl.src = this.currentUser.photo || '/assets/icons/default-avatar.png';

        // Update stats
        const stats = {
            'total-jobs': this.currentUser.total_jobs || 0,
            'this-month': this.currentUser.jobs_this_month || 0,
            'rating': this.currentUser.rating || 0,
            'earnings': this.currentUser.total_earnings || 0
        };

        Object.entries(stats).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) {
                if (id === 'earnings') {
                    el.textContent = this.formatCurrency(value);
                } else {
                    el.textContent = value;
                }
            }
        });
    }

    // Service Worker Registration
    async initializeServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/professional/sw.js');
                console.log('✅ Service Worker registered:', registration);
                
                // Listen for messages from Service Worker
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data && event.data.type === 'NOTIFICATION_CLICK') {
                        this.handleNotificationClick(event.data);
                    }
                });

                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateAvailable();
                        }
                    });
                });
            } catch (error) {
                console.error('❌ Service Worker registration failed:', error);
            }
        }
    }

    showUpdateAvailable() {
        const banner = document.createElement('div');
        banner.className = 'update-banner';
        banner.innerHTML = `
            <div class="update-content">
                <span>📱 App update available!</span>
                <button onclick="app.applyUpdate()">Update Now</button>
                <button onclick="this.parentElement.parentElement.remove()">Later</button>
            </div>
        `;
        document.body.prepend(banner);
    }

    applyUpdate() {
        window.location.reload();
    }

    // Event Listeners
    setupEventListeners() {
        // Online/Offline status
        window.addEventListener('online', () => {
            console.log('📡 Back online');
            this.isOnline = true;
            document.body.classList.remove('offline');
            this.processOfflineQueue();
            this.initializeWebSocket();
        });
        
        window.addEventListener('offline', () => {
            console.log('📴 Gone offline');
            this.isOnline = false;
            document.body.classList.add('offline');
            this.showOfflineMessage('You are currently offline. Actions will be synced when connection is restored.');
        });

        // App lifecycle
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.saveOfflineData();
            } else {
                if (this.isOnline && this.offlineQueue.length > 0) {
                    this.processOfflineQueue();
                }
            }
        });

        // Prevent accidental navigation away
        window.addEventListener('beforeunload', (e) => {
            if (this.currentBooking && this.currentBooking.status === 'in_progress') {
                e.preventDefault();
                e.returnValue = 'You have an active job. Are you sure you want to leave?';
            }
            this.saveOfflineData();
        });

        // Handle back button
        window.addEventListener('popstate', (e) => {
            this.handleBackButton();
        });
    }

    // Utility Methods
    redirectToLogin() {
        window.location.href = '/professional/login.html';
    }

    closeModal() {
        const modal = document.querySelector('.quick-update-modal');
        if (modal) modal.remove();
    }

    showOfflineMessage(message) {
        // Remove existing offline messages
        const existing = document.querySelector('.offline-message');
        if (existing) existing.remove();

        const banner = document.createElement('div');
        banner.className = 'offline-message';
        banner.innerHTML = `
            <div class="offline-content">
                <i class="fas fa-wifi-slash"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        document.body.prepend(banner);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (banner.parentNode) {
                banner.remove();
            }
        }, 5000);
    }

    showSuccessMessage(message) {
        const banner = document.createElement('div');
        banner.className = 'success-message';
        banner.innerHTML = `
            <div class="success-content">
                <i class="fas fa-check-circle"></i>
                <span>${message}</span>
            </div>
        `;
        document.body.prepend(banner);

        setTimeout(() => banner.remove(), 3000);
    }

    playNotificationSound() {
        try {
            const audio = new Audio('/assets/sounds/notification.mp3');
            audio.volume = 0.5;
            audio.play();
        } catch (error) {
            console.log('Could not play notification sound');
        }
    }

    playMessageSound() {
        try {
            const audio = new Audio('/assets/sounds/message.mp3');
            audio.volume = 0.3;
            audio.play();
        } catch (error) {
            console.log('Could not play message sound');
        }
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('en-AU', {
            style: 'currency',
            currency: 'AUD'
        }).format(amount);
    }

    formatDistance(meters) {
        if (meters < 1000) {
            return `${Math.round(meters)}m`;
        }
        return `${(meters / 1000).toFixed(1)}km`;
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-AU', {
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        });
    }

    formatTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString('en-AU', {
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Additional Methods
    async viewJobDetails(jobId) {
        // Implementation for viewing job details
        console.log('Viewing job details:', jobId);
    }

    showJobDetails(booking) {
        this.currentBooking = booking;
        // Switch to job details view
        // Implementation depends on your UI framework
    }

    openChat() {
        if (this.currentBooking) {
            this.initializeChat(this.currentBooking.id);
        }
    }

    async cancelJob() {
        if (!confirm('Are you sure you want to cancel this job?')) return;
        
        // Implementation for job cancellation
        console.log('Cancelling job:', this.currentBooking?.id);
    }

    async finalizeJob() {
        // Implementation for job finalization
        console.log('Finalizing job:', this.currentBooking?.id);
    }

    async reportIssue() {
        // Implementation for issue reporting
        console.log('Reporting issue for job:', this.currentBooking?.id);
    }

    showMainScreen() {
        // Implementation to show main screen
        this.getAvailableJobs();
    }

    handleBackButton() {
        // Implementation for back button handling
        console.log('Back button pressed');
    }

    handleNotificationClick(data) {
        // Implementation for notification click handling
        console.log('Notification clicked:', data);
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new ProfessionalApp();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ProfessionalApp;
}
