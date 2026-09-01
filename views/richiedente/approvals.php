<?php
/** Coda di approvazione: obiettivo tre settimane in meno di 30 secondi. */
use App\Core\{Csrf, View}; use App\Support\{Ui, Week};
?>
<h1 class="h1">Approvazioni <?php if ($pending): ?><span class="badge badge--amber"><?= count($pending) ?></span><?php endif; ?></h1>

<?php if (!$pending): ?>
  <div class="card"><div class="empty">
    <div class="empty__icon">✅</div>
    Tutto in ordine, nessuna settimana in attesa.
  </div></div>
<?php else: ?>
  <?php foreach ($pending as $ts): ?>
    <div class="card">
      <div class="card__body">
        <div style="display:flex;justify-content:space-between;gap:8px">
          <div>
            <div class="list__title">Settimana <?= (int) $ts['iso_week'] ?></div>
            <div class="list__sub"><?= View::e(Week::label((int) $ts['iso_year'], (int) $ts['iso_week'])) ?></div>
          </div>
          <?= Ui::timesheetBadge($ts['status']) ?>
        </div>
        <div class="list__sub mt"><?= View::e($ts['provider_name']) ?> · <?= View::e($ts['resource_title'] ?? $ts['contract_code']) ?></div>
        <div class="kv mt">
          <span class="kv__k"><?= View::e(Ui::quantity($ts['total_quantity'], $ts['unit'])) ?></span>
          <span class="kv__v"><?= View::money((float) $ts['total_quantity'] * (float) $ts['agreed_rate']) ?></span>
        </div>
      </div>
      <div class="card__foot actions">
        <a class="btn btn--ghost btn--sm" href="<?= View::url('/rendicontazione/' . $ts['id']) ?>">Dettaglio</a>
        <form method="post" action="<?= View::url('/rendicontazione/' . $ts['id'] . '/approva') ?>" style="flex:1">
          <?= Csrf::field() ?>
          <input type="hidden" name="back" value="/richiedente/approvazioni">
          <button class="btn btn--success btn--sm btn--block">✓ Approva</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($recent): ?>
  <h2 class="h3 mt-lg">Storico</h2>
  <div class="card"><ul class="list">
    <?php foreach ($recent as $ts): ?>
      <li><a class="list__item" href="<?= View::url('/rendicontazione/' . $ts['id']) ?>">
        <div class="list__main">
          <div class="list__title">Sett. <?= (int) $ts['iso_week'] ?> · <?= View::e($ts['provider_name']) ?></div>
          <div class="list__sub"><?= View::e(Ui::quantity($ts['total_quantity'], $ts['unit'])) ?>
            <?= $ts['amount'] ? '· ' . View::money($ts['amount']) : '' ?></div>
        </div>
        <?= Ui::timesheetBadge($ts['status']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>
