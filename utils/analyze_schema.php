<?php
/**
 * Database Schema Analysis for Calendar Implementation
 */

require_once 'config.php';

echo "=== DATABASE SCHEMA ANALYSIS ===\n\n";

try {
    // Analyze services table
    echo "📋 SERVICES TABLE STRUCTURE:\n";
    $stmt = $pdo->query("DESCRIBE services");
    $serviceColumns = $stmt->fetchAll();
    foreach ($serviceColumns as $column) {
        echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
    }
    
    echo "\n📊 SAMPLE SERVICES:\n";
    $stmt = $pdo->query("SELECT id, name, duration_minutes, base_price FROM services LIMIT 3");
    $services = $stmt->fetchAll();
    foreach ($services as $service) {
        echo "- ID: {$service['id']} | {$service['name']} | {$service['duration_minutes']}min | \${$service['base_price']}\n";
    }
    
    echo "\n📋 BOOKINGS TABLE STRUCTURE:\n";
    $stmt = $pdo->query("DESCRIBE bookings");
    $bookingColumns = $stmt->fetchAll();
    foreach ($bookingColumns as $column) {
        echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
    }
    
    echo "\n📊 SAMPLE BOOKINGS:\n";
    $stmt = $pdo->query("SELECT id, service_id, scheduled_date, scheduled_time, status FROM bookings LIMIT 3");
    $bookings = $stmt->fetchAll();
    foreach ($bookings as $booking) {
        echo "- ID: {$booking['id']} | Service: {$booking['service_id']} | {$booking['scheduled_date']} {$booking['scheduled_time']} | {$booking['status']}\n";
    }
    
    echo "\n📋 PROFESSIONALS TABLE STRUCTURE:\n";
    $stmt = $pdo->query("DESCRIBE professionals");
    $profColumns = $stmt->fetchAll();
    foreach ($profColumns as $column) {
        echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
    }
    
    // Check if we need to create professional_availability table
    $stmt = $pdo->query("SHOW TABLES LIKE 'professional_availability'");
    $hasAvailabilityTable = $stmt->fetch();
    
    if (!$hasAvailabilityTable) {
        echo "\n⚠️  MISSING: professional_availability table\n";
        echo "❗ Need to create availability table for calendar functionality\n";
    } else {
        echo "\n✅ professional_availability table exists\n";
    }
    
    // Check operating hours
    echo "\n📋 OPERATING_HOURS TABLE:\n";
    $stmt = $pdo->query("SELECT * FROM operating_hours LIMIT 5");
    $hours = $stmt->fetchAll();
    if (!empty($hours)) {
        foreach ($hours as $hour) {
            echo "- Day: {$hour['day_of_week']} | {$hour['open_time']} - {$hour['close_time']} | Open: {$hour['is_open']}\n";
        }
    } else {
        echo "- No operating hours configured\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
