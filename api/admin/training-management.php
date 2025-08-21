<?php
/**
 * Training Management API
 * Blue Cleaning Services - Admin Training System
 * 
 * Australian Standardized Version 2.0
 * Compliant with Australian business practices and data formats
 * 
 * @author Blue Cleaning Development Team
 * @version 2.0.0
 * @created 07/08/2025
 */

require_once __DIR__ . '/../../config/australian-database.php';
require_once __DIR__ . '/../../config/australian-environment.php';
require_once __DIR__ . '/../../utils/australian-validators.php';

class AustralianTrainingAdminManager {
    private $pdo;
    private $config;
    
    public function __construct() {
        $this->pdo = AustralianDatabase::getInstance()->getConnection();
        $this->config = AustralianEnvironmentConfig::getRegionalSettings();
    }
    
    /**
     * List all trainings with Australian formatting
     */
    public function listTrainings($filters = []) {
        try {
            $sql = "
                SELECT t.*, 
                       COUNT(DISTINCT ctp.candidate_id) as enrolled_count,
                       COUNT(DISTINCT CASE WHEN ctp.status = 'completed' THEN ctp.candidate_id END) as completed_count,
                       COUNT(DISTINCT cer.candidate_id) as evaluated_count,
                       AVG(cer.score) as avg_score,
                       DATE_FORMAT(t.created_at, '%d/%m/%Y %H:%i') as created_formatted,
                       DATE_FORMAT(t.updated_at, '%d/%m/%Y %H:%i') as updated_formatted
                FROM trainings t
                LEFT JOIN candidate_training_progress ctp ON t.id = ctp.training_id
                LEFT JOIN candidate_evaluation_results cer ON t.id = cer.training_id AND cer.passed = 1
                WHERE 1=1
            ";
            
            $params = [];
            
            // Apply filters with Australian date handling
            if (!empty($filters['status'])) {
                $sql .= " AND t.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['type'])) {
                $sql .= " AND t.training_type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Australian date range filter
            if (!empty($filters['date_from'])) {
                $sql .= " AND t.created_at >= STR_TO_DATE(?, '%d/%m/%Y')";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND t.created_at <= STR_TO_DATE(?, '%d/%m/%Y 23:59:59')";
                $params[] = $filters['date_to'];
            }
            
            $sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $trainings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format data for Australian display
            foreach ($trainings as &$training) {
                // Format duration in Australian style
                if ($training['estimated_duration']) {
                    $hours = floor($training['estimated_duration'] / 60);
                    $minutes = $training['estimated_duration'] % 60;
                    $training['duration_formatted'] = $hours > 0 ? 
                        "{$hours} hour" . ($hours > 1 ? 's' : '') . ($minutes > 0 ? " {$minutes} min" : '') :
                        "{$minutes} minute" . ($minutes > 1 ? 's' : '');
                }
                
                // Format scores as percentages
                if ($training['avg_score']) {
                    $training['avg_score_formatted'] = number_format($training['avg_score'], 1) . '%';
                }
            }
            
            return [
                'success' => true,
                'trainings' => $trainings,
                'timezone' => $this->config['timezone'],
                'date_format' => $this->config['date_format']
            ];
            
        } catch (Exception $e) {
            error_log("Australian Training List Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error loading trainings'];
        }
    }
    
    /**
     * List training candidates with Australian formatting
     */
    public function listCandidates($filters = []) {
        try {
            $sql = "
                SELECT c.id, c.first_name, c.last_name, c.email, c.mobile,
                       c.suburb, c.state, c.postcode,
                       t.id as training_id, t.title as training_title, t.training_type,
                       ctp.status, ctp.progress_percentage, 
                       DATE_FORMAT(ctp.started_at, '%d/%m/%Y %H:%i') as started_formatted,
                       DATE_FORMAT(ctp.completed_at, '%d/%m/%Y %H:%i') as completed_formatted,
                       cer.score, cer.attempt_number, cer.passed,
                       DATE_FORMAT(cer.submitted_at, '%d/%m/%Y %H:%i') as evaluation_date_formatted
                FROM candidates c
                JOIN candidate_training_progress ctp ON c.id = ctp.candidate_id
                JOIN trainings t ON ctp.training_id = t.id
                LEFT JOIN candidate_evaluation_results cer ON (c.id = cer.candidate_id AND t.id = cer.training_id)
                WHERE c.status IN ('pending', 'in_training', 'training_completed')
            ";
            
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND ctp.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['training_id'])) {
                $sql .= " AND t.id = ?";
                $params[] = $filters['training_id'];
            }
            
            if (!empty($filters['state'])) {
                $sql .= " AND c.state = ?";
                $params[] = $filters['state'];
            }
            
            $sql .= " ORDER BY ctp.started_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format Australian-specific data
            foreach ($candidates as &$candidate) {
                // Format mobile number
                if (!empty($candidate['mobile'])) {
                    $candidate['mobile_formatted'] = AustralianValidators::formatMobile($candidate['mobile']);
                }
                
                // Format location
                if (!empty($candidate['suburb']) && !empty($candidate['state']) && !empty($candidate['postcode'])) {
                    $candidate['location_formatted'] = "{$candidate['suburb']}, {$candidate['state']} {$candidate['postcode']}";
                }
                
                // Format score
                if ($candidate['score']) {
                    $candidate['score_formatted'] = number_format($candidate['score'], 1) . '%';
                }
            }
            
            return [
                'success' => true,
                'candidates' => $candidates,
                'timezone' => $this->config['timezone']
            ];
            
        } catch (Exception $e) {
            error_log("Australian Candidates List Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error loading candidates'];
        }
    }
    
    /**
     * Save training (create/edit) with Australian standards
     */
    public function saveTraining($data, $files = []) {
        try {
            $this->pdo->beginTransaction();
            
            $trainingId = $data['training_id'] ?? null;
            
            if ($trainingId) {
                // Update existing training
                $stmt = $this->pdo->prepare("
                    UPDATE trainings SET 
                        title = ?, description = ?, training_type = ?, 
                        estimated_duration = ?, passing_score = ?, max_attempts = ?,
                        skills_acquired = ?, is_required = ?, status = ?,
                        video_url = ?, content_text = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $data['title'],
                    $data['description'],
                    $data['training_type'],
                    $data['estimated_duration'],
                    $data['passing_score'],
                    $data['max_attempts'],
                    $data['skills_acquired'],
                    isset($data['is_required']) ? 1 : 0,
                    $data['status'],
                    $data['video_url'] ?? null,
                    $data['content_text'] ?? null,
                    $trainingId
                ]);
            } else {
                // Create new training with training_code
                $trainingCode = 'TRN_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -6));
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO trainings (
                        training_code, title, description, training_type, estimated_duration, 
                        passing_score, max_attempts, skills_acquired, is_required, 
                        status, video_url, content_text, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $trainingCode,
                    $data['title'],
                    $data['description'],
                    $data['training_type'],
                    $data['estimated_duration'],
                    $data['passing_score'],
                    $data['max_attempts'],
                    $data['skills_acquired'],
                    isset($data['is_required']) ? 1 : 0,
                    $data['status'],
                    $data['video_url'] ?? null,
                    $data['content_text'] ?? null
                ]);
                
                $trainingId = $this->pdo->lastInsertId();
            }
            
            // Handle file uploads
            if (!empty($files)) {
                $this->saveTrainingFiles($trainingId, $files);
            }
            
            // Handle questions
            if (!empty($data['questions'])) {
                $this->saveTrainingQuestions($trainingId, $data['questions']);
            }
            
            // If this is a required onboarding training, assign to all pending candidates
            if ($data['status'] === 'active' && 
                $data['training_type'] === 'onboarding' && 
                isset($data['is_required'])) {
                $this->assignToOnboardingCandidates($trainingId);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'training_id' => $trainingId,
                'message' => 'Training saved successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Save training error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error saving training'];
        }
    }
    
    /**
     * Save training files with Australian compliance
     */
    private function saveTrainingFiles($trainingId, $files) {
        $uploadDir = __DIR__ . '/../../uploads/trainings/' . $trainingId . '/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) continue;
            
            // Generate unique filename with Australian timestamp
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'training_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Determine file type
                $fileType = $this->determineFileType($file['type'], $extension);
                
                // Save file info to database
                $stmt = $this->pdo->prepare("
                    INSERT INTO training_files (
                        training_id, file_type, original_name, file_name, file_path, 
                        file_size, mime_type, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $trainingId,
                    $fileType,
                    $file['name'],
                    $filename,
                    '/uploads/trainings/' . $trainingId . '/' . $filename,
                    $file['size'],
                    $file['type']
                ]);
            }
        }
    }
    
    /**
     * Determine file type for Australian training system
     */
    private function determineFileType($mimeType, $extension) {
        $videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $documentTypes = ['pdf', 'doc', 'docx', 'txt', 'rtf'];
        $audioTypes = ['mp3', 'wav', 'aac', 'ogg'];
        
        $ext = strtolower($extension);
        
        if (in_array($ext, $videoTypes)) return 'video';
        if (in_array($ext, $imageTypes)) return 'image';
        if (in_array($ext, $documentTypes)) return 'document';
        if (in_array($ext, $audioTypes)) return 'audio';
        
        return 'other';
    }
    
    /**
     * Save training questions with Australian formatting
     */
    private function saveTrainingQuestions($trainingId, $questions) {
        // Remove existing questions
        $stmt = $this->pdo->prepare("DELETE FROM training_questions WHERE training_id = ?");
        $stmt->execute([$trainingId]);
        
        foreach ($questions as $index => $questionData) {
            if (empty($questionData['question'])) continue;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO training_questions (
                    training_id, question_text, question_type, points, 
                    correct_answer, answer_options, explanation, display_order, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Prepare answer options
            $options = [];
            $correctAnswer = null;
            
            if ($questionData['type'] === 'multiple_choice') {
                $options = array_filter($questionData['options'] ?? []);
                $correctAnswer = $questionData['correct'] ?? 0;
            } elseif ($questionData['type'] === 'true_false') {
                $options = ['True', 'False']; // Australian English
                $correctAnswer = $questionData['correct'] ?? 0;
            } else {
                $correctAnswer = $questionData['correct_text'] ?? '';
            }
            
            $stmt->execute([
                $trainingId,
                $questionData['question'],
                $questionData['type'],
                $questionData['points'] ?? 1,
                $correctAnswer,
                json_encode($options, JSON_UNESCAPED_UNICODE),
                $questionData['explanation'] ?? null,
                $index + 1
            ]);
        }
    }
    
    /**
     * Atribuir treinamento obrigatório a candidatos pendentes
     */
    private function assignToOnboardingCandidates($trainingId) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM candidates 
            WHERE status IN ('pending', 'in_training')
            AND id NOT IN (
                SELECT candidate_id FROM candidate_training_progress 
                WHERE training_id = ?
            )
        ");
        $stmt->execute([$trainingId]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($candidates as $candidate) {
            $stmt = $this->pdo->prepare("
                INSERT INTO candidate_training_progress (
                    candidate_id, training_id, status, assigned_at
                ) VALUES (?, ?, 'assigned', NOW())
            ");
            $stmt->execute([$candidate['id'], $trainingId]);
        }
    }
    
    /**
     * Obter detalhes de um treinamento
     */
    public function getTrainingDetails($trainingId) {
        try {
            // Get training basic info
            $stmt = $this->pdo->prepare("
                SELECT t.*, 
                       COUNT(DISTINCT ctp.candidate_id) as enrolled_count,
                       COUNT(DISTINCT CASE WHEN ctp.status = 'completed' THEN ctp.candidate_id END) as completed_count
                FROM trainings t
                LEFT JOIN candidate_training_progress ctp ON t.id = ctp.training_id
                WHERE t.id = ?
                GROUP BY t.id
            ");
            $stmt->execute([$trainingId]);
            $training = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$training) {
                return ['success' => false, 'message' => 'Treinamento não encontrado'];
            }
            
            // Get questions
            $stmt = $this->pdo->prepare("
                SELECT * FROM training_questions 
                WHERE training_id = ? 
                ORDER BY id ASC
            ");
            $stmt->execute([$trainingId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get files
            $stmt = $this->pdo->prepare("
                SELECT * FROM training_files 
                WHERE training_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$trainingId]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get candidate progress
            $stmt = $this->pdo->prepare("
                SELECT c.first_name, c.last_name, c.email,
                       ctp.status, ctp.progress_percentage, ctp.started_at, ctp.completed_at,
                       cer.score, cer.passed, cer.attempt_number
                FROM candidate_training_progress ctp
                JOIN candidates c ON ctp.candidate_id = c.id
                LEFT JOIN candidate_evaluation_results cer ON (c.id = cer.candidate_id AND ctp.training_id = cer.training_id)
                WHERE ctp.training_id = ?
                ORDER BY ctp.assigned_at DESC
            ");
            $stmt->execute([$trainingId]);
            $candidateProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'training' => $training,
                'questions' => $questions,
                'files' => $files,
                'candidate_progress' => $candidateProgress
            ];
            
        } catch (Exception $e) {
            error_log("Get training details error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao carregar detalhes'];
        }
    }
    
    /**
     * Arquivar treinamento
     */
    public function archiveTraining($trainingId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE trainings SET status = 'archived', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$trainingId]);
            
            return [
                'success' => true,
                'message' => 'Treinamento arquivado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log("Archive training error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao arquivar treinamento'];
        }
    }
    
    /**
     * Ativar treinamento
     */
    public function activateTraining($trainingId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE trainings SET status = 'active', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$trainingId]);
            
            return [
                'success' => true,
                'message' => 'Treinamento ativado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log("Activate training error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao ativar treinamento'];
        }
    }
    
    /**
     * Excluir treinamento
     */
    public function deleteTraining($trainingId) {
        try {
            // Check if training has enrolled candidates
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count FROM candidate_training_progress 
                WHERE training_id = ? AND status != 'assigned'
            ");
            $stmt->execute([$trainingId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                return [
                    'success' => false,
                    'message' => 'Não é possível excluir treinamento com candidatos em progresso'
                ];
            }
            
            $this->pdo->beginTransaction();
            
            // Delete related records
            $stmt = $this->pdo->prepare("DELETE FROM training_questions WHERE training_id = ?");
            $stmt->execute([$trainingId]);
            
            $stmt = $this->pdo->prepare("DELETE FROM training_files WHERE training_id = ?");
            $stmt->execute([$trainingId]);
            
            $stmt = $this->pdo->prepare("DELETE FROM candidate_training_progress WHERE training_id = ?");
            $stmt->execute([$trainingId]);
            
            // Delete training
            $stmt = $this->pdo->prepare("DELETE FROM trainings WHERE id = ?");
            $stmt->execute([$trainingId]);
            
            $this->pdo->commit();
            
            // Delete uploaded files
            $uploadDir = __DIR__ . '/../../uploads/trainings/' . $trainingId . '/';
            if (is_dir($uploadDir)) {
                $this->deleteDirectory($uploadDir);
            }
            
            return [
                'success' => true,
                'message' => 'Treinamento excluído com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Delete training error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao excluir treinamento'];
        }
    }
    
    /**
     * Duplicar treinamento
     */
    public function duplicateTraining($trainingId) {
        try {
            $this->pdo->beginTransaction();
            
            // Get original training
            $stmt = $this->pdo->prepare("SELECT * FROM trainings WHERE id = ?");
            $stmt->execute([$trainingId]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$original) {
                throw new Exception('Treinamento não encontrado');
            }
            
            // Create duplicate
            $stmt = $this->pdo->prepare("
                INSERT INTO trainings (
                    title, description, training_type, estimated_duration, 
                    passing_score, max_attempts, skills_acquired, is_required, 
                    status, video_url, content_text, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $original['title'] . ' (Cópia)',
                $original['description'],
                $original['training_type'],
                $original['estimated_duration'],
                $original['passing_score'],
                $original['max_attempts'],
                $original['skills_acquired'],
                $original['is_required'],
                $original['video_url'],
                $original['content_text']
            ]);
            
            $newTrainingId = $this->pdo->lastInsertId();
            
            // Duplicate questions
            $stmt = $this->pdo->prepare("
                INSERT INTO training_questions (
                    training_id, question_text, question_type, points, 
                    correct_answer, answer_options, explanation, created_at
                )
                SELECT ?, question_text, question_type, points, 
                       correct_answer, answer_options, explanation, NOW()
                FROM training_questions WHERE training_id = ?
            ");
            $stmt->execute([$newTrainingId, $trainingId]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'training_id' => $newTrainingId,
                'message' => 'Treinamento duplicado com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Duplicate training error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao duplicar treinamento'];
        }
    }
    
    /**
     * Ver progresso de um candidato específico
     */
    public function viewCandidateProgress($candidateId, $trainingId = null) {
        try {
            $sql = "
                SELECT c.first_name, c.last_name, c.email,
                       t.title as training_title, t.training_type,
                       ctp.status, ctp.progress_percentage, ctp.started_at, ctp.completed_at,
                       cer.score, cer.attempt_number, cer.passed, cer.submitted_at as evaluation_date
                FROM candidates c
                JOIN candidate_training_progress ctp ON c.id = ctp.candidate_id
                JOIN trainings t ON ctp.training_id = t.id
                LEFT JOIN candidate_evaluation_results cer ON (c.id = cer.candidate_id AND t.id = cer.training_id)
                WHERE c.id = ?
            ";
            
            $params = [$candidateId];
            
            if ($trainingId) {
                $sql .= " AND t.id = ?";
                $params[] = $trainingId;
            }
            
            $sql .= " ORDER BY ctp.started_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'progress' => $progress
            ];
            
        } catch (Exception $e) {
            error_log("View candidate progress error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao carregar progresso'];
        }
    }
    
    /**
     * Excluir diretório recursivamente
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return false;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
}

// Handle API requests
header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Check authentication with Australian timezone
session_start();
if (empty($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode([
        'success' => false, 
        'message' => 'Access denied',
        'timestamp' => AustralianEnvironmentConfig::formatDateTime(new DateTime())
    ]);
    exit;
}

$manager = new AustralianTrainingAdminManager();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list_trainings':
        $filters = [
            'status' => $_GET['status'] ?? '',
            'type' => $_GET['type'] ?? '',
            'search' => $_GET['search'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        echo json_encode($manager->listTrainings($filters));
        break;
        
    case 'list_candidates':
        $filters = [
            'status' => $_GET['status'] ?? '',
            'training_id' => $_GET['training_id'] ?? '',
            'state' => $_GET['state'] ?? ''
        ];
        echo json_encode($manager->listCandidates($filters));
        break;
        
    case 'save_training':
        $files = $_FILES['files'] ?? [];
        echo json_encode($manager->saveTraining($_POST, $files));
        break;
        
    case 'get_training_details':
        $trainingId = $_GET['training_id'] ?? 0;
        echo json_encode($manager->getTrainingDetails($trainingId));
        break;
        
    case 'archive_training':
        $trainingId = $_POST['training_id'] ?? 0;
        echo json_encode($manager->archiveTraining($trainingId));
        break;
        
    case 'activate_training':
        $trainingId = $_POST['training_id'] ?? 0;
        echo json_encode($manager->activateTraining($trainingId));
        break;
        
    case 'delete_training':
        $trainingId = $_POST['training_id'] ?? 0;
        echo json_encode($manager->deleteTraining($trainingId));
        break;
        
    case 'duplicate_training':
        $trainingId = $_POST['training_id'] ?? 0;
        echo json_encode($manager->duplicateTraining($trainingId));
        break;
        
    case 'view_candidate_progress':
        $candidateId = $_GET['candidate_id'] ?? 0;
        $trainingId = $_GET['training_id'] ?? null;
        echo json_encode($manager->viewCandidateProgress($candidateId, $trainingId));
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
