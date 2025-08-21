/**
 * =========================================================
 * PROJETO BLUE - SISTEMA DE RESERVAS DE LIMPEZA DOMICILIAR
 * =========================================================
 * 
 * @file booking4.js
 * @description JavaScript otimizado para booking4.php
 * @version 2.1
 * @date 2025-08-04
 * 
 * MELHORIAS IMPLEMENTADAS:
 * - ES6+ moderno
 * - Performance otimizada
 * - Acessibilidade melhorada
 * - Touch-friendly
 * - Lazy loading
 */

'use strict';

/**
 * Aplicação principal de reservas
 */
class BookingApp {
    constructor() {
        this.config = window.BlueProject || {};
        this.elements = {};
        this.state = {
            isInitialized: false,
            currentStep: 'booking',
            formData: {},
            calculation: {
                subtotal: 0,
                discount: 0,
                total: 0
            }
        };
        
        // Throttling para eventos frequentes
        this.throttledEvents = new Map();
        
        // Performance monitoring
        this.performanceMarks = new Map();
    }

    /**
     * Inicialização da aplicação
     */
    init() {
        this.mark('app-init-start');
        
        try {
            this.cacheElements();
            this.setupEventListeners();
            this.setupAccessibility();
            this.setupTouchEnhancements();
            this.initializeLazyLoading();
            this.restoreFormState();
            
            this.state.isInitialized = true;
            this.mark('app-init-end');
            
            if (this.config.debug) {
                console.log('BookingApp initialized successfully');
                this.logPerformanceMetrics();
            }
        } catch (error) {
            console.error('Failed to initialize BookingApp:', error);
            this.handleInitializationError(error);
        }
    }

    /**
     * Cache de elementos DOM para performance
     */
    cacheElements() {
        const selectors = {
            // Formulário principal
            bookingForm: '#bookingForm',
            bookingContainer: '#bookingContainer',
            
            // Barra de reserva
            addressInput: '#address',
            recurrenceSelect: '#recurrence',
            calendarPreview: '#calendarPreview',
            executionDateInput: '#execution_date',
            timeWindowSelect: '#time_window',
            
            // Seções
            inclusionsSection: '#inclusionsSection',
            extrasSection: '#extrasGroup',
            preferencesSection: '#preferencesGroup',
            customerInfoSection: '#customerInfoSection',
            
            // Resumo
            summaryBar: '#summaryBar',
            summaryTotal: '#summaryTotal',
            summaryModal: '#summaryModal',
            openSummaryBtn: '#openSummaryBtn',
            closeSummaryModal: '#closeSummaryModal',
            
            // Elementos interativos
            quantityButtons: '[data-target]',
            preferenceInputs: '[name^="preferences"]',
            infoButtons: '.info-icon',
            
            // Campos de dados pessoais
            personalInfoInputs: '[data-field-type="personal-info"]'
        };

        Object.entries(selectors).forEach(([key, selector]) => {
            const elements = document.querySelectorAll(selector);
            this.elements[key] = elements.length === 1 ? elements[0] : elements;
        });
    }

