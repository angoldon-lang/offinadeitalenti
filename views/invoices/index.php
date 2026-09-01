<?php use App\Core\{Auth, View}; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1">Fatture</h1>
<?php if (Auth::role() === 'OFFERENTE'): ?>
  <a class="btn btn--primary btn--block mb" href="<?= View::url('/fatture/da-fatturare') ?>">Prepara una fattura</a>
<?php endif; ?>

<?php if ($totals): ?>
  <div class="stats">
    <?php foreach (['INVIATA' => 'Da incassare', 'PAGATA' => 'Incassato', 'SCADUTA' => 'Scaduto'] as $k => $label):
      $t = $totals[$k] ?? ['n' => 0, 'tot' => 0]; ?>
      <div class="stat <?= $k === 'SCADUTA' && $t['n'] ? 'stat--alert' : '' ?>">
        <div class="stat__n"><?= View::money($t['tot']) ?></div>
        <div class="stat__l"><?= $label ?> (<?= (int) $t['n'] ?>)</div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$invoices): ?>
  <div class="card"><div class="empty"><div class="empty__icon">💶</div>Nessuna fattura.</div></div>
<?php else: ?>
  <div class="card"><ul class="list">
    <?php foreach ($invoices as $i): ?>
      <li><a class="list__item" href="<?= View::url('/fatture/' . $i['id']) ?>">
        <div class="list__main">
          <div class="list__title"><?= View::e($i['number'] ?: 'senza numero') ?> · <?= View::money($i['amount_total']) ?></div>
          <div class="list__sub">
            <?= View::e($i['provider_name']) ?> → <?= View::e($i['client_name']) ?> ·
            <?= View::date($i['period_start']) ?>–<?= View::date($i['period_end']) ?>
          </div>
        </div>
        <?= Ui::paymentBadge($i['payment_status']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>
