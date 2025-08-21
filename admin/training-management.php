<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Treinamentos - Blue Cleaning Admin</title>
    <link rel="stylesheet" href="../liquid-glass-components.css">
    <link rel="stylesheet" href="../assets/css/inclusion-layout.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border-color);
        }

        .training-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .training-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
            box-shadow: var(--glass-shadow);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .training-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .training-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .training-status {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: var(--success-color-light);
            color: var(--success-color);
        }

        .status-draft {
            background: var(--warning-color-light);
            color: var(--warning-color);
        }

        .status-archived {
            background: var(--glass-bg-light);
            color: var(--text-secondary);
        }

        .training-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--glass-bg-light);
            border-radius: var(--radius-md);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .training-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--glass-border-color);
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border-color);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--glass-bg-light);
            color: var(--text-primary);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .question-item {
            background: var(--glass-bg-light);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--glass-border-color);
        }

        .question-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .question-type {
            padding: 0.25rem 0.5rem;
            background: var(--primary-color-light);
            color: var(--primary-color);
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .answer-options {
            margin-top: 1rem;
        }

        .answer-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
            padding: 0.5rem;
            background: var(--glass-bg);
            border-radius: var(--radius-sm);
        }

        .answer-option.correct {
            background: var(--success-color-light);
            border: 1px solid var(--success-color);
        }

        .candidates-table {
            background: var(--glass-bg);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
            box-shadow: var(--glass-shadow);
            overflow: hidden;
        }

        .table-header {
            background: var(--glass-bg-light);
            padding: 1rem;
            font-weight: 600;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 120px;
            gap: 1rem;
            align-items: center;
        }

        .table-row {
            padding: 1rem;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 120px;
            gap: 1rem;
            align-items: center;
            border-top: 1px solid var(--glass-border-color);
            transition: all 0.3s ease;
        }

        .table-row:hover {
            background: var(--glass-bg-light);
        }

        .progress-bar-small {
            width: 100%;
            height: 6px;
            background: var(--glass-bg-light);
            border-radius: var(--radius-full);
            overflow: hidden;
        }

        .progress-fill-small {
            height: 100%;
            background: var(--primary-color);
            border-radius: var(--radius-full);
            transition: width 0.3s ease;
        }

        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--glass-bg);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding-left: 2.5rem;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .admin-container {
                margin: 1rem;
                padding: 1rem;
            }

            .admin-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .training-grid {
                grid-template-columns: 1fr;
            }

            .table-header,
            .table-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .filter-bar {
                flex-direction: column;
            }

            .modal-content {
                width: 95%;
                margin: 1rem;
                padding: 1rem;
            }
        }

        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--glass-border-color);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* File upload styling */
        .file-upload {
            border: 2px dashed var(--glass-border-color);
            border-radius: var(--radius-md);
            padding: 2rem;
            text-align: center;
            background: var(--glass-bg-light);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: var(--primary-color);
            background: var(--primary-color-light);
        }

        .file-upload.drag-over {
            border-color: var(--primary-color);
            background: var(--primary-color-light);
            transform: scale(1.02);
        }

        .uploaded-files {
            margin-top: 1rem;
        }

        .uploaded-file {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem;
            background: var(--glass-bg);
            border-radius: var(--radius-sm);
            margin: 0.5rem 0;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-remove {
            color: var(--danger-color);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
        }

        .file-remove:hover {
            background: var(--danger-color-light);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1 class="heading-2 mb-2">Gerenciar Treinamentos</h1>
                <p class="text-secondary">Crie e gerencie treinamentos para profissionais</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="openCreateTrainingModal()">
                + Novo Treinamento
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="form-input" id="searchInput" placeholder="Buscar treinamentos...">
            </div>
            <select class="form-input" id="statusFilter">
                <option value="">Todos os status</option>
                <option value="active">Ativos</option>
                <option value="draft">Rascunhos</option>
                <option value="archived">Arquivados</option>
            </select>
            <select class="form-input" id="typeFilter">
                <option value="">Todos os tipos</option>
                <option value="onboarding">Onboarding</option>
                <option value="skill">Habilidade</option>
                <option value="certification">Certificação</option>
            </select>
        </div>

        <!-- Training Cards -->
        <div class="training-grid" id="trainingGrid">
            <!-- Training cards will be loaded here -->
        </div>

        <!-- Candidates Section -->
        <div class="candidates-section" style="margin-top: 4rem;">
            <div class="flex justify-between items-center mb-4">
                <h2 class="heading-3">Candidatos em Treinamento</h2>
                <button type="button" class="btn btn-secondary" onclick="refreshCandidates()">
                    🔄 Atualizar
                </button>
            </div>

            <div class="candidates-table" id="candidatesTable">
                <!-- Candidates table will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Create/Edit Training Modal -->
    <div class="modal" id="trainingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="heading-3" id="modalTitle">Novo Treinamento</h3>
                <button type="button" class="modal-close" onclick="closeTrainingModal()">×</button>
            </div>

            <form id="trainingForm">
                <input type="hidden" id="trainingId" name="training_id">

                <!-- Basic Info -->
                <div class="form-section">
                    <div class="section-title">📋 Informações Básicas</div>
                    
                    <div class="form-group">
                        <label class="form-label" for="trainingTitle">Título do Treinamento *</label>
                        <input type="text" id="trainingTitle" name="title" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="trainingDescription">Descrição *</label>
                        <textarea id="trainingDescription" name="description" class="form-input" rows="3" required></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label" for="trainingType">Tipo *</label>
                            <select id="trainingType" name="training_type" class="form-input" required>
                                <option value="">Selecione o tipo</option>
                                <option value="onboarding">Onboarding</option>
                                <option value="skill">Habilidade</option>
                                <option value="certification">Certificação</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="trainingDuration">Duração Estimada (minutos) *</label>
                            <input type="number" id="trainingDuration" name="estimated_duration" class="form-input" required min="1">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label" for="passingScore">Nota Mínima (%) *</label>
                            <input type="number" id="passingScore" name="passing_score" class="form-input" required min="0" max="100" value="70">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="maxAttempts">Máximo de Tentativas *</label>
                            <input type="number" id="maxAttempts" name="max_attempts" class="form-input" required min="1" value="3">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="skillsAcquired">Habilidades Adquiridas</label>
                        <textarea id="skillsAcquired" name="skills_acquired" class="form-input" rows="2" placeholder="Liste as habilidades separadas por vírgula"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="isRequired" name="is_required">
                            <span class="checkmark"></span>
                            Treinamento obrigatório para onboarding
                        </label>
                    </div>
                </div>

                <!-- Content -->
                <div class="form-section">
                    <div class="section-title">📚 Conteúdo do Treinamento</div>
                    
                    <div class="form-group">
                        <label class="form-label">Materiais de Estudo</label>
                        <div class="file-upload" id="fileUpload">
                            <div class="file-upload-content">
                                <div style="font-size: 2rem; margin-bottom: 1rem;">📁</div>
                                <p class="text-lg font-medium mb-1">Adicionar arquivos</p>
                                <p class="text-sm text-secondary">PDF, DOC, PPT, MP4, imagens - máx. 50MB cada</p>
                            </div>
                            <input type="file" id="fileInput" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.jpg,.jpeg,.png,.webp" style="display: none;">
                        </div>
                        <div class="uploaded-files" id="uploadedFiles"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="videoUrl">URL do Vídeo (YouTube/Vimeo)</label>
                        <input type="url" id="videoUrl" name="video_url" class="form-input" placeholder="https://www.youtube.com/watch?v=...">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="contentText">Conteúdo Textual</label>
                        <textarea id="contentText" name="content_text" class="form-input" rows="5" placeholder="Digite o conteúdo do treinamento..."></textarea>
                    </div>
                </div>

                <!-- Questions -->
                <div class="form-section">
                    <div class="section-title">
                        ❓ Avaliação
                        <button type="button" class="btn btn-sm btn-primary ml-auto" onclick="addQuestion()">
                            + Adicionar Pergunta
                        </button>
                    </div>
                    
                    <div id="questionsContainer">
                        <!-- Questions will be added here -->
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-between gap-4 mt-6 pt-4 border-top">
                    <button type="button" class="btn btn-secondary" onclick="closeTrainingModal()">
                        Cancelar
                    </button>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-outline" onclick="saveTrainingDraft()">
                            💾 Salvar Rascunho
                        </button>
                        <button type="submit" class="btn btn-primary">
                            ✅ Publicar Treinamento
                            <span class="spinner ml-2" id="saveSpinner" style="display: none;"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- View Training Details Modal -->
    <div class="modal" id="viewTrainingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="heading-3" id="viewModalTitle">Detalhes do Treinamento</h3>
                <button type="button" class="modal-close" onclick="closeViewTrainingModal()">×</button>
            </div>
            <div id="trainingDetails">
                <!-- Training details will be loaded here -->
            </div>
        </div>
    </div>

    <script src="../assets/js/training-management.js"></script>
</body>
</html>
