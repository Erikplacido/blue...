document.addEventListener('DOMContentLoaded', function () {
  // Verificação de elementos essenciais
  const summaryTotal = document.querySelector('.summary-total');
  const discountLine = document.getElementById('discountLine');
  const summaryInfo = document.getElementById('summaryInfoPanel');
  
  if (!summaryTotal) {
    console.error('❌ Elemento summaryTotal não encontrado! Pricing Calculator não pode inicializar.');
    return;
  }
  
  console.log('✅ Pricing Calculator inicializando com elementos:', {
    summaryTotal: !!summaryTotal,
    discountLine: !!discountLine,
    summaryInfo: !!summaryInfo
  });

  // SISTEMA DE CÁLCULO À PROVA DE FALHAS - VERSÃO 2.0 COM INTEGRAÇÃO DE CUPOM
  let isCalculating = false;
  let calculationTimeout = null;
  let currentCouponDiscount = 0; // ✅ NOVO: Armazenar desconto ativo

  // ✅ NOVO: Event listener para integração com sistema de cupom
  window.addEventListener('couponUpdate', function(event) {
    const { type, data, discount } = event.detail;
    console.log('🎫 Cupom evento recebido:', { type, data, discount });
    
    if (type === 'applied') {
      currentCouponDiscount = discount || 0;
      console.log(`✅ Desconto aplicado: $${currentCouponDiscount.toFixed(2)}`);
    } else if (type === 'removed') {
      currentCouponDiscount = 0;
      console.log('🗑️ Desconto removido');
    }
    
    // Recalcular total com desconto
    window.updateTotal(true);
  });

  // ✅ NOVO: Event listener para eventos de pricing do coupon-system
  window.addEventListener('pricingUpdated', function(event) {
    const { subtotal, discount, final, coupon } = event.detail;
    console.log('💰 Pricing evento recebido:', { subtotal, discount, final, coupon });
    
    if (discount && discount > 0) {
      currentCouponDiscount = discount;
      console.log(`✅ Desconto sincronizado via pricingUpdated: $${currentCouponDiscount.toFixed(2)}`);
      
      // Atualizar display sem recalcular (evitar loop)
      updateTotalDisplay(final, discount);
      updateDiscountDisplay(discount, coupon);
    }
  });

  // Atualiza total e painéis com proteção contra conflitos
  window.updateTotal = function (forceUpdate = false) {
    // Previne múltiplas execuções simultâneas
    if (isCalculating && !forceUpdate) {
      console.log('⏳ Cálculo já em andamento, aguardando...');
      return;
    }

    isCalculating = true;
    clearTimeout(calculationTimeout);

    let total = 0.0;
    console.log('🧮 Iniciando cálculo de preços (versão à prova de falhas)...');

    try {
      // ✅ CORRIGIDO: Calcular apenas serviços inclusos + extras (sem preço base fixo)
      console.log(`🏠 Calculando serviços inclusos dinamicamente...`);

      // Soma todos os itens com preço e quantidade
      document.querySelectorAll('.item-card, .extra-item').forEach(el => {
        const price = parseFloat(el.getAttribute('data-price') || 0);
        const qtyElement = el.querySelector('.qty');
        const qty = parseInt(qtyElement?.textContent || '0', 10);
        
        if (qty > 0 && !isNaN(price) && !isNaN(qty)) {
          const itemTotal = price * qty;
          total += itemTotal;
          
          // Debug para verificar os cálculos
          const itemName = el.querySelector('h4, .extra-name')?.textContent?.trim() || 'Item desconhecido';
          console.log(`📊 ${itemName}: $${price} × ${qty} = $${itemTotal.toFixed(2)}`);
        }
      });

    // SISTEMA DE CÁLCULO DE PREFERÊNCIAS REFINADO
    // Adiciona taxas de preferências (checkboxes, selects, texts)
    document.querySelectorAll('.preference-checkbox, .preference-select, .preference-text').forEach(element => {
      let extraFee = 0;
      let prefLabel = element.getAttribute('data-preference-name') || 'Preferência';
      
      if (element.classList.contains('preference-checkbox')) {
        // Checkbox - taxa aplicada se marcado
        extraFee = parseFloat(element.getAttribute('data-extra-fee') || '0');
        if (element.checked && extraFee > 0 && !isNaN(extraFee)) {
          total += extraFee;
          console.log(`💰 [Checkbox] ${prefLabel}: +$${extraFee.toFixed(2)}`);
          updateFeeIndicator(element, extraFee, true);
        } else {
          updateFeeIndicator(element, 0, false);
        }
      } else if (element.classList.contains('preference-select')) {
        // Select - taxa baseada na opção selecionada
        const selectedOption = element.options[element.selectedIndex];
        if (selectedOption) {
          extraFee = parseFloat(selectedOption.getAttribute('data-fee') || '0');
          if (extraFee > 0 && !isNaN(extraFee) && element.value !== '') {
            total += extraFee;
            console.log(`💰 [Select] ${prefLabel}: "${selectedOption.text}" +$${extraFee.toFixed(2)}`);
            updateFeeIndicator(element, extraFee, true);
          } else {
            updateFeeIndicator(element, 0, false);
          }
        }
      } else if (element.classList.contains('preference-text')) {
        // Text - taxa aplicada se preenchido
        extraFee = parseFloat(element.getAttribute('data-extra-fee') || '0');
        if (element.value.trim() !== '' && extraFee > 0 && !isNaN(extraFee)) {
          total += extraFee;
          console.log(`💰 [Text] ${prefLabel}: "${element.value}" +$${extraFee.toFixed(2)}`);
          updateFeeIndicator(element, extraFee, true);
        } else {
          updateFeeIndicator(element, 0, false);
        }
      }
    });
    
    console.log(`🏆 Total final calculado: $${total.toFixed(2)}`);

      // ✅ NOVO: Aplicar desconto do cupom ao total final
      const finalTotal = (total > 0 && currentCouponDiscount > 0) ? 
        Math.max(0, total - currentCouponDiscount) : total;

      // ATUALIZAÇÃO RESISTENTE A CONFLITOS COM DESCONTO
      updateTotalDisplay(finalTotal, currentCouponDiscount);
      updateSummaryPanel(finalTotal);
      
      // ✅ NOVO: Atualizar displays de desconto
      if (currentCouponDiscount > 0) {
        updateDiscountDisplay(currentCouponDiscount);
        console.log(`💰 Subtotal: $${total.toFixed(2)}`);
        console.log(`🎫 Desconto: -$${currentCouponDiscount.toFixed(2)}`);
        console.log(`✅ Total Final: $${finalTotal.toFixed(2)}`);
      }

    } catch (error) {
      console.error('❌ Erro no cálculo:', error);
      total = 0;
    } finally {
      isCalculating = false;
    }
    
    return total;
  }

  // Função para atualizar indicadores visuais de taxa
  function updateFeeIndicator(element, fee, isActive) {
    const preferenceItem = element.closest('.preference-item');
    const feeIndicator = preferenceItem.querySelector('.preference-fee-indicator');
    
    if (feeIndicator) {
      const feeAmount = feeIndicator.querySelector('.fee-amount');
      
      if (isActive && fee > 0) {
        feeAmount.textContent = fee.toFixed(2);
        feeIndicator.classList.add('active');
        feeIndicator.style.display = 'block';
        preferenceItem.classList.add('fee-applied');
        
        // Remove a classe de animação após a animação
        setTimeout(() => {
          preferenceItem.classList.remove('fee-applied');
        }, 1500);
      } else {
        feeIndicator.classList.remove('active');
        feeIndicator.style.display = 'none';
        preferenceItem.classList.remove('fee-applied');
      }
    }
  }

  // ✅ MELHORADA: Função para atualizar displays com proteção e desconto integrado
  function updateTotalDisplay(total, appliedDiscount = null) {
    // ✅ NOVO: Aplicar desconto de cupom se existir
    const discount = appliedDiscount !== null ? appliedDiscount : currentCouponDiscount;
    const subtotalBeforeDiscount = total; // Subtotal antes do desconto
    const finalTotal = discount > 0 ? Math.max(0, total - discount) : total; // Total final
    
    const formattedSubtotal = `$${subtotalBeforeDiscount.toFixed(2)}`;
    const formattedDiscount = discount > 0 ? `$${discount.toFixed(2)}` : null;
    const formattedTotal = `$${finalTotal.toFixed(2)}`;
    
    console.log('📊 updateTotalDisplay MELHORADA:', { 
      subtotalBeforeDiscount, 
      discount, 
      finalTotal, 
      formattedTotal 
    });
    
    // ✅ 1. BARRA DE RESUMO FLUTUANTE
    if (summaryTotal) {
      summaryTotal.textContent = formattedTotal;
      summaryTotal.setAttribute('data-current-value', finalTotal.toFixed(2));
      summaryTotal.setAttribute('data-discount-applied', discount.toFixed(2));
      summaryTotal.setAttribute('data-last-updated', Date.now());
      console.log(`✅ summaryTotal: ${formattedTotal} (desconto: ${formattedDiscount || '$0.00'})`);
    }

    // ✅ 2. PRICING BREAKDOWN (página principal)
    const totalPriceLabel = document.getElementById('totalPriceLabel');
    if (totalPriceLabel) {
      totalPriceLabel.textContent = formattedTotal;
      console.log(`✅ totalPriceLabel: ${formattedTotal}`);
    }
    
    // ✅ 3. SUBTOTAL (página principal)
    const subtotalAmount = document.getElementById('subtotalAmount');
    if (subtotalAmount) {
      subtotalAmount.textContent = formattedSubtotal;
      console.log(`✅ subtotalAmount: ${formattedSubtotal}`);
    }
    
    // ✅ 4. LINHA DE DESCONTO (página principal)
    const discountRow = document.getElementById('discountRow');
    const discountAmount_el = document.getElementById('discountAmount');
    
    if (discount > 0) {
      if (discountRow) {
        discountRow.style.display = 'flex';
      }
      if (discountAmount_el) {
        discountAmount_el.textContent = `-${formattedDiscount}`;
      }
      console.log(`✅ Desconto mostrado: -${formattedDiscount}`);
    } else {
      if (discountRow) {
        discountRow.style.display = 'none';
      }
      console.log('✅ Desconto ocultado');
    }

    // ✅ 5. ATUALIZAR DISPLAY DE DESCONTO GLOBAL
    updateDiscountDisplay(discount);
    
    // ✅ 6. SINCRONIZAR COM MODAL SE EXISTIR
    updateModalElements(finalTotal, discount, subtotalBeforeDiscount);

    // ✅ 7. TRIGGER EVENTO PARA OUTROS SISTEMAS
    window.dispatchEvent(new CustomEvent('totalUpdated', {
      detail: { 
        subtotal: subtotalBeforeDiscount, 
        discount: discount, 
        total: finalTotal 
      }
    }));

    console.log('✅ updateTotalDisplay completo - todos os elementos sincronizados');
  }
  
  // ✅ NOVA FUNÇÃO: Atualizar elementos do modal
  function updateModalElements(finalTotal, discount, subtotal) {
    // Buscar elementos específicos do modal que podem não ter sido atualizados
    const modalElements = [
      '[data-modal-total]',
      '[data-modal-discount]', 
      '[data-modal-subtotal]',
      '#summaryModalTotal',
      '#modalSubtotal'
    ];
    
    modalElements.forEach(selector => {
      const element = document.querySelector(selector);
      if (element) {
        if (selector.includes('discount')) {
          element.textContent = discount > 0 ? `-$${discount.toFixed(2)}` : '$0.00';
          element.style.display = discount > 0 ? 'block' : 'none';
        } else if (selector.includes('subtotal')) {
          element.textContent = `$${subtotal.toFixed(2)}`;
        } else {
          element.textContent = `$${finalTotal.toFixed(2)}`;
        }
        console.log(`✅ Modal element ${selector} atualizado`);
      }
    });
  }

  // ✅ NOVA FUNÇÃO: Atualizar displays de desconto para modal
  function updateDiscountDisplay(discountAmount = 0, couponData = null) {
    console.log('🎫 updateDiscountDisplay chamada:', { discountAmount, couponData });
    
    // Encontrar elementos do modal de desconto
    const discountRow = document.querySelector('[data-discount-amount]')?.parentElement;
    const discountAmountEl = document.querySelector('[data-discount-amount]');
    const discountStatus = document.getElementById('discountStatus');
    
    if (discountAmount > 0) {
      // Mostrar linha de desconto
      if (discountRow) {
        discountRow.style.display = 'flex';
      }
      
      // Atualizar valor do desconto
      if (discountAmountEl) {
        discountAmountEl.textContent = `$${discountAmount.toFixed(2)}`;
        console.log('💰 Valor desconto atualizado:', discountAmountEl.textContent);
      }
      
      // Atualizar status do desconto
      if (discountStatus) {
        const couponCode = couponData ? couponData.code : 'Applied';
        discountStatus.innerHTML = `
          <div class="discount-active">
            <i class="fas fa-check-circle"></i>
            <span>✅ Coupon "${couponCode}" active - Save $${discountAmount.toFixed(2)}</span>
          </div>
        `;
        discountStatus.className = 'discount-status success';
      }
      
    } else {
      // Ocultar linha de desconto
      if (discountRow) {
        discountRow.style.display = 'none';
      }
      
      // Limpar status do desconto
      if (discountStatus) {
        discountStatus.innerHTML = '';
        discountStatus.className = 'discount-status';
      }
    }
  }

  // ✅ FUNÇÃO DE INTEGRAÇÃO: Disponibilizar globalmente
  window.updateDiscountDisplay = updateDiscountDisplay;

  // Atualiza painel lateral com resumo de itens
  function updateSummaryPanel(currentTotal) {
    if (!summaryInfo) return;
    summaryInfo.innerHTML = '';

    let panelTotal = 0;

    document.querySelectorAll('.item-card, .extra-item').forEach(el => {
      const name = el.querySelector('h4, .extra-name')?.textContent?.trim();
      const price = parseFloat(el.getAttribute('data-price') || 0);
      const qty = parseInt(el.querySelector('.qty')?.textContent || 0, 10);
      
      if (qty > 0 && !isNaN(price) && !isNaN(qty)) {
        const itemTotal = price * qty;
        panelTotal += itemTotal;
        
        const line = document.createElement('p');
        line.textContent = `${name} × ${qty} = $${itemTotal.toFixed(2)}`;
        line.className = 'summary-line';
        summaryInfo.appendChild(line);
      }
    });

    // Adicionar preferências com taxa no resumo
    document.querySelectorAll('.preference-checkbox, .preference-select, .preference-text').forEach(element => {
      let extraFee = 0;
      let prefLabel = element.getAttribute('data-preference-name') || 'Preferência';
      let prefValue = '';
      
      if (element.classList.contains('preference-checkbox') && element.checked) {
        extraFee = parseFloat(element.getAttribute('data-extra-fee') || '0');
        prefValue = 'Selected';
      } else if (element.classList.contains('preference-select') && element.value !== '') {
        const selectedOption = element.options[element.selectedIndex];
        if (selectedOption) {
          extraFee = parseFloat(selectedOption.getAttribute('data-fee') || '0');
          prefValue = selectedOption.text;
        }
      } else if (element.classList.contains('preference-text') && element.value.trim() !== '') {
        extraFee = parseFloat(element.getAttribute('data-extra-fee') || '0');
        prefValue = 'Custom input';
      }
      
      if (extraFee > 0 && !isNaN(extraFee)) {
        panelTotal += extraFee;
        
        const line = document.createElement('p');
        line.textContent = `${prefLabel} (${prefValue}) = +$${extraFee.toFixed(2)}`;
        line.className = 'summary-line preference-fee';
        summaryInfo.appendChild(line);
      }
    });

    // Validação cruzada
    if (currentTotal && Math.abs(panelTotal - currentTotal) > 0.01) {
      console.warn(`⚠️ Divergência detectada! Panel: $${panelTotal.toFixed(2)}, Calculado: $${currentTotal.toFixed(2)}`);
    }
  }

  // SISTEMA DE MONITORAMENTO E CORREÇÃO AUTOMÁTICA
  let monitoringInterval = null;
  
  function startPriceMonitoring() {
    if (monitoringInterval) return;
    
    monitoringInterval = setInterval(() => {
      const currentValue = summaryTotal?.getAttribute('data-current-value');
      const displayValue = summaryTotal?.textContent;
      
      // Se o valor exibido for $0.00 mas deveria ter um valor
      if (displayValue === '$0.00' && currentValue && parseFloat(currentValue) > 0) {
        console.warn('🚨 Valor zerado detectado! Recalculando...');
        updateTotal(true); // Força recálculo
      }
      
      // Se o valor exibido não corresponder ao valor armazenado
      if (currentValue && displayValue !== `$${parseFloat(currentValue).toFixed(2)}`) {
        console.warn('🚨 Inconsistência no display detectada! Corrigindo...');
        summaryTotal.textContent = `$${parseFloat(currentValue).toFixed(2)}`;
      }
    }, 1000); // Verifica a cada 1 segundo
  }

  function stopPriceMonitoring() {
    if (monitoringInterval) {
      clearInterval(monitoringInterval);
      monitoringInterval = null;
    }
  }

// Manipuladores dos botões de quantidade (+ / -) COM PROTEÇÃO
document.body.addEventListener('click', function (e) {
  if (!e.target.classList.contains('plus') && !e.target.classList.contains('minus')) return;

  const isPlus = e.target.classList.contains('plus');
  const container = e.target.closest('.item-card, .extra-item');
  const qtySpan = container.querySelector('.qty');
  // procura o hidden input dentro deste mesmo card
  const hiddenInput = container.querySelector('input[type="hidden"]');

  let qty = parseInt(qtySpan.textContent, 10);
  const minQty = parseInt(container.getAttribute('data-min-quantity')) || 0;

  if (isPlus) {
    qty++;
  } else {
    qty = Math.max(minQty, qty - 1);
  }

  // atualiza o contador visível
  qtySpan.textContent = qty;

  // atualiza o hidden input para envio no form
  if (hiddenInput) {
    hiddenInput.value = qty;
  }

  // Log do item alterado
  const itemName = container.querySelector('h4, .extra-name')?.textContent?.trim() || 'Item';
  const price = parseFloat(container.getAttribute('data-price') || 0);
  console.log(`🔄 ${itemName}: Quantidade ${qty} × $${price} = $${(qty * price).toFixed(2)}`);

  // recalcula totais com delay para evitar conflitos
  clearTimeout(calculationTimeout);
  calculationTimeout = setTimeout(() => {
    updateTotal(true);
  }, 100);
});

// Listener para todos os tipos de preferências COM PROTEÇÃO
document.body.addEventListener('change', function(e) {
  if (e.target.classList.contains('preference-checkbox') || 
      e.target.classList.contains('preference-select') || 
      e.target.classList.contains('preference-text')) {
    
    const prefName = e.target.getAttribute('data-preference-name') || 'Preferência';
    let extraFee = 0;
    let action = '';
    
    if (e.target.classList.contains('preference-checkbox')) {
      extraFee = parseFloat(e.target.getAttribute('data-extra-fee') || '0');
      action = e.target.checked ? 'adicionada' : 'removida';
      
      if (e.target.checked && extraFee > 0) {
        console.log(`✅ [Checkbox] ${prefName}: Taxa de $${extraFee.toFixed(2)} ${action}`);
      } else if (!e.target.checked && extraFee > 0) {
        console.log(`❌ [Checkbox] ${prefName}: Taxa de $${extraFee.toFixed(2)} ${action}`);
      }
    } else if (e.target.classList.contains('preference-select')) {
      const selectedOption = e.target.options[e.target.selectedIndex];
      if (selectedOption) {
        extraFee = parseFloat(selectedOption.getAttribute('data-fee') || '0');
        const optionText = selectedOption.text;
        
        if (e.target.value !== '' && extraFee > 0) {
          console.log(`✅ [Select] ${prefName}: "${optionText}" com taxa de $${extraFee.toFixed(2)}`);
        } else {
          console.log(`🔄 [Select] ${prefName}: Opção alterada para "${optionText}"`);
        }
      }
    } else if (e.target.classList.contains('preference-text')) {
      extraFee = parseFloat(e.target.getAttribute('data-extra-fee') || '0');
      const inputValue = e.target.value.trim();
      
      if (inputValue !== '' && extraFee > 0) {
        console.log(`✅ [Text] ${prefName}: Valor "${inputValue}" com taxa de $${extraFee.toFixed(2)}`);
      } else if (inputValue === '' && extraFee > 0) {
        console.log(`❌ [Text] ${prefName}: Campo limpo, taxa de $${extraFee.toFixed(2)} removida`);
      }
    }
    
    // recalcula totais com delay para evitar conflitos
    clearTimeout(calculationTimeout);
    calculationTimeout = setTimeout(() => {
      updateTotal(true);
    }, 100);
  }
});

// Listener adicional para inputs de texto (para atualização em tempo real)
document.body.addEventListener('input', function(e) {
  if (e.target.classList.contains('preference-text')) {
    // Delay maior para inputs de texto para evitar muitas atualizações
    clearTimeout(calculationTimeout);
    calculationTimeout = setTimeout(() => {
      updateTotal(true);
    }, 500);
  }
});

  // PROTEÇÃO CONTRA OUTROS SCRIPTS QUE TENTAM ZERAR O TOTAL
  const originalUpdateTotal = window.updateTotal;
  window.updateTotal = function(...args) {
    console.log('🔒 updateTotal chamado através do proxy de proteção');
    return originalUpdateTotal.apply(this, args);
  };

  // Proteção do elemento summaryTotal contra alterações externas
  if (summaryTotal) {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'childList' || mutation.type === 'characterData') {
          const currentText = summaryTotal.textContent;
          const storedValue = summaryTotal.getAttribute('data-current-value');
          
          // Se foi zerado incorretamente
          if (currentText === '$0.00' && storedValue && parseFloat(storedValue) > 0) {
            console.warn('🛡️ Tentativa de zerar summaryTotal detectada e bloqueada!');
            summaryTotal.textContent = `$${parseFloat(storedValue).toFixed(2)}`;
          }
        }
      });
    });
    
    observer.observe(summaryTotal, { 
      childList: true, 
      subtree: true, 
      characterData: true 
    });
    
    console.log('🛡️ Proteção do summaryTotal ativada via MutationObserver');
  }

  // Total inicial ao carregar a página + monitoramento
  console.log('🚀 Inicializando sistema de cálculo de preços à prova de falhas...');
  setTimeout(() => {
    updateTotal(true);
    startPriceMonitoring();
    console.log('✅ Sistema de preços inicializado e monitoramento ativo');
  }, 500);

  // Parar monitoramento quando sair da página
  window.addEventListener('beforeunload', stopPriceMonitoring);
});

