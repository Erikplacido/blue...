<?php
/**
 * Support & Customer Service System
 * Integrated ticketing and live chat system
 * Blue Cleaning Services
 */

session_start();
require_once __DIR__ . '/config/australian-environment.php';
require_once __DIR__ . '/config/australian-database.php';

class SupportSystem {
    private $db;
    private $logger;
    
    public function __construct() {
        // Load Australian environment configuration
        AustralianEnvironmentConfig::load();
        
        // Use standardized database connection
        $this->db = AustralianDatabase::getInstance()->getConnection();
        $this->logger = new Logger('support-system');
    }
    
    /**
     * Create support ticket
     */
    public function createTicket($data) {
        $ticketNumber = 'BCS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("
            INSERT INTO support_tickets 
            (ticket_number, user_id, name, email, phone, subject, category, priority, message, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
        ");
        
        $result = $stmt->execute([
            $ticketNumber,
            $data['user_id'] ?? null,
            $data['name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['subject'],
            $data['category'],
            $data['priority'] ?? 'medium',
            $data['message']
        ]);
        
        if ($result) {
            $ticketId = $this->db->lastInsertId();
            
            // Send confirmation email
            $this->sendTicketConfirmation($ticketNumber, $data['email'], $data['name']);
            
            // Log ticket creation
            $this->logger->info("Support ticket created", [
                'ticket_number' => $ticketNumber,
                'user_id' => $data['user_id'],
                'category' => $data['category']
            ]);
            
            return ['id' => $ticketId, 'ticket_number' => $ticketNumber];
        }
        
        return false;
    }
    
    /**
     * Get user's tickets
     */
    public function getUserTickets($userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM support_tickets 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get ticket by number
     */
    public function getTicket($ticketNumber) {
        $stmt = $this->db->prepare("
            SELECT st.*, GROUP_CONCAT(
                CONCAT(sr.message, '|||', sr.created_at, '|||', sr.is_customer) 
                ORDER BY sr.created_at 
                SEPARATOR '###'
            ) as responses
            FROM support_tickets st
            LEFT JOIN support_responses sr ON st.id = sr.ticket_id
            WHERE st.ticket_number = ?
            GROUP BY st.id
        ");
        $stmt->execute([$ticketNumber]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ticket && $ticket['responses']) {
            $responses = [];
            foreach (explode('###', $ticket['responses']) as $response) {
                [$message, $created_at, $is_customer] = explode('|||', $response);
                $responses[] = [
                    'message' => $message,
                    'created_at' => $created_at,
                    'is_customer' => (bool)$is_customer
                ];
            }
            $ticket['responses'] = $responses;
        } else {
            $ticket['responses'] = [];
        }
        
        return $ticket;
    }
    
    /**
     * Add response to ticket
     */
    public function addTicketResponse($ticketNumber, $message, $isCustomer = true, $userId = null) {
        $stmt = $this->db->prepare("SELECT id FROM support_tickets WHERE ticket_number = ?");
        $stmt->execute([$ticketNumber]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ticket) return false;
        
        $stmt = $this->db->prepare("
            INSERT INTO support_responses 
            (ticket_id, user_id, message, is_customer, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $ticket['id'],
            $userId,
            $message,
            $isCustomer ? 1 : 0
        ]);
        
        if ($result && !$isCustomer) {
            // Update ticket status to 'responded'
            $stmt = $this->db->prepare("
                UPDATE support_tickets 
                SET status = 'responded', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$ticket['id']]);
        }
        
        return $result;
    }
    
    /**
     * Start live chat session
     */
    public function startChatSession($userId, $name, $email) {
        $sessionId = uniqid('chat_', true);
        
        $stmt = $this->db->prepare("
            INSERT INTO chat_sessions 
            (session_id, user_id, name, email, status, started_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        
        $result = $stmt->execute([$sessionId, $userId, $name, $email]);
        
        if ($result) {
            $this->logger->info("Chat session started", [
                'session_id' => $sessionId,
                'user_id' => $userId
            ]);
            return $sessionId;
        }
        
        return false;
    }
    
    /**
     * Send chat message
     */
    public function sendChatMessage($sessionId, $message, $isCustomer = true, $userId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO chat_messages 
            (session_id, user_id, message, is_customer, sent_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $sessionId,
            $userId,
            $message,
            $isCustomer ? 1 : 0
        ]);
    }
    
    /**
     * Get chat messages
     */
    public function getChatMessages($sessionId, $lastMessageId = 0) {
        $stmt = $this->db->prepare("
            SELECT * FROM chat_messages 
            WHERE session_id = ? AND id > ?
            ORDER BY sent_at ASC
        ");
        $stmt->execute([$sessionId, $lastMessageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get support statistics
     */
    public function getSupportStats() {
        $stats = [];
        
        // Ticket counts
        $stmt = $this->db->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM support_tickets 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY status
        ");
        $stats['ticket_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Response times
        $stmt = $this->db->query("
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, st.created_at, sr.created_at)) as avg_response_hours
            FROM support_tickets st
            JOIN support_responses sr ON st.id = sr.ticket_id
            WHERE sr.is_customer = 0 
            AND sr.created_at = (
                SELECT MIN(created_at) 
                FROM support_responses 
                WHERE ticket_id = st.id AND is_customer = 0
            )
            AND st.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['avg_response_time'] = $stmt->fetchColumn() ?: 0;
        
        // Active chat sessions
        $stmt = $this->db->query("
            SELECT COUNT(*) 
            FROM chat_sessions 
            WHERE status = 'active'
        ");
        $stats['active_chats'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    private function sendTicketConfirmation($ticketNumber, $email, $name) {
        // This would integrate with your email system
        $subject = "Support Ticket Created - {$ticketNumber}";
        $message = "
        Dear {$name},
        
        Your support ticket has been created successfully.
        
        Ticket Number: {$ticketNumber}
        
        We will respond to your inquiry as soon as possible.
        
        You can check the status of your ticket at any time by visiting our support portal.
        
        Best regards,
        Blue Cleaning Services Support Team
        ";
        
        // Send email using your email service
        // mail($email, $subject, $message);
        
        $this->logger->info("Ticket confirmation sent", [
            'ticket_number' => $ticketNumber,
            'email' => $email
        ]);
    }
}

// API endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $support = new SupportSystem();
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_ticket':
            $ticketData = [
                'user_id' => $_SESSION['user_id'] ?? null,
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'] ?? null,
                'subject' => $_POST['subject'],
                'category' => $_POST['category'],
                'priority' => $_POST['priority'] ?? 'medium',
                'message' => $_POST['message']
            ];
            
            $result = $support->createTicket($ticketData);
            echo json_encode($result ? ['success' => true, 'ticket' => $result] : ['success' => false]);
            break;
            
        case 'add_response':
            $ticketNumber = $_POST['ticket_number'];
            $message = $_POST['message'];
            $userId = $_SESSION['user_id'] ?? null;
            
            $result = $support->addTicketResponse($ticketNumber, $message, true, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        case 'start_chat':
            $userId = $_SESSION['user_id'] ?? null;
            $name = $_POST['name'];
            $email = $_POST['email'];
            
            $sessionId = $support->startChatSession($userId, $name, $email);
            echo json_encode($sessionId ? ['success' => true, 'session_id' => $sessionId] : ['success' => false]);
            break;
            
        case 'send_chat_message':
            $sessionId = $_POST['session_id'];
            $message = $_POST['message'];
            $userId = $_SESSION['user_id'] ?? null;
            
            $result = $support->sendChatMessage($sessionId, $message, true, $userId);
            echo json_encode(['success' => $result]);
            break;
            
        case 'get_chat_messages':
            $sessionId = $_POST['session_id'];
            $lastMessageId = $_POST['last_message_id'] ?? 0;
            
            $messages = $support->getChatMessages($sessionId, $lastMessageId);
            echo json_encode(['messages' => $messages]);
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Center - Blue Cleaning Services</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Support Center</h1>
            <p class="text-gray-600">Get help with your Blue Cleaning Services account</p>
        </div>
        
        <!-- Support Options -->
        <div class="grid md:grid-cols-2 gap-8 mb-8">
            <!-- Create Ticket -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center mb-6">
                    <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-ticket-alt text-2xl text-blue-600"></i>
                    </div>
                    <h2 class="text-xl font-semibold mb-2">Create Support Ticket</h2>
                    <p class="text-gray-600">Submit a detailed support request and track its progress</p>
                </div>
                
                <form id="ticket-form" class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <input type="text" name="name" placeholder="Your Name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <input type="email" name="email" placeholder="Your Email" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <input type="tel" name="phone" placeholder="Phone Number (optional)"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    
                    <input type="text" name="subject" placeholder="Subject" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <select name="category" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Category</option>
                            <option value="booking">Booking Issues</option>
                            <option value="payment">Payment Problems</option>
                            <option value="service_quality">Service Quality</option>
                            <option value="technical">Technical Issues</option>
                            <option value="account">Account Management</option>
                            <option value="other">Other</option>
                        </select>
                        
                        <select name="priority" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="low">Low Priority</option>
                            <option value="medium" selected>Medium Priority</option>
                            <option value="high">High Priority</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <textarea name="message" placeholder="Describe your issue in detail..." required
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-32 resize-none"></textarea>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Ticket
                    </button>
                </form>
            </div>
            
            <!-- Live Chat -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center mb-6">
                    <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-comments text-2xl text-green-600"></i>
                    </div>
                    <h2 class="text-xl font-semibold mb-2">Live Chat Support</h2>
                    <p class="text-gray-600">Get instant help from our support team</p>
                    <div class="mt-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            Online Now
                        </span>
                    </div>
                </div>
                
                <div id="chat-container" class="hidden">
                    <div id="chat-messages" class="border border-gray-300 rounded-lg h-64 p-4 overflow-y-auto mb-4 bg-gray-50">
                        <!-- Chat messages will appear here -->
                    </div>
                    
                    <div class="flex">
                        <input type="text" id="chat-input" placeholder="Type your message..." 
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <button onclick="sendChatMessage()" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-r-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
                
                <div id="chat-start" class="text-center">
                    <form id="chat-start-form" class="space-y-4 mb-4">
                        <input type="text" name="name" placeholder="Your Name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <input type="email" name="email" placeholder="Your Email" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </form>
                    <button onclick="startChat()" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-comments mr-2"></i>
                        Start Chat
                    </button>
                </div>
            </div>
        </div>
        
        <!-- FAQ Section -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-6">Frequently Asked Questions</h2>
            <div class="space-y-4">
                <div class="border-b pb-4">
                    <h3 class="font-medium text-gray-900 mb-2">How do I reschedule my cleaning appointment?</h3>
                    <p class="text-gray-600">You can reschedule your appointment up to 24 hours in advance through your customer dashboard or by calling our support team.</p>
                </div>
                <div class="border-b pb-4">
                    <h3 class="font-medium text-gray-900 mb-2">What if I'm not satisfied with the cleaning service?</h3>
                    <p class="text-gray-600">We offer a 100% satisfaction guarantee. If you're not happy with our service, contact us within 24 hours and we'll make it right.</p>
                </div>
                <div class="border-b pb-4">
                    <h3 class="font-medium text-gray-900 mb-2">How do I update my payment information?</h3>
                    <p class="text-gray-600">You can update your payment details in your account settings or contact our support team for assistance.</p>
                </div>
                <div class="pb-4">
                    <h3 class="font-medium text-gray-900 mb-2">Do I need to be home during the cleaning?</h3>
                    <p class="text-gray-600">No, you don't need to be present. Many of our customers provide access instructions and we'll securely complete the service.</p>
                </div>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="bg-blue-50 rounded-lg p-6">
            <div class="text-center">
                <h2 class="text-xl font-semibold text-blue-900 mb-4">Other Ways to Contact Us</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="bg-blue-600 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-phone text-white"></i>
                        </div>
                        <h3 class="font-medium text-blue-900">Phone</h3>
                        <p class="text-blue-700">1300 BLUE CLEAN</p>
                        <p class="text-sm text-blue-600">Mon-Fri: 7AM-6PM<br>Sat: 8AM-4PM</p>
                    </div>
                    <div class="text-center">
                        <div class="bg-blue-600 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-envelope text-white"></i>
                        </div>
                        <h3 class="font-medium text-blue-900">Email</h3>
                        <p class="text-blue-700">support@bluecleaning.com.au</p>
                        <p class="text-sm text-blue-600">We respond within 2 hours</p>
                    </div>
                    <div class="text-center">
                        <div class="bg-blue-600 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-map-marker-alt text-white"></i>
                        </div>
                        <h3 class="font-medium text-blue-900">Office</h3>
                        <p class="text-blue-700">Sydney, NSW</p>
                        <p class="text-sm text-blue-600">Australia</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    class SupportSystem {
        constructor() {
            this.chatSessionId = null;
            this.lastMessageId = 0;
            this.chatInterval = null;
            
            this.initializeEventListeners();
        }
        
        initializeEventListeners() {
            // Ticket form submission
            document.getElementById('ticket-form').addEventListener('submit', this.handleTicketSubmission.bind(this));
            
            // Chat input enter key
            document.getElementById('chat-input').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.sendChatMessage();
                }
            });
        }
        
        async handleTicketSubmission(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'create_ticket');
            
            try {
                const response = await fetch('/api/support.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Ticket created successfully! Ticket number: ${result.ticket.ticket_number}`);
                    e.target.reset();
                } else {
                    alert('Failed to create ticket. Please try again.');
                }
            } catch (error) {
                console.error('Error creating ticket:', error);
                alert('An error occurred. Please try again.');
            }
        }
        
        async startChat() {
            const form = document.getElementById('chat-start-form');
            const formData = new FormData(form);
            formData.append('action', 'start_chat');
            
            try {
                const response = await fetch('/api/support.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.chatSessionId = result.session_id;
                    document.getElementById('chat-start').classList.add('hidden');
                    document.getElementById('chat-container').classList.remove('hidden');
                    
                    // Start polling for messages
                    this.startMessagePolling();
                    
                    // Send welcome message
                    this.addChatMessage("Support team will be with you shortly. How can we help?", false);
                } else {
                    alert('Failed to start chat. Please try again.');
                }
            } catch (error) {
                console.error('Error starting chat:', error);
                alert('An error occurred. Please try again.');
            }
        }
        
        async sendChatMessage() {
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            
            if (!message || !this.chatSessionId) return;
            
            // Add message to chat immediately
            this.addChatMessage(message, true);
            input.value = '';
            
            // Send to server
            try {
                const response = await fetch('/api/support.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'send_chat_message',
                        session_id: this.chatSessionId,
                        message: message
                    })
                });
                
                const result = await response.json();
                if (!result.success) {
                    console.error('Failed to send message');
                }
            } catch (error) {
                console.error('Error sending message:', error);
            }
        }
        
        startMessagePolling() {
            this.chatInterval = setInterval(() => {
                this.pollMessages();
            }, 2000);
        }
        
        async pollMessages() {
            if (!this.chatSessionId) return;
            
            try {
                const response = await fetch('/api/support.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'get_chat_messages',
                        session_id: this.chatSessionId,
                        last_message_id: this.lastMessageId
                    })
                });
                
                const result = await response.json();
                
                result.messages.forEach(msg => {
                    if (msg.id > this.lastMessageId) {
                        this.addChatMessage(msg.message, msg.is_customer == 1);
                        this.lastMessageId = msg.id;
                    }
                });
            } catch (error) {
                console.error('Error polling messages:', error);
            }
        }
        
        addChatMessage(message, isCustomer) {
            const messagesContainer = document.getElementById('chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `mb-3 ${isCustomer ? 'text-right' : 'text-left'}`;
            
            messageDiv.innerHTML = `
                <div class="inline-block max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${
                    isCustomer 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-gray-200 text-gray-900'
                }">
                    <p class="text-sm">${this.escapeHtml(message)}</p>
                    <span class="text-xs opacity-75">${new Date().toLocaleTimeString()}</span>
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
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
    }
    
    // Initialize support system
    const supportSystem = new SupportSystem();
    
    // Global functions for HTML onclick handlers
    function startChat() {
        supportSystem.startChat();
    }
    
    function sendChatMessage() {
        supportSystem.sendChatMessage();
    }
    </script>
</body>
</html>
