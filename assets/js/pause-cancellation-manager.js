/**
 * Sistema de Pausas e Cancelamentos - Blue Project V2
 * Integração completa com Stripe para autonomia total
 */

class PauseCancellationManager {
    constructor(config = {}) {
        this.config = {
            apiBaseUrl: config.apiBaseUrl || (window.location.pathname.includes('/allblue/') ? '/allblue/api' : '/api'),
            stripePublicKey: config.stripePublicKey || '',
            debug: config.debug || false,
            ...config
        };
        
        this.stripe = null;
        this.currentBooking = null;
        this.pauseTier = null;
        this.isInitialized = false;
        
        this.init();
    }
    
    async init() {
        try {
            // Inicializa Stripe se a chave estiver disponível
            if (this.config.stripePublicKey && window.Stripe) {
                this.stripe = Stripe(this.config.stripePublicKey);
                this.log('Stripe inicializado com sucesso');
            }
            
            // Carrega configurações do sistema
            await this.loadSystemConfig();
            
            // Inicializa event listeners
            this.initEventListeners();
            
            this.isInitialized = true;
            this.log('PauseCancellationManager inicializado');
            
        } catch (error) {
            this.error('Erro na inicialização:', error);
        }
    }
    
    async loadSystemConfig() {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}/system-config`);
            if (response.ok) {
                this.systemConfig = await response.json();
                this.log('Configurações do sistema carregadas:', this.systemConfig);
            }
        } catch (error) {
            this.error('Erro ao carregar configurações:', error);
        }
    }
    
    initEventListeners() {
        // Botões de pausa
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="pause-subscription"]')) {
                e.preventDefault();
                this.showPauseModal(e.target.dataset.bookingId);
            }
            
            if (e.target.matches('[data-action="cancel-subscription"]')) {
                e.preventDefault();
                this.showCancellationModal(e.target.dataset.bookingId);
            }
            
            if (e.target.matches('[data-action="confirm-pause"]')) {
                e.preventDefault();
                this.processPause();
            }
            
            if (e.target.matches('[data-action="confirm-cancellation"]')) {
                e.preventDefault();
                this.processCancellation();
            }
        });
        
        // Formulários
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#pause-form')) {
                e.preventDefault();
                this.handlePauseForm(e.target);
            }
            
            if (e.target.matches('#cancellation-form')) {
                e.preventDefault();
                this.handleCancellationForm(e.target);
            }
        });
    }
    
    async showPauseModal(bookingId) {
        try {
            // Carrega informações do booking
            this.currentBooking = await this.loadBookingInfo(bookingId);
            if (!this.currentBooking) {
                throw new Error('Booking não encontrado');
            }
            
            // Determina tier de pausas
            this.pauseTier = await this.determinePauseTier(bookingId);
            
            // Gera HTML do modal
            const modalHtml = this.generatePauseModalHtml();
            this.showModal('pause-modal', modalHtml);
            
        } catch (error) {
            this.error('Erro ao exibir modal de pausa:', error);
            this.showErrorMessage('Erro ao carregar informações de pausa. Tente novamente.');
        }
    }
    
    async showCancellationModal(bookingId) {
        try {
            // Carrega informações do booking
            this.currentBooking = await this.loadBookingInfo(bookingId);
            if (!this.currentBooking) {
                throw new Error('Booking não encontrado');
            }
            
            // Calcula penalidade
            const penaltyInfo = await this.calculateCancellationPenalty(bookingId);
            
            // Gera HTML do modal
            const modalHtml = this.generateCancellationModalHtml(penaltyInfo);
            this.showModal('cancellation-modal', modalHtml);
            
        } catch (error) {
            this.error('Erro ao exibir modal de cancelamento:', error);
            this.showErrorMessage('Erro ao carregar informações de cancelamento. Tente novamente.');
        }
    }
    
    generatePauseModalHtml() {
        const pausesUsed = this.currentBooking.used_pauses || 0;
        const pausesAllowed = this.pauseTier.free_pauses;
        const pausesRemaining = Math.max(0, pausesAllowed - pausesUsed);
        const isFreePause = pausesRemaining > 0;
        
        return `
            <div class="modal-overlay" id="pause-modal">
                <div class="modal-content pause-modal">
                    <div class="modal-header">
                        <h3>Pausar Assinatura</h3>
                        <button class="modal-close" data-action="close-modal">&times;</button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="pause-tier-info">
                            <div class="tier-badge tier-${this.pauseTier.tier_id}">
                                ${this.pauseTier.tier_name}
                            </div>
                            <p>Você possui <strong>${pausesRemaining}</strong> pausas gratuitas restantes.</p>
                        </div>
                        
