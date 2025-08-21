<?php
/**
 * API de Chat em Tempo Real - Blue Project V2
 * Sistema de comunicação entre clientes e profissionais
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Include dependencies
require_once '../../booking2.php';

try {
    $action = $_GET['action'] ?? 'get_messages';
    
    switch ($action) {
        case 'get_messages':
            handleGetMessages();
            break;
        case 'send_message':
            handleSendMessage();
            break;
        case 'mark_read':
            handleMarkRead();
            break;
        case 'get_conversations':
            handleGetConversations();
            break;
        case 'start_conversation':
            handleStartConversation();
            break;
        case 'upload_media':
            handleUploadMedia();
            break;
        case 'get_typing_status':
            handleGetTypingStatus();
            break;
        case 'set_typing':
            handleSetTyping();
            break;
        default:
            throw new InvalidArgumentException('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get messages for a conversation
 */
function handleGetMessages() {
    $conversationId = $_GET['conversation_id'] ?? null;
    $lastMessageId = $_GET['last_message_id'] ?? 0;
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    
    if (!$conversationId) {
        throw new InvalidArgumentException('Conversation ID is required');
    }
    
    // Verify user has access to this conversation
    $userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
    if (!canAccessConversation($userId, $conversationId)) {
        throw new InvalidArgumentException('Access denied to this conversation');
    }
    
    // Get messages
    $messages = getMessages($conversationId, $lastMessageId, $limit);
    
    // Mark messages as read
    markMessagesAsRead($conversationId, $userId);
    
    // Get conversation info
    $conversationInfo = getConversationInfo($conversationId);
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'messages' => $messages,
        'conversation_info' => $conversationInfo,
        'total_count' => count($messages),
        'has_more' => count($messages) === $limit,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Send a new message
 */
function handleSendMessage() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = $input['conversation_id'] ?? null;
    $senderId = $input['sender_id'] ?? $_SESSION['user_id'] ?? null;
    $message = trim($input['message'] ?? '');
    $messageType = $input['type'] ?? 'text'; // text, image, file, location
    $replyToId = $input['reply_to_id'] ?? null;
    
    if (!$conversationId || !$senderId) {
        throw new InvalidArgumentException('Conversation ID and Sender ID are required');
    }
    
    if (empty($message) && $messageType === 'text') {
        throw new InvalidArgumentException('Message content is required');
    }
    
    // Verify sender has access to conversation
    if (!canAccessConversation($senderId, $conversationId)) {
        throw new InvalidArgumentException('Access denied to this conversation');
    }
    
    // Rate limiting
    if (!canSendMessage($senderId)) {
        throw new Exception('Rate limit exceeded. Please wait before sending another message.');
    }
    
    // Process message based on type
    $messageData = processMessage($message, $messageType, $input);
    
    // Save message
    $messageId = saveMessage([
        'conversation_id' => $conversationId,
        'sender_id' => $senderId,
        'message' => $messageData['content'],
        'message_type' => $messageType,
        'metadata' => json_encode($messageData['metadata']),
        'reply_to_id' => $replyToId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($messageId) {
        // Get the saved message with sender info
        $savedMessage = getMessageById($messageId);
        
        // Send push notifications to other participants
        sendMessageNotifications($conversationId, $senderId, $savedMessage);
        
        // Update conversation last activity
        updateConversationActivity($conversationId);
        
        // Clear typing status
        clearTypingStatus($conversationId, $senderId);
        
        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'message' => $savedMessage,
            'conversation_id' => $conversationId,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to send message');
    }
}

/**
 * Mark messages as read
 */
function handleMarkRead() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = $input['conversation_id'] ?? null;
    $userId = $input['user_id'] ?? $_SESSION['user_id'] ?? null;
    $messageIds = $input['message_ids'] ?? [];
    
    if (!$conversationId || !$userId) {
        throw new InvalidArgumentException('Conversation ID and User ID are required');
    }
    
    // Verify access
    if (!canAccessConversation($userId, $conversationId)) {
        throw new InvalidArgumentException('Access denied to this conversation');
    }
    
    // Mark messages as read
    $result = markMessagesAsRead($conversationId, $userId, $messageIds);
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'marked_count' => $result['marked_count'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get user's conversations
 */
function handleGetConversations() {
    $userId = $_GET['user_id'] ?? $_SESSION['user_id'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    $status = $_GET['status'] ?? 'all'; // all, active, archived
    
    if (!$userId) {
        throw new InvalidArgumentException('User ID is required');
    }
    
    // Get conversations
    $conversations = getUserConversations($userId, $page, $limit, $status);
    
    // Add unread counts and last message info
    foreach ($conversations as &$conversation) {
        $conversation['unread_count'] = getUnreadCount($conversation['id'], $userId);
        $conversation['last_message'] = getLastMessage($conversation['id']);
        $conversation['other_participant'] = getOtherParticipant($conversation['id'], $userId);
    }
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations,
        'page' => $page,
        'limit' => $limit,
        'total_count' => count($conversations),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Start a new conversation
 */
function handleStartConversation() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $initiatorId = $input['initiator_id'] ?? $_SESSION['user_id'] ?? null;
    $participantId = $input['participant_id'] ?? null;
    $bookingId = $input['booking_id'] ?? null;
    $initialMessage = trim($input['initial_message'] ?? '');
    
    if (!$initiatorId || !$participantId) {
        throw new InvalidArgumentException('Initiator ID and Participant ID are required');
    }
    
    if ($initiatorId === $participantId) {
        throw new InvalidArgumentException('Cannot start conversation with yourself');
    }
    
    // Check if conversation already exists
    $existingConversation = findExistingConversation($initiatorId, $participantId, $bookingId);
    
    if ($existingConversation) {
        echo json_encode([
            'success' => true,
            'conversation_id' => $existingConversation['id'],
            'existing' => true,
            'conversation' => $existingConversation
        ]);
        return;
    }
    
    // Create new conversation
    $conversationId = createConversation([
        'initiator_id' => $initiatorId,
        'participant_id' => $participantId,
        'booking_id' => $bookingId,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($conversationId) {
        // Send initial message if provided
        if (!empty($initialMessage)) {
            saveMessage([
                'conversation_id' => $conversationId,
                'sender_id' => $initiatorId,
                'message' => $initialMessage,
                'message_type' => 'text',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Get conversation details
        $conversation = getConversationInfo($conversationId);
        
        echo json_encode([
            'success' => true,
            'conversation_id' => $conversationId,
            'existing' => false,
            'conversation' => $conversation
        ]);
    } else {
        throw new Exception('Failed to create conversation');
    }
}

/**
 * Upload media files
 */
function handleUploadMedia() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $conversationId = $_POST['conversation_id'] ?? null;
    $senderId = $_POST['sender_id'] ?? $_SESSION['user_id'] ?? null;
    
    if (!$conversationId || !$senderId) {
        throw new InvalidArgumentException('Conversation ID and Sender ID are required');
    }
    
    // Verify access
    if (!canAccessConversation($senderId, $conversationId)) {
        throw new InvalidArgumentException('Access denied to this conversation');
    }
    
    if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('No valid file uploaded');
    }
    
    $file = $_FILES['media'];
    
    // Validate file
    $validation = validateUploadedFile($file);
    if (!$validation['valid']) {
        throw new InvalidArgumentException($validation['error']);
    }
    
    // Process upload
    $uploadResult = processMediaUpload($file, $conversationId, $senderId);
    
    if ($uploadResult['success']) {
        echo json_encode([
            'success' => true,
            'file_url' => $uploadResult['file_url'],
            'file_type' => $uploadResult['file_type'],
            'file_size' => $uploadResult['file_size'],
            'thumbnail_url' => $uploadResult['thumbnail_url'] ?? null,
            'upload_id' => $uploadResult['upload_id']
        ]);
    } else {
        throw new Exception($uploadResult['error']);
    }
}

/**
 * Get typing status
 */
function handleGetTypingStatus() {
    $conversationId = $_GET['conversation_id'] ?? null;
    $userId = $_GET['user_id'] ?? $_SESSION['user_id'] ?? null;
    
    if (!$conversationId || !$userId) {
        throw new InvalidArgumentException('Conversation ID and User ID are required');
    }
    
    // Verify access
    if (!canAccessConversation($userId, $conversationId)) {
        throw new InvalidArgumentException('Access denied to this conversation');
    }
    
    $typingUsers = getTypingUsers($conversationId, $userId);
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'typing_users' => $typingUsers,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Set typing status
 */
function handleSetTyping() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = $input['conversation_id'] ?? null;
    $userId = $input['user_id'] ?? $_SESSION['user_id'] ?? null;
    $isTyping = $input['is_typing'] ?? false;
    
    if (!$conversationId || !$userId) {
        throw new InvalidArgumentException('Conversation ID and User ID are required');
    }
    
    // Verify access
    if (!canAccessConversation($userId, $conversationId)) {
        throw new InvalidArgumentException('Access denied to this conversation');
    }
    
    if ($isTyping) {
        setTypingStatus($conversationId, $userId);
    } else {
        clearTypingStatus($conversationId, $userId);
    }
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'is_typing' => $isTyping,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Helper Functions
 */

function canAccessConversation($userId, $conversationId) {
    // Check if user is participant in conversation
    // This would typically query your database
    return true; // Simplified for demo
}

function canSendMessage($senderId) {
    // Rate limiting: max 10 messages per minute
    $key = "rate_limit_messages_" . $senderId;
    $current = getCache($key, 0);
    
    if ($current >= 10) {
        return false;
    }
    
    setCache($key, $current + 1, 60); // 60 seconds TTL
    return true;
}

function getMessages($conversationId, $lastMessageId = 0, $limit = 50) {
    // Simulate message data - replace with actual database query
    $sampleMessages = [
        [
            'id' => 1,
            'conversation_id' => $conversationId,
            'sender_id' => 'prof_123',
            'sender_name' => 'Carlos Silva',
            'sender_type' => 'professional',
            'sender_avatar' => '/assets/avatars/carlos.jpg',
            'message' => 'Olá! Estou a caminho da sua casa. Chego em aproximadamente 15 minutos.',
            'message_type' => 'text',
            'metadata' => null,
            'reply_to_id' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'read_at' => date('Y-m-d H:i:s', strtotime('-25 minutes')),
            'is_read' => true
        ],
        [
            'id' => 2,
            'conversation_id' => $conversationId,
            'sender_id' => 'cust_456',
            'sender_name' => 'Maria Silva',
            'sender_type' => 'customer',
            'sender_avatar' => '/assets/avatars/maria.jpg',
            'message' => 'Perfeito! Estarei em casa esperando.',
            'message_type' => 'text',
            'metadata' => null,
            'reply_to_id' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-28 minutes')),
            'read_at' => date('Y-m-d H:i:s', strtotime('-27 minutes')),
            'is_read' => true
        ],
        [
            'id' => 3,
            'conversation_id' => $conversationId,
            'sender_id' => 'prof_123',
            'sender_name' => 'Carlos Silva',
            'sender_type' => 'professional',
            'sender_avatar' => '/assets/avatars/carlos.jpg',
            'message' => 'Ótimo! Lembre-se de deixar os pets em um local seguro durante a limpeza.',
            'message_type' => 'text',
            'metadata' => null,
            'reply_to_id' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-25 minutes')),
            'read_at' => null,
            'is_read' => false
        ],
        [
            'id' => 4,
            'conversation_id' => $conversationId,
            'sender_id' => 'prof_123',
            'sender_name' => 'Carlos Silva',
            'sender_type' => 'professional',
            'sender_avatar' => '/assets/avatars/carlos.jpg',
            'message' => 'Chegando em 2 minutos!',
            'message_type' => 'text',
            'metadata' => null,
            'reply_to_id' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
            'read_at' => null,
            'is_read' => false
        ]
    ];
    
    // Filter by last message ID
    return array_filter($sampleMessages, function($msg) use ($lastMessageId) {
        return $msg['id'] > $lastMessageId;
    });
}

function processMessage($message, $type, $input) {
    $metadata = [];
    
    switch ($type) {
        case 'text':
            $content = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            break;
            
        case 'image':
            $content = $input['image_url'] ?? '';
            $metadata = [
                'width' => $input['width'] ?? null,
                'height' => $input['height'] ?? null,
                'thumbnail_url' => $input['thumbnail_url'] ?? null
            ];
            break;
            
        case 'file':
            $content = $input['file_url'] ?? '';
            $metadata = [
                'filename' => $input['filename'] ?? '',
                'file_size' => $input['file_size'] ?? 0,
                'mime_type' => $input['mime_type'] ?? ''
            ];
            break;
            
        case 'location':
            $content = $input['location_name'] ?? 'Localização compartilhada';
            $metadata = [
                'latitude' => $input['latitude'] ?? null,
                'longitude' => $input['longitude'] ?? null,
                'address' => $input['address'] ?? ''
            ];
            break;
            
        default:
            $content = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
    
    return [
        'content' => $content,
        'metadata' => $metadata
    ];
}

function saveMessage($messageData) {
    // Save message to database and return ID
    // This would be your actual database insertion
    return rand(1000, 9999); // Simulated message ID
}

function getMessageById($messageId) {
    // Get message details from database
    return [
        'id' => $messageId,
        'conversation_id' => 'conv_123',
        'sender_id' => 'user_456',
        'sender_name' => 'Current User',
        'message' => 'Message content',
        'message_type' => 'text',
        'created_at' => date('Y-m-d H:i:s'),
        'is_read' => false
    ];
}

function sendMessageNotifications($conversationId, $senderId, $message) {
    // Send push notifications to other participants
    $participants = getConversationParticipants($conversationId);
    
    foreach ($participants as $participant) {
        if ($participant['id'] !== $senderId) {
            sendPushNotification($participant['device_token'], [
                'title' => $message['sender_name'],
                'body' => truncateMessage($message['message']),
                'data' => [
                    'type' => 'new_message',
                    'conversation_id' => $conversationId,
                    'message_id' => $message['id']
                ]
            ]);
        }
    }
}

function truncateMessage($message, $length = 100) {
    return strlen($message) > $length ? substr($message, 0, $length) . '...' : $message;
}

function getConversationInfo($conversationId) {
    // Get conversation details
    return [
        'id' => $conversationId,
        'booking_id' => 'booking_123',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'participants' => [
            [
                'id' => 'prof_123',
                'name' => 'Carlos Silva',
                'type' => 'professional',
                'avatar' => '/assets/avatars/carlos.jpg',
                'online' => true,
                'last_seen' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'cust_456',
                'name' => 'Maria Silva',
                'type' => 'customer',
                'avatar' => '/assets/avatars/maria.jpg',
                'online' => false,
                'last_seen' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
            ]
        ]
    ];
}

function markMessagesAsRead($conversationId, $userId, $messageIds = []) {
    // Mark messages as read in database
    return ['marked_count' => count($messageIds)];
}

function getUserConversations($userId, $page, $limit, $status) {
    // Get user's conversations from database
    return [
        [
            'id' => 'conv_123',
            'booking_id' => 'booking_456',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
        ]
    ];
}

function getUnreadCount($conversationId, $userId) {
    // Count unread messages for user in conversation
    return rand(0, 5);
}

function getLastMessage($conversationId) {
    // Get last message in conversation
    return [
        'id' => 4,
        'message' => 'Chegando em 2 minutos!',
        'sender_name' => 'Carlos Silva',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes'))
    ];
}

function getOtherParticipant($conversationId, $userId) {
    // Get the other participant in conversation
    return [
        'id' => 'prof_123',
        'name' => 'Carlos Silva',
        'type' => 'professional',
        'avatar' => '/assets/avatars/carlos.jpg',
        'online' => true
    ];
}

// Additional helper functions
function findExistingConversation($user1, $user2, $bookingId = null) { return null; }
function createConversation($data) { return 'conv_' . uniqid(); }
function updateConversationActivity($conversationId) {}
function validateUploadedFile($file) { return ['valid' => true]; }
function processMediaUpload($file, $conversationId, $senderId) { return ['success' => true, 'file_url' => '/uploads/media.jpg']; }
function getTypingUsers($conversationId, $excludeUserId) { return []; }
function setTypingStatus($conversationId, $userId) {}
function clearTypingStatus($conversationId, $userId) {}
function getConversationParticipants($conversationId) { return []; }
function sendPushNotification($deviceToken, $data) {}
function getCache($key, $default = null) { return $default; }
function setCache($key, $value, $ttl) {}
?>
