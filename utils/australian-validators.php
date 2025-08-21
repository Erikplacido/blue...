<?php
/**
 * Australian Validation System
 * Blue Cleaning Services - Regional Validation Classes
 * 
 * This system provides validation for Australian-specific data formats
 * including phone numbers, postcodes, ABN, ACN, and addresses.
 * 
 * @author Blue Cleaning Development Team
 * @version 2.0.0
 * @created 07/08/2025
 */

require_once __DIR__ . '/../config/australian-environment.php';

class AustralianValidators {
    
    // Australian state/territory postcodes mapping
    private static $postcodeRanges = [
        'NSW' => [1000, 1999, 2000, 2599, 2619, 2898, 2921, 2999],
        'ACT' => [200, 299, 2600, 2618, 2900, 2920],
        'VIC' => [3000, 3999, 8000, 8999],
        'QLD' => [4000, 4999, 9000, 9999],
        'SA'  => [5000, 5999],
        'WA'  => [6000, 6799, 6800, 6999],
        'TAS' => [7000, 7999],
        'NT'  => [800, 899, 900, 999]
    ];
    
    // Australian street types
    private static $streetTypes = [
        'ST', 'STREET', 'RD', 'ROAD', 'AVE', 'AVENUE', 'PL', 'PLACE',
        'CT', 'COURT', 'DR', 'DRIVE', 'LN', 'LANE', 'WAY', 'CL', 'CLOSE',
        'TCE', 'TERRACE', 'HWY', 'HIGHWAY', 'PWAY', 'PARKWAY', 'BLVD', 'BOULEVARD',
        'CIR', 'CIRCLE', 'CRES', 'CRESCENT', 'GDNS', 'GARDENS', 'GRV', 'GROVE',
        'MEWS', 'PARADE', 'ROW', 'SQUARE', 'WALK'
    ];
    