                        <form id="pause-form">
                            <div class="form-group">
                                <label for="pause-start-date">Data de início da pausa:</label>
                                <input type="date" id="pause-start-date" name="start_date" 
                                       min="${this.getMinPauseDate()}" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="pause-duration">Duração da pausa (dias):</label>
                                <select id="pause-duration" name="duration" required>
                                    <option value="7">1 semana</option>
                                    <option value="14">2 semanas</option>
                                    <option value="30">1 mês</option>
                                    <option value="60">2 meses</option>
                                    <option value="90">3 meses</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="pause-reason">Motivo da pausa (opcional):</label>
                                <select id="pause-reason" name="reason">
                                    <option value="">Selecione um motivo</option>
                                    <option value="vacation">Viagem/Férias</option>
                                    <option value="financial">Motivos financeiros</option>
                                    <option value="temporary-relocation">Mudança temporária</option>
                                    <option value="dissatisfaction">Insatisfação com o serviço</option>
                                    <option value="other">Outro</option>
                                </select>
                            </div>
                            
                            ${!isFreePause ? `
                                <div class="pause-fee-notice">
                                    <div class="alert alert-warning">
                                        <strong>Taxa de pausa:</strong> Como você excedeu suas pausas gratuitas, 
                                        será cobrada uma taxa de <strong>$${this.systemConfig?.pause_fee || 0}</strong>.
                                    </div>
                                </div>
                            ` : ''}
                            
                            <div class="pause-summary">
                                <h4>Resumo da pausa:</h4>
                                <ul>
                                    <li>Tier atual: <strong>${this.pauseTier.tier_name}</strong></li>
                                    <li>Pausas usadas: <strong>${pausesUsed}/${pausesAllowed}</strong></li>
                                    <li>Pausas restantes: <strong>${pausesRemaining}</strong></li>
                                    <li>Taxa: <strong>${isFreePause ? 'Gratuita' : '$' + (this.systemConfig?.pause_fee || 0)}</strong></li>
                                </ul>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" data-action="close-modal">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-primary" data-action="confirm-pause">
                                    ${isFreePause ? 'Pausar Gratuitamente' : 'Pausar e Pagar Taxa'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
    }
    
