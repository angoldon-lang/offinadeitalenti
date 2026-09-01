<?php use App\Core\View; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1">Le mie richieste</h1>
<?php if (!$requests): ?>
  <div class="card"><div class="empty"><div class="empty__icon">📨</div>
    Nessuna richiesta inviata.<br><span class="small">Cerca una risorsa e premi "Richiedi".</span></div></div>
<?php else: ?>
  <div class="card"><ul class="list">
    <?php foreach ($requests as $q): ?>
      <li class="list__item">
        <div class="list__main">
          <div class="list__title"><?= View::e($q['resource_title']) ?></div>
          <div class="list__sub">
            <?= $q['provider_name'] ? View::e($q['provider_name']) : 'Fornitore anonimo' ?> ·
            <?= View::e(Ui::ago($q['created_at'])) ?>
          </div>
        </div>
        <span class="badge badge--<?= match ($q['status']) {
            'ACCEPTED', 'CONTRACTED' => 'emerald',
            'DECLINED', 'EXPIRED'    => 'rose',
            'REQUESTED'              => 'amber',
            default                  => 'slate' } ?>">
          <?= View::e(Enums::label(Enums::REQUEST_STATUS, $q['status'])) ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>
