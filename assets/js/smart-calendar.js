/**
 * Smart Booking Calendar - Integrated with Blue Cleaning Services
 * Shows only available days based on professional availability
 * Implements 48-hour minimum booking rule and modal display
 */

class SmartBookingCalendar {
    constructor(options = {}) {
        this.containerId = options.containerId || 'smart-calendar';
        this.serviceId = options.serviceId || null;
        this.currentDate = new Date();
        this.selectedDate = null;
        this.availableDays = [];
        this.slotsDetails = {};
        this.isLoading = false;
        this.onDateSelected = options.onDateSelected || null;
        this.isModal = options.modal !== false; // Default to modal mode
        this.modalElement = null;
        
        // 48-hour minimum booking rule
        this.minimumHours = 48;
        this.minimumBookingDate = this.calculateMinimumDate();
        
        // Month names for display
        this.monthNames = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        
        this.weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        
        this.init();
    }
    
    calculateMinimumDate() {
        const now = new Date();
        const minimumDate = new Date(now.getTime() + (this.minimumHours * 60 * 60 * 1000));
        return minimumDate;
    }
    
    async init() {
        if (this.isModal) {
            this.createModal();
        } else {
            const container = document.getElementById(this.containerId);
            if (!container) {
                console.error(`Smart Calendar: Container #${this.containerId} not found`);
                return;
            }
        }
        
        if (this.serviceId) {
            await this.loadAvailableDays();
        }
        
        this.render();
        this.bindEvents();
    }
    
    createModal() {
        // Create modal structure
        this.modalElement = document.createElement('div');
        this.modalElement.className = 'smart-calendar-modal';
        this.modalElement.innerHTML = `
            <div class="smart-calendar-modal-content">
                <div id="${this.containerId}" class="smart-calendar-wrapper"></div>
            </div>
        `;
        
        document.body.appendChild(this.modalElement);
        
        // Close modal on overlay click
        this.modalElement.addEventListener('click', (e) => {
            if (e.target === this.modalElement) {
                this.closeModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modalElement.classList.contains('active')) {
                this.closeModal();
            }
        });
    }
    
