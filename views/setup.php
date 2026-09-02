<?php use App\Core\{Csrf, View}; ?>
<div class="card card--pad">
  <h1 class="h2">Installazione</h1>
  <p class="muted">
    Il database risponde correttamente. Crea l'utente amministratore: sara' l'unico account con
    accesso al back-office finche' non ne aggiungerai altri.
  </p>

  <?php if ($skills === 0): ?>
    <div class="flash flash--error">
      Attenzione: la tabella delle competenze e' vuota. Esegui <strong>03-skills.sql</strong> in
      phpMyAdmin, altrimenti gli offerenti non potranno selezionare alcuna skill.
    </div>
  <?php else: ?>
    <p class="muted small">✓ <?= (int) $skills ?> competenze caricate.</p>
  <?php endif; ?>

  <form method="post" action="<?= View::url('/installazione') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="full_name">Nome e cognome *</label>
      <input type="text" id="full_name" name="full_name" required autocomplete="name" autofocus>
    </div>
    <div class="field">
      <label for="email">Email *</label>
      <input type="email" id="email" name="email" required autocomplete="email">
    </div>
    <div class="field">
      <label for="password">Password *</label>
      <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password">
      <div class="field__hint">Almeno 12 caratteri. Non verra' scritta in nessun file.</div>
    </div>
    <div class="field">
      <label for="password_confirm">Conferma password *</label>
      <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
    </div>
    <button class="btn btn--primary btn--block">Crea amministratore ed entra</button>
  </form>

  <p class="muted small mt">
    Questa pagina si disattiva da sola non appena l'amministratore esiste: da quel momento
    risponde 404. Non c'e' nulla da cancellare dal server.
  </p>
</div>
