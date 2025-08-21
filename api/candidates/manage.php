/**
 * Candidate Management API
 * Blue Cleaning Services - Professional Recruitment System
 */

<?php
require_once __DIR__ . '/../../config/australian-database.php';
require_once __DIR__ . '/../../config/email-config.php';
require_once __DIR__ . '/training.php';

class CandidateManager {
    private $pdo;
    private $emailService;
    private $trainingManager;
    
    public function __construct() {
        $this->pdo = AustralianDatabase::getInstance()->getConnection();
        $this->emailService = new EmailService();
        $this->trainingManager = new TrainingManager();
    }
    
    /**
     * Registrar novo candidato
     */
    public function registerCandidate($data) {
        try {
            // Validar dados obrigatórios
            $required = ['email', 'first_name', 'last_name', 'phone'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Campo obrigatório: {$field}");
                }
            }
            
            // Verificar se já existe candidato com este email
            if ($this->candidateExists($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Este email já está cadastrado como candidato'
                ];
            }
            
            // Verificar se já é profissional ativo
            if ($this->isProfessional($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Este email já está cadastrado como profissional'
                ];
            }
            
            // Validar idade mínima
            if (!empty($data['date_of_birth'])) {
                $age = $this->calculateAge($data['date_of_birth']);
                $minAge = $this->getSetting('minimum_age_requirement', 18);
                
                if ($age < $minAge) {
                    return [
                        'success' => false,
                        'message' => "Idade mínima requerida: {$minAge} anos"
                    ];
                }
            }
            
            // Inserir candidato
            $stmt = $this->pdo->prepare("
                INSERT INTO candidates (
                    email, first_name, last_name, phone, date_of_birth,
                    address, city, state, postal_code, referral_source,
                    emergency_contact_name, emergency_contact_phone,
                    has_transport, has_experience, experience_years,
                    availability, preferred_areas, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'applied')
            ");
            
            $stmt->execute([
                $data['email'],
                $data['first_name'],
                $data['last_name'],
                $data['phone'],
                $data['date_of_birth'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $data['postal_code'] ?? null,
                $data['referral_source'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
                isset($data['has_transport']) ? (bool)$data['has_transport'] : false,
                isset($data['has_experience']) ? (bool)$data['has_experience'] : false,
                intval($data['experience_years'] ?? 0),
                !empty($data['availability']) ? json_encode($data['availability']) : null,
                !empty($data['preferred_areas']) ? json_encode($data['preferred_areas']) : null
            ]);
            
            $candidateId = $this->pdo->lastInsertId();
            
            // Atribuir treinamentos obrigatórios
            $this->assignOnboardingTrainings($candidateId);
            
            // Enviar email de boas-vindas
            if ($this->getSetting('send_welcome_email', true)) {
                $this->sendWelcomeEmail($candidateId);
            }
            
            // Log da aplicação
            $this->logCandidateEvent($candidateId, 'application_submitted', [
                'referral_source' => $data['referral_source'] ?? null,
                'has_experience' => $data['has_experience'] ?? false,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return [
                'success' => true,
                'candidate_id' => $candidateId,
                'message' => 'Candidatura registrada com sucesso! Você receberá um email com as próximas etapas.'
            ];
            
        } catch (Exception $e) {
            error_log("Candidate registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao registrar candidatura. Tente novamente.'
            ];
        }
    }
    
    /**
     * Iniciar treinamento para candidato
     */
    public function startTraining($candidateId) {
        try {
            $candidate = $this->getCandidate($candidateId);
            if (!$candidate) {
                throw new Exception('Candidato não encontrado');
            }
            
            if ($candidate['status'] !== 'applied') {
                return [
                    'success' => false,
                    'message' => 'Candidato não está no status correto para iniciar treinamento'
                ];
            }
            
            // Atualizar status
            $this->updateCandidateStatus($candidateId, 'in_training');
            
            // Marcar início do treinamento
            $stmt = $this->pdo->prepare("
                UPDATE candidates 
                SET training_started_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$candidateId]);
            
            // Atualizar progresso dos treinamentos
            $stmt = $this->pdo->prepare("
                UPDATE candidate_training_progress 
                SET status = 'in_progress', started_at = NOW() 
                WHERE candidate_id = ? AND status = 'not_started'
            ");
            $stmt->execute([$candidateId]);
            
            return [
                'success' => true,
                'message' => 'Treinamento iniciado com sucesso!',
                'trainings' => $this->getCandidateTrainings($candidateId)
            ];
            
        } catch (Exception $e) {
            error_log("Start training error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao iniciar treinamento'
            ];
        }
    }
    
    /**
     * Completar módulo de treinamento
     */
    public function completeTrainingModule($candidateId, $trainingId, $moduleId) {
        try {
            // Atualizar progresso
            $progress = $this->calculateTrainingProgress($candidateId, $trainingId);
            
            $stmt = $this->pdo->prepare("
                UPDATE candidate_training_progress 
                SET progress_percentage = ?, 
                    current_module_id = ?,
                    last_accessed_at = NOW(),
                    time_spent = time_spent + ?
                WHERE candidate_id = ? AND training_id = ?
            ");
            
            $timeSpent = 300; // 5 minutos por módulo (exemplo)
            $stmt->execute([$progress, $moduleId, $timeSpent, $candidateId, $trainingId]);
            
            // Verificar se completou o treinamento
            if ($progress >= 100) {
                $this->completeTraining($candidateId, $trainingId);
            }
            
            return [
                'success' => true,
                'progress' => $progress,
                'completed' => $progress >= 100
            ];
            
        } catch (Exception $e) {
            error_log("Complete module error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao atualizar progresso'
            ];
        }
    }
    
    /**
     * Completar treinamento
     */
    public function completeTraining($candidateId, $trainingId) {
        try {
            // Marcar treinamento como completo
            $stmt = $this->pdo->prepare("
                UPDATE candidate_training_progress 
                SET status = 'completed', 
                    progress_percentage = 100,
                    completed_at = NOW()
                WHERE candidate_id = ? AND training_id = ?
            ");
            $stmt->execute([$candidateId, $trainingId]);
            
            // Verificar se todos os treinamentos obrigatórios foram completados
            if ($this->hasCompletedAllTrainings($candidateId)) {
                $this->updateCandidateStatus($candidateId, 'evaluated');
                
                // Marcar data de conclusão do treinamento
                $stmt = $this->pdo->prepare("
                    UPDATE candidates 
                    SET training_completed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$candidateId]);
                
                return [
                    'success' => true,
                    'message' => 'Todos os treinamentos concluídos! Você pode iniciar as avaliações.',
                    'ready_for_evaluation' => true
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Treinamento concluído!',
                'ready_for_evaluation' => false
            ];
            
        } catch (Exception $e) {
            error_log("Complete training error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao completar treinamento'
            ];
        }
    }
    
    /**
     * Submeter avaliação
     */
    public function submitEvaluation($candidateId, $trainingId, $evaluationId, $answers) {
        try {
            $candidate = $this->getCandidate($candidateId);
            if (!$candidate || $candidate['status'] !== 'evaluated') {
                throw new Exception('Candidato não está autorizado para fazer avaliação');
            }
            
            // Verificar tentativas máximas
            $attempts = $this->getEvaluationAttempts($candidateId, $trainingId, $evaluationId);
            $maxAttempts = $this->getSetting('max_evaluation_attempts', 3);
            
            if ($attempts >= $maxAttempts) {
                return [
                    'success' => false,
                    'message' => 'Número máximo de tentativas excedido'
                ];
            }
            
            // Calcular pontuação
            $result = $this->calculateEvaluationScore($evaluationId, $answers);
            $attemptNumber = $attempts + 1;
            $minScore = $this->getSetting('minimum_approval_score', 80);
            $passed = $result['score'] >= $minScore;
            
            // Salvar resultado
            $stmt = $this->pdo->prepare("
                INSERT INTO candidate_evaluation_results (
                    candidate_id, training_id, evaluation_id, attempt_number,
                    total_questions, correct_answers, score, passed,
                    time_taken, answers, started_at, completed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $candidateId,
                $trainingId,
                $evaluationId,
                $attemptNumber,
                $result['total_questions'],
                $result['correct_answers'],
                $result['score'],
                $passed,
                $result['time_taken'] ?? null,
                json_encode($answers),
                date('Y-m-d H:i:s', strtotime('-' . ($result['time_taken'] ?? 1800) . ' seconds'))
            ]);
            
            // Atualizar melhor pontuação
            $this->updateBestScore($candidateId, $trainingId, $result['score']);
            
            // Se passou na avaliação, verificar se pode ser aprovado
            if ($passed) {
                if ($this->hasPassedAllEvaluations($candidateId)) {
                    $this->approveCandidate($candidateId);
                    
                    return [
                        'success' => true,
                        'passed' => true,
                        'score' => $result['score'],
                        'approved' => true,
                        'message' => 'Parabéns! Você foi aprovado e agora pode completar seu perfil profissional.'
                    ];
                }
            }
            
            return [
                'success' => true,
                'passed' => $passed,
                'score' => $result['score'],
                'attempts_left' => $maxAttempts - $attemptNumber,
                'message' => $passed 
                    ? 'Avaliação aprovada!' 
                    : "Pontuação insuficiente. Você precisa de pelo menos {$minScore}%. Tentativas restantes: " . ($maxAttempts - $attemptNumber)
            ];
            
        } catch (Exception $e) {
            error_log("Submit evaluation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao processar avaliação'
            ];
        }
    }
    
    /**
     * Aprovar candidato e promover a profissional
     */
    public function approveCandidate($candidateId) {
        try {
            $this->pdo->beginTransaction();
            
            $candidate = $this->getCandidate($candidateId);
            if (!$candidate) {
                throw new Exception('Candidato não encontrado');
            }
            
            // Criar usuário profissional
            $userId = $this->createProfessionalUser($candidate);
            
            // Atualizar status do candidato
            $stmt = $this->pdo->prepare("
                UPDATE candidates 
                SET status = 'approved', approved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$candidateId]);
            
            // Log da aprovação
            $this->logCandidateEvent($candidateId, 'candidate_approved', [
                'professional_user_id' => $userId,
                'approval_method' => 'automatic'
            ]);
            
            // Enviar email de aprovação
            $this->sendApprovalEmail($candidateId, $userId);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'Candidato aprovado e promovido a profissional'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Approve candidate error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao aprovar candidato'
            ];
        }
    }
    
    /**
     * Criar usuário profissional a partir do candidato
     */
    private function createProfessionalUser($candidate) {
        // Gerar senha temporária
        $tempPassword = $this->generateTempPassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        // Criar usuário
        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                name, email, password, user_type, status,
                candidate_id, source, profile_completion_percentage,
                phone, date_of_birth, address, city, state, postal_code,
                created_at
            ) VALUES (?, ?, ?, 'professional', 'onboarding', ?, 'candidate_approved', 20, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $fullName = $candidate['first_name'] . ' ' . $candidate['last_name'];
        
        $stmt->execute([
            $fullName,
            $candidate['email'],
            $hashedPassword,
            $candidate['id'],
            $candidate['phone'],
            $candidate['date_of_birth'],
            $candidate['address'],
            $candidate['city'],
            $candidate['state'],
            $candidate['postal_code']
        ]);
        
        $userId = $this->pdo->lastInsertId();
        
        // Criar perfil profissional básico
        $stmt = $this->pdo->prepare("
            INSERT INTO professionals (
                user_id, name, email, phone, status, 
                has_transport, experience_years, emergency_contact_name, 
                emergency_contact_phone, created_at
            ) VALUES (?, ?, ?, ?, 'onboarding', ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $fullName,
            $candidate['email'],
            $candidate['phone'],
            $candidate['has_transport'],
            $candidate['experience_years'],
            $candidate['emergency_contact_name'],
            $candidate['emergency_contact_phone']
        ]);
        
        // Transferir skills dos treinamentos completados
        $this->transferTrainingSkills($candidate['id'], $userId);
        
        return $userId;
    }
    
    /**
     * Obter candidato por ID
     */
    public function getCandidate($candidateId) {
        $stmt = $this->pdo->prepare("SELECT * FROM candidates WHERE id = ?");
        $stmt->execute([$candidateId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter progresso completo do candidato
     */
    public function getCandidateProgress($candidateId) {
        try {
            $candidate = $this->getCandidate($candidateId);
            if (!$candidate) {
                return ['success' => false, 'message' => 'Candidato não encontrado'];
            }
            
            // Progresso dos treinamentos
            $trainings = $this->getCandidateTrainings($candidateId);
            
            // Resultados das avaliações
            $evaluations = $this->getCandidateEvaluations($candidateId);
            
            // Documentos enviados
            $documents = $this->getCandidateDocuments($candidateId);
            
            // Próximos passos
            $nextSteps = $this->getNextSteps($candidate);
            
            return [
                'success' => true,
                'candidate' => $candidate,
                'trainings' => $trainings,
                'evaluations' => $evaluations,
                'documents' => $documents,
                'next_steps' => $nextSteps,
                'overall_progress' => $this->calculateOverallProgress($candidateId)
            ];
            
        } catch (Exception $e) {
            error_log("Get candidate progress error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao obter progresso do candidato'
            ];
        }
    }
    
    /**
     * Upload de documento do candidato
     */
    public function uploadDocument($candidateId, $documentType, $file) {
        try {
            $candidate = $this->getCandidate($candidateId);
            if (!$candidate) {
                return ['success' => false, 'message' => 'Candidato não encontrado'];
            }
            
            // Validar tipo de documento
            $allowedTypes = ['rg', 'cpf', 'proof_address', 'photo', 'resume', 'reference', 'criminal_record'];
            if (!in_array($documentType, $allowedTypes)) {
                return ['success' => false, 'message' => 'Tipo de documento inválido'];
            }
            
            // Validar arquivo
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'Erro no upload do arquivo'];
            }
            
            $allowedMimeTypes = [
                'image/jpeg', 'image/png', 'image/gif',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                return ['success' => false, 'message' => 'Tipo de arquivo não permitido'];
            }
            
            // Salvar arquivo
            $uploadDir = __DIR__ . '/../../uploads/candidates/' . $candidateId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = $documentType . '_' . time() . '_' . $file['name'];
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Falha ao salvar arquivo'];
            }
            
            // Registrar no banco
            $stmt = $this->pdo->prepare("
                INSERT INTO candidate_documents (
                    candidate_id, document_type, file_name, file_path,
                    file_size, mime_type, verification_status
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $candidateId,
                $documentType,
                $fileName,
                'uploads/candidates/' . $candidateId . '/' . $fileName,
                $file['size'],
                $mimeType
            ]);
            
            return [
                'success' => true,
                'document_id' => $this->pdo->lastInsertId(),
                'message' => 'Documento enviado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log("Upload document error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar documento'
            ];
        }
    }
    
    /**
     * Métodos auxiliares privados
     */
    private function candidateExists($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM candidates WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function isProfessional($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND user_type = 'professional'");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function calculateAge($dateOfBirth) {
        return floor((time() - strtotime($dateOfBirth)) / 31556926);
    }
    
    private function getSetting($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT setting_value, setting_type FROM candidate_system_settings WHERE setting_key = ? AND is_active = 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $default;
        }
        
        switch ($result['setting_type']) {
            case 'number':
                return (float)$result['setting_value'];
            case 'boolean':
                return $result['setting_value'] === 'true';
            case 'json':
                return json_decode($result['setting_value'], true);
            default:
                return $result['setting_value'];
        }
    }
    
    private function assignOnboardingTrainings($candidateId) {
        // Obter treinamentos obrigatórios
        $stmt = $this->pdo->prepare("
            SELECT id FROM trainings 
            WHERE is_onboarding_required = 1 AND active = 1 
            ORDER BY onboarding_order
        ");
        $stmt->execute();
        $trainings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Atribuir cada treinamento ao candidato
        foreach ($trainings as $training) {
            $stmt = $this->pdo->prepare("
                INSERT INTO candidate_training_progress (
                    candidate_id, training_id, status, progress_percentage
                ) VALUES (?, ?, 'not_started', 0)
            ");
            $stmt->execute([$candidateId, $training['id']]);
        }
    }
    
    private function updateCandidateStatus($candidateId, $newStatus) {
        $stmt = $this->pdo->prepare("UPDATE candidates SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $candidateId]);
        
        // Log será criado automaticamente pelo trigger
    }
    
    private function logCandidateEvent($candidateId, $eventType, $metadata = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO candidate_status_history (
                candidate_id, new_status, changed_by_type, reason, metadata
            ) VALUES (?, ?, 'system', ?, ?)
        ");
        
        $stmt->execute([
            $candidateId,
            $eventType,
            'System automated event',
            json_encode($metadata)
        ]);
    }
    
    private function generateTempPassword() {
        return 'Temp' . rand(1000, 9999) . '!';
    }
    
    // Outros métodos auxiliares continuarão...
}

// API Endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $candidateManager = new CandidateManager();
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register':
            echo json_encode($candidateManager->registerCandidate($_POST));
            break;
            
        case 'start_training':
            $candidateId = $_POST['candidate_id'] ?? '';
            echo json_encode($candidateManager->startTraining($candidateId));
            break;
            
        case 'complete_module':
            echo json_encode($candidateManager->completeTrainingModule(
                $_POST['candidate_id'] ?? '',
                $_POST['training_id'] ?? '',
                $_POST['module_id'] ?? ''
            ));
            break;
            
        case 'submit_evaluation':
            echo json_encode($candidateManager->submitEvaluation(
                $_POST['candidate_id'] ?? '',
                $_POST['training_id'] ?? '',
                $_POST['evaluation_id'] ?? '',
                json_decode($_POST['answers'] ?? '[]', true)
            ));
            break;
            
        case 'upload_document':
            echo json_encode($candidateManager->uploadDocument(
                $_POST['candidate_id'] ?? '',
                $_POST['document_type'] ?? '',
                $_FILES['document'] ?? null
            ));
            break;
            
        case 'get_progress':
            $candidateId = $_POST['candidate_id'] ?? '';
            echo json_encode($candidateManager->getCandidateProgress($candidateId));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    
    $candidateManager = new CandidateManager();
    $candidateId = $_GET['candidate_id'] ?? '';
    
    if ($candidateId) {
        echo json_encode($candidateManager->getCandidateProgress($candidateId));
    } else {
        echo json_encode(['success' => false, 'message' => 'Candidate ID required']);
    }
}
?>
