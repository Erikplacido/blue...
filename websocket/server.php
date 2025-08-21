<?php
/**
 * Real-time WebSocket Server
 * Blue Cleaning Services - Chat & Live Tracking
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/environment.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class BlueWebSocketServer implements MessageComponentInterface {
    protected $clients;
    protected $rooms;
    protected $userConnections;
    protected $logger;
    protected $redis;
    protected $db;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        $this->userConnections = [];
        $this->logger = new Logger('websocket');
        
        // Initialize Redis for message persistence
        try {
            $this->redis = new Redis();
            $this->redis->connect(
                EnvironmentConfig::get('redis.host', 'localhost'),
                EnvironmentConfig::get('redis.port', 6379)
            );
            echo "✅ Redis connected\n";
        } catch (Exception $e) {
            echo "⚠️  Redis not available, using memory storage\n";
            $this->redis = null;
        }
        
        // Database connection
        $this->db = new PDO(
            "mysql:host=" . EnvironmentConfig::get('database.host') . ";dbname=" . EnvironmentConfig::get('database.database'),
            EnvironmentConfig::get('database.username'),
            EnvironmentConfig::get('database.password')
        );
        
        echo "🚀 Blue WebSocket Server Starting...\n";
        echo "📡 Listening for professional and customer connections\n";
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->authenticated = false;
        $conn->userType = null;
        $conn->userId = null;
        $conn->bookingRooms = [];
        $conn->lastPing = time();
        
        echo "📱 New connection! ({$conn->resourceId})\n";
        
        $conn->send(json_encode([
            'type' => 'connection_established',
            'connection_id' => $conn->resourceId,
            'server_time' => time(),
            'timestamp' => time()
        ]));
        
        $this->logger->info("Connection opened", [
            'connection_id' => $conn->resourceId,
            'ip' => $this->getClientIp($conn)
        ]);
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Invalid message format');
                return;
            }
            
            // Update last activity
            $from->lastPing = time();
            
            switch ($data['type']) {
                case 'authenticate':
                    $this->handleAuthentication($from, $data);
                    break;
                    
                case 'join_room':
                    $this->handleJoinRoom($from, $data);
                    break;
                    
                case 'leave_room':
                    $this->handleLeaveRoom($from, $data);
                    break;
                    
                case 'chat_message':
                    $this->handleChatMessage($from, $data);
                    break;
                    
                case 'location_update':
                    $this->handleLocationUpdate($from, $data);
                    break;
                    
                case 'job_update':
                    $this->handleJobUpdate($from, $data);
                    break;
                    
                case 'typing_indicator':
                    $this->handleTypingIndicator($from, $data);
                    break;
                    
                case 'job_offer':
                    $this->handleJobOffer($from, $data);
                    break;
                    
                case 'job_accept':
                    $this->handleJobAccept($from, $data);
                    break;
                    
                case 'emergency_alert':
                    $this->handleEmergencyAlert($from, $data);
                    break;
                    
                case 'ping':
                    $from->send(json_encode(['type' => 'pong', 'timestamp' => time()]));
                    break;
                    
                case 'get_online_users':
                    $this->handleGetOnlineUsers($from, $data);
                    break;
                    
                default:
                    $this->sendError($from, 'Unknown message type: ' . $data['type']);
            }
        } catch (Exception $e) {
            $this->logger->error('Message handling error', [
                'connection_id' => $from->resourceId,
                'message' => $msg,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->sendError($from, 'Message processing failed');
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        // Remove from all rooms
        foreach ($conn->bookingRooms as $roomId) {
            $this->leaveRoom($conn, $roomId);
        }
        
        // Remove from user connections
        if ($conn->userId) {
            unset($this->userConnections[$conn->userId]);
            
            // Notify other users that this user went offline
            $this->broadcastUserStatus($conn->userId, $conn->userType, 'offline');
        }
        
        $this->clients->detach($conn);
        
        echo "📴 Connection {$conn->resourceId} ({$conn->userType}:{$conn->userId}) has disconnected\n";
        
        $this->logger->info("Connection closed", [
            'connection_id' => $conn->resourceId,
            'user_id' => $conn->userId,
            'user_type' => $conn->userType
        ]);
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->error('WebSocket connection error', [
            'connection_id' => $conn->resourceId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        echo "💥 Connection error ({$conn->resourceId}): {$e->getMessage()}\n";
        $conn->close();
    }
    
    private function handleAuthentication(ConnectionInterface $conn, $data) {
        $token = $data['token'] ?? null;
        $userType = $data['user_type'] ?? null;
        
        if (!$token || !$userType) {
            $this->sendError($conn, 'Missing authentication data');
            return;
        }
        
        // Validate token against database
        $userData = $this->validateToken($token, $userType);
        
        if (!$userData) {
            $this->sendError($conn, 'Invalid authentication token');
            return;
        }
        
        $conn->authenticated = true;
        $conn->userType = $userType;
        $conn->userId = $userData['id'];
        $conn->userName = $userData['name'];
        
        // Store user connection for direct messaging
        $this->userConnections[$userData['id']] = $conn;
        
        $conn->send(json_encode([
            'type' => 'authenticated',
            'user_id' => $userData['id'],
            'user_type' => $userType,
            'user_name' => $userData['name'],
            'timestamp' => time()
        ]));
        
        // Broadcast that user is online
        $this->broadcastUserStatus($userData['id'], $userType, 'online');
        
        // Send queued messages if any
        $this->sendQueuedMessages($userData['id']);
        
        echo "✅ User authenticated: {$userData['name']} ({$userType})\n";
        
        $this->logger->info('User authenticated', [
            'user_id' => $userData['id'],
            'user_type' => $userType,
            'user_name' => $userData['name'],
            'connection_id' => $conn->resourceId
        ]);
    }
    
    private function handleJoinRoom(ConnectionInterface $conn, $data) {
        if (!$conn->authenticated) {
            $this->sendError($conn, 'Authentication required');
            return;
        }
        
        $roomId = $data['room_id'] ?? null;
        if (!$roomId) {
            $this->sendError($conn, 'Room ID required');
            return;
        }
        
        // Verify user has access to this room (booking)
        if (!$this->canAccessRoom($conn->userId, $conn->userType, $roomId)) {
            $this->sendError($conn, 'Access denied to room');
            return;
        }
        
        $this->joinRoom($conn, $roomId);
        
        echo "👥 {$conn->userName} joined room {$roomId}\n";
    }
    
    private function handleLeaveRoom(ConnectionInterface $conn, $data) {
        $roomId = $data['room_id'] ?? null;
        if (!$roomId) return;
        
        $this->leaveRoom($conn, $roomId);
        
        echo "👋 {$conn->userName} left room {$roomId}\n";
    }
    
    private function handleChatMessage(ConnectionInterface $from, $data) {
        if (!$from->authenticated) {
            $this->sendError($from, 'Authentication required');
            return;
        }
        
        $bookingId = $data['booking_id'] ?? null;
        $message = $data['message'] ?? null;
        
        if (!$bookingId || !$message) {
            $this->sendError($from, 'Missing required fields');
            return;
        }
        
        // Validate message length
        if (strlen($message) > 1000) {
            $this->sendError($from, 'Message too long');
            return;
        }
        
        // Save message to database
        $messageId = $this->saveChatMessage(
            $bookingId,
            $from->userId,
            $from->userType,
            $message
        );
        
        if (!$messageId) {
            $this->sendError($from, 'Failed to save message');
            return;
        }
        
        // Prepare message data
        $messageData = [
            'type' => 'chat_message',
            'message_id' => $messageId,
            'booking_id' => $bookingId,
            'sender_id' => $from->userId,
            'sender_type' => $from->userType,
            'sender_name' => $from->userName,
            'message' => $message,
            'timestamp' => time()
        ];
        
        // Broadcast to all users in booking room
        $this->broadcastToRoom($bookingId, $messageData, $from);
        
        // Send push notification to offline users
        $this->sendChatNotification($bookingId, $from->userId, $from->userType, $message);
        
        // Cache in Redis for quick retrieval
        if ($this->redis) {
            $this->redis->lpush("chat:$bookingId", json_encode($messageData));
            $this->redis->ltrim("chat:$bookingId", 0, 99); // Keep last 100 messages
        }
        
        echo "💬 Message from {$from->userName} in room {$bookingId}\n";
    }
    
    private function handleLocationUpdate(ConnectionInterface $from, $data) {
        if (!$from->authenticated || $from->userType !== 'professional') {
            $this->sendError($from, 'Professional authentication required');
            return;
        }
        
        $bookingId = $data['booking_id'] ?? null;
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        $accuracy = $data['accuracy'] ?? null;
        
        if (!$bookingId || !$latitude || !$longitude) {
            $this->sendError($from, 'Missing location data');
            return;
        }
        
        // Validate coordinates
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            $this->sendError($from, 'Invalid coordinates');
            return;
        }
        
        // Save location to database
        $this->saveLocation($from->userId, $bookingId, $latitude, $longitude, $accuracy);
        
        // Prepare location data
        $locationData = [
            'type' => 'location_update',
            'booking_id' => $bookingId,
            'professional_id' => $from->userId,
            'professional_name' => $from->userName,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'timestamp' => time()
        ];
        
        // Broadcast location to customer in booking room
        $this->broadcastToRoom($bookingId, $locationData, $from);
        
        // Cache current location
        if ($this->redis) {
            $this->redis->setex("location:professional:$from->userId", 300, json_encode($locationData));
        }
        
        echo "📍 Location update from {$from->userName} for booking {$bookingId}\n";
    }
    
    private function handleJobUpdate(ConnectionInterface $from, $data) {
        if (!$from->authenticated) {
            $this->sendError($from, 'Authentication required');
            return;
        }
        
        $bookingId = $data['booking_id'] ?? null;
        $status = $data['status'] ?? null;
        $notes = $data['notes'] ?? '';
        
        if (!$bookingId || !$status) {
            $this->sendError($from, 'Missing required fields');
            return;
        }
        
        // Validate status
        $validStatuses = ['accepted', 'traveling', 'arrived', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            $this->sendError($from, 'Invalid status');
            return;
        }
        
        // Update job status in database
        $this->updateJobStatus($bookingId, $status, $notes, $from->userId);
        
        // Prepare update data
        $updateData = [
            'type' => 'job_update',
            'booking_id' => $bookingId,
            'status' => $status,
            'status_text' => $this->getStatusText($status),
            'notes' => $notes,
            'updated_by' => $from->userId,
            'updated_by_type' => $from->userType,
            'updated_by_name' => $from->userName,
            'timestamp' => time()
        ];
        
        // Broadcast to all users in booking room
        $this->broadcastToRoom($bookingId, $updateData);
        
        // Send notification
        $this->sendJobUpdateNotification($bookingId, $status, $notes);
        
        echo "🔄 Job status updated to '{$status}' by {$from->userName} for booking {$bookingId}\n";
    }
    
    private function handleTypingIndicator(ConnectionInterface $from, $data) {
        $bookingId = $data['booking_id'] ?? null;
        $isTyping = $data['is_typing'] ?? false;
        
        if (!$bookingId) return;
        
        $typingData = [
            'type' => 'typing_indicator',
            'booking_id' => $bookingId,
            'user_id' => $from->userId,
            'user_type' => $from->userType,
            'user_name' => $from->userName,
            'is_typing' => $isTyping,
            'timestamp' => time()
        ];
        
        $this->broadcastToRoom($bookingId, $typingData, $from);
    }
    
    private function handleJobOffer(ConnectionInterface $from, $data) {
        if (!$from->authenticated || $from->userType !== 'admin') {
            $this->sendError($from, 'Admin authentication required');
            return;
        }
        
        $jobId = $data['job_id'] ?? null;
        $professionalIds = $data['professional_ids'] ?? [];
        
        if (!$jobId || empty($professionalIds)) {
            $this->sendError($from, 'Missing job or professional data');
            return;
        }
        
        // Get job details
        $jobDetails = $this->getJobDetails($jobId);
        if (!$jobDetails) {
            $this->sendError($from, 'Job not found');
            return;
        }
        
        // Send job offer to each professional
        foreach ($professionalIds as $professionalId) {
            if (isset($this->userConnections[$professionalId])) {
                $this->userConnections[$professionalId]->send(json_encode([
                    'type' => 'new_job',
                    'job_id' => $jobId,
                    'job_details' => $jobDetails,
                    'timestamp' => time()
                ]));
            }
        }
        
        echo "📋 Job offer sent to " . count($professionalIds) . " professionals\n";
    }
    
    private function handleJobAccept(ConnectionInterface $from, $data) {
        if (!$from->authenticated || $from->userType !== 'professional') {
            $this->sendError($from, 'Professional authentication required');
            return;
        }
        
        $jobId = $data['job_id'] ?? null;
        
        if (!$jobId) {
            $this->sendError($from, 'Job ID required');
            return;
        }
        
        // Check if job is still available
        if (!$this->isJobAvailable($jobId)) {
            $this->sendError($from, 'Job no longer available');
            return;
        }
        
        // Assign job to professional
        $bookingId = $this->assignJobToProfessional($jobId, $from->userId);
        
        if ($bookingId) {
            $from->send(json_encode([
                'type' => 'job_accepted',
                'booking_id' => $bookingId,
                'timestamp' => time()
            ]));
            
            // Notify other professionals that job is taken
            $this->notifyJobTaken($jobId, $from->userId);
            
            echo "✅ Job {$jobId} accepted by {$from->userName}\n";
        } else {
            $this->sendError($from, 'Failed to assign job');
        }
    }
    
    private function handleEmergencyAlert(ConnectionInterface $from, $data) {
        if (!$from->authenticated) {
            $this->sendError($from, 'Authentication required');
            return;
        }
        
        $alertType = $data['alert_type'] ?? null;
        $location = $data['location'] ?? null;
        $details = $data['details'] ?? '';
        
        if (!$alertType) {
            $this->sendError($from, 'Alert type required');
            return;
        }
        
        // Log emergency alert
        $this->logger->critical('Emergency alert', [
            'user_id' => $from->userId,
            'user_type' => $from->userType,
            'alert_type' => $alertType,
            'location' => $location,
            'details' => $details
        ]);
        
        // Notify all admin users
        $this->notifyAdmins([
            'type' => 'emergency_alert',
            'user_id' => $from->userId,
            'user_type' => $from->userType,
            'user_name' => $from->userName,
            'alert_type' => $alertType,
            'location' => $location,
            'details' => $details,
            'timestamp' => time()
        ]);
        
        echo "🚨 EMERGENCY ALERT from {$from->userName}: {$alertType}\n";
    }
    
    private function handleGetOnlineUsers(ConnectionInterface $from, $data) {
        if (!$from->authenticated) {
            $this->sendError($from, 'Authentication required');
            return;
        }
        
        $onlineUsers = [];
        
        foreach ($this->userConnections as $userId => $conn) {
            if ($conn->authenticated) {
                $onlineUsers[] = [
                    'user_id' => $userId,
                    'user_type' => $conn->userType,
                    'user_name' => $conn->userName,
                    'last_seen' => $conn->lastPing
                ];
            }
        }
        
        $from->send(json_encode([
            'type' => 'online_users',
            'users' => $onlineUsers,
            'timestamp' => time()
        ]));
    }
    
    // Room Management
    private function joinRoom(ConnectionInterface $conn, $roomId) {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = new \SplObjectStorage;
        }
        
        $this->rooms[$roomId]->attach($conn);
        $conn->bookingRooms[] = $roomId;
        
        $conn->send(json_encode([
            'type' => 'room_joined',
            'room_id' => $roomId,
            'timestamp' => time()
        ]));
        
        // Load recent messages for this room
        $recentMessages = $this->getRecentMessages($roomId);
        if ($recentMessages) {
            $conn->send(json_encode([
                'type' => 'message_history',
                'room_id' => $roomId,
                'messages' => $recentMessages,
                'timestamp' => time()
            ]));
        }
        
        // Notify other room members
        $this->broadcastToRoom($roomId, [
            'type' => 'user_joined',
            'room_id' => $roomId,
            'user_id' => $conn->userId,
            'user_type' => $conn->userType,
            'user_name' => $conn->userName,
            'timestamp' => time()
        ], $conn);
    }
    
    private function leaveRoom(ConnectionInterface $conn, $roomId) {
        if (isset($this->rooms[$roomId])) {
            $this->rooms[$roomId]->detach($conn);
            
            // Notify other room members
            $this->broadcastToRoom($roomId, [
                'type' => 'user_left',
                'room_id' => $roomId,
                'user_id' => $conn->userId,
                'user_type' => $conn->userType,
                'user_name' => $conn->userName,
                'timestamp' => time()
            ], $conn);
            
            if (count($this->rooms[$roomId]) === 0) {
                unset($this->rooms[$roomId]);
            }
        }
        
        $conn->bookingRooms = array_filter($conn->bookingRooms, function($room) use ($roomId) {
            return $room !== $roomId;
        });
        
        $conn->send(json_encode([
            'type' => 'room_left',
            'room_id' => $roomId,
            'timestamp' => time()
        ]));
    }
    
    private function broadcastToRoom($roomId, $message, ConnectionInterface $exclude = null) {
        if (!isset($this->rooms[$roomId])) return;
        
        $messageJson = json_encode($message);
        $count = 0;
        
        foreach ($this->rooms[$roomId] as $client) {
            if ($client !== $exclude && $client->authenticated) {
                $client->send($messageJson);
                $count++;
            }
        }
        
        // Also cache the message in Redis if it's a chat message
        if ($this->redis && $message['type'] === 'chat_message') {
            $this->redis->lpush("recent:$roomId", $messageJson);
            $this->redis->ltrim("recent:$roomId", 0, 49); // Keep last 50 messages
        }
        
        return $count;
    }
    
    private function broadcastUserStatus($userId, $userType, $status) {
        $statusMessage = [
            'type' => 'user_status',
            'user_id' => $userId,
            'user_type' => $userType,
            'status' => $status,
            'timestamp' => time()
        ];
        
        $messageJson = json_encode($statusMessage);
        
        foreach ($this->clients as $client) {
            if ($client->authenticated && $client->userId !== $userId) {
                $client->send($messageJson);
            }
        }
    }
    
    private function sendError(ConnectionInterface $conn, $message) {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => time()
        ]));
        
        $this->logger->warning('Error sent to client', [
            'connection_id' => $conn->resourceId,
            'user_id' => $conn->userId,
            'message' => $message
        ]);
    }
    
    // Database Helper Methods
    private function validateToken($token, $userType) {
        try {
            $table = $userType === 'professional' ? 'professionals' : 
                    ($userType === 'customer' ? 'customers' : 'admin_users');
            
            $stmt = $this->db->prepare("
                SELECT id, name, email 
                FROM {$table} 
                WHERE auth_token = ? AND token_expires_at > NOW()
            ");
            $stmt->execute([$token]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error('Token validation failed', [
                'error' => $e->getMessage(),
                'user_type' => $userType
            ]);
            return false;
        }
    }
    
    private function canAccessRoom($userId, $userType, $roomId) {
        try {
            if ($userType === 'admin') {
                return true; // Admins can access any room
            }
            
            if ($userType === 'customer') {
                $stmt = $this->db->prepare("SELECT 1 FROM bookings WHERE id = ? AND customer_id = ?");
            } else {
                $stmt = $this->db->prepare("SELECT 1 FROM bookings WHERE id = ? AND professional_id = ?");
            }
            
            $stmt->execute([$roomId, $userId]);
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            $this->logger->error('Room access check failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'room_id' => $roomId
            ]);
            return false;
        }
    }
    
    private function saveChatMessage($bookingId, $userId, $userType, $message) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_messages (booking_id, sender_id, sender_type, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$bookingId, $userId, $userType, $message]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            $this->logger->error('Failed to save chat message', [
                'error' => $e->getMessage(),
                'booking_id' => $bookingId,
                'user_id' => $userId
            ]);
            return false;
        }
    }
    
    private function saveLocation($professionalId, $bookingId, $latitude, $longitude, $accuracy) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO location_tracking 
                (professional_id, booking_id, latitude, longitude, accuracy, recorded_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$professionalId, $bookingId, $latitude, $longitude, $accuracy]);
        } catch (Exception $e) {
            $this->logger->error('Failed to save location', [
                'error' => $e->getMessage(),
                'professional_id' => $professionalId,
                'booking_id' => $bookingId
            ]);
        }
    }
    
    private function updateJobStatus($bookingId, $status, $notes, $updatedBy) {
        try {
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET status = ?, 
                    notes = CONCAT(COALESCE(notes, ''), IF(notes IS NULL OR notes = '', '', '\n'), ?),
                    updated_at = NOW(),
                    updated_by = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$status, $notes, $updatedBy, $bookingId]);
            
            // Also log the status change
            $stmt = $this->db->prepare("
                INSERT INTO booking_status_history 
                (booking_id, old_status, new_status, changed_by, notes, changed_at)
                VALUES (?, 
                    (SELECT status FROM bookings WHERE id = ?), 
                    ?, ?, ?, NOW())
            ");
            
            // Note: This is a simplified version - in reality you'd need to handle the subquery better
        } catch (Exception $e) {
            $this->logger->error('Failed to update job status', [
                'error' => $e->getMessage(),
                'booking_id' => $bookingId,
                'status' => $status
            ]);
        }
    }
    
    private function getRecentMessages($roomId, $limit = 20) {
        try {
            // First try Redis cache
            if ($this->redis) {
                $cachedMessages = $this->redis->lrange("recent:$roomId", 0, $limit - 1);
                if ($cachedMessages) {
                    return array_map('json_decode', array_reverse($cachedMessages));
                }
            }
            
            // Fallback to database
            $stmt = $this->db->prepare("
                SELECT 
                    cm.id as message_id,
                    cm.message,
                    cm.sender_id,
                    cm.sender_type,
                    cm.created_at,
                    COALESCE(c.name, p.name, 'Unknown') as sender_name
                FROM chat_messages cm
                LEFT JOIN customers c ON cm.sender_id = c.id AND cm.sender_type = 'customer'
                LEFT JOIN professionals p ON cm.sender_id = p.id AND cm.sender_type = 'professional'
                WHERE cm.booking_id = ?
                ORDER BY cm.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$roomId, $limit]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return array_reverse($messages);
        } catch (Exception $e) {
            $this->logger->error('Failed to get recent messages', [
                'error' => $e->getMessage(),
                'room_id' => $roomId
            ]);
            return [];
        }
    }
    
    private function getJobDetails($jobId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    b.*,
                    s.name as service_name,
                    s.description as service_description,
                    c.name as customer_name
                FROM bookings b
                JOIN services s ON b.service_id = s.id
                JOIN customers c ON b.customer_id = c.id
                WHERE b.id = ?
            ");
            
            $stmt->execute([$jobId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error('Failed to get job details', [
                'error' => $e->getMessage(),
                'job_id' => $jobId
            ]);
            return null;
        }
    }
    
    private function isJobAvailable($jobId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 1 FROM bookings 
                WHERE id = ? AND professional_id IS NULL AND status = 'pending'
            ");
            $stmt->execute([$jobId]);
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function assignJobToProfessional($jobId, $professionalId) {
        try {
            $this->db->beginTransaction();
            
            // Check if still available
            $stmt = $this->db->prepare("
                SELECT id FROM bookings 
                WHERE id = ? AND professional_id IS NULL AND status = 'pending'
                FOR UPDATE
            ");
            $stmt->execute([$jobId]);
            
            if (!$stmt->fetchColumn()) {
                $this->db->rollback();
                return false;
            }
            
            // Assign professional
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET professional_id = ?, status = 'accepted', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$professionalId, $jobId]);
            
            $this->db->commit();
            return $jobId;
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->error('Failed to assign job', [
                'error' => $e->getMessage(),
                'job_id' => $jobId,
                'professional_id' => $professionalId
            ]);
            return false;
        }
    }
    
    private function notifyJobTaken($jobId, $acceptedBy) {
        foreach ($this->userConnections as $userId => $conn) {
            if ($conn->userType === 'professional' && $userId !== $acceptedBy) {
                $conn->send(json_encode([
                    'type' => 'job_taken',
                    'job_id' => $jobId,
                    'accepted_by' => $acceptedBy,
                    'timestamp' => time()
                ]));
            }
        }
    }
    
    private function notifyAdmins($message) {
        foreach ($this->userConnections as $userId => $conn) {
            if ($conn->userType === 'admin') {
                $conn->send(json_encode($message));
            }
        }
    }
    
    private function sendQueuedMessages($userId) {
        // Implementation would check for any queued messages for this user
        // and send them upon connection
    }
    
    private function sendChatNotification($bookingId, $senderId, $senderType, $message) {
        // Implementation would send push notifications to offline users
        // This would integrate with Firebase FCM or similar service
        
        // For now, just log it
        $this->logger->info('Chat notification queued', [
            'booking_id' => $bookingId,
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'message_preview' => substr($message, 0, 50)
        ]);
    }
    
    private function sendJobUpdateNotification($bookingId, $status, $notes) {
        // Implementation would send push notifications about job status changes
        
        $this->logger->info('Job update notification queued', [
            'booking_id' => $bookingId,
            'status' => $status,
            'notes' => $notes
        ]);
    }
    
    private function getStatusText($status) {
        $statusTexts = [
            'accepted' => 'Job Accepted',
            'traveling' => 'On the Way',
            'arrived' => 'Arrived at Location',
            'in_progress' => 'Cleaning in Progress',
            'completed' => 'Cleaning Completed',
            'cancelled' => 'Job Cancelled'
        ];
        
        return $statusTexts[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
    
    private function getClientIp(ConnectionInterface $conn) {
        // This would need to be implemented based on your proxy setup
        return 'unknown';
    }
    
    // Cleanup methods
    public function cleanupInactiveConnections() {
        $currentTime = time();
        
        foreach ($this->clients as $client) {
            // Close connections inactive for more than 5 minutes
            if ($currentTime - $client->lastPing > 300) {
                echo "🧹 Closing inactive connection {$client->resourceId}\n";
                $client->close();
            }
        }
    }
    
    public function getStats() {
        return [
            'total_connections' => count($this->clients),
            'authenticated_users' => count($this->userConnections),
            'active_rooms' => count($this->rooms),
            'memory_usage' => memory_get_usage(true),
            'uptime' => time() - $this->startTime ?? time()
        ];
    }
}

// WebSocket Server Startup
try {
    echo "🔧 Initializing WebSocket server...\n";
    
    $wsServer = new BlueWebSocketServer();
    
    $server = IoServer::factory(
        new HttpServer(
            new WsServer($wsServer)
        ),
        8080
    );
    
    // Setup periodic cleanup
    $server->loop->addPeriodicTimer(60, function() use ($wsServer) {
        $wsServer->cleanupInactiveConnections();
        
        $stats = $wsServer->getStats();
        echo "📊 Stats: {$stats['authenticated_users']} users, {$stats['active_rooms']} rooms, " . 
             round($stats['memory_usage']/1024/1024, 1) . "MB memory\n";
    });
    
    // Setup graceful shutdown
    $server->loop->addSignal(SIGTERM, function() use ($server) {
        echo "🛑 Shutting down gracefully...\n";
        $server->socket->close();
    });
    
    echo "🌐 Blue WebSocket Server running on port 8080\n";
    echo "📱 Ready to handle professional and customer connections\n";
    echo "Press Ctrl+C to stop\n\n";
    
    $server->run();
    
} catch (Exception $e) {
    echo "💥 Failed to start WebSocket server: " . $e->getMessage() . "\n";
    exit(1);
}
