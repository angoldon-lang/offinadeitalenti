<?php use App\Core\View; use App\Domain\Enums; use App\Support\{Ui, Week}; ?>
<h1 class="h1">Ciao<?= !empty($currentUser['full_name']) ? ', ' . View::e(explode(' ', $currentUser['full_name'])[0]) : '' ?></h1>

<?php /* La home apre su cosa fare oggi. I numeri vengono dopo. */ ?>
<?php if ($toFill): ?>
  <div class="card">
    <div class="card__head">
      <h2 class="h3">Settimane da compilare</h2>
      <span class="badge badge--amber"><?= (int) $toFillCount ?></span>
    </div>
    <ul class="list">
      <?php foreach ($toFill as $item): ?>
        <li>
          <a class="list__item" href="<?= View::url('/rendicontazione/' . $item['contract']['id'] . '/' . $item['week']['iso_year'] . '/' . $item['week']['iso_week']) ?>">
            <div class="list__main">
              <div class="list__title">Settimana <?= (int) $item['week']['iso_week'] ?> · <?= View::e($item['contract']['client_name']) ?></div>
              <div class="list__sub"><?= View::e(Week::label((int) $item['week']['iso_year'], (int) $item['week']['iso_week'])) ?></div>
            </div>
            <?= Ui::timesheetBadge($item['timesheet']['status'] ?? 'DRAFT') ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><div class="stat__n"><?= count($resources) ?></div><div class="stat__l">Risorse a catalogo</div></div>
  <div class="stat <?= $pendingRequests ? 'stat--alert' : '' ?>">
    <div class="stat__n"><?= (int) $pendingRequests ?></div><div class="stat__l">Richieste da evadere</div>
  </div>
</div>

<div class="actions mb">
  <a class="btn btn--primary" href="<?= View::url('/offerente/risorse/nuova') ?>">+ Nuova risorsa</a>
  <a class="btn btn--ghost" href="<?= View::url('/offerente/richieste') ?>">Richieste</a>
  <a class="btn btn--ghost" href="<?= View::url('/fatture/da-fatturare') ?>">Da fatturare</a>
</div>

<?php if ($recent): ?>
<div class="card">
  <div class="card__head"><h2 class="h3">Ultime settimane</h2>
    <a class="small" href="<?= View::url('/offerente/rendicontazione') ?>">Tutte</a></div>
  <ul class="list">
    <?php foreach ($recent as $ts): ?>
      <li><a class="list__item" href="<?= View::url('/rendicontazione/' . $ts['id']) ?>">
        <div class="list__main">
          <div class="list__title">Sett. <?= (int) $ts['iso_week'] ?> · <?= View::e($ts['client_name']) ?></div>
          <div class="list__sub"><?= View::e(Ui::quantity($ts['total_quantity'], $ts['unit'])) ?>
            <?= $ts['amount'] ? '· ' . View::money($ts['amount']) : '' ?></div>
        </div>
        <?= Ui::timesheetBadge($ts['status']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
