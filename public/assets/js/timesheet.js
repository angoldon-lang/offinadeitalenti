/* =============================================================================
   Compilazione della settimana.
   Tre regole non negoziabili:
     1. il totale mostrato viene SEMPRE dal server, mai calcolato qui;
     2. ogni modifica e' salvata da sola, senza pulsante "Salva";
     3. senza rete si continua a lavorare: la coda locale si svuota al ritorno.
   ========================================================================== */
(function () {
  'use strict';

  var root = document.getElementById('ts-days');
  if (!root || root.dataset.editable !== '1') return;

  var endpoint = root.dataset.endpoint;
  var unit     = root.dataset.unit;
  var rate     = parseFloat(root.dataset.rate) || 0;
  var csrf     = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
  var queueKey = 'odt.ts.' + root.dataset.timesheet;

  var elTotal   = document.getElementById('ts-total');
  var elAmount  = document.getElementById('ts-amount');
  var elSave    = document.getElementById('ts-saveline');
  var elCQty    = document.getElementById('confirm-qty');
  var elCAmount = document.getElementById('confirm-amount');

  function fmtQty(n) { return String(Math.round(n * 100) / 100).replace('.', ','); }
  function fmtMoney(n) {
    return n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  }
  function buzz() { if (navigator.vibrate) navigator.vibrate(10); }

  function status(text, offline) {
    if (!elSave) return;
    elSave.textContent = text;
    elSave.classList.toggle('is-offline', !!offline);
  }

  /* Il numero che scorre e' il feedback che dice "ti ho capito". */
  function countUp(el, from, to, render) {
    if (!el) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = render(to); return; }
    var start = performance.now(), dur = 200;
    function frame(now) {
      var p = Math.min((now - start) / dur, 1);
      el.textContent = render(from + (to - from) * p);
      if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  var lastTotal = parseFloat(String(elTotal ? elTotal.textContent : '0').replace(',', '.')) || 0;

  function applyTotals(total, amount) {
    countUp(elTotal, lastTotal, total, fmtQty);
    countUp(elAmount, lastTotal * rate, amount, fmtMoney);
    lastTotal = total;
    if (elCQty)    elCQty.textContent    = fmtQty(total) + (unit === 'HOURLY' ? ' ore' : ' giorni');
    if (elCAmount) elCAmount.textContent = fmtMoney(amount);
  }

  // ---- coda offline (le modifiche non si perdono mai) ---------------------
  function loadQueue() {
    try { return JSON.parse(localStorage.getItem(queueKey) || '{}'); } catch (e) { return {}; }
  }
  function saveQueue(q) {
    try { localStorage.setItem(queueKey, JSON.stringify(q)); } catch (e) { /* quota piena: si prosegue online */ }
  }
  function enqueue(payload) {
    var q = loadQueue();
    q[payload.date] = payload;          // una sola voce per giorno: l'ultima vince
    saveQueue(q);
  }
  function dequeue(date) {
    var q = loadQueue();
    delete q[date];
    saveQueue(q);
  }

  function send(payload, silent) {
    var body = new URLSearchParams();
    body.set('_csrf', csrf);
    body.set('date', payload.date);
    body.set('quantity', payload.quantity);
    if (payload.day_type) body.set('day_type', payload.day_type);
    if (payload.note != null) body.set('note', payload.note);

    return fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().then(function (data) { return { ok: res.ok, status: res.status, data: data }; });
    }).then(function (r) {
      if (!r.ok) {
        // 409: la settimana e' stata inviata o approvata altrove.
        // Mai una sovrascrittura silenziosa: si dice cosa e' successo.
        dequeue(payload.date);
        status(r.data.error || 'Salvataggio non riuscito', true);
        if (r.status === 409) setTimeout(function () { location.reload(); }, 2500);
        return;
      }
      dequeue(payload.date);
      applyTotals(r.data.total, r.data.amount);
      if (!silent) status('salvata ora');
    }).catch(function () {
      enqueue(payload);
      status('salvata sul dispositivo · sincronizzo appena torna la rete', true);
    });
  }

  function flushQueue() {
    var q = loadQueue(), dates = Object.keys(q);
    if (!dates.length) return;
    status('sincronizzo…');
    dates.reduce(function (chain, d) {
      return chain.then(function () { return send(q[d], true); });
    }, Promise.resolve()).then(function () { status('sincronizzata'); });
  }

  window.addEventListener('online', flushQueue);
  if (navigator.onLine) flushQueue(); else status('offline — le modifiche restano sul dispositivo', true);

  // ---- interazione sulle righe -------------------------------------------
  var pending = {};
  function schedule(dateEl, payload) {
    clearTimeout(pending[payload.date]);
    status('salvo…');
    pending[payload.date] = setTimeout(function () { send(payload); }, 500);
  }

  root.addEventListener('click', function (e) {
    var row = e.target.closest('.day');
    if (!row) return;
    var date = row.dataset.date;

    // Segmented control 0 / mezza / intera giornata
    var seg = e.target.closest('.js-seg');
    if (seg) {
      row.querySelectorAll('.js-seg').forEach(function (b) { b.classList.remove('is-on'); });
      seg.classList.add('is-on');
      buzz();
      var q = parseFloat(seg.dataset.value);
      schedule(row, { date: date, quantity: q, day_type: q > 0 ? 'LAVORO' : null });
      return;
    }

    // Stepper a ore
    var step = e.target.closest('.js-step');
    if (step) {
      var input = row.querySelector('.js-qty');
      var val   = Math.max(0, Math.min(24, (parseFloat(input.value.replace(',', '.')) || 0) + parseFloat(step.dataset.delta)));
      input.value = fmtQty(val);
      buzz();
      schedule(row, { date: date, quantity: val, day_type: val > 0 ? 'LAVORO' : null });
      return;
    }

    // Casi fuori standard: tipo giornata e nota
    if (e.target.closest('.js-more')) { openDaySheet(row); }
  });

  root.addEventListener('change', function (e) {
    if (!e.target.classList.contains('js-qty')) return;
    var row = e.target.closest('.day');
    var val = Math.max(0, Math.min(24, parseFloat(e.target.value.replace(',', '.')) || 0));
    e.target.value = fmtQty(val);
    schedule(row, { date: row.dataset.date, quantity: val, day_type: val > 0 ? 'LAVORO' : null });
  });

  // ---- foglio "tipo giornata e nota" -------------------------------------
  var sheet    = document.getElementById('day-sheet');
  var selType  = document.getElementById('day-type');
  var inpQty   = document.getElementById('day-qty');
  var inpNote  = document.getElementById('day-note');
  var btnSave  = document.getElementById('day-save');
  var current  = null;

  function openDaySheet(row) {
    current = row;
    var label = row.querySelector('.day__label b');
    document.getElementById('day-sheet-title').textContent = label ? label.textContent.trim() : 'Giornata';

    if (inpQty) {
      var on = row.querySelector('.js-seg.is-on');
      inpQty.value = on ? on.dataset.value : '0';
    }
    // Nella variante a ore il campo quantita' del foglio non esiste:
    // la riga ha gia' il proprio stepper.
    inpNote.value = row.dataset.note || '';
    window.OdT.openSheet('day-sheet');
  }

  if (btnSave) {
    btnSave.addEventListener('click', function () {
      if (!current) return;
      var date = current.dataset.date;
      var qty  = inpQty ? Math.max(0, Math.min(24, parseFloat(String(inpQty.value).replace(',', '.')) || 0))
                        : parseFloat((current.querySelector('.js-qty') || { value: 0 }).value) || 0;

      // Rispecchia subito la scelta sulla riga, poi salva.
      var segs = current.querySelectorAll('.js-seg');
      if (segs.length) {
        segs.forEach(function (b) { b.classList.toggle('is-on', Math.abs(parseFloat(b.dataset.value) - qty) < 0.001); });
      }

      send({ date: date, quantity: qty, day_type: selType.value, note: inpNote.value });
      window.OdT.closeSheets();
      status('salvo…');
    });
  }
})();
