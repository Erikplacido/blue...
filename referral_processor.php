<?php
/**
 * =========================================================
 * SISTEMA AUTOMÁTICO DE PROCESSAMENTO REFERRAL
 * =========================================================
 * 
 * @file referral_processor.php
 * @description Processa automaticamente referrals quando bookings são pagos
 * @version 1.0
 * @date 2025-08-09
 */

class ReferralProcessor {
    private $db;
    private $connection;
    
    public function __construct() {
        require_once __DIR__ . '/config/australian-database.php';
        $this->db = AustralianDatabase::getInstance();
        $this->connection = $this->db->getConnection();
    }
    
    /**
     * Processa comissão de referral quando booking é confirmado/pago
     */
    public function processBookingReferralCommission($bookingCode) {
        try {
            // Buscar booking com dados de referral
            $stmt = $this->connection->prepare("
                SELECT b.*, ru.id as referrer_user_id, ru.name as referrer_name,
                       rl.commission_percentage, rl.level_name
                FROM bookings b
                JOIN referral_users ru ON b.referred_by = ru.id
                LEFT JOIN referral_levels rl ON ru.current_level_id = rl.id
                WHERE b.booking_code = ? 
                AND b.referral_code IS NOT NULL 
                AND b.status = 'completed' 
                AND b.payment_status = 'paid'
                AND b.referral_commission_calculated = FALSE
            ");
            
            $stmt->execute([$bookingCode]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                return [
                    'success' => false,
                    'message' => 'Booking não encontrado ou não elegível para comissão'
                ];
            }
            
            // Calcular comissão
            $commissionRate = $booking['commission_percentage'] ?: 10; // 10% padrão
            $commissionAmount = ($booking['total_amount'] * $commissionRate) / 100;
            
            // Verificar se referral já existe
            $checkStmt = $this->connection->prepare("SELECT id FROM referrals WHERE booking_id = ?");
            $checkStmt->execute([$bookingCode]);
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Referral já foi processado para este booking'
                ];
            }
            
            // Extrair cidade do endereço
            $addressParts = explode(',', $booking['address']);
            $city = trim($addressParts[0]);
            
            // Criar registro de referral
            $insertReferralSQL = "
                INSERT INTO referrals (
                    referrer_id, booking_id, customer_name, customer_email,
                    service_type, booking_date, booking_value, commission_earned,
                    city, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())
            ";
            
            $stmt = $this->connection->prepare($insertReferralSQL);
            $stmt->execute([
                $booking['referrer_user_id'],
                $bookingCode,
                $booking['customer_name'],
                $booking['customer_email'],
                $booking['service_type'],
                $booking['service_date'],
                $booking['total_amount'],
                $commissionAmount,
                $city
            ]);
            
            // Marcar booking como processado
            $updateBookingSQL = "UPDATE bookings SET referral_commission_calculated = TRUE WHERE booking_code = ?";
            $stmt = $this->connection->prepare($updateBookingSQL);
            $stmt->execute([$bookingCode]);
            
            // Atualizar estatísticas do usuário
            $this->updateUserStats($booking['referrer_user_id']);
            
            return [
                'success' => true,
                'message' => "Comissão de \${$commissionAmount} processada para {$booking['referrer_name']}",
                'data' => [
                    'booking_code' => $bookingCode,
                    'referrer_name' => $booking['referrer_name'],
                    'commission_amount' => $commissionAmount,
                    'commission_rate' => $commissionRate,
                    'booking_value' => $booking['total_amount']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar comissão: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualiza estatísticas do usuário referral
     */
    private function updateUserStats($userId) {
        $updateSQL = "
            UPDATE referral_users SET
                total_earned = (
                    SELECT COALESCE(SUM(commission_earned), 0)
                    FROM referrals
                    WHERE referrer_id = ?
                ),
                total_referrals = (
                    SELECT COUNT(*)
                    FROM referrals
                    WHERE referrer_id = ?
                )
            WHERE id = ?
        ";
        
        $stmt = $this->connection->prepare($updateSQL);
        $stmt->execute([$userId, $userId, $userId]);
    }
    
    /**
     * Processa todos os bookings pendentes de comissão
     */
    public function processAllPendingCommissions() {
        $stmt = $this->connection->query("
            SELECT booking_code 
            FROM bookings 
            WHERE referral_code IS NOT NULL 
            AND status = 'completed' 
            AND payment_status = 'paid'
            AND referral_commission_calculated = FALSE
        ");
        
        $pendingBookings = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $results = [];
        
        foreach ($pendingBookings as $bookingCode) {
            $result = $this->processBookingReferralCommission($bookingCode);
            $results[] = [
                'booking_code' => $bookingCode,
                'result' => $result
            ];
        }
        
        return $results;
    }
    
    /**
     * Simula um novo booking com referral
     */
    public function simulateNewBookingWithReferral($referralCode, $customerData, $serviceData) {
        try {
            // Buscar usuário pelo código de referral
            $stmt = $this->connection->prepare("
                SELECT id, name FROM referral_users 
                WHERE referral_code = ? AND is_active = 1
            ");
            $stmt->execute([$referralCode]);
            $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$referrer) {
                return [
                    'success' => false,
                    'message' => 'Código de referral inválido'
                ];
            }
            
            // Gerar código único do booking
            $bookingCode = 'BK' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            // Criar novo booking
            $insertBookingSQL = "
                INSERT INTO bookings (
                    booking_code, customer_name, customer_email, customer_phone,
                    service_date, service_time, service_type, address, total_amount,
                    status, payment_status, referral_code, referred_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', 'paid', ?, ?, NOW())
            ";
            
            $stmt = $this->connection->prepare($insertBookingSQL);
            $stmt->execute([
                $bookingCode,
                $customerData['name'],
                $customerData['email'],
                $customerData['phone'],
                $serviceData['date'],
                $serviceData['time'],
                $serviceData['type'],
                $serviceData['address'],
                $serviceData['amount'],
                $referralCode,
                $referrer['id']
            ]);
            
            // Processar comissão automaticamente
            $commissionResult = $this->processBookingReferralCommission($bookingCode);
            
            return [
                'success' => true,
                'message' => 'Booking criado e comissão processada',
                'data' => [
                    'booking_code' => $bookingCode,
                    'referrer_name' => $referrer['name'],
                    'commission_result' => $commissionResult
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar booking: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter relatório de referrals por usuário
     */
    public function getReferralReport($userId = null) {
        $whereClause = $userId ? "WHERE ru.id = ?" : "WHERE ru.is_active = 1";
        $params = $userId ? [$userId] : [];
        
        $stmt = $this->connection->prepare("
            SELECT ru.id, ru.name, ru.referral_code, ru.total_earned, ru.total_referrals,
                   COUNT(b.id) as total_bookings,
                   SUM(b.total_amount) as total_booking_value,
                   COUNT(CASE WHEN b.payment_status = 'paid' THEN 1 END) as paid_bookings,
                   COUNT(CASE WHEN b.payment_status = 'pending' THEN 1 END) as pending_bookings
            FROM referral_users ru
            LEFT JOIN bookings b ON ru.id = b.referred_by
            {$whereClause}
            GROUP BY ru.id
            ORDER BY ru.total_earned DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Função para uso direto em outras páginas
function processReferralCommission($bookingCode) {
    $processor = new ReferralProcessor();
    return $processor->processBookingReferralCommission($bookingCode);
}

function createBookingWithReferral($referralCode, $customerData, $serviceData) {
    $processor = new ReferralProcessor();
    return $processor->simulateNewBookingWithReferral($referralCode, $customerData, $serviceData);
}
?>
