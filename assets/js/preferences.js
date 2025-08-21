document.addEventListener('DOMContentLoaded', function () {
  // Aplica a função a todos os checkboxes no carregamento inicial
  document.querySelectorAll('.preference-checkbox').forEach(cb => {
    togglePrefNote(cb);

    // Mostra/oculta nota e força atualização do total
    cb.addEventListener('change', () => {
      togglePrefNote(cb);

      // Garante que updateTotal (de app.js) seja chamado, se existir
      if (typeof updateTotal === 'function') {
        updateTotal();
      }
    });
  });
});

function togglePrefNote(checkbox) {
  const note = checkbox.getAttribute('data-note');
  const noteDiv = checkbox.closest('.preferences-field')?.querySelector('.preference-note');

  if (!noteDiv) return;

  if (!checkbox.checked && note && note !== 'null') {
    try {
      const noteObj = JSON.parse(note);
      noteDiv.textContent = noteObj.note || '';
      noteDiv.style.display = 'block';
    } catch (e) {
      noteDiv.textContent = note;
      noteDiv.style.display = 'block';
    }
  } else {
    noteDiv.style.display = 'none';
  }
}
