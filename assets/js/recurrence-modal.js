(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const recurrenceSelect = document.getElementById('recurrence');
    const modal = document.getElementById('recurrenceModal');
    const modalTitle = document.getElementById('recurrenceModalTitle');
    const modalMessage = document.getElementById('recurrenceModalMessage');
    const closeModalBtn = document.getElementById('closeRecurrenceModal');
    const ackModalBtn = document.getElementById('ackRecurrenceModal');

    // 🚨 Fallback: se qualquer um dos elementos não existe, não faz nada
    if (!recurrenceSelect || !modal || !modalTitle || !modalMessage || !closeModalBtn || !ackModalBtn) {
      console.warn('[RecurrenceModal] Required modal elements not found in DOM.');
      return;
    }

    const messages = {
      'weekly': {
        title: "Weekly Recurrence",
        message: "The Weekly recurrence will execute the chosen service weekly at the specified address until the contract is concluded or cancelled. Payment will be processed 48h before each service."
      },
      'fortnightly': {
        title: "Fortnightly Recurrence",
        message: "The Fortnightly recurrence will execute the service every 15 days. Payment will be processed 48h before each service."
      },
      'monthly': {
        title: "Monthly Recurrence",
        message: "The Monthly recurrence will execute the service every 30 days. Payment will be processed 48h before each service."
      }
    };

    function showModal(title, message) {
      modalTitle.textContent = title;
      modalMessage.textContent = message;
      modal.style.display = 'flex';
    }

    function closeModal() {
      modal.style.display = 'none';
    }

    // ⏳ Quando o usuário muda a recorrência
    recurrenceSelect.addEventListener('change', function () {
      const value = this.value;
      if (messages[value]) {
        showModal(messages[value].title, messages[value].message);
      }
    });

    // 👌 Fechar modal com botão ou X
    closeModalBtn.addEventListener('click', closeModal);
    ackModalBtn.addEventListener('click', closeModal);
  });
})();
