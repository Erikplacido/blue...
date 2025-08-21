/**
 * Candidate Trainings JavaScript
 * Blue Cleaning Services - Training Portal for Candidates
 */

class CandidateTrainings {
    constructor() {
        this.trainings = [];
        this.filteredTrainings = [];
        this.candidateData = {};
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadTrainings();
        this.loadCandidateData();
    }
    
    setupEventListeners() {
        // Search and filters
        document.getElementById('searchInput').addEventListener('input', this.filterTrainings.bind(this));
        document.getElementById('statusFilter').addEventListener('change', this.filterTrainings.bind(this));
        document.getElementById('typeFilter').addEventListener('change', this.filterTrainings.bind(this));
    }
    
    async loadTrainings() {
        try {
            this.showLoading(true);
            
            const response = await fetch('/api/candidates/training-portal.php?action=get_trainings', {
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Failed to load trainings');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.trainings = data.trainings || [];
                this.filteredTrainings = [...this.trainings];
                this.renderTrainings();
                this.updateProgressOverview();
            } else {
                throw new Error(data.message);
            }
            
        } catch (error) {
            console.error('Load trainings error:', error);
            this.showError(error.message);
        } finally {
            this.showLoading(false);
        }
    }
    
    async loadCandidateData() {
        try {
            const response = await fetch('/api/candidates/training-portal.php?action=get_candidate_data', {
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.candidateData = data.candidate;
                }
            }
        } catch (error) {
            console.error('Load candidate data error:', error);
        }
    }
    
    renderTrainings() {
        const grid = document.getElementById('trainingsGrid');
        const emptyState = document.getElementById('emptyState');
        
        if (this.filteredTrainings.length === 0) {
            grid.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }
        
        grid.style.display = 'grid';
        emptyState.style.display = 'none';
        
        grid.innerHTML = this.filteredTrainings.map(training => this.renderTrainingCard(training)).join('');
    }
    
    renderTrainingCard(training) {
        const typeClass = {
            'onboarding': 'type-onboarding',
            'skill': 'type-skill',
            'certification': 'type-certification'
        }[training.training_type] || 'type-onboarding';
        
        const typeIcon = {
            'onboarding': '🎯',
            'skill': '🛠️',
            'certification': '🏆'
        }[training.training_type] || '📚';
        
        const statusClass = {
            'assigned': 'status-assigned',
            'in_progress': 'status-in-progress',
            'completed': 'status-completed',
            'failed': 'status-failed'
        }[training.status] || 'status-assigned';
        
        const statusText = {
            'assigned': 'Atribuído',
            'in_progress': 'Em Andamento',
            'completed': 'Concluído',
            'failed': 'Reprovado'
        }[training.status] || 'Atribuído';
        
        const progress = Math.round(training.progress_percentage || 0);
        const canStart = training.status === 'assigned';
        const canContinue = training.status === 'in_progress';
        const canRetake = training.status === 'failed' && (training.attempt_count || 0) < (training.max_attempts || 3);
        const isCompleted = training.status === 'completed';
        
        return `
            <div class="training-card">
                <div class="training-header">
                    <div class="training-type ${typeClass}">
                        ${typeIcon} ${this.capitalizeFirst(training.training_type)}
                    </div>
                    <div class="training-status ${statusClass}">
                        ${statusText}
                    </div>
                </div>
                
                <div class="training-content">
                    <h3 class="training-title">${training.title}</h3>
                    <p class="training-description">${training.description}</p>
                    
                    <div class="training-meta">
                        <div class="meta-item">
                            <span>⏱️</span>
                            <span>${training.estimated_duration} minutos</span>
                        </div>
                        <div class="meta-item">
                            <span>📊</span>
                            <span>Nota mínima: ${training.passing_score}%</span>
                        </div>
                        <div class="meta-item">
                            <span>🔄</span>
                            <span>Tentativas: ${training.attempt_count || 0}/${training.max_attempts}</span>
                        </div>
                        ${training.is_required ? '<div class="meta-item"><span>⭐</span><span>Obrigatório</span></div>' : ''}
                    </div>
                    
                    ${training.status !== 'assigned' ? `
                        <div class="progress-section">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${progress}%"></div>
                            </div>
                            <div class="progress-text">
                                <span>Progresso</span>
                                <span class="font-medium">${progress}%</span>
                            </div>
                        </div>
                    ` : ''}
                    
                    ${training.last_score !== null ? `
                        <div class="text-center p-3 bg-info-light rounded mb-3">
                            <div class="text-lg font-bold ${training.last_score >= training.passing_score ? 'text-success' : 'text-danger'}">
                                Última nota: ${training.last_score}%
                            </div>
                            ${training.last_score >= training.passing_score ? 
                                '<div class="text-sm text-success">✅ Aprovado</div>' : 
                                '<div class="text-sm text-danger">❌ Reprovado</div>'
                            }
                        </div>
                    ` : ''}
                </div>
                
                <div class="training-actions">
                    ${canStart ? `
                        <button type="button" class="btn btn-primary btn-training" 
                                onclick="candidateTrainings.startTraining(${training.id})">
                            ▶️ Iniciar Treinamento
                        </button>
                    ` : ''}
                    
                    ${canContinue ? `
                        <button type="button" class="btn btn-warning btn-training" 
                                onclick="candidateTrainings.continueTraining(${training.id})">
                            ⏯️ Continuar
                        </button>
                        <button type="button" class="btn btn-success btn-training" 
                                onclick="candidateTrainings.takeEvaluation(${training.id})">
                            📝 Fazer Avaliação
                        </button>
                    ` : ''}
                    
                    ${canRetake ? `
                        <button type="button" class="btn btn-info btn-training" 
                                onclick="candidateTrainings.retakeTraining(${training.id})">
                            🔄 Tentar Novamente
                        </button>
                    ` : ''}
                    
                    ${isCompleted ? `
                        <button type="button" class="btn btn-outline btn-training" 
                                onclick="candidateTrainings.reviewTraining(${training.id})">
                            👁️ Revisar Conteúdo
                        </button>
                        <button type="button" class="btn btn-success btn-training" 
                                onclick="candidateTrainings.downloadCertificate(${training.id})">
                            🏆 Certificado
                        </button>
                    ` : ''}
                </div>
                
                ${isCompleted ? `
                    <div class="certificate-section">
                        <div class="certificate-icon">🏆</div>
                        <div class="font-bold text-success mb-2">Treinamento Concluído!</div>
                        <div class="text-sm text-secondary">
                            Concluído em ${this.formatDate(training.completed_at)}
                        </div>
                        ${training.skills_acquired ? `
                            <div class="mt-2 text-sm">
                                <strong>Habilidades adquiridas:</strong><br>
                                ${training.skills_acquired}
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    updateProgressOverview() {
        const total = this.trainings.length;
        const inProgress = this.trainings.filter(t => t.status === 'in_progress').length;
        const completed = this.trainings.filter(t => t.status === 'completed').length;
        const overall = total > 0 ? Math.round((completed / total) * 100) : 0;
        
        document.getElementById('totalTrainings').textContent = total;
        document.getElementById('inProgressTrainings').textContent = inProgress;
        document.getElementById('completedTrainings').textContent = completed;
        document.getElementById('overallProgress').textContent = overall + '%';
    }
    
    filterTrainings() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value;
        const typeFilter = document.getElementById('typeFilter').value;
        
        this.filteredTrainings = this.trainings.filter(training => {
            const matchesSearch = training.title.toLowerCase().includes(searchTerm) ||
                                training.description.toLowerCase().includes(searchTerm);
            const matchesStatus = !statusFilter || training.status === statusFilter;
            const matchesType = !typeFilter || training.training_type === typeFilter;
            
            return matchesSearch && matchesStatus && matchesType;
        });
        
        this.renderTrainings();
    }
    
    async startTraining(trainingId) {
        try {
            const response = await fetch('/api/candidates/training-portal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'start_training',
                    training_id: trainingId
                }),
                credentials: 'same-origin'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Treinamento iniciado com sucesso!');
                // Redirect to training content page
                window.location.href = `/candidate/training-content.php?id=${trainingId}`;
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('Start training error:', error);
            this.showError(error.message);
        }
    }
    
    continueTraining(trainingId) {
        // Redirect to training content page
        window.location.href = `/candidate/training-content.php?id=${trainingId}`;
    }
    
    takeEvaluation(trainingId) {
        // Redirect to evaluation page
        window.location.href = `/candidate/training-evaluation.php?id=${trainingId}`;
    }
    
    async retakeTraining(trainingId) {
        if (!confirm('Deseja reiniciar este treinamento? Seu progresso atual será perdido.')) {
            return;
        }
        
        try {
            const response = await fetch('/api/candidates/training-portal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'retake_training',
                    training_id: trainingId
                }),
                credentials: 'same-origin'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Treinamento reiniciado com sucesso!');
                this.loadTrainings(); // Refresh the list
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('Retake training error:', error);
            this.showError(error.message);
        }
    }
    
    reviewTraining(trainingId) {
        // Redirect to review mode
        window.location.href = `/candidate/training-content.php?id=${trainingId}&mode=review`;
    }
    
    async downloadCertificate(trainingId) {
        try {
            const response = await fetch(`/api/candidates/training-portal.php?action=download_certificate&training_id=${trainingId}`, {
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `certificado_treinamento_${trainingId}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.showSuccess('Certificado baixado com sucesso!');
            } else {
                throw new Error('Erro ao baixar certificado');
            }
            
        } catch (error) {
            console.error('Download certificate error:', error);
            this.showError(error.message);
        }
    }
    
