/**
 * =========================================================
 * BLUE PROJECT V2 - BOOKING SYSTEM JAVASCRIPT
 * =========================================================
 * 
 * @file booking5.js
 * @description JavaScript principal com funcionalidades avançadas
 * @version 2.0
 * @date 2025-08-05
 */

(function() {
    'use strict';

    // =========================================================
    // CONFIGURAÇÕES GLOBAIS
    // =========================================================
    const CONFIG = {
        // URLs da API
        API_ENDPOINTS: {
            checkAvailability: '/api/check-availability.php',
            validateDiscount: '/api/validate-discount.php',
            calculatePricing: '/api/calculate-pricing.php',
            createPaymentIntent: '/api/create-payment-intent.php',
            processBooking: '/api/process-booking.php'
        },
        
        // Configurações de tempo
        MINIMUM_BOOKING_HOURS: 48,
        DEBOUNCE_DELAY: 300,
        
        // Configurações de validação
        VALIDATION: {
            email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
            phone: /^[\+]?[\d\s\-\(\)]{10,}$/,
            postcode: /^\d{4}$/
        },
        
        // Configurações de recorrência
        RECURRENCE_CONFIG: window.BlueProject?.recurrenceConfig || {},
        
        // Debug
        DEBUG: window.BlueProject?.debug === 'true' || false
    };

    // =========================================================
    // UTILITÁRIOS
    // =========================================================
    const Utils = {
        /**
         * Debounce function calls
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Format currency
         */
        formatCurrency(amount, currency = 'AUD') {
            return new Intl.NumberFormat('en-AU', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 2
            }).format(amount);
        },

        /**
         * Format date
         */
        formatDate(date, options = {}) {
            const defaultOptions = {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            return new Intl.DateTimeFormat('en-AU', { ...defaultOptions, ...options }).format(date);
        },

        /**
         * Get minimum booking date (48h from now)
         */
        getMinimumBookingDate() {
            const now = new Date();
            now.setHours(now.getHours() + CONFIG.MINIMUM_BOOKING_HOURS);
            return now;
        },

        /**
         * Check if date is valid for booking
         */
        isValidBookingDate(date) {
            const minDate = this.getMinimumBookingDate();
            return date >= minDate;
        },

        /**
         * Log debug messages
         */
        log(...args) {
            if (CONFIG.DEBUG) {
                console.log('[BookingApp]', ...args);
            }
        },

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} animate-fade-in`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
    };

    // =========================================================
    // GESTÃO DE ESTADO
    // =========================================================
    const State = {
        booking: {
            // Dados básicos
            address: '',
            postcode: '',
            latitude: null,
            longitude: null,
            recurrence: 'one-time',
            execution_date: '',
            time_window: '',
            
            // Inclusões e extras
            inclusions: new Map(),
            extras: new Map(),
            preferences: new Map(),
            
            // Dados pessoais
            customer: {},
            
            // Preços
            pricing: {
                subtotal: 0,
                discount: 0,
                total: 0
            },
            
            // Contrato
            contract: {
                duration: null,
                totalOccurrences: 0,
                nextChargeDate: null
            },
            
            // Estado da UI
            ui: {
                currentStep: 1,
                isLoading: false,
                availabilityChecked: false
            }
        },

        /**
         * Update state
         */
        update(path, value) {
            const keys = path.split('.');
            let current = this.booking;
            
            for (let i = 0; i < keys.length - 1; i++) {
                if (!current[keys[i]]) {
                    current[keys[i]] = {};
                }
                current = current[keys[i]];
            }
            
            current[keys[keys.length - 1]] = value;
            this.notifyChange(path, value);
        },

        /**
         * Get state value
         */
        get(path) {
            const keys = path.split('.');
            let current = this.booking;
            
            for (const key of keys) {
                if (current[key] === undefined) {
                    return undefined;
                }
                current = current[key];
            }
            
            return current;
        },

        /**
         * Notify state change
         */
        notifyChange(path, value) {
            Utils.log('State changed:', path, value);
            
            // Trigger relevant updates based on path
            if (path.startsWith('pricing')) {
                PricingCalculator.updateDisplay();
            }
            
            if (path === 'recurrence' || path === 'execution_date') {
                ContractManager.updateContractOptions();
            }
        }
    };

    // =========================================================
    // VALIDAÇÃO DE 48H
    // =========================================================
    const TimeValidation = {
        /**
         * Initialize time validation
         */
        init() {
            this.bindEvents();
            this.updateCalendarAvailability();
        },

        /**
         * Bind events
         */
        bindEvents() {
            const recurrenceSelect = document.getElementById('recurrence');
            const executionDateInput = document.getElementById('execution_date');

            if (recurrenceSelect) {
                recurrenceSelect.addEventListener('change', () => {
                    this.handleRecurrenceChange();
                });
            }

            if (executionDateInput) {
                executionDateInput.addEventListener('change', () => {
                    this.validateSelectedDate();
                });
            }
        },

        /**
         * Handle recurrence change
         */
        handleRecurrenceChange() {
            const recurrence = document.getElementById('recurrence').value;
            State.update('recurrence', recurrence);
            
            // Show recurrence info modal
            this.showRecurrenceInfo(recurrence);
            
            // Update calendar
            this.updateCalendarAvailability();
        },

        /**
         * Show recurrence information
         */
        showRecurrenceInfo(recurrence) {
            const config = CONFIG.RECURRENCE_CONFIG[recurrence];
            if (!config) return;

            const modal = document.getElementById('recurrenceModal');
            const message = document.getElementById('recurrenceModalMessage');
            
            if (modal && message) {
                let messageText = `You selected ${config.name} service.`;
                
                if (config.discount_percentage > 0) {
                    messageText += ` You'll receive a ${config.discount_percentage}% discount for choosing recurring service.`;
                }
                
                messageText += ` Payment will be charged 48 hours before each service execution.`;
                
                if (recurrence !== 'one-time') {
                    messageText += ` Cancellation is free up to 48 hours before execution. After that, cancellation fees may apply.`;
                }

                message.textContent = messageText;
                modal.style.display = 'flex';
            }
        },

        /**
         * Update calendar availability
         */
        updateCalendarAvailability() {
            const minDate = Utils.getMinimumBookingDate();
            const calendar = document.getElementById('calendarGrid');
            
            if (!calendar) return;

            // Mark unavailable dates
            const dayElements = calendar.querySelectorAll('.calendar-day');
            dayElements.forEach(dayEl => {
                const dayDate = new Date(dayEl.dataset.date);
                
                if (!Utils.isValidBookingDate(dayDate)) {
                    dayEl.classList.add('disabled');
                    dayEl.title = 'Minimum 48 hours advance booking required';
                } else {
                    dayEl.classList.remove('disabled');
                }
            });

            // Update date preview
            this.updateDatePreview();
        },

        /**
         * Update date preview with validation
         */
        updateDatePreview() {
            const preview = document.getElementById('calendarPreview');
            const selectedDate = State.get('execution_date');
            
            if (!preview) return;

            if (!selectedDate) {
                preview.textContent = 'Choose a date';
                preview.classList.remove('status-available', 'status-unavailable');
                return;
            }

            const date = new Date(selectedDate);
            const isValid = Utils.isValidBookingDate(date);
            
            preview.textContent = Utils.formatDate(date);
            
            if (isValid) {
                preview.classList.add('status-available');
                preview.classList.remove('status-unavailable');
            } else {
                preview.classList.add('status-unavailable');
                preview.classList.remove('status-available');
            }
        },

        /**
         * Validate selected date
         */
        validateSelectedDate() {
            const selectedDate = State.get('execution_date');
            if (!selectedDate) return;

            const date = new Date(selectedDate);
            const isValid = Utils.isValidBookingDate(date);
            
            if (!isValid) {
                Utils.showNotification(
                    'Selected date must be at least 48 hours from now. Please choose a different date.',
                    'error'
                );
                State.update('execution_date', '');
                this.updateDatePreview();
                return false;
            }

            // Check availability with API
            this.checkAvailability(date);
            return true;
        },

        /**
         * Check availability via API
         */
        async checkAvailability(date) {
            const timeWindow = State.get('time_window');
            if (!timeWindow) return;

            try {
                State.update('ui.isLoading', true);
                
                const response = await fetch(CONFIG.API_ENDPOINTS.checkAvailability, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        date: date.toISOString().split('T')[0],
                        time_window: timeWindow,
                        service_id: window.BlueProject?.serviceId || 1
                    })
                });

                const result = await response.json();
                
                if (result.available) {
                    Utils.showNotification('Date and time available!', 'success');
                    State.update('ui.availabilityChecked', true);
                } else {
                    Utils.showNotification(
                        result.message || 'Selected date/time is not available. Please choose another slot.',
                        'warning'
                    );
                    State.update('ui.availabilityChecked', false);
                }
                
            } catch (error) {
                Utils.log('Availability check failed:', error);
                Utils.showNotification('Unable to check availability. Please try again.', 'error');
            } finally {
                State.update('ui.isLoading', false);
            }
        }
    };

    // =========================================================
    // CALCULADORA DE PREÇOS
    // =========================================================
    const PricingCalculator = {
        /**
         * Initialize pricing calculator
         */
        init() {
            this.bindEvents();
            this.calculatePricing();
        },

        /**
         * Bind events
         */
        bindEvents() {
            // Listen for quantity changes
            document.addEventListener('click', (e) => {
                if (e.target.matches('.plus, .minus')) {
                    setTimeout(() => this.calculatePricing(), 50);
                }
            });

            // Listen for preference changes
            document.addEventListener('change', (e) => {
                if (e.target.matches('.preference-checkbox, .preference-select')) {
                    setTimeout(() => this.calculatePricing(), 50);
                }
            });

            // Listen for recurrence changes
            document.addEventListener('change', (e) => {
                if (e.target.id === 'recurrence') {
                    setTimeout(() => this.calculatePricing(), 50);
                }
            });
        },

        /**
         * Calculate total pricing
         */
        calculatePricing() {
            let subtotal = 0;

            // Calculate inclusions
            const inclusions = document.querySelectorAll('[data-inclusion-id]');
            inclusions.forEach(inclusion => {
                const price = parseFloat(inclusion.dataset.price || 0);
                const qtyInput = inclusion.querySelector('input[type="hidden"]');
                const qty = parseInt(qtyInput?.value || 0);
                subtotal += price * qty;
            });

            // Calculate extras
            const extras = document.querySelectorAll('[data-extra-id]');
            extras.forEach(extra => {
                const price = parseFloat(extra.dataset.price || 0);
                const qtyInput = extra.querySelector('input[type="hidden"]');
                const qty = parseInt(qtyInput?.value || 0);
                subtotal += price * qty;
            });

            // Calculate preferences with fees
            const preferences = document.querySelectorAll('.preference-checkbox:checked');
            preferences.forEach(pref => {
                const fee = parseFloat(pref.dataset.extraFee || 0);
                subtotal += fee;
            });

            // Apply recurrence discount
            const recurrence = State.get('recurrence') || 'one-time';
            const discountPercentage = CONFIG.RECURRENCE_CONFIG[recurrence]?.discount_percentage || 0;
            const discount = subtotal * (discountPercentage / 100);

            // Final calculation WITHOUT any tax
            const afterDiscount = subtotal - discount;
            const total = afterDiscount; // No tax calculations

            // Update state
            State.update('pricing.subtotal', subtotal);
            State.update('pricing.discount', discount);
            State.update('pricing.total', total); // Total without tax

            // Update display
            this.updateDisplay();
        },

        /**
         * Update pricing display
         */
        updateDisplay() {
            const pricing = State.get('pricing');
            
            // Update summary bar
            const summaryTotal = document.getElementById('summaryTotal');
            if (summaryTotal) {
                summaryTotal.textContent = Utils.formatCurrency(pricing.total);
            }

            // Update modal pricing
            const totalPriceLabel = document.getElementById('totalPriceLabel');
            if (totalPriceLabel) {
                totalPriceLabel.textContent = Utils.formatCurrency(pricing.total);
            }

            // Update hidden input for form submission
            const baseTotalInput = document.getElementById('baseTotalInput');
            if (baseTotalInput) {
                baseTotalInput.value = pricing.total.toFixed(2);
            }
        }
    };

    // =========================================================
    // GERENCIADOR DE CONTRATO
    // =========================================================
    const ContractManager = {
        /**
         * Initialize contract manager
         */
        init() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents() {
            const contractSelect = document.getElementById('contractDuration');
            if (contractSelect) {
                contractSelect.addEventListener('change', () => {
                    this.handleContractChange();
                });
            }
        },

        /**
         * Update contract options based on recurrence
         */
        updateContractOptions() {
            const recurrence = State.get('recurrence');
            const contractSelect = document.getElementById('contractDuration');
            const contractSection = document.getElementById('contractDurationSection');
            
            if (!contractSelect || !contractSection) return;

            // Clear existing options
            contractSelect.innerHTML = '<option value="">Select duration</option>';

            // Hide for one-time services
            if (recurrence === 'one-time') {
                contractSection.style.display = 'none';
                return;
            }

            // Show for recurring services
            contractSection.style.display = 'block';

            const config = CONFIG.RECURRENCE_CONFIG[recurrence];
            if (!config) return;

            // Generate options based on recurrence type
            const minDuration = config.minimum_duration;
            const maxDuration = config.maximum_duration;

            for (let i = minDuration; i <= maxDuration; i++) {
                const option = document.createElement('option');
                option.value = i;
                
                if (recurrence === 'weekly') {
                    option.textContent = `${i} week${i > 1 ? 's' : ''}`;
                } else if (recurrence === 'fortnightly') {
                    option.textContent = `${i} fortnight${i > 1 ? 's' : ''} (${i * 2} weeks)`;
                } else if (recurrence === 'monthly') {
                    option.textContent = `${i} month${i > 1 ? 's' : ''}`;
                }
                
                contractSelect.appendChild(option);
            }
        },

        /**
         * Handle contract duration change
         */
        handleContractChange() {
            const contractSelect = document.getElementById('contractDuration');
            const duration = parseInt(contractSelect.value);
            const recurrence = State.get('recurrence');
            
            if (!duration || !recurrence) return;

            // Calculate total occurrences
            let totalOccurrences = 0;
            if (recurrence === 'weekly') {
                totalOccurrences = duration;
            } else if (recurrence === 'fortnightly') {
                totalOccurrences = duration;
            } else if (recurrence === 'monthly') {
                totalOccurrences = duration;
            }

            // Calculate next charge date
            const executionDate = new Date(State.get('execution_date'));
            const nextChargeDate = new Date(executionDate);
            nextChargeDate.setHours(nextChargeDate.getHours() - 48);

            // Update state
            State.update('contract.duration', duration);
            State.update('contract.totalOccurrences', totalOccurrences);
            State.update('contract.nextChargeDate', nextChargeDate);

            // Update preview
            this.updateContractPreview();
        },

        /**
         * Update contract preview
         */
        updateContractPreview() {
            const preview = document.getElementById('contractPreview');
            const totalOccurrencesSpan = document.getElementById('totalOccurrences');
            const contractPeriodSpan = document.getElementById('contractPeriod');
            const nextChargeDateSpan = document.getElementById('nextChargeDate');
            const billingFrequencySpan = document.getElementById('billingFrequency');
            
            if (!preview) return;

            const contract = State.get('contract');
            const recurrence = State.get('recurrence');
            const config = CONFIG.RECURRENCE_CONFIG[recurrence];
            
            if (contract.duration && contract.totalOccurrences) {
                preview.style.display = 'block';
                
                if (totalOccurrencesSpan) {
                    totalOccurrencesSpan.textContent = contract.totalOccurrences;
                }
                
                if (contractPeriodSpan) {
                    contractPeriodSpan.textContent = `${contract.duration} ${recurrence === 'weekly' ? 'weeks' : recurrence === 'fortnightly' ? 'fortnights' : 'months'}`;
                }
                
                if (nextChargeDateSpan && contract.nextChargeDate) {
                    nextChargeDateSpan.textContent = Utils.formatDate(contract.nextChargeDate);
                }
                
                if (billingFrequencySpan && config) {
                    billingFrequencySpan.textContent = config.billing_frequency;
                }
            } else {
                preview.style.display = 'none';
            }
        }
    };

    // =========================================================
    // SISTEMA DE DESCONTO
    // =========================================================
    const DiscountSystem = {
        /**
         * Initialize discount system
         */
        init() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents() {
            const applyBtn = document.getElementById('applyDiscountBtnModal');
            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    this.applyDiscount();
                });
            }

            const discountInput = document.getElementById('discountCodeModal');
            if (discountInput) {
                discountInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.applyDiscount();
                    }
                });
            }
        },

        /**
         * Apply discount code
         */
        async applyDiscount() {
            const discountInput = document.getElementById('discountCodeModal');
            const code = discountInput?.value.trim();
            
            if (!code) {
                Utils.showNotification('Please enter a discount code', 'warning');
                return;
            }

            try {
                const response = await fetch(CONFIG.API_ENDPOINTS.validateDiscount, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        code: code,
                        subtotal: State.get('pricing.subtotal')
                    })
                });

                const result = await response.json();
                
                if (result.valid) {
                    Utils.showNotification(`Discount applied: ${result.description}`, 'success');
                    State.update('pricing.discount', State.get('pricing.discount') + result.discount_amount);
                    PricingCalculator.calculatePricing();
                    
                    // Hide input and show applied state
                    discountInput.disabled = true;
                    discountInput.value = `${code} (Applied)`;
                    
                } else {
                    Utils.showNotification(result.message || 'Invalid discount code', 'error');
                }
                
            } catch (error) {
                Utils.log('Discount validation failed:', error);
                Utils.showNotification('Unable to validate discount code. Please try again.', 'error');
            }
        }
    };

    // =========================================================
    // VALIDAÇÃO DE FORMULÁRIO
    // =========================================================
    const FormValidation = {
        /**
         * Initialize form validation
         */
        init() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents() {
            const form = document.getElementById('bookingForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    if (!this.validateForm()) {
                        e.preventDefault();
                    }
                });
            }

            // Real-time validation
            const inputs = document.querySelectorAll('input[required], select[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
            });
        },

        /**
         * Validate entire form
         */
        validateForm() {
            const errors = [];

            // Check required fields
            const requiredFields = document.querySelectorAll('input[required], select[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    errors.push(`${this.getFieldLabel(field)} is required`);
                }
            });

            // Check email format
            const emailField = document.getElementById('email');
            if (emailField && emailField.value && !CONFIG.VALIDATION.email.test(emailField.value)) {
                errors.push('Please enter a valid email address');
            }

            // Check phone format
            const phoneField = document.getElementById('phone');
            if (phoneField && phoneField.value && !CONFIG.VALIDATION.phone.test(phoneField.value)) {
                errors.push('Please enter a valid phone number');
            }

            // Check date validation
            if (!Utils.isValidBookingDate(new Date(State.get('execution_date')))) {
                errors.push('Booking date must be at least 48 hours from now');
            }

            // Check terms agreement
            const termsCheckbox = document.getElementById('agreedToTerms');
            if (termsCheckbox && !termsCheckbox.checked) {
                errors.push('You must agree to the terms and conditions');
            }

            // Show errors
            if (errors.length > 0) {
                Utils.showNotification(errors.join('\n'), 'error');
                return false;
            }

            return true;
        },

        /**
         * Validate individual field
         */
        validateField(field) {
            const value = field.value.trim();
            let isValid = true;
            let message = '';

            if (field.required && !value) {
                isValid = false;
                message = `${this.getFieldLabel(field)} is required`;
            } else if (field.type === 'email' && value && !CONFIG.VALIDATION.email.test(value)) {
                isValid = false;
                message = 'Please enter a valid email address';
            } else if (field.type === 'tel' && value && !CONFIG.VALIDATION.phone.test(value)) {
                isValid = false;
                message = 'Please enter a valid phone number';
            }

            // Update field appearance
            if (isValid) {
                field.classList.remove('error');
            } else {
                field.classList.add('error');
                Utils.showNotification(message, 'error');
            }

            return isValid;
        },

        /**
         * Get field label
         */
        getFieldLabel(field) {
            const label = document.querySelector(`label[for="${field.id}"]`);
            return label ? label.textContent.trim() : field.placeholder || field.name || 'Field';
        }
    };

    // =========================================================
    // INICIALIZAÇÃO PRINCIPAL
    // =========================================================
    const BookingApp = {
        /**
         * Initialize the application
         */
        init() {
            Utils.log('Initializing BookingApp v2.0...');

            // Initialize components
            TimeValidation.init();
            PricingCalculator.init();
            ContractManager.init();
            DiscountSystem.init();
            FormValidation.init();

            // Set up global event listeners
            this.bindGlobalEvents();

            // Initial calculations
            PricingCalculator.calculatePricing();

            Utils.log('BookingApp initialized successfully!');
        },

        /**
         * Bind global events
         */
        bindGlobalEvents() {
            // Modal controls
            this.bindModalEvents();

            // Calendar events
            this.bindCalendarEvents();

            // Counter events
            this.bindCounterEvents();
        },

        /**
         * Bind modal events
         */
        bindModalEvents() {
            // Summary modal
            const openSummaryBtn = document.getElementById('openSummaryBtn');
            const closeSummaryBtn = document.getElementById('closeSummaryModal');
            const summaryModal = document.getElementById('summaryModal');

            if (openSummaryBtn) {
                openSummaryBtn.addEventListener('click', () => {
                    summaryModal?.classList.remove('hidden');
                });
            }

            if (closeSummaryBtn) {
                closeSummaryBtn.addEventListener('click', () => {
                    summaryModal?.classList.add('hidden');
                });
            }

            // Terms modal
            const openTermsBtn = document.getElementById('openTermsBtn');
            const closeTermsBtn = document.getElementById('closeTermsModal');
            const termsModal = document.getElementById('termsModal');

            if (openTermsBtn) {
                openTermsBtn.addEventListener('click', () => {
                    termsModal?.classList.remove('hidden');
                });
            }

            if (closeTermsBtn) {
                closeTermsBtn.addEventListener('click', () => {
                    termsModal?.classList.add('hidden');
                });
            }

            // Close modals on outside click
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal-overlay')) {
                    e.target.classList.add('hidden');
                }
            });
        },

        /**
         * Bind calendar events
         */
        bindCalendarEvents() {
            const calendarPreview = document.getElementById('calendarPreview');
            const calendarPopover = document.getElementById('calendarPopover');

            if (calendarPreview) {
                calendarPreview.addEventListener('click', () => {
                    calendarPopover?.classList.toggle('show');
                });
            }

            // Close calendar on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.my-calendar')) {
                    calendarPopover?.classList.remove('show');
                }
            });
        },

        /**
         * Bind counter events
         */
        bindCounterEvents() {
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('plus') || e.target.classList.contains('minus')) {
                    const targetId = e.target.dataset.target;
                    const input = document.getElementById(targetId);
                    const display = document.getElementById(targetId.replace('_qty_', '_display_'));
                    
                    if (!input || !display) return;

                    let currentValue = parseInt(input.value || 0);
                    const minValue = parseInt(input.dataset.min || 0);
                    
                    if (e.target.classList.contains('plus')) {
                        currentValue++;
                    } else if (e.target.classList.contains('minus') && currentValue > minValue) {
                        currentValue--;
                    }

                    input.value = currentValue;
                    display.textContent = currentValue;

                    // Trigger pricing recalculation
                    setTimeout(() => PricingCalculator.calculatePricing(), 50);
                }
            });
        }
    };

    // =========================================================
    // EXPORT TO GLOBAL SCOPE
    // =========================================================
    window.BookingApp = BookingApp;
    window.BookingState = State;
    window.BookingUtils = Utils;

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => BookingApp.init());
    } else {
        BookingApp.init();
    }

})();
