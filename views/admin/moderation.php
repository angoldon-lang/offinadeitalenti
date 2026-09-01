<?php use App\Core\View; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1">Moderazione</h1>
<?php if (!$resources): ?>
  <div class="card"><div class="empty"><div class="empty__icon">⏳</div>
    Nessun profilo in attesa. Coda vuota.</div></div>
<?php else: ?>
  <div class="card"><ul class="list">
    <?php foreach ($resources as $r): ?>
      <li><a class="list__item" href="<?= View::url('/admin/moderazione/' . $r['id']) ?>">
        <div class="list__main">
          <div class="list__title"><?= View::e($r['title']) ?></div>
          <div class="list__sub"><?= View::e($r['org_name']) ?> ·
            <?= View::e(Enums::label(Enums::SENIORITY, $r['seniority'])) ?> ·
            <?= View::e(Ui::ago($r['updated_at'])) ?></div>
        </div>
        <span aria-hidden="true">›</span>
      </a></li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>
