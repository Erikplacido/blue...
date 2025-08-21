<?php
/**
 * Stripe Subscription Manager - Blue Cleaning Services
 * Gerencia assinaturas, pausas e cobranças automáticas
 */

require_once __DIR__ . '/../config/stripe-enterprise.php';
require_once __DIR__ . '/../config/australian-database.php';

class StripeSubscriptionManager {
    private $db;
    private $connection;
    
    public function __construct() {
        $this->db = AustralianDatabase::getInstance();
        $this->connection = $this->db->getConnection();
    }
    
    /**
     * Criar assinatura Stripe com booking
     */
    public function createSubscription($bookingData) {
        try {
            // 1. Criar ou recuperar cliente Stripe
            $customer = $this->createOrUpdateCustomer($bookingData);
            
            // 2. Criar produto e preço
            $price = $this->createServicePrice($bookingData);
            
            // 3. Calcular desconto (não acumulativo)
            $discount = $this->calculateBestDiscount($bookingData);
            
            // 4. Criar sessão de checkout
            $sessionData = [
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $price->id,
                    'quantity' => 1,
                ]],
                'mode' => $bookingData['recurrence_type'] === 'one-time' ? 'payment' : 'subscription',
                'success_url' => STRIPE_SUCCESS_URL . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => STRIPE_CANCEL_URL . '?booking_id=' . $bookingData['booking_id'],
                'metadata' => [
                    'booking_id' => $bookingData['booking_id'],
                    'booking_code' => $bookingData['booking_code'],
                    'recurrence_type' => $bookingData['recurrence_type'],
                    'referral_code' => $bookingData['referral_code'] ?? '',
                    'customer_name' => $bookingData['customer_name'],
                    'service_address' => $bookingData['service_address'] ?? '',
                ]
            ];
            
            // Aplicar desconto se houver
            if ($discount) {
                $coupon = $this->createStripeCoupon($discount, $bookingData);
                $sessionData['discounts'] = [['coupon' => $coupon->id]];
                
                // Salvar desconto usado
                $this->saveDiscountUsed($bookingData['booking_id'], $discount);
            }
            
            // Para recorrentes, configurar cobrança 48h antes
            if ($bookingData['recurrence_type'] !== 'one-time') {
                $billingDate = calculateNextBilling($bookingData['first_service_date'], $bookingData['recurrence_type']);
                $sessionData['subscription_data'] = [
                    'billing_cycle_anchor' => strtotime($billingDate),
                    'proration_behavior' => 'none',
                    'metadata' => $sessionData['metadata']
                ];
            }
            
            $session = \Stripe\Checkout\Session::create($sessionData);
            
            // Salvar dados da sessão
            $this->saveStripeSession($bookingData['booking_id'], $session->id, $customer->id);
            
            return [
                'success' => true,
                'session_id' => $session->id,
                'checkout_url' => $session->url,
                'customer_id' => $customer->id
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao criar subscription Stripe: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar ou atualizar cliente no Stripe
     */
    private function createOrUpdateCustomer($bookingData) {
        // Verificar se já existe cliente
        $stmt = $this->connection->prepare("
            SELECT stripe_customer_id FROM bookings 
            WHERE customer_email = ? AND stripe_customer_id IS NOT NULL 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$bookingData['customer_email']]);
        $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingCustomer && $existingCustomer['stripe_customer_id']) {
            // Recuperar cliente existente
            try {
                return \Stripe\Customer::retrieve($existingCustomer['stripe_customer_id']);
            } catch (Exception $e) {
                // Cliente não existe mais, criar novo
            }
        }
        
