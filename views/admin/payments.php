<?php use App\Core\View; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1">Stato pagamenti</h1>

<div class="stats">
  <?php foreach (['INVIATA' => 'Da incassare', 'PAGATA' => 'Incassato', 'SCADUTA' => 'Scaduto', 'CONTESTATA' => 'Contestato'] as $k => $label):
      $t = $totals[$k] ?? ['n' => 0, 'tot' => 0]; ?>
    <div class="stat <?= in_array($k, ['SCADUTA', 'CONTESTATA'], true) && $t['n'] ? 'stat--alert' : '' ?>">
      <div class="stat__n"><?= View::money($t['tot']) ?></div>
      <div class="stat__l"><?= $label ?> (<?= (int) $t['n'] ?>)</div>
    </div>
  <?php endforeach; ?>
</div>

<div class="filterbar">
  <a class="chipbtn <?= $filter === '' ? 'is-on' : '' ?>" href="<?= View::url('/admin/pagamenti') ?>">Tutte</a>
  <?php foreach (Enums::PAYMENT_STATUS as $k => $label): ?>
    <a class="chipbtn <?= $filter === $k ? 'is-on' : '' ?>" href="<?= View::url('/admin/pagamenti?stato=' . $k) ?>"><?= View::e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$invoices): ?>
  <div class="card"><div class="empty"><div class="empty__icon">💶</div>Nessuna fattura con questo filtro.</div></div>
<?php else: ?>
  <div class="card"><ul class="list">
    <?php foreach ($invoices as $i): ?>
      <li><a class="list__item" href="<?= View::url('/fatture/' . $i['id']) ?>">
        <div class="list__main">
          <div class="list__title"><?= View::e($i['number'] ?: 'senza numero') ?> · <?= View::money($i['amount_total']) ?></div>
          <div class="list__sub"><?= View::e($i['provider_name']) ?> → <?= View::e($i['client_name']) ?>
            <?= $i['due_date'] ? '· scad. ' . View::date($i['due_date']) : '' ?></div>
        </div>
        <?= Ui::paymentBadge($i['payment_status']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>
