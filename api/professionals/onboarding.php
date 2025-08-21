/**
 * Professional Onboarding Completion System
 * Blue Cleaning Services - Complete Professional Profile
 */

<?php
require_once __DIR__ . '/../../config/australian-database.php';
require_once __DIR__ . '/../../config/email-config.php';
require_once __DIR__ . '/../auth/session-manager.php';

class ProfessionalOnboarding {
    private $pdo;
    private $emailService;
    
    public function __construct() {
        $this->pdo = AustralianDatabase::getInstance()->getConnection();
        $this->emailService = new EmailService();
    }
    
    /**
     * Obter dados do profissional para onboarding
     */
    public function getOnboardingData($userId) {
        try {
            // Verificar se é profissional aprovado de candidato
            $stmt = $this->pdo->prepare("
                SELECT u.*, c.first_name, c.last_name, c.has_experience, 
                       c.experience_years, c.preferred_areas, c.availability
                FROM users u
                LEFT JOIN candidates c ON u.candidate_id = c.id
                WHERE u.id = ? AND u.user_type = 'professional' AND u.source = 'candidate_approved'
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuário não encontrado ou não autorizado'];
            }
            
            // Obter skills dos treinamentos completados
            $skills = $this->getAcquiredSkills($user['candidate_id']);
            
            // Obter documentos já enviados
            $documents = $this->getUploadedDocuments($user['candidate_id']);
            
            // Calcular progresso do perfil
            $profileCompletion = $this->calculateProfileCompletion($userId);
            
            // Obter campos obrigatórios ainda pendentes
            $requiredFields = $this->getRequiredFields($userId);
            
            return [
                'success' => true,
                'user' => $user,
                'skills' => $skills,
                'documents' => $documents,
                'profile_completion' => $profileCompletion,
                'required_fields' => $requiredFields,
                'next_steps' => $this->getNextSteps($userId, $profileCompletion)
            ];
            
        } catch (Exception $e) {
            error_log("Get onboarding data error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao obter dados'];
        }
    }
    
    /**
     * Atualizar informações básicas do perfil
     */
    public function updateBasicInfo($userId, $data) {
        try {
            $this->pdo->beginTransaction();
            
            // Atualizar tabela users
            $stmt = $this->pdo->prepare("
                UPDATE users SET 
                    name = ?, phone = ?, date_of_birth = ?, address = ?,
                    city = ?, state = ?, postal_code = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['phone'],
                $data['date_of_birth'],
                $data['address'],
                $data['city'],
                $data['state'],
                $data['postal_code'],
                $userId
            ]);
            
            // Atualizar tabela professionals
            $stmt = $this->pdo->prepare("
                UPDATE professionals SET 
                    name = ?, phone = ?, bio = ?, emergency_contact_name = ?,
                    emergency_contact_phone = ?, has_transport = ?
                WHERE user_id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['phone'],
                $data['bio'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
                isset($data['has_transport']) ? (bool)$data['has_transport'] : false,
                $userId
            ]);
            
            // Atualizar progresso
            $this->updateProfileCompletion($userId);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Informações básicas atualizadas com sucesso',
                'profile_completion' => $this->calculateProfileCompletion($userId)
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Update basic info error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao atualizar informações'];
        }
    }
    
    /**
     * Definir especialidades e serviços
     */
    public function setSpecialties($userId, $specialties, $serviceTypes) {
        try {
            $this->pdo->beginTransaction();
            
            // Limpar especialidades existentes
            $stmt = $this->pdo->prepare("DELETE FROM professional_specialties WHERE professional_id = ?");
            $stmt->execute([$userId]);
            
            // Inserir novas especialidades
            foreach ($specialties as $specialtyId) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO professional_specialties (professional_id, specialty_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $specialtyId]);
            }
            
            // Limpar tipos de serviço existentes
            $stmt = $this->pdo->prepare("DELETE FROM professional_service_types WHERE professional_id = ?");
            $stmt->execute([$userId]);
            