    /**
     * Configuração de event listeners
     */
    setupEventListeners() {
        // Eventos de formulário
        if (this.elements.bookingForm) {
            this.elements.bookingForm.addEventListener('submit', this.handleFormSubmit.bind(this));
            this.elements.bookingForm.addEventListener('change', this.throttle(this.handleFormChange.bind(this), 300));
        }

        // Eventos de quantidade (inclusões e extras)
        if (this.elements.quantityButtons.length) {
            this.elements.quantityButtons.forEach(button => {
                button.addEventListener('click', this.handleQuantityChange.bind(this));
                // Touch events para melhor responsividade mobile
                button.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
            });
        }

        // Eventos de preferências
        if (this.elements.preferenceInputs.length) {
            this.elements.preferenceInputs.forEach(input => {
                input.addEventListener('change', this.handlePreferenceChange.bind(this));
                
                // Eventos específicos para cada tipo
                if (input.type === 'checkbox') {
                    input.addEventListener('change', this.handleCheckboxChange.bind(this));
                }
            });
        }

        // Eventos de informação (tooltips)
        if (this.elements.infoButtons.length) {
            this.elements.infoButtons.forEach(button => {
                button.addEventListener('click', this.handleInfoClick.bind(this));
                // Suporte para teclado
                button.addEventListener('keydown', this.handleInfoKeydown.bind(this));
            });
        }

        // Eventos de resumo
        if (this.elements.openSummaryBtn) {
            this.elements.openSummaryBtn.addEventListener('click', this.openSummaryModal.bind(this));
        }

        if (this.elements.closeSummaryModal) {
            this.elements.closeSummaryModal.addEventListener('click', this.closeSummaryModal.bind(this));
        }

        // Eventos de calendário
        if (this.elements.calendarPreview) {
            this.elements.calendarPreview.addEventListener('click', this.handleCalendarOpen.bind(this));
            this.elements.calendarPreview.addEventListener('keydown', this.handleCalendarKeydown.bind(this));
        }

        // Eventos de endereço
        if (this.elements.addressInput) {
            this.elements.addressInput.addEventListener('input', 
                this.throttle(this.handleAddressInput.bind(this), 500)
            );
        }

        // Eventos de dados pessoais
        if (this.elements.personalInfoInputs.length) {
            this.elements.personalInfoInputs.forEach(input => {
                input.addEventListener('blur', this.handlePersonalInfoBlur.bind(this));
                input.addEventListener('input', this.throttle(this.saveFormState.bind(this), 1000));
            });
        }

        // Eventos globais
        window.addEventListener('resize', this.throttle(this.handleResize.bind(this), 250));
        window.addEventListener('beforeunload', this.handleBeforeUnload.bind(this));
        
        // Eventos de teclado para acessibilidade
        document.addEventListener('keydown', this.handleGlobalKeydown.bind(this));
    }

    /**
     * Configurações de acessibilidade
     */
    setupAccessibility() {
        // ARIA live regions para anúncios dinâmicos
        this.createLiveRegion();
        
        // Melhorar labels e descrições
        this.enhanceFormLabels();
        
        // Foco gerenciado
        this.setupFocusManagement();
    }

    /**
     * Criar região live para anúncios
     */
    createLiveRegion() {
        if (!document.getElementById('live-region')) {
            const liveRegion = document.createElement('div');
            liveRegion.id = 'live-region';
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            liveRegion.className = 'sr-only';
            document.body.appendChild(liveRegion);
        }
    }

    /**
     * Anunciar mudanças para leitores de tela
     */
    announce(message) {
        const liveRegion = document.getElementById('live-region');
        if (liveRegion) {
            liveRegion.textContent = message;
            setTimeout(() => {
                liveRegion.textContent = '';
            }, 1000);
        }
    }

    /**
     * Melhorar labels do formulário
     */
    enhanceFormLabels() {
        // Adicionar descrições aria para campos complexos
        const complexFields = document.querySelectorAll('[data-field-type]');
        complexFields.forEach(field => {
            if (!field.getAttribute('aria-describedby')) {
                const description = this.getFieldDescription(field);
                if (description) {
                    const descId = `desc-${field.id || Math.random().toString(36).substr(2, 9)}`;
                    const descElement = document.createElement('div');
                    descElement.id = descId;
                    descElement.className = 'sr-only';
                    descElement.textContent = description;
                    field.parentNode.appendChild(descElement);
                    field.setAttribute('aria-describedby', descId);
                }
            }
        });
    }

    /**
     * Obter descrição para campos
     */
    getFieldDescription(field) {
        const fieldType = field.getAttribute('data-field-type');
        const descriptions = {
            'address': 'Enter your complete street address for service location',
            'recurrence': 'Choose how often you want the service repeated',
            'date': 'Select the date when you want the service to start',
            'time': 'Choose your preferred time window for the service',
            'quantity': 'Use plus and minus buttons to adjust quantity',
            'personal-info': 'This information is required for booking confirmation'
        };
        return descriptions[fieldType] || null;
    }

