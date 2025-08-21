/**
 * Booking App v4.0 - Versão corrigida para resolver erros de elementos ausentes
 * Corrige todos os problemas identificados no console
 */
class BookingApp {
    constructor() {
        this.elements = {};
        this.initialized = false;
        this.debugMode = window.BlueProject?.debug || false;
    }

    init() {
        try {
            console.log('🚀 Inicializando BookingApp v4.0 (Fixed)');
            
            this.findElements();
            this.setupEventListeners();
            this.initializePricing();
            
            this.initialized = true;
            console.log('✅ BookingApp inicializado com sucesso');
            
        } catch (error) {
            console.error('❌ Erro ao inicializar BookingApp:', error);
            this.initializeFallback();
        }
    }

    findElements() {
        // Lista de elementos opcionais que podem não existir
        const elementMap = {
            // Elementos obrigatórios
            summaryTotal: '#summaryTotal',
            summaryBar: '#summaryBar', 
            openSummaryBtn: '#openSummaryBtn',
            summaryModal: '#summaryModal',
            
            // Elementos opcionais
            calendarPreview: '#calendarPreview',
            totalPriceLabel: '#totalPriceLabel',
            contractPreview: '#contractPreview',
            discountCodeModal: '#discountCodeModal',
            pointsApplied: '#pointsApplied'
        };
        
        for (const [key, selector] of Object.entries(elementMap)) {
            const element = document.querySelector(selector);
            if (element) {
                this.elements[key] = element;
                if (this.debugMode) console.log(`✅ Elemento encontrado: ${selector}`);
            } else {
                console.warn(`⚠️ Elemento não encontrado: ${selector}`);
                // Criar elemento placeholder se crítico
                if (['summaryTotal', 'summaryBar', 'openSummaryBtn'].includes(key)) {
                    this.elements[key] = this.createPlaceholder(key, selector);
                }
            }
        }
        
        console.log('📋 Elementos encontrados:', Object.keys(this.elements));
    }

    createPlaceholder(key, selector) {
        console.log(`🔧 Criando placeholder para ${key}`);
        const placeholder = document.createElement('div');
        placeholder.id = selector.replace('#', '');
        placeholder.className = 'placeholder-element';
        placeholder.setAttribute('data-placeholder', 'true');
        placeholder.style.display = 'none'; // Esconder por padrão
        document.body.appendChild(placeholder);
        return placeholder;
    }

