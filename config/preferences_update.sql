CREATE TABLE IF NOT EXISTS cleaning_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    field_type ENUM('checkbox', 'select', 'text') NOT NULL,
    is_checked_default BOOLEAN DEFAULT FALSE,
    is_required BOOLEAN DEFAULT FALSE,
    extra_fee DECIMAL(10,2) DEFAULT 0.00,
    options TEXT,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DELETE FROM cleaning_preferences;

INSERT INTO cleaning_preferences (name, field_type, is_checked_default, is_required, extra_fee, options, sort_order) VALUES
('Eco-friendly products', 'checkbox', 0, 0, 5.00, 'Use only eco-friendly and non-toxic cleaning products', 1),
('Professional chemicals', 'checkbox', 0, 0, 15.00, 'Use professional-grade cleaning chemicals for enhanced results', 2),
('Professional equipment', 'checkbox', 0, 0, 20.00, 'Use professional cleaning equipment and tools', 3),
('Service priority level', 'select', 0, 0, 0.00, '["Standard|0", "Priority|10.00", "Express|25.00"]', 4),
('Special cleaning instructions', 'text', 0, 0, 10.00, 'Additional fee for custom or special cleaning requests', 5),
('Key collection method', 'select', 0, 1, 0.00, '["I will be home", "Hide key (specify location)", "Lockbox", "Property manager", "Spare key pickup"]', 6),
('Pet-friendly service', 'checkbox', 0, 0, 0.00, 'Our team is comfortable working around pets', 7),
('Allergies or sensitivities', 'text', 0, 0, 0.00, 'Please specify any allergies or chemical sensitivities', 8);
