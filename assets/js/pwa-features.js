/**
 * Enhanced PWA Features
 * Blue Cleaning Services - Advanced Web App Capabilities
 */

// Background Fetch API Implementation
class BackgroundFetchManager {
    constructor() {
        this.isSupported = 'serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype;
        this.init();
    }

    async init() {
        if (!this.isSupported) {
            console.warn('Background Fetch not supported');
            return;
        }

        // Register for background sync
        const registration = await navigator.serviceWorker.ready;
        
        // Listen for background fetch events
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data.type === 'BACKGROUND_FETCH_SUCCESS') {
                this.handleBackgroundFetchSuccess(event.data);
            } else if (event.data.type === 'BACKGROUND_FETCH_FAIL') {
                this.handleBackgroundFetchFail(event.data);
            }
        });
    }

    async scheduleDataSync(tag, data) {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            if ('backgroundFetch' in registration) {
                await registration.backgroundFetch.fetch(tag, `/api/sync/${tag}`, {
                    icons: [
                        {
                            src: '/assets/icons/icon-256x256.png',
                            sizes: '256x256',
                            type: 'image/png'
                        }
                    ],
                    title: 'Sincronizando dados...',
                    downloadTotal: 1000000 // 1MB estimate
                });
                
                console.log('✅ Background fetch scheduled:', tag);
                return { success: true, tag };
            } else {
                // Fallback to background sync
                await registration.sync.register(tag);
                console.log('✅ Background sync scheduled:', tag);
                return { success: true, tag };
            }
        } catch (error) {
            console.error('❌ Background fetch failed:', error);
            return { success: false, error: error.message };
        }
    }

    handleBackgroundFetchSuccess(data) {
        // Show success notification
        this.showNotification('Sincronização concluída', {
            body: 'Seus dados foram sincronizados com sucesso',
            icon: '/assets/icons/success-icon.png',
            tag: 'sync-success'
        });

        // Update UI if needed
        document.dispatchEvent(new CustomEvent('backgroundSyncSuccess', {
            detail: data
        }));
    }

    handleBackgroundFetchFail(data) {
        console.error('Background fetch failed:', data);
        
        // Show error notification
        this.showNotification('Erro na sincronização', {
            body: 'Não foi possível sincronizar os dados. Tentaremos novamente.',
            icon: '/assets/icons/error-icon.png',
            tag: 'sync-error'
        });
    }

    async showNotification(title, options) {
        const registration = await navigator.serviceWorker.ready;
        await registration.showNotification(title, {
            badge: '/assets/icons/badge-72x72.png',
            vibrate: [200, 100, 200],
            ...options
        });
    }
}

// Web Share API Implementation
class WebShareManager {
    constructor() {
        this.isSupported = navigator.share || navigator.canShare;
        this.init();
    }

    init() {
        if (!this.isSupported) {
            console.warn('Web Share API not supported');
            return;
        }

        // Add share buttons to relevant elements
        this.addShareButtons();
        
        // Handle share target (when app is shared to)
        this.handleShareTarget();
    }

    async share(data) {
        if (!this.isSupported) {
            this.fallbackShare(data);
            return;
        }

        try {
            if (navigator.canShare && !navigator.canShare(data)) {
                throw new Error('Data not shareable');
            }

            await navigator.share(data);
            console.log('✅ Content shared successfully');
            
            // Track sharing event
            this.trackShareEvent(data);
            
            return { success: true };
        } catch (error) {
            console.log('Share cancelled or failed:', error);
            this.fallbackShare(data);
            return { success: false, error: error.message };
        }
    }

