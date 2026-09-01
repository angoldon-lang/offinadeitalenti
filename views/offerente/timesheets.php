<?php use App\Core\View; use App\Support\{Ui, Week}; ?>
<h1 class="h1">Rendicontazione</h1>

<?php if (!$contracts): ?>
  <div class="card"><div class="empty">
    <div class="empty__icon">📅</div>
    Nessun contratto attivo.<br>
    <span class="small">La rendicontazione si attiva automaticamente quando un contratto diventa attivo.</span>
  </div></div>
<?php else: ?>
  <?php $byContract = [];
        foreach ($rows as $row) { $byContract[$row['contract']['id']][] = $row; } ?>

  <?php foreach ($byContract as $contractId => $weeks):
        $contract = $weeks[0]['contract']; ?>
    <div class="card">
      <div class="card__head">
        <div>
          <div class="list__title"><?= View::e($contract['client_name']) ?></div>
          <div class="list__sub">
            <?= View::e($contract['resource_title'] ?? $contract['code']) ?> ·
            <?= View::money($contract['agreed_rate']) ?> <?= View::e(\App\Domain\Enums::RATE_UNIT_SHORT[$contract['rate_unit']] ?? '') ?>
          </div>
        </div>
      </div>
      <ul class="list">
        <?php foreach (array_slice($weeks, 0, 12) as $w): ?>
          <li><a class="list__item"
                 href="<?= View::url($w['timesheet']
                        ? '/rendicontazione/' . $w['timesheet']['id']
                        : '/rendicontazione/' . $contractId . '/' . $w['iso_year'] . '/' . $w['iso_week']) ?>">
            <div class="list__main">
              <div class="list__title">Settimana <?= (int) $w['iso_week'] ?></div>
              <div class="list__sub"><?= View::e(Week::label((int) $w['iso_year'], (int) $w['iso_week'])) ?>
                <?php if ($w['timesheet']): ?>
                  · <?= View::e(Ui::quantity($w['timesheet']['total_quantity'], $w['timesheet']['unit'])) ?>
                <?php endif; ?>
              </div>
            </div>
            <?= Ui::timesheetBadge($w['timesheet']['status'] ?? 'DRAFT') ?>
          </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
