<?php use App\Core\View; use App\Support\Week; ?>
<h1 class="h1">Le mie ore</h1>
<p class="muted">Settimana <?= (int) $isoWeek ?> · <?= View::e(Week::label((int) $isoYear, (int) $isoWeek)) ?></p>

<?php if (!$contracts): ?>
  <div class="card"><div class="empty"><div class="empty__icon">📅</div>
    Nessun contratto attivo a te associato.</div></div>
<?php else: ?>
  <div class="card"><ul class="list">
    <?php foreach ($contracts as $c): ?>
      <li><a class="list__item" href="<?= View::url('/rendicontazione/' . $c['id'] . '/' . $isoYear . '/' . $isoWeek) ?>">
        <div class="list__main">
          <div class="list__title"><?= View::e($c['client_name']) ?></div>
          <div class="list__sub"><?= View::e($c['resource_title'] ?? $c['code']) ?></div>
        </div>
        <span aria-hidden="true">›</span>
      </a></li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>
