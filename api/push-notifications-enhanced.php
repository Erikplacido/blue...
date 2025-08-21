<?php
/**
 * Sistema de Push Notifications - Blue Cleaning Services
 * Gerenciamento completo de notificações push
 */

class PushNotificationManager {
    
    private $config;
    private $db;
    private $logger;
    
    public function __construct($database = null) {
        $this->config = EnvironmentConfig::get('push_notifications', [
            'firebase_server_key' => '',
            'firebase_sender_id' => '',
            'vapid_public_key' => '',
            'vapid_private_key' => '',
            'enabled' => true
        ]);
        
        $this->db = $database ?? new PDO(
            "mysql:host=" . EnvironmentConfig::get('database.host') . ";dbname=" . EnvironmentConfig::get('database.database'),
            EnvironmentConfig::get('database.username'),
            EnvironmentConfig::get('database.password')
        );
        
        if (class_exists('Logger')) {
            $this->logger = Logger::getInstance();
        }
        
        $this->initializeDatabase();
    }
    
    /**
     * Inicializar tabelas do banco de dados
     */
    private function initializeDatabase() {
        $queries = [
            // Tabela de subscriptions
            "CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                endpoint TEXT NOT NULL,
                p256dh_key VARCHAR(255) NOT NULL,
                auth_key VARCHAR(255) NOT NULL,
                user_agent TEXT,
                ip_address VARCHAR(45),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_subscription (user_id, endpoint(255))
            )",
            
            // Tabela de notificações enviadas
            "CREATE TABLE IF NOT EXISTS push_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                icon VARCHAR(255),
                badge VARCHAR(255),
                tag VARCHAR(100),
                data JSON,
                target_type ENUM('all', 'user', 'group', 'segment') DEFAULT 'all',
                target_value VARCHAR(255),
                sent_count INT DEFAULT 0,
                success_count INT DEFAULT 0,
                failure_count INT DEFAULT 0,
                status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
                scheduled_at TIMESTAMP NULL,
                sent_at TIMESTAMP NULL,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            // Tabela de logs de entrega
            "CREATE TABLE IF NOT EXISTS push_delivery_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                notification_id INT,
                subscription_id INT,
                status ENUM('success', 'failed') NOT NULL,
                error_message TEXT,
                response_code INT,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (notification_id) REFERENCES push_notifications(id) ON DELETE CASCADE,
                FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE CASCADE
            )",
            
