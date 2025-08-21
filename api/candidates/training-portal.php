<?php
/**
 * Candidate Training Portal API
 * Blue Cleaning Services - Training System for Candidates
 */

require_once __DIR__ . '/../../config/australian-database.php';
require_once __DIR__ . '/../../config/email-config.php';

class CandidateTrainingPortal {
    private $pdo;
    private $candidateId;
    
    public function __construct() {
        $this->pdo = AustralianDatabase::getInstance()->getConnection();
        $this->setCandidateId();
    }
    
    private function setCandidateId() {
        session_start();
        if (!empty($_SESSION['candidate_id'])) {
            $this->candidateId = $_SESSION['candidate_id'];
        } else {
            // Try to get from user session if logged in as professional
            if (!empty($_SESSION['user_id'])) {
                $stmt = $this->pdo->prepare("SELECT candidate_id FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && $user['candidate_id']) {
                    $this->candidateId = $user['candidate_id'];
                }
            }
        }
    }
    
    /**
     * Obter treinamentos do candidato
     */
    public function getTrainings() {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT t.*, 
                       ctp.status, ctp.progress_percentage, ctp.started_at, ctp.completed_at,
                       ctp.attempt_count,
                       cer.score as last_score, cer.passed as last_passed
                FROM candidate_training_progress ctp
                JOIN trainings t ON ctp.training_id = t.id
                LEFT JOIN (
                    SELECT candidate_id, training_id, score, passed,
                           ROW_NUMBER() OVER (PARTITION BY candidate_id, training_id ORDER BY submitted_at DESC) as rn
                    FROM candidate_evaluation_results
                ) cer ON ctp.candidate_id = cer.candidate_id 
                      AND ctp.training_id = cer.training_id 
                      AND cer.rn = 1
                WHERE ctp.candidate_id = ? 
                  AND t.status = 'active'
                ORDER BY 
                    CASE WHEN t.is_required = 1 THEN 0 ELSE 1 END,
                    ctp.assigned_at ASC
            ");
            
            $stmt->execute([$this->candidateId]);
            $trainings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'trainings' => $trainings
            ];
            
        } catch (Exception $e) {
            error_log("Get candidate trainings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao carregar treinamentos'];
        }
    }
    
    /**
     * Obter dados do candidato
     */
    public function getCandidateData() {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT c.*, 
                       COUNT(ctp.training_id) as total_trainings,
                       COUNT(CASE WHEN ctp.status = 'completed' THEN 1 END) as completed_trainings,
                       AVG(CASE WHEN cer.passed = 1 THEN cer.score END) as avg_score
                FROM candidates c
                LEFT JOIN candidate_training_progress ctp ON c.id = ctp.candidate_id
                LEFT JOIN candidate_evaluation_results cer ON c.id = cer.candidate_id
                WHERE c.id = ?
                GROUP BY c.id
            ");
            
            $stmt->execute([$this->candidateId]);
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'candidate' => $candidate
            ];
            
        } catch (Exception $e) {
            error_log("Get candidate data error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao carregar dados do candidato'];
        }
    }
    
    /**
     * Iniciar treinamento
     */
    public function startTraining($trainingId) {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            $this->pdo->beginTransaction();
            
            // Verificar se o treinamento está atribuído
            $stmt = $this->pdo->prepare("
                SELECT status FROM candidate_training_progress 
                WHERE candidate_id = ? AND training_id = ?
            ");
            $stmt->execute([$this->candidateId, $trainingId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$progress) {
                throw new Exception('Treinamento não atribuído');
            }
            
            if ($progress['status'] !== 'assigned') {
                throw new Exception('Treinamento já foi iniciado');
            }
            
            // Atualizar status para em progresso
            $stmt = $this->pdo->prepare("
                UPDATE candidate_training_progress SET 
                    status = 'in_progress',
                    started_at = NOW(),
                    progress_percentage = 0
                WHERE candidate_id = ? AND training_id = ?
            ");
            $stmt->execute([$this->candidateId, $trainingId]);
            
            // Log do evento
            $this->logTrainingEvent($trainingId, 'training_started');
            
            // Atualizar status do candidato se necessário
            $this->updateCandidateStatus();
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Treinamento iniciado com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Start training error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Obter conteúdo do treinamento
     */
    public function getTrainingContent($trainingId) {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            // Verificar acesso ao treinamento
            $stmt = $this->pdo->prepare("
                SELECT ctp.status, ctp.progress_percentage
                FROM candidate_training_progress ctp
                WHERE ctp.candidate_id = ? AND ctp.training_id = ?
            ");
            $stmt->execute([$this->candidateId, $trainingId]);
            $access = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$access) {
                return ['success' => false, 'message' => 'Acesso negado ao treinamento'];
            }
            
            // Obter dados do treinamento
            $stmt = $this->pdo->prepare("
                SELECT t.*, ctp.progress_percentage, ctp.status as training_status
                FROM trainings t
                JOIN candidate_training_progress ctp ON t.id = ctp.training_id
                WHERE t.id = ? AND ctp.candidate_id = ?
            ");
            $stmt->execute([$trainingId, $this->candidateId]);
            $training = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$training) {
                return ['success' => false, 'message' => 'Treinamento não encontrado'];
            }
            
            // Obter arquivos do treinamento
            $stmt = $this->pdo->prepare("
                SELECT * FROM training_files 
                WHERE training_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$trainingId]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'training' => $training,
                'files' => $files
            ];
            
        } catch (Exception $e) {
            error_log("Get training content error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao carregar conteúdo'];
        }
    }
    
    /**
     * Atualizar progresso do treinamento
     */
    public function updateTrainingProgress($trainingId, $progressPercentage) {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE candidate_training_progress SET 
                    progress_percentage = ?,
                    last_accessed_at = NOW()
                WHERE candidate_id = ? AND training_id = ?
            ");
            $stmt->execute([$progressPercentage, $this->candidateId, $trainingId]);
            
            // Log do progresso
            $this->logTrainingEvent($trainingId, 'progress_updated', [
                'progress_percentage' => $progressPercentage
            ]);
            
            return [
                'success' => true,
                'message' => 'Progresso atualizado'
            ];
            
        } catch (Exception $e) {
            error_log("Update training progress error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao atualizar progresso'];
        }
    }
    
    /**
     * Obter perguntas da avaliação
     */
    public function getEvaluationQuestions($trainingId) {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            // Verificar se pode fazer avaliação
            $stmt = $this->pdo->prepare("
                SELECT ctp.status, ctp.attempt_count, t.max_attempts
                FROM candidate_training_progress ctp
                JOIN trainings t ON ctp.training_id = t.id
                WHERE ctp.candidate_id = ? AND ctp.training_id = ?
            ");
            $stmt->execute([$this->candidateId, $trainingId]);
            $access = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$access) {
                return ['success' => false, 'message' => 'Acesso negado'];
            }
            
            if ($access['status'] !== 'in_progress' && $access['status'] !== 'failed') {
                return ['success' => false, 'message' => 'Treinamento deve estar em progresso'];
            }
            
            if ($access['attempt_count'] >= $access['max_attempts']) {
                return ['success' => false, 'message' => 'Máximo de tentativas atingido'];
            }
            
            // Obter perguntas (sem respostas corretas)
            $stmt = $this->pdo->prepare("
                SELECT id, question_text, question_type, points, answer_options
                FROM training_questions 
                WHERE training_id = ? 
                ORDER BY RAND()
            ");
            $stmt->execute([$trainingId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse answer options
            foreach ($questions as &$question) {
                if ($question['answer_options']) {
                    $question['answer_options'] = json_decode($question['answer_options'], true);
                }
            }
            
            return [
                'success' => true,
                'questions' => $questions,
                'attempt_number' => ($access['attempt_count'] ?? 0) + 1,
                'max_attempts' => $access['max_attempts']
            ];
            
        } catch (Exception $e) {
            error_log("Get evaluation questions error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao carregar avaliação'];
        }
    }
    
    /**
     * Submeter avaliação
     */
    public function submitEvaluation($trainingId, $answers) {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            $this->pdo->beginTransaction();
            
            // Obter perguntas com respostas corretas
            $stmt = $this->pdo->prepare("
                SELECT id, question_type, correct_answer, points
                FROM training_questions 
                WHERE training_id = ?
            ");
            $stmt->execute([$trainingId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular pontuação
            $totalPoints = 0;
            $earnedPoints = 0;
            $questionResults = [];
            
            foreach ($questions as $question) {
                $totalPoints += $question['points'];
                $questionId = $question['id'];
                $userAnswer = $answers[$questionId] ?? null;
                $isCorrect = false;
                
                if ($userAnswer !== null) {
                    if ($question['question_type'] === 'text') {
                        // Para perguntas de texto, comparação case-insensitive
                        $isCorrect = strcasecmp(trim($userAnswer), trim($question['correct_answer'])) === 0;
                    } else {
                        // Para múltipla escolha e verdadeiro/falso
                        $isCorrect = $userAnswer == $question['correct_answer'];
                    }
                }
                
                if ($isCorrect) {
                    $earnedPoints += $question['points'];
                }
                
                $questionResults[] = [
                    'question_id' => $questionId,
                    'user_answer' => $userAnswer,
                    'is_correct' => $isCorrect,
                    'points_earned' => $isCorrect ? $question['points'] : 0
                ];
            }
            
            $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
            
            // Obter dados do treinamento
            $stmt = $this->pdo->prepare("
                SELECT passing_score FROM trainings WHERE id = ?
            ");
            $stmt->execute([$trainingId]);
            $training = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $passed = $score >= $training['passing_score'];
            
            // Obter número da tentativa
            $stmt = $this->pdo->prepare("
                SELECT attempt_count FROM candidate_training_progress 
                WHERE candidate_id = ? AND training_id = ?
            ");
            $stmt->execute([$this->candidateId, $trainingId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            $attemptNumber = ($progress['attempt_count'] ?? 0) + 1;
            
            // Salvar resultado
            $stmt = $this->pdo->prepare("
                INSERT INTO candidate_evaluation_results (
                    candidate_id, training_id, score, total_points, earned_points,
                    passed, attempt_number, question_results, submitted_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->candidateId,
                $trainingId,
                $score,
                $totalPoints,
                $earnedPoints,
                $passed ? 1 : 0,
                $attemptNumber,
                json_encode($questionResults)
            ]);
            
            // Atualizar progresso
            if ($passed) {
                $stmt = $this->pdo->prepare("
                    UPDATE candidate_training_progress SET 
                        status = 'completed',
                        progress_percentage = 100,
                        completed_at = NOW(),
                        attempt_count = ?
                    WHERE candidate_id = ? AND training_id = ?
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE candidate_training_progress SET 
                        status = 'failed',
                        attempt_count = ?
                    WHERE candidate_id = ? AND training_id = ?
                ");
            }
            
            $stmt->execute([$attemptNumber, $this->candidateId, $trainingId]);
            
            // Log do evento
            $this->logTrainingEvent($trainingId, 'evaluation_submitted', [
                'score' => $score,
                'passed' => $passed,
                'attempt_number' => $attemptNumber
            ]);
            
            // Atualizar status do candidato
            $this->updateCandidateStatus();
            
            // Enviar email de resultado
            $this->sendEvaluationResultEmail($trainingId, $score, $passed, $attemptNumber);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'score' => $score,
                'passed' => $passed,
                'attempt_number' => $attemptNumber,
                'message' => $passed ? 'Parabéns! Você foi aprovado!' : 'Não foi desta vez. Tente novamente!'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Submit evaluation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao submeter avaliação'];
        }
    }
    
    /**
     * Reiniciar treinamento
     */
    public function retakeTraining($trainingId) {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            $this->pdo->beginTransaction();
            
            // Verificar se pode reiniciar
            $stmt = $this->pdo->prepare("
                SELECT ctp.status, ctp.attempt_count, t.max_attempts
                FROM candidate_training_progress ctp
                JOIN trainings t ON ctp.training_id = t.id
                WHERE ctp.candidate_id = ? AND ctp.training_id = ?
            ");
            $stmt->execute([$this->candidateId, $trainingId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$progress || $progress['status'] !== 'failed') {
                throw new Exception('Treinamento não pode ser reiniciado');
            }
            
            if ($progress['attempt_count'] >= $progress['max_attempts']) {
                throw new Exception('Máximo de tentativas atingido');
            }
            
            // Resetar progresso
            $stmt = $this->pdo->prepare("
                UPDATE candidate_training_progress SET 
                    status = 'in_progress',
                    progress_percentage = 0,
                    started_at = NOW(),
                    completed_at = NULL
                WHERE candidate_id = ? AND training_id = ?
            ");
            $stmt->execute([$this->candidateId, $trainingId]);
            
            // Log do evento
            $this->logTrainingEvent($trainingId, 'training_retaken');
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Treinamento reiniciado com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Retake training error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Gerar certificado
     */
    public function generateCertificate($trainingId) {
        try {
            if (!$this->candidateId) {
                return ['success' => false, 'message' => 'Candidate not found'];
            }
            
            // Verificar se treinamento foi concluído
            $stmt = $this->pdo->prepare("
                SELECT ctp.status, c.first_name, c.last_name, t.title, 
                       ctp.completed_at, cer.score
                FROM candidate_training_progress ctp
                JOIN candidates c ON ctp.candidate_id = c.id
                JOIN trainings t ON ctp.training_id = t.id
                LEFT JOIN candidate_evaluation_results cer ON (c.id = cer.candidate_id AND t.id = cer.training_id AND cer.passed = 1)
                WHERE ctp.candidate_id = ? AND ctp.training_id = ? AND ctp.status = 'completed'
                ORDER BY cer.submitted_at DESC
                LIMIT 1
            ");
            $stmt->execute([$this->candidateId, $trainingId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return ['success' => false, 'message' => 'Certificado não disponível'];
            }
            
            // Gerar PDF do certificado (implementar com biblioteca PDF)
            $pdfContent = $this->createCertificatePDF($data);
            
            return [
                'success' => true,
                'certificate_data' => $data,
                'pdf_content' => $pdfContent
            ];
            
        } catch (Exception $e) {
            error_log("Generate certificate error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao gerar certificado'];
        }
    }
    
    /**
     * Log de eventos de treinamento
     */
    private function logTrainingEvent($trainingId, $eventType, $metadata = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO candidate_training_log (
                    candidate_id, training_id, event_type, metadata, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->candidateId,
                $trainingId,
                $eventType,
                json_encode($metadata)
            ]);
        } catch (Exception $e) {
            error_log("Log training event error: " . $e->getMessage());
        }
    }
    
    /**
     * Atualizar status do candidato
     */
    private function updateCandidateStatus() {
        try {
            // Verificar se todos os treinamentos obrigatórios foram concluídos
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total_required,
                       COUNT(CASE WHEN ctp.status = 'completed' THEN 1 END) as completed_required
                FROM trainings t
                JOIN candidate_training_progress ctp ON t.id = ctp.training_id
                WHERE ctp.candidate_id = ? AND t.is_required = 1 AND t.status = 'active'
            ");
            $stmt->execute([$this->candidateId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $newStatus = 'in_training';
            if ($result['total_required'] > 0 && $result['completed_required'] >= $result['total_required']) {
                $newStatus = 'training_completed';
            }
            
            // Atualizar status do candidato
            $stmt = $this->pdo->prepare("
                UPDATE candidates SET status = ? WHERE id = ?
            ");
            $stmt->execute([$newStatus, $this->candidateId]);
            
            // Se completou todos os treinamentos obrigatórios, processar aprovação
            if ($newStatus === 'training_completed') {
                $this->processTrainingCompletion();
            }
            
        } catch (Exception $e) {
            error_log("Update candidate status error: " . $e->getMessage());
        }
    }
    
    /**
     * Processar conclusão de treinamentos
     */
    private function processTrainingCompletion() {
        // Implementar lógica de aprovação automática aqui
        // Por exemplo, criar usuário profissional automaticamente
        include_once __DIR__ . '/manage.php';
        
        $candidateManager = new CandidateManager();
        $candidateManager->approveCandidate($this->candidateId);
    }
    
    /**
     * Criar PDF do certificado
     */
    private function createCertificatePDF($data) {
        // Implementar geração de PDF
        // Por enquanto, retornar dados para gerar no frontend
        return base64_encode(json_encode($data));
    }
    
    /**
     * Enviar email com resultado da avaliação
     */
    private function sendEvaluationResultEmail($trainingId, $score, $passed, $attemptNumber) {
        // Implementar envio de email
        // Usar o sistema de email existente
    }
}

// Handle API requests
header('Content-Type: application/json');

$portal = new CandidateTrainingPortal();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle JSON input for POST requests
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    foreach ($input as $key => $value) {
        $_POST[$key] = $value;
    }
}

switch ($action) {
    case 'get_trainings':
        echo json_encode($portal->getTrainings());
        break;
        
    case 'get_candidate_data':
        echo json_encode($portal->getCandidateData());
        break;
        
    case 'start_training':
        $trainingId = $_POST['training_id'] ?? 0;
        echo json_encode($portal->startTraining($trainingId));
        break;
        
    case 'get_training_content':
        $trainingId = $_GET['training_id'] ?? 0;
        echo json_encode($portal->getTrainingContent($trainingId));
        break;
        
    case 'update_progress':
        $trainingId = $_POST['training_id'] ?? 0;
        $progress = $_POST['progress_percentage'] ?? 0;
        echo json_encode($portal->updateTrainingProgress($trainingId, $progress));
        break;
        
    case 'get_evaluation_questions':
        $trainingId = $_GET['training_id'] ?? 0;
        echo json_encode($portal->getEvaluationQuestions($trainingId));
        break;
        
    case 'submit_evaluation':
        $trainingId = $_POST['training_id'] ?? 0;
        $answers = $_POST['answers'] ?? [];
        echo json_encode($portal->submitEvaluation($trainingId, $answers));
        break;
        
    case 'retake_training':
        $trainingId = $_POST['training_id'] ?? 0;
        echo json_encode($portal->retakeTraining($trainingId));
        break;
        
    case 'download_certificate':
        $trainingId = $_GET['training_id'] ?? 0;
        $result = $portal->generateCertificate($trainingId);
        
        if ($result['success']) {
            // Return PDF for download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="certificado_treinamento_' . $trainingId . '.pdf"');
            echo base64_decode($result['pdf_content']);
        } else {
            echo json_encode($result);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
