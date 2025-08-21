-- ============================================================================
-- BLUE CLEANING SERVICES - SIMPLIFIED AUTHENTICATION SCHEMA
-- Versão simplificada para MariaDB/MySQL compatível com shared hosting
-- ============================================================================

-- Tabela: customers (clientes do sistema)
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    postcode VARCHAR(10),
    city VARCHAR(100),
    state VARCHAR(50) DEFAULT 'NSW',
    country VARCHAR(50) DEFAULT 'Australia',
    email_verified BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    timezone VARCHAR(50) DEFAULT 'Australia/Sydney'
);

-- Tabela: professionals (profissionais de limpeza)
CREATE TABLE IF NOT EXISTS professionals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    abn VARCHAR(20),
    business_name VARCHAR(200),
    address TEXT,
    postcode VARCHAR(10),
    city VARCHAR(100),
    state VARCHAR(50) DEFAULT 'NSW',
    country VARCHAR(50) DEFAULT 'Australia',
    service_areas TEXT,
    hourly_rate DECIMAL(8,2),
    availability JSON,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_jobs INT DEFAULT 0,
    verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended', 'pending_verification') DEFAULT 'pending_verification',
    timezone VARCHAR(50) DEFAULT 'Australia/Sydney'
);

-- Tabela: admin_users (usuários administrativos)
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'manager', 'support') DEFAULT 'admin',
    permissions JSON,
    last_login TIMESTAMP NULL DEFAULT NULL,
    last_login_ip VARCHAR(45),
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);

-- Tabela: password_reset_tokens (tokens para reset de senha)
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    user_type ENUM('customer', 'professional', 'admin') NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL DEFAULT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_type (email, user_type),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Tabela: user_sessions (sessões ativas dos usuários)
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('customer', 'professional', 'admin') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_user (user_id, user_type),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity)
);

-- Tabela: security_audit_log (log de auditoria de segurança)
CREATE TABLE IF NOT EXISTS security_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_type ENUM('customer', 'professional', 'admin', 'system'),
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_data JSON,
    result ENUM('success', 'failure', 'error') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    INDEX idx_severity (severity)
);

-- Tabela: two_factor_auth (autenticação de dois fatores)
CREATE TABLE IF NOT EXISTS two_factor_auth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('customer', 'professional', 'admin') NOT NULL,
    method ENUM('email', 'sms', 'app') NOT NULL,
    secret VARCHAR(255),
    backup_codes JSON,
    enabled BOOLEAN DEFAULT FALSE,
    verified BOOLEAN DEFAULT FALSE,
    last_used TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type)
);

-- Tabela: login_attempts (tentativas de login para controle de rate limiting)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    user_type ENUM('customer', 'professional', 'admin') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN NOT NULL DEFAULT FALSE,
    failure_reason VARCHAR(100),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_ip (email, ip_address),
    INDEX idx_created (created_at),
    INDEX idx_success (success)
);

-- ============================================================================
-- DADOS INICIAIS
-- ============================================================================

-- Usuário admin padrão (SENHA DEVE SER ALTERADA!)
INSERT IGNORE INTO admin_users (
    email, 
    password, 
    first_name, 
    last_name, 
    role, 
    permissions,
    status
) VALUES (
    'admin@bluecleaningservices.com.au',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password
    'Sistema',
    'Administrador',
    'super_admin',
    '{"users": {"create": true, "read": true, "update": true, "delete": true}, "bookings": {"create": true, "read": true, "update": true, "delete": true}, "reports": {"create": true, "read": true, "update": true, "delete": true}, "system": {"create": true, "read": true, "update": true, "delete": true}}',
    'active'
);

-- ============================================================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ============================================================================

-- Otimização para consultas de autenticação
ALTER TABLE customers ADD INDEX idx_email_status (email, status);
ALTER TABLE professionals ADD INDEX idx_email_status (email, status);
ALTER TABLE admin_users ADD INDEX idx_email_status (email, status);

-- Otimização para limpeza automática de dados expirados
ALTER TABLE password_reset_tokens ADD INDEX idx_cleanup (used, expires_at);
ALTER TABLE user_sessions ADD INDEX idx_cleanup (expires_at);

-- Índices para relatórios e auditoria
ALTER TABLE security_audit_log ADD INDEX idx_reporting (created_at, user_type, action);
ALTER TABLE login_attempts ADD INDEX idx_rate_limit (ip_address, created_at);

-- Log de instalação
INSERT INTO security_audit_log (
    user_type,
    action,
    description,
    ip_address,
    result,
    severity
) VALUES (
    'system',
    'database_setup',
    'Blue Cleaning Services authentication schema installed successfully',
    '127.0.0.1',
    'success',
    'medium'
);
