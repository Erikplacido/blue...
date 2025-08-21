/**
 * Live Chat System with Media Support
 * Blue Cleaning Services - Real-time Messaging Platform
 */

<?php
require_once __DIR__ . '/../config/australian-database.php';
require_once __DIR__ . '/websocket-server.php';

class LiveChatSystem {
    private $pdo;
    private $wsServer;
    private $maxFileSize;
    private $allowedFileTypes;
    private $mediaUploadPath;
    
    public function __construct() {
        $this->pdo = AustralianDatabase::getInstance()->getConnection();
        $this->wsServer = new WebSocketServer();
        
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB
        $this->allowedFileTypes = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'document' => ['pdf', 'doc', 'docx', 'txt', 'rtf'],
            'video' => ['mp4', 'webm', 'mov', 'avi'],
            'audio' => ['mp3', 'wav', 'ogg', 'aac']
        ];
        
        $this->mediaUploadPath = __DIR__ . '/../../uploads/chat/';
        
        if (!is_dir($this->mediaUploadPath)) {
            mkdir($this->mediaUploadPath, 0755, true);
        }
    }
    
    /**
     * Send a text message
     */
    public function sendMessage($senderId, $receiverId, $message, $chatType = 'support') {
        try {
            // Validate and sanitize message
            $message = trim($message);
            if (empty($message) || strlen($message) > 5000) {
                throw new Exception('Invalid message length');
            }
            
            // Insert message into database
            $stmt = $this->pdo->prepare("
                INSERT INTO chat_messages (
                    sender_id, receiver_id, message_type, message_content,
                    chat_type, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $senderId,
                $receiverId,
                'text',
                $message,
                $chatType,
                'sent'
            ]);
            
            $messageId = $this->pdo->lastInsertId();
            
            // Get complete message data
            $messageData = $this->getMessageById($messageId);
            
            // Send via WebSocket
            $this->broadcastMessage($messageData, $receiverId);
            
            // Send push notification if user offline
            $this->sendPushNotificationIfOffline($receiverId, $messageData);
            
            return [
                'success' => true,
                'message_id' => $messageId,
                'message' => $messageData
            ];
            
        } catch (Exception $e) {
            error_log("Chat send message error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload and send media message
     */
    public function sendMediaMessage($senderId, $receiverId, $file, $caption = '', $chatType = 'support') {
        try {
            // Validate file upload
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed');
            }
            
            if ($file['size'] > $this->maxFileSize) {
                throw new Exception('File size exceeds maximum limit (10MB)');
            }
            
            $fileInfo = $this->validateAndProcessFile($file);
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($fileInfo['extension']);
            $filePath = $this->mediaUploadPath . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Create thumbnail for images/videos
            $thumbnailPath = null;
            if (in_array($fileInfo['type'], ['image', 'video'])) {
                $thumbnailPath = $this->createThumbnail($filePath, $fileInfo['type']);
            }
            
            // Insert media message into database
            $stmt = $this->pdo->prepare("
                INSERT INTO chat_messages (
                    sender_id, receiver_id, message_type, message_content,
                    media_filename, media_type, media_size, thumbnail_path,
                    chat_type, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $senderId,
                $receiverId,
                'media',
                $caption,
                $filename,
                $fileInfo['type'],
                $file['size'],
                $thumbnailPath,
                $chatType,
                'sent'
            ]);
            
            $messageId = $this->pdo->lastInsertId();
            
            // Get complete message data
            $messageData = $this->getMessageById($messageId);
            
            // Send via WebSocket
            $this->broadcastMessage($messageData, $receiverId);
            
            // Send push notification
            $this->sendPushNotificationIfOffline($receiverId, $messageData);
            
            return [
                'success' => true,
                'message_id' => $messageId,
                'message' => $messageData
            ];
            
        } catch (Exception $e) {
            error_log("Chat send media error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get chat history between two users
     */
    public function getChatHistory($userId1, $userId2, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT cm.*, 
                       u1.name as sender_name,
                       u1.avatar as sender_avatar,
                       u2.name as receiver_name
                FROM chat_messages cm
                JOIN users u1 ON cm.sender_id = u1.id
                JOIN users u2 ON cm.receiver_id = u2.id
                WHERE (cm.sender_id = ? AND cm.receiver_id = ?) 
                   OR (cm.sender_id = ? AND cm.receiver_id = ?)
                ORDER BY cm.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$userId1, $userId2, $userId2, $userId1, $limit, $offset]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process messages for frontend
            foreach ($messages as &$message) {
                $message = $this->processMessageForOutput($message);
            }
            
            return [
                'success' => true,
                'messages' => array_reverse($messages) // Return in chronological order
            ];
            
        } catch (Exception $e) {
            error_log("Get chat history error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user's chat conversations
     */
    public function getUserConversations($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT
                    CASE 
                        WHEN cm.sender_id = ? THEN cm.receiver_id 
                        ELSE cm.sender_id 
                    END as contact_id,
                    u.name as contact_name,
                    u.avatar as contact_avatar,
                    u.user_type,
                    u.online_status,
                    u.last_seen,
                    (SELECT message_content 
                     FROM chat_messages 
                     WHERE (sender_id = ? AND receiver_id = contact_id) 
                        OR (sender_id = contact_id AND receiver_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at 
                     FROM chat_messages 
                     WHERE (sender_id = ? AND receiver_id = contact_id) 
                        OR (sender_id = contact_id AND receiver_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_message_time,
                    (SELECT COUNT(*) 
                     FROM chat_messages 
                     WHERE sender_id = contact_id 
                       AND receiver_id = ? 
                       AND status = 'sent') as unread_count
                FROM chat_messages cm
                JOIN users u ON u.id = CASE 
                    WHEN cm.sender_id = ? THEN cm.receiver_id 
                    ELSE cm.sender_id 
                END
                WHERE cm.sender_id = ? OR cm.receiver_id = ?
                ORDER BY last_message_time DESC
            ");
            
            $stmt->execute([
                $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId
            ]);
            
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'conversations' => $conversations
            ];
            
        } catch (Exception $e) {
            error_log("Get conversations error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark messages as read
     */
    public function markMessagesAsRead($userId, $senderId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE chat_messages 
                SET status = 'read', read_at = NOW()
                WHERE receiver_id = ? AND sender_id = ? AND status = 'sent'
            ");
            
            $stmt->execute([$userId, $senderId]);
            
            // Notify sender via WebSocket
            $this->wsServer->sendToUser($senderId, [
                'type' => 'messages_read',
                'reader_id' => $userId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'messages_marked' => $stmt->rowCount()
            ];
            
        } catch (Exception $e) {
            error_log("Mark messages read error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete message
     */
    public function deleteMessage($messageId, $userId) {
        try {
            // Check if user owns the message
            $stmt = $this->pdo->prepare("
                SELECT sender_id, media_filename, thumbnail_path 
                FROM chat_messages 
                WHERE id = ?
            ");
            $stmt->execute([$messageId]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$message) {
                throw new Exception('Message not found');
            }
            
            if ($message['sender_id'] != $userId) {
                throw new Exception('Unauthorized to delete this message');
            }
            
            // Delete associated media files
            if ($message['media_filename']) {
                $mediaPath = $this->mediaUploadPath . $message['media_filename'];
                if (file_exists($mediaPath)) {
                    unlink($mediaPath);
                }
                
                if ($message['thumbnail_path']) {
                    $thumbnailPath = $this->mediaUploadPath . $message['thumbnail_path'];
                    if (file_exists($thumbnailPath)) {
                        unlink($thumbnailPath);
                    }
                }
            }
            
            // Mark message as deleted instead of actually deleting
            $stmt = $this->pdo->prepare("
                UPDATE chat_messages 
                SET status = 'deleted', deleted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$messageId]);
            
            return [
                'success' => true,
                'message' => 'Message deleted successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Delete message error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get typing indicators
     */
    public function setTypingIndicator($userId, $targetUserId, $isTyping = true) {
        $data = [
            'type' => 'typing_indicator',
            'user_id' => $userId,
            'is_typing' => $isTyping,
            'timestamp' => time()
        ];
        
        $this->wsServer->sendToUser($targetUserId, $data);
        
        return ['success' => true];
    }
    
    /**
     * Private helper methods
     */
    private function validateAndProcessFile($file) {
        $filename = $file['name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $fileType = null;
        foreach ($this->allowedFileTypes as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                $fileType = $type;
                break;
            }
        }
        
        if (!$fileType) {
            throw new Exception('File type not allowed');
        }
        
        // Additional MIME type validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'application/rtf',
            'video/mp4', 'video/webm', 'video/quicktime', 'video/avi',
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/aac'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Invalid file format detected');
        }
        
        return [
            'type' => $fileType,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'original_name' => $filename
        ];
    }
    
    private function generateUniqueFilename($extension) {
        return date('Y/m/d/') . uniqid('chat_', true) . '.' . $extension;
    }
    
    private function createThumbnail($filePath, $mediaType) {
        $thumbnailDir = dirname($filePath) . '/thumbnails/';
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }
        
        $thumbnailPath = $thumbnailDir . 'thumb_' . basename($filePath);
        
        try {
            if ($mediaType === 'image') {
                return $this->createImageThumbnail($filePath, $thumbnailPath);
            } elseif ($mediaType === 'video') {
                return $this->createVideoThumbnail($filePath, $thumbnailPath);
            }
        } catch (Exception $e) {
            error_log("Thumbnail creation failed: " . $e->getMessage());
        }
        
        return null;
    }
    
    private function createImageThumbnail($sourcePath, $thumbnailPath) {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) return null;
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Calculate thumbnail dimensions (max 200x200)
        $maxDimension = 200;
        if ($sourceWidth > $sourceHeight) {
            $thumbWidth = $maxDimension;
            $thumbHeight = ($sourceHeight * $maxDimension) / $sourceWidth;
        } else {
            $thumbHeight = $maxDimension;
            $thumbWidth = ($sourceWidth * $maxDimension) / $sourceHeight;
        }
        
        // Create source image resource
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return null;
        }
        
        if (!$sourceImage) return null;
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Handle transparency for PNG/GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $thumbnail, $sourceImage,
            0, 0, 0, 0,
            $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight
        );
        
        // Save thumbnail
        $result = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $result = imagejpeg($thumbnail, $thumbnailPath, 85);
                break;
            case 'image/png':
                $result = imagepng($thumbnail, $thumbnailPath, 8);
                break;
            case 'image/gif':
                $result = imagegif($thumbnail, $thumbnailPath);
                break;
            case 'image/webp':
                $result = imagewebp($thumbnail, $thumbnailPath, 85);
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $result ? basename($thumbnailPath) : null;
    }
    
    private function createVideoThumbnail($sourcePath, $thumbnailPath) {
        // This requires FFmpeg to be installed
        $command = sprintf(
            'ffmpeg -i %s -ss 00:00:01 -vframes 1 -vf "scale=200:200:force_original_aspect_ratio=decrease,pad=200:200:-1:-1:black" %s 2>/dev/null',
            escapeshellarg($sourcePath),
            escapeshellarg($thumbnailPath)
        );
        
        exec($command, $output, $returnCode);
        
        return $returnCode === 0 ? basename($thumbnailPath) : null;
    }
    
    private function getMessageById($messageId) {
        $stmt = $this->pdo->prepare("
            SELECT cm.*, 
                   u1.name as sender_name,
                   u1.avatar as sender_avatar,
                   u2.name as receiver_name
            FROM chat_messages cm
            JOIN users u1 ON cm.sender_id = u1.id
            JOIN users u2 ON cm.receiver_id = u2.id
            WHERE cm.id = ?
        ");
        
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $message ? $this->processMessageForOutput($message) : null;
    }
    
    private function processMessageForOutput($message) {
        if ($message['media_filename']) {
            $message['media_url'] = '/uploads/chat/' . $message['media_filename'];
            
            if ($message['thumbnail_path']) {
                $message['thumbnail_url'] = '/uploads/chat/thumbnails/' . $message['thumbnail_path'];
            }
        }
        
        $message['formatted_time'] = date('H:i', strtotime($message['created_at']));
        $message['formatted_date'] = date('d/m/Y', strtotime($message['created_at']));
        
        return $message;
    }
    
    private function broadcastMessage($messageData, $receiverId) {
        $this->wsServer->sendToUser($receiverId, [
            'type' => 'new_message',
            'message' => $messageData
        ]);
    }
    
    private function sendPushNotificationIfOffline($userId, $messageData) {
        // Check if user is online
        $stmt = $this->pdo->prepare("
            SELECT online_status, fcm_token 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['online_status'] === 'online') {
            return; // User is online, no need for push notification
        }
        
        if ($user['fcm_token']) {
            $this->sendFCMNotification($user['fcm_token'], $messageData);
        }
    }
    
    private function sendFCMNotification($fcmToken, $messageData) {
        $serverKey = $_ENV['FCM_SERVER_KEY'] ?? '';
        if (empty($serverKey)) return;
        
        $title = 'Nova mensagem de ' . $messageData['sender_name'];
        $body = $messageData['message_type'] === 'media' 
            ? '📎 ' . ucfirst($messageData['media_type'])
            : substr($messageData['message_content'], 0, 100);
        
        $payload = [
            'to' => $fcmToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => '/assets/icons/icon-192x192.png',
                'click_action' => '/chat.php?user=' . $messageData['sender_id']
            ],
            'data' => [
                'type' => 'chat_message',
                'sender_id' => $messageData['sender_id'],
                'message_id' => $messageData['id']
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        error_log("FCM notification sent: " . $response);
    }
}

// API endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $chatSystem = new LiveChatSystem();
    $action = $_POST['action'] ?? '';
    
    // Validate user session
    session_start();
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    switch ($action) {
        case 'send_message':
            $receiverId = $_POST['receiver_id'] ?? '';
            $message = $_POST['message'] ?? '';
            $chatType = $_POST['chat_type'] ?? 'support';
            
            echo json_encode($chatSystem->sendMessage($userId, $receiverId, $message, $chatType));
            break;
            
        case 'send_media':
            $receiverId = $_POST['receiver_id'] ?? '';
            $caption = $_POST['caption'] ?? '';
            $file = $_FILES['media'] ?? null;
            $chatType = $_POST['chat_type'] ?? 'support';
            
            echo json_encode($chatSystem->sendMediaMessage($userId, $receiverId, $file, $caption, $chatType));
            break;
            
        case 'get_history':
            $contactId = $_POST['contact_id'] ?? '';
            $limit = intval($_POST['limit'] ?? 50);
            $offset = intval($_POST['offset'] ?? 0);
            
            echo json_encode($chatSystem->getChatHistory($userId, $contactId, $limit, $offset));
            break;
            
        case 'get_conversations':
            echo json_encode($chatSystem->getUserConversations($userId));
            break;
            
        case 'mark_read':
            $senderId = $_POST['sender_id'] ?? '';
            echo json_encode($chatSystem->markMessagesAsRead($userId, $senderId));
            break;
            
        case 'delete_message':
            $messageId = $_POST['message_id'] ?? '';
            echo json_encode($chatSystem->deleteMessage($messageId, $userId));
            break;
            
        case 'set_typing':
            $targetUserId = $_POST['target_user_id'] ?? '';
            $isTyping = $_POST['is_typing'] ?? false;
            echo json_encode($chatSystem->setTypingIndicator($userId, $targetUserId, $isTyping));
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle media file serving
    $file = $_GET['file'] ?? '';
    if ($file) {
        $filePath = $chatSystem->mediaUploadPath . $file;
        if (file_exists($filePath) && is_file($filePath)) {
            $mimeType = mime_content_type($filePath);
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: public, max-age=31536000'); // 1 year cache
            readfile($filePath);
            exit;
        }
    }
    
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'File not found']);
}
?>