    generateCancellationModalHtml(penaltyInfo) {
        const withinFreeWindow = this.isWithinFreeCancellationWindow();
        
        return `
            <div class="modal-overlay" id="cancellation-modal">
                <div class="modal-content cancellation-modal">
                    <div class="modal-header">
                        <h3>Cancelar Assinatura</h3>
                        <button class="modal-close" data-action="close-modal">&times;</button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="cancellation-warning">
                            <div class="alert alert-danger">
                                <strong>Atenção:</strong> Esta ação é irreversível. Sua assinatura será cancelada permanentemente.
                            </div>
                        </div>
                        
                        <div class="cancellation-details">
                            <h4>Detalhes do cancelamento:</h4>
                            <ul>
                                <li>Tipo de recorrência: <strong>${this.currentBooking.recurrence_pattern || 'Único'}</strong></li>
                                <li>Valor total: <strong>$${this.currentBooking.total_amount || 0}</strong></li>
                                <li>Serviços restantes: <strong>${this.currentBooking.remaining_services || 1}</strong></li>
                            </ul>
                        </div>
                        
                        ${!withinFreeWindow ? `
                            <div class="penalty-info">
                                <div class="alert alert-warning">
                                    <h4>Taxa de cancelamento:</h4>
                                    <p>Como o cancelamento está sendo feito fora do período gratuito de 48h, será aplicada uma penalidade de:</p>
                                    <div class="penalty-amount">
                                        <strong>$${penaltyInfo.penalty_amount.toFixed(2)}</strong>
                                        <small>(${penaltyInfo.penalty_percentage}% do valor restante)</small>
                                    </div>
                                </div>
                            </div>
                        ` : `
                            <div class="free-cancellation-notice">
                                <div class="alert alert-success">
                                    <strong>Cancelamento gratuito:</strong> Como você está dentro do período de 48h, 
                                    não será cobrada nenhuma taxa de cancelamento.
                                </div>
                            </div>
                        `}
                        
                        <form id="cancellation-form">
                            <div class="form-group">
                                <label for="cancellation-reason">Motivo do cancelamento:</label>
                                <select id="cancellation-reason" name="reason" required>
                                    <option value="">Selecione um motivo</option>
                                    <option value="price">Preço muito alto</option>
                                    <option value="service-quality">Qualidade do serviço</option>
                                    <option value="schedule">Problemas de agendamento</option>
                                    <option value="moving">Mudança de endereço</option>
                                    <option value="financial">Dificuldades financeiras</option>
                                    <option value="competitor">Encontrou melhor opção</option>
                                    <option value="other">Outro</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="cancellation-feedback">Feedback adicional (opcional):</label>
                                <textarea id="cancellation-feedback" name="feedback" 
                                         placeholder="Conte-nos como podemos melhorar..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="confirm-cancellation" name="confirm" required>
                                    Confirmo que desejo cancelar permanentemente esta assinatura
                                </label>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" data-action="close-modal">
                                    Manter Assinatura
                                </button>
                                <button type="submit" class="btn btn-danger" data-action="confirm-cancellation">
                                    ${withinFreeWindow ? 'Cancelar Gratuitamente' : `Cancelar e Pagar $${penaltyInfo.penalty_amount.toFixed(2)}`}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
    }
    
    async processPause() {
        try {
            const form = document.getElementById('pause-form');
            const formData = new FormData(form);
            
            const pauseData = {
                booking_id: this.currentBooking.booking_id,
                start_date: formData.get('start_date'),
                duration: parseInt(formData.get('duration')),
                reason: formData.get('reason'),
                tier_info: this.pauseTier
            };
            
            this.showLoading('Processando pausa...');
            
            const response = await fetch(`${this.config.apiBaseUrl}/pause-subscription`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(pauseData)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                this.hideLoading();
                this.closeModal();
                this.showSuccessMessage('Assinatura pausada com sucesso!');
                
                // Atualiza a interface
                this.updateBookingStatus('paused');
                
            } else {
                throw new Error(result.message || 'Erro ao processar pausa');
            }
            
        } catch (error) {
            this.hideLoading();
            this.error('Erro ao processar pausa:', error);
            this.showErrorMessage('Erro ao pausar assinatura. Tente novamente.');
        }
    }
    
    async processCancellation() {
        try {
            const form = document.getElementById('cancellation-form');
            const formData = new FormData(form);
            
            const cancellationData = {
                booking_id: this.currentBooking.booking_id,
                reason: formData.get('reason'),
                feedback: formData.get('feedback'),
                confirmed: formData.get('confirm') === 'on'
            };
            
            if (!cancellationData.confirmed) {
                this.showErrorMessage('Você deve confirmar o cancelamento');
                return;
            }
            
            this.showLoading('Processando cancelamento...');
            
            const response = await fetch(`${this.config.apiBaseUrl}/cancel-subscription`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(cancellationData)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                this.hideLoading();
                this.closeModal();
                this.showSuccessMessage('Assinatura cancelada com sucesso!');
                
                // Atualiza a interface
                this.updateBookingStatus('cancelled');
                
                // Redireciona após 3 segundos
                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 3000);
                
            } else {
                throw new Error(result.message || 'Erro ao processar cancelamento');
            }
            
        } catch (error) {
            this.hideLoading();
            this.error('Erro ao processar cancelamento:', error);
            this.showErrorMessage('Erro ao cancelar assinatura. Tente novamente.');
        }
    }
    
    async loadBookingInfo(bookingId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}/booking/${bookingId}`);
            if (response.ok) {
                return await response.json();
            }
            throw new Error('Booking não encontrado');
        } catch (error) {
            this.error('Erro ao carregar booking:', error);
            return null;
        }
    }
    
    async determinePauseTier(bookingId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}/pause-tier/${bookingId}`);
            if (response.ok) {
                return await response.json();
            }
            throw new Error('Tier não determinado');
        } catch (error) {
            this.error('Erro ao determinar tier:', error);
            return { tier_id: 'basic', tier_name: 'Basic', free_pauses: 2 };
        }
    }
    
    async calculateCancellationPenalty(bookingId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}/cancellation-penalty/${bookingId}`);
            if (response.ok) {
                return await response.json();
            }
            throw new Error('Penalidade não calculada');
        } catch (error) {
            this.error('Erro ao calcular penalidade:', error);
            return { penalty_amount: 0, penalty_percentage: 0 };
        }
    }
    
    getMinPauseDate() {
        const minHours = this.systemConfig?.minimum_notice_hours || 48;
        const minDate = new Date();
        minDate.setHours(minDate.getHours() + minHours);
        return minDate.toISOString().split('T')[0];
    }
    
    isWithinFreeCancellationWindow() {
        if (!this.currentBooking?.created_at) return false;
        
        const createdAt = new Date(this.currentBooking.created_at);
        const now = new Date();
        const hoursDiff = (now - createdAt) / (1000 * 60 * 60);
        
        return hoursDiff <= (this.systemConfig?.free_cancellation_hours || 48);
    }
    
    showModal(id, html) {
        // Remove modal existente
        const existingModal = document.getElementById(id);
        if (existingModal) {
            existingModal.remove();
        }
        
        // Adiciona novo modal
        document.body.insertAdjacentHTML('beforeend', html);
        
        // Adiciona event listeners para fechar
        const modal = document.getElementById(id);
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.matches('[data-action="close-modal"]')) {
                this.closeModal();
            }
        });
        
        // Adiciona classe para animação
        setTimeout(() => modal.classList.add('show'), 10);
    }
    
    closeModal() {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        });
    }
    
    showLoading(message = 'Carregando...') {
        const loadingHtml = `
            <div class="loading-overlay">
                <div class="loading-content">
                    <div class="spinner"></div>
                    <p>${message}</p>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', loadingHtml);
    }
    
    hideLoading() {
        const loading = document.querySelector('.loading-overlay');
        if (loading) {
            loading.remove();
        }
    }
    
    showSuccessMessage(message) {
        this.showNotification(message, 'success');
    }
    
    showErrorMessage(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove após 5 segundos
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Remove ao clicar no X
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        });
    }
    
    updateBookingStatus(status) {
        // Atualiza elementos da interface com o novo status
        const statusElements = document.querySelectorAll('[data-booking-status]');
        statusElements.forEach(element => {
            element.setAttribute('data-booking-status', status);
            element.textContent = this.getStatusText(status);
        });
        
        // Atualiza botões de ação
        const actionButtons = document.querySelectorAll('[data-booking-id]');
        actionButtons.forEach(button => {
            if (status === 'cancelled') {
                button.style.display = 'none';
            } else if (status === 'paused') {
                button.textContent = 'Reativar';
                button.setAttribute('data-action', 'resume-subscription');
            }
        });
    }
    
    getStatusText(status) {
        const statusTexts = {
            'active': 'Ativo',
            'paused': 'Pausado',
            'cancelled': 'Cancelado',
            'pending': 'Pendente'
        };
        
        return statusTexts[status] || status;
    }
    
    log(message, data = null) {
        if (this.config.debug) {
            console.log(`[PauseCancellationManager] ${message}`, data);
        }
    }
    
    error(message, error = null) {
        console.error(`[PauseCancellationManager] ${message}`, error);
    }
}

// Inicialização automática quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    // Verifica se as configurações estão disponíveis
    const config = window.pauseCancellationConfig || {};
    
    // Inicializa o gerenciador
    window.pauseCancellationManager = new PauseCancellationManager(config);
});

// Export para uso em módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PauseCancellationManager;
}
