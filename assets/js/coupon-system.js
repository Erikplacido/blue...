/**
 * =========================================================
 * COUPON SYSTEM - FRONTEND JAVASCRIPT
 * =========================================================
 * 
 * @file assets/js/coupon-system.js
 * @description Sistema completo de cupons para o frontend
 * @date 2025-08-11
 */

class CouponSystem {
    
    constructor() {
        this.apiUrl = 'api/validate-discount.php';
        this.currentDiscount = 0;
        this.currentCoupon = null;
        this.isProcessing = false;
        
        this.init();
    }
    
    /**
     * Inicializar sistema de cupons
     */
    init() {
        this.setupElements();
        this.bindEvents();
        
        console.log('✅ CouponSystem: Initialized');
    }
    
    /**
     * Configurar elementos do DOM
     */
    setupElements() {
        // ✅ CORRIGIDO: Procurar pelos IDs corretos do booking3.php
        this.couponInput = document.getElementById('discountCodeModal') || document.getElementById('coupon-code');
        this.applyButton = document.getElementById('applyDiscountBtnModal') || document.getElementById('apply-coupon');
        this.removeButton = document.getElementById('remove-coupon');
        this.couponStatus = document.getElementById('discountStatus') || document.getElementById('coupon-status');
        this.discountAmount = document.getElementById('couponDiscountAmount') || document.getElementById('discount-amount');
        this.finalAmount = document.getElementById('summaryTotal') || document.getElementById('final-amount');
        
        // Criar elementos se não existirem
        if (!this.couponStatus) {
            this.createCouponStatusElement();
        }
    }
    
    /**
     * Criar elemento de status do cupom
     */
    createCouponStatusElement() {
        const container = document.querySelector('.coupon-container') || 
                         document.querySelector('.discount-section') ||
                         document.querySelector('.pricing-summary');
        
        if (container) {
            const statusDiv = document.createElement('div');
            statusDiv.id = 'coupon-status';
            statusDiv.className = 'coupon-status';
            statusDiv.style.cssText = `
                margin-top: 10px;
                padding: 10px;
                border-radius: 5px;
                display: none;
                font-size: 14px;
                font-weight: 500;
            `;
            container.appendChild(statusDiv);
            this.couponStatus = statusDiv;
        }
    }
    
