<?php use App\Core\{Auth, View}; use App\Domain\Enums; ?>
<h1 class="h1">Contratti</h1>
<?php if (in_array(Auth::role(), ['OFFERENTE', 'ADMIN'], true)): ?>
  <a class="btn btn--primary btn--block mb" href="<?= View::url('/contratti/nuovo') ?>">+ Nuovo contratto</a>
<?php endif; ?>
<a class="btn btn--ghost btn--block mb" href="<?= View::url('/fatture') ?>">Vai alle fatture →</a>

<?php if (!$contracts): ?>
  <div class="card"><div class="empty"><div class="empty__icon">📄</div>Nessun contratto.</div></div>
<?php else: ?>
  <div class="card"><ul class="list">
    <?php foreach ($contracts as $c): ?>
      <li><a class="list__item" href="<?= View::url('/contratti/' . $c['id']) ?>">
        <div class="list__main">
          <div class="list__title"><?= View::e($c['code']) ?> · <?= View::e($c['resource_title'] ?? '—') ?></div>
          <div class="list__sub">
            <?= View::e($c['provider_name']) ?> → <?= View::e($c['client_name']) ?><br>
            <?= View::date($c['start_date']) ?> – <?= View::date($c['end_date']) ?> ·
            <?= View::money($c['agreed_rate']) ?> <?= View::e(Enums::RATE_UNIT_SHORT[$c['rate_unit']] ?? '') ?>
          </div>
        </div>
        <span class="badge badge--<?= $c['status'] === 'ACTIVE' ? 'emerald' : 'slate' ?>">
          <?= View::e(Enums::label(Enums::CONTRACT_STATUS, $c['status'])) ?>
        </span>
      </a></li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>