    showLoading(show) {
        const loadingState = document.getElementById('loadingState');
        const trainingsGrid = document.getElementById('trainingsGrid');
        
        if (show) {
            loadingState.style.display = 'block';
            trainingsGrid.style.display = 'none';
        } else {
            loadingState.style.display = 'none';
            trainingsGrid.style.display = 'grid';
        }
    }
    
    showError(message) {
        // Create or update error notification
        this.showNotification(message, 'error');
    }
    
    showSuccess(message) {
        // Create or update success notification
        this.showNotification(message, 'success');
    }
    
    showNotification(message, type) {
        // Remove existing notification
        const existing = document.getElementById('notification');
        if (existing) {
            existing.remove();
        }
        
        // Create new notification
        const notification = document.createElement('div');
        notification.id = 'notification';
        notification.className = `alert alert-${type === 'error' ? 'danger' : 'success'} notification`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>${type === 'error' ? '⚠️' : '✅'}</span>
                    <span>${message}</span>
                </div>
                <button type="button" onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; font-size: 1.2rem; cursor: pointer;">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
        
        // Add CSS for animation if not exists
        if (!document.getElementById('notificationStyles')) {
            const style = document.createElement('style');
            style.id = 'notificationStyles';
            style.textContent = `
                @keyframes slideInRight {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

// Initialize when DOM is loaded
let candidateTrainings;
document.addEventListener('DOMContentLoaded', function() {
    candidateTrainings = new CandidateTrainings();
});
