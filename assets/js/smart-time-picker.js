/**
 * =========================================================
 * SMART TIME PICKER MODAL - Sistema de Seleção de Horários
 * =========================================================
 * 
 * Modal elegante para seleção de horários disponíveis
 * Integrado com o sistema de calendário existente
 */

class SmartTimePicker {
    constructor(options = {}) {
        this.options = {
            containerId: options.containerId || 'smart-time-picker-modal',
            serviceId: options.serviceId || 1,
            selectedDate: options.selectedDate || null,
            selectedDateFormatted: options.selectedDateFormatted || null,
            onTimeSelected: options.onTimeSelected || function() {},
            timeSlots: options.timeSlots || this.generateDefaultTimeSlots(),
            modalClass: options.modalClass || 'time-picker-modal',
            inputId: options.inputId || null,
            hiddenFieldId: options.hiddenFieldId || null
        };
        
        this.selectedTime = null;
        this.modalElement = null;
        this.isOpen = false;
        
        // Set up event listeners on input field
        if (this.options.inputId) {
            this.setupInputEventListeners();
        }
        
        console.log('🕐 Smart Time Picker inicializado:', this.options);
    }
    
    /**
     * Setup event listeners on input field
     */
    setupInputEventListeners() {
        const input = document.getElementById(this.options.inputId);
        if (input) {
            input.addEventListener('click', () => this.openModal());
            input.addEventListener('focus', () => this.openModal());
            console.log('✅ Event listeners configured for input:', this.options.inputId);
        } else {
            console.warn('⚠️ Input field not found:', this.options.inputId);
        }
    }
    
    /**
     * Gerar horários padrão (6h às 17h)
     */
    generateDefaultTimeSlots() {
        const slots = [];
        for (let hour = 6; hour <= 17; hour++) {
            const startTime = String(hour).padStart(2, '0') + ':00';
            const endTime = String(hour + 1).padStart(2, '0') + ':00';
            
            slots.push({
                value: startTime,
                display: `${startTime} – ${endTime}`,
                available: true,
                popular: hour >= 9 && hour <= 11 // Horários populares
            });
        }
        return slots;
    }
    
