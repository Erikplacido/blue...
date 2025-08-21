/**
 * EXTRAÇÃO UNIFICADA DE TOTAL DO FRONTEND
 * Função JavaScript para garantir que o total correto seja sempre enviado ao API
 */

function extractFrontendTotal() {
    console.log('🧮 Starting unified total extraction...');
    
    // Lista de elementos possíveis que podem conter o total (ordem de prioridade)
    const totalElements = [
        'summaryTotal',           // Elemento principal
        'subtotalAmount',         // Backup subtotal (mais confiável que totalPriceLabel duplicado)
        'baseTotalInput'          // Input hidden como último recurso
    ];
    
    let extractedTotal = null;
    let sourceElement = null;
    
    // Tentar extrair de cada elemento na ordem de prioridade
    for (const elementId of totalElements) {
        const element = document.getElementById(elementId);
        if (!element) {
            console.log(`⚠️ Element ${elementId} not found, skipping...`);
            continue;
        }
        
        let textValue = '';
        
        // Diferentes formas de obter o valor dependendo do tipo de elemento
        if (element.tagName === 'INPUT') {
            textValue = element.value;
        } else {
            textValue = element.textContent || element.innerText || '';
        }
        
        // Extrair número do texto
        const cleanText = textValue.replace(/[^0-9.]/g, '');
        const numericValue = parseFloat(cleanText);
        
        console.log(`🔍 Checking ${elementId}:`, {
            found: true,
            textValue: textValue,
            cleanText: cleanText,
            numericValue: numericValue,
            isValid: !isNaN(numericValue) && numericValue > 0
        });
        
        if (!isNaN(numericValue) && numericValue > 0) {
            extractedTotal = numericValue;
            sourceElement = elementId;
            break;
        }
    }
    
    // Se não encontrou nenhum valor válido, usar fallback
    if (!extractedTotal || extractedTotal <= 0) {
        console.log('⚠️ No valid total found in DOM elements, checking State.get...');
        
        // Tentar obter do State se disponível
        if (typeof State !== 'undefined' && State.get) {
            const pricingTotal = State.get('pricing.total');
            if (pricingTotal && pricingTotal > 0) {
                extractedTotal = pricingTotal;
                sourceElement = 'State.pricing.total';
                console.log('✅ Total extracted from State:', extractedTotal);
            }
        }
        
        // Fallback final para valor padrão
        if (!extractedTotal || extractedTotal <= 0) {
            // ERRO CRÍTICO: Não conseguiu extrair o valor da interface
            console.error('❌ Não foi possível extrair valor total da interface');
            alert('Erro: Não foi possível calcular o valor total. Por favor, recarregue a página.');
            return null;
            sourceElement = 'fallback';
            console.log('⚠️ Using fallback total:', extractedTotal);
        }
    }
    
    console.log('🎯 Final total extraction result:', {
        total: extractedTotal,
        source: sourceElement,
        formatted: `$${extractedTotal.toFixed(2)}`
    });
    
    return {
        total: extractedTotal,
        source: sourceElement,
        isValid: extractedTotal > 0
    };
}

/**
 * VALIDAÇÃO DE DADOS DE CHECKOUT
 * Validar todos os dados necessários antes de enviar ao Stripe
 */
function validateCheckoutData(bookingData) {
    console.log('✅ Validating checkout data...');
    
    const required = {
        'service': bookingData.service,
        'customer.name': bookingData.customer?.name,
        'customer.email': bookingData.customer?.email,
        'customer.phone': bookingData.customer?.phone,
        'address': bookingData.address,
        'date': bookingData.date,
        'time': bookingData.time,
        'total': bookingData.total
    };
    
    const missing = [];
    const invalid = [];
    
    for (const [field, value] of Object.entries(required)) {
        if (!value || (typeof value === 'string' && value.trim() === '')) {
            missing.push(field);
        } else if (field === 'total' && (isNaN(value) || value <= 0)) {
            invalid.push(`${field} (${value}) must be a positive number`);
        } else if (field === 'customer.email' && !value.includes('@')) {
            invalid.push(`${field} (${value}) must be a valid email`);
        }
    }
    
    const isValid = missing.length === 0 && invalid.length === 0;
    
    console.log('📋 Validation result:', {
        isValid: isValid,
        missing: missing,
        invalid: invalid,
        totalValidation: {
            value: bookingData.total,
            type: typeof bookingData.total,
            isNumber: !isNaN(bookingData.total),
            isPositive: bookingData.total > 0
        }
    });
    
    return {
        isValid: isValid,
        missing: missing,
        invalid: invalid,
        errors: [...missing.map(f => `Missing: ${f}`), ...invalid]
    };
}

/**
 * LOGGING DETALHADO PARA DEBUG
 */
function logCheckoutFlow(step, data) {
    const timestamp = new Date().toISOString();
    console.group(`🔄 CHECKOUT FLOW - ${step} [${timestamp}]`);
    console.log('Data:', data);
    console.groupEnd();
    
    // Salvar no sessionStorage para debug posterior
    const logs = JSON.parse(sessionStorage.getItem('checkoutLogs') || '[]');
    logs.push({
        timestamp,
        step,
        data
    });
    sessionStorage.setItem('checkoutLogs', JSON.stringify(logs.slice(-20))); // Manter últimos 20 logs
}

// Disponibilizar funções globalmente
window.extractFrontendTotal = extractFrontendTotal;
window.validateCheckoutData = validateCheckoutData;
window.logCheckoutFlow = logCheckoutFlow;