    fallbackShare(data) {
        // Create share modal or copy to clipboard
        const shareUrl = data.url || window.location.href;
        const shareText = data.text || data.title || 'Confira este conteúdo';
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(`${shareText} - ${shareUrl}`).then(() => {
                this.showShareSuccess('Link copiado para a área de transferência!');
            });
        } else {
            // Open share dialog
            this.openShareDialog(data);
        }
    }

    openShareDialog(data) {
        const modal = document.createElement('div');
        modal.className = 'share-modal';
        modal.innerHTML = `
            <div class="share-modal-content">
                <div class="share-header">
                    <h3>Compartilhar</h3>
                    <button class="close-btn" onclick="this.closest('.share-modal').remove()">×</button>
                </div>
                <div class="share-options">
                    <button onclick="this.shareVia('whatsapp', '${encodeURIComponent(data.url || window.location.href)}', '${encodeURIComponent(data.text || data.title || '')}')">
                        <img src="/assets/icons/whatsapp.svg" alt="WhatsApp">
                        WhatsApp
                    </button>
                    <button onclick="this.shareVia('telegram', '${encodeURIComponent(data.url || window.location.href)}', '${encodeURIComponent(data.text || data.title || '')}')">
                        <img src="/assets/icons/telegram.svg" alt="Telegram">
                        Telegram
                    </button>
                    <button onclick="this.shareVia('email', '${encodeURIComponent(data.url || window.location.href)}', '${encodeURIComponent(data.text || data.title || '')}')">
                        <img src="/assets/icons/email.svg" alt="Email">
                        Email
                    </button>
                    <button onclick="this.copyToClipboard('${data.url || window.location.href}')">
                        <img src="/assets/icons/copy.svg" alt="Copiar">
                        Copiar Link
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Add share methods to buttons
        modal.shareVia = (platform, url, text) => {
            const shareUrls = {
                whatsapp: `https://wa.me/?text=${text}%20${url}`,
                telegram: `https://t.me/share/url?url=${url}&text=${text}`,
                email: `mailto:?subject=${text}&body=${url}`
            };
            
            window.open(shareUrls[platform], '_blank');
            modal.remove();
        };
        
        modal.copyToClipboard = (url) => {
            navigator.clipboard.writeText(url).then(() => {
                this.showShareSuccess('Link copiado!');
                modal.remove();
            });
        };
    }

    addShareButtons() {
        // Add share buttons to booking confirmations, services, etc.
        const shareableElements = document.querySelectorAll('[data-shareable]');
        
        shareableElements.forEach(element => {
            const shareBtn = document.createElement('button');
            shareBtn.className = 'share-btn';
            shareBtn.innerHTML = '🔗 Compartilhar';
            
            shareBtn.onclick = () => {
                const data = {
                    title: element.dataset.shareTitle || 'Blue Cleaning Services',
                    text: element.dataset.shareText || 'Confira este serviço de limpeza!',
                    url: element.dataset.shareUrl || window.location.href
                };
                
                this.share(data);
            };
            
            element.appendChild(shareBtn);
        });
    }

    handleShareTarget() {
        // Check if app was opened as share target
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('shared')) {
            const sharedData = {
                title: urlParams.get('title'),
                text: urlParams.get('text'),
                url: urlParams.get('url')
            };
            
            // Process shared content
            this.processSharedContent(sharedData);
        }
    }

    processSharedContent(data) {
        // Show shared content in UI
        const notification = document.createElement('div');
        notification.className = 'shared-content-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <h4>Conteúdo Compartilhado</h4>
                <p>${data.text}</p>
                ${data.url ? `<a href="${data.url}" target="_blank">${data.url}</a>` : ''}
                <button onclick="this.closest('.shared-content-notification').remove()">Fechar</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 10000);
    }

    showShareSuccess(message) {
        const toast = document.createElement('div');
        toast.className = 'share-success-toast';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    trackShareEvent(data) {
        // Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'share', {
                content_type: 'page',
                content_id: data.url || window.location.href,
                method: 'web_share_api'
            });
        }
    }
}

// Contact Picker API Implementation
class ContactPickerManager {
    constructor() {
        this.isSupported = 'contacts' in navigator && 'ContactsManager' in window;
        this.init();
    }

    init() {
        if (!this.isSupported) {
            console.warn('Contact Picker API not supported');
            return;
        }

        this.addContactPickerButtons();
    }

    async pickContacts(properties = ['name', 'tel'], options = { multiple: true }) {
        if (!this.isSupported) {
            return { success: false, error: 'Contact Picker not supported' };
        }

        try {
            const availableProperties = await navigator.contacts.getProperties();
            const supportedProperties = properties.filter(prop => 
                availableProperties.includes(prop)
            );

            const contacts = await navigator.contacts.select(supportedProperties, options);
            
            console.log('✅ Contacts selected:', contacts);
            return { success: true, contacts };
        } catch (error) {
            console.log('Contact picker cancelled or failed:', error);
            return { success: false, error: error.message };
        }
    }