    /**
     * Criar modal de seleção de horários
     */
    createModal() {
        if (this.modalElement) {
            return this.modalElement;
        }
        
        // Remover modal existente se houver
        const existingModal = document.getElementById(this.options.containerId);
        if (existingModal) {
            existingModal.remove();
        }
        
        const modalHtml = `
            <div id="${this.options.containerId}" class="time-picker-modal-overlay" style="display: none;">
                <div class="time-picker-modal-content">
                    <!-- Header do Modal -->
                    <div class="time-picker-header">
                        <div class="time-picker-title">
                            <i class="fas fa-clock" style="color: #667eea; margin-right: 8px;"></i>
                            <span>Available Times</span>
                        </div>
                        <div class="time-picker-subtitle">
                            ${this.options.selectedDateFormatted || 'Select your preferred start time'}
                        </div>
                        <button type="button" class="time-picker-close" onclick="smartTimePicker.closeModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Corpo do Modal -->
                    <div class="time-picker-body">
                        <div class="time-slots-grid" id="time-slots-grid">
                            <!-- Horários serão inseridos aqui -->
                        </div>
                        
                        <!-- Legenda -->
                        <div class="time-picker-legend">
                            <div class="legend-item">
                                <div class="legend-color available"></div>
                                <span>Available</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color popular"></div>
                                <span>Popular times</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color unavailable"></div>
                                <span>Unavailable</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer do Modal -->
                    <div class="time-picker-footer">
                        <button type="button" class="btn-cancel" onclick="smartTimePicker.closeModal()">
                            Cancel
                        </button>
                        <button type="button" class="btn-confirm" id="confirm-time-btn" disabled onclick="smartTimePicker.confirmSelection()">
                            Confirm Time
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Inserir HTML no documento
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        this.modalElement = document.getElementById(this.options.containerId);
        
        // Adicionar event listeners
        this.addEventListeners();
        
        return this.modalElement;
    }
    
    /**
     * Adicionar event listeners
     */
    addEventListeners() {
        // Click fora do modal para fechar
        this.modalElement.addEventListener('click', (e) => {
            if (e.target === this.modalElement) {
                this.closeModal();
            }
        });
        
        // ESC para fechar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.closeModal();
            }
        });
    }
    
    /**
     * Renderizar horários disponíveis
     */
    renderTimeSlots() {
        const container = document.getElementById('time-slots-grid');
        if (!container) return;
        
        const slotsHtml = this.options.timeSlots.map(slot => {
            const isSelected = this.selectedTime === slot.value;
            const classes = [
                'time-slot-item',
                slot.available ? 'available' : 'unavailable',
                slot.popular ? 'popular' : '',
                isSelected ? 'selected' : ''
            ].filter(c => c).join(' ');
            
            return `
                <div 
                    class="${classes}"
                    data-time="${slot.value}"
                    data-available="${slot.available}"
                    onclick="smartTimePicker.selectTime('${slot.value}', '${slot.display}')"
                    ${!slot.available ? 'style="cursor: not-allowed;"' : ''}
                >
                    <div class="time-display">${slot.display}</div>
                    ${slot.popular ? '<div class="popular-badge">Popular</div>' : ''}
                    ${!slot.available ? '<div class="unavailable-badge">Unavailable</div>' : ''}
                    ${isSelected ? '<div class="selected-badge"><i class="fas fa-check"></i></div>' : ''}
                </div>
            `;
        }).join('');
        
        container.innerHTML = slotsHtml;
    }
    
    /**
     * Selecionar um horário
     */
    selectTime(timeValue, timeDisplay) {
        console.log('🕐 Selecionando horário:', timeValue);
        
        // Verificar se o horário está disponível
        const slot = this.options.timeSlots.find(s => s.value === timeValue);
        if (!slot || !slot.available) {
            console.warn('⚠️ Horário não disponível:', timeValue);
            return;
        }
        
        // Atualizar seleção
        this.selectedTime = timeValue;
        
        // Re-renderizar para mostrar seleção
        this.renderTimeSlots();
        
        // Habilitar botão de confirmação
        const confirmBtn = document.getElementById('confirm-time-btn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = `Confirm ${timeDisplay}`;
            confirmBtn.classList.add('enabled');
        }
        
        console.log('✅ Horário selecionado:', timeValue);
    }
    
    /**
     * Confirmar seleção
     */
    confirmSelection() {
        if (!this.selectedTime) {
            console.warn('⚠️ Nenhum horário selecionado');
            return;
        }
        
        const selectedSlot = this.options.timeSlots.find(s => s.value === this.selectedTime);
        
        console.log('✅ Confirmando seleção:', this.selectedTime);
        
        // Chamar callback
        if (typeof this.options.onTimeSelected === 'function') {
            this.options.onTimeSelected({
                time: this.selectedTime,
                display: selectedSlot.display,
                date: this.options.selectedDate,
                dateFormatted: this.options.selectedDateFormatted
            });
        }
        
        // Fechar modal
        this.closeModal();
    }
    
    /**
     * Abrir modal
     */
    openModal() {
        if (!this.modalElement) {
            this.createModal();
        }
        
        // Renderizar horários
        this.renderTimeSlots();
        
        // Mostrar modal com animação
        this.modalElement.style.display = 'flex';
        
        // Força o reflow e adiciona classe para animação
        this.modalElement.offsetHeight;
        this.modalElement.classList.add('open');
        
        this.isOpen = true;
        
        // Prevenção de scroll do body
        document.body.style.overflow = 'hidden';
        
        console.log('🕐 Modal de horários aberto');
    }
    
    /**
     * Fechar modal
     */
    closeModal() {
        if (!this.modalElement) return;
        
        // Animação de fechamento
        this.modalElement.classList.remove('open');
        
        setTimeout(() => {
            this.modalElement.style.display = 'none';
            this.isOpen = false;
            
            // Restaurar scroll do body
            document.body.style.overflow = '';
        }, 300);
        
        console.log('🕐 Modal de horários fechado');
    }
    
    /**
     * Atualizar data selecionada
     */
    updateSelectedDate(date, dateFormatted) {
        this.options.selectedDate = date;
        this.options.selectedDateFormatted = dateFormatted;
        
        // Atualizar título se modal estiver aberto
        if (this.isOpen) {
            const subtitle = this.modalElement.querySelector('.time-picker-subtitle');
            if (subtitle) {
                subtitle.textContent = dateFormatted;
            }
        }
    }
    
    /**
     * Atualizar horários disponíveis
     */
    updateAvailableSlots(timeSlots) {
        this.options.timeSlots = timeSlots;
        
        // Re-renderizar se modal estiver aberto
        if (this.isOpen) {
            this.renderTimeSlots();
        }
    }
    
    /**
     * Update available times (called when date is selected from calendar)
     * @param {Array} times - Array of time objects with {value, display, available}
     */
    updateAvailableTimes(times) {
        // Convert times to the format expected by SmartTimePicker
        const formattedSlots = times.map(time => ({
            value: time.value,
            display: time.display,
            available: time.available,
            popular: false // Could be enhanced to mark popular times
        }));
        
        this.updateAvailableSlots(formattedSlots);
        console.log('⏰ SmartTimePicker updated with', times.length, 'time slots');
    }
}

// CSS para o modal (será injetado dinamicamente)
const timePickerCSS = `
    .time-picker-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .time-picker-modal-overlay.open {
        opacity: 1;
    }
    
    .time-picker-modal-content {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        transform: translateY(50px) scale(0.9);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .time-picker-modal-overlay.open .time-picker-modal-content {
        transform: translateY(0) scale(1);
    }
    
    .time-picker-header {
        padding: 24px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(20px);
        position: relative;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .time-picker-title {
        font-size: 1.4rem;
        font-weight: 600;
        color: white;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
    }
    
    .time-picker-subtitle {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.8);
    }
    
    .time-picker-close {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        color: white;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .time-picker-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }
    
    .time-picker-body {
        padding: 24px;
        background: rgba(255, 255, 255, 0.95);
        max-height: 400px;
        overflow-y: auto;
    }
    
    .time-slots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    
    .time-slot-item {
        position: relative;
        padding: 16px 12px;
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid transparent;
        background: #f7fafc;
        min-height: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        user-select: none;
    }
    
    .time-slot-item.available {
        border-color: #e2e8f0;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    }
    
    .time-slot-item.available:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, #ebf8ff 0%, #e6fffa 100%);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
    }
    
    .time-slot-item.popular {
        background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%);
        border-color: #f6ad55;
    }
    
    .time-slot-item.selected {
        border-color: #48bb78;
        background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
        box-shadow: 0 8px 25px rgba(72, 187, 120, 0.3);
        transform: translateY(-3px);
    }
    
    .time-slot-item.unavailable {
        background: #f1f5f9;
        border-color: #e2e8f0;
        color: #a0aec0;
        cursor: not-allowed;
    }
    
    .time-display {
        font-weight: 600;
        font-size: 0.9rem;
        color: #2d3748;
    }
    
    .time-slot-item.unavailable .time-display {
        color: #a0aec0;
    }
    
    .popular-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #f6ad55;
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: 600;
    }
    
