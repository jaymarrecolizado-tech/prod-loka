/**
 * LOKA — prevent double-submit while sync emails/notifications run.
 * Opt-in: <form data-loka-busy-submit="1">
 */
(function () {
  function ensureDialog() {
    var dlg = document.getElementById('lokaBusySubmitModal');
    if (dlg) return dlg;

    dlg = document.createElement('dialog');
    dlg.id = 'lokaBusySubmitModal';
    dlg.className = 'modal';
    dlg.innerHTML =
      '<div class="modal-box text-center">' +
      '<span class="loading loading-spinner loading-lg text-primary mb-3"></span>' +
      '<h3 class="font-semibold text-lg">Submitting…</h3>' +
      '<p class="py-2 text-sm opacity-70">Sending notifications. Please wait — do not click again.</p>' +
      '</div>' +
      '<form method="dialog" class="modal-backdrop"><button disabled aria-hidden="true">close</button></form>';
    document.body.appendChild(dlg);
    return dlg;
  }

  function armForm(form) {
    if (form.dataset.lokaBusyBound === '1') return;
    form.dataset.lokaBusyBound = '1';

    form.addEventListener('submit', function (e) {
      if (form.dataset.lokaSubmitting === '1') {
        e.preventDefault();
        return;
      }
      form.dataset.lokaSubmitting = '1';

      var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
      buttons.forEach(function (btn) {
        btn.disabled = true;
        if (btn.tagName === 'BUTTON' && !btn.dataset.lokaBusyOriginal) {
          btn.dataset.lokaBusyOriginal = btn.innerHTML;
          btn.innerHTML = 'Please wait…';
        }
      });

      try {
        var dlg = ensureDialog();
        if (typeof dlg.showModal === 'function') {
          dlg.showModal();
        }
      } catch (err) {
        /* ignore */
      }
    });
  }

  function init() {
    document.querySelectorAll('form[data-loka-busy-submit="1"]').forEach(armForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
