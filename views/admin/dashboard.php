<?php use App\Core\View; use App\Support\{Ui, Week}; ?>
<h1 class="h1">Back-office</h1>

<div class="stats">
  <a class="stat <?= $counts['pending_orgs'] ? 'stat--alert' : '' ?>" href="<?= View::url('/admin/organizzazioni?stato=PENDING_APPROVAL') ?>">
    <div class="stat__n"><?= (int) $counts['pending_orgs'] ?></div><div class="stat__l">Account da attivare</div></a>
  <a class="stat <?= $counts['pending_resources'] ? 'stat--alert' : '' ?>" href="<?= View::url('/admin/moderazione') ?>">
    <div class="stat__n"><?= (int) $counts['pending_resources'] ?></div><div class="stat__l">Profili da moderare</div></a>
  <a class="stat" href="<?= View::url('/admin/monitor?stato=SUBMITTED') ?>">
    <div class="stat__n"><?= (int) $counts['to_approve'] ?></div><div class="stat__l">Settimane in attesa</div></a>
  <a class="stat <?= $counts['overdue_invoices'] ? 'stat--alert' : '' ?>" href="<?= View::url('/admin/pagamenti?stato=SCADUTA') ?>">
    <div class="stat__n"><?= (int) $counts['overdue_invoices'] ?></div><div class="stat__l">Fatture scadute</div></a>
</div>

<?php if ($expiring): ?>
  <div class="card">
    <div class="card__head"><h2 class="h3">Account in scadenza (30 gg)</h2>
      <span class="badge badge--amber"><?= count($expiring) ?></span></div>
    <ul class="list">
      <?php foreach ($expiring as $o): $d = \App\Repository\OrganizationRepository::daysLeft($o['access_expires_at']); ?>
        <li><a class="list__item" href="<?= View::url('/admin/organizzazioni/' . $o['id']) ?>">
          <div class="list__main">
            <div class="list__title"><?= View::e($o['legal_name']) ?></div>
            <div class="list__sub">Scade il <?= View::date($o['access_expires_at']) ?></div>
          </div>
          <span class="badge badge--<?= $d <= 7 ? 'rose' : 'amber' ?>"><?= $d >= 0 ? $d . ' gg' : 'scaduto' ?></span>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($stale): ?>
  <div class="card">
    <div class="card__head"><h2 class="h3">Settimane ferme da oltre 5 giorni</h2></div>
    <ul class="list">
      <?php foreach ($stale as $ts): ?>
        <li><a class="list__item" href="<?= View::url('/rendicontazione/' . $ts['id']) ?>">
          <div class="list__main">
            <div class="list__title">Sett. <?= (int) $ts['iso_week'] ?> · <?= View::e($ts['provider_name']) ?> → <?= View::e($ts['client_name']) ?></div>
            <div class="list__sub"><?= View::e(Week::label((int) $ts['iso_year'], (int) $ts['iso_week'])) ?></div>
          </div>
          <?= Ui::timesheetBadge($ts['status']) ?>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="actions">
  <a class="btn btn--ghost btn--sm" href="<?= View::url('/admin/organizzazioni') ?>">Organizzazioni</a>
  <a class="btn btn--ghost btn--sm" href="<?= View::url('/contratti') ?>">Contratti</a>
  <a class="btn btn--ghost btn--sm" href="<?= View::url('/admin/audit') ?>">Audit log</a>
</div>
