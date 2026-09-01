<?php use App\Core\View; use App\Domain\Enums; use App\Repository\OrganizationRepository; use App\Support\Ui; ?>
<h1 class="h1">Organizzazioni</h1>

<div class="filterbar">
  <a class="chipbtn <?= $filterStatus === '' ? 'is-on' : '' ?>" href="<?= View::url('/admin/organizzazioni') ?>">Tutte</a>
  <?php foreach (Enums::ORG_STATUS as $k => $label): ?>
    <a class="chipbtn <?= $filterStatus === $k ? 'is-on' : '' ?>"
       href="<?= View::url('/admin/organizzazioni?stato=' . $k) ?>"><?= View::e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="card"><ul class="list">
  <?php foreach ($orgs as $o): $d = OrganizationRepository::daysLeft($o['access_expires_at']); ?>
    <li><a class="list__item" href="<?= View::url('/admin/organizzazioni/' . $o['id']) ?>">
      <div class="list__main">
        <div class="list__title"><?= View::e($o['legal_name']) ?></div>
        <div class="list__sub">
          <?= View::e(Enums::label(Enums::ORG_TYPES, $o['type'])) ?> ·
          <?= $o['access_expires_at']
                ? 'scade ' . View::date($o['access_expires_at']) . ($d !== null ? ' (' . $d . ' gg)' : '')
                : 'nessuna scadenza impostata' ?>
        </div>
      </div>
      <?= Ui::orgBadge($o['status']) ?>
    </a></li>
  <?php endforeach; ?>
</ul></div>
