-- ====================================
-- TABELAS DE RELACIONAMENTO PARA BOOKING DINÂMICO
-- Data: 8 de agosto de 2025
-- ====================================

-- Tabela para armazenar extras selecionados em cada booking
CREATE TABLE IF NOT EXISTS booking_extras (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    extra_id INT NOT NULL,
    price_paid DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (extra_id) REFERENCES service_extras(id),
    UNIQUE KEY unique_booking_extra (booking_id, extra_id)
);

-- Tabela para armazenar preferências selecionadas em cada booking
CREATE TABLE IF NOT EXISTS booking_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    preference_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (preference_id) REFERENCES cleaning_preferences(id),
    UNIQUE KEY unique_booking_preference (booking_id, preference_id)
);

-- Adicionar colunas necessárias na tabela bookings se não existirem
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS referral_user_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS discount_percentage DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS extras_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS gst_amount DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS special_instructions TEXT DEFAULT NULL;

-- Índices para performance
CREATE INDEX idx_booking_extras_booking ON booking_extras (booking_id);
CREATE INDEX idx_booking_preferences_booking ON booking_preferences (booking_id);
CREATE INDEX idx_bookings_referral ON bookings (referral_code);
CREATE INDEX idx_bookings_referral_user ON bookings (referral_user_id);

-- ====================================
-- ATUALIZAÇÃO DA TABELA BOOKINGS EXISTENTE
-- ====================================

-- Se a tabela bookings já existir, adicionar as colunas que faltam
-- (Este comando é seguro, ignora se as colunas já existirem)

-- Para MySQL 8.0+
SET @sql = CONCAT(
    'ALTER TABLE bookings ',
    'ADD COLUMN IF NOT EXISTS reference_number VARCHAR(20) UNIQUE DEFAULT NULL, ',
    'ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0.00'
);

-- Executar se necessário (comentado para evitar erros)
-- PREPARE stmt FROM @sql;
-- EXECUTE stmt;
-- DEALLOCATE PREPARE stmt;

-- ====================================
-- COMENTÁRIOS FINAIS
-- ====================================

-- Estas tabelas complementam o sistema dinâmico permitindo:
-- 1. Rastrear quais extras foram selecionados em cada booking
-- 2. Rastrear quais preferências foram selecionadas
-- 3. Armazenar preços pagos pelos extras (para histórico)
-- 4. Relacionar bookings com códigos de referral
