/* Comportamenti condivisi: fogli dal basso, filtri, registrazione del service worker. */
(function () {
  'use strict';

  // ---- bottom sheet -------------------------------------------------------
  function openSheet(id) {
    var el = document.getElementById(id);
    if (el) { el.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
  }
  function closeSheets() {
    document.querySelectorAll('.sheet.is-open').forEach(function (s) { s.classList.remove('is-open'); });
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-sheet-open]');
    if (open) { openSheet(open.getAttribute('data-sheet-open')); return; }
    if (e.target.closest('[data-sheet-close]')) { closeSheets(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSheets(); });

  window.OdT = { openSheet: openSheet, closeSheets: closeSheets };

  // ---- ricerca: i filtri si applicano da soli, senza pulsante "Cerca" -----
  var form = document.getElementById('search-form');
  if (form) {
    var timer = null;
    form.addEventListener('input', function (e) {
      var delay = e.target.type === 'search' || e.target.type === 'text' ? 300 : 0;
      clearTimeout(timer);
      timer = setTimeout(function () { form.submit(); }, delay);
    });
    form.addEventListener('change', function () { form.submit(); });
  }

  // ---- PWA ---------------------------------------------------------------
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(document.body.dataset.swPath || '/sw.js').catch(function () { /* offline: riprova al prossimo avvio */ });
    });
  }
})();
