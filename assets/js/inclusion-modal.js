document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('inclusionInfoModal');
  const modalTitle = document.getElementById('inclusionModalTitle');
  const modalMessage = document.getElementById('inclusionModalMessage');
  const closeBtn = document.getElementById('closeInclusionModal');
  const ackBtn = document.getElementById('ackInclusionModal');

  // Evento para botões "ⓘ"
  document.querySelectorAll('.info-icon').forEach(btn => {
    btn.addEventListener('click', () => {
      const title = btn.dataset.title || 'Info';
      const desc = btn.dataset.description || '';
      modalTitle.textContent = title;
      modalMessage.textContent = desc;
      modal.style.display = 'flex';
    });
  });

  const closeModal = () => {
    modal.style.display = 'none';
  };

  closeBtn.addEventListener('click', closeModal);
  ackBtn.addEventListener('click', closeModal);
});