    addContactPickerButtons() {
        // Add to referral forms, emergency contacts, etc.
        const referralForms = document.querySelectorAll('.referral-form');
        
        referralForms.forEach(form => {
            const pickBtn = document.createElement('button');
            pickBtn.type = 'button';
            pickBtn.className = 'contact-picker-btn';
            pickBtn.innerHTML = '📱 Escolher dos Contatos';
            
            pickBtn.onclick = async () => {
                const result = await this.pickContacts(['name', 'tel']);
                
                if (result.success) {
                    this.populateReferralForm(form, result.contacts);
                }
            };
            
            form.querySelector('.contact-input-group')?.appendChild(pickBtn);
        });

        // Add to emergency contact forms
        const emergencyForms = document.querySelectorAll('.emergency-contact-form');
        
        emergencyForms.forEach(form => {
            const pickBtn = document.createElement('button');
            pickBtn.type = 'button';
            pickBtn.className = 'contact-picker-btn';
            pickBtn.innerHTML = '📱 Escolher Contato de Emergência';
            
            pickBtn.onclick = async () => {
                const result = await this.pickContacts(['name', 'tel'], { multiple: false });
                
                if (result.success && result.contacts.length > 0) {
                    const contact = result.contacts[0];
                    form.querySelector('[name="emergency_name"]').value = contact.name?.[0] || '';
                    form.querySelector('[name="emergency_phone"]').value = contact.tel?.[0] || '';
                }
            };
            
            form.appendChild(pickBtn);
        });
    }

    populateReferralForm(form, contacts) {
        const referralList = form.querySelector('.referral-contacts-list') || 
                           this.createReferralList(form);
        
        contacts.forEach(contact => {
            if (contact.name && contact.tel) {
                const contactItem = document.createElement('div');
                contactItem.className = 'referral-contact-item';
                contactItem.innerHTML = `
                    <div class="contact-info">
                        <span class="name">${contact.name[0]}</span>
                        <span class="phone">${contact.tel[0]}</span>
                    </div>
                    <button type="button" class="remove-contact" onclick="this.parentElement.remove()">×</button>
                    <input type="hidden" name="referral_contacts[]" value="${JSON.stringify({name: contact.name[0], phone: contact.tel[0]})}">
                `;
                
                referralList.appendChild(contactItem);
            }
        });
    }

    createReferralList(form) {
        const listContainer = document.createElement('div');
        listContainer.className = 'referral-contacts-list';
        
        const title = document.createElement('h4');
        title.textContent = 'Contatos Selecionados:';
        
        listContainer.appendChild(title);
        form.appendChild(listContainer);
        
        return listContainer;
    }
}

// Payment Request API Implementation
class PaymentRequestManager {
    constructor() {
        this.isSupported = window.PaymentRequest;
        this.init();
    }

    init() {
        if (!this.isSupported) {
            console.warn('Payment Request API not supported');
            return;
        }

        this.addPaymentButtons();
    }

    async createPaymentRequest(details, options = {}) {
        const supportedMethods = [
            {
                supportedMethods: 'basic-card',
                data: {
                    supportedNetworks: ['visa', 'mastercard', 'elo'],
                    supportedTypes: ['credit', 'debit']
                }
            }
        ];

        // Add PIX support if available
        if ('pix' in window) {
            supportedMethods.push({
                supportedMethods: 'https://pix.bcb.gov.br',
                data: {
                    supportedNetworks: ['pix']
                }
            });
        }

        const paymentOptions = {
            requestPayerName: true,
            requestPayerEmail: true,
            requestPayerPhone: true,
            requestShipping: options.requiresShipping || false,
            shippingType: 'delivery',
            ...options
        };

        try {
            const paymentRequest = new PaymentRequest(
                supportedMethods,
                details,
                paymentOptions
            );

            // Check if payment can be made
            const canMakePayment = await paymentRequest.canMakePayment();
            if (!canMakePayment) {
                throw new Error('Payment method not available');
            }

            return paymentRequest;
        } catch (error) {
            console.error('Payment Request creation failed:', error);
            throw error;
        }
    }