// FUNÇÃO GLOBAL DE EMERGÊNCIA PARA RECALCULAR PREÇOS
window.recalculateEmergency = function() {
  console.log('🚨 RECÁLCULO DE EMERGÊNCIA ATIVADO');
  if (window.updateTotal) {
    return window.updateTotal(true);
  }
  return 0;
};

// SISTEMA GLOBAL DE PRICING CALCULATOR
window.PricingCalculator = {
  calculateTotal: function() {
    console.log('🧮 PricingCalculator.calculateTotal() chamado');
    if (window.updateTotal) {
      return window.updateTotal(true);
    } else {
      console.warn('⚠️ window.updateTotal não encontrada');
      return 0;
    }
  },
  
  // ✅ NOVO: Calcular e exibir com integração completa
  calculateAndDisplay: function() {
    const total = this.calculateTotal();
    if (typeof window.updateDiscountDisplay === 'function') {
      window.updateDiscountDisplay(currentCouponDiscount);
    }
    this.updateSummaryModal();
    return total;
  },
  
  // ✅ MELHORADO: Atualizar modal do resumo com integração completa
  updateSummaryModal: function() {
    console.log('📋 updateSummaryModal MELHORADO iniciado...');
    
    const summaryTotalEl = document.getElementById('summaryTotal');
    if (summaryTotalEl && window.updateTotal) {
      try {
        // Obter total atual e calcular com desconto
        const currentTotal = window.updateTotal(true);
        
        // ✅ CORRIGIDO: Obter desconto de cupom aplicado
        let discountApplied = 0;
        if (window.couponSystem && window.couponSystem.getCurrentDiscount) {
          discountApplied = window.couponSystem.getCurrentDiscount();
        } else if (typeof currentCouponDiscount !== 'undefined') {
          discountApplied = currentCouponDiscount;
        }
        
        const finalTotal = discountApplied > 0 ? 
          Math.max(0, currentTotal - discountApplied) : currentTotal;
        
        console.log('📊 updateSummaryModal cálculo:', {
          currentTotal,
          discountApplied, 
          finalTotal
        });
        
        // ✅ 1. Atualizar total principal do modal
        summaryTotalEl.textContent = `$${finalTotal.toFixed(2)}`;
        summaryTotalEl.setAttribute('data-current-value', finalTotal.toFixed(2));
        summaryTotalEl.setAttribute('data-discount-applied', discountApplied.toFixed(2));
        
        // ✅ 2. Buscar e atualizar TODOS os elementos de preço no modal
        const modalPriceElements = [
          { selector: '#totalPriceLabel', value: finalTotal, prefix: '$' },
          { selector: '#subtotalAmount', value: currentTotal, prefix: '$' },
          { selector: '#discountAmount', value: discountApplied, prefix: '-$', show: discountApplied > 0 },
          { selector: '[data-modal-total]', value: finalTotal, prefix: '$' },
          { selector: '[data-discount-amount]', value: discountApplied, prefix: '$', show: discountApplied > 0 }
        ];
        
        modalPriceElements.forEach(item => {
          const element = document.querySelector(item.selector);
          if (element) {
            const displayValue = `${item.prefix}${item.value.toFixed(2)}`;
            element.textContent = displayValue;
            
            // Mostrar/ocultar baseado na condição
            if (item.hasOwnProperty('show')) {
              element.style.display = item.show ? 'block' : 'none';
              
              // Para elementos de desconto, mostrar/ocultar a linha pai também
              if (item.selector.includes('discount')) {
                const parentRow = element.closest('.price-row, .discount-row, [class*="discount"]');
                if (parentRow) {
                  parentRow.style.display = item.show ? 'flex' : 'none';
                }
              }
            }
            
            console.log(`✅ Modal element ${item.selector}: ${displayValue}`);
          }
        });
        
        // ✅ 3. Atualizar elementos específicos do pricing breakdown
        this.updatePricingBreakdownElements(currentTotal, discountApplied, finalTotal);
        
        console.log('✅ updateSummaryModal completo - modal totalmente sincronizado');
        
      } catch (error) {
        console.error('❌ Erro em updateSummaryModal:', error);
      }
    } else {
      console.warn('⚠️ updateSummaryModal: summaryTotal ou updateTotal não disponível');
    }
  },
  
  // ✅ NOVA FUNÇÃO: Atualizar elementos específicos do pricing breakdown
  updatePricingBreakdownElements: function(subtotal, discount, finalTotal) {
    console.log('🧾 updatePricingBreakdownElements executando...', { subtotal, discount, finalTotal });
    
    // Atualizar elementos do pricing breakdown na página principal
    const breakdownElements = [
      { selector: '#basePrice', value: subtotal },
      { selector: '#subtotalAmount', value: subtotal }, 
      { selector: '#discountAmount', value: discount, prefix: '-$' },
      { selector: '#discountRow', show: discount > 0 },
      { selector: '#totalPriceLabel', value: finalTotal }
    ];
    
    breakdownElements.forEach(item => {
      const element = document.querySelector(item.selector);
      if (element) {
        if (item.hasOwnProperty('value')) {
          const prefix = item.prefix || '$';
          element.textContent = `${prefix}${item.value.toFixed(2)}`;
          console.log(`✅ Pricing breakdown ${item.selector}: ${prefix}${item.value.toFixed(2)}`);
        }
        
        if (item.hasOwnProperty('show')) {
          element.style.display = item.show ? 'flex' : 'none';
          console.log(`✅ Pricing breakdown ${item.selector} visibility: ${item.show ? 'visible' : 'hidden'}`);
        }
      }
    });
  },
  
  updatePricing: function() {
    return this.calculateTotal();
  },
  
  recalculate: function() {
    return this.calculateTotal();
  },
  
  isInitialized: function() {
    return !!(window.updateTotal && typeof window.updateTotal === 'function');
  }
};

// Garantir que updatePricing global também existe
if (!window.updatePricing) {
  window.updatePricing = function() {
    return window.PricingCalculator.calculateTotal();
  };
}

console.log('✅ PricingCalculator global inicializado');
