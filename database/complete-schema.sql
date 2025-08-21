-- ============================================================================
-- BLUE CLEANING SERVICES - SCHEMA COMPLETO PARA PRODUÇÃO
-- Versão: 2.0 - Sistema completo de booking e treinamento
-- ============================================================================

-- Adicionar campos necessários à tabela customers existente
ALTER TABLE customers ADD COLUMN IF NOT EXISTS customer_code VARCHAR(20) UNIQUE AFTER id;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS suburb VARCHAR(100) AFTER phone;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS state VARCHAR(50) DEFAULT 'NSW' AFTER suburb;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS postcode VARCHAR(10) AFTER state;

-- Atualizar customer_code para registros existentes
UPDATE customers SET customer_code = CONCAT('CUST_', LPAD(id, 6, '0')) WHERE customer_code IS NULL;

-- Adicionar campos necessários à tabela professionals existente  
ALTER TABLE professionals ADD COLUMN IF NOT EXISTS professional_code VARCHAR(20) UNIQUE AFTER id;
ALTER TABLE professionals ADD COLUMN IF NOT EXISTS suburb VARCHAR(100) AFTER phone;

-- Atualizar professional_code para registros existentes
UPDATE professionals SET professional_code = CONCAT('PROF_', LPAD(id, 6, '0')) WHERE professional_code IS NULL;

-- ============================================================================
-- TABELAS DO SISTEMA DE BOOKING
-- ============================================================================

-- Tabela de serviços
CREATE TABLE IF NOT EXISTS services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 120,
    category ENUM('residential', 'commercial', 'specialized') DEFAULT 'residential',
    is_active BOOLEAN DEFAULT TRUE,
    requires_special_equipment BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service_code (service_code),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
);

-- Inserir serviços básicos
INSERT IGNORE INTO services (service_code, name, description, base_price, duration_minutes, category) VALUES
('HOUSE_BASIC', 'Basic House Cleaning', 'Standard residential cleaning service including dusting, vacuuming, mopping, and bathroom cleaning', 45.00, 120, 'residential'),
('HOUSE_DEEP', 'Deep House Cleaning', 'Comprehensive deep cleaning service including all basic services plus detailed cleaning of appliances, inside cabinets, and hard-to-reach areas', 85.00, 180, 'residential'),
('OFFICE_CLEAN', 'Office Cleaning', 'Commercial office cleaning including desk areas, meeting rooms, kitchen facilities, and restrooms', 55.00, 90, 'commercial'),
('CARPET_CLEAN', 'Carpet Cleaning', 'Professional carpet cleaning service using steam cleaning and eco-friendly products', 35.00, 60, 'specialized'),
('WINDOW_CLEAN', 'Window Cleaning', 'Interior and exterior window cleaning service', 25.00, 45, 'specialized'),
('MOVE_CLEAN', 'Move In/Out Cleaning', 'Complete cleaning service for moving in or out of property', 120.00, 240, 'residential');

-- Tabela de reservas/bookings
CREATE TABLE IF NOT EXISTS bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_code VARCHAR(20) UNIQUE NOT NULL,
    customer_id BIGINT UNSIGNED,
    professional_id BIGINT UNSIGNED NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    
    -- Dados do cliente
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    
    -- Endereço australiano
    street_address VARCHAR(255) NOT NULL,
    suburb VARCHAR(100) NOT NULL,
    state ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT') NOT NULL,
    postcode VARCHAR(4) NOT NULL,
    
    -- Agendamento
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 120,
    
    -- Preços (valores em AUD)
    base_price DECIMAL(10,2) NOT NULL,
    extras_price DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    gst_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    
    -- Status
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded', 'failed', 'partial') DEFAULT 'pending',
    
    -- Extras e observações
    extras JSON,
    special_instructions TEXT,
    access_instructions TEXT,
    
    -- Avaliação
    rating TINYINT UNSIGNED NULL,
    review TEXT NULL,
    reviewed_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    
    -- Indexes
    INDEX idx_booking_code (booking_code),
    INDEX idx_customer_email (customer_email),
    INDEX idx_scheduled_date (scheduled_date),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_postcode (postcode),
    INDEX idx_suburb (suburb)
);

-- Tabela de pagamentos
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_code VARCHAR(20) UNIQUE NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    
    -- Stripe data
    stripe_payment_intent_id VARCHAR(255),
    stripe_charge_id VARCHAR(255),
    stripe_customer_id VARCHAR(255),
    
    -- Payment details
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) DEFAULT 'AUD',
    payment_method ENUM('card', 'bank_transfer', 'cash', 'afterpay') DEFAULT 'card',
    
    -- Card details (últimos 4 dígitos)
    card_last4 VARCHAR(4),
    card_brand VARCHAR(20),
    
    -- Status
    status ENUM('pending', 'processing', 'succeeded', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    failure_reason TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    refunded_at TIMESTAMP NULL,
    
    -- Indexes
    INDEX idx_payment_code (payment_code),
    INDEX idx_booking_id (booking_id),
    INDEX idx_stripe_payment_intent (stripe_payment_intent_id),
    INDEX idx_status (status)
);

