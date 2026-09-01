<?php use App\Core\{Auth, Csrf, View}; use App\Domain\Enums; ?>
<h1 class="h1">Nuovo contratto</h1>
<p class="muted small">La <strong>tariffa concordata</strong> qui indicata e' la base di calcolo di
   tutta la rendicontazione: il range sul profilo risorsa resta solo indicativo.</p>

<form method="post" action="<?= View::url('/contratti/nuovo') ?>">
  <?= Csrf::field() ?>
  <div class="card card--pad">
    <div class="field">
      <label>Codice</label>
      <input type="text" value="<?= View::e($code) ?>" disabled>
    </div>

    <?php if (Auth::isAdmin()): ?>
      <div class="field">
        <label for="provider_org_id">Fornitore *</label>
        <select id="provider_org_id" name="provider_org_id" required>
          <option value="">—</option>
          <?php foreach ($providers as $p): ?>
            <option value="<?= View::e($p['id']) ?>"><?= View::e($p['legal_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="field">
      <label for="client_org_id">Cliente *</label>
      <select id="client_org_id" name="client_org_id" required>
        <option value="">—</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= View::e($c['id']) ?>"><?= View::e($c['legal_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="resource_id">Risorsa</label>
      <select id="resource_id" name="resource_id">
        <option value="">—</option>
        <?php foreach ($resources as $r): ?>
          <option value="<?= View::e($r['id']) ?>"><?= View::e($r['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row">
      <div class="field">
        <label for="start_date">Inizio *</label>
        <input type="date" id="start_date" name="start_date" required>
      </div>
      <div class="field">
        <label for="end_date">Fine *</label>
        <input type="date" id="end_date" name="end_date" required>
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label for="agreed_rate">Tariffa concordata *</label>
        <input type="number" id="agreed_rate" name="agreed_rate" required min="1" step="0.01" inputmode="decimal">
      </div>
      <div class="field">
        <label for="rate_unit">Unita'</label>
        <select id="rate_unit" name="rate_unit">
          <option value="DAILY">€/giorno</option>
          <option value="HOURLY">€/ora</option>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="status">Stato</label>
      <select id="status" name="status">
        <option value="DRAFT">Bozza</option>
        <option value="ACTIVE">Attivo (abilita la rendicontazione)</option>
      </select>
    </div>

    <div class="checks mb">
      <input type="checkbox" id="timesheet_required" name="timesheet_required" value="1" checked>
      <label for="timesheet_required">Rendicontazione settimanale richiesta</label>
    </div>

    <div class="field">
      <label for="auto_approve_after_days">Auto-approvazione dopo (giorni)</label>
      <input type="number" id="auto_approve_after_days" name="auto_approve_after_days" min="1" max="60" inputmode="numeric">
      <div class="field__hint">Lascia vuoto per disattivarla: va concordata esplicitamente fra le parti.</div>
    </div>

    <div class="field">
      <label for="notes">Note</label>
      <textarea id="notes" name="notes"></textarea>
    </div>

    <button class="btn btn--primary btn--block">Crea contratto</button>
  </div>
</form>
