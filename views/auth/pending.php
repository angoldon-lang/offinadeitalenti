<?php use App\Core\View; ?>
<div class="card card--pad center">
  <div class="empty__icon" aria-hidden="true">⏳</div>
  <h1 class="h2">Profilo in attivazione</h1>
  <p class="muted">
    Stiamo verificando i dati di <strong><?= View::e($user['org_name'] ?? '') ?></strong>.
    Ti scriviamo a <?= View::e($user['email']) ?> appena l'accesso e' attivo, di norma entro 24 ore lavorative.
  </p>
  <p class="muted small">
    L'attivazione e la durata dell'account sono impostate manualmente dal nostro team
    sulla base del contratto sottoscritto.
  </p>
  <a class="btn btn--ghost" href="<?= View::url('/logout') ?>">Esci</a>
</div>