        // Criar novo cliente
        return \Stripe\Customer::create([
            'email' => $bookingData['customer_email'],
            'name' => $bookingData['customer_name'],
            'phone' => $bookingData['customer_phone'] ?? '',
            'address' => [
                'line1' => $bookingData['service_address'] ?? '',
                'city' => $bookingData['service_city'] ?? '',
                'state' => $bookingData['service_state'] ?? '',
                'postal_code' => $bookingData['service_postcode'] ?? '',
                'country' => 'AU'
            ],
            'metadata' => [
                'source' => 'blue_cleaning_booking',
                'referral_code' => $bookingData['referral_code'] ?? ''
            ]
        ]);
    }
    
    /**
     * Criar preço no Stripe baseado no serviço
     */
    private function createServicePrice($bookingData) {
        // Criar produto único para este booking
        $product = \Stripe\Product::create([
            'name' => 'Blue Cleaning - ' . ($bookingData['service_type'] ?? 'Regular Cleaning'),
            'description' => $this->buildServiceDescription($bookingData),
            'metadata' => [
                'booking_id' => $bookingData['booking_id'],
                'recurrence_type' => $bookingData['recurrence_type']
            ]
        ]);
        
        $priceData = [
            'product' => $product->id,
            'unit_amount' => stripeAmount($bookingData['total_amount']),
            'currency' => STRIPE_CURRENCY,
            'metadata' => [
                'booking_id' => $bookingData['booking_id'],
                'original_amount' => $bookingData['total_amount']
            ]
        ];
        
        // Para recorrentes, adicionar intervalo
        if ($bookingData['recurrence_type'] !== 'one-time') {
            $priceData['recurring'] = [
                'interval' => $this->getStripeInterval($bookingData['recurrence_type']),
                'interval_count' => $this->getStripeIntervalCount($bookingData['recurrence_type'])
            ];
        }
        
        return \Stripe\Price::create($priceData);
    }
    
    /**
     * Calcular melhor desconto (não acumulativo)
     */
    private function calculateBestDiscount($bookingData) {
        $discounts = [];
        
        // 1. Desconto por recorrência
        $recurrenceDiscount = getRecurrenceDiscount($bookingData['recurrence_type']);
        if ($recurrenceDiscount > 0) {
            $discounts[] = [
                'type' => 'recurrence',
                'amount' => $recurrenceDiscount,
                'code' => $bookingData['recurrence_type'],
                'description' => ucfirst($bookingData['recurrence_type']) . ' Service Discount'
            ];
        }
        
        // 2. Desconto por referral
        if (!empty($bookingData['referral_code'])) {
            $referralDiscount = $this->getReferralDiscount($bookingData['referral_code']);
            if ($referralDiscount > 0) {
                $discounts[] = [
                    'type' => 'referral',
                    'amount' => $referralDiscount,
                    'code' => $bookingData['referral_code'],
                    'description' => 'Referral Code: ' . $bookingData['referral_code']
                ];
            }
        }
        
        // 3. Cupom promocional (se houver)
        if (!empty($bookingData['promo_code'])) {
            $promoDiscount = $this->getPromoDiscount($bookingData['promo_code']);
            if ($promoDiscount > 0) {
                $discounts[] = [
                    'type' => 'promo',
                    'amount' => $promoDiscount,
                    'code' => $bookingData['promo_code'],
                    'description' => 'Promo Code: ' . $bookingData['promo_code']
                ];
            }
        }
        
        // Retornar o maior desconto (não acumulativo)
        if (empty($discounts)) {
            return null;
        }
        
        usort($discounts, function($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        
        return $discounts[0];
    }
    
    /**
     * Criar cupom no Stripe
     */
    private function createStripeCoupon($discount, $bookingData) {
        $couponData = [
            'percent_off' => $discount['amount'],
            'metadata' => [
                'discount_type' => $discount['type'],
                'discount_code' => $discount['code'],
                'booking_id' => $bookingData['booking_id']
            ]
        ];
        
        // Para recorrentes, aplicar desconto por todo o período
        if ($bookingData['recurrence_type'] !== 'one-time') {
            $couponData['duration'] = 'forever';
        } else {
            $couponData['duration'] = 'once';
        }
        
        return \Stripe\Coupon::create($couponData);
    }
    
    /**
     * Pausar assinatura
     */
    public function pauseSubscription($subscriptionId, $pauseDurationWeeks = 1) {
        try {
            // Verificar se cliente ainda tem pausas disponíveis
            if (!$this->hasAvailablePauses($subscriptionId)) {
                return [
                    'success' => false,
                    'error' => 'No free pauses remaining. Contact support for assistance.'
                ];
            }
            
            // Pausar no Stripe
            $subscription = \Stripe\Subscription::retrieve($subscriptionId);
            $pauseEnd = time() + ($pauseDurationWeeks * 7 * 24 * 3600);
            
            $subscription->pause_collection = [
                'behavior' => 'void',
                'resumes_at' => $pauseEnd
            ];
            $subscription->save();
            
            // Registrar pausa no banco
            $this->recordPause($subscriptionId, $pauseDurationWeeks);
            
            return [
                'success' => true,
                'message' => "Subscription paused for {$pauseDurationWeeks} week(s)",
                'resumes_at' => date('Y-m-d', $pauseEnd)
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao pausar subscription: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Helper functions
     */
    private function getStripeInterval($recurrenceType) {
        return match($recurrenceType) {
            'weekly' => 'week',
            'fortnightly' => 'week',
            'monthly' => 'month',
            default => 'week'
        };
    }
    
    private function getStripeIntervalCount($recurrenceType) {
        return match($recurrenceType) {
            'weekly' => 1,
            'fortnightly' => 2,
            'monthly' => 1,
            default => 1
        };
    }
    
    private function getReferralDiscount($referralCode) {
        $stmt = $this->connection->prepare("
            SELECT rl.commission_percentage 
            FROM referral_users ru
            JOIN referral_levels rl ON ru.current_level_id = rl.id
            WHERE ru.referral_code = ? AND ru.is_active = 1
        ");
        $stmt->execute([$referralCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['commission_percentage'] : 0;
    }
    
    private function buildServiceDescription($bookingData) {
        $desc = $bookingData['service_type'] ?? 'Cleaning Service';
        if (!empty($bookingData['recurrence_type']) && $bookingData['recurrence_type'] !== 'one-time') {
            $desc .= ' (' . ucfirst($bookingData['recurrence_type']) . ')';
        }
        return $desc;
    }
    
    private function saveStripeSession($bookingId, $sessionId, $customerId) {
        $stmt = $this->connection->prepare("
            UPDATE bookings 
            SET stripe_session_id = ?, stripe_customer_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$sessionId, $customerId, $bookingId]);
    }
    
    private function saveDiscountUsed($bookingId, $discount) {
        $stmt = $this->connection->prepare("
            UPDATE bookings 
            SET discount_type = ?, discount_code = ?, discount_amount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $discount['type'], 
            $discount['code'], 
            $discount['amount'], 
            $bookingId
        ]);
    }
    
    private function hasAvailablePauses($subscriptionId) {
        // Implementar lógica de verificação de pausas
        // Por enquanto, retorna true (será implementado completamente)
        return true;
    }
    
    private function recordPause($subscriptionId, $duration) {
        // Implementar registro de pausa no banco
        // Será implementado na próxima fase
        return true;
    }
    
    private function getPromoDiscount($promoCode) {
        // Implementar verificação de cupons promocionais
        return 0;
    }
}
?>
