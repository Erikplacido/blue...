/**
 * Training Management JavaScript
 * Blue Cleaning Services - Admin Training System
 */

class TrainingManager {
    constructor() {
        this.trainings = [];
        this.candidates = [];
        this.currentTrainingId = null;
        this.questionCounter = 0;
        this.uploadedFiles = [];
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadTrainings();
        this.loadCandidates();
        this.setupFileUpload();
    }
    
    setupEventListeners() {
        // Search and filters
        document.getElementById('searchInput').addEventListener('input', this.filterTrainings.bind(this));
        document.getElementById('statusFilter').addEventListener('change', this.filterTrainings.bind(this));
        document.getElementById('typeFilter').addEventListener('change', this.filterTrainings.bind(this));
        
        // Training form
        const trainingForm = document.getElementById('trainingForm');
        trainingForm.addEventListener('submit', this.saveTraining.bind(this));
        
        // Modal events
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
        
        // Click outside modal to close
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeAllModals();
            }
        });
    }
    
    setupFileUpload() {
        const fileUpload = document.getElementById('fileUpload');
        const fileInput = document.getElementById('fileInput');
        
        if (!fileUpload || !fileInput) return;
        
        // Click to upload
        fileUpload.addEventListener('click', () => {
            fileInput.click();
        });
        
        // File selection
        fileInput.addEventListener('change', (e) => {
            this.handleFileSelection(e.target.files);
        });
        
        // Drag and drop
        fileUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUpload.classList.add('drag-over');
        });
        
        fileUpload.addEventListener('dragleave', () => {
            fileUpload.classList.remove('drag-over');
        });
        
        fileUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUpload.classList.remove('drag-over');
            this.handleFileSelection(e.dataTransfer.files);
        });
    }
    
    async loadTrainings() {
        try {
            const response = await fetch('/api/admin/training-management.php?action=list_trainings', {
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Failed to load trainings');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.trainings = data.trainings;
                this.renderTrainings();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('Load trainings error:', error);
            this.showError('Erro ao carregar treinamentos');
        }
    }
    
    async loadCandidates() {
        try {
            const response = await fetch('/api/admin/training-management.php?action=list_candidates', {
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Failed to load candidates');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.candidates = data.candidates;
                this.renderCandidates();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('Load candidates error:', error);
            this.showError('Erro ao carregar candidatos');
        }
    }
    
    renderTrainings() {
        const grid = document.getElementById('trainingGrid');
        if (!grid) return;
        
        if (this.trainings.length === 0) {
            grid.innerHTML = `
                <div class="text-center" style="grid-column: 1 / -1;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📚</div>
                    <h3 class="heading-4 mb-2">Nenhum treinamento encontrado</h3>
                    <p class="text-secondary">Crie seu primeiro treinamento para começar</p>
                    <button type="button" class="btn btn-primary mt-3" onclick="openCreateTrainingModal()">
                        + Criar Treinamento
                    </button>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = this.trainings.map(training => this.renderTrainingCard(training)).join('');
    }
    
    renderTrainingCard(training) {
        const statusClass = {
            'active': 'status-active',
            'draft': 'status-draft',
            'archived': 'status-archived'
        }[training.status] || 'status-draft';
        
        const statusText = {
            'active': 'Ativo',
            'draft': 'Rascunho',
            'archived': 'Arquivado'
        }[training.status] || 'Rascunho';
        
        const typeIcon = {
            'onboarding': '🎯',
            'skill': '🛠️',
            'certification': '🏆'
        }[training.training_type] || '📚';
        
        return `
            <div class="training-card">
                <div class="training-header">
                    <div>
                        <span style="font-size: 1.5rem; margin-right: 0.5rem;">${typeIcon}</span>
                        <span class="training-status ${statusClass}">${statusText}</span>
                    </div>
                    ${training.is_required ? '<span title="Obrigatório">⭐</span>' : ''}
                </div>
                
                <h3 class="heading-4 mb-2">${training.title}</h3>
                <p class="text-secondary text-sm mb-3">${training.description}</p>
                
                <div class="training-stats">
                    <div class="stat-item">
                        <div class="stat-number">${training.enrolled_count || 0}</div>
                        <div class="stat-label">Inscritos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">${training.completed_count || 0}</div>
                        <div class="stat-label">Concluídos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">${Math.round(((training.completed_count || 0) / Math.max(training.enrolled_count || 1, 1)) * 100)}%</div>
                        <div class="stat-label">Taxa</div>
                    </div>
                </div>
                
                <div class="text-sm text-secondary">
                    <div>⏱️ ${training.estimated_duration} minutos</div>
                    <div>📊 Nota mínima: ${training.passing_score}%</div>
                    <div>🔄 Máx. tentativas: ${training.max_attempts}</div>
                </div>
                
                <div class="training-actions">
                    <button type="button" class="btn btn-icon btn-primary" 
                            onclick="viewTraining(${training.id})" title="Visualizar">
                        👁️
                    </button>
                    <button type="button" class="btn btn-icon btn-secondary" 
                            onclick="editTraining(${training.id})" title="Editar">
                        ✏️
                    </button>
                    <button type="button" class="btn btn-icon btn-outline" 
                            onclick="duplicateTraining(${training.id})" title="Duplicar">
                        📋
                    </button>
                    ${training.status === 'active' ? `
                        <button type="button" class="btn btn-icon btn-warning" 
                                onclick="archiveTraining(${training.id})" title="Arquivar">
                            📦
                        </button>
                    ` : `
                        <button type="button" class="btn btn-icon btn-success" 
                                onclick="activateTraining(${training.id})" title="Ativar">
                            ▶️
                        </button>
                    `}
                    <button type="button" class="btn btn-icon btn-danger" 
                            onclick="deleteTraining(${training.id})" title="Excluir">
                        🗑️
                    </button>
                </div>
            </div>
        `;
    }
    
    renderCandidates() {
        const table = document.getElementById('candidatesTable');
        if (!table) return;
        
        if (this.candidates.length === 0) {
            table.innerHTML = `
                <div class="text-center p-8">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                    <h3 class="heading-4 mb-2">Nenhum candidato em treinamento</h3>
                    <p class="text-secondary">Os candidatos aparecem aqui quando iniciam os treinamentos</p>
                </div>
            `;
            return;
        }
        
        table.innerHTML = `
            <div class="table-header">
                <div>Candidato</div>
                <div>Treinamento</div>
                <div>Progresso</div>
                <div>Status</div>
                <div>Ações</div>
            </div>
            ${this.candidates.map(candidate => this.renderCandidateRow(candidate)).join('')}
        `;
    }
    
    renderCandidateRow(candidate) {
        const statusClass = {
            'in_progress': 'status-warning',
            'completed': 'status-success',
            'failed': 'status-danger'
        }[candidate.status] || 'status-warning';
        
        const statusText = {
            'in_progress': 'Em Andamento',
            'completed': 'Concluído',
            'failed': 'Reprovado'
        }[candidate.status] || 'Em Andamento';
        
        const progress = Math.round(candidate.progress_percentage || 0);
        
        return `
            <div class="table-row">
                <div>
                    <div class="font-medium">${candidate.first_name} ${candidate.last_name}</div>
                    <div class="text-sm text-secondary">${candidate.email}</div>
                </div>
                <div>
                    <div class="font-medium">${candidate.training_title}</div>
                    <div class="text-sm text-secondary">${candidate.training_type}</div>
                </div>
                <div>
                    <div class="progress-bar-small">
                        <div class="progress-fill-small" style="width: ${progress}%"></div>
                    </div>
                    <div class="text-sm text-secondary mt-1">${progress}% concluído</div>
                </div>
                <div>
                    <span class="training-status ${statusClass}">${statusText}</span>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-primary" 
                            onclick="viewCandidateProgress(${candidate.id})">
                        👁️ Ver
                    </button>
                </div>
            </div>
        `;
    }
    
    filterTrainings() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value;
        const typeFilter = document.getElementById('typeFilter').value;
        
        const filtered = this.trainings.filter(training => {
            const matchesSearch = training.title.toLowerCase().includes(searchTerm) ||
                                training.description.toLowerCase().includes(searchTerm);
            const matchesStatus = !statusFilter || training.status === statusFilter;
            const matchesType = !typeFilter || training.training_type === typeFilter;
            
            return matchesSearch && matchesStatus && matchesType;
        });
        
        const grid = document.getElementById('trainingGrid');
        if (filtered.length === 0) {
            grid.innerHTML = `
                <div class="text-center" style="grid-column: 1 / -1;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                    <h3 class="heading-4 mb-2">Nenhum resultado encontrado</h3>
                    <p class="text-secondary">Tente ajustar os filtros de busca</p>
                </div>
            `;
        } else {
            grid.innerHTML = filtered.map(training => this.renderTrainingCard(training)).join('');
        }
    }
    
    handleFileSelection(files) {
        Array.from(files).forEach(file => {
            if (this.validateFile(file)) {
                this.uploadedFiles.push({
                    file: file,
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    id: Date.now() + Math.random()
                });
            }
        });
        
        this.updateUploadedFilesList();
    }
    
    validateFile(file) {
        const maxSize = 50 * 1024 * 1024; // 50MB
        const allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'video/mp4',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp'
        ];
        
        if (file.size > maxSize) {
            this.showError(`Arquivo "${file.name}" é muito grande. Máximo 50MB.`);
            return false;
        }
        
        if (!allowedTypes.includes(file.type)) {
            this.showError(`Tipo de arquivo "${file.name}" não é permitido.`);
            return false;
        }
        
        return true;
    }
    
    updateUploadedFilesList() {
        const container = document.getElementById('uploadedFiles');
        if (!container) return;
        
        if (this.uploadedFiles.length === 0) {
            container.innerHTML = '';
            return;
        }
        
        container.innerHTML = this.uploadedFiles.map(fileData => `
            <div class="uploaded-file">
                <div class="file-info">
                    <span>${this.getFileIcon(fileData.type)}</span>
                    <div>
                        <div class="font-medium">${fileData.name}</div>
                        <div class="text-sm text-secondary">${this.formatFileSize(fileData.size)}</div>
                    </div>
                </div>
                <span class="file-remove" onclick="trainingManager.removeFile('${fileData.id}')">
                    ❌
                </span>
            </div>
        `).join('');
    }
    
    getFileIcon(mimeType) {
        const icons = {
            'application/pdf': '📄',
            'application/msword': '📝',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '📝',
            'application/vnd.ms-powerpoint': '📊',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation': '📊',
            'video/mp4': '🎥',
            'image/jpeg': '🖼️',
            'image/jpg': '🖼️',
            'image/png': '🖼️',
            'image/webp': '🖼️'
        };
        
        return icons[mimeType] || '📎';
    }
    
    formatFileSize(bytes) {
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 Bytes';
        const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }
    
    removeFile(fileId) {
        this.uploadedFiles = this.uploadedFiles.filter(file => file.id !== fileId);
        this.updateUploadedFilesList();
    }
    
    addQuestion() {
        this.questionCounter++;
        const container = document.getElementById('questionsContainer');
        if (!container) return;
        
        const questionHtml = `
            <div class="question-item" data-question-id="${this.questionCounter}">
                <div class="question-header">
                    <div class="flex-1">
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div class="form-group">
                                <label class="form-label">Tipo da Pergunta</label>
                                <select name="questions[${this.questionCounter}][type]" class="form-input" required>
                                    <option value="multiple_choice">Múltipla Escolha</option>
                                    <option value="true_false">Verdadeiro/Falso</option>
                                    <option value="text">Resposta Texto</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Pontos</label>
                                <input type="number" name="questions[${this.questionCounter}][points]" class="form-input" value="1" min="1" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Pergunta</label>
                            <textarea name="questions[${this.questionCounter}][question]" class="form-input" rows="2" required></textarea>
                        </div>
                        
                        <div class="answer-options" id="answers-${this.questionCounter}">
                            <div class="form-group">
                                <label class="form-label">Opções de Resposta</label>
                                <div class="answer-option">
                                    <input type="radio" name="questions[${this.questionCounter}][correct]" value="0" required>
                                    <input type="text" name="questions[${this.questionCounter}][options][]" class="form-input" placeholder="Opção 1" required>
                                </div>
                                <div class="answer-option">
                                    <input type="radio" name="questions[${this.questionCounter}][correct]" value="1">
                                    <input type="text" name="questions[${this.questionCounter}][options][]" class="form-input" placeholder="Opção 2" required>
                                </div>
                                <div class="answer-option">
                                    <input type="radio" name="questions[${this.questionCounter}][correct]" value="2">
                                    <input type="text" name="questions[${this.questionCounter}][options][]" class="form-input" placeholder="Opção 3">
                                </div>
                                <div class="answer-option">
                                    <input type="radio" name="questions[${this.questionCounter}][correct]" value="3">
                                    <input type="text" name="questions[${this.questionCounter}][options][]" class="form-input" placeholder="Opção 4">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Explicação (opcional)</label>
                                <textarea name="questions[${this.questionCounter}][explanation]" class="form-input" rows="2" placeholder="Explique por que esta é a resposta correta..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-danger" onclick="trainingManager.removeQuestion(${this.questionCounter})">
                        🗑️ Remover
                    </button>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', questionHtml);
    }
    
    removeQuestion(questionId) {
        const questionElement = document.querySelector(`[data-question-id="${questionId}"]`);
        if (questionElement && confirm('Deseja remover esta pergunta?')) {
            questionElement.remove();
        }
    }
    
    async saveTraining(event) {
        event.preventDefault();
        
        try {
            this.showLoading('saveSpinner', true);
            
            const formData = new FormData(event.target);
            
            // Add uploaded files
            this.uploadedFiles.forEach(fileData => {
                formData.append('files[]', fileData.file);
            });
            
            formData.append('action', 'save_training');
            formData.append('status', 'active'); // Published
            
            const response = await fetch('/api/admin/training-management.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Failed to save training');
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Treinamento salvo com sucesso!');
                this.closeTrainingModal();
                this.loadTrainings(); // Refresh list
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('Save training error:', error);
            this.showError(error.message);
        } finally {
            this.showLoading('saveSpinner', false);
        }
    }
    
    async saveTrainingDraft() {
        try {
            const formData = new FormData(document.getElementById('trainingForm'));
            
            // Add uploaded files
            this.uploadedFiles.forEach(fileData => {
                formData.append('files[]', fileData.file);
            });
            
            formData.append('action', 'save_training');
            formData.append('status', 'draft'); // Draft
            
            const response = await fetch('/api/admin/training-management.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Failed to save draft');
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Rascunho salvo com sucesso!');
                this.loadTrainings(); // Refresh list
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('Save draft error:', error);
            this.showError(error.message);
        }
    }
    
    openCreateTrainingModal() {
        this.currentTrainingId = null;
        document.getElementById('modalTitle').textContent = 'Novo Treinamento';
        document.getElementById('trainingForm').reset();
        document.getElementById('questionsContainer').innerHTML = '';
        this.uploadedFiles = [];
        this.updateUploadedFilesList();
        this.questionCounter = 0;
        document.getElementById('trainingModal').classList.add('active');
    }
    
    closeTrainingModal() {
        document.getElementById('trainingModal').classList.remove('active');
    }
    
    closeViewTrainingModal() {
        document.getElementById('viewTrainingModal').classList.remove('active');
    }
    
    closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('active');
        });
    }
    
    showLoading(spinnerId, show) {
        const spinner = document.getElementById(spinnerId);
        if (spinner) {
            spinner.style.display = show ? 'inline-block' : 'none';
        }
    }
    
    showError(message) {
        // Implementation similar to other components
        console.error(message);
        alert('Erro: ' + message); // Temporary - should use proper notification system
    }
    
    showSuccess(message) {
        // Implementation similar to other components
        console.log(message);
        alert('Sucesso: ' + message); // Temporary - should use proper notification system
    }
}

// Global functions for onclick handlers
function viewTraining(id) {
    trainingManager.viewTraining(id);
}

function editTraining(id) {
    trainingManager.editTraining(id);
}

function duplicateTraining(id) {
    trainingManager.duplicateTraining(id);
}

function archiveTraining(id) {
    trainingManager.archiveTraining(id);
}

function activateTraining(id) {
    trainingManager.activateTraining(id);
}

function deleteTraining(id) {
    trainingManager.deleteTraining(id);
}

function viewCandidateProgress(id) {
    trainingManager.viewCandidateProgress(id);
}

function openCreateTrainingModal() {
    trainingManager.openCreateTrainingModal();
}

function closeTrainingModal() {
    trainingManager.closeTrainingModal();
}

function closeViewTrainingModal() {
    trainingManager.closeViewTrainingModal();
}

function refreshCandidates() {
    trainingManager.loadCandidates();
}

function addQuestion() {
    trainingManager.addQuestion();
}

function saveTrainingDraft() {
    trainingManager.saveTrainingDraft();
}

// Initialize when DOM is loaded
let trainingManager;
document.addEventListener('DOMContentLoaded', function() {
    trainingManager = new TrainingManager();
});
