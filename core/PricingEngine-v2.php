<?php
/**
 * =========================================================
 * PRICING ENGINE - COM SUPORTE A CUPONS
 * =========================================================
 * 
 * @file core/PricingEngine.php
 * @description Engine centralizado para cálculo de preços com cupons
 * @version 2.0 - COUPON INTEGRATION
 * @date 2025-08-11
 */

class PricingEngine 
{
    /**
     * FONTE ÚNICA DE VERDADE - PREÇOS BASE
     */
    private static $servicePrices = [
        // Valores dinâmicos carregados do banco de dados - não usar fallbacks fixos
    ];

    /**
     * EXTRAS - FONTE ÚNICA
     */
    private static $extraPrices = [
        'carpet' => 15.00,
        'oven' => 25.00,
        'fridge' => 20.00,
        'windows' => 30.00,
        'garage' => 40.00,
        'balcony' => 15.00,
    ];

    /**
     * DESCONTOS DE RECORRÊNCIA - FONTE ÚNICA
     */
    private static $recurrenceDiscounts = [
        'one-time' => 0,        // Sem desconto
        'weekly' => 7,          // 7% desconto
        'fortnightly' => 5,     // 5% desconto
        'monthly' => 3,         // 3% desconto
    ];

    /**
     * CALCULAR PREÇO FINAL COM SUPORTE A CUPONS
     * 
     * @param string|int $serviceId ID do serviço
     * @param array $extras Lista de extras selecionados
     * @param string $recurrence Tipo de recorrência
     * @param float $discountAmount Desconto adicional (referral, etc)
     * @param string $couponCode Código do cupom (opcional)
     * @param string $customerEmail Email do cliente (opcional)
     * @return array Breakdown completo do preço
     */
    public static function calculate($serviceId, $extras = [], $recurrence = 'one-time', $discountAmount = 0, $couponCode = '', $customerEmail = '')
    {
        error_log("🧮 PricingEngine v2.0: Calculating for service $serviceId (coupon: $couponCode)");
        
        // 1. PREÇO BASE
        $basePrice = self::getServicePrice($serviceId);
        
        // 2. EXTRAS
        $extrasPrice = self::calculateExtras($extras);
        
        // 3. SUBTOTAL
        $subtotal = $basePrice + $extrasPrice;
        
        // 4. DESCONTO DE RECORRÊNCIA
        $recurrenceDiscount = self::calculateRecurrenceDiscount($subtotal, $recurrence);
        
        // 5. CUPOM DE DESCONTO (NOVO!)
        $couponDiscount = 0.0;
        $couponResult = null;
        
        if (!empty($couponCode)) {
            try {
                // Load CouponManager if not already loaded
                if (!class_exists('CouponManager')) {
                    require_once __DIR__ . '/CouponManager.php';
                }
                
                $couponManager = createCouponManager(false);
                $couponResult = $couponManager->validateCoupon($couponCode, $subtotal, $customerEmail);
                
                if ($couponResult['valid']) {
                    $couponDiscount = $couponResult['discount_amount'];
                    error_log("🎫 PricingEngine: Cupom '$couponCode' aplicado: desconto de $" . $couponDiscount);
                } else {
                    error_log("❌ PricingEngine: Cupom '$couponCode' inválido: " . $couponResult['message']);
                }
                
            } catch (Exception $e) {
                error_log("❌ PricingEngine: Erro ao processar cupom '$couponCode': " . $e->getMessage());
            }
        }
        
        // 6. DESCONTO ADICIONAL
        $additionalDiscount = floatval($discountAmount);
        
        // 7. TOTAL FINAL
        $totalDiscount = $recurrenceDiscount + $couponDiscount + $additionalDiscount;
        $finalAmount = $subtotal - $totalDiscount;
        
        // 8. GARANTIR VALOR MÍNIMO
        $finalAmount = max($finalAmount, 50.00); // Valor mínimo de segurança
        
        $breakdown = [
            'service_id' => $serviceId,
            'base_price' => round($basePrice, 2),
            'extras' => $extras,
            'extras_price' => round($extrasPrice, 2),
            'subtotal' => round($subtotal, 2),
            'recurrence' => $recurrence,
            'recurrence_discount' => round($recurrenceDiscount, 2),
            'coupon_code' => $couponCode,
            'coupon_discount' => round($couponDiscount, 2),
            'coupon_valid' => $couponResult ? $couponResult['valid'] : false,
            'coupon_message' => $couponResult ? $couponResult['message'] : '',
            'additional_discount' => round($additionalDiscount, 2),
            'total_discount' => round($totalDiscount, 2),
            'final_amount' => round($finalAmount, 2),
            'currency' => 'AUD',
            'stripe_amount_cents' => intval($finalAmount * 100)
        ];
        
        error_log("✅ PricingEngine v2.0 result: " . json_encode($breakdown));
        
        return $breakdown;
    }

    /**
     * Obter preço do serviço com fallback seguro
     */
    private static function getServicePrice($serviceId) 
    {
        $serviceId = strval($serviceId);
        
        if (isset(self::$servicePrices[$serviceId])) {
            return self::$servicePrices[$serviceId];
        }
        
        // ERRO: Não usar fallback fixo - carregar do banco de dados
        error_log("❌ PricingEngine-v2: Service ID $serviceId not found - MUST load from database");
        throw new Exception("Unable to determine price for service ID: $serviceId. Please check database configuration.");
    }

    /**
     * Calcular preço dos extras
     */
    private static function calculateExtras($extras) 
    {
        $total = 0.0;
        
        if (!empty($extras) && is_array($extras)) {
            foreach ($extras as $extra) {
                if (isset(self::$extraPrices[$extra])) {
                    $total += self::$extraPrices[$extra];
                } else {
                    error_log("⚠️ PricingEngine: Extra '$extra' não encontrado");
                }
            }
        }
        
        return $total;
    }

    /**
     * Calcular desconto de recorrência
     */
    private static function calculateRecurrenceDiscount($subtotal, $recurrence) 
    {
        if (!isset(self::$recurrenceDiscounts[$recurrence])) {
            error_log("⚠️ PricingEngine: Recurrence '$recurrence' não encontrada");
            return 0.00;
        }
        
        $discountPercent = self::$recurrenceDiscounts[$recurrence];
        $discount = ($subtotal * $discountPercent) / 100;
        
        if ($discount > 0) {
            error_log("💰 PricingEngine: Recurrence discount ($recurrence): {$discountPercent}% = $$discount");
        }
        
        return $discount;
    }

    /**
     * Validar se um service ID é válido
     */
    public static function isValidServiceId($serviceId) 
    {
        return isset(self::$servicePrices[strval($serviceId)]);
    }

    /**
     * Obter lista de preços para debug/admin
     */
    public static function getAllPrices() 
    {
        return [
            'services' => self::$servicePrices,
            'extras' => self::$extraPrices,
            'recurrence_discounts' => self::$recurrenceDiscounts
        ];
    }

    /**
     * Método para sincronizar com JavaScript (gera JSON)
     */
    public static function generateJSConfig() 
    {
        return json_encode([
            'services' => self::$servicePrices,
            'extras' => self::$extraPrices,
            'recurrence_discounts' => self::$recurrenceDiscounts
        ]);
    }
}
