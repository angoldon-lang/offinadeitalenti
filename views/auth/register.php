<?php use App\Core\{Csrf, View};
$isOfferente = $type === 'OFFERENTE';
$old = $old ?: [];
?>
<div class="card card--pad">
  <h1 class="h2"><?= $isOfferente ? 'Registrati come Offerente' : 'Registrati come Richiedente' ?></h1>
  <p class="muted small">
    <?= $isOfferente
        ? 'Metterai a catalogo i profili tecnici della tua struttura.'
        : 'Cercherai e prenoterai competenze per i tuoi progetti.' ?>
  </p>

  <form method="post" action="<?= View::url('/registrati') ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="type" value="<?= View::e($type) ?>">

    <div class="field">
      <label for="legal_name">Ragione sociale *</label>
      <input type="text" id="legal_name" name="legal_name" required value="<?= View::e($old['legal_name'] ?? '') ?>">
    </div>
    <div class="row">
      <div class="field">
        <label for="vat_number">Partita IVA</label>
        <input type="text" id="vat_number" name="vat_number" inputmode="numeric" value="<?= View::e($old['vat_number'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="sector">Settore</label>
        <input type="text" id="sector" name="sector" value="<?= View::e($old['sector'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label for="full_name">Referente *</label>
      <input type="text" id="full_name" name="full_name" required autocomplete="name" value="<?= View::e($old['full_name'] ?? '') ?>">
    </div>
    <div class="row">
      <div class="field">
        <label for="email">Email *</label>
        <input type="email" id="email" name="email" required autocomplete="email" value="<?= View::e($old['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="phone">Telefono</label>
        <input type="text" id="phone" name="phone" inputmode="tel" value="<?= View::e($old['phone'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label for="password">Password *</label>
      <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password">
      <div class="field__hint">Almeno 10 caratteri.</div>
    </div>
    <div class="field">
      <label for="password_confirm">Conferma password *</label>
      <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
    </div>

    <button class="btn btn--primary btn--block">Crea account</button>
    <p class="muted small mt center">
      L'accesso viene attivato manualmente dal nostro team dopo la verifica dei dati.
    </p>
  </form>
</div>