    /**
     * Criar skip links
     */
    /**
     * Configurar gerenciamento de foco
     */
    setupFocusManagement() {
        // Gerenciar foco em modais
        this.lastFocusedElement = null;
        
        // Trap focus em modais
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                const modal = document.querySelector('.modal-overlay:not(.hidden)');
                if (modal) {
                    this.trapFocus(e, modal);
                }
            }
        });
    }

    /**
     * Trap focus dentro de elemento
     */
    trapFocus(event, container) {
        const focusableElements = container.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];

        if (event.shiftKey) {
            if (document.activeElement === firstFocusable) {
                lastFocusable.focus();
                event.preventDefault();
            }
        } else {
            if (document.activeElement === lastFocusable) {
                firstFocusable.focus();
                event.preventDefault();
            }
        }
    }

    /**
     * Configurações para touch/mobile
     */
    setupTouchEnhancements() {
        // Aumentar área de toque para botões pequenos
        this.enhanceTouchTargets();
        
        // Gestos personalizados
        this.setupCustomGestures();
        
        // Feedback tátil
        this.setupHapticFeedback();
    }

    /**
     * Melhorar alvos de toque
     */
    enhanceTouchTargets() {
        const smallButtons = document.querySelectorAll('button');
        smallButtons.forEach(button => {
            const rect = button.getBoundingClientRect();
            if (rect.width < 44 || rect.height < 44) {
                button.style.minWidth = '44px';
                button.style.minHeight = '44px';
                button.style.display = 'flex';
                button.style.alignItems = 'center';
                button.style.justifyContent = 'center';
            }
        });
    }

    /**
     * Configurar gestos personalizados
     */
    setupCustomGestures() {
        let touchStartX = 0;
        let touchStartY = 0;

        document.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            if (!touchStartX || !touchStartY) return;

            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            const diffX = touchStartX - touchEndX;
            const diffY = touchStartY - touchEndY;

            // Swipe horizontal para navegação (futuro)
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    // Swipe left
                    this.handleSwipeLeft();
                } else {
                    // Swipe right
                    this.handleSwipeRight();
                }
            }

            touchStartX = 0;
            touchStartY = 0;
        }, { passive: true });
    }

    /**
     * Feedback tátil
     */
    setupHapticFeedback() {
        if ('vibrate' in navigator) {
            // Vibração sutil para ações importantes
            this.elements.bookingForm?.addEventListener('submit', () => {
                navigator.vibrate(50);
            });
        }
    }

    /**
     * Inicializar lazy loading
     */
    initializeLazyLoading() {
        if (this.config.performance?.enableLazyLoading) {
            // Lazy load para imagens
            const images = document.querySelectorAll('img[data-src]');
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });

                images.forEach(img => imageObserver.observe(img));
            }

            // Lazy load para componentes
            this.setupComponentLazyLoading();
        }
    }

    /**
     * Lazy loading de componentes
     */
    setupComponentLazyLoading() {
        const components = document.querySelectorAll('[data-component]');
        if ('IntersectionObserver' in window) {
            const componentObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const component = entry.target;
                        const componentType = component.dataset.component;
                        this.loadComponent(componentType, component);
                    }
                });
            }, { rootMargin: '50px' });

            components.forEach(component => componentObserver.observe(component));
        }
    }

    /**
     * Carregar componente específico
     */
    async loadComponent(type, element) {
        try {
            switch (type) {
                case 'calendar':
                    if (!window.CalendarComponent) {
                        await this.loadScript('assets/js/calendar.js');
                    }
                    break;
                case 'summary-modal':
                    if (!window.SummaryModal) {
                        await this.loadScript('assets/js/summary-modal.js');
                    }
                    break;
                // Adicionar mais componentes conforme necessário
            }
        } catch (error) {
            console.warn(`Failed to load component ${type}:`, error);
        }
    }

    /**
     * Carregar script dinamicamente
     */
    loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Restaurar estado do formulário
     */
    restoreFormState() {
        try {
            const savedState = localStorage.getItem('booking-form-state');
            if (savedState) {
                const state = JSON.parse(savedState);
                this.state.formData = state;
                this.populateFormFromState(state);
            }
        } catch (error) {
            console.warn('Failed to restore form state:', error);
        }
    }

    /**
     * Salvar estado do formulário
     */
    saveFormState() {
        try {
            const formData = new FormData(this.elements.bookingForm);
            const state = Object.fromEntries(formData.entries());
            this.state.formData = state;
            localStorage.setItem('booking-form-state', JSON.stringify(state));
        } catch (error) {
            console.warn('Failed to save form state:', error);
        }
    }

    /**
     * Popular formulário a partir do estado
     */
    populateFormFromState(state) {
        Object.entries(state).forEach(([name, value]) => {
            const field = document.querySelector(`[name="${name}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = value === '1' || value === 'on';
                } else {
                    field.value = value;
                }
            }
        });
    }

    /**
     * Manipuladores de eventos
     */
    handleFormSubmit(event) {
        event.preventDefault();
        this.mark('form-submit-start');
        
        if (this.validateForm()) {
            this.submitForm();
        } else {
            this.handleFormValidationError();
        }
    }

    handleFormChange(event) {
        this.saveFormState();
        this.updateCalculations();
        this.updateSummary();
    }

    handleQuantityChange(event) {
        const button = event.target;
        const targetId = button.dataset.target;
        const targetInput = document.getElementById(targetId);
        
        if (!targetInput) return;

        const isPlus = button.classList.contains('plus');
        const currentValue = parseInt(targetInput.value) || 0;
        const minValue = parseInt(targetInput.dataset.min) || 0;
        
        let newValue;
        if (isPlus) {
            newValue = currentValue + 1;
        } else {
            newValue = Math.max(minValue, currentValue - 1);
        }

        targetInput.value = newValue;
        
        // Atualizar display visual
        const displayElement = document.getElementById(targetId.replace('qty_', 'qty_display_'));
        if (displayElement) {
            displayElement.textContent = newValue;
        }

        // Anunciar mudança para leitores de tela
        const itemName = this.getItemNameFromButton(button);
        this.announce(`${itemName} quantity changed to ${newValue}`);

        // Trigger change event
        targetInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    handleTouchStart(event) {
        // Adicionar classe para feedback visual em touch
        event.target.classList.add('touch-active');
        setTimeout(() => {
            event.target.classList.remove('touch-active');
        }, 150);
    }

    handlePreferenceChange(event) {
        const input = event.target;
        const preferenceItem = input.closest('.preference-item');
        
        if (input.type === 'checkbox') {
            this.handleCheckboxChange(event);
        }
        
        // Salvar preferência
        this.saveFormState();
        this.updateCalculations();
    }

    handleCheckboxChange(event) {
        const checkbox = event.target;
        const note = checkbox.dataset.note;
        const extraFee = parseFloat(checkbox.dataset.extraFee) || 0;
        
        if (checkbox.checked && note) {
            this.showPreferenceNote(checkbox, note, extraFee);
        } else {
            this.hidePreferenceNote(checkbox);
        }
    }

    showPreferenceNote(checkbox, note, extraFee) {
        const preferenceItem = checkbox.closest('.preference-item');
        const noteElement = preferenceItem.querySelector('.preference-note');
        
        if (noteElement) {
            let message = note;
            if (extraFee > 0) {
                message += ` (Additional $${extraFee.toFixed(2)} fee applies)`;
            }
            
            noteElement.textContent = message;
            noteElement.style.display = 'block';
            noteElement.setAttribute('aria-live', 'polite');
        }
    }

    hidePreferenceNote(checkbox) {
        const preferenceItem = checkbox.closest('.preference-item');
        const noteElement = preferenceItem.querySelector('.preference-note');
        
        if (noteElement) {
            noteElement.style.display = 'none';
        }
    }

    handleInfoClick(event) {
        const button = event.target;
        const title = button.dataset.title;
        const description = button.dataset.description;
        
        this.showInfoModal(title, description);
    }

    handleInfoKeydown(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.handleInfoClick(event);
        }
    }

    handleCalendarOpen(event) {
        // Delegado para calendar.js
        if (window.CalendarComponent) {
            window.CalendarComponent.open();
        }
    }

    handleCalendarKeydown(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.handleCalendarOpen(event);
        }
    }

    handleAddressInput(event) {
        // Delegado para address.js
        if (window.AddressAutocomplete) {
            window.AddressAutocomplete.handleInput(event);
        }
    }

    handlePersonalInfoBlur(event) {
        const input = event.target;
        this.validateField(input);
    }

    handleResize(event) {
        // Reajustar layouts responsivos
        this.adjustResponsiveElements();
    }

    handleBeforeUnload(event) {
        // Salvar estado antes de sair
        this.saveFormState();
    }

    handleGlobalKeydown(event) {
        // Atalhos de teclado globais
        if (event.ctrlKey || event.metaKey) {
            switch (event.key) {
                case 's':
                    event.preventDefault();
                    this.saveFormState();
                    this.announce('Form state saved');
                    break;
                case 'Enter':
                    if (event.target.closest('#summaryModal')) {
                        event.preventDefault();
                        this.handleFormSubmit(event);
                    }
                    break;
            }
        }
        
        // Escape para fechar modais
        if (event.key === 'Escape') {
            this.closeAllModals();
        }
    }

    handleSwipeLeft() {
        // Implementar navegação por swipe (futuro)
        if (this.config.debug) {
            console.log('Swipe left detected');
        }
    }

    handleSwipeRight() {
        // Implementar navegação por swipe (futuro)
        if (this.config.debug) {
            console.log('Swipe right detected');
        }
    }

    /**
     * Métodos de validação
     */
    validateForm() {
        const requiredFields = this.elements.bookingForm.querySelectorAll('[required]');
        let isValid = true;
        const errors = [];

        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
                errors.push(this.getFieldLabel(field));
            }
        });

        if (!isValid) {
            this.showValidationErrors(errors);
        }

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;

        // Validação básica de required
        if (field.hasAttribute('required') && !value) {
            isValid = false;
        }

        // Validações específicas por tipo
        switch (field.type) {
            case 'email':
                if (value && !this.isValidEmail(value)) {
                    isValid = false;
                }
                break;
            case 'tel':
                if (value && !this.isValidPhone(value)) {
                    isValid = false;
                }
                break;
        }

        // Adicionar/remover classes de erro
        if (isValid) {
            field.classList.remove('error');
            field.setAttribute('aria-invalid', 'false');
        } else {
            field.classList.add('error');
            field.setAttribute('aria-invalid', 'true');
        }

        return isValid;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        return phoneRegex.test(phone.replace(/\s+/g, ''));
    }

    /**
     * Métodos de UI
     */
    showInfoModal(title, description) {
        // Implementação simplificada - pode ser expandida
        alert(`${title}\n\n${description}`);
    }

    openSummaryModal() {
        this.lastFocusedElement = document.activeElement;
        
        if (this.elements.summaryModal) {
            this.elements.summaryModal.classList.remove('hidden');
            this.elements.summaryModal.setAttribute('aria-hidden', 'false');
            
            // Focar no primeiro elemento focável do modal
            const firstFocusable = this.elements.summaryModal.querySelector(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (firstFocusable) {
                firstFocusable.focus();
            }
            
            // Atualizar conteúdo do modal
            this.updateSummaryModalContent();
        }
    }

    closeSummaryModal() {
        if (this.elements.summaryModal) {
            this.elements.summaryModal.classList.add('hidden');
            this.elements.summaryModal.setAttribute('aria-hidden', 'true');
            
            // Restaurar foco
            if (this.lastFocusedElement) {
                this.lastFocusedElement.focus();
            }
        }
    }

    closeAllModals() {
        const modals = document.querySelectorAll('.modal-overlay:not(.hidden)');
        modals.forEach(modal => {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
        });
        
        if (this.lastFocusedElement) {
            this.lastFocusedElement.focus();
        }
    }

    updateSummaryModalContent() {
        // Delegado para summary-modal.js
        if (window.SummaryModal) {
            window.SummaryModal.update();
        }
    }

    /**
     * Métodos de cálculo
     */
    updateCalculations() {
        // Delegado para pricing-calculator.js
        if (window.PricingCalculator) {
            this.state.calculation = window.PricingCalculator.calculate();
        }
    }

    updateSummary() {
        if (this.elements.summaryTotal) {
            this.elements.summaryTotal.textContent = `$${this.state.calculation.total.toFixed(2)}`;
        }
    }

    /**
     * Métodos de utilitário
     */
    throttle(func, wait) {
        const key = func.toString();
        if (this.throttledEvents.has(key)) {
            return this.throttledEvents.get(key);
        }
        
        let timeout;
        const throttledFunc = (...args) => {
            if (!timeout) {
                timeout = setTimeout(() => {
                    timeout = null;
                    func.apply(this, args);
                }, wait);
            }
        };
        
        this.throttledEvents.set(key, throttledFunc);
        return throttledFunc;
    }

    mark(name) {
        if ('performance' in window && 'mark' in performance) {
            performance.mark(name);
            this.performanceMarks.set(name, performance.now());
        }
    }

    logPerformanceMetrics() {
        if (this.performanceMarks.has('app-init-start') && this.performanceMarks.has('app-init-end')) {
            const initTime = this.performanceMarks.get('app-init-end') - this.performanceMarks.get('app-init-start');
            console.log(`App initialization took ${initTime.toFixed(2)}ms`);
        }
    }

    getItemNameFromButton(button) {
        const itemCard = button.closest('.item-card, .extra-item');
        if (itemCard) {
            const nameElement = itemCard.querySelector('.item-card__title, .extra-name');
            return nameElement ? nameElement.textContent.trim() : 'Item';
        }
        return 'Item';
    }

    getFieldLabel(field) {
        const label = document.querySelector(`label[for="${field.id}"]`);
        if (label) {
            return label.textContent.trim();
        }
        return field.placeholder || field.name || 'Field';
    }

    showValidationErrors(errors) {
        const message = `Please complete the following required fields:\n${errors.join('\n')}`;
        alert(message);
        this.announce(`Form validation failed. ${errors.length} errors found.`);
    }

    handleFormValidationError() {
        // Scroll para o primeiro campo com erro
        const firstError = this.elements.bookingForm.querySelector('.error, [aria-invalid="true"]');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }

    adjustResponsiveElements() {
        // Ajustar elementos para diferentes tamanhos de tela
        const isMobile = window.innerWidth < 768;
        
        if (isMobile) {
            document.body.classList.add('mobile-view');
        } else {
            document.body.classList.remove('mobile-view');
        }
    }

    submitForm() {
        this.mark('form-submit-process-start');
        
        // Implementação do envio do formulário
        // Por enquanto, apenas simula o processo
        
        this.announce('Form submitted successfully');
        
        if (this.config.debug) {
            console.log('Form submission data:', this.state.formData);
        }
        
        this.mark('form-submit-process-end');
    }

    handleInitializationError(error) {
        // Fallback gracioso em caso de erro
        console.error('BookingApp initialization failed:', error);
        
        // Tentar funcionalidade básica sem JavaScript avançado
        this.setupBasicFallbacks();
    }

    setupBasicFallbacks() {
        // Funcionalidade básica para quando JS avançado falha
        const form = document.getElementById('bookingForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                // Validação básica
                const requiredFields = form.querySelectorAll('[required]');
                let hasErrors = false;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#ef4444';
                        hasErrors = true;
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (hasErrors) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        }
    }
}

/**
 * Inicialização global
 */
window.BookingApp = new BookingApp();

// Auto-inicialização se DOM já estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.BookingApp.init();
    });
} else {
    window.BookingApp.init();
}
