<?php
/**
 * =========================================================
 * COUPON MANAGER - SISTEMA COMPLETO DE CUPONS
 * =========================================================
 * 
 * @file core/CouponManager.php
 * @description Gerenciamento completo de cupons de desconto
 * @date 2025-08-11
 */

class CouponManager {
    
    private $pdo;
    private $debug = false;
    
    public function __construct($database = null, $debug = false) {
        $this->debug = $debug;
        
        if ($database) {
            $this->pdo = $database;
        } else {
            $this->initDatabase();
        }
    }
    
    /**
     * Inicializar conexão com banco de dados
     */
    private function initDatabase() {
        // Carregar configuração do banco de dados
        if (file_exists(__DIR__ . '/../config.php')) {
            require_once __DIR__ . '/../config.php';
        } else {
            throw new Exception("Arquivo de configuração não encontrado");
        }
        
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            if ($this->debug) {
                echo "✅ CouponManager: Conectado ao banco\n";
            }
            
        } catch (PDOException $e) {
            if ($this->debug) {
                echo "❌ CouponManager: Erro de conexão - " . $e->getMessage() . "\n";
            }
            throw new Exception("Erro de conexão com banco de dados: " . $e->getMessage());
        }
    }
    
    /**
     * Validar e aplicar cupom de desconto
     * 
     * @param string $code Código do cupom
     * @param float $subtotal Valor subtotal do pedido
     * @param string $customerEmail Email do cliente
     * @return array Resultado da validação
     */
    public function validateCoupon($code, $subtotal, $customerEmail = null) {
        try {
            
            // 1. BUSCAR CUPOM NO BANCO
            $stmt = $this->pdo->prepare("
                SELECT * FROM coupons 
                WHERE code = :code 
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute(['code' => strtoupper($code)]);
            $coupon = $stmt->fetch();
            
            if (!$coupon) {
                return [
                    'valid' => false,
                    'message' => 'Cupom não encontrado ou inativo',
                    'discount_amount' => 0,
                    'coupon_data' => null
                ];
            }
            
            // 2. VERIFICAR VALIDADE (DATAS)
            $now = new DateTime();
            $validFrom = new DateTime($coupon['valid_from']);
            $validUntil = $coupon['valid_until'] ? new DateTime($coupon['valid_until']) : null;
            
            if ($now < $validFrom) {
                return [
                    'valid' => false,
                    'message' => 'Cupom ainda não está válido',
                    'discount_amount' => 0,
                    'coupon_data' => $coupon
                ];
            }
            
            if ($validUntil && $now > $validUntil) {
                return [
                    'valid' => false,
                    'message' => 'Cupom expirado',
                    'discount_amount' => 0,
                    'coupon_data' => $coupon
                ];
            }
            
            // 3. VERIFICAR VALOR MÍNIMO
            if ($subtotal < $coupon['minimum_amount']) {
                return [
                    'valid' => false,
                    'message' => "Valor mínimo de $" . number_format($coupon['minimum_amount'], 2) . " necessário",
                    'discount_amount' => 0,
                    'coupon_data' => $coupon
                ];
            }
            
            // 4. VERIFICAR LIMITE GERAL DE USO
            if ($coupon['usage_limit'] > 0 && $coupon['usage_count'] >= $coupon['usage_limit']) {
                return [
                    'valid' => false,
                    'message' => 'Cupom esgotado',
                    'discount_amount' => 0,
                    'coupon_data' => $coupon
                ];
            }
            
            // 5. VERIFICAR USO POR CLIENTE (se email fornecido)
            if ($customerEmail && $coupon['per_customer_limit'] > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as usage_count 
                    FROM coupon_usage 
                    WHERE coupon_id = :coupon_id 
                    AND customer_email = :email
                ");
                $stmt->execute([
                    'coupon_id' => $coupon['id'],
                    'email' => $customerEmail
                ]);
                $customerUsage = $stmt->fetch()['usage_count'];
                
                if ($customerUsage >= $coupon['per_customer_limit']) {
                    return [
                        'valid' => false,
                        'message' => 'Limite de uso por cliente atingido',
                        'discount_amount' => 0,
                        'coupon_data' => $coupon
                    ];
                }
            }
            
            // 6. VERIFICAR SE É PRIMEIRO CLIENTE
            if ($coupon['first_time_only'] && $customerEmail) {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as bookings_count 
                    FROM bookings 
                    WHERE customer_email = :email
                ");
                $stmt->execute(['email' => $customerEmail]);
                $bookingsCount = $stmt->fetch()['bookings_count'];
                
                if ($bookingsCount > 0) {
                    return [
                        'valid' => false,
                        'message' => 'Cupom válido apenas para novos clientes',
                        'discount_amount' => 0,
                        'coupon_data' => $coupon
                    ];
                }
            }
            
            // 7. CALCULAR DESCONTO
            $discountAmount = $this->calculateDiscount($coupon, $subtotal);
            
            return [
                'valid' => true,
                'message' => 'Cupom válido!',
                'discount_amount' => $discountAmount,
                'coupon_data' => $coupon,
                'formatted_discount' => $this->formatDiscount($coupon, $discountAmount)
            ];
            
        } catch (Exception $e) {
            if ($this->debug) {
                echo "❌ Erro na validação: " . $e->getMessage() . "\n";
            }
            
            return [
                'valid' => false,
                'message' => 'Erro interno na validação do cupom',
                'discount_amount' => 0,
                'coupon_data' => null
            ];
        }
    }
    
    /**
     * Calcular valor do desconto
     */
    private function calculateDiscount($coupon, $subtotal) {
        if ($coupon['type'] === 'percentage') {
            $discount = ($subtotal * $coupon['value']) / 100;
            
            // Aplicar limite máximo se definido
            if ($coupon['maximum_discount'] && $discount > $coupon['maximum_discount']) {
                $discount = $coupon['maximum_discount'];
            }
            
        } else { // fixed
            $discount = $coupon['value'];
            
            // Desconto não pode ser maior que o subtotal
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }
        }
        
        return round($discount, 2);
    }
    
    /**
     * Formatar desconto para exibição
     */
    private function formatDiscount($coupon, $discountAmount) {
        if ($coupon['type'] === 'percentage') {
            return "{$coupon['value']}% ($" . number_format($discountAmount, 2) . ")";
        } else {
            return "$" . number_format($discountAmount, 2);
        }
    }
    
    /**
     * Registrar uso do cupom
     */
    public function registerUsage($couponCode, $customerEmail, $bookingCode, $discountAmount, $subtotal) {
        try {
            
            // 1. BUSCAR ID DO CUPOM
            $stmt = $this->pdo->prepare("SELECT id FROM coupons WHERE code = :code");
            $stmt->execute(['code' => strtoupper($couponCode)]);
            $couponId = $stmt->fetch()['id'];
            
            if (!$couponId) {
                throw new Exception("Cupom não encontrado para registro de uso");
            }
            
            // 2. REGISTRAR USO
            $stmt = $this->pdo->prepare("
                INSERT INTO coupon_usage 
                (coupon_id, coupon_code, customer_email, booking_code, discount_amount, subtotal) 
                VALUES (:coupon_id, :coupon_code, :customer_email, :booking_code, :discount_amount, :subtotal)
            ");
            
            $stmt->execute([
                'coupon_id' => $couponId,
                'coupon_code' => strtoupper($couponCode),
                'customer_email' => $customerEmail,
                'booking_code' => $bookingCode,
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal
            ]);
            
            // 3. INCREMENTAR CONTADOR DE USO
            $stmt = $this->pdo->prepare("
                UPDATE coupons 
                SET usage_count = usage_count + 1 
                WHERE id = :coupon_id
            ");
            $stmt->execute(['coupon_id' => $couponId]);
            
            if ($this->debug) {
                echo "✅ Uso do cupom '$couponCode' registrado para $customerEmail\n";
            }
            
            return true;
            
        } catch (Exception $e) {
            if ($this->debug) {
                echo "❌ Erro ao registrar uso do cupom: " . $e->getMessage() . "\n";
            }
            return false;
        }
    }
    
    /**
     * Listar cupons ativos
     */
    public function getActiveCoupons() {
        try {
            $stmt = $this->pdo->query("
                SELECT code, name, description, type, value, minimum_amount, 
                       maximum_discount, usage_limit, usage_count, valid_until
                FROM coupons 
                WHERE is_active = 1 
                AND (valid_until IS NULL OR valid_until > NOW())
                ORDER BY type, value DESC
            ");
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            if ($this->debug) {
                echo "❌ Erro ao buscar cupons ativos: " . $e->getMessage() . "\n";
            }
            return [];
        }
    }
    
    /**
     * Buscar estatísticas de uso de cupons
     */
    public function getCouponStats($couponCode = null) {
        try {
            
            if ($couponCode) {
                // Estatísticas de um cupom específico
                $stmt = $this->pdo->prepare("
                    SELECT 
                        c.code, c.name, c.usage_count as total_uses,
                        COUNT(cu.id) as recorded_uses,
                        SUM(cu.discount_amount) as total_discount_given,
                        AVG(cu.discount_amount) as avg_discount,
                        COUNT(DISTINCT cu.customer_email) as unique_customers
                    FROM coupons c
                    LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
                    WHERE c.code = :code
                    GROUP BY c.id
                ");
                $stmt->execute(['code' => strtoupper($couponCode)]);
                return $stmt->fetch();
                
            } else {
                // Estatísticas gerais
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(*) as total_coupons,
                        SUM(usage_count) as total_uses,
                        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_coupons,
                        COUNT(CASE WHEN type = 'percentage' THEN 1 END) as percentage_coupons,
                        COUNT(CASE WHEN type = 'fixed' THEN 1 END) as fixed_coupons
                    FROM coupons
                ");
                return $stmt->fetch();
            }
            
        } catch (Exception $e) {
            if ($this->debug) {
                echo "❌ Erro ao buscar estatísticas: " . $e->getMessage() . "\n";
            }
            return null;
        }
    }
    
    /**
     * Ativar debug mode
     */
    public function enableDebug() {
        $this->debug = true;
    }
    
    /**
     * Desativar debug mode
     */
    public function disableDebug() {
        $this->debug = false;
    }
}

/**
 * Factory function para facilitar uso
 */
function createCouponManager($debug = false) {
    return new CouponManager(null, $debug);
}