    /**
     * Validate Australian mobile phone number
     * Format: +61 4XX XXX XXX or 04XX XXX XXX
     * 
     * @param string $mobile
     * @return bool
     */
    public static function validateMobile($mobile) {
        // Clean the input
        $mobile = preg_replace('/[\s\-\(\)]/', '', $mobile);
        
        // Australian mobile patterns
        $patterns = [
            '/^(\+61|61)4\d{8}$/',  // +61 4XXXXXXXX or 61 4XXXXXXXX
            '/^04\d{8}$/'           // 04XXXXXXXX
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $mobile)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate Australian landline phone number
     * Format: +61 X XXXX XXXX or 0X XXXX XXXX
     * 
     * @param string $landline
     * @return bool
     */
    public static function validateLandline($landline) {
        // Clean the input
        $landline = preg_replace('/[\s\-\(\)]/', '', $landline);
        
        // Australian landline patterns (area codes: 02, 03, 07, 08)
        $patterns = [
            '/^(\+61|61)[2378]\d{8}$/',  // +61 X XXXXXXXX
            '/^0[2378]\d{8}$/'           // 0X XXXXXXXX
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $landline)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate Australian postcode
     * 
     * @param string $postcode
     * @param string|null $state Optional state/territory validation
     * @return bool
     */
    public static function validatePostcode($postcode, $state = null) {
        $postcode = trim($postcode);
        
        // Must be 4 digits
        if (!preg_match('/^\d{4}$/', $postcode)) {
            return false;
        }
        
        $postcodeNum = (int)$postcode;
        
        // If no state specified, just check if it's a valid Australian postcode
        if ($state === null) {
            return ($postcodeNum >= 200 && $postcodeNum <= 9999) || 
                   ($postcodeNum >= 1000 && $postcodeNum <= 2999);
        }
        
        // Validate against specific state/territory
        $state = strtoupper($state);
        if (!isset(self::$postcodeRanges[$state])) {
            return false;
        }
        
        $ranges = self::$postcodeRanges[$state];
        for ($i = 0; $i < count($ranges); $i += 2) {
            if ($postcodeNum >= $ranges[$i] && $postcodeNum <= $ranges[$i + 1]) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate Australian Business Number (ABN)
     * 11-digit number with checksum validation
     * 
     * @param string $abn
     * @return bool
     */
    public static function validateABN($abn) {
        // Clean the input
        $abn = preg_replace('/[\s\-]/', '', $abn);
        
        // Must be 11 digits
        if (!preg_match('/^\d{11}$/', $abn)) {
            return false;
        }
        
        // ABN checksum algorithm
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $sum = 0;
        
        // Subtract 1 from first digit
        $firstDigit = (int)$abn[0] - 1;
        $sum += $firstDigit * $weights[0];
        
        // Add weighted sum of remaining digits
        for ($i = 1; $i < 11; $i++) {
            $sum += (int)$abn[$i] * $weights[$i];
        }
        
        // Check if sum is divisible by 89
        return $sum % 89 === 0;
    }
    
    /**
     * Validate Australian Company Number (ACN)
     * 9-digit number with checksum validation
     * 
     * @param string $acn
     * @return bool
     */
    public static function validateACN($acn) {
        // Clean the input
        $acn = preg_replace('/[\s\-]/', '', $acn);
        
        // Must be 9 digits
        if (!preg_match('/^\d{9}$/', $acn)) {
            return false;
        }
        
        // ACN checksum algorithm
        $weights = [8, 7, 6, 5, 4, 3, 2, 1];
        $sum = 0;
        
        // Calculate weighted sum of first 8 digits
        for ($i = 0; $i < 8; $i++) {
            $sum += (int)$acn[$i] * $weights[$i];
        }
        
        // Calculate check digit
        $remainder = $sum % 10;
        $checkDigit = $remainder === 0 ? 0 : 10 - $remainder;
        
        // Verify check digit matches last digit
        return $checkDigit === (int)$acn[8];
    }
    
    /**
     * Validate Australian Tax File Number (TFN)
     * 8 or 9 digit number with checksum validation
     * 
     * @param string $tfn
     * @return bool
     */
    public static function validateTFN($tfn) {
        // Clean the input
        $tfn = preg_replace('/[\s\-]/', '', $tfn);
        
        // Must be 8 or 9 digits
        if (!preg_match('/^\d{8,9}$/', $tfn)) {
            return false;
        }
        
        // Pad to 9 digits if necessary
        $tfn = str_pad($tfn, 9, '0', STR_PAD_LEFT);
        
        // TFN checksum algorithm
        $weights = [1, 4, 3, 7, 5, 8, 6, 9, 10];
        $sum = 0;
        
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$tfn[$i] * $weights[$i];
        }
        
        return $sum % 11 === 0;
    }
    
    /**
     * Validate Australian email format
     * 
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate Australian street address
     * 
     * @param string $address
     * @return bool
     */
    public static function validateStreetAddress($address) {
        $address = trim(strtoupper($address));
        
        if (empty($address)) {
            return false;
        }
        
        // Check for common Australian street types
        foreach (self::$streetTypes as $type) {
            if (strpos($address, $type) !== false) {
                return true;
            }
        }
        
        // Also accept addresses without explicit street types
        return strlen($address) >= 5;
    }
    
    /**
     * Format mobile number for display
     * 
     * @param string $mobile
     * @return string
     */
    public static function formatMobile($mobile) {
        $mobile = preg_replace('/[\s\-\(\)]/', '', $mobile);
        
        if (preg_match('/^(\+61|61|0)4(\d{2})(\d{3})(\d{3})$/', $mobile, $matches)) {
            return "+61 4{$matches[2]} {$matches[3]} {$matches[4]}";
        }
        
        return $mobile;
    }
    
    /**
     * Format landline number for display
     * 
     * @param string $landline
     * @return string
     */
    public static function formatLandline($landline) {
        $landline = preg_replace('/[\s\-\(\)]/', '', $landline);
        
        if (preg_match('/^(\+61|61|0)([2378])(\d{4})(\d{4})$/', $landline, $matches)) {
            return "+61 {$matches[2]} {$matches[3]} {$matches[4]}";
        }
        
        return $landline;
    }
    
    /**
     * Format ABN for display
     * 
     * @param string $abn
     * @return string
     */
    public static function formatABN($abn) {
        $abn = preg_replace('/[\s\-]/', '', $abn);
        
        if (preg_match('/^(\d{2})(\d{3})(\d{3})(\d{3})$/', $abn, $matches)) {
            return "{$matches[1]} {$matches[2]} {$matches[3]} {$matches[4]}";
        }
        
        return $abn;
    }
    
    /**
     * Format ACN for display
     * 
     * @param string $acn
     * @return string
     */
    public static function formatACN($acn) {
        $acn = preg_replace('/[\s\-]/', '', $acn);
        
        if (preg_match('/^(\d{3})(\d{3})(\d{3})$/', $acn, $matches)) {
            return "{$matches[1]} {$matches[2]} {$matches[3]}";
        }
        
        return $acn;
    }
    
    /**
     * Get Australian states/territories list
     * 
     * @return array
     */
    public static function getStates() {
        return [
            'NSW' => 'New South Wales',
            'VIC' => 'Victoria',
            'QLD' => 'Queensland',
            'SA' => 'South Australia',
            'WA' => 'Western Australia',
            'TAS' => 'Tasmania',
            'NT' => 'Northern Territory',
            'ACT' => 'Australian Capital Territory'
        ];
    }
    
    /**
     * Validate credit card number using Luhn algorithm
     * 
     * @param string $number
     * @return bool
     */
    public static function validateCreditCard($number) {
        $number = preg_replace('/[\s\-]/', '', $number);
        
        if (!preg_match('/^\d{13,19}$/', $number)) {
            return false;
        }
        
        // Luhn algorithm
        $sum = 0;
        $length = strlen($number);
        
        for ($i = 0; $i < $length; $i++) {
            $digit = (int)$number[$length - $i - 1];
            
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }
        
        return $sum % 10 === 0;
    }
}
?>