    async processPayment(paymentRequest) {
        try {
            const paymentResponse = await paymentRequest.show();
            
            // Validate payment details
            const validationResult = await this.validatePayment(paymentResponse);
            
            if (validationResult.success) {
                await paymentResponse.complete('success');
                return {
                    success: true,
                    paymentData: paymentResponse,
                    transactionId: validationResult.transactionId
                };
            } else {
                await paymentResponse.complete('fail');
                throw new Error(validationResult.error);
            }
            
        } catch (error) {
            console.error('Payment processing failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async validatePayment(paymentResponse) {
        // Send payment data to backend for validation
        try {
            const response = await fetch('/api/payments/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    paymentData: paymentResponse.toJSON ? paymentResponse.toJSON() : paymentResponse,
                    timestamp: Date.now()
                })
            });

            const result = await response.json();
            return result;
        } catch (error) {
            return {
                success: false,
                error: 'Payment validation failed'
            };
        }
    }

    addPaymentButtons() {
        const paymentForms = document.querySelectorAll('.payment-form');
        
        paymentForms.forEach(form => {
            const payBtn = document.createElement('button');
            payBtn.type = 'button';
            payBtn.className = 'payment-request-btn';
            payBtn.innerHTML = '💳 Pagamento Rápido';
            
            payBtn.onclick = async () => {
                await this.handleQuickPayment(form);
            };
            
            form.querySelector('.payment-buttons')?.appendChild(payBtn);
        });
    }

    async handleQuickPayment(form) {
        try {
            const formData = new FormData(form);
            const amount = parseFloat(formData.get('amount')) || 0;
            const serviceDescription = formData.get('service') || 'Serviço de Limpeza';
            
            const paymentDetails = {
                total: {
                    label: 'Total',
                    amount: {
                        currency: 'BRL',
                        value: amount.toFixed(2)
                    }
                },
                displayItems: [
                    {
                        label: serviceDescription,
                        amount: {
                            currency: 'BRL',
                            value: amount.toFixed(2)
                        }
                    }
                ]
            };

            const paymentRequest = await this.createPaymentRequest(paymentDetails);
            const result = await this.processPayment(paymentRequest);
            
            if (result.success) {
                this.showPaymentSuccess();
                form.dispatchEvent(new CustomEvent('paymentSuccess', {
                    detail: result
                }));
            } else {
                this.showPaymentError(result.error);
            }
            
        } catch (error) {
            console.error('Quick payment failed:', error);
            this.showPaymentError(error.message);
        }
    }

    showPaymentSuccess() {
        const notification = document.createElement('div');
        notification.className = 'payment-success-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <div class="success-icon">✅</div>
                <h3>Pagamento Realizado!</h3>
                <p>Sua transação foi processada com sucesso.</p>
                <button onclick="this.closest('.payment-success-notification').remove()">OK</button>
            </div>
        `;
        
        document.body.appendChild(notification);
    }

    showPaymentError(error) {
        const notification = document.createElement('div');
        notification.className = 'payment-error-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <div class="error-icon">❌</div>
                <h3>Erro no Pagamento</h3>
                <p>${error}</p>
                <button onclick="this.closest('.payment-error-notification').remove()">OK</button>
            </div>
        `;
        
        document.body.appendChild(notification);
    }
}

// Device API Integration
class DeviceAPIManager {
    constructor() {
        this.init();
    }

    init() {
        this.initBatteryAPI();
        this.initNetworkAPI();
        this.initOrientationAPI();
        this.initVibrationAPI();
    }

    // Battery API
    async initBatteryAPI() {
        if ('getBattery' in navigator) {
            try {
                const battery = await navigator.getBattery();
                
                this.updateBatteryInfo(battery);
                
                // Listen for battery events
                battery.addEventListener('chargingchange', () => this.updateBatteryInfo(battery));
                battery.addEventListener('levelchange', () => this.updateBatteryInfo(battery));
                
                // Warn if battery is low during service
                if (battery.level < 0.15 && !battery.charging) {
                    this.showLowBatteryWarning();
                }
                
            } catch (error) {
                console.log('Battery API not available:', error);
            }
        }
    }

    updateBatteryInfo(battery) {
        const batteryInfo = {
            level: Math.round(battery.level * 100),
            charging: battery.charging,
            chargingTime: battery.chargingTime,
            dischargingTime: battery.dischargingTime
        };
        
        // Update UI elements
        const batteryIndicators = document.querySelectorAll('.battery-indicator');
        batteryIndicators.forEach(indicator => {
            indicator.textContent = `🔋 ${batteryInfo.level}%`;
            indicator.classList.toggle('charging', batteryInfo.charging);
            indicator.classList.toggle('low', batteryInfo.level < 15);
        });
        
        // Store for offline sync decisions
        localStorage.setItem('batteryInfo', JSON.stringify(batteryInfo));
    }

