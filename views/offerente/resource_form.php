<?php use App\Core\{Csrf, View}; use App\Domain\Enums;
$r  = $resource ?: [];
$id = $r['id'] ?? 'nuova';
$val = static fn (string $k, $d = '') => View::e((string) ($r[$k] ?? $d));
$locked = ($r['publication_status'] ?? 'DRAFT') === 'IN_REVIEW';
?>
<h1 class="h1"><?= $resource ? 'Modifica risorsa' : 'Nuova risorsa' ?></h1>

<?php if ($locked): ?>
  <div class="flash flash--error">La risorsa e' in approvazione: le modifiche saranno possibili dopo l'esito.</div>
<?php endif; ?>

<form method="post" action="<?= View::url('/offerente/risorse/' . $id) ?>">
  <?= Csrf::field() ?>
  <fieldset style="border:0;padding:0;margin:0" <?= $locked ? 'disabled' : '' ?>>

  <div class="card card--pad">
    <h2 class="h3">1. Identita'</h2>
    <div class="field">
      <label for="title">Nome / Ruolo *</label>
      <input type="text" id="title" name="title" required placeholder="Es. Senior React Developer" value="<?= $val('title') ?>">
      <div class="field__hint">E' il titolo pubblico della scheda: sia specifico, non generico.</div>
    </div>
    <div class="row">
      <div class="field">
        <label for="seniority">Esperienza *</label>
        <select id="seniority" name="seniority" required>
          <?php foreach (Enums::SENIORITY as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($r['seniority'] ?? '') === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="operational_status">Stato risorsa</label>
        <select id="operational_status" name="operational_status">
          <?php foreach (Enums::RESOURCE_OP_STATUS as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($r['operational_status'] ?? 'ATTIVA') === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label for="description">Descrizione</label>
      <textarea id="description" name="description" placeholder="Esperienze principali, settori, progetti tipici"><?= $val('description') ?></textarea>
    </div>
  </div>

  <div class="card card--pad">
    <h2 class="h3">2. Competenze *</h2>
    <?php foreach (['HARD' => 'Hard skills', 'SOFT' => 'Soft skills'] as $cat => $label): ?>
      <div class="field">
        <label><?= $label ?></label>
        <div class="checks">
          <?php foreach ($skills[$cat] as $s): ?>
            <input type="checkbox" id="sk-<?= View::e($s['id']) ?>" name="skills[]" value="<?= View::e($s['id']) ?>"
                   <?= in_array($s['id'], $selected, true) ? 'checked' : '' ?>>
            <label for="sk-<?= View::e($s['id']) ?>"><?= View::e($s['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card card--pad">
    <h2 class="h3">3. Condizioni</h2>
    <div class="row">
      <div class="field">
        <label for="availability">Disponibilita' *</label>
        <select id="availability" name="availability" required>
          <?php foreach (Enums::AVAILABILITY as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($r['availability'] ?? '') === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="engagement">Impegno *</label>
        <select id="engagement" name="engagement" required>
          <?php foreach (Enums::ENGAGEMENT as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($r['engagement'] ?? '') === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="field">
        <label for="rate_min">Tariffa min *</label>
        <input type="number" id="rate_min" name="rate_min" required min="1" step="0.01" inputmode="decimal" value="<?= $val('rate_min') ?>">
      </div>
      <div class="field">
        <label for="rate_max">Tariffa max *</label>
        <input type="number" id="rate_max" name="rate_max" required min="1" step="0.01" inputmode="decimal" value="<?= $val('rate_max') ?>">
      </div>
      <div class="field">
        <label for="rate_unit">Unita'</label>
        <select id="rate_unit" name="rate_unit">
          <option value="DAILY"  <?= ($r['rate_unit'] ?? 'DAILY') === 'DAILY' ? 'selected' : '' ?>>€/giorno</option>
          <option value="HOURLY" <?= ($r['rate_unit'] ?? '') === 'HOURLY' ? 'selected' : '' ?>>€/ora</option>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="field">
        <label for="work_mode">Modalita' *</label>
        <select id="work_mode" name="work_mode" required>
          <?php foreach (Enums::WORK_MODE as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($r['work_mode'] ?? '') === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="city">Localita'</label>
        <input type="text" id="city" name="city" value="<?= $val('city') ?>" placeholder="Milano">
        <div class="field__hint">Obbligatoria se non e' interamente da remoto.</div>
      </div>
    </div>
    <div class="field">
      <label for="languages">Lingue</label>
      <input type="text" id="languages" name="languages" value="<?= $val('languages') ?>" placeholder="Italiano, Inglese">
    </div>
  </div>

  <button class="btn btn--primary btn--block">Salva</button>
  </fieldset>
</form>