    setupEventListeners() {
        // Só configurar listeners para elementos que existem
        if (this.elements.openSummaryBtn) {
            this.elements.openSummaryBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openSummaryModal();
            });
            console.log('✅ Listener configurado para openSummaryBtn');
        }
        
        // Calendar preview (opcional)
        if (this.elements.calendarPreview) {
            if (typeof this.elements.calendarPreview.addEventListener === 'function') {
                this.elements.calendarPreview.addEventListener('click', () => {
                    if (window.bookingCalendar) {
                        window.bookingCalendar.show();
                    }
                });
                console.log('✅ Listener configurado para calendarPreview');
            } else {
                console.warn('⚠️ calendarPreview não tem addEventListener');
            }
        }
        
        // Discount code (opcional)
        if (this.elements.discountCodeModal) {
            this.elements.discountCodeModal.addEventListener('input', 
                this.debounce(() => this.validateDiscountCode(), 500)
            );
            console.log('✅ Listener configurado para discountCodeModal');
        }
        
        console.log('🔗 Event listeners configurados');
    }

    initializePricing() {
        // Aguardar um pouco mais para garantir que pricing-calculator.js carregue
        setTimeout(() => {
            console.log('🔍 Verificando sistemas de pricing disponíveis...');
            
            // Verificar se PricingCalculator está disponível
            if (window.PricingCalculator && typeof window.PricingCalculator.calculateTotal === 'function') {
                try {
                    window.PricingCalculator.calculateTotal();
                    console.log('✅ PricingCalculator inicializado e funcionando');
                } catch (error) {
                    console.error('❌ Erro ao executar PricingCalculator:', error);
                    this.initializeFallbackPricing();
                }
            }
            // Verificar se updateTotal está disponível
            else if (window.updateTotal && typeof window.updateTotal === 'function') {
                try {
                    window.updateTotal(true);
                    console.log('✅ updateTotal executado com sucesso');
                } catch (error) {
                    console.error('❌ Erro ao executar updateTotal:', error);
                    this.initializeFallbackPricing();
                }
            }
            // Verificar se updatePricing está disponível
            else if (window.updatePricing && typeof window.updatePricing === 'function') {
                try {
                    window.updatePricing();
                    console.log('✅ updatePricing executado com sucesso');
                } catch (error) {
                    console.error('❌ Erro ao executar updatePricing:', error);
                    this.initializeFallbackPricing();
                }
            } else {
                console.warn('⚠️ Nenhum sistema de pricing encontrado, aguardando mais tempo...');
                
                // Tentar novamente após mais tempo
                setTimeout(() => {
                    if (window.PricingCalculator || window.updateTotal || window.updatePricing) {
                        console.log('✅ Sistema de pricing encontrado após espera adicional');
                        this.initializePricing();
                    } else {
                        console.warn('⚠️ Sistema de pricing ainda não encontrado, inicializando fallback...');
                        this.initializeFallbackPricing();
                    }
                }, 1000);
            }
        }, 800); // Aumentado para 800ms para aguardar carregamento do pricing-calculator.js
    }

    initializeFallbackPricing() {
        // ✅ CORRIGIDO: Sistema básico de pricing calculando serviços inclusos
        const calculateBasicTotal = () => {
            let total = 0; // ✅ CORRIGIDO: Começar de zero
            
            // Calcular serviços inclusos dinamicamente
            document.querySelectorAll('.item-card').forEach(el => {
                const price = parseFloat(el.getAttribute('data-price') || 0);
                const qtyElement = el.querySelector('.qty');
                const qty = parseInt(qtyElement?.textContent || '0', 10);
                
                if (qty > 0 && !isNaN(price)) {
                    total += price * qty;
                    const itemName = el.querySelector('h4')?.textContent?.trim() || 'Item';
                    console.log(`📊 Fallback - ${itemName}: $${price} × ${qty} = $${(price * qty).toFixed(2)}`);
                }
            });
            
            // Se não encontrou nenhum item, erro crítico - não deve acontecer em sistema dinâmico
            if (total === 0) {
                console.error('❌ CRITICAL: No pricing data found - dynamic pricing system failure');
                console.error('📊 This indicates a database connectivity or configuration issue');
                
                // Mostrar erro ao usuário em vez de fallback
                const summaryTotal = document.getElementById('summaryTotal');
                if (summaryTotal) {
                    summaryTotal.textContent = 'Error';
                    summaryTotal.style.color = '#ff6b6b';
                }
                return; // Não continuar com cálculos
            }
            
            const summaryTotal = document.getElementById('summaryTotal');
            const totalPriceLabel = document.getElementById('totalPriceLabel');
            
            if (summaryTotal) {
                summaryTotal.textContent = `$${total.toFixed(2)}`;
                summaryTotal.setAttribute('data-current-value', total.toFixed(2));
            }
            
            if (totalPriceLabel) {
                totalPriceLabel.textContent = `$${total.toFixed(2)}`;
            }
            
            console.log('✅ Fallback pricing aplicado:', total);
        };
        
        // Executar cálculo inicial
        calculateBasicTotal();
        
        // Criar sistema global de fallback
        window.updatePricing = calculateBasicTotal;
        window.recalculateEmergency = calculateBasicTotal;
        
        console.log('✅ Sistema de pricing fallback inicializado');
    }

    openSummaryModal() {
        if (this.elements.summaryModal) {
            this.elements.summaryModal.classList.remove('hidden');
            this.elements.summaryModal.setAttribute('aria-hidden', 'false');
            
            // Atualizar conteúdo se SummaryModal existir
            if (window.SummaryModal && typeof window.SummaryModal.update === 'function') {
                try {
                    window.SummaryModal.update();
                } catch (error) {
                    console.error('❌ Erro ao atualizar SummaryModal:', error);
                }
            }
            
            console.log('✅ Summary modal aberto');
        } else {
            console.warn('⚠️ summaryModal não encontrado');
        }
    }

    initializeFallback() {
        console.log('🔧 Inicializando sistema de fallback...');
        
        // Garantir que botão de checkout funcione
        const button = document.getElementById('openSummaryBtn');
        const modal = document.getElementById('summaryModal');
        
        if (button && modal) {
            button.onclick = function(e) {
                e.preventDefault();
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                console.log('✅ Fallback modal aberto');
            };
            
            console.log('✅ Fallback configurado para checkout');
        } else {
            console.error('❌ Elementos críticos não encontrados para fallback');
        }

        // Garantir que sistema de pricing funcione básico
        if (!window.PricingCalculator && !window.updatePricing) {
            console.log('🔧 Criando sistema de pricing básico...');
            
            window.updatePricing = function() {
                console.log('📊 Fallback pricing executado');
                // Implementação básica de pricing
                const totalElement = document.getElementById('summaryTotal');
                if (totalElement) {
                    const currentValue = totalElement.getAttribute('data-current-value') || '0.00'; // Sem fallback fixo
                    totalElement.textContent = `$${currentValue}`;
                }
            };
            
            // Executar uma vez para definir valor inicial
            setTimeout(() => {
                window.updatePricing();
            }, 500);
        }
    }

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
    }

    validateDiscountCode() {
        console.log('🎫 Validando código de desconto...');
        // Implementação será adicionada conforme necessário
    }

    // Método para adicionar elementos dinamicamente
    addElement(key, element) {
        if (element) {
            this.elements[key] = element;
            console.log(`✅ Elemento ${key} adicionado dinamicamente`);
        }
    }

    // Método para verificar se um elemento existe
    hasElement(key) {
        return this.elements.hasOwnProperty(key) && this.elements[key];
    }

    // Método público para debug
    getDebugInfo() {
        return {
            initialized: this.initialized,
            elementsFound: Object.keys(this.elements),
            hasWindow: {
                PricingCalculator: !!window.PricingCalculator,
                updatePricing: !!window.updatePricing,
                bookingCalendar: !!window.bookingCalendar,
                SummaryModal: !!window.SummaryModal
            }
        };
    }
}

// Função de inicialização segura
function initializeBookingApp() {
    try {
        window.BookingApp = new BookingApp();
        
        // Aguardar um pouco antes de inicializar para garantir que outros scripts carregaram
        setTimeout(() => {
            window.BookingApp.init();
            
            // Debug info se necessário
            if (window.BookingApp.debugMode) {
                console.log('🔍 BookingApp Debug Info:', window.BookingApp.getDebugInfo());
            }
        }, 500);
        
    } catch (error) {
        console.error('❌ Falha crítica ao inicializar BookingApp:', error);
        
        // Fallback absoluto
        setTimeout(() => {
            const button = document.getElementById('openSummaryBtn');
            const modal = document.getElementById('summaryModal');
            
            if (button && modal) {
                button.onclick = function(e) {
                    e.preventDefault();
                    modal.classList.remove('hidden');
                    console.log('✅ Fallback absoluto executado');
                };
            }
        }, 1000);
    }
}

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeBookingApp);
} else {
    initializeBookingApp();
}

// Export para uso global
window.BookingApp = window.BookingApp || {};

// Compatibilidade com sistemas antigos
window.initBookingApp = initializeBookingApp;
