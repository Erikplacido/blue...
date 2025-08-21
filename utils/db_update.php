<?php
$conn = new mysqli('localhost', 'root', '', 'blue_cleaning_au');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
echo 'Connected successfully' . PHP_EOL;

$sql = "CREATE TABLE IF NOT EXISTS cleaning_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    field_type ENUM('checkbox', 'select', 'text') NOT NULL,
    extra_fee DECIMAL(10,2) DEFAULT 0.00,
    options TEXT,
    sort_order INT DEFAULT 0
)";

if ($conn->query($sql)) {
    echo 'Table ready' . PHP_EOL;
} else {
    echo 'Error: ' . $conn->error . PHP_EOL;
}

$conn->query('DELETE FROM cleaning_preferences');

$prefs = [
    ['Eco-friendly products', 'checkbox', 5.00, 'Use only eco-friendly and non-toxic cleaning products', 1],
    ['Professional chemicals', 'checkbox', 15.00, 'Use professional-grade cleaning chemicals for enhanced results', 2],
    ['Professional equipment', 'checkbox', 20.00, 'Use professional cleaning equipment and tools', 3],
    ['Service priority level', 'select', 0.00, '["Standard|0", "Priority|10.00", "Express|25.00"]', 4],
    ['Special cleaning instructions', 'text', 10.00, 'Additional fee for custom or special cleaning requests', 5],
    ['Key collection method', 'select', 0.00, '["I will be home", "Hide key (specify location)", "Lockbox", "Property manager", "Spare key pickup"]', 6],
    ['Pet-friendly service', 'checkbox', 0.00, 'Our team is comfortable working around pets', 7],
    ['Allergies or sensitivities', 'text', 0.00, 'Please specify any allergies or chemical sensitivities', 8]
];

$stmt = $conn->prepare('INSERT INTO cleaning_preferences (name, field_type, extra_fee, options, sort_order) VALUES (?, ?, ?, ?, ?)');

foreach ($prefs as $p) {
    $stmt->bind_param('ssdsi', $p[0], $p[1], $p[2], $p[3], $p[4]);
    if ($stmt->execute()) {
        echo 'Inserted: ' . $p[0] . ' (Fee: $' . $p[2] . ')' . PHP_EOL;
    }
}

echo 'Database update completed!' . PHP_EOL;
$conn->close();
?>
