<?php use App\Core\{Csrf, View}; use App\Domain\Enums; ?>
<h1 class="h1"><?= View::e($resource['title']) ?></h1>

<div class="card card--pad">
  <p class="muted small">
    🔒 Il fornitore resta anonimo fino all'accettazione della richiesta.
  </p>
  <div class="kv"><span class="kv__k">Esperienza</span><span class="kv__v"><?= View::e(Enums::label(Enums::SENIORITY, $resource['seniority'])) ?></span></div>
  <div class="kv"><span class="kv__k">Tariffa</span><span class="kv__v"><?= View::money($resource['rate_min']) ?>–<?= View::money($resource['rate_max']) ?> <?= View::e(Enums::RATE_UNIT_SHORT[$resource['rate_unit']] ?? '') ?></span></div>
  <div class="kv"><span class="kv__k">Disponibilita'</span><span class="kv__v"><?= View::e(Enums::label(Enums::AVAILABILITY, $resource['availability'])) ?> · <?= View::e(Enums::label(Enums::ENGAGEMENT, $resource['engagement'])) ?></span></div>
  <div class="kv"><span class="kv__k">Modalita'</span><span class="kv__v"><?= View::e(Enums::label(Enums::WORK_MODE, $resource['work_mode'])) ?><?= $resource['city'] ? ' · ' . View::e($resource['city']) : '' ?></span></div>
  <div class="kv"><span class="kv__k">Stato</span><span class="kv__v"><?= $resource['operational_status'] === 'ATTIVA' ? '🟢 Attiva' : '🟠 Occupata' ?></span></div>
  <?php if ($resource['languages']): ?>
    <div class="kv"><span class="kv__k">Lingue</span><span class="kv__v"><?= View::e($resource['languages']) ?></span></div>
  <?php endif; ?>
  <?php if ($resource['description']): ?>
    <p class="mt small"><?= nl2br(View::e($resource['description'])) ?></p>
  <?php endif; ?>
  <div class="mt">
    <?php foreach ($skills as $s): ?>
      <span class="chip"><?= View::e($s['name']) ?><?= $s['level'] ? ' · ' . (int) $s['level'] . '/5' : '' ?></span>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($existing && in_array($existing['status'], ['REQUESTED', 'ACCEPTED', 'IN_NEGOTIATION', 'CONTRACTED'], true)): ?>
  <div class="card card--pad center">
    <p>Hai gia' una richiesta per questa risorsa:
      <strong><?= View::e(Enums::label(Enums::REQUEST_STATUS, $existing['status'])) ?></strong></p>
    <a class="btn btn--ghost" href="<?= View::url('/richiedente/richieste') ?>">Vedi le mie richieste</a>
  </div>
<?php else: ?>
  <div class="card card--pad">
    <h2 class="h3">Richiedi questa risorsa</h2>
    <form method="post" action="<?= View::url('/richiedente/risorsa/' . $resource['id']) ?>">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="project_brief">Descrizione del progetto *</label>
        <textarea id="project_brief" name="project_brief" required minlength="20"
                  placeholder="Contesto, attivita' previste, tecnologie"></textarea>
      </div>
      <div class="row">
        <div class="field">
          <label for="estimated_duration">Durata stimata</label>
          <input type="text" id="estimated_duration" name="estimated_duration" placeholder="3 mesi">
        </div>
        <div class="field">
          <label for="desired_start_date">Inizio</label>
          <input type="date" id="desired_start_date" name="desired_start_date">
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label for="budget_hint">Budget</label>
          <input type="number" id="budget_hint" name="budget_hint" min="0" step="10" inputmode="numeric">
        </div>
        <div class="field">
          <label for="budget_unit">Unita'</label>
          <select id="budget_unit" name="budget_unit">
            <option value="DAILY">€/giorno</option>
            <option value="HOURLY">€/ora</option>
          </select>
        </div>
      </div>
      <button class="btn btn--primary btn--block">Invia richiesta</button>
    </form>
  </div>
<?php endif; ?>
