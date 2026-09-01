<?php use App\Core\View; use App\Domain\Enums; use App\Support\{Ui, Week}; ?>
<h1 class="h1">Monitor rendicontazione</h1>

<div class="filterbar">
  <a class="chipbtn <?= $filter === '' ? 'is-on' : '' ?>" href="<?= View::url('/admin/monitor') ?>">Tutte</a>
  <?php foreach (Enums::TIMESHEET_STATUS as $k => $label): ?>
    <a class="chipbtn <?= $filter === $k ? 'is-on' : '' ?>" href="<?= View::url('/admin/monitor?stato=' . $k) ?>"><?= View::e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$timesheets): ?>
  <div class="card"><div class="empty"><div class="empty__icon">📅</div>Nessuna settimana con questo filtro.</div></div>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Settimana</th><th>Fornitore</th><th>Cliente</th><th>Quantita'</th><th>Importo</th><th>Stato</th></tr></thead>
        <tbody>
          <?php foreach ($timesheets as $ts): ?>
            <tr>
              <td><a href="<?= View::url('/rendicontazione/' . $ts['id']) ?>">
                <?= (int) $ts['iso_year'] ?>-<?= sprintf('%02d', $ts['iso_week']) ?></a></td>
              <td><?= View::e($ts['provider_name']) ?></td>
              <td><?= View::e($ts['client_name']) ?></td>
              <td><?= View::e(Ui::quantity($ts['total_quantity'], $ts['unit'])) ?></td>
              <td><?= $ts['amount'] ? View::money($ts['amount']) : '—' ?></td>
              <td><?= Ui::timesheetBadge($ts['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
