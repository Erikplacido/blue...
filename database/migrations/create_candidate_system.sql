/**
 * Candidate Onboarding System - Database Schema
 * Blue Cleaning Services - Professional Recruitment via Training
 */

-- 1. Tabela de candidatos
CREATE TABLE candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(50),
    postal_code VARCHAR(20),
    status ENUM('applied', 'in_training', 'evaluated', 'approved', 'rejected', 'onboarding', 'completed') DEFAULT 'applied',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    training_started_at TIMESTAMP NULL,
    training_completed_at TIMESTAMP NULL,
    evaluation_score DECIMAL(5,2) DEFAULT 0,
    evaluation_attempts INT DEFAULT 0,
    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT,
    notes TEXT,
    referral_source VARCHAR(100),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    has_transport BOOLEAN DEFAULT FALSE,
    has_experience BOOLEAN DEFAULT FALSE,
    experience_years INT DEFAULT 0,
    availability JSON,
    preferred_areas JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_application_date (application_date)
);

-- 2. Histórico de status do candidato
CREATE TABLE candidate_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT,
    changed_by_type ENUM('system', 'admin', 'candidate') DEFAULT 'system',
    reason TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    INDEX idx_candidate_status (candidate_id, new_status)
);

-- 3. Progresso do candidato nos treinamentos
CREATE TABLE candidate_training_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    training_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started',
    progress_percentage DECIMAL(5,2) DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    time_spent INT DEFAULT 0, -- em segundos
    current_module_id INT,
    attempts INT DEFAULT 0,
    best_score DECIMAL(5,2),
    last_accessed_at TIMESTAMP NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_candidate_training (candidate_id, training_id),
    INDEX idx_candidate_progress (candidate_id, status)
);

-- 4. Resultados das avaliações dos candidatos
CREATE TABLE candidate_evaluation_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    training_id INT NOT NULL,
    evaluation_id INT NOT NULL,
    attempt_number INT NOT NULL,
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    passed BOOLEAN NOT NULL,
    time_taken INT, -- em segundos
    answers JSON, -- respostas detalhadas
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NOT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    INDEX idx_candidate_evaluation (candidate_id, training_id),
    INDEX idx_evaluation_attempts (candidate_id, attempt_number)
);

-- 5. Documentos enviados pelos candidatos
CREATE TABLE candidate_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    document_type ENUM('rg', 'cpf', 'proof_address', 'photo', 'resume', 'reference', 'criminal_record', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    verification_status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
    verified_at TIMESTAMP NULL,
    verified_by INT NULL,
    expiry_date DATE NULL,
    notes TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    INDEX idx_candidate_docs (candidate_id, document_type),
    INDEX idx_verification_status (verification_status)
);

-- 6. Modificar tabela de usuários para incluir origem do candidato
ALTER TABLE users ADD COLUMN IF NOT EXISTS candidate_id INT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS source ENUM('direct', 'candidate_approved', 'social_login') DEFAULT 'direct';
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_completion_percentage INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS onboarding_completed_at TIMESTAMP NULL;

-- 7. Modificar tabela de treinamentos para marcar obrigatórios
ALTER TABLE trainings ADD COLUMN IF NOT EXISTS is_onboarding_required BOOLEAN DEFAULT FALSE;
ALTER TABLE trainings ADD COLUMN IF NOT EXISTS onboarding_order INT DEFAULT 0;
ALTER TABLE trainings ADD COLUMN IF NOT EXISTS minimum_score_required DECIMAL(5,2) DEFAULT 70.00;
ALTER TABLE trainings ADD COLUMN IF NOT EXISTS max_attempts INT DEFAULT 3;

-- 8. Tabela de configurações do sistema de candidatos
CREATE TABLE candidate_system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key),
    INDEX idx_category (category)
);

-- 9. Inserir configurações padrão
INSERT INTO candidate_system_settings (setting_key, setting_value, setting_type, description, category) VALUES
('minimum_age_requirement', '18', 'number', 'Idade mínima para candidatos', 'requirements'),
('max_training_days', '30', 'number', 'Dias máximos para completar treinamento', 'training'),
('minimum_approval_score', '80', 'number', 'Pontuação mínima para aprovação (%)', 'evaluation'),
('max_evaluation_attempts', '3', 'number', 'Máximo de tentativas na avaliação', 'evaluation'),
('auto_approve_after_training', 'true', 'boolean', 'Aprovação automática após treinamento', 'automation'),
('require_background_check', 'false', 'boolean', 'Verificação de antecedentes obrigatória', 'requirements'),
('send_welcome_email', 'true', 'boolean', 'Enviar email de boas-vindas', 'communication'),
('candidate_profile_fields', '["emergency_contact", "transport", "experience", "availability"]', 'json', 'Campos obrigatórios do perfil', 'requirements'),
('required_documents', '["rg", "cpf", "proof_address", "photo"]', 'json', 'Documentos obrigatórios', 'requirements');