-- ============================================================================
-- SISTEMA DE TREINAMENTO E CANDIDATOS
-- ============================================================================

-- Tabela de candidatos
CREATE TABLE IF NOT EXISTS candidates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_code VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    
    -- Endereço
    street_address VARCHAR(255),
    suburb VARCHAR(100),
    state ENUM('NSW','VIC','QLD','SA','WA','TAS','NT','ACT'),
    postcode VARCHAR(4),
    
    -- Experiência e qualificações
    has_experience BOOLEAN DEFAULT FALSE,
    experience_years TINYINT UNSIGNED DEFAULT 0,
    has_abn BOOLEAN DEFAULT FALSE,
    abn VARCHAR(20),
    has_insurance BOOLEAN DEFAULT FALSE,
    has_police_check BOOLEAN DEFAULT FALSE,
    
    -- Status do candidato
    status ENUM('applied', 'screening', 'in_training', 'evaluation', 'approved', 'rejected', 'onboarding', 'converted') DEFAULT 'applied',
    
    -- Timestamps do processo
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    screening_completed_at TIMESTAMP NULL,
    training_started_at TIMESTAMP NULL,
    training_completed_at TIMESTAMP NULL,
    evaluation_score DECIMAL(5,2) NULL,
    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    converted_at TIMESTAMP NULL,
    
    -- Referência para usuário convertido
    converted_user_id BIGINT UNSIGNED NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_candidate_code (candidate_code),
    INDEX idx_postcode (postcode)
);

-- Tabela de treinamentos
CREATE TABLE IF NOT EXISTS trainings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    training_code VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- Configurações
    is_mandatory BOOLEAN DEFAULT FALSE,
    is_paid BOOLEAN DEFAULT FALSE,
    price DECIMAL(10,2) DEFAULT 0.00,
    duration_minutes INT DEFAULT 60,
    passing_score DECIMAL(3,1) DEFAULT 80.0,
    max_attempts TINYINT UNSIGNED DEFAULT 3,
    
    -- Status
    status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    
    -- Conteúdo
    content_html TEXT,
    content_url VARCHAR(500),
    video_url VARCHAR(500),
    materials_url VARCHAR(500),
    
    -- Ordem e categoria
    category ENUM('safety', 'techniques', 'customer_service', 'business', 'assessment') DEFAULT 'safety',
    sort_order INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_training_code (training_code),
    INDEX idx_status (status),
    INDEX idx_mandatory (is_mandatory),
    INDEX idx_category (category),
    INDEX idx_sort_order (sort_order)
);

-- Inserir treinamentos obrigatórios
INSERT IGNORE INTO trainings (training_code, title, description, is_mandatory, duration_minutes, status, category, sort_order) VALUES
('SAFETY_001', 'Workplace Health & Safety', 'Essential safety procedures, hazard identification, and emergency protocols for cleaning professionals', TRUE, 45, 'active', 'safety', 1),
('CUSTOMER_001', 'Customer Service Excellence', 'Professional communication, handling complaints, and maintaining high service standards', TRUE, 30, 'active', 'customer_service', 2),
('EQUIPMENT_001', 'Equipment & Chemical Safety', 'Proper use of cleaning equipment, chemical handling, and safety data sheets', TRUE, 60, 'active', 'safety', 3),
('TECHNIQUES_001', 'Basic Cleaning Techniques', 'Standard cleaning procedures for residential and commercial properties', TRUE, 90, 'active', 'techniques', 4),
('BUSINESS_001', 'Professional Standards', 'Punctuality, dress code, privacy, and professional boundaries', TRUE, 20, 'active', 'business', 5);

-- Tabela de perguntas para treinamentos
CREATE TABLE IF NOT EXISTS training_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    training_id BIGINT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer') DEFAULT 'multiple_choice',
    
    -- Opções para múltipla escolha (JSON)
    options JSON,
    correct_answer TEXT NOT NULL,
    explanation TEXT,
    
    points TINYINT UNSIGNED DEFAULT 1,
    sort_order INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_training_id (training_id),
    INDEX idx_sort_order (sort_order)
);