            // Inserir novos tipos de serviço
            foreach ($serviceTypes as $serviceType) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO professional_service_types (professional_id, service_type_id, rate_per_hour) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$userId, $serviceType['id'], $serviceType['rate'] ?? 0]);
            }
            
            // Atualizar progresso
            $this->updateProfileCompletion($userId);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Especialidades definidas com sucesso',
                'profile_completion' => $this->calculateProfileCompletion($userId)
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Set specialties error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao definir especialidades'];
        }
    }
    
    /**
     * Definir disponibilidade
     */
    public function setAvailability($userId, $availability) {
        try {
            // Validar formato da disponibilidade
            if (!$this->validateAvailability($availability)) {
                return ['success' => false, 'message' => 'Formato de disponibilidade inválido'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE professionals SET 
                    availability_schedule = ?,
                    max_distance_km = ?,
                    preferred_areas = ?
                WHERE user_id = ?
            ");
            
            $stmt->execute([
                json_encode($availability['schedule']),
                $availability['max_distance'] ?? 50,
                json_encode($availability['preferred_areas'] ?? []),
                $userId
            ]);
            
            // Atualizar progresso
            $this->updateProfileCompletion($userId);
            
            return [
                'success' => true,
                'message' => 'Disponibilidade definida com sucesso',
                'profile_completion' => $this->calculateProfileCompletion($userId)
            ];
            
        } catch (Exception $e) {
            error_log("Set availability error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao definir disponibilidade'];
        }
    }
    
    /**
     * Upload de foto de perfil
     */
    public function uploadProfilePhoto($userId, $photoFile) {
        try {
            // Validar arquivo
            if ($photoFile['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'Erro no upload da foto'];
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $photoFile['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ['success' => false, 'message' => 'Tipo de arquivo não permitido. Use JPG, PNG ou WebP'];
            }
            
            // Validar tamanho (máx 5MB)
            if ($photoFile['size'] > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'Arquivo muito grande. Máximo 5MB'];
            }
            
            // Criar diretório se não existir
            $uploadDir = __DIR__ . '/../../uploads/professionals/' . $userId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Gerar nome único
            $extension = pathinfo($photoFile['name'], PATHINFO_EXTENSION);
            $fileName = 'profile_' . time() . '.' . $extension;
            $filePath = $uploadDir . $fileName;
            
            // Redimensionar imagem
            if ($this->resizeImage($photoFile['tmp_name'], $filePath, 400, 400)) {
                // Atualizar banco de dados
                $stmt = $this->pdo->prepare("
                    UPDATE professionals SET 
                        avatar = ?, profile_photo = ? 
                    WHERE user_id = ?
                ");
                
                $photoUrl = '/uploads/professionals/' . $userId . '/' . $fileName;
                $stmt->execute([$photoUrl, $photoUrl, $userId]);
                
                // Atualizar progresso
                $this->updateProfileCompletion($userId);
                
                return [
                    'success' => true,
                    'photo_url' => $photoUrl,
                    'message' => 'Foto de perfil atualizada com sucesso',
                    'profile_completion' => $this->calculateProfileCompletion($userId)
                ];
            } else {
                return ['success' => false, 'message' => 'Erro ao processar imagem'];
            }
            
        } catch (Exception $e) {
            error_log("Upload profile photo error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao fazer upload da foto'];
        }
    }
    
    /**
     * Finalizar onboarding
     */
    public function completeOnboarding($userId) {
        try {
            $profileCompletion = $this->calculateProfileCompletion($userId);
            
            if ($profileCompletion < 100) {
                return [
                    'success' => false,
                    'message' => 'Perfil não está completo. Complete todos os campos obrigatórios.',
                    'completion_percentage' => $profileCompletion,
                    'missing_fields' => $this->getMissingFields($userId)
                ];
            }
            
            $this->pdo->beginTransaction();
            
            // Atualizar status do profissional
            $stmt = $this->pdo->prepare("
                UPDATE professionals SET 
                    status = 'active',
                    onboarding_completed_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            // Atualizar usuário
            $stmt = $this->pdo->prepare("
                UPDATE users SET 
                    status = 'active',
                    profile_completion_percentage = 100,
                    onboarding_completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
            // Log do evento
            $this->logOnboardingEvent($userId, 'onboarding_completed');
            
            // Enviar email de boas-vindas
            $this->sendWelcomeProfessionalEmail($userId);
            
            // Criar tarefas iniciais (opcional)
            $this->createInitialTasks($userId);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Onboarding concluído com sucesso! Bem-vindo à equipe Blue Cleaning!',
                'next_url' => '/professional/dashboard.php'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Complete onboarding error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao finalizar onboarding'];
        }
    }
    
    /**
     * Calcular progresso do perfil
     */
    private function calculateProfileCompletion($userId) {
        try {
            $weights = [
                'basic_info' => 25,      // Nome, telefone, endereço
                'photo' => 15,           // Foto de perfil
                'specialties' => 20,     // Especialidades selecionadas
                'availability' => 20,    // Horários disponíveis
                'documents' => 15,       // Documentos aprovados
                'emergency_contact' => 5 // Contato de emergência
            ];
            
            $completion = 0;
            
            // Verificar informações básicas
            $stmt = $this->pdo->prepare("
                SELECT u.name, u.phone, u.address, u.city, u.state, u.postal_code,
                       p.emergency_contact_name, p.emergency_contact_phone
                FROM users u
                JOIN professionals p ON u.id = p.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $basicInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($basicInfo && !empty($basicInfo['name']) && !empty($basicInfo['phone']) && 
                !empty($basicInfo['address']) && !empty($basicInfo['city'])) {
                $completion += $weights['basic_info'];
            }
            
            // Verificar contato de emergência
            if ($basicInfo && !empty($basicInfo['emergency_contact_name']) && 
                !empty($basicInfo['emergency_contact_phone'])) {
                $completion += $weights['emergency_contact'];
            }
            
            // Verificar foto de perfil
            $stmt = $this->pdo->prepare("SELECT avatar FROM professionals WHERE user_id = ? AND avatar IS NOT NULL");
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                $completion += $weights['photo'];
            }
            
            // Verificar especialidades
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM professional_specialties WHERE professional_id = ?");
            $stmt->execute([$userId]);
            $specialties = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($specialties['count'] > 0) {
                $completion += $weights['specialties'];
            }
            
            // Verificar disponibilidade
            $stmt = $this->pdo->prepare("SELECT availability_schedule FROM professionals WHERE user_id = ? AND availability_schedule IS NOT NULL");
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                $completion += $weights['availability'];
            }
            
            // Verificar documentos aprovados
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as approved_docs
                FROM candidate_documents cd
                JOIN users u ON u.candidate_id = cd.candidate_id
                WHERE u.id = ? AND cd.verification_status = 'approved'
                AND cd.document_type IN ('rg', 'cpf', 'proof_address', 'photo')
            ");
            $stmt->execute([$userId]);
            $docs = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($docs['approved_docs'] >= 4) { // Todos os documentos obrigatórios
                $completion += $weights['documents'];
            } elseif ($docs['approved_docs'] > 0) {
                $completion += ($weights['documents'] * $docs['approved_docs'] / 4);
            }
            
            return round($completion, 2);
            
        } catch (Exception $e) {
            error_log("Calculate profile completion error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Atualizar progresso do perfil
     */
    private function updateProfileCompletion($userId) {
        $completion = $this->calculateProfileCompletion($userId);
        
        $stmt = $this->pdo->prepare("
            UPDATE users SET profile_completion_percentage = ? WHERE id = ?
        ");
        $stmt->execute([$completion, $userId]);
        
        return $completion;
    }
    
    /**
     * Obter skills adquiridas dos treinamentos
     */
    private function getAcquiredSkills($candidateId) {
        $stmt = $this->pdo->prepare("
            SELECT t.title, t.skills_acquired, cer.score, cer.passed
            FROM candidate_evaluation_results cer
            JOIN trainings t ON cer.training_id = t.id
            WHERE cer.candidate_id = ? AND cer.passed = 1
            GROUP BY t.id
            HAVING cer.score = MAX(cer.score)
        ");
        $stmt->execute([$candidateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Redimensionar imagem
     */
    private function resizeImage($sourcePath, $destPath, $maxWidth, $maxHeight) {
        try {
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) return false;
            
            $sourceWidth = $imageInfo[0];
            $sourceHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Calcular novas dimensões mantendo proporção
            $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
            $newWidth = $sourceWidth * $ratio;
            $newHeight = $sourceHeight * $ratio;
            
            // Criar imagem fonte
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/webp':
                    $sourceImage = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return false;
            }
            
            if (!$sourceImage) return false;
            
            // Criar nova imagem
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Manter transparência para PNG
            if ($mimeType === 'image/png') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            // Redimensionar
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, 
                              $newWidth, $newHeight, $sourceWidth, $sourceHeight);
            
            // Salvar
            $result = false;
            switch ($mimeType) {
                case 'image/jpeg':
                    $result = imagejpeg($newImage, $destPath, 90);
                    break;
                case 'image/png':
                    $result = imagepng($newImage, $destPath, 8);
                    break;
                case 'image/webp':
                    $result = imagewebp($newImage, $destPath, 90);
                    break;
            }
            
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Resize image error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log de eventos do onboarding
     */
    private function logOnboardingEvent($userId, $eventType, $metadata = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO professional_activity_log (
                professional_id, activity_type, description, metadata, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $eventType,
            'Professional onboarding event',
            json_encode($metadata)
        ]);
    }
    
    // Outros métodos auxiliares...
}

// API Endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    session_start();
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $onboarding = new ProfessionalOnboarding();
    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['user_id'];
    
    switch ($action) {
        case 'get_data':
            echo json_encode($onboarding->getOnboardingData($userId));
            break;
            
        case 'update_basic_info':
            echo json_encode($onboarding->updateBasicInfo($userId, $_POST));
            break;
            
        case 'set_specialties':
            $specialties = json_decode($_POST['specialties'] ?? '[]', true);
            $serviceTypes = json_decode($_POST['service_types'] ?? '[]', true);
            echo json_encode($onboarding->setSpecialties($userId, $specialties, $serviceTypes));
            break;
            
        case 'set_availability':
            $availability = json_decode($_POST['availability'] ?? '{}', true);
            echo json_encode($onboarding->setAvailability($userId, $availability));
            break;
            
        case 'upload_photo':
            echo json_encode($onboarding->uploadProfilePhoto($userId, $_FILES['photo'] ?? null));
            break;
            
        case 'complete_onboarding':
            echo json_encode($onboarding->completeOnboarding($userId));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    
    session_start();
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $onboarding = new ProfessionalOnboarding();
    echo json_encode($onboarding->getOnboardingData($_SESSION['user_id']));
}
?>
