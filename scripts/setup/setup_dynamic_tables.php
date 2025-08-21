<?php
/**
 * Database Migration Script - Dynamic System Tables
 * Blue Cleaning Services - Create Missing Dynamic Tables
 */

require_once __DIR__ . '/config/australian-database.php';

echo "=== Blue Cleaning Services - Dynamic System Migration ===\n\n";

try {
    // Get database instance
    $db = AustralianDatabase::getInstance();
    $connection = $db->getConnection();
    
    echo "✅ Database connection established\n";
    echo "Connected to: " . getenv('DB_DATABASE') . " on " . getenv('DB_HOST') . "\n\n";
    
    // Create service_inclusions table
    echo "Creating service_inclusions table...\n";
    $sql_inclusions = "
    CREATE TABLE IF NOT EXISTS `service_inclusions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `icon` varchar(100) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `sort_order` int(11) DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $connection->exec($sql_inclusions);
    echo "✅ service_inclusions table created\n";
    
    // Create service_extras table
    echo "Creating service_extras table...\n";
    $sql_extras = "
    CREATE TABLE IF NOT EXISTS `service_extras` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `price` decimal(10,2) NOT NULL,
        `icon` varchar(100) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `sort_order` int(11) DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $connection->exec($sql_extras);
    echo "✅ service_extras table created\n";
    
    // Create cleaning_preferences table
    echo "Creating cleaning_preferences table...\n";
    $sql_preferences = "
    CREATE TABLE IF NOT EXISTS `cleaning_preferences` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `icon` varchar(100) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `sort_order` int(11) DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $connection->exec($sql_preferences);
    echo "✅ cleaning_preferences table created\n";
    
    // Create operating_hours table
    echo "Creating operating_hours table...\n";
    $sql_hours = "
    CREATE TABLE IF NOT EXISTS `operating_hours` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `day_of_week` tinyint(1) NOT NULL COMMENT '1=Monday, 7=Sunday',
        `is_open` tinyint(1) DEFAULT 1,
        `open_time` time DEFAULT NULL,
        `close_time` time DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_day` (`day_of_week`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $connection->exec($sql_hours);
    echo "✅ operating_hours table created\n\n";
    
    echo "=== Populating Tables with Initial Data ===\n";
    
    // Insert service inclusions
    $inclusions = [
        ['name' => 'Dusting all surfaces', 'icon' => 'fas fa-feather-alt', 'sort_order' => 1],
        ['name' => 'Vacuuming carpets', 'icon' => 'fas fa-vacuum', 'sort_order' => 2],
        ['name' => 'Mopping floors', 'icon' => 'fas fa-mop', 'sort_order' => 3],
        ['name' => 'Cleaning bathrooms', 'icon' => 'fas fa-bath', 'sort_order' => 4],
        ['name' => 'Kitchen cleaning', 'icon' => 'fas fa-utensils', 'sort_order' => 5],
        ['name' => 'Trash removal', 'icon' => 'fas fa-trash', 'sort_order' => 6],
        ['name' => 'Window cleaning (interior)', 'icon' => 'fas fa-window-restore', 'sort_order' => 7],
        ['name' => 'Bed making', 'icon' => 'fas fa-bed', 'sort_order' => 8]
    ];
    
    $stmt_inclusions = $connection->prepare("INSERT INTO service_inclusions (name, icon, sort_order) VALUES (?, ?, ?)");
    foreach ($inclusions as $inclusion) {
        $stmt_inclusions->execute([$inclusion['name'], $inclusion['icon'], $inclusion['sort_order']]);
    }
    echo "✅ Service inclusions populated (" . count($inclusions) . " items)\n";
    
    // Insert service extras
    $extras = [
        ['name' => 'Inside oven cleaning', 'price' => 49.00, 'icon' => 'fas fa-fire', 'sort_order' => 1],
        ['name' => 'Inside fridge cleaning', 'price' => 39.00, 'icon' => 'fas fa-snowflake', 'sort_order' => 2],
        ['name' => 'Cabinet interior cleaning', 'price' => 59.00, 'icon' => 'fas fa-archive', 'sort_order' => 3],
        ['name' => 'Garage cleaning', 'price' => 79.00, 'icon' => 'fas fa-warehouse', 'sort_order' => 4],
        ['name' => 'Laundry service', 'price' => 35.00, 'icon' => 'fas fa-tshirt', 'sort_order' => 5],
        ['name' => 'Exterior window cleaning', 'price' => 89.00, 'icon' => 'fas fa-home', 'sort_order' => 6]
    ];
    
    $stmt_extras = $connection->prepare("INSERT INTO service_extras (name, price, icon, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($extras as $extra) {
        $stmt_extras->execute([$extra['name'], $extra['price'], $extra['icon'], $extra['sort_order']]);
    }
    echo "✅ Service extras populated (" . count($extras) . " items)\n";
    
    // Insert cleaning preferences
    $preferences = [
        ['name' => 'Eco-friendly products only', 'icon' => 'fas fa-leaf', 'sort_order' => 1],
        ['name' => 'Pet-safe cleaning products', 'icon' => 'fas fa-paw', 'sort_order' => 2],
        ['name' => 'Fragrance-free products', 'icon' => 'fas fa-wind', 'sort_order' => 3],
        ['name' => 'Extra attention to allergens', 'icon' => 'fas fa-shield-alt', 'sort_order' => 4],
        ['name' => 'Deep cleaning focus', 'icon' => 'fas fa-search-plus', 'sort_order' => 5],
        ['name' => 'Quick maintenance clean', 'icon' => 'fas fa-clock', 'sort_order' => 6]
    ];
    
    $stmt_preferences = $connection->prepare("INSERT INTO cleaning_preferences (name, icon, sort_order) VALUES (?, ?, ?)");
    foreach ($preferences as $preference) {
        $stmt_preferences->execute([$preference['name'], $preference['icon'], $preference['sort_order']]);
    }
    echo "✅ Cleaning preferences populated (" . count($preferences) . " items)\n";
    
    // Insert operating hours (Monday to Sunday)
    $hours = [
        ['day_of_week' => 1, 'is_open' => 1, 'open_time' => '07:00', 'close_time' => '19:00'], // Monday
        ['day_of_week' => 2, 'is_open' => 1, 'open_time' => '07:00', 'close_time' => '19:00'], // Tuesday
        ['day_of_week' => 3, 'is_open' => 1, 'open_time' => '07:00', 'close_time' => '19:00'], // Wednesday
        ['day_of_week' => 4, 'is_open' => 1, 'open_time' => '07:00', 'close_time' => '19:00'], // Thursday
        ['day_of_week' => 5, 'is_open' => 1, 'open_time' => '07:00', 'close_time' => '19:00'], // Friday
        ['day_of_week' => 6, 'is_open' => 1, 'open_time' => '08:00', 'close_time' => '17:00'], // Saturday
        ['day_of_week' => 7, 'is_open' => 0, 'open_time' => null, 'close_time' => null]        // Sunday (closed)
    ];
    
    $stmt_hours = $connection->prepare("INSERT INTO operating_hours (day_of_week, is_open, open_time, close_time) VALUES (?, ?, ?, ?)");
    foreach ($hours as $hour) {
        $stmt_hours->execute([$hour['day_of_week'], $hour['is_open'], $hour['open_time'], $hour['close_time']]);
    }
    echo "✅ Operating hours populated (7 days)\n";
    
    echo "\n=== Migration Complete ===\n";
    echo "All dynamic system tables have been created and populated with initial data.\n";
    echo "The booking system is now ready to use the dynamic configuration!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Error details: " . $e->getFile() . " line " . $e->getLine() . "\n";
}

echo "\n=== Migration Script Complete ===\n";
?>