-- Tabela de progresso de treinamento dos candidatos
CREATE TABLE IF NOT EXISTS candidate_trainings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_id BIGINT UNSIGNED NOT NULL,
    training_id BIGINT UNSIGNED NOT NULL,
    
    -- Progresso
    status ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started',
    progress_percentage TINYINT UNSIGNED DEFAULT 0,
    
    -- Tentativas de avaliação
    attempts_count TINYINT UNSIGNED DEFAULT 0,
    current_score DECIMAL(5,2) NULL,
    best_score DECIMAL(5,2) NULL,
    passed BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    last_accessed_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_candidate_training (candidate_id, training_id),
    INDEX idx_candidate_id (candidate_id),
    INDEX idx_training_id (training_id),
    INDEX idx_status (status)
);

-- ============================================================================
-- TABELAS AUXILIARES
-- ============================================================================

-- Tabela de notificações
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    user_type ENUM('customer', 'professional', 'admin') NOT NULL,
    
    type ENUM('booking_confirmed', 'booking_reminder', 'payment_received', 'training_due', 'system_alert') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    
    -- Canais de entrega
    send_email BOOLEAN DEFAULT TRUE,
    send_sms BOOLEAN DEFAULT FALSE,
    send_push BOOLEAN DEFAULT FALSE,
    
    -- Status de entrega
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    sms_sent BOOLEAN DEFAULT FALSE,
    sms_sent_at TIMESTAMP NULL,
    
    -- Status de leitura
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    
    -- Dados adicionais (JSON)
    data JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id, user_type),
    INDEX idx_type (type),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
);

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS system_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
);

-- Inserir configurações padrão
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('business_name', 'Blue Cleaning Services Pty Ltd', 'string', 'Nome da empresa', TRUE),
('business_phone', '+61 2 9876 5432', 'string', 'Telefone comercial', TRUE),
('business_email', 'info@bluecleaningservices.com.au', 'string', 'Email comercial', TRUE),
('gst_rate', '0.10', 'decimal', 'Taxa de GST (10%)', FALSE),
('default_service_area', 'Sydney Metro', 'string', 'Área de serviço padrão', TRUE),
('booking_advance_hours', '24', 'integer', 'Horas mínimas para agendamento', TRUE),
('payment_methods', '["card", "afterpay"]', 'json', 'Métodos de pagamento aceitos', TRUE),
('training_passing_score', '80.0', 'decimal', 'Pontuação mínima para aprovação em treinamentos', FALSE);

-- ============================================================================
-- TRIGGERS E PROCEDURES AUXILIARES
-- ============================================================================

-- Trigger para gerar códigos de booking automaticamente
DELIMITER //
CREATE TRIGGER IF NOT EXISTS tr_bookings_generate_code 
    BEFORE INSERT ON bookings 
    FOR EACH ROW 
BEGIN 
    IF NEW.booking_code IS NULL OR NEW.booking_code = '' THEN
        SET NEW.booking_code = CONCAT('BK', DATE_FORMAT(NOW(), '%y%m'), LPAD(LAST_INSERT_ID() + 1, 6, '0'));
    END IF;
    
    -- Calcular GST e total
    IF NEW.gst_amount = 0 THEN
        SET NEW.gst_amount = (NEW.base_price + NEW.extras_price - NEW.discount_amount) * 0.10;
    END IF;
    
    IF NEW.total_amount = 0 THEN
        SET NEW.total_amount = NEW.base_price + NEW.extras_price - NEW.discount_amount + NEW.gst_amount;
    END IF;
END //
DELIMITER ;

-- Trigger para gerar códigos de pagamento
DELIMITER //
CREATE TRIGGER IF NOT EXISTS tr_payments_generate_code 
    BEFORE INSERT ON payments 
    FOR EACH ROW 
BEGIN 
    IF NEW.payment_code IS NULL OR NEW.payment_code = '' THEN
        SET NEW.payment_code = CONCAT('PAY', DATE_FORMAT(NOW(), '%y%m'), LPAD(LAST_INSERT_ID() + 1, 6, '0'));
    END IF;
END //
DELIMITER ;

-- ============================================================================
-- DADOS DE TESTE (DESENVOLVIMENTO)
-- ============================================================================

-- Inserir candidato de teste
INSERT IGNORE INTO candidates (
    candidate_code, email, first_name, last_name, phone, 
    suburb, state, postcode, has_experience, status
) VALUES (
    'CAND_001', 'candidate@test.com', 'Test', 'Candidate', '+61423456789',
    'Parramatta', 'NSW', '2150', TRUE, 'applied'
);

-- Log da instalação completa
INSERT INTO security_audit_log (
    user_type, action, description, ip_address, result, severity
) VALUES (
    'system', 'complete_schema_install', 
    'Complete Blue Cleaning Services schema installed with booking system, training modules, and auxiliary tables', 
    '127.0.0.1', 'success', 'medium'
);

-- ============================================================================
-- FIM DO SCHEMA COMPLETO
-- ============================================================================