    showLowBatteryWarning() {
        const warning = document.createElement('div');
        warning.className = 'low-battery-warning';
        warning.innerHTML = `
            <div class="warning-content">
                <span class="warning-icon">⚠️</span>
                <span>Bateria baixa! Considere carregar o dispositivo.</span>
                <button onclick="this.parentElement.remove()">×</button>
            </div>
        `;
        
        document.body.appendChild(warning);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (warning.parentNode) {
                warning.remove();
            }
        }, 10000);
    }

    // Network Information API
    initNetworkAPI() {
        if ('connection' in navigator) {
            const connection = navigator.connection;
            
            this.updateNetworkInfo(connection);
            
            connection.addEventListener('change', () => {
                this.updateNetworkInfo(connection);
            });
        }
    }

    updateNetworkInfo(connection) {
        const networkInfo = {
            effectiveType: connection.effectiveType,
            downlink: connection.downlink,
            rtt: connection.rtt,
            saveData: connection.saveData
        };
        
        // Adapt UI based on connection quality
        if (connection.effectiveType === 'slow-2g' || connection.saveData) {
            document.body.classList.add('low-bandwidth-mode');
            this.enableDataSavingMode();
        } else {
            document.body.classList.remove('low-bandwidth-mode');
        }
        
        // Update connection indicator
        const connectionIndicators = document.querySelectorAll('.connection-indicator');
        connectionIndicators.forEach(indicator => {
            indicator.textContent = this.getConnectionIcon(connection.effectiveType);
            indicator.title = `${connection.effectiveType} - ${connection.downlink} Mbps`;
        });
        
        localStorage.setItem('networkInfo', JSON.stringify(networkInfo));
    }

    getConnectionIcon(effectiveType) {
        const icons = {
            'slow-2g': '📶 1/4',
            '2g': '📶 2/4',
            '3g': '📶 3/4',
            '4g': '📶 4/4'
        };
        return icons[effectiveType] || '📶';
    }

    enableDataSavingMode() {
        // Disable auto-loading of images
        const images = document.querySelectorAll('img[data-src]');
        images.forEach(img => {
            img.removeAttribute('src');
            img.style.display = 'none';
        });
        
        // Reduce update frequencies
        document.dispatchEvent(new CustomEvent('enableDataSavingMode'));
    }

    // Device Orientation API
    initOrientationAPI() {
        if ('DeviceOrientationEvent' in window) {
            window.addEventListener('deviceorientation', (event) => {
                this.handleOrientationChange(event);
            });
        }
    }

    handleOrientationChange(event) {
        const orientation = {
            alpha: Math.round(event.alpha), // 0-360°
            beta: Math.round(event.beta),   // -180 to 180°
            gamma: Math.round(event.gamma)  // -90 to 90°
        };
        
        // Use for GPS compass functionality
        document.dispatchEvent(new CustomEvent('orientationUpdate', {
            detail: orientation
        }));
    }

    // Vibration API
    initVibrationAPI() {
        if ('vibrate' in navigator) {
            // Vibrate on important notifications
            document.addEventListener('importantNotification', () => {
                navigator.vibrate([200, 100, 200]);
            });
            
            // Vibrate on form errors
            document.addEventListener('formError', () => {
                navigator.vibrate([300]);
            });
            
            // Success vibration
            document.addEventListener('actionSuccess', () => {
                navigator.vibrate([100, 50, 100]);
            });
        }
    }

    // Utility method for haptic feedback
    vibrate(pattern = [100]) {
        if ('vibrate' in navigator) {
            navigator.vibrate(pattern);
        }
    }
}

// Initialization
document.addEventListener('DOMContentLoaded', () => {
    // Initialize all PWA features
    const backgroundFetch = new BackgroundFetchManager();
    const webShare = new WebShareManager();
    const contactPicker = new ContactPickerManager();
    const paymentRequest = new PaymentRequestManager();
    const deviceAPI = new DeviceAPIManager();
    
    // Make globally available
    window.BlueCleaningPWA = {
        backgroundFetch,
        webShare,
        contactPicker,
        paymentRequest,
        deviceAPI
    };
    
    console.log('🚀 Enhanced PWA features loaded');
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        BackgroundFetchManager,
        WebShareManager,
        ContactPickerManager,
        PaymentRequestManager,
        DeviceAPIManager
    };
}
