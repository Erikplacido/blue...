document.addEventListener('DOMContentLoaded', () => {
  const openBtn          = document.getElementById('openSummaryBtn');
  const closeBtn         = document.getElementById('closeSummaryModal');
  const modal            = document.getElementById('summaryModal');
  const content          = document.getElementById('summaryInfoContent');
  const agreedToTerms    = document.getElementById('agreedToTerms');
  const confirmBtn       = document.getElementById('confirmBtn');
  const totalPriceLabel  = document.getElementById('totalPriceLabel');
  const summaryTotal     = document.querySelector('.summary-total');

  const openTermsBtn     = document.getElementById('openTermsBtn');
  const closeTermsModal  = document.getElementById('closeTermsModal');
  const termsModal       = document.getElementById('termsModal');
  const termsContent     = document.getElementById('termsContent');

  // Função que preenche e recalcula o resumo
  function fillSummary() {
    // 1) recalcula _baseTotal_ (itens + extras)
    let baseTotal = 0;
    document.querySelectorAll('.item-card, .extra-item').forEach(el => {
      const price = parseFloat(el.getAttribute('data-price') || '0');
      const qty   = parseInt(el.querySelector('.qty')?.textContent || '0', 10);
      baseTotal += price * qty;
    });
    const baseTotalField = document.getElementById('baseTotalInput');
    if (baseTotalField) baseTotalField.value = baseTotal;

    // 2) ✅ CORRIGIDO: aplica taxas de preferência apenas se estiverem MARCADAS
    let extraTotal = 0;
    const extraPrefs = [];
    document.querySelectorAll('.preference-checkbox').forEach(cb => {
      const fee = parseFloat(cb.getAttribute('data-extra-fee') || '0');
      if (cb.checked && fee > 0) {  // ✅ CORRIGIDO: Somar apenas quando MARCADO
        extraTotal += fee;
        // tenta extrair um rótulo legível
        const labelEl = cb.closest('label');
        const label = labelEl
          ? labelEl.textContent.trim()
          : `Preference #${cb.name}`;
        extraPrefs.push(`${label} (+$${fee.toFixed(2)})`);
      }
    });

    // 3) total final = base + preferências
    const currentTotal = baseTotal + extraTotal;
    summaryTotal.textContent    = `$${currentTotal.toFixed(2)}`;
    totalPriceLabel.textContent = `$${currentTotal.toFixed(2)}`;

    // 4) coleta dados de endereço, data, recorrência e hora
    const address    = document.getElementById('address')?.value       || 'N/A';
    const date       = document.getElementById('execution_date')?.value || 'N/A';
    const recurrence = document.getElementById('recurrence')?.value    || 'N/A';
    const time       = document.getElementById('time_window')?.value   || 'N/A';

    // 5) monta listas de itens incluídos e extras
    const includedItems = [...document.querySelectorAll('.inclusion-item')]
      .map(item => {
        const name = item.querySelector('h4')?.textContent.trim() || '';
        const qty  = parseInt(item.querySelector('.qty')?.textContent || '0', 10);
        return qty > 0 ? `${name} × ${qty}` : null;
      }).filter(Boolean);

    const extras = [...document.querySelectorAll('.extra-item')]
      .map(item => {
        const name = item.querySelector('.extra-name')?.textContent.trim() || '';
        const qty  = parseInt(item.querySelector('.qty')?.textContent || '0', 10);
        return qty > 0 ? `${name} × ${qty}` : null;
      }).filter(Boolean);

    // 6) gera seção de fees de preferência, se houver
    const extraPrefSection = extraPrefs.length
      ? `<div class="summary-section">
           <h4>Preference Fees:</h4>
           <ul>${extraPrefs.map(p => `<li>${p}</li>`).join('')}</ul>
         </div>`
      : '';

    // 7) injeta tudo no HTML da modal
  content.innerHTML = `
      <p><strong>Address:</strong> ${address}</p>
      <p><strong>Date:</strong> ${date}</p>
      <p><strong>Time:</strong> ${time}</p>
      <p><strong>Recurrence:</strong> ${recurrence}</p>

      <div class="summary-section">
        <h4>Included Items:</h4>
        ${
          includedItems.length
            ? `<ul>${includedItems.map(i => `<li>${i}</li>`).join('')}</ul>`
            : '<p>No additional inclusions.</p>'
        }
      </div>

      <div class="summary-section">
        <h4>Extra Items:</h4>
        ${
          extras.length
            ? `<ul>${extras.map(i => `<li>${i}</li>`).join('')}</ul>`
            : '<p>No extras selected.</p>'
        }
      </div>

  ${extraPrefSection}
  `;
  }

  // Preenche o modal de termos com dados dinâmicos
  function fillTerms() {
    const address    = document.getElementById('address')?.value       || 'N/A';
    const date       = document.getElementById('execution_date')?.value || 'N/A';
    const time       = document.getElementById('time_window')?.value   || 'N/A';
    const recurrence = document.getElementById('recurrence')?.value    || 'N/A';
    const price      = document.getElementById('totalPriceLabel')?.textContent ||
                       document.querySelector('.summary-total')?.textContent || '$0.00';

    const html = `
      <p><strong>Summary:</strong></p>
      <ul>
        <li>Service will be provided at <strong>${address}</strong> on <strong>${date}</strong> within the <strong>${time}</strong> window.</li>
        <li>Recurring services repeat according to selected frequency: <strong>${recurrence}</strong>.</li>
        <li>Payment of <strong>${price}</strong> is due 48 hours before each execution.</li>
        <li>Card will be charged automatically.</li>
        <li>Changes/cancellations must be made at least 48 hours in advance.</li>
        <li>Early termination may incur a penalty.</li>
        <li>We are not responsible for pre-existing damage.</li>
        <li>Issues must be reported within 24 hours.</li>
      </ul>
      <p>By continuing, you agree to all terms stated above.</p>`;

    if (termsContent) termsContent.innerHTML = html;
  }

  // Abre o modal e preenche
  openBtn?.addEventListener('click', () => {
    modal.classList.remove('hidden');
    fillSummary();
  });

  // Fecha o modal
  closeBtn?.addEventListener('click', () => {
    modal.classList.add('hidden');
  });  // Habilita/desabilita o botão de confirmação
  agreedToTerms?.addEventListener('change', () => {
    confirmBtn.disabled = !agreedToTerms.checked;
  });

  // Modal de Termos & Condições
  if (openTermsBtn && closeTermsModal && termsModal) {
    openTermsBtn.addEventListener('click',  () => {
      fillTerms();
      termsModal.classList.remove('hidden');
    });
    closeTermsModal.addEventListener('click', () => termsModal.classList.add('hidden'));
    window.addEventListener('click',   e => { if (e.target === termsModal) termsModal.classList.add('hidden'); });
    window.addEventListener('keydown', e => { if (e.key === 'Escape')      termsModal.classList.add('hidden'); });
  }

  // Recalcula o resumo se o usuário mudar alguma preferência antes de abrir
  document.querySelectorAll('.preference-checkbox').forEach(cb => {
    cb.addEventListener('change', fillSummary);
  });
});
