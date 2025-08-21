<?php
/**
 * =========================================================
 * PRICING ENGINE - FONTE ÚNICA DE VERDADE
 * =========================================================
 * 
 * @file core/PricingEngine.php
 * @description Engine centralizado para cálculo de preços
 * @version 1.0 - UNIFIED PRICING
 * @date 2025-08-11
 * 
 * BACKUP FILE - DEPRECATED
 * - This file contains old hard-coded values and should not be used
 * - All pricing must come from database
 * - See PricingEngine.php for current implementation
 */

class PricingEngine 
{
    /**
     * DEPRECATED - DO NOT USE HARD-CODED PRICES
     * All prices must come from database
     */
    private static $servicePrices = [
        // THESE VALUES ARE DEPRECATED - USE DATABASE INSTEAD
        '1' => 0.00,   // Use database
        '2' => 0.00,   // Use database  
        '3' => 0.00,   // Use database
        '4' => 0.00,   // Use database
        '5' => 0.00,   // Use database
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
     * CÁLCULO PRINCIPAL - MÉTODO ÚNICO
     * 
     * @param string|int $serviceId ID do serviço
     * @param array $extras Lista de extras selecionados
     * @param string $recurrence Tipo de recorrência
     * @param float $discountAmount Desconto adicional (referral, etc)
     * @return array Breakdown completo do preço
     */
    public static function calculate($serviceId, $extras = [], $recurrence = 'one-time', $discountAmount = 0)
    {
        error_log("🧮 PricingEngine: Calculating for service $serviceId");
        
        // 1. PREÇO BASE
        $basePrice = self::getServicePrice($serviceId);
        
        // 2. EXTRAS
        $extrasPrice = self::calculateExtras($extras);
        
        // 3. SUBTOTAL
        $subtotal = $basePrice + $extrasPrice;
        
        // 4. DESCONTO DE RECORRÊNCIA
        $recurrenceDiscount = self::calculateRecurrenceDiscount($subtotal, $recurrence);
        
        // 5. DESCONTO ADICIONAL
        $additionalDiscount = floatval($discountAmount);
        
        // 6. TOTAL FINAL
        $totalDiscount = $recurrenceDiscount + $additionalDiscount;
        $finalAmount = $subtotal - $totalDiscount;
        
        // 7. GARANTIR VALOR MÍNIMO
        $finalAmount = max($finalAmount, 50.00); // Valor mínimo de segurança
        
        $breakdown = [
            'service_id' => $serviceId,
            'base_price' => round($basePrice, 2),
            'extras' => $extras,
            'extras_price' => round($extrasPrice, 2),
            'subtotal' => round($subtotal, 2),
            'recurrence' => $recurrence,
            'recurrence_discount' => round($recurrenceDiscount, 2),
            'additional_discount' => round($additionalDiscount, 2),
            'total_discount' => round($totalDiscount, 2),
            'final_amount' => round($finalAmount, 2),
            'currency' => 'AUD',
            'stripe_amount_cents' => intval($finalAmount * 100)
        ];
        
        error_log("✅ PricingEngine result: " . json_encode($breakdown));
        
        return $breakdown;
    }

    /**
     * Obter preço do serviço com fallback seguro
     */
    private static function getServicePrice($serviceId) 
    {
        $serviceId = strval($serviceId);
        
        // BACKUP FILE - DEPRECATED: Do not use hard-coded values
        error_log("❌ CRITICAL: PricingEngine-backup.php is deprecated - use database pricing");
        throw new Exception("PricingEngine-backup.php is deprecated. Service ID $serviceId must be loaded from database.");
    }

    /**
     * Calcular preço dos extras
     */
    private static function calculateExtras($extras) 
    {
        if (!is_array($extras)) {
            return 0.00;
        }
        
        $total = 0.00;
        foreach ($extras as $extra) {
            if (isset(self::$extraPrices[$extra])) {
                $total += self::$extraPrices[$extra];
                error_log("📦 PricingEngine: Added extra '$extra' = $" . self::$extraPrices[$extra]);
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
