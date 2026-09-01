<?php use App\Core\{Csrf, View}; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1"><?= View::e($org['legal_name']) ?></h1>
<p><?= Ui::orgBadge($org['status']) ?>
   <span class="muted small"><?= View::e(Enums::label(Enums::ORG_TYPES, $org['type'])) ?></span></p>

<div class="card card--pad">
  <div class="kv"><span class="kv__k">P.IVA</span><span class="kv__v"><?= View::e($org['vat_number'] ?: '—') ?></span></div>
  <div class="kv"><span class="kv__k">Settore</span><span class="kv__v"><?= View::e($org['sector'] ?: '—') ?></span></div>
  <div class="kv"><span class="kv__k">Scadenza account</span>
    <span class="kv__v"><?= View::date($org['access_expires_at']) ?>
      <?= $daysLeft !== null ? '(' . $daysLeft . ' gg)' : '' ?></span></div>
  <div class="kv"><span class="kv__k">Rif. contratto</span><span class="kv__v"><?= View::e($org['external_contract_ref'] ?: '—') ?></span></div>
</div>

<div class="card card--pad">
  <h2 class="h3">Durata del profilo</h2>
  <p class="muted small">La scadenza si imposta a mano, sulla base del contratto cartaceo.
     Alla scadenza l'account entra in tolleranza per 15 giorni, poi passa a scaduto: i dati restano,
     cambia solo cosa si puo' fare.</p>
  <form method="post" action="<?= View::url('/admin/organizzazioni/' . $org['id'] . '/scadenza') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="access_expires_at">Attivo fino al *</label>
      <input type="date" id="access_expires_at" name="access_expires_at" required
             value="<?= View::e($org['access_expires_at'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="external_contract_ref">Riferimento contratto</label>
      <input type="text" id="external_contract_ref" name="external_contract_ref"
             value="<?= View::e($org['external_contract_ref'] ?? '') ?>" placeholder="Es. Contratto 2026/17 del 12/01">
    </div>
    <div class="field">
      <label for="admin_notes">Note interne</label>
      <textarea id="admin_notes" name="admin_notes"><?= View::e($org['admin_notes'] ?? '') ?></textarea>
    </div>
    <button class="btn btn--primary btn--block">
      <?= $org['status'] === 'PENDING_APPROVAL' ? 'Attiva account' : 'Aggiorna scadenza' ?>
    </button>
  </form>
</div>

<div class="card card--pad">
  <h2 class="h3">Stato</h2>
  <form method="post" action="<?= View::url('/admin/organizzazioni/' . $org['id'] . '/stato') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <select name="status">
        <?php foreach (Enums::ORG_STATUS as $k => $label): ?>
          <option value="<?= $k ?>" <?= $org['status'] === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--ghost btn--block btn--sm">Applica</button>
  </form>
</div>

<?php if ($extensions): ?>
  <div class="card">
    <div class="card__head"><h2 class="h3">Storico proroghe</h2></div>
    <ul class="list">
      <?php foreach ($extensions as $e): ?>
        <li class="list__item">
          <div class="list__main">
            <div class="list__title"><?= View::date($e['previous_expiry']) ?> → <?= View::date($e['new_expiry']) ?></div>
            <div class="list__sub"><?= View::e($e['external_ref'] ?: $e['reason'] ?: '—') ?></div>
          </div>
          <span class="muted small nowrap"><?= View::date($e['created_at']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card__head"><h2 class="h3">Utenti</h2></div>
  <ul class="list">
    <?php foreach ($users as $u): ?>
      <li class="list__item">
        <div class="list__main">
          <div class="list__title"><?= View::e($u['full_name']) ?></div>
          <div class="list__sub"><?= View::e($u['email']) ?> · <?= View::e($u['org_role']) ?></div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
