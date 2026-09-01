<?php use App\Core\{Auth, Csrf, View}; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1">Fattura <?= View::e($invoice['number'] ?: '—') ?></h1>

<div class="card card--pad">
  <div class="kv"><span class="kv__k">Fornitore</span><span class="kv__v"><?= View::e($invoice['provider_name']) ?></span></div>
  <div class="kv"><span class="kv__k">Cliente</span><span class="kv__v"><?= View::e($invoice['client_name']) ?></span></div>
  <div class="kv"><span class="kv__k">Periodo</span><span class="kv__v"><?= View::date($invoice['period_start']) ?> – <?= View::date($invoice['period_end']) ?></span></div>
  <div class="kv"><span class="kv__k">Imponibile</span><span class="kv__v"><?= View::money($invoice['amount_net']) ?></span></div>
  <div class="kv"><span class="kv__k">IVA <?= View::qty($invoice['vat_rate']) ?>%</span>
    <span class="kv__v"><?= View::money((float) $invoice['amount_total'] - (float) $invoice['amount_net']) ?></span></div>
  <div class="kv"><span class="kv__k">Totale</span><span class="kv__v"><?= View::money($invoice['amount_total']) ?></span></div>
  <div class="kv"><span class="kv__k">Scadenza</span><span class="kv__v"><?= View::date($invoice['due_date']) ?></span></div>
  <div class="kv"><span class="kv__k">Stato</span><span class="kv__v"><?= Ui::paymentBadge($invoice['payment_status']) ?></span></div>
  <?php if ($invoice['paid_at']): ?>
    <div class="kv"><span class="kv__k">Pagata il</span><span class="kv__v"><?= View::date($invoice['paid_at']) ?></span></div>
  <?php endif; ?>
</div>

<?php if ($invoice['storage_key']): ?>
  <a class="btn btn--ghost btn--block mb" href="<?= View::url('/fatture/' . $invoice['id'] . '/scarica') ?>">Scarica il PDF</a>
<?php elseif (Auth::role() === 'OFFERENTE' || Auth::isAdmin()): ?>
  <form class="card card--pad" method="post" enctype="multipart/form-data"
        action="<?= View::url('/fatture/' . $invoice['id'] . '/documento') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="document">Carica il PDF della fattura</label>
      <input type="file" id="document" name="document" accept="application/pdf" required>
    </div>
    <button class="btn btn--primary btn--block btn--sm">Carica</button>
  </form>
<?php endif; ?>

<div class="card">
  <div class="card__head"><h2 class="h3">Settimane incluse</h2></div>
  <ul class="list">
    <?php foreach ($timesheets as $ts): ?>
      <li><a class="list__item" href="<?= View::url('/rendicontazione/' . $ts['id']) ?>">
        <div class="list__main">
          <div class="list__title">Settimana <?= (int) $ts['iso_week'] ?> · <?= View::e($ts['resource_title'] ?? $ts['contract_code']) ?></div>
          <div class="list__sub"><?= View::e(Ui::quantity($ts['total_quantity'], $ts['unit'])) ?></div>
        </div>
        <strong class="nowrap"><?= View::money($ts['amount']) ?></strong>
      </a></li>
    <?php endforeach; ?>
  </ul>
</div>

<?php if (Auth::isAdmin()): ?>
  <div class="card card--pad">
    <h2 class="h3">Stato del pagamento</h2>
    <p class="muted small">Aggiornamento manuale: non esiste alcun collegamento automatico con la banca.</p>
    <form method="post" action="<?= View::url('/fatture/' . $invoice['id'] . '/stato') ?>">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="payment_status">Stato</label>
        <select id="payment_status" name="payment_status">
          <?php foreach (Enums::PAYMENT_STATUS as $k => $label): ?>
            <option value="<?= $k ?>" <?= $invoice['payment_status'] === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row">
        <div class="field"><label for="paid_at">Data pagamento</label>
          <input type="date" id="paid_at" name="paid_at" value="<?= View::e($invoice['paid_at'] ?? '') ?>"></div>
        <div class="field"><label for="paid_amount">Importo incassato</label>
          <input type="number" id="paid_amount" name="paid_amount" step="0.01" inputmode="decimal" value="<?= View::e($invoice['paid_amount'] ?? '') ?>"></div>
      </div>
      <button class="btn btn--primary btn--block">Aggiorna stato</button>
    </form>
  </div>
<?php endif; ?>