    .selected-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #48bb78;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
    }
    
    .unavailable-badge {
        position: absolute;
        bottom: 4px;
        left: 50%;
        transform: translateX(-50%);
        background: #f56565;
        color: white;
        font-size: 0.6rem;
        padding: 2px 6px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .time-picker-legend {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        color: #4a5568;
    }
    
    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
    }
    
    .legend-color.available {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
    }
    
    .legend-color.popular {
        background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%);
    }
    
    .legend-color.unavailable {
        background: #f1f5f9;
    }
    
    .time-picker-footer {
        padding: 20px 24px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(20px);
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    
    .time-picker-footer button {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
    }
    
    .btn-cancel {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }
    
    .btn-cancel:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    
    .btn-confirm {
        background: #a0aec0;
        color: white;
        cursor: not-allowed;
    }
    
    .btn-confirm.enabled {
        background: #48bb78;
        cursor: pointer;
    }
    
    .btn-confirm.enabled:hover {
        background: #38a169;
        transform: translateY(-2px);
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .time-picker-modal-content {
            width: 95%;
            max-height: 85vh;
        }
        
        .time-slots-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .time-slot-item {
            padding: 12px 8px;
            min-height: 50px;
        }
        
        .time-display {
            font-size: 0.8rem;
        }
        
        .time-picker-legend {
            gap: 12px;
        }
        
        .legend-item {
            font-size: 0.7rem;
        }
    }
`;

// Injetar CSS
function injectTimePickerCSS() {
    if (!document.getElementById('time-picker-styles')) {
        const style = document.createElement('style');
        style.id = 'time-picker-styles';
        style.textContent = timePickerCSS;
        document.head.appendChild(style);
    }
}

// Exportar para uso global
window.SmartTimePicker = SmartTimePicker;

// Auto-injetar CSS quando o script for carregado
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', injectTimePickerCSS);
} else {
    injectTimePickerCSS();
}

console.log('🕐 Smart Time Picker carregado e pronto para uso');
