<?php use App\Core\{Auth, Csrf, View}; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1"><?= View::e($contract['code']) ?></h1>

<div class="card card--pad">
  <div class="kv"><span class="kv__k">Fornitore</span><span class="kv__v"><?= View::e($contract['provider_name']) ?></span></div>
  <div class="kv"><span class="kv__k">Cliente</span><span class="kv__v"><?= View::e($contract['client_name']) ?></span></div>
  <div class="kv"><span class="kv__k">Risorsa</span><span class="kv__v"><?= View::e($contract['resource_title'] ?? '—') ?></span></div>
  <div class="kv"><span class="kv__k">Periodo</span><span class="kv__v"><?= View::date($contract['start_date']) ?> – <?= View::date($contract['end_date']) ?></span></div>
  <div class="kv"><span class="kv__k">Tariffa concordata</span>
    <span class="kv__v"><?= View::money($contract['agreed_rate']) ?> <?= View::e(Enums::RATE_UNIT_SHORT[$contract['rate_unit']] ?? '') ?></span></div>
  <div class="kv"><span class="kv__k">Stato</span><span class="kv__v"><?= View::e(Enums::label(Enums::CONTRACT_STATUS, $contract['status'])) ?></span></div>
</div>

<?php if ($contract['status'] === 'ACTIVE' && $contract['timesheet_required']): ?>
  <a class="btn btn--primary btn--block mb" href="<?= View::url('/offerente/rendicontazione') ?>">Vai alla rendicontazione</a>
<?php endif; ?>

<div class="card">
  <div class="card__head"><h2 class="h3">Documenti</h2></div>
  <?php if (!$documents): ?>
    <div class="empty small">Nessun documento caricato.</div>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($documents as $d): ?>
        <li class="list__item">
          <div class="list__main">
            <div class="list__title"><?= View::e(Enums::label(Enums::CONTRACT_DOC_TYPE, $d['doc_type'])) ?> v<?= (int) $d['version'] ?></div>
            <div class="list__sub"><?= View::e($d['file_name']) ?> · <?= View::date($d['created_at']) ?>
              <?= $d['signed_at'] ? '· firmato ' . View::date($d['signed_at']) : '' ?></div>
          </div>
          <a class="btn btn--ghost btn--sm" href="<?= View::url('/documenti/' . $d['id']) ?>">Scarica</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="card__foot">
    <form method="post" action="<?= View::url('/contratti/' . $contract['id'] . '/documento') ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <div class="row">
        <div class="field">
          <label for="doc_type">Tipo</label>
          <select id="doc_type" name="doc_type">
            <?php foreach (Enums::CONTRACT_DOC_TYPE as $k => $label): ?>
              <option value="<?= $k ?>"><?= View::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="signed_at">Firmato il</label>
          <input type="date" id="signed_at" name="signed_at">
        </div>
      </div>
      <div class="field">
        <label for="document">PDF (max 20 MB) *</label>
        <input type="file" id="document" name="document" accept="application/pdf" required>
      </div>
      <div class="field">
        <label for="visibility">Visibilita'</label>
        <select id="visibility" name="visibility">
          <?php foreach (Enums::DOC_VISIBILITY as $k => $label): ?>
            <option value="<?= $k ?>"><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn--primary btn--block btn--sm">Carica documento</button>
    </form>
  </div>
</div>

<?php if ($timesheets): ?>
  <div class="card">
    <div class="card__head"><h2 class="h3">Settimane rendicontate</h2></div>
    <ul class="list">
      <?php foreach (array_slice(array_reverse($timesheets, true), 0, 10) as $ts): ?>
        <li><a class="list__item" href="<?= View::url('/rendicontazione/' . $ts['id']) ?>">
          <div class="list__main">
            <div class="list__title">Settimana <?= (int) $ts['iso_week'] ?></div>
            <div class="list__sub"><?= View::e(Ui::quantity($ts['total_quantity'], $ts['unit'])) ?></div>
          </div>
          <?= Ui::timesheetBadge($ts['status']) ?>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
