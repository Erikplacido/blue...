/**
 * ========================================
 * GOOGLE PLACES AUTOCOMPLETE IMPLEMENTATION
 * ========================================
 * Integrado com o sistema de booking existente
 * Focado na Austrália com validação de área de serviço
 */

class GooglePlacesAutocomplete {
    constructor() {
        this.autocomplete = null;
        this.addressInput = null;
        this.initialized = false;
        this.config = {
            // Configurações específicas para Austrália
            country: 'AU',
            types: ['address'],
            // Áreas de serviço válidas (estados australianos)
            validStates: ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT'],
            // Cidades principais para validação
            majorCities: ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast', 'Canberra', 'Newcastle']
        };
    }

    /**
     * Inicializa o Google Places Autocomplete
     */
    init() {
        // Aguarda o carregamento da API do Google
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            console.log('⏳ Aguardando carregamento da API Google Places...');
            setTimeout(() => this.init(), 100);
            return;
        }

        try {
            this.setupAutocomplete();
            this.initialized = true;
            console.log('✅ Google Places Autocomplete inicializado com sucesso!');
            
            // Dispara evento de inicialização
            document.dispatchEvent(new CustomEvent('googlePlacesReady', {
                detail: { instance: this }
            }));
            
        } catch (error) {
            console.error('❌ Erro ao inicializar Google Places:', error);
            this.showErrorMessage('Failed to initialize address lookup. Please enter address manually.');
        }
    }

    /**
     * Configura o autocomplete nos campos de endereço
     */
    setupAutocomplete() {
        // Busca todos os campos de endereço possíveis
        const addressSelectors = [
            '#address',
            '#streetAddress', 
            '#customerAddress',
            '#service_address',
            'input[name="address"]',
            'input[name="street_address"]',
            'input[placeholder*="address" i]',
            'input[placeholder*="endereço" i]',
            '.address-input',
            '[data-address-field]'
        ];

        let addressField = null;
        
        // Tenta encontrar o campo de endereço
        for (const selector of addressSelectors) {
            addressField = document.querySelector(selector);
            if (addressField) {
                console.log(`📍 Campo de endereço encontrado: ${selector}`);
                break;
            }
        }

        // Se não encontrou, cria um observador para campos dinâmicos
        if (!addressField) {
            console.log('🔍 Campo de endereço não encontrado, iniciando observador...');
            this.observeForAddressFields();
            return;
        }

        this.setupAutocompleteForField(addressField);
    }

    /**
     * Configura autocomplete para um campo específico
     */
    setupAutocompleteForField(field) {
        this.addressInput = field;

        // Configurações do autocomplete
        const options = {
            types: this.config.types,
            componentRestrictions: { country: this.config.country },
            fields: [
                'formatted_address',
                'address_components', 
                'geometry',
                'place_id',
                'types',
                'name'
            ]
        };

        // Cria o autocomplete
        this.autocomplete = new google.maps.places.Autocomplete(field, options);

        // Adiciona listener para quando um lugar é selecionado
        this.autocomplete.addListener('place_changed', () => {
            this.handlePlaceSelection();
        });

        // Adiciona estilos e funcionalidades ao campo
        this.enhanceAddressField(field);

        console.log('🎯 Autocomplete configurado para:', field);
        
        // Feedback visual de sucesso
        this.showSuccessIndicator(field);
    }

    /**
     * Manipula a seleção de um lugar
     */
    handlePlaceSelection() {
        const place = this.autocomplete.getPlace();

        if (!place.geometry) {
            console.warn('⚠️ Local selecionado não possui informações de geometria');
            this.showWarningMessage('Please select a valid address from the suggestions.');
            return;
        }

        console.log('📍 Local selecionado:', place);

        // Preenche o campo com o endereço formatado
        this.addressInput.value = place.formatted_address;

        // Extrai componentes do endereço
        const addressData = this.extractAddressComponents(place);
        
        // Valida área de serviço
        const isValidArea = this.validateServiceArea(addressData);
        
        if (isValidArea) {
            // Dispara evento customizado com os dados
            this.dispatchAddressSelectedEvent(addressData, place);

            // Atualiza outros campos se existirem
            this.updateRelatedFields(addressData);

            // Feedback visual de sucesso
            this.showAddressSelectedFeedback();
            
            // Salva dados para uso posterior
            this.saveAddressData(addressData);
            
        } else {
            this.showServiceAreaError(addressData);
        }
    }

    /**
     * Extrai componentes estruturados do endereço
     */
    extractAddressComponents(place) {
        const components = {};
        
        place.address_components.forEach(component => {
            const types = component.types;
            
            if (types.includes('street_number')) {
                components.streetNumber = component.long_name;
            }
            if (types.includes('route')) {
                components.streetName = component.long_name;
            }
            if (types.includes('locality')) {
                components.suburb = component.long_name;
            }
            if (types.includes('administrative_area_level_1')) {
                components.state = component.short_name;
                components.stateFullName = component.long_name;
            }
            if (types.includes('postal_code')) {
                components.postcode = component.long_name;
            }
            if (types.includes('country')) {
                components.country = component.long_name;
                components.countryCode = component.short_name;
            }
            if (types.includes('administrative_area_level_2')) {
                components.region = component.long_name;
            }
        });

        return {
            ...components,
            fullAddress: place.formatted_address,
            placeId: place.place_id,
            coordinates: {
                lat: place.geometry.location.lat(),
                lng: place.geometry.location.lng()
            },
            placeName: place.name || '',
            types: place.types || []
        };
    }

    /**
     * Valida se o endereço está na área de serviço
     */
    validateServiceArea(addressData) {
        // Verifica país
        if (addressData.countryCode !== 'AU') {
            return false;
        }

        // Verifica estado
        if (!addressData.state || !this.config.validStates.includes(addressData.state)) {
            return false;
        }

        return true;
    }

    /**
     * Dispara evento customizado quando endereço é selecionado
     */
    dispatchAddressSelectedEvent(addressData, place) {
        const event = new CustomEvent('addressSelected', {
            detail: {
                addressData,
                place,
                field: this.addressInput,
                isValidServiceArea: true
            }
        });
        
        document.dispatchEvent(event);
        console.log('🚀 Evento addressSelected disparado:', addressData);
    }

    /**
     * Atualiza campos relacionados automaticamente
     */
    updateRelatedFields(addressData) {
        // Mapeamento de campos comuns
        const fieldMappings = {
            suburb: ['#suburb', '#city', '#locality', 'input[name="suburb"]', 'input[name="city"]'],
            state: ['#state', 'input[name="state"]', 'select[name="state"]'],
            postcode: ['#postcode', '#zipcode', 'input[name="postcode"]', 'input[name="postal_code"]'],
            country: ['#country', 'input[name="country"]'],
            streetNumber: ['#street_number', 'input[name="street_number"]'],
            streetName: ['#street_name', 'input[name="street_name"]']
        };

        Object.entries(fieldMappings).forEach(([key, selectors]) => {
            if (addressData[key]) {
                selectors.forEach(selector => {
                    const field = document.querySelector(selector);
                    if (field && !field.value) {
                        if (field.tagName === 'SELECT') {
                            // Para selects, tenta encontrar a opção correspondente
                            const option = field.querySelector(`option[value="${addressData[key]}"]`);
                            if (option) {
                                field.value = addressData[key];
                                this.animateFieldUpdate(field);
                            }
                        } else {
                            field.value = addressData[key];
                            this.animateFieldUpdate(field);
                        }
                    }
                });
            }
        });
    }

    /**
     * Observa campos de endereço criados dinamicamente
     */
    observeForAddressFields() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        const addressField = node.querySelector?.('input[placeholder*="address" i], [data-address-field]') ||
                                           (node.matches?.('input[placeholder*="address" i], [data-address-field]') ? node : null);
                        
                        if (addressField && !addressField.hasAttribute('data-autocomplete-setup')) {
                            console.log('🔄 Campo de endereço dinâmico encontrado');
                            setTimeout(() => this.setupAutocompleteForField(addressField), 100);
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Aprimora o campo de endereço com funcionalidades extras
     */
    enhanceAddressField(field) {
        // Marca como configurado
        field.setAttribute('data-autocomplete-setup', 'true');
        
        // Adiciona classe CSS
        field.classList.add('google-places-autocomplete');
        
        // Melhora o placeholder
        if (!field.placeholder || field.placeholder.trim() === '') {
            field.placeholder = 'Start typing your address...';
        }
        
        // Cria wrapper para ícones
        this.createFieldWrapper(field);
        
        // Adiciona listeners para melhor UX
        field.addEventListener('focus', () => this.onFieldFocus(field));
        field.addEventListener('blur', () => this.onFieldBlur(field));
        field.addEventListener('input', (e) => this.onFieldInput(e, field));
    }

    /**
     * Cria wrapper com ícones para o campo
     */
    createFieldWrapper(field) {
        // Verifica se já não está em um wrapper
        if (field.parentNode.classList.contains('address-field-wrapper')) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'address-field-wrapper';
        wrapper.style.position = 'relative';
        wrapper.style.display = 'block';
        wrapper.style.width = '100%';

        // Ícone principal
        const mainIcon = document.createElement('div');
        mainIcon.className = 'address-main-icon';
        mainIcon.innerHTML = '📍';
        mainIcon.style.cssText = `
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 16px;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            z-index: 2;
        `;
        mainIcon.title = 'Powered by Google Places';

        // Ícone de status (loading/success/error)
        const statusIcon = document.createElement('div');
        statusIcon.className = 'address-status-icon';
        statusIcon.style.cssText = `
            position: absolute;
            right: 35px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 14px;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 3;
        `;

        // Envolve o campo
        field.parentNode.insertBefore(wrapper, field);
        wrapper.appendChild(field);
        wrapper.appendChild(statusIcon);
        wrapper.appendChild(mainIcon);

        // Armazena referências
        field._wrapper = wrapper;
        field._statusIcon = statusIcon;
        field._mainIcon = mainIcon;
    }

    /**
     * Handlers de eventos do campo
     */
    onFieldFocus(field) {
        if (field._mainIcon) {
            field._mainIcon.style.opacity = '1';
        }
        field.classList.add('focused');
    }

    onFieldBlur(field) {
        if (field._mainIcon) {
            field._mainIcon.style.opacity = '0.6';
        }
        field.classList.remove('focused');
    }

    onFieldInput(e, field) {
        const value = e.target.value;
        
        if (value.length > 3) {
            this.showLoadingState(field);
        } else {
            this.hideLoadingState(field);
        }
    }

    /**
     * Estados visuais do campo
     */
    showLoadingState(field) {
        if (field._statusIcon) {
            field._statusIcon.innerHTML = '⏳';
            field._statusIcon.style.opacity = '1';
        }
    }

    hideLoadingState(field) {
        if (field._statusIcon) {
            field._statusIcon.style.opacity = '0';
        }
    }

    showSuccessState(field) {
        if (field._statusIcon) {
            field._statusIcon.innerHTML = '✅';
            field._statusIcon.style.opacity = '1';
            setTimeout(() => {
                if (field._statusIcon) {
                    field._statusIcon.style.opacity = '0';
                }
            }, 3000);
        }
    }

    showErrorState(field) {
        if (field._statusIcon) {
            field._statusIcon.innerHTML = '❌';
            field._statusIcon.style.opacity = '1';
            setTimeout(() => {
                if (field._statusIcon) {
                    field._statusIcon.style.opacity = '0';
                }
            }, 3000);
        }
    }

    /**
     * Feedback visual quando endereço é selecionado
     */
    showAddressSelectedFeedback() {
        const field = this.addressInput;
        
        // Estado de sucesso no campo
        this.showSuccessState(field);
        
        // Animação de borda
        const originalBorder = field.style.border;
        field.style.border = '2px solid #48bb78';
        field.style.transition = 'border 0.3s ease';

        setTimeout(() => {
            field.style.border = originalBorder;
        }, 2000);

        // Notificação de sucesso
        this.showNotification('✅ Address confirmed!', 'success');
    }

    /**
     * Mostra erro de área de serviço
     */
    showServiceAreaError(addressData) {
        this.showErrorState(this.addressInput);
        
        let message = '⚠️ Sorry, we currently don\'t service this area.';
        
        if (addressData.countryCode !== 'AU') {
            message = '⚠️ We only service addresses in Australia.';
        } else if (!this.config.validStates.includes(addressData.state)) {
            message = `⚠️ We don't currently service ${addressData.stateFullName || addressData.state}.`;
        }
        
        this.showNotification(message, 'error');
        console.warn('Service area validation failed:', addressData);
    }

    /**
     * Salva dados do endereço
     */
    saveAddressData(addressData) {
        // Salva no sessionStorage para uso em outras páginas
        sessionStorage.setItem('selectedAddress', JSON.stringify(addressData));
        
        // Salva coordenadas separadamente para cálculos
        if (addressData.coordinates) {
            sessionStorage.setItem('selectedCoordinates', JSON.stringify(addressData.coordinates));
        }
        
        console.log('💾 Dados do endereço salvos:', addressData);
    }

    /**
     * Anima atualização de campo
     */
    animateFieldUpdate(field) {
        const originalBackground = field.style.backgroundColor;
        field.style.backgroundColor = '#e6fffa';
        field.style.transition = 'background-color 0.3s ease';

        setTimeout(() => {
            field.style.backgroundColor = originalBackground;
        }, 1000);
    }

    /**
     * Sistema de notificações
     */
    showNotification(message, type = 'info') {
        // Remove notificação anterior
        const existing = document.querySelector('.google-places-notification');
        if (existing) {
            existing.remove();
        }

        const notification = document.createElement('div');
        notification.className = `google-places-notification ${type}`;
        notification.textContent = message;
        
        const colors = {
            success: '#48bb78',
            error: '#f56565',
            warning: '#ed8936',
            info: '#4299e1'
        };
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            color: ${colors[type]};
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid ${colors[type]};
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            font-size: 14px;
            font-weight: 500;
            max-width: 300px;
            animation: slideInRight 0.3s ease;
        `;

        document.body.appendChild(notification);

        // Remove automaticamente
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    /**
     * Mostra mensagens de erro
     */
    showErrorMessage(message) {
        this.showNotification(message, 'error');
    }

    showWarningMessage(message) {
        this.showNotification(message, 'warning');
    }

    showSuccessMessage(message) {
        this.showNotification(message, 'success');
    }

    /**
     * Mostra indicador de inicialização bem-sucedida
     */
    showSuccessIndicator(field) {
        // Adiciona pequeno indicador visual de que o Google Places está ativo
        setTimeout(() => {
            if (field._mainIcon) {
                field._mainIcon.style.animation = 'pulse 0.5s ease';
            }
        }, 100);
    }

    /**
     * Método para reinicializar (útil para SPAs)
     */
    reinitialize() {
        if (this.autocomplete) {
            google.maps.event.clearListeners(this.autocomplete, 'place_changed');
        }
        this.initialized = false;
        this.init();
    }

    /**
     * Obtém dados do endereço atual
     */
    getCurrentAddressData() {
        const saved = sessionStorage.getItem('selectedAddress');
        return saved ? JSON.parse(saved) : null;
    }

    /**
     * Limpa dados salvos
     */
    clearSavedData() {
        sessionStorage.removeItem('selectedAddress');
        sessionStorage.removeItem('selectedCoordinates');
    }
}

// Instância global
window.GooglePlacesAutocomplete = GooglePlacesAutocomplete;

// Função de callback para o Google Maps API
window.initGooglePlaces = function() {
    console.log('🗺️ Google Maps API carregada');
    
    // Aguarda DOM estar pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.placesAutocomplete = new GooglePlacesAutocomplete();
            window.placesAutocomplete.init();
        });
    } else {
        window.placesAutocomplete = new GooglePlacesAutocomplete();
        window.placesAutocomplete.init();
    }
};

// Auto-inicialização se API já estiver carregada
if (typeof google !== 'undefined' && google.maps && google.maps.places) {
    window.initGooglePlaces();
}

// Adiciona estilos de animação
if (!document.querySelector('#google-places-animations')) {
    const style = document.createElement('style');
    style.id = 'google-places-animations';
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        @keyframes pulse {
            0%, 100% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.1); }
        }
    `;
    document.head.appendChild(style);
}
