-- ====================================
-- MIGRATION: Dinamização de Dados Hardcoded
-- Data: 8 de agosto de 2025
-- Objetivo: Mover dados fixos do booking2.php para banco
-- ====================================

-- 1. Tabela de Configurações do Sistema
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Tabela de Inclusões Dinâmicas
CREATE TABLE IF NOT EXISTS service_inclusions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. Tabela de Extras Dinâmicos
CREATE TABLE IF NOT EXISTS service_extras (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    price_type ENUM('fixed', 'per_hour', 'percentage') DEFAULT 'fixed',
    icon VARCHAR(50),
    category VARCHAR(50),
    is_popular BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Tabela de Preferências de Limpeza
CREATE TABLE IF NOT EXISTS cleaning_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    category VARCHAR(50),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 5. Tabela de Horários de Funcionamento
CREATE TABLE IF NOT EXISTS operating_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    day_of_week TINYINT NOT NULL, -- 0=Domingo, 1=Segunda, etc
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day (day_of_week)
);

-- ====================================
-- POPULAÇÃO DE DADOS INICIAIS
-- ====================================

-- Configurações do Sistema
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES
('base_cleaning_price', '45.00', 'number', 'pricing', 'Preço base por hora de limpeza'),
('minimum_hours', '2', 'number', 'booking', 'Mínimo de horas para agendamento'),
('maximum_hours', '8', 'number', 'booking', 'Máximo de horas para agendamento'),
('advance_booking_days', '14', 'number', 'booking', 'Dias máximos de antecedência para agendamento'),
('cancellation_hours', '24', 'number', 'booking', 'Horas mínimas para cancelamento sem taxa'),
('cancellation_fee_percentage', '50', 'number', 'booking', 'Porcentagem da taxa de cancelamento'),
('gst_rate', '0.10', 'number', 'pricing', 'Taxa GST (10%)'),
('stripe_fee_percentage', '0.029', 'number', 'pricing', 'Taxa Stripe (2.9%)'),
('company_name', 'Blue Cleaning Services', 'string', 'company', 'Nome da empresa'),
('company_abn', '12 345 678 901', 'string', 'company', 'ABN da empresa'),
('support_email', 'support@bluecleaningservices.com.au', 'string', 'contact', 'Email de suporte'),
('support_phone', '+61 2 9876 5432', 'string', 'contact', 'Telefone de suporte');

-- Inclusões Padrão
INSERT INTO service_inclusions (name, description, icon, is_default, sort_order) VALUES
('Dusting all surfaces', 'Complete dusting of all accessible surfaces including furniture, shelves, and decorative items', 'dust', TRUE, 1),
('Vacuuming carpets and rugs', 'Thorough vacuuming of all carpeted areas and rugs throughout the property', 'vacuum', TRUE, 2),
('Mopping hard floors', 'Comprehensive mopping of all hard floor surfaces including tiles, laminate, and hardwood', 'mop', TRUE, 3),
('Cleaning bathrooms', 'Complete bathroom sanitization including toilets, showers, sinks, mirrors, and floors', 'bathroom', TRUE, 4),
('Kitchen cleaning', 'Thorough kitchen cleaning including countertops, sink, stovetop, and exterior of appliances', 'kitchen', TRUE, 5),
('Emptying bins', 'Emptying and replacing liners in all waste bins throughout the property', 'bin', TRUE, 6),
('Making beds', 'Making all beds with fresh linens if provided by client', 'bed', TRUE, 7),
('Wiping down surfaces', 'Sanitizing and wiping all surfaces including countertops, tables, and handles', 'wipe', TRUE, 8);

-- Extras de Serviço
INSERT INTO service_extras (name, description, price, price_type, icon, category, is_popular, sort_order) VALUES
('Inside oven cleaning', 'Deep cleaning of oven interior including racks and glass door', 25.00, 'fixed', 'oven', 'kitchen', TRUE, 1),
('Inside fridge cleaning', 'Complete refrigerator interior cleaning and sanitization', 20.00, 'fixed', 'fridge', 'kitchen', TRUE, 2),
('Window cleaning (interior)', 'Cleaning interior windows and sills throughout the property', 15.00, 'per_hour', 'window', 'general', TRUE, 3),
('Laundry service', 'Washing, drying, and folding of clothes and linens', 18.00, 'per_hour', 'laundry', 'general', FALSE, 4),
('Ironing service', 'Professional ironing of clothes and linens', 20.00, 'per_hour', 'iron', 'general', FALSE, 5),
('Deep carpet cleaning', 'Professional steam cleaning of carpets and rugs', 35.00, 'fixed', 'carpet', 'deep_clean', FALSE, 6),
('Organizing and decluttering', 'Organizing belongings and decluttering living spaces', 22.00, 'per_hour', 'organize', 'general', FALSE, 7),
('Inside microwave cleaning', 'Deep cleaning and sanitization of microwave interior', 10.00, 'fixed', 'microwave', 'kitchen', FALSE, 8),
('Balcony/patio cleaning', 'Cleaning outdoor balcony or patio areas including furniture', 25.00, 'fixed', 'balcony', 'outdoor', FALSE, 9),
('Inside cabinets cleaning', 'Cleaning interior of kitchen and bathroom cabinets', 30.00, 'fixed', 'cabinet', 'deep_clean', FALSE, 10);

