<?php
/**
 * =========================================================
 * PAINEL DO PROFISSIONAL - GESTÃO DE DISPONIBILIDADE
 * =========================================================
 * 
 * @file professional/dashboard/availability.php
 * @description Painel para o profissional gerenciar suas próprias disponibilidades
 * @version 1.0
 * @date 2025-08-10
 */

require_once __DIR__ . '/../../config.php';

session_start();

// Simulação de profissional logado (implementar autenticação depois)
$professional_id = 1;
$professional_name = "João Silva"; // Buscar do banco depois
$professional_email = "joao@bluecleaning.com.au"; // Buscar do banco depois

// Processar ações AJAX
if ($_POST && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        if ($_POST['action'] === 'add_availability') {
            $date = $_POST['date'] ?? '';
            $start_time = $_POST['start_time'] ?? '';
            $end_time = $_POST['end_time'] ?? '';
            $service_id = $_POST['service_id'] ?? 1;
            $capacity = $_POST['capacity'] ?? 1;
            
            if (empty($date) || empty($start_time) || empty($end_time)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            // Validar data (não pode ser no passado)
            if (strtotime($date) < strtotime(date('Y-m-d'))) {
                throw new Exception('Não é possível criar disponibilidade para datas passadas');
            }
            
            // Validar horários
            if (strtotime($end_time) <= strtotime($start_time)) {
                throw new Exception('O horário de fim deve ser posterior ao horário de início');
            }
            
            // Verificar conflitos
            $check_stmt = $pdo->prepare("
                SELECT id FROM professional_availability 
                WHERE professional_id = ? AND date = ? AND (
                    (start_time <= ? AND end_time > ?) OR
                    (start_time < ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )
            ");
            $check_stmt->execute([
                $professional_id, $date, 
                $start_time, $start_time,
                $end_time, $end_time,
                $start_time, $end_time
            ]);
            
            if ($check_stmt->fetch()) {
                throw new Exception('Já existe disponibilidade conflitante para este horário');
            }
            
            // Inserir nova disponibilidade
            $stmt = $pdo->prepare("
                INSERT INTO professional_availability 
                (professional_id, service_id, date, start_time, end_time, capacity, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$professional_id, $service_id, $date, $start_time, $end_time, $capacity]);
            
            $response = ['success' => true, 'message' => 'Disponibilidade adicionada com sucesso!'];
            
        } elseif ($_POST['action'] === 'remove_availability') {
            $availability_id = $_POST['availability_id'] ?? '';
            
            if (empty($availability_id)) {
                throw new Exception('ID da disponibilidade é obrigatório');
            }
            
            // Verificar se existem agendamentos para esta disponibilidade
            $bookings_check = $pdo->prepare("
                SELECT COUNT(*) as count FROM bookings 
                WHERE professional_id = ? AND DATE(execution_date) = (
                    SELECT date FROM professional_availability WHERE id = ?
                ) AND status NOT IN ('cancelled', 'completed')
            ");
            $bookings_check->execute([$professional_id, $availability_id]);
            $booking_count = $bookings_check->fetch()['count'];
            
            if ($booking_count > 0) {
                throw new Exception('Não é possível remover disponibilidade que já possui agendamentos');
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM professional_availability 
                WHERE id = ? AND professional_id = ?
            ");
            
            $stmt->execute([$availability_id, $professional_id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Disponibilidade não encontrada');
            }
            
            $response = ['success' => true, 'message' => 'Disponibilidade removida com sucesso!'];
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Buscar estatísticas do profissional
$stats = ['total_slots' => 0, 'upcoming_slots' => 0, 'booked_slots' => 0, 'available_slots' => 0];

try {
    // Total de slots criados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM professional_availability 
        WHERE professional_id = ? AND date >= CURDATE()
    ");
    $stmt->execute([$professional_id]);
    $stats['total_slots'] = $stmt->fetch()['count'];
    
    // Slots nos próximos 7 dias
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM professional_availability 
        WHERE professional_id = ? AND date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$professional_id]);
    $stats['upcoming_slots'] = $stmt->fetch()['count'];
    
    // Slots com agendamentos
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT pa.id) as count 
        FROM professional_availability pa
        LEFT JOIN bookings b ON b.professional_id = pa.professional_id 
            AND DATE(b.execution_date) = pa.date
            AND b.status NOT IN ('cancelled')
        WHERE pa.professional_id = ? AND pa.date >= CURDATE()
        AND b.id IS NOT NULL
    ");
    $stmt->execute([$professional_id]);
    $stats['booked_slots'] = $stmt->fetch()['count'];
    
    // Slots disponíveis
    $stats['available_slots'] = $stats['total_slots'] - $stats['booked_slots'];
    
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
}

// Buscar disponibilidades do profissional
$availabilities = [];
try {
    $stmt = $pdo->prepare("
        SELECT pa.*, ps.name as service_name,
               COUNT(b.id) as bookings_count
        FROM professional_availability pa
        LEFT JOIN professional_services ps ON pa.service_id = ps.id
        LEFT JOIN bookings b ON b.professional_id = pa.professional_id 
            AND DATE(b.execution_date) = pa.date
            AND b.status NOT IN ('cancelled')
        WHERE pa.professional_id = ?
        AND pa.date >= CURDATE()
        GROUP BY pa.id
        ORDER BY pa.date ASC, pa.start_time ASC
        LIMIT 50
    ");
    
    $stmt->execute([$professional_id]);
    $availabilities = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erro ao buscar disponibilidades: " . $e->getMessage());
}

// Buscar serviços disponíveis
$services = [];
try {
    $stmt = $pdo->query("SELECT * FROM professional_services ORDER BY name");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erro ao buscar serviços: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Agenda - Blue Cleaning</title>
    <link rel="stylesheet" href="../assets/css/professional-dashboard.css">
</head>
<body>
    <div class="professional-container">
        <!-- Cabeçalho -->
        <div class="header">
            <div>
                <h1>👋 Olá, <?= htmlspecialchars($professional_name) ?>!</h1>
                <div class="professional-info">
                    <span>📧 <?= htmlspecialchars($professional_email) ?></span> | 
                    <span>🕐 <?= date('d/m/Y H:i') ?></span>
                </div>
            </div>
            <a href="../../booking3.php" class="btn btn-primary">
                🏠 Voltar ao Site
            </a>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_slots'] ?></div>
                <div class="stat-label">Total Slots</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['upcoming_slots'] ?></div>
                <div class="stat-label">Próximos 7 dias</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['booked_slots'] ?></div>
                <div class="stat-label">Com Agendamentos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['available_slots'] ?></div>
                <div class="stat-label">Disponíveis</div>
            </div>
        </div>
        
        <!-- Formulário para Adicionar Disponibilidade -->
        <div class="section">
            <h2>🆕 Criar Nova Disponibilidade</h2>
            <form id="availability-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="date">📅 Data</label>
                        <input type="date" id="date" name="date" required 
                               min="<?= date('Y-m-d') ?>" 
                               max="<?= date('Y-m-d', strtotime('+3 months')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">⏰ Início</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">⏰ Fim</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="service_id">🔧 Serviço</label>
                        <select id="service_id" name="service_id" required>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="capacity">👥 Capacidade</label>
                        <input type="number" id="capacity" name="capacity" value="1" min="1" max="5" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    ➕ Criar Disponibilidade
                </button>
            </form>
        </div>
        
        <!-- Lista de Disponibilidades -->
        <div class="section">
            <h2>📅 Suas Disponibilidades</h2>
            
            <div id="alert-container"></div>
            
            <?php if (empty($availabilities)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>Nenhuma disponibilidade encontrada</h3>
                    <p>Crie sua primeira disponibilidade usando o formulário acima.</p>
                </div>
            <?php else: ?>
                <div class="availability-grid">
                    <?php foreach ($availabilities as $availability): ?>
                        <div class="availability-card">
                            <div class="card-header">
                                <div class="card-date">
                                    📅 <?= date('d/m/Y (D)', strtotime($availability['date'])) ?>
                                </div>
                                <button class="btn btn-danger" onclick="removeAvailability(<?= $availability['id'] ?>)">
                                    🗑️
                                </button>
                            </div>
                            
                            <div class="card-time">
                                ⏰ <?= date('H:i', strtotime($availability['start_time'])) ?> - <?= date('H:i', strtotime($availability['end_time'])) ?>
                            </div>
                            
                            <div class="card-service">
                                🔧 <?= htmlspecialchars($availability['service_name'] ?? 'Serviço não encontrado') ?>
                            </div>
                            
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <div class="card-capacity">
                                    👥 Cap: <?= $availability['capacity'] ?>
                                </div>
                                
                                <?php if ($availability['bookings_count'] > 0): ?>
                                    <div style="background: rgba(255, 193, 7, 0.2); color: #ffc107; padding: 4px 10px; border-radius: 15px; font-size: 0.75rem;">
                                        📋 <?= $availability['bookings_count'] ?> agendado(s)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Função para mostrar alertas
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<strong>${type === 'success' ? '✅' : '❌'}</strong> ${message}`;
            
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alert);
            
            // Auto-remove após 5 segundos
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
        
        // Envio do formulário via AJAX
        document.getElementById('availability-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_availability');
            formData.append('ajax', '1');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Criando...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    this.reset();
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro ao criar disponibilidade. Tente novamente.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '➕ Criar Disponibilidade';
            });
        });
        
        // Função para remover disponibilidade
        function removeAvailability(id) {
            if (!confirm('Tem certeza que deseja remover esta disponibilidade?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'remove_availability');
            formData.append('availability_id', id);
            formData.append('ajax', '1');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro ao remover disponibilidade. Tente novamente.', 'error');
            });
        }
        
        // Validação de horários em tempo real
        document.getElementById('start_time').addEventListener('change', function() {
            const endTime = document.getElementById('end_time');
            if (this.value) {
                // Definir mínimo para o horário de fim (1 hora após o início)
                const startTime = new Date('2000-01-01 ' + this.value);
                startTime.setHours(startTime.getHours() + 1);
                const minEndTime = startTime.toTimeString().slice(0, 5);
                endTime.min = minEndTime;
                
                if (endTime.value && endTime.value <= this.value) {
                    endTime.value = minEndTime;
                }
            }
        });
        
        // Validação ao alterar horário de fim
        document.getElementById('end_time').addEventListener('change', function() {
            const startTime = document.getElementById('start_time').value;
            if (startTime && this.value <= startTime) {
                showAlert('O horário de fim deve ser pelo menos 1 hora após o início', 'error');
                this.value = '';
            }
        });
        
        // Auto-selecionar horários padrão ao escolher data
        document.getElementById('date').addEventListener('change', function() {
            const startTime = document.getElementById('start_time');
            const endTime = document.getElementById('end_time');
            
            if (!startTime.value) {
                startTime.value = '09:00';
            }
            if (!endTime.value) {
                endTime.value = '17:00';
            }
        });
    </script>
</body>
</html>
