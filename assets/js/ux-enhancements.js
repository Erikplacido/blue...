/**
 * UX Enhancement System - Blue Cleaning Services
 * Sistema completo de melhorias de experiência do usuário
 */

// Loading States Manager
class LoadingStateManager {
    constructor() {
        this.activeLoaders = new Set();
        this.loadingQueue = new Map();
        this.defaultOptions = {
            overlay: true,
            blur: true,
            message: 'Loading...',
            timeout: 30000,
            spinner: 'dots'
        };
        
        this.initializeStyles();
        this.createLoadingElements();
    }
    
    initializeStyles() {
        const styles = `
            .blue-loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                backdrop-filter: blur(3px);
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .blue-loading-overlay.active {
                opacity: 1;
            }
            
            .blue-loading-content {
                background: white;
                border-radius: 12px;
                padding: 2rem;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 300px;
                transform: scale(0.8);
                transition: transform 0.3s ease;
            }
            
            .blue-loading-overlay.active .blue-loading-content {
                transform: scale(1);
            }
            
            /* Spinners */
            .blue-spinner {
                margin: 0 auto 1rem;
            }
            
            .blue-spinner.dots {
                width: 50px;
                height: 50px;
                position: relative;
            }
            
            .blue-spinner.dots::before,
            .blue-spinner.dots::after {
                content: '';
                position: absolute;
                width: 8px;
                height: 8px;
                background: #2563eb;
                border-radius: 50%;
                animation: loading-dots 1.5s infinite;
            }
            
            .blue-spinner.dots::before {
                left: 8px;
                animation-delay: -0.3s;
            }
            
            .blue-spinner.dots::after {
                right: 8px;
                animation-delay: -0.6s;
            }
            
            @keyframes loading-dots {
                0%, 80%, 100% { 
                    transform: scale(0);
                    opacity: 0.5;
                }
                40% { 
                    transform: scale(1);
                    opacity: 1;
                }
            }
            
            .blue-spinner.ring {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f4f6;
                border-top: 4px solid #2563eb;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .blue-spinner.pulse {
                width: 50px;
                height: 50px;
                background: #2563eb;
                border-radius: 50%;
                animation: pulse 1.5s ease-in-out infinite;
            }
            
            @keyframes pulse {
                0% { transform: scale(0.8); opacity: 1; }
                50% { transform: scale(1.2); opacity: 0.5; }
                100% { transform: scale(0.8); opacity: 1; }
            }
            
            /* Skeleton Loaders */
            .skeleton {
                background: linear-gradient(
                    90deg,
                    #f3f4f6 25%,
                    #e5e7eb 50%,
                    #f3f4f6 75%
                );
                background-size: 200% 100%;
                animation: skeleton-loading 1.5s infinite;
            }
            
            @keyframes skeleton-loading {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
            
            .skeleton-text {
                height: 1em;
                border-radius: 4px;
                margin-bottom: 0.5em;
            }
            
            .skeleton-text.short { width: 60%; }
            .skeleton-text.medium { width: 80%; }
            .skeleton-text.long { width: 100%; }
            
            .skeleton-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
            }
            
            .skeleton-card {
                height: 200px;
                border-radius: 8px;
                margin-bottom: 1rem;
            }
            
            /* Inline Loading States */
            .blue-loading-button {
                position: relative;
                overflow: hidden;
            }
            
            .blue-loading-button.loading {
                color: transparent;
                pointer-events: none;
            }
            
            .blue-loading-button.loading::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 20px;
                height: 20px;
                margin: -10px 0 0 -10px;
                border: 2px solid transparent;
                border-top: 2px solid white;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            /* Progress Bar */
            .blue-progress-bar {
                width: 100%;
                height: 4px;
                background: #f3f4f6;
                border-radius: 2px;
                overflow: hidden;
                margin: 1rem 0;
            }
            
            .blue-progress-fill {
                height: 100%;
                background: #2563eb;
                border-radius: 2px;
                transition: width 0.3s ease;
                position: relative;
            }
            
            .blue-progress-fill.indeterminate {
                width: 30% !important;
                animation: progress-indeterminate 2s infinite;
            }
            
            @keyframes progress-indeterminate {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(400%); }
            }
            
            /* Toast Notifications */
            .blue-toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10001;
                pointer-events: none;
            }
            
            .blue-toast {
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                margin-bottom: 10px;
                padding: 1rem;
                min-width: 300px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                pointer-events: auto;
                border-left: 4px solid #2563eb;
            }
            
            .blue-toast.show {
                transform: translateX(0);
            }
            
            .blue-toast.success {
                border-left-color: #10b981;
            }
            
            .blue-toast.error {
                border-left-color: #ef4444;
            }
            
            .blue-toast.warning {
                border-left-color: #f59e0b;
            }
            
            /* Micro Interactions */
            .blue-button {
                transition: all 0.2s ease;
                transform: translateY(0);
            }
            
            .blue-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            }
            
            .blue-button:active {
                transform: translateY(0);
            }
            
            .blue-card {
                transition: all 0.3s ease;
                cursor: pointer;
            }
            
            .blue-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            }
            
            /* Form States */
            .form-field {
                position: relative;
                margin-bottom: 1.5rem;
            }
            
            .form-field.loading .form-input {
                padding-right: 3rem;
            }
            
            .form-field.loading::after {
                content: '';
                position: absolute;
                top: 50%;
                right: 1rem;
                width: 20px;
                height: 20px;
                margin-top: -10px;
                border: 2px solid #e5e7eb;
                border-top: 2px solid #2563eb;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            .form-field.success .form-input {
                border-color: #10b981;
            }
            
            .form-field.error .form-input {
                border-color: #ef4444;
            }
            
            .form-field.success::after {
                content: '✓';
                position: absolute;
                top: 50%;
                right: 1rem;
                margin-top: -10px;
                color: #10b981;
                font-weight: bold;
            }
            
            .form-field.error::after {
                content: '✕';
                position: absolute;
                top: 50%;
                right: 1rem;
                margin-top: -10px;
                color: #ef4444;
                font-weight: bold;
            }
            
            /* Responsive Adjustments */
            @media (max-width: 768px) {
                .blue-loading-content {
                    margin: 0 1rem;
                    padding: 1.5rem;
                }
                
                .blue-toast-container {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                }
                
                .blue-toast {
                    min-width: auto;
                }
            }
        `;
        
        if (!document.getElementById('blue-loading-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'blue-loading-styles';
            styleSheet.textContent = styles;
            document.head.appendChild(styleSheet);
        }
    }
    
    createLoadingElements() {
        // Create main loading overlay
        this.overlay = document.createElement('div');
        this.overlay.className = 'blue-loading-overlay';
        this.overlay.innerHTML = `
            <div class="blue-loading-content">
                <div class="blue-spinner dots"></div>
                <div class="loading-message">Loading...</div>
            </div>
        `;
        document.body.appendChild(this.overlay);
        
        // Create toast container
        this.toastContainer = document.createElement('div');
        this.toastContainer.className = 'blue-toast-container';
        document.body.appendChild(this.toastContainer);
    }
    
    show(id = 'default', options = {}) {
        const config = { ...this.defaultOptions, ...options };
        
        if (this.activeLoaders.has(id)) {
            return; // Already showing
        }
        
        this.activeLoaders.add(id);
        
        // Update overlay content
        const spinner = this.overlay.querySelector('.blue-spinner');
        const message = this.overlay.querySelector('.loading-message');
        
        spinner.className = `blue-spinner ${config.spinner}`;
        message.textContent = config.message;
        
        // Show overlay
        this.overlay.classList.add('active');
        
        if (config.blur) {
            document.body.style.filter = 'blur(1px)';
        }
        
        // Set timeout
        if (config.timeout) {
            setTimeout(() => {
                this.hide(id);
            }, config.timeout);
        }
    }
    
    hide(id = 'default') {
        this.activeLoaders.delete(id);
        
        if (this.activeLoaders.size === 0) {
            this.overlay.classList.remove('active');
            document.body.style.filter = '';
        }
    }
    
    hideAll() {
        this.activeLoaders.clear();
        this.overlay.classList.remove('active');
        document.body.style.filter = '';
    }
    
    // Button Loading State
    setButtonLoading(button, loading = true) {
        if (loading) {
            button.classList.add('loading');
            button.setAttribute('data-original-text', button.textContent);
            button.disabled = true;
        } else {
            button.classList.remove('loading');
            const originalText = button.getAttribute('data-original-text');
            if (originalText) {
                button.textContent = originalText;
            }
            button.disabled = false;
        }
    }
    
    // Toast Notifications
    showToast(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `blue-toast ${type}`;
        toast.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; font-size: 1.2em; cursor: pointer; padding: 0; margin-left: 1rem;">×</button>
            </div>
        `;
        
        this.toastContainer.appendChild(toast);
        
        // Show toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Auto remove
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }, duration);
    }
    
    // Progress Bar
    createProgressBar(container, options = {}) {
        const progressBar = document.createElement('div');
        progressBar.className = 'blue-progress-bar';
        progressBar.innerHTML = `
            <div class="blue-progress-fill" style="width: 0%"></div>
        `;
        
        container.appendChild(progressBar);
        
        return {
            setProgress: (percent) => {
                const fill = progressBar.querySelector('.blue-progress-fill');
                fill.style.width = `${Math.min(100, Math.max(0, percent))}%`;
            },
            setIndeterminate: (indeterminate = true) => {
                const fill = progressBar.querySelector('.blue-progress-fill');
                if (indeterminate) {
                    fill.classList.add('indeterminate');
                } else {
                    fill.classList.remove('indeterminate');
                }
            },
            remove: () => {
                progressBar.remove();
            }
        };
    }
    
    // Skeleton Loaders
    createSkeleton(container, type = 'text', count = 3) {
        const skeletonContainer = document.createElement('div');
        
        switch (type) {
            case 'text':
                for (let i = 0; i < count; i++) {
                    const skeleton = document.createElement('div');
                    skeleton.className = `skeleton skeleton-text ${i % 3 === 0 ? 'short' : i % 3 === 1 ? 'medium' : 'long'}`;
                    skeletonContainer.appendChild(skeleton);
                }
                break;
                
            case 'card':
                for (let i = 0; i < count; i++) {
                    const skeleton = document.createElement('div');
                    skeleton.className = 'skeleton skeleton-card';
                    skeletonContainer.appendChild(skeleton);
                }
                break;
                
            case 'avatar':
                const skeleton = document.createElement('div');
                skeleton.className = 'skeleton skeleton-avatar';
                skeletonContainer.appendChild(skeleton);
                break;
        }
        
        container.appendChild(skeletonContainer);
        
        return {
            remove: () => {
                skeletonContainer.remove();
            }
        };
    }
}

// Enhanced Form Handler with Loading States
class FormHandler {
    constructor() {
        this.loadingManager = new LoadingStateManager();
    }
    
    async submitForm(form, options = {}) {
        const submitButton = form.querySelector('[type="submit"]');
        const formFields = form.querySelectorAll('.form-field');
        
        try {
            // Set loading states
            this.loadingManager.setButtonLoading(submitButton, true);
            
            // Clear previous states
            formFields.forEach(field => {
                field.classList.remove('success', 'error', 'loading');
            });
            
            // Validate form
            const validation = await this.validateForm(form);
            if (!validation.valid) {
                this.showValidationErrors(validation.errors);
                return { success: false, errors: validation.errors };
            }
            
            // Show form loading state
            if (options.showProgress) {
                this.loadingManager.show('form-submit', {
                    message: options.loadingMessage || 'Submitting...'
                });
            }
            
            // Submit form
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method,
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.loadingManager.showToast(
                    result.message || 'Form submitted successfully!',
                    'success'
                );
                
                // Mark fields as successful
                formFields.forEach(field => {
                    field.classList.add('success');
                });
                
                if (options.resetOnSuccess) {
                    setTimeout(() => form.reset(), 1000);
                }
                
                if (options.redirectOnSuccess && result.redirectUrl) {
                    setTimeout(() => {
                        window.location.href = result.redirectUrl;
                    }, 1500);
                }
            } else {
                this.loadingManager.showToast(
                    result.message || 'An error occurred',
                    'error'
                );
                
                if (result.errors) {
                    this.showValidationErrors(result.errors);
                }
            }
            
            return result;
            
        } catch (error) {
            console.error('Form submission error:', error);
            this.loadingManager.showToast(
                'Network error. Please try again.',
                'error'
            );
            
            return { success: false, error: error.message };
            
        } finally {
            this.loadingManager.setButtonLoading(submitButton, false);
            this.loadingManager.hide('form-submit');
        }
    }
    
    async validateForm(form) {
        const errors = {};
        let valid = true;
        
        const fields = form.querySelectorAll('[required], [data-validation]');
        
        for (const field of fields) {
            const fieldContainer = field.closest('.form-field');
            
            if (fieldContainer) {
                fieldContainer.classList.add('loading');
            }
            
            // Simulate async validation delay
            await new Promise(resolve => setTimeout(resolve, 100));
            
            const fieldErrors = this.validateField(field);
            
            if (fieldContainer) {
                fieldContainer.classList.remove('loading');
            }
            
            if (fieldErrors.length > 0) {
                valid = false;
                errors[field.name] = fieldErrors;
                
                if (fieldContainer) {
                    fieldContainer.classList.add('error');
                }
            } else if (fieldContainer) {
                fieldContainer.classList.add('success');
            }
        }
        
        return { valid, errors };
    }
    
    validateField(field) {
        const errors = [];
        const value = field.value.trim();
        
        // Required validation
        if (field.hasAttribute('required') && !value) {
            errors.push('This field is required');
        }
        
        // Email validation
        if (field.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            errors.push('Please enter a valid email address');
        }
        
        // Phone validation
        if (field.type === 'tel' && value && !/^[\+]?[\d\s\-\(\)]{10,}$/.test(value)) {
            errors.push('Please enter a valid phone number');
        }
        
        // Custom validation
        const customValidation = field.getAttribute('data-validation');
        if (customValidation && value) {
            switch (customValidation) {
                case 'postcode':
                    if (!/^\d{4}$/.test(value)) {
                        errors.push('Please enter a valid Australian postcode');
                    }
                    break;
                    
                case 'strong-password':
                    if (value.length < 8 || !/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(value)) {
                        errors.push('Password must be at least 8 characters with uppercase, lowercase and number');
                    }
                    break;
            }
        }
        
        return errors;
    }
    
    showValidationErrors(errors) {
        Object.keys(errors).forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                const fieldContainer = field.closest('.form-field');
                if (fieldContainer) {
                    fieldContainer.classList.add('error');
                    
                    // Show error message
                    let errorElement = fieldContainer.querySelector('.error-message');
                    if (!errorElement) {
                        errorElement = document.createElement('div');
                        errorElement.className = 'error-message';
                        errorElement.style.cssText = 'color: #ef4444; font-size: 0.875rem; margin-top: 0.25rem;';
                        fieldContainer.appendChild(errorElement);
                    }
                    errorElement.textContent = errors[fieldName][0];
                }
            }
        });
    }
}

// Page Transition Manager
class PageTransitionManager {
    constructor() {
        this.loadingManager = new LoadingStateManager();
        this.setupPageTransitions();
    }
    
    setupPageTransitions() {
        // Intercept internal links
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && this.shouldInterceptLink(link)) {
                e.preventDefault();
                this.navigateTo(link.href);
            }
        });
        
        // Handle browser back/forward
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.page) {
                this.loadPage(e.state.page, false);
            }
        });
    }
    
    shouldInterceptLink(link) {
        return (
            link.hostname === window.location.hostname &&
            !link.hasAttribute('download') &&
            !link.hasAttribute('target') &&
            !link.href.includes('#') &&
            link.getAttribute('data-no-transition') !== 'true'
        );
    }
    
    async navigateTo(url) {
        try {
            // Show loading
            this.loadingManager.show('page-transition', {
                message: 'Loading page...',
                spinner: 'ring'
            });
            
            // Load new page
            await this.loadPage(url, true);
            
        } catch (error) {
            console.error('Navigation error:', error);
            this.loadingManager.showToast('Failed to load page', 'error');
            window.location.href = url; // Fallback to normal navigation
        } finally {
            this.loadingManager.hide('page-transition');
        }
    }
    
    async loadPage(url, pushState = true) {
        const response = await fetch(url);
        const html = await response.text();
        
        // Parse the new page
        const parser = new DOMParser();
        const newDoc = parser.parseFromString(html, 'text/html');
        
        // Update page content
        document.title = newDoc.title;
        document.body.innerHTML = newDoc.body.innerHTML;
        
        // Update URL
        if (pushState) {
            history.pushState({ page: url }, newDoc.title, url);
        }
        
        // Reinitialize components
        this.reinitializeComponents();
    }
    
    reinitializeComponents() {
        // Reinitialize any JavaScript components
        if (window.BlueCalendar) {
            window.BlueCalendar.init();
        }
        
        if (window.ChatWidget) {
            window.ChatWidget.init();
        }
        
        // Trigger custom event
        window.dispatchEvent(new CustomEvent('pageLoaded'));
    }
}

// Initialize UX enhancements
document.addEventListener('DOMContentLoaded', () => {
    // Initialize loading manager
    window.LoadingManager = new LoadingStateManager();
    
    // Initialize form handler
    window.FormHandler = new FormHandler();
    
    // Initialize page transitions
    window.PageTransitionManager = new PageTransitionManager();
    
    // Setup automatic form handling
    document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await window.FormHandler.submitForm(form, {
                showProgress: true,
                resetOnSuccess: form.hasAttribute('data-reset-on-success'),
                redirectOnSuccess: form.hasAttribute('data-redirect-on-success')
            });
        });
    });
    
    // Add hover effects to buttons and cards
    document.querySelectorAll('button, .btn').forEach(button => {
        if (!button.classList.contains('blue-button')) {
            button.classList.add('blue-button');
        }
    });
    
    document.querySelectorAll('.card, [class*="card"]').forEach(card => {
        if (!card.classList.contains('blue-card')) {
            card.classList.add('blue-card');
        }
    });
    
    console.log('Blue Cleaning UX enhancements loaded');
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        LoadingStateManager,
        FormHandler,
        PageTransitionManager
    };
}