    /**
     * Vincular eventos
     */
    bindEvents() {
        if (this.applyButton) {
            this.applyButton.addEventListener('click', () => this.applyCoupon());
        }
        
        if (this.removeButton) {
            this.removeButton.addEventListener('click', () => this.removeCoupon());
        }
        
        if (this.couponInput) {
            this.couponInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyCoupon();
                }
            });
            
            this.couponInput.addEventListener('input', () => this.clearStatus());
        }
    }
    
    /**
     * ✅ NOVO: Obter desconto atual aplicado
     * @returns {number} Valor do desconto atual
     */
    getCurrentDiscount() {
        return this.currentDiscount || 0;
    }
    
    /**
     * ✅ NOVO: Obter dados do cupom atual
     * @returns {object|null} Dados do cupom atual ou null
     */
    getCurrentCoupon() {
        return this.currentCoupon;
    }

    /**
     * Aplicar cupom de desconto
     */
    async applyCoupon() {
        if (this.isProcessing) return;
        
        const code = this.couponInput ? this.couponInput.value.trim() : '';
        
        if (!code) {
            this.showError('Digite um código de cupom');
            return;
        }
        
        this.isProcessing = true;
        this.setLoadingState(true);
        
        try {
            const subtotal = this.getCurrentSubtotal();
            const customerEmail = this.getCustomerEmail();
            
            console.log('🚀 Aplicando cupom:', {
                code,
                subtotal,
                customerEmail,
                apiUrl: this.apiUrl
            });
            
            const requestData = {
                code: code,
                subtotal: subtotal,
                customer_email: customerEmail
            };
            
            console.log('📤 Enviando dados:', JSON.stringify(requestData, null, 2));
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });
            
            console.log('📡 Response status:', response.status);
            console.log('📡 Response headers:', response.headers);
            
            const result = await response.json();
            
            console.log('📥 Resposta recebida:', result);
            
            if (result.valid) {
                this.currentDiscount = result.discount_amount;
                this.currentCoupon = {
                    code: code.toUpperCase(),
                    discount: result.discount_amount,
                    message: result.message,
                    formatted: result.formatted_discount || `$${result.discount_amount.toFixed(2)}`
                };
                
                this.showSuccess(`✅ ${result.message} - Desconto: ${this.currentCoupon.formatted}`);
                this.updatePricing();
                this.toggleCouponButtons(true);
                
                // Trigger global event
                this.triggerCouponEvent('applied', this.currentCoupon);
                
            } else {
                this.showError(`❌ ${result.message}`);
                this.currentDiscount = 0;
                this.currentCoupon = null;
            }
            
        } catch (error) {
            console.error('❌ CouponSystem: Error applying coupon', error);
            this.showError('Erro ao aplicar cupom. Tente novamente.');
            
        } finally {
            this.isProcessing = false;
            this.setLoadingState(false);
        }
    }
    
    /**
     * Remover cupom aplicado
     */
    removeCoupon() {
        this.currentDiscount = 0;
        this.currentCoupon = null;
        
        if (this.couponInput) {
            this.couponInput.value = '';
        }
        
        this.clearStatus();
        this.updatePricing();
        this.toggleCouponButtons(false);
        
        // Trigger global event
        this.triggerCouponEvent('removed', null);
        
        console.log('🗑️ CouponSystem: Coupon removed');
    }
    
    /**
     * Obter subtotal atual do sistema de preços
     */
    getCurrentSubtotal() {
        console.log('💰 Obtendo subtotal atual...');
        
        // ✅ MELHORADO: Integração com pricing-calculator
        const sources = [
            // 1. Tentar pegar do summaryTotal data attribute (mais confiável)
            () => {
                const summaryTotal = document.querySelector('.summary-total');
                if (summaryTotal) {
                    const storedValue = summaryTotal.getAttribute('data-current-value');
                    if (storedValue) {
                        console.log('✅ Subtotal obtido de data-current-value:', storedValue);
                        return parseFloat(storedValue);
                    }
                }
                return null;
            },
            
            // 2. Usar função global do pricing calculator
            () => {
                if (window.PricingCalculator && window.PricingCalculator.calculateTotal) {
                    const total = window.PricingCalculator.calculateTotal();
                    console.log('✅ Subtotal calculado via PricingCalculator:', total);
                    return total;
                }
                return null;
            },
            
            // 3. Usar função global updateTotal
            () => {
                if (window.updateTotal) {
                    const total = window.updateTotal();
                    console.log('✅ Subtotal calculado via updateTotal:', total);
                    return total;
                }
                return null;
            },
            
            // 4. Parse do summaryTotal text content
            () => {
                const summaryTotal = document.querySelector('.summary-total');
                if (summaryTotal && summaryTotal.textContent) {
                    const parsed = parseFloat(summaryTotal.textContent.replace(/[^\d.]/g, ''));
                    if (!isNaN(parsed) && parsed > 0) {
                        console.log('✅ Subtotal parseado de summaryTotal:', parsed);
                        return parsed;
                    }
                }
                return null;
            }
        ];
        
        // Tentar cada source até encontrar um valor válido
        for (const source of sources) {
            try {
                const value = source();
                if (value !== null && !isNaN(value) && value > 0) {
                    console.log(`✅ Subtotal final: $${value.toFixed(2)}`);
                    return value;
                }
            } catch (e) {
                console.warn('⚠️ Erro ao obter subtotal de source:', e);
                continue;
            }
        }
        
        console.warn('⚠️ CouponSystem: Could not determine subtotal, using fallback');
        return 25.00; // ✅ CORRIGIDO: Valor base mínimo mais realista
    }
    
    /**
     * Obter email do cliente
     */
    getCustomerEmail() {
        const sources = [
            () => document.getElementById('customer-email')?.value,
            () => document.getElementById('email')?.value,
            () => document.querySelector('input[type="email"]')?.value,
            () => window.customerEmail,
            () => ''
        ];
        
        for (const source of sources) {
            try {
                const value = source();
                if (value && value.includes('@')) {
                    return value;
                }
            } catch (e) {
                continue;
            }
        }
        
        return '';
    }
    
    /**
     * ✅ MELHORADO: Atualizar preços na interface com integração completa
     */
    updatePricing() {
        console.log('💰 CouponSystem: updatePricing executando...');
        
        // ✅ MELHORADO: Obter subtotal via múltiplas estratégias
        let subtotal = this.getCurrentSubtotal();
        
        // ✅ FALLBACK: Tentar obter via PricingCalculator se subtotal inválido
        if (!subtotal || subtotal === 0) {
            console.warn('⚠️ CouponSystem: Subtotal inválido, tentando via PricingCalculator...');
            
            if (window.PricingCalculator && window.PricingCalculator.calculateTotal) {
                try {
                    subtotal = window.PricingCalculator.calculateTotal();
                    console.log('🔄 CouponSystem: Subtotal obtido via PricingCalculator:', subtotal);
                } catch(e) {
                    console.warn('❌ Erro ao obter subtotal via PricingCalculator:', e);
                    subtotal = 0;
                }
            }
        }
        
        const discount = this.currentDiscount || 0;
        const final = Math.max(0, subtotal - discount);
        
        console.log('💰 CouponSystem: Cálculo final', {
            subtotal: subtotal.toFixed(2),
            discount: discount.toFixed(2),
            final: final.toFixed(2)
        });
        
        // Atualizar elementos de desconto
        if (this.discountAmount) {
            this.discountAmount.textContent = `$${discount.toFixed(2)}`;
            this.discountAmount.style.display = discount > 0 ? 'block' : 'none';
        }
        
        // Atualizar total final
        if (this.finalAmount) {
            this.finalAmount.textContent = `$${final.toFixed(2)}`;
        }
        
        // Atualizar outros elementos com data attributes
        document.querySelectorAll('[data-discount-amount]').forEach(el => {
            el.textContent = `$${discount.toFixed(2)}`;
            el.style.display = discount > 0 ? 'block' : 'none';
        });
        
        document.querySelectorAll('[data-final-amount]').forEach(el => {
            el.textContent = `$${final.toFixed(2)}`;
        });
        
        // ✅ NOVO: Múltiplos eventos para integração completa
        const eventData = { subtotal, discount, final, coupon: this.currentCoupon };
        
        // Trigger pricing updated event
        window.dispatchEvent(new CustomEvent('pricingUpdated', { detail: eventData }));
        
        // ✅ NOVO: Trigger coupon-specific events
        window.dispatchEvent(new CustomEvent('couponUpdate', { 
            detail: { 
                type: discount > 0 ? 'applied' : 'removed', 
                data: this.currentCoupon,
                discount: discount
            } 
        }));
        
        // ✅ NOVO: Atualizar pricing calculator diretamente
        if (window.PricingCalculator && window.PricingCalculator.updateSummaryModal) {
            setTimeout(() => {
                window.PricingCalculator.updateSummaryModal();
            }, 50);
        }
        
        // ✅ NOVO: Atualizar display visual do desconto
        this.updatePricingBreakdownDisplay(discount, this.currentCoupon);
        
        console.log('🚀 CouponSystem: Todos os eventos e atualizações disparados');
    }
    
    /**
     * ✅ NOVO: Atualizar a exibição visual do desconto no Pricing Breakdown
     */
    updatePricingBreakdownDisplay(discount, couponData) {
        console.log('🎨 Atualizando exibição do pricing breakdown...', { discount, couponData });
        
        // Elementos do pricing breakdown
        const couponDiscountRow = document.getElementById('couponDiscountRow');
        const couponDiscountAmount = document.getElementById('couponDiscountAmount');
        const couponCodeDisplay = document.getElementById('coupon-code-display');
        
        if (discount > 0 && couponData) {
            // Mostrar linha de desconto do cupom
            if (couponDiscountRow) {
                couponDiscountRow.style.display = 'flex';
                couponDiscountRow.style.opacity = '0';
                setTimeout(() => {
                    couponDiscountRow.style.opacity = '1';
                }, 100);
            }
            
            // Atualizar valor do desconto
            if (couponDiscountAmount) {
                couponDiscountAmount.textContent = `-$${discount.toFixed(2)}`;
                couponDiscountAmount.style.color = '#38a169';
            }
            
            // Mostrar código do cupom
            if (couponCodeDisplay && couponData.code) {
                couponCodeDisplay.textContent = couponData.code.toUpperCase();
            }
            
            console.log('✅ Linha de desconto exibida no pricing breakdown');
            
        } else {
            // Ocultar linha de desconto
            if (couponDiscountRow) {
                couponDiscountRow.style.display = 'none';
            }
            
            console.log('➖ Linha de desconto ocultada');
        }
    }
    
    /**
     * Mostrar mensagem de sucesso
     */
    showSuccess(message) {
        console.log('✅ showSuccess chamada:', message);
        console.log('📍 couponStatus element:', this.couponStatus);
        
        if (!this.couponStatus) {
            console.error('❌ couponStatus element não encontrado!');
            return;
        }
        
        this.couponStatus.innerHTML = message;
        this.couponStatus.className = 'coupon-status coupon-success';
        this.couponStatus.style.cssText = `
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            display: block;
            font-size: 14px;
            font-weight: 500;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        `;
        
        console.log('✅ Mensagem de sucesso exibida');
    }
    
    /**
     * Mostrar mensagem de erro
     */
    showError(message) {
        if (!this.couponStatus) return;
        
        this.couponStatus.innerHTML = message;
        this.couponStatus.className = 'coupon-status coupon-error';
        this.couponStatus.style.cssText = `
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            display: block;
            font-size: 14px;
            font-weight: 500;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        `;
    }
    
    /**
     * Limpar status
     */
    clearStatus() {
        if (this.couponStatus) {
            this.couponStatus.style.display = 'none';
        }
    }
    
    /**
     * Controlar estado de loading
     */
    setLoadingState(loading) {
        console.log('🔄 setLoadingState:', loading);
        
        if (this.applyButton) {
            this.applyButton.disabled = loading;
            
            if (loading) {
                this.applyButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validando...';
            } else {
                this.applyButton.innerHTML = '<i class="fas fa-plus"></i> Add';
            }
            
            console.log('🔘 Botão atualizado:', this.applyButton.innerHTML);
        } else {
            console.warn('⚠️ applyButton não encontrado em setLoadingState');
        }
        
        if (this.couponInput) {
            this.couponInput.disabled = loading;
        }
    }
    
    /**
     * Alternar visibilidade dos botões
     */
    toggleCouponButtons(applied) {
        if (this.applyButton) {
            this.applyButton.style.display = applied ? 'none' : 'inline-block';
        }
        
        if (this.removeButton) {
            this.removeButton.style.display = applied ? 'inline-block' : 'none';
        }
        
        if (this.couponInput) {
            this.couponInput.readonly = applied;
        }
    }
    
    /**
     * Disparar eventos globais
     */
    triggerCouponEvent(type, data) {
        window.dispatchEvent(new CustomEvent('couponUpdate', {
            detail: { type, data, discount: this.currentDiscount }
        }));
    }
    
    /**
     * API pública para obter estado atual
     */
    getState() {
        return {
            hasActiveCoupon: !!this.currentCoupon,
            currentDiscount: this.currentDiscount,
            currentCoupon: this.currentCoupon,
            isProcessing: this.isProcessing
        };
    }
    
    /**
     * API pública para aplicar cupom programaticamente
     */
    async applyCouponCode(code) {
        if (this.couponInput) {
            this.couponInput.value = code;
        }
        return await this.applyCoupon();
    }
}

