<?php
/** Riepilogo di fatturazione: il sistema conta, non emette documenti fiscali. */
use App\Core\{Csrf, View}; use App\Support\{Ui, Week};
?>
<h1 class="h1">Da fatturare</h1>

<form method="get" class="card card--pad" action="<?= View::url('/fatture/da-fatturare') ?>">
  <div class="row">
    <div class="field"><label for="da">Dal</label><input type="date" id="da" name="da" value="<?= View::e($from) ?>"></div>
    <div class="field"><label for="a">Al</label><input type="date" id="a" name="a" value="<?= View::e($to) ?>"></div>
  </div>
  <button class="btn btn--ghost btn--block btn--sm">Aggiorna periodo</button>
</form>

<?php if (!$byClient): ?>
  <div class="card"><div class="empty"><div class="empty__icon">🧾</div>
    Nessuna settimana approvata da fatturare in questo periodo.</div></div>
<?php else: foreach ($byClient as $clientId => $group): ?>
  <form method="post" action="<?= View::url('/fatture/crea') ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <div class="card">
      <div class="card__head">
        <h2 class="h3"><?= View::e($group['client_name']) ?></h2>
        <strong><?= View::money($group['total']) ?></strong>
      </div>
      <ul class="list">
        <?php foreach ($group['rows'] as $ts): ?>
          <li class="list__item">
            <input type="checkbox" name="timesheets[]" value="<?= View::e($ts['id']) ?>" checked
                   aria-label="Includi settimana <?= (int) $ts['iso_week'] ?>" style="width:22px;height:22px;flex:0 0 auto">
            <div class="list__main">
              <div class="list__title">Settimana <?= (int) $ts['iso_week'] ?> · <?= View::e($ts['resource_title'] ?? $ts['contract_code']) ?></div>
              <div class="list__sub"><?= View::e(Week::label((int) $ts['iso_year'], (int) $ts['iso_week'])) ?> ·
                <?= View::e(Ui::quantity($ts['total_quantity'], $ts['rate_unit'])) ?> ×
                <?= View::money($ts['rate_snapshot']) ?></div>
            </div>
            <strong class="nowrap"><?= View::money($ts['amount']) ?></strong>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="card__foot">
        <div class="row">
          <div class="field"><label>Numero fattura</label><input type="text" name="number" placeholder="2026/114"></div>
          <div class="field"><label>IVA %</label><input type="number" name="vat_rate" value="22" step="0.5" inputmode="decimal"></div>
        </div>
        <div class="row">
          <div class="field"><label>Emissione</label><input type="date" name="issue_date"></div>
          <div class="field"><label>Scadenza</label><input type="date" name="due_date"></div>
        </div>
        <div class="field">
          <label>PDF della fattura (facoltativo ora)</label>
          <input type="file" name="document" accept="application/pdf">
        </div>
        <button class="btn btn--primary btn--block">Crea fattura per <?= View::e($group['client_name']) ?></button>
      </div>
    </div>
  </form>
<?php endforeach; endif; ?>