-- Preferências de Limpeza
INSERT INTO cleaning_preferences (name, description, icon, category, sort_order) VALUES
-- Produtos
('Eco-friendly products only', 'Use only environmentally friendly, non-toxic cleaning products', 'eco', 'products', 1),
('Fragrance-free products', 'Use fragrance-free cleaning products for sensitive individuals', 'fragrance-free', 'products', 2),
('Bring your own products', 'Client will provide their own preferred cleaning products', 'own-products', 'products', 3),
('Hypoallergenic products', 'Use hypoallergenic cleaning products for allergy sufferers', 'hypoallergenic', 'products', 4),

-- Animais
('Pet-friendly cleaning', 'Extra care around pets and pet areas, pet-safe products', 'pet', 'pets', 5),
('Cat litter area attention', 'Special attention to cat litter areas and odor control', 'cat', 'pets', 6),
('Pet hair removal focus', 'Extra focus on removing pet hair from furniture and carpets', 'pet-hair', 'pets', 7),

-- Cuidados Especiais
('Handle fragile items carefully', 'Extra care when cleaning around delicate or valuable items', 'fragile', 'special_care', 8),
('Focus on high-traffic areas', 'Pay special attention to heavily used areas of the home', 'traffic', 'special_care', 9),
('Child-safe cleaning', 'Use child-safe products and secure dangerous items', 'child-safe', 'special_care', 10),
('Senior-friendly service', 'Gentle, respectful service adapted for elderly clients', 'senior', 'special_care', 11),

-- Áreas Específicas
('Deep kitchen focus', 'Extra attention to kitchen deep cleaning and sanitization', 'kitchen-focus', 'areas', 12),
('Bathroom deep clean', 'Intensive bathroom cleaning and mold prevention', 'bathroom-focus', 'areas', 13),
('Home office cleaning', 'Careful cleaning of office equipment and documents', 'office', 'areas', 14),
('Guest room preparation', 'Prepare guest rooms for upcoming visitors', 'guest-room', 'areas', 15);

-- Horários de Funcionamento (Segunda a Sexta: 8h-18h, Sábado: 9h-15h)
INSERT INTO operating_hours (day_of_week, start_time, end_time, is_available) VALUES
(1, '08:00:00', '18:00:00', TRUE), -- Segunda
(2, '08:00:00', '18:00:00', TRUE), -- Terça
(3, '08:00:00', '18:00:00', TRUE), -- Quarta
(4, '08:00:00', '18:00:00', TRUE), -- Quinta
(5, '08:00:00', '18:00:00', TRUE), -- Sexta
(6, '09:00:00', '15:00:00', TRUE), -- Sábado
(0, '00:00:00', '00:00:00', FALSE); -- Domingo (fechado)

-- ====================================
-- ÍNDICES PARA PERFORMANCE
-- ====================================

-- Índices de busca
CREATE INDEX idx_service_inclusions_active ON service_inclusions (is_active, sort_order);
CREATE INDEX idx_service_extras_active ON service_extras (is_active, sort_order);
CREATE INDEX idx_cleaning_preferences_active ON cleaning_preferences (is_active, sort_order);
CREATE INDEX idx_system_settings_key ON system_settings (setting_key, is_active);
CREATE INDEX idx_operating_hours_day ON operating_hours (day_of_week, is_available);

-- ====================================
-- COMENTÁRIOS FINAIS
-- ====================================

-- Esta migração move todos os dados hardcoded do booking2.php
-- para o banco de dados, permitindo configuração dinâmica
-- através do painel administrativo.

-- Próximos passos:
-- 1. Criar APIs para buscar estes dados
-- 2. Modificar booking2.php para usar os dados do banco
-- 3. Criar interface admin para gerenciar estes dados