// Inicializar sistema de cupons quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    window.couponSystem = new CouponSystem();
    
    console.log('🎫 CouponSystem: Ready for use');
});

// Adicionar estilos CSS ao head se não existirem
if (!document.querySelector('#coupon-system-styles')) {
    const styles = document.createElement('style');
    styles.id = 'coupon-system-styles';
    styles.textContent = `
        .coupon-container {
            margin: 15px 0;
        }
        
        .coupon-input-group {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        #coupon-code {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            text-transform: uppercase;
            min-width: 120px;
            flex: 1;
        }
        
        #apply-coupon, #remove-coupon {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        #apply-coupon {
            background-color: #007bff;
            color: white;
        }
        
        #apply-coupon:hover {
            background-color: #0056b3;
        }
        
        #apply-coupon:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        #remove-coupon {
            background-color: #dc3545;
            color: white;
        }
        
        #remove-coupon:hover {
            background-color: #c82333;
        }
        
        .coupon-status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .coupon-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .coupon-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .discount-line {
            display: flex;
            justify-content: space-between;
            color: #28a745;
            font-weight: 600;
            margin: 5px 0;
        }
        
        @media (max-width: 768px) {
            .coupon-input-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            #coupon-code {
                min-width: auto;
            }
            
            #apply-coupon, #remove-coupon {
                width: 100%;
                margin-top: 5px;
            }
        }
    `;
    document.head.appendChild(styles);
}