            // Tabela de preferências de notificação
            "CREATE TABLE IF NOT EXISTS notification_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNIQUE,
                booking_reminders BOOLEAN DEFAULT TRUE,
                booking_confirmations BOOLEAN DEFAULT TRUE,
                payment_notifications BOOLEAN DEFAULT TRUE,
                promotional_offers BOOLEAN DEFAULT TRUE,
                service_updates BOOLEAN DEFAULT TRUE,
                marketing_emails BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ];
        
        foreach ($queries as $query) {
            $this->db->exec($query);
        }
    }
    
    /**
     * Registrar uma subscription de push
     */
    public function subscribe($userId, $subscription) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    p256dh_key = VALUES(p256dh_key),
                    auth_key = VALUES(auth_key),
                    user_agent = VALUES(user_agent),
                    ip_address = VALUES(ip_address),
                    is_active = TRUE,
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $userId,
                $subscription['endpoint'],
                $subscription['keys']['p256dh'],
                $subscription['keys']['auth'],
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            if ($this->logger) {
                $this->logger->info('Push subscription registered', [
                    'user_id' => $userId,
                    'endpoint' => substr($subscription['endpoint'], -20)
                ]);
            }
            
            return ['success' => true, 'message' => 'Subscription registered successfully'];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to register push subscription', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return ['success' => false, 'message' => 'Failed to register subscription'];
        }
    }
    
    /**
     * Cancelar subscription
     */
    public function unsubscribe($userId, $endpoint = null) {
        try {
            if ($endpoint) {
                $stmt = $this->db->prepare("
                    UPDATE push_subscriptions 
                    SET is_active = FALSE 
                    WHERE user_id = ? AND endpoint = ?
                ");
                $stmt->execute([$userId, $endpoint]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE push_subscriptions 
                    SET is_active = FALSE 
                    WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
            }
            
            return ['success' => true, 'message' => 'Unsubscribed successfully'];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to unsubscribe', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return ['success' => false, 'message' => 'Failed to unsubscribe'];
        }
    }
    
    /**
     * Enviar notificação para usuário específico
     */
    public function sendToUser($userId, $notification) {
        return $this->createAndSendNotification($notification, 'user', $userId);
    }
    
    /**
     * Enviar notificação para todos os usuários
     */
    public function sendToAll($notification) {
        return $this->createAndSendNotification($notification, 'all', null);
    }
    
    /**
     * Enviar notificação para segmento de usuários
     */
    public function sendToSegment($segment, $notification) {
        return $this->createAndSendNotification($notification, 'segment', $segment);
    }
    
    /**
     * Criar e enviar notificação
     */
    private function createAndSendNotification($notification, $targetType, $targetValue) {
        try {
            // Salvar notificação no banco
            $stmt = $this->db->prepare("
                INSERT INTO push_notifications 
                (title, body, icon, badge, tag, data, target_type, target_value, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $notification['title'],
                $notification['body'],
                $notification['icon'] ?? '/assets/icons/icon-192x192.png',
                $notification['badge'] ?? '/assets/icons/badge-72x72.png',
                $notification['tag'] ?? 'general',
                json_encode($notification['data'] ?? []),
                $targetType,
                $targetValue,
                $notification['created_by'] ?? null
            ]);
            
            $notificationId = $this->db->lastInsertId();
            
            // Obter subscriptions baseado no alvo
            $subscriptions = $this->getTargetSubscriptions($targetType, $targetValue);
            
            if (empty($subscriptions)) {
                return ['success' => false, 'message' => 'No active subscriptions found'];
            }
            
            // Marcar como enviando
            $this->updateNotificationStatus($notificationId, 'sending');
            
            // Enviar para cada subscription
            $results = $this->sendToSubscriptions($notificationId, $subscriptions, $notification);
            
            // Atualizar estatísticas
            $this->updateNotificationStats($notificationId, $results);
            
            return [
                'success' => true,
                'message' => 'Notification sent successfully',
                'notification_id' => $notificationId,
                'stats' => $results['stats']
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to send notification', [
                    'error' => $e->getMessage(),
                    'target_type' => $targetType,
                    'target_value' => $targetValue
                ]);
            }
            
            return ['success' => false, 'message' => 'Failed to send notification'];
        }
    }
    
    /**
     * Obter subscriptions baseado no alvo
     */
    private function getTargetSubscriptions($targetType, $targetValue) {
        switch ($targetType) {
            case 'user':
                $stmt = $this->db->prepare("
                    SELECT * FROM push_subscriptions 
                    WHERE user_id = ? AND is_active = TRUE
                ");
                $stmt->execute([$targetValue]);
                break;
                
            case 'segment':
                // Implementar lógica de segmentação baseada em critérios específicos
                $stmt = $this->getSegmentSubscriptions($targetValue);
                break;
                
            case 'all':
            default:
                $stmt = $this->db->prepare("
                    SELECT * FROM push_subscriptions 
                    WHERE is_active = TRUE
                ");
                $stmt->execute();
                break;
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter subscriptions por segmento
     */
    private function getSegmentSubscriptions($segment) {
        // Exemplos de segmentação
        switch ($segment) {
            case 'new_users':
                return $this->db->prepare("
                    SELECT ps.* FROM push_subscriptions ps
                    JOIN users u ON ps.user_id = u.id
                    WHERE ps.is_active = TRUE 
                    AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                
            case 'active_customers':
                return $this->db->prepare("
                    SELECT ps.* FROM push_subscriptions ps
                    JOIN users u ON ps.user_id = u.id
                    JOIN bookings b ON u.id = b.customer_id
                    WHERE ps.is_active = TRUE 
                    AND b.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    GROUP BY ps.id
                ");
                
            default:
                return $this->db->prepare("
                    SELECT * FROM push_subscriptions 
                    WHERE is_active = TRUE
                ");
        }
    }
    
    /**
     * Enviar para lista de subscriptions
     */
    private function sendToSubscriptions($notificationId, $subscriptions, $notification) {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($subscriptions as $subscription) {
            try {
                $success = $this->sendSingleNotification($subscription, $notification);
                
                if ($success) {
                    $results['success']++;
                    $this->logDelivery($notificationId, $subscription['id'], 'success');
                } else {
                    $results['failed']++;
                    $this->logDelivery($notificationId, $subscription['id'], 'failed', 'Send failed');
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
                $this->logDelivery($notificationId, $subscription['id'], 'failed', $e->getMessage());
            }
        }
        
        $results['stats'] = [
            'total' => count($subscriptions),
            'success' => $results['success'],
            'failed' => $results['failed'],
            'success_rate' => count($subscriptions) > 0 ? round(($results['success'] / count($subscriptions)) * 100, 2) : 0
        ];
        
        return $results;
    }
    
    /**
     * Enviar notificação única
     */
    private function sendSingleNotification($subscription, $notification) {
        $payload = json_encode([
            'title' => $notification['title'],
            'body' => $notification['body'],
            'icon' => $notification['icon'] ?? '/assets/icons/icon-192x192.png',
            'badge' => $notification['badge'] ?? '/assets/icons/badge-72x72.png',
            'tag' => $notification['tag'] ?? 'general',
            'data' => $notification['data'] ?? [],
            'actions' => $notification['actions'] ?? [
                [
                    'action' => 'view',
                    'title' => 'View',
                    'icon' => '/assets/icons/action-view.png'
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Dismiss',
                    'icon' => '/assets/icons/action-dismiss.png'
                ]
            ]
        ]);
        
        // Headers para FCM
        $headers = [
            'Authorization: key=' . $this->config['firebase_server_key'],
            'Content-Type: application/json'
        ];
        
        // Payload para FCM
        $fcmPayload = [
            'to' => $subscription['endpoint'],
            'notification' => [
                'title' => $notification['title'],
                'body' => $notification['body'],
                'icon' => $notification['icon'] ?? '/assets/icons/icon-192x192.png',
                'click_action' => $notification['click_action'] ?? '/'
            ],
            'data' => $notification['data'] ?? []
        ];
        
        // Enviar via cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://fcm.googleapis.com/fcm/send',
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($fcmPayload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            return isset($responseData['success']) && $responseData['success'] === 1;
        }
        
        return false;
    }
    
    /**
     * Registrar log de entrega
     */
    private function logDelivery($notificationId, $subscriptionId, $status, $errorMessage = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO push_delivery_logs 
                (notification_id, subscription_id, status, error_message)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([$notificationId, $subscriptionId, $status, $errorMessage]);
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to log delivery', ['error' => $e->getMessage()]);
            }
        }
    }
    
    /**
     * Atualizar estatísticas da notificação
     */
    private function updateNotificationStats($notificationId, $results) {
        $stmt = $this->db->prepare("
            UPDATE push_notifications 
            SET sent_count = ?, success_count = ?, failure_count = ?, 
                status = 'sent', sent_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $results['stats']['total'],
            $results['success'],
            $results['failed'],
            $notificationId
        ]);
    }
    
    /**
     * Atualizar status da notificação
     */
    private function updateNotificationStatus($notificationId, $status) {
        $stmt = $this->db->prepare("
            UPDATE push_notifications 
            SET status = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $notificationId]);
    }
    
    /**
     * Obter preferências de notificação do usuário
     */
    public function getUserPreferences($userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_preferences 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prefs) {
            // Criar preferências padrão
            $this->createDefaultPreferences($userId);
            return $this->getUserPreferences($userId);
        }
        
        return $prefs;
    }
    
    /**
     * Atualizar preferências do usuário
     */
    public function updateUserPreferences($userId, $preferences) {
        $stmt = $this->db->prepare("
            UPDATE notification_preferences 
            SET booking_reminders = ?, booking_confirmations = ?, 
                payment_notifications = ?, promotional_offers = ?,
                service_updates = ?, marketing_emails = ?
            WHERE user_id = ?
        ");
        
        return $stmt->execute([
            $preferences['booking_reminders'] ?? true,
            $preferences['booking_confirmations'] ?? true,
            $preferences['payment_notifications'] ?? true,
            $preferences['promotional_offers'] ?? true,
            $preferences['service_updates'] ?? true,
            $preferences['marketing_emails'] ?? false,
            $userId
        ]);
    }
    
    /**
     * Criar preferências padrão
     */
    private function createDefaultPreferences($userId) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO notification_preferences (user_id)
            VALUES (?)
        ");
        
        $stmt->execute([$userId]);
    }
    
    /**
     * Limpar subscriptions inativas
     */
    public function cleanupInactiveSubscriptions($days = 30) {
        $stmt = $this->db->prepare("
            DELETE FROM push_subscriptions 
            WHERE is_active = FALSE 
            AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Obter estatísticas de notificações
     */
    public function getNotificationStats($period = '30 days') {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_notifications,
                SUM(sent_count) as total_sent,
                SUM(success_count) as total_success,
                SUM(failure_count) as total_failures,
                ROUND(AVG(success_count / NULLIF(sent_count, 0) * 100), 2) as avg_success_rate
            FROM push_notifications 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $days = $period === '30 days' ? 30 : ($period === '7 days' ? 7 : 1);
        $stmt->execute([$days]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Gerar chaves VAPID
     */
    public static function generateVAPIDKeys() {
        // Simulação - em produção, usar biblioteca específica para VAPID
        return [
            'public' => base64_encode(random_bytes(65)),
            'private' => base64_encode(random_bytes(32))
        ];
    }
}

// Endpoint para API
if (basename(__FILE__) === 'push-notifications.php' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    
    $pushManager = new PushNotificationManager();
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (isset($input['action'])) {
                switch ($input['action']) {
                    case 'subscribe':
                        if (isset($input['user_id'], $input['subscription'])) {
                            $response = $pushManager->subscribe($input['user_id'], $input['subscription']);
                        }
                        break;
                        
                    case 'unsubscribe':
                        if (isset($input['user_id'])) {
                            $response = $pushManager->unsubscribe($input['user_id'], $input['endpoint'] ?? null);
                        }
                        break;
                        
                    case 'send':
                        if (isset($input['notification'])) {
                            $notification = $input['notification'];
                            if (isset($input['user_id'])) {
                                $response = $pushManager->sendToUser($input['user_id'], $notification);
                            } elseif (isset($input['segment'])) {
                                $response = $pushManager->sendToSegment($input['segment'], $notification);
                            } else {
                                $response = $pushManager->sendToAll($notification);
                            }
                        }
                        break;
                }
            }
            break;
    }
    
    echo json_encode($response);
}

?>
