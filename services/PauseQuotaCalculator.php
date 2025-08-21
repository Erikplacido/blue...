<?php
/**
 * Pause Quota Calculator - Blue Cleaning Services
 * Calcula pausas disponíveis baseado no número de serviços
 */

require_once __DIR__ . '/../config/stripe-enterprise.php';
require_once __DIR__ . '/../config/australian-database.php';

class PauseQuotaCalculator {
    private $db;
    private $connection;
    
    public function __construct() {
        $this->db = AustralianDatabase::getInstance();
        $this->connection = $this->db->getConnection();
    }
    
    /**
     * Calcular pausas disponíveis para uma assinatura
     */
    public function calculateAvailablePauses($subscriptionId) {
        try {
            // 1. Buscar dados da assinatura
            $subscriptionData = $this->getSubscriptionData($subscriptionId);
            if (!$subscriptionData) {
                return [
                    'success' => false,
                    'error' => 'Subscription not found'
                ];
            }
            
            // 2. Contar serviços planejados no período
            $plannedServices = $this->countPlannedServices($subscriptionData);
            
            // 3. Determinar faixa de pausa baseada no número de serviços
            $pauseQuota = $this->getPauseQuotaForServices($plannedServices);
            
            // 4. Contar pausas já utilizadas
            $usedPauses = $this->countUsedPauses($subscriptionId);
            
            // 5. Calcular pausas restantes
            $remainingPauses = max(0, $pauseQuota['free_pauses'] - $usedPauses);
            
            return [
                'success' => true,
                'data' => [
                    'planned_services' => $plannedServices,
                    'quota_info' => $pauseQuota,
                    'total_pauses_allowed' => $pauseQuota['free_pauses'],
                    'pauses_used' => $usedPauses,
                    'pauses_remaining' => $remainingPauses,
                    'can_pause_free' => $remainingPauses > 0,
                    'next_pause_fee' => $remainingPauses > 0 ? 0 : $this->calculatePauseFee($subscriptionData)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao calcular pausas: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar se pode pausar gratuitamente
     */
    public function canPauseFree($subscriptionId) {
        $result = $this->calculateAvailablePauses($subscriptionId);
        return $result['success'] && $result['data']['can_pause_free'];
    }
    
    /**
     * Registrar nova pausa
     */
    public function recordPause($subscriptionId, $pauseStartDate, $pauseEndDate, $reason = 'customer_request') {
        try {
            $stmt = $this->connection->prepare("
                INSERT INTO subscription_pauses 
                (subscription_id, pause_start, pause_end, reason, is_free, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $isFree = $this->canPauseFree($subscriptionId);
            
            $result = $stmt->execute([
                $subscriptionId,
                $pauseStartDate,
                $pauseEndDate,
                $reason,
                $isFree ? 1 : 0
            ]);
            
            if ($result) {
                // Atualizar contador de pausas
                $this->updatePauseCounter($subscriptionId);
                
                return [
                    'success' => true,
                    'is_free' => $isFree,
                    'message' => $isFree ? 'Free pause applied' : 'Paid pause applied'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to record pause'
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao registrar pausa: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter dados da assinatura
     */
    private function getSubscriptionData($subscriptionId) {
        $stmt = $this->connection->prepare("
            SELECT b.*, bs.*
            FROM bookings b
            JOIN booking_subscriptions bs ON b.id = bs.booking_id
            WHERE bs.stripe_subscription_id = ? OR b.stripe_session_id = ?
        ");
        $stmt->execute([$subscriptionId, $subscriptionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Contar serviços planejados baseado na recorrência
     */
    private function countPlannedServices($subscriptionData) {
        $recurrenceType = $subscriptionData['recurrence_type'] ?? 'weekly';
        $contractDuration = $subscriptionData['contract_duration_weeks'] ?? 26;
        
        return match($recurrenceType) {
            'weekly' => $contractDuration, // 1 por semana
            'fortnightly' => ceil($contractDuration / 2), // 1 a cada 2 semanas
            'monthly' => ceil($contractDuration / 4), // 1 por mês (aproximado)
            default => $contractDuration
        };
    }
    
    /**
     * Determinar faixa de pausa baseada no número de serviços
     */
    private function getPauseQuotaForServices($servicesCount) {
        $quotas = PAUSE_QUOTAS;
        
        foreach ($quotas as $quota) {
            if ($servicesCount >= $quota['min'] && $servicesCount <= $quota['max']) {
                return $quota;
            }
        }
        
        // Fallback para a faixa mais alta
        return end($quotas);
    }
    
    /**
     * Contar pausas já utilizadas
     */
    private function countUsedPauses($subscriptionId) {
        $stmt = $this->connection->prepare("
            SELECT COUNT(*) as used_pauses
            FROM subscription_pauses 
            WHERE subscription_id = ? AND is_free = 1
        ");
        $stmt->execute([$subscriptionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['used_pauses'] : 0;
    }
    
    /**
     * Calcular taxa de pausa quando exceder o limite gratuito
     */
    private function calculatePauseFee($subscriptionData) {
        // Taxa baseada em percentual do valor mensal
        $monthlyAmount = $subscriptionData['total_amount'] ?? 150;
        return round($monthlyAmount * 0.15, 2); // 15% do valor como taxa
    }
    
    /**
     * Atualizar contador de pausas no booking
     */
    private function updatePauseCounter($subscriptionId) {
        $stmt = $this->connection->prepare("
            UPDATE booking_subscriptions 
            SET pauses_used = pauses_used + 1, updated_at = NOW()
            WHERE stripe_subscription_id = ?
        ");
        return $stmt->execute([$subscriptionId]);
    }
    
    /**
     * Obter histórico de pausas de uma assinatura
     */
    public function getPauseHistory($subscriptionId) {
        $stmt = $this->connection->prepare("
            SELECT * FROM subscription_pauses 
            WHERE subscription_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$subscriptionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Validar se pode pausar (48h de antecedência)
     */
    public function canPauseWithNotice($subscriptionId, $nextServiceDate) {
        if (!canPauseOrCancel($nextServiceDate)) {
            return [
                'success' => false,
                'error' => 'Minimum 48 hours notice required for pause/cancellation'
            ];
        }
        
        $pauseData = $this->calculateAvailablePauses($subscriptionId);
        if (!$pauseData['success']) {
            return $pauseData;
        }
        
        return [
            'success' => true,
            'can_pause_free' => $pauseData['data']['can_pause_free'],
            'fee_required' => !$pauseData['data']['can_pause_free'],
            'pause_fee' => $pauseData['data']['next_pause_fee']
        ];
    }
    
    /**
     * Criar tabelas necessárias se não existirem
     */
    public function ensureTablesExist() {
        try {
            // Tabela para pausas
            $this->connection->exec("
                CREATE TABLE IF NOT EXISTS subscription_pauses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    subscription_id VARCHAR(255) NOT NULL,
                    pause_start DATETIME NOT NULL,
                    pause_end DATETIME NOT NULL,
                    reason VARCHAR(255) DEFAULT 'customer_request',
                    is_free BOOLEAN DEFAULT TRUE,
                    pause_fee DECIMAL(10,2) DEFAULT 0.00,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_subscription (subscription_id),
                    INDEX idx_dates (pause_start, pause_end)
                )
            ");
            
            // Tabela para assinaturas
            $this->connection->exec("
                CREATE TABLE IF NOT EXISTS booking_subscriptions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    booking_id INT NOT NULL,
                    stripe_subscription_id VARCHAR(255) UNIQUE,
                    stripe_customer_id VARCHAR(255),
                    status VARCHAR(50) DEFAULT 'active',
                    recurrence_type VARCHAR(20),
                    contract_duration_weeks INT DEFAULT 26,
                    pauses_used INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (booking_id) REFERENCES bookings(id),
                    INDEX idx_stripe_sub (stripe_subscription_id)
                )
            ");
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao criar tabelas: " . $e->getMessage());
            return false;
        }
    }
}
?>