-- 10. Tabela para tracking de emails enviados
CREATE TABLE candidate_email_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    email_type ENUM('welcome', 'training_reminder', 'evaluation_result', 'approval', 'rejection', 'profile_incomplete') NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    template_used VARCHAR(100),
    send_status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    INDEX idx_candidate_email (candidate_id, email_type),
    INDEX idx_send_status (send_status, created_at)
);

-- 11. View para candidatos com progresso completo
CREATE VIEW candidate_overview AS
SELECT 
    c.*,
    COUNT(ctp.id) as total_trainings_assigned,
    COUNT(CASE WHEN ctp.status = 'completed' THEN 1 END) as trainings_completed,
    ROUND(AVG(ctp.progress_percentage), 2) as avg_progress,
    MAX(cer.score) as best_evaluation_score,
    COUNT(DISTINCT cd.id) as documents_uploaded,
    COUNT(CASE WHEN cd.verification_status = 'approved' THEN 1 END) as documents_approved
FROM candidates c
LEFT JOIN candidate_training_progress ctp ON c.id = ctp.candidate_id
LEFT JOIN candidate_evaluation_results cer ON c.id = cer.candidate_id
LEFT JOIN candidate_documents cd ON c.id = cd.candidate_id
GROUP BY c.id;

-- 12. Triggers para auditoria automática
DELIMITER //

CREATE TRIGGER candidate_status_change_trigger 
AFTER UPDATE ON candidates
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO candidate_status_history (
            candidate_id, previous_status, new_status, 
            changed_by_type, reason, created_at
        ) VALUES (
            NEW.id, OLD.status, NEW.status, 
            'system', 'Automatic status change', NOW()
        );
    END IF;
END//

DELIMITER ;

-- 13. Função para calcular progresso geral do candidato
DELIMITER //

CREATE FUNCTION calculate_candidate_progress(candidate_id_param INT) 
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_progress DECIMAL(5,2) DEFAULT 0;
    DECLARE total_trainings INT DEFAULT 0;
    
    SELECT 
        COUNT(*) INTO total_trainings
    FROM candidate_training_progress 
    WHERE candidate_id = candidate_id_param;
    
    IF total_trainings > 0 THEN
        SELECT 
            AVG(progress_percentage) INTO total_progress
        FROM candidate_training_progress 
        WHERE candidate_id = candidate_id_param;
    END IF;
    
    RETURN COALESCE(total_progress, 0);
END//

DELIMITER ;

-- 14. Índices para performance
CREATE INDEX idx_candidates_status_date ON candidates(status, application_date);
CREATE INDEX idx_training_progress_candidate ON candidate_training_progress(candidate_id, status, updated_at);
CREATE INDEX idx_evaluation_results_score ON candidate_evaluation_results(candidate_id, score DESC);
CREATE INDEX idx_documents_verification ON candidate_documents(candidate_id, verification_status);

-- 15. Comentários para documentação
ALTER TABLE candidates COMMENT = 'Tabela principal de candidatos a profissionais';
ALTER TABLE candidate_status_history COMMENT = 'Histórico de mudanças de status dos candidatos';
ALTER TABLE candidate_training_progress COMMENT = 'Progresso dos candidatos nos treinamentos obrigatórios';
ALTER TABLE candidate_evaluation_results COMMENT = 'Resultados das avaliações dos candidatos';
ALTER TABLE candidate_documents COMMENT = 'Documentos enviados pelos candidatos para verificação';
ALTER TABLE candidate_system_settings COMMENT = 'Configurações do sistema de candidatos';
ALTER TABLE candidate_email_log COMMENT = 'Log de emails enviados aos candidatos';

-- Executar ao final para garantir integridade
ANALYZE TABLE candidates, candidate_training_progress, candidate_evaluation_results, candidate_documents;
