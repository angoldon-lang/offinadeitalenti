<?php use App\Core\View; use App\Support\Ui; ?>
<h1 class="h1">Notifiche</h1>
<?php if (!$items): ?>
  <div class="card"><div class="empty"><div class="empty__icon">🔔</div>Nessuna notifica.</div></div>
<?php else: ?>
  <div class="card"><ul class="list">
    <?php foreach ($items as $n): ?>
      <li class="list__item">
        <div class="list__main">
          <div class="list__title"><?= View::e($n['title']) ?></div>
          <?php if ($n['body']): ?><div class="list__sub"><?= View::e($n['body']) ?></div><?php endif; ?>
        </div>
        <div class="right">
          <div class="muted small nowrap"><?= View::e(Ui::ago($n['created_at'])) ?></div>
          <?php if ($n['link']): ?><a class="small" href="<?= View::url($n['link']) ?>">Apri</a><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>
