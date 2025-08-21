<?php
/**
 * Australian Timezone and Locale Configuration
 * Blue Cleaning Services - Regional Settings
 * 
 * This file sets the default timezone and locale for Australian operations.
 * All dates, times, and formatting will use Australian standards.
 * 
 * @author Blue Cleaning Development Team
 * @version 2.0.0
 * @created 07/08/2025
 */

// Load Australian environment configuration
require_once __DIR__ . '/australian-environment.php';

// Set Australian timezone (Eastern Standard Time by default)
$timezone = AustralianEnvironmentConfig::get('APP_TIMEZONE', 'Australia/Sydney');
date_default_timezone_set($timezone);

// Set Australian locale
$locale = AustralianEnvironmentConfig::get('APP_LOCALE', 'en_AU.UTF-8');
setlocale(LC_ALL, $locale);

// Define Australian-specific constants
define('AUSTRALIAN_TIMEZONE', $timezone);
define('AUSTRALIAN_LOCALE', $locale);
define('AUSTRALIAN_CURRENCY', AustralianEnvironmentConfig::get('CURRENCY', 'AUD'));
define('AUSTRALIAN_CURRENCY_SYMBOL', AustralianEnvironmentConfig::get('CURRENCY_SYMBOL', '$'));
define('AUSTRALIAN_DATE_FORMAT', AustralianEnvironmentConfig::get('DATE_FORMAT', 'd/m/Y'));
define('AUSTRALIAN_TIME_FORMAT', AustralianEnvironmentConfig::get('TIME_FORMAT', 'H:i'));
define('AUSTRALIAN_DATETIME_FORMAT', AustralianEnvironmentConfig::get('DATETIME_FORMAT', 'd/m/Y H:i'));

// Australian states and territories
define('AUSTRALIAN_STATES', [
    'NSW' => 'New South Wales',
    'VIC' => 'Victoria', 
    'QLD' => 'Queensland',
    'SA' => 'South Australia',
    'WA' => 'Western Australia',
    'TAS' => 'Tasmania',
    'NT' => 'Northern Territory',
    'ACT' => 'Australian Capital Territory'
]);

/**
 * Australian-specific helper functions
 */

/**
 * Format amount in Australian dollars
 * 
 * @param float $amount
 * @return string
 */
function formatAustralianCurrency($amount) {
    return AustralianEnvironmentConfig::formatCurrency($amount);
}

/**
 * Format date in Australian format (DD/MM/YYYY)
 * 
 * @param string|DateTime $date
 * @return string
 */
function formatAustralianDate($date) {
    return AustralianEnvironmentConfig::formatDate($date);
}

/**
 * Format datetime in Australian format (DD/MM/YYYY HH:MM)
 * 
 * @param string|DateTime $datetime
 * @return string
 */
function formatAustralianDateTime($datetime) {
    return AustralianEnvironmentConfig::formatDateTime($datetime);
}

/**
 * Get current Australian time
 * 
 * @param string $format
 * @return string
 */
function getAustralianTime($format = 'd/m/Y H:i:s') {
    $now = new DateTime('now', new DateTimeZone(AUSTRALIAN_TIMEZONE));
    return $now->format($format);
}

/**
 * Convert UTC time to Australian timezone
 * 
 * @param string $utcTime
 * @return DateTime
 */
function utcToAustralianTime($utcTime) {
    $utc = new DateTime($utcTime, new DateTimeZone('UTC'));
    $utc->setTimezone(new DateTimeZone(AUSTRALIAN_TIMEZONE));
    return $utc;
}

/**
 * Get business hours for Australian operations
 * 
 * @return array
 */
function getAustralianBusinessHours() {
    return [
        'weekday_start' => '08:00',
        'weekday_end' => '17:00',
        'saturday_start' => '09:00',
        'saturday_end' => '15:00',
        'sunday_closed' => true,
        'public_holidays_closed' => true,
        'timezone' => AUSTRALIAN_TIMEZONE
    ];
}

/**
 * Check if current time is within Australian business hours
 * 
 * @return bool
 */
function isAustralianBusinessHours() {
    $now = new DateTime('now', new DateTimeZone(AUSTRALIAN_TIMEZONE));
    $hours = getAustralianBusinessHours();
    
    $dayOfWeek = (int)$now->format('w'); // 0 = Sunday, 6 = Saturday
    $currentTime = $now->format('H:i');
    
    // Sunday
    if ($dayOfWeek === 0 && $hours['sunday_closed']) {
        return false;
    }
    
    // Saturday
    if ($dayOfWeek === 6) {
        return $currentTime >= $hours['saturday_start'] && $currentTime <= $hours['saturday_end'];
    }
    
    // Weekdays (Monday-Friday)
    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
        return $currentTime >= $hours['weekday_start'] && $currentTime <= $hours['weekday_end'];
    }
    
    return false;
}

/**
 * Get Australian public holidays (basic list - should be expanded)
 * 
 * @param int $year
 * @return array
 */
function getAustralianPublicHolidays($year = null) {
    if ($year === null) {
        $year = (int)date('Y');
    }
    
    return [
        "01/01/{$year}" => "New Year's Day",
        "26/01/{$year}" => "Australia Day",
        "25/04/{$year}" => "ANZAC Day",
        "25/12/{$year}" => "Christmas Day",
        "26/12/{$year}" => "Boxing Day"
        // Note: Easter dates and Queen's Birthday vary by state and year
        // A more comprehensive implementation would calculate these dynamically
    ];
}

// Log timezone setting in debug mode
if (AustralianEnvironmentConfig::isDebugMode()) {
    error_log("Australian Regional Settings: Timezone set to {$timezone}, Locale set to {$locale}");
}
?>