    openModal() {
        if (this.modalElement) {
            this.modalElement.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    closeModal() {
        if (this.modalElement) {
            this.modalElement.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    async loadAvailableDays() {
        if (this.isLoading || !this.serviceId) {
            console.log('LoadAvailableDays skipped:', { isLoading: this.isLoading, serviceId: this.serviceId });
            return;
        }
        
        this.isLoading = true;
        this.showLoadingState();
        
        try {
            const month = this.currentDate.getMonth() + 1;
            const year = this.currentDate.getFullYear();
            
            console.log(`🔍 Loading availability for service ${this.serviceId}, ${month}/${year}`);
            
            const url = `api/get_available_days.php?service_id=${this.serviceId}&month=${month}&year=${year}`;
            console.log(`📡 API URL: ${url}`);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
            
            console.log(`📈 Response status: ${response.status} ${response.statusText}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('📊 API Response:', data);
            
            if (data.success) {
                this.availableDays = data.available_days || [];
                this.slotsDetails = data.slots_details || {};
                
                console.log(`✅ Loaded ${this.availableDays.length} available days:`, this.availableDays);
                console.log('📅 Available days array:', this.availableDays);
                console.log('🎯 Slots details:', this.slotsDetails);
            } else {
                throw new Error(data.error || 'Unknown API error');
            }
            
        } catch (error) {
            console.error('❌ Error loading available days:', error);
            this.showError('Não foi possível carregar a disponibilidade. Verifique sua conexão.');
            this.availableDays = [];
            this.slotsDetails = {};
        } finally {
            this.isLoading = false;
        }
    }
    
    render() {
        const container = document.getElementById(this.containerId);
        if (!container) return;
        
        if (this.isLoading) {
            return; // Loading state already shown
        }
        
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        
        let html = `
            <div class="smart-calendar-container">
                ${this.isModal ? '<button type="button" class="calendar-close-btn" data-action="close-modal">×</button>' : ''}
                <div class="calendar-header">
                    <button type="button" class="calendar-nav-btn prev" data-action="prev-month">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h3 class="calendar-title">
                        ${this.monthNames[month]} ${year}
                    </h3>
                    <button type="button" class="calendar-nav-btn next" data-action="next-month">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="calendar-weekdays">
                    ${this.weekdays.map(day => `<div class="calendar-weekday">${day}</div>`).join('')}
                </div>
                
                <div class="calendar-grid">
        `;
        
        // Empty cells for alignment
        for (let i = 0; i < firstDay; i++) {
            html += '<div class="calendar-day empty"></div>';
        }
        
        console.log('🎨 Starting render...', {
            year,
            month: month + 1, 
            daysInMonth,
            availableDays: this.availableDays,
            availableDaysCount: this.availableDays.length
        });

        // Calendar days
        for (let day = 1; day <= daysInMonth; day++) {
            const currentDayDate = new Date(year, month, day);
            const isToday = this.isSameDay(currentDayDate, today);
            const isPast = currentDayDate < today && !isToday;
            const isTooSoon = this.isTooSoonForBooking(currentDayDate);
            const isAvailable = this.availableDays.includes(day) && !isPast && !isTooSoon;
            const isSelected = this.selectedDate && this.isSameDay(currentDayDate, this.selectedDate);
            
            // Debug specific days
            if (day <= 20) {
                console.log(`Day ${day}:`, {
                    isToday,
                    isPast,
                    isTooSoon,
                    inAvailableDays: this.availableDays.includes(day),
                    isAvailable,
                    minimumDate: this.minimumBookingDate.toDateString()
                });
            }
            
            let classes = ['calendar-day'];
            let title = '';
            
            if (isSelected) {
                classes.push('selected');
            } else if (isToday) {
                classes.push('today');
                if (isAvailable) classes.push('available');
            } else if (isPast) {
                classes.push('past');
            } else if (isTooSoon) {
                classes.push('too-soon');
                title = 'Agendamento deve ser feito com 48h de antecedência';
            } else if (isAvailable) {
                classes.push('available');
            } else {
                classes.push('unavailable');
            }
            
            const slotsInfo = this.slotsDetails[day] || null;
            const slotsText = slotsInfo ? 
                              `${slotsInfo.available_capacity} slot${slotsInfo.available_capacity > 1 ? 's' : ''}` : 
                              '';
            
            const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            html += `
                <div class="${classes.join(' ')}" 
                     data-day="${day}" 
                     data-date="${dateString}"
                     ${title ? `title="${title}"` : ''}
                     ${isAvailable ? 'role="button" tabindex="0" aria-label="Selecionar ' + day + ' de ' + this.monthNames[month] + '"' : 'aria-label="' + day + ' de ' + this.monthNames[month] + ' - indisponível"'}>
                    <span class="day-number">${day}</span>
                    ${slotsText ? `<small class="slots-indicator">${slotsText}</small>` : ''}
                </div>
            `;
        }
        
        html += `
                </div>
                
                <div class="calendar-legend">
                    <div class="legend-item">
                        <div class="legend-color available"></div>
                        <span>Disponível</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color unavailable"></div>
                        <span>Indisponível</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color today"></div>
                        <span>Hoje</span>
                    </div>
                    <div class="legend-item">
                        <span style="color: #f56565; font-size: 0.75rem;">⏱ Mínimo 48h antecedência</span>
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    }
    
    bindEvents() {
        const container = document.getElementById(this.containerId);
        if (!container) return;
        
        // Remove existing event listeners to prevent duplicates
        container.replaceWith(container.cloneNode(true));
        const newContainer = document.getElementById(this.containerId);
        
        // Month navigation and close button
        newContainer.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="prev-month"]')) {
                this.previousMonth();
            } else if (e.target.closest('[data-action="next-month"]')) {
                this.nextMonth();
            } else if (e.target.closest('[data-action="close-modal"]')) {
                this.closeModal();
            }
        });
        
        // Day selection
        newContainer.addEventListener('click', (e) => {
            const dayElement = e.target.closest('.calendar-day.available:not(.past):not(.too-soon)');
            if (dayElement) {
                const dateString = dayElement.dataset.date;
                this.selectDate(dateString);
            }
        });
        
        // Keyboard navigation
        newContainer.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                const dayElement = e.target.closest('.calendar-day.available:not(.past):not(.too-soon)');
                if (dayElement) {
                    e.preventDefault();
                    const dateString = dayElement.dataset.date;
                    this.selectDate(dateString);
                }
            }
        });
    }
    
    isTooSoonForBooking(date) {
        return date < this.minimumBookingDate;
    }
    
    async previousMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() - 1);
        await this.loadAvailableDays();
        this.render();
        this.bindEvents();
    }
    
    async nextMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() + 1);
        await this.loadAvailableDays();
        this.render();
        this.bindEvents();
    }
    
    selectDate(dateString) {
        const selectedDate = new Date(dateString + 'T00:00:00');
        
        // Validar regra de 48 horas
        if (this.isTooSoonForBooking(selectedDate)) {
            console.warn('Data muito próxima - mínimo 48 horas necessárias');
            
            // Mostrar feedback visual se modal
            if (this.isModal) {
                this.showDateError('Agendamento deve ser feito com 48h de antecedência');
            }
            return;
        }
        
        this.selectedDate = selectedDate;
        this.render(); // Re-render to show selection
        
        // Trigger callback
        if (this.onDateSelected && typeof this.onDateSelected === 'function') {
            this.onDateSelected({
                date: this.selectedDate,
                dateString: dateString,
                formattedDate: this.formatDate(this.selectedDate)
            });
        }
        
        // Trigger custom event
        const event = new CustomEvent('smartCalendarDateSelected', {
            detail: {
                date: this.selectedDate,
                dateString: dateString,
                formattedDate: this.formatDate(this.selectedDate),
                serviceId: this.serviceId
            }
        });
        
        document.dispatchEvent(event);
        
        console.log('Date selected:', dateString);
    }
    
    showDateError(message) {
        // Criar um feedback temporário de erro
        const errorEl = document.createElement('div');
        errorEl.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(245, 101, 101, 0.95);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            backdrop-filter: blur(10px);
        `;
        errorEl.textContent = message;
        
        document.body.appendChild(errorEl);
        
        // Remover após 3 segundos
        setTimeout(() => {
            if (errorEl.parentNode) {
                errorEl.parentNode.removeChild(errorEl);
            }
        }, 3000);
    }
    
    // Utility methods
    isSameDay(date1, date2) {
        return date1.getDate() === date2.getDate() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getFullYear() === date2.getFullYear();
    }
    
    formatDate(date) {
        return date.toLocaleDateString('pt-BR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    showLoadingState() {
        const container = document.getElementById(this.containerId);
        if (container) {
            container.innerHTML = `
                <div class="calendar-loading">
                    <div class="loading-spinner"></div>
                    <p>Carregando disponibilidade...</p>
                </div>
            `;
        }
    }
    
    showError(message) {
        const container = document.getElementById(this.containerId);
        if (container) {
            container.innerHTML = `
                <div class="calendar-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>${message}</p>
                    <button type="button" onclick="location.reload()" class="retry-button">
                        Tentar Novamente
                    </button>
                </div>
            `;
        }
    }
    
    // Public methods
    updateService(serviceId) {
        if (this.serviceId !== serviceId) {
            this.serviceId = serviceId;
            this.selectedDate = null;
            this.availableDays = [];
            this.slotsDetails = {};
            
            if (serviceId) {
                this.loadAvailableDays().then(() => {
                    this.render();
                    this.bindEvents();
                });
            } else {
                this.render();
                this.bindEvents();
            }
        }
    }
    
    getSelectedDate() {
        return this.selectedDate;
    }
    
    getSelectedDateString() {
        if (!this.selectedDate) return null;
        
        const year = this.selectedDate.getFullYear();
        const month = String(this.selectedDate.getMonth() + 1).padStart(2, '0');
        const day = String(this.selectedDate.getDate()).padStart(2, '0');
        
        return `${year}-${month}-${day}`;
    }
}

// Auto-initialization when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Smart Calendar: DOM loaded, initializing...');
    
    // Global calendar instance
    window.smartCalendar = null;
    
    // Service selection handler
    const serviceSelect = document.getElementById('service-select');
    if (serviceSelect) {
        serviceSelect.addEventListener('change', function() {
            const serviceId = parseInt(this.value);
            
            console.log('Service changed to:', serviceId);
            
            if (serviceId && !isNaN(serviceId)) {
                if (window.smartCalendar) {
                    window.smartCalendar.updateService(serviceId);
                } else {
                    window.smartCalendar = new SmartBookingCalendar({
                        containerId: 'smart-calendar',
                        serviceId: serviceId,
                        onDateSelected: function(data) {
                            console.log('Calendar date selected:', data);
                            
                            // Update hidden field if exists
                            const hiddenInput = document.getElementById('booking-date');
                            if (hiddenInput) {
                                hiddenInput.value = data.dateString;
                            }
                            
                            // Show date selection feedback
                            const feedbackElement = document.getElementById('selected-date-display');
                            if (feedbackElement) {
                                feedbackElement.textContent = data.formattedDate;
                                feedbackElement.style.display = 'block';
                            }
                            
                            // Show next step (time selection)
                            const timeSection = document.getElementById('time-selection');
                            if (timeSection) {
                                timeSection.style.display = 'block';
                                loadAvailableTimes(data.serviceId, data.dateString);
                            }
                        }
                    });
                }
                
                // Show calendar container
                const calendarContainer = document.getElementById('calendar-section');
                if (calendarContainer) {
                    calendarContainer.style.display = 'block';
                }
                
            } else {
                // Hide calendar if no service selected
                const calendarContainer = document.getElementById('calendar-section');
                if (calendarContainer) {
                    calendarContainer.style.display = 'none';
                }
                
                // Hide time selection
                const timeSection = document.getElementById('time-selection');
                if (timeSection) {
                    timeSection.style.display = 'none';
                }
            }
        });
    }
    
    // Global event listener for calendar selections
    document.addEventListener('smartCalendarDateSelected', function(event) {
        console.log('Global calendar event:', event.detail);
        
        // You can add any global date selection logic here
        // For example, updating other parts of the form
    });
});

// Helper function to load available times (will be called from calendar)
async function loadAvailableTimes(serviceId, dateString) {
    const timeContainer = document.getElementById('available-times-container');
    if (!timeContainer) return;
    
    try {
        timeContainer.innerHTML = '<div class="loading">Carregando horários...</div>';
        
        const response = await fetch(
            `api/get_available_times.php?service_id=${serviceId}&date=${dateString}`
        );
        
        const data = await response.json();
        
        if (data.success && data.available_times) {
            let timesHtml = '<div class="time-slots-grid">';
            
            data.available_times.forEach(timeSlot => {
                timesHtml += `
                    <button type="button" class="time-slot" 
                            data-time="${timeSlot.time}" 
                            data-service="${serviceId}"
                            data-date="${dateString}">
                        <span class="time">${timeSlot.display_time}</span>
                        <small class="capacity">${timeSlot.total_capacity} vaga${timeSlot.total_capacity > 1 ? 's' : ''}</small>
                    </button>
                `;
            });
            
            timesHtml += '</div>';
            timeContainer.innerHTML = timesHtml;
            
            // Add event listeners to time slots
            timeContainer.querySelectorAll('.time-slot').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove selection from other buttons
                    timeContainer.querySelectorAll('.time-slot').forEach(btn => {
                        btn.classList.remove('selected');
                    });
                    
                    // Select this button
                    this.classList.add('selected');
                    
                    // Update hidden field
                    const timeInput = document.getElementById('booking-time');
                    if (timeInput) {
                        timeInput.value = this.dataset.time;
                    }
                    
                    console.log('Time selected:', this.dataset.time);
                });
            });
            
        } else {
            timeContainer.innerHTML = '<div class="no-times">Nenhum horário disponível para esta data.</div>';
        }
        
    } catch (error) {
        console.error('Error loading times:', error);
        timeContainer.innerHTML = '<div class="error">Erro ao carregar horários. Tente novamente.</div>';
    }
}
