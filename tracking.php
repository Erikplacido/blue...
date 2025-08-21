<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="Acompanhe seu profissional em tempo real - Blue Services">
    
    <title>Rastreamento do Serviço - Blue Services</title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="liquid-glass-components.css">
    <link rel="stylesheet" href="assets/css/blue7.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <style>
        /* Mobile-First Tracking Interface */
        :root {
            --primary-blue: #2563eb;
            --success-green: #10b981;
            --warning-yellow: #f59e0b;
            --error-red: #ef4444;
            --neutral-gray: #6b7280;
            --light-gray: #f3f4f6;
            --white: #ffffff;
            --black: #1f2937;
            
            --header-height: 60px;
            --bottom-nav-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light-gray);
            overflow-x: hidden;
            padding-top: var(--header-height);
            padding-bottom: var(--bottom-nav-height);
        }

        /* Header */
        .tracking-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1rem;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .back-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-blue);
            cursor: pointer;
            padding: 0.5rem;
            margin-left: -0.5rem;
        }

        .header-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--black);
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--neutral-gray);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--light-gray);
            color: var(--primary-blue);
        }

        /* Service Status Card */
        .service-status-card {
            background: var(--white);
            margin: 1rem;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .status-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .status-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
        }

        .status-icon.en-route {
            background: var(--primary-blue);
        }

        .status-icon.arrived {
            background: var(--warning-yellow);
        }

        .status-icon.in-progress {
            background: var(--success-green);
        }

        .status-info {
            flex: 1;
            margin-left: 1rem;
        }

        .status-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.25rem;
        }

        .status-subtitle {
            color: var(--neutral-gray);
            font-size: 0.9rem;
        }

        .eta-display {
            text-align: right;
        }

        .eta-time {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .eta-label {
            font-size: 0.8rem;
            color: var(--neutral-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Map Container */
        .map-container {
            height: 300px;
            margin: 0 1rem 1rem;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        #trackingMap {
            height: 100%;
            width: 100%;
        }

        .map-overlay {
            position: absolute;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            z-index: 1000;
            pointer-events: none;
        }

        .professional-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            pointer-events: all;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .pro-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.2rem;
            font-weight: 600;
        }

        .pro-info {
            flex: 1;
        }

        .pro-name {
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.25rem;
        }

        .pro-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--warning-yellow);
            font-size: 0.9rem;
        }

        .contact-professional {
            display: flex;
            gap: 0.5rem;
        }

        .contact-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .call-btn {
            background: var(--success-green);
            color: var(--white);
        }

        .chat-btn {
            background: var(--primary-blue);
            color: var(--white);
        }

        .contact-btn:hover {
            transform: scale(1.1);
        }

        /* Timeline */
        .timeline-container {
            background: var(--white);
            margin: 1rem;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .timeline-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 1.5rem;
        }

        .timeline {
            position: relative;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--light-gray);
        }

        .timeline-item {
            position: relative;
            padding-left: 3rem;
            margin-bottom: 1.5rem;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-icon {
            position: absolute;
            left: 0;
            top: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: var(--white);
            z-index: 2;
        }

        .timeline-item.completed .timeline-icon {
            background: var(--success-green);
        }

        .timeline-item.active .timeline-icon {
            background: var(--primary-blue);
            animation: pulse 2s infinite;
        }

        .timeline-item.pending .timeline-icon {
            background: var(--neutral-gray);
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
        }

        .timeline-content {
            color: var(--black);
        }

        .timeline-title-item {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .timeline-time {
            color: var(--neutral-gray);
            font-size: 0.9rem;
        }

        .timeline-description {
            color: var(--neutral-gray);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        /* Quick Actions */
        .quick-actions {
            position: fixed;
            bottom: var(--bottom-nav-height);
            left: 0;
            right: 0;
            background: var(--white);
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding: 1rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .quick-action-btn {
            flex: 1;
            max-width: 120px;
            background: var(--light-gray);
            border: none;
            padding: 1rem 0.5rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
        }

        .quick-action-btn.primary {
            background: var(--primary-blue);
            color: var(--white);
        }

        .quick-action-btn.success {
            background: var(--success-green);
            color: var(--white);
        }

        .quick-action-btn.warning {
            background: var(--warning-yellow);
            color: var(--white);
        }

        .quick-action-btn.danger {
            background: var(--error-red);
            color: var(--white);
        }

        .quick-action-icon {
            font-size: 1.2rem;
        }

        .quick-action-label {
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Chat Overlay */
        .chat-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            display: none;
            align-items: flex-end;
        }

        .chat-overlay.active {
            display: flex;
        }

        .chat-container {
            background: var(--white);
            border-radius: 20px 20px 0 0;
            max-height: 80vh;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-title {
            font-weight: 600;
            color: var(--black);
        }

        .close-chat {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--neutral-gray);
            cursor: pointer;
        }

        .chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            max-height: 50vh;
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .message.sent {
            flex-direction: row-reverse;
        }

        .message-bubble {
            background: var(--light-gray);
            padding: 0.75rem 1rem;
            border-radius: 16px;
            max-width: 70%;
            font-size: 0.9rem;
        }

        .message.sent .message-bubble {
            background: var(--primary-blue);
            color: var(--white);
        }

        .message-time {
            font-size: 0.7rem;
            color: var(--neutral-gray);
            margin-top: 0.25rem;
        }

        .chat-input {
            padding: 1rem;
            border-top: 1px solid var(--light-gray);
            display: flex;
            gap: 0.5rem;
        }

        .chat-input input {
            flex: 1;
            border: 1px solid var(--light-gray);
            border-radius: 20px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            outline: none;
        }

        .send-btn {
            background: var(--primary-blue);
            color: var(--white);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Emergency Modal */
        .emergency-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .emergency-modal.active {
            display: flex;
        }

        .emergency-content {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .emergency-icon {
            font-size: 3rem;
            color: var(--error-red);
            margin-bottom: 1rem;
        }

        .emergency-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 1rem;
        }

        .emergency-text {
            color: var(--neutral-gray);
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .emergency-actions {
            display: flex;
            gap: 1rem;
        }

        .emergency-btn {
            flex: 1;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .emergency-btn.primary {
            background: var(--error-red);
            color: var(--white);
        }

        .emergency-btn.secondary {
            background: var(--light-gray);
            color: var(--black);
        }

        /* Loading States */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--light-gray);
            border-radius: 50%;
            border-top-color: var(--primary-blue);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .professional-card {
                padding: 0.75rem;
            }

            .quick-actions {
                padding: 0.75rem;
                gap: 0.75rem;
            }

            .quick-action-btn {
                max-width: 100px;
                padding: 0.75rem 0.5rem;
            }

            .map-container {
                height: 250px;
            }
        }

        /* Accessibility */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --white: #1f2937;
                --light-gray: #374151;
                --black: #f9fafb;
                --neutral-gray: #9ca3af;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="tracking-header">
        <div class="header-content">
            <button class="back-btn" onclick="goBack()">
                <i class="fas fa-arrow-left"></i>
            </button>
            
            <h1 class="header-title">Acompanhar Serviço</h1>
            
            <div class="header-actions">
                <button class="action-btn" onclick="refreshTracking()" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button class="action-btn" onclick="shareTracking()">
                    <i class="fas fa-share"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Service Status Card -->
    <div class="service-status-card">
        <div class="status-header">
            <div class="status-icon in-progress" id="statusIcon">
                <i class="fas fa-route" id="statusIconSymbol"></i>
            </div>
            
            <div class="status-info">
                <div class="status-title" id="statusTitle">A caminho</div>
                <div class="status-subtitle" id="statusSubtitle">Carlos está se dirigindo para sua casa</div>
            </div>
            
            <div class="eta-display">
                <div class="eta-time" id="etaTime">12 min</div>
                <div class="eta-label">ETA</div>
            </div>
        </div>
    </div>

    <!-- Map Container -->
    <div class="map-container">
        <div id="trackingMap"></div>
        
        <div class="map-overlay">
            <div class="professional-card">
                <div class="pro-avatar" id="proAvatar">CS</div>
                
                <div class="pro-info">
                    <div class="pro-name" id="proName">Carlos Silva</div>
                    <div class="pro-rating">
                        <i class="fas fa-star"></i>
                        <span id="proRating">4.9</span>
                        <span>(247 avaliações)</span>
                    </div>
                </div>
                
                <div class="contact-professional">
                    <button class="contact-btn call-btn" onclick="callProfessional()">
                        <i class="fas fa-phone"></i>
                    </button>
                    <button class="contact-btn chat-btn" onclick="openChat()">
                        <i class="fas fa-comments"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="timeline-container">
        <h3 class="timeline-title">Progresso do Serviço</h3>
        
        <div class="timeline">
            <div class="timeline-item completed">
                <div class="timeline-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-title-item">Profissional Designado</div>
                    <div class="timeline-time">10:30</div>
                    <div class="timeline-description">Carlos Silva foi designado para seu serviço</div>
                </div>
            </div>
            
            <div class="timeline-item completed">
                <div class="timeline-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-title-item">A Caminho</div>
                    <div class="timeline-time">11:15</div>
                    <div class="timeline-description">Profissional saiu e está se dirigindo para sua casa</div>
                </div>
            </div>
            
            <div class="timeline-item active">
                <div class="timeline-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-title-item">Chegada</div>
                    <div class="timeline-time">Estimado: 11:45</div>
                    <div class="timeline-description">Profissional chegará em breve</div>
                </div>
            </div>
            
            <div class="timeline-item pending">
                <div class="timeline-icon">
                    <i class="fas fa-broom"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-title-item">Serviço em Andamento</div>
                    <div class="timeline-time">Estimado: 12:00</div>
                    <div class="timeline-description">Início do serviço de limpeza</div>
                </div>
            </div>
            
            <div class="timeline-item pending">
                <div class="timeline-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-title-item">Serviço Concluído</div>
                    <div class="timeline-time">Estimado: 14:30</div>
                    <div class="timeline-description">Avaliação e finalização</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <button class="quick-action-btn primary" onclick="openChat()">
            <div class="quick-action-icon">
                <i class="fas fa-comments"></i>
            </div>
            <div class="quick-action-label">Chat</div>
        </button>
        
        <button class="quick-action-btn success" onclick="callProfessional()">
            <div class="quick-action-icon">
                <i class="fas fa-phone"></i>
            </div>
            <div class="quick-action-label">Ligar</div>
        </button>
        
        <button class="quick-action-btn warning" onclick="reportIssue()">
            <div class="quick-action-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="quick-action-label">Problema</div>
        </button>
        
        <button class="quick-action-btn danger" onclick="showEmergencyModal()">
            <div class="quick-action-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="quick-action-label">Emergência</div>
        </button>
    </div>

    <!-- Chat Overlay -->
    <div class="chat-overlay" id="chatOverlay">
        <div class="chat-container">
            <div class="chat-header">
                <div class="chat-title">Chat com Carlos Silva</div>
                <button class="close-chat" onclick="closeChat()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="message">
                    <div>
                        <div class="message-bubble">Olá! Estou a caminho da sua casa. Chego em aproximadamente 15 minutos.</div>
                        <div class="message-time">11:20</div>
                    </div>
                </div>
                
                <div class="message sent">
                    <div>
                        <div class="message-bubble">Perfeito! Estarei em casa esperando.</div>
                        <div class="message-time">11:22</div>
                    </div>
                </div>
                
                <div class="message">
                    <div>
                        <div class="message-bubble">Ótimo! Lembre-se de deixar os pets em um local seguro durante a limpeza.</div>
                        <div class="message-time">11:23</div>
                    </div>
                </div>
            </div>
            
            <div class="chat-input">
                <input type="text" placeholder="Digite sua mensagem..." id="messageInput">
                <button class="send-btn" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Emergency Modal -->
    <div class="emergency-modal" id="emergencyModal">
        <div class="emergency-content">
            <div class="emergency-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h3 class="emergency-title">Emergência</h3>
            
            <p class="emergency-text">
                Caso haja alguma situação de emergência, entre em contato conosco imediatamente 
                ou ligue para os serviços de emergência se necessário.
            </p>
            
            <div class="emergency-actions">
                <button class="emergency-btn primary" onclick="callEmergency()">
                    Ligar Emergência
                </button>
                <button class="emergency-btn secondary" onclick="closeEmergencyModal()">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Global variables
        let map;
        let professionalMarker;
        let customerMarker;
        let routeLine;
        let trackingInterval;
        let currentJobId = 'job_12345'; // Would come from URL/session

        // Initialize tracking page
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
            startTracking();
            setupEventListeners();
        });

        // Initialize Leaflet map
        function initializeMap() {
            // Customer location (would come from booking data)
            const customerLocation = [-33.8675, 151.2070]; // Sydney CBD
            
            // Initialize map
            map = L.map('trackingMap').setView(customerLocation, 14);
            
            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add customer marker
            customerMarker = L.marker(customerLocation, {
                icon: L.divIcon({
                    className: 'customer-marker',
                    html: '<div style="background: #ef4444; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="fas fa-home"></i></div>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(map);
            
            customerMarker.bindPopup('Sua Casa').openPopup();
        }

        // Start tracking professional
        function startTracking() {
            updateProfessionalLocation();
            
            // Update every 30 seconds
            trackingInterval = setInterval(updateProfessionalLocation, 30000);
        }

        // Update professional location
        function updateProfessionalLocation() {
            // Simulate API call to get professional location
            // In real implementation, this would call your tracking API
            
            const professionalLocation = [-33.8820, 151.2069]; // Simulated location
            
            if (professionalMarker) {
                map.removeLayer(professionalMarker);
            }
            
            // Add professional marker
            professionalMarker = L.marker(professionalLocation, {
                icon: L.divIcon({
                    className: 'professional-marker',
                    html: '<div style="background: #2563eb; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="fas fa-user"></i></div>',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                })
            }).addTo(map);
            
            professionalMarker.bindPopup('Carlos Silva<br>Profissional de Limpeza');
            
            // Update route
            updateRoute(professionalLocation, [-33.8675, 151.2070]);
            
            // Update ETA
            updateETA();
        }

        // Update route line
        function updateRoute(start, end) {
            if (routeLine) {
                map.removeLayer(routeLine);
            }
            
            // Simple route line (in real app, use routing service)
            routeLine = L.polyline([start, end], {
                color: '#2563eb',
                weight: 4,
                opacity: 0.7,
                dashArray: '10, 10'
            }).addTo(map);
            
            // Fit map to show route
            const group = new L.featureGroup([professionalMarker, customerMarker, routeLine]);
            map.fitBounds(group.getBounds().pad(0.1));
        }

        // Update ETA
        function updateETA() {
            // Simulate ETA calculation
            const eta = Math.floor(Math.random() * 5) + 10; // 10-15 minutes
            document.getElementById('etaTime').textContent = eta + ' min';
        }

        // Refresh tracking
        function refreshTracking() {
            const refreshBtn = document.getElementById('refreshBtn');
            const icon = refreshBtn.querySelector('i');
            
            // Add loading animation
            icon.classList.add('fa-spin');
            
            updateProfessionalLocation();
            
            // Remove loading animation after 1 second
            setTimeout(() => {
                icon.classList.remove('fa-spin');
            }, 1000);
        }

        // Setup event listeners
        function setupEventListeners() {
            // Message input enter key
            document.getElementById('messageInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });
            
            // Close chat overlay when clicking outside
            document.getElementById('chatOverlay').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeChat();
                }
            });
            
            // Close emergency modal when clicking outside
            document.getElementById('emergencyModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeEmergencyModal();
                }
            });
        }

        // Navigation functions
        function goBack() {
            window.history.back();
        }

        function shareTracking() {
            if (navigator.share) {
                navigator.share({
                    title: 'Acompanhe meu serviço',
                    text: 'Veja o progresso do meu serviço em tempo real',
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Link copiado para a área de transferência!');
                });
            }
        }

        // Communication functions
        function callProfessional() {
            // Vibration feedback
            if (navigator.vibrate) {
                navigator.vibrate(100);
            }
            
            // In real app, would initiate call
            window.location.href = 'tel:+61412345678';
        }

        function openChat() {
            document.getElementById('chatOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeChat() {
            document.getElementById('chatOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (message) {
                // Add message to chat
                addMessage(message, true);
                input.value = '';
                
                // Simulate response
                setTimeout(() => {
                    addMessage('Mensagem recebida! Obrigado pelo contato.', false);
                }, 1000);
            }
        }

        function addMessage(text, isSent) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isSent ? 'sent' : ''}`;
            
            const time = new Date().toLocaleTimeString('pt-BR', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            messageDiv.innerHTML = `
                <div>
                    <div class="message-bubble">${text}</div>
                    <div class="message-time">${time}</div>
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Issue reporting
        function reportIssue() {
            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100]);
            }
            
            const issues = [
                'Profissional está atrasado',
                'Não consegui contatar o profissional',
                'Problema com a localização',
                'Outro problema'
            ];
            
            const selectedIssue = prompt(`Selecione o problema:\n${issues.map((issue, index) => `${index + 1}. ${issue}`).join('\n')}`);
            
            if (selectedIssue) {
                alert('Problema reportado! Nossa equipe entrará em contato em breve.');
            }
        }

        // Emergency functions
        function showEmergencyModal() {
            if (navigator.vibrate) {
                navigator.vibrate([200, 100, 200]);
            }
            
            document.getElementById('emergencyModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEmergencyModal() {
            document.getElementById('emergencyModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function callEmergency() {
            // In Australia, emergency number is 000
            window.location.href = 'tel:000';
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (trackingInterval) {
                clearInterval(trackingInterval);
            }
        });

        // Real-time updates simulation
        function simulateStatusUpdates() {
            const statuses = [
                {
                    icon: 'fas fa-route',
                    title: 'A caminho',
                    subtitle: 'Carlos está se dirigindo para sua casa',
                    class: 'en-route'
                },
                {
                    icon: 'fas fa-map-marker-alt',
                    title: 'Chegou',
                    subtitle: 'Carlos chegou na sua casa',
                    class: 'arrived'
                },
                {
                    icon: 'fas fa-broom',
                    title: 'Serviço em andamento',
                    subtitle: 'Limpeza iniciada',
                    class: 'in-progress'
                },
                {
                    icon: 'fas fa-check',
                    title: 'Concluído',
                    subtitle: 'Serviço finalizado com sucesso',
                    class: 'completed'
                }
            ];
            
            let currentStatus = 0;
            
            setInterval(() => {
                if (currentStatus < statuses.length - 1) {
                    currentStatus++;
                    const status = statuses[currentStatus];
                    
                    document.getElementById('statusIcon').className = `status-icon ${status.class}`;
                    document.getElementById('statusIconSymbol').className = status.icon;
                    document.getElementById('statusTitle').textContent = status.title;
                    document.getElementById('statusSubtitle').textContent = status.subtitle;
                    
                    // Update timeline
                    updateTimeline(currentStatus);
                }
            }, 60000); // Update every minute for demo
        }

        function updateTimeline(activeIndex) {
            const timelineItems = document.querySelectorAll('.timeline-item');
            
            timelineItems.forEach((item, index) => {
                item.classList.remove('active', 'completed', 'pending');
                
                if (index < activeIndex) {
                    item.classList.add('completed');
                } else if (index === activeIndex) {
                    item.classList.add('active');
                } else {
                    item.classList.add('pending');
                }
            });
        }

        // Start status simulation
        setTimeout(simulateStatusUpdates, 5000);
    </script>
</body>
</html>
