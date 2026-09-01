<?php
/** Ricerca mobile: barra sticky, chip filtro scrollabili, risultati in card. */
use App\Core\View; use App\Domain\Enums;

$f = $filters;
$activeCount = count($f['skills']) + count($f['seniority']) + count($f['work_mode']) + count($f['availability'])
             + ($f['budget_max'] ? 1 : 0) + ($f['city'] !== '' ? 1 : 0) + ($f['engagement'] ? 1 : 0);
?>
<form method="get" action="<?= View::url('/richiedente') ?>" id="search-form">
  <div class="searchbar">
    <input type="search" name="q" value="<?= View::e($f['q']) ?>" placeholder="Cerca per ruolo, es. React" aria-label="Cerca">
  </div>

  <div class="filterbar" role="group" aria-label="Filtri rapidi">
    <button type="button" class="chipbtn <?= $f['skills'] ? 'is-on' : '' ?>" data-sheet-open="sheet-skills">
      Skill<?= $f['skills'] ? ' · ' . count($f['skills']) : '' ?>
    </button>
    <button type="button" class="chipbtn <?= $f['seniority'] ? 'is-on' : '' ?>" data-sheet-open="sheet-seniority">
      Esperienza<?= $f['seniority'] ? ' · ' . count($f['seniority']) : '' ?>
    </button>
    <button type="button" class="chipbtn <?= $f['budget_max'] ? 'is-on' : '' ?>" data-sheet-open="sheet-budget">
      Budget<?= $f['budget_max'] ? ' · ≤' . (int) $f['budget_max'] . '€' : '' ?>
    </button>
    <button type="button" class="chipbtn <?= $f['work_mode'] ? 'is-on' : '' ?>" data-sheet-open="sheet-mode">
      Modalita'<?= $f['work_mode'] ? ' · ' . count($f['work_mode']) : '' ?>
    </button>
    <button type="button" class="chipbtn <?= $f['availability'] ? 'is-on' : '' ?>" data-sheet-open="sheet-avail">
      Disponibilita'<?= $f['availability'] ? ' · ' . count($f['availability']) : '' ?>
    </button>
    <?php if ($activeCount): ?>
      <a class="chipbtn" href="<?= View::url('/richiedente') ?>">✕ Azzera</a>
    <?php endif; ?>
  </div>

  <?php
  /* Ogni filtro vive in un foglio dal basso: il contesto resta visibile. */
  $sheets = [
    'sheet-skills'    => ['Competenze', null],
    'sheet-seniority' => ['Livello di esperienza', ['seniority', Enums::SENIORITY, $f['seniority']]],
    'sheet-mode'      => ['Modalita\' di lavoro', ['work_mode', Enums::WORK_MODE, $f['work_mode']]],
    'sheet-avail'     => ['Disponibilita\'', ['availability', Enums::AVAILABILITY, $f['availability']]],
  ];
  foreach ($sheets as $sid => [$stitle, $conf]): ?>
    <div class="sheet" id="<?= $sid ?>">
      <div class="sheet__scrim" data-sheet-close></div>
      <div class="sheet__panel">
        <div class="sheet__grip"></div>
        <h2 class="h3"><?= View::e($stitle) ?></h2>
        <?php if ($conf === null): ?>
          <?php foreach (['HARD' => 'Hard skills', 'SOFT' => 'Soft skills'] as $cat => $lab): ?>
            <div class="field">
              <label><?= $lab ?></label>
              <div class="checks">
                <?php foreach ($allSkills[$cat] as $s): ?>
                  <input type="checkbox" id="f-<?= View::e($s['id']) ?>" name="skills[]" value="<?= View::e($s['id']) ?>"
                         <?= in_array($s['id'], $f['skills'], true) ? 'checked' : '' ?>>
                  <label for="f-<?= View::e($s['id']) ?>"><?= View::e($s['name']) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="field">
            <label for="skill_mode">Corrispondenza</label>
            <select name="skill_mode" id="skill_mode">
              <option value="AND" <?= $f['skill_mode'] === 'AND' ? 'selected' : '' ?>>Deve avere tutte le skill</option>
              <option value="OR"  <?= $f['skill_mode'] === 'OR'  ? 'selected' : '' ?>>Almeno una skill</option>
            </select>
          </div>
        <?php else: [$name, $map, $selected] = $conf; ?>
          <div class="checks">
            <?php foreach ($map as $k => $label): ?>
              <input type="checkbox" id="f-<?= $name . '-' . $k ?>" name="<?= $name ?>[]" value="<?= $k ?>"
                     <?= in_array($k, $selected, true) ? 'checked' : '' ?>>
              <label for="f-<?= $name . '-' . $k ?>"><?= View::e($label) ?></label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <button class="btn btn--primary btn--block mt" type="button" data-sheet-close>
          Mostra <?= count($results) ?> risorse
        </button>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="sheet" id="sheet-budget">
    <div class="sheet__scrim" data-sheet-close></div>
    <div class="sheet__panel">
      <div class="sheet__grip"></div>
      <h2 class="h3">Budget</h2>
      <p class="muted small">Valori normalizzati a giornata (1 gg = 8 h), cosi' i profili a ore e a
         giornata sono confrontabili.</p>
      <div class="row">
        <div class="field">
          <label for="budget_min">Da (€/gg)</label>
          <input type="number" id="budget_min" name="budget_min" min="0" step="10" inputmode="numeric" value="<?= View::e((string) ($f['budget_min'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="budget_max">A (€/gg)</label>
          <input type="number" id="budget_max" name="budget_max" min="0" step="10" inputmode="numeric" value="<?= View::e((string) ($f['budget_max'] ?? '')) ?>">
        </div>
      </div>
      <div class="field">
        <label for="city">Localita'</label>
        <input type="text" id="city" name="city" value="<?= View::e($f['city']) ?>" placeholder="Milano">
      </div>
      <div class="checks">
        <input type="checkbox" id="include_busy" name="include_busy" value="1" <?= $f['include_busy'] ? 'checked' : '' ?>>
        <label for="include_busy">Includi risorse occupate</label>
      </div>
      <button class="btn btn--primary btn--block mt" type="button" data-sheet-close>Mostra <?= count($results) ?> risorse</button>
    </div>
  </div>
</form>

<p class="muted small mt"><?= count($results) ?> risorse trovate</p>

<?php if (!$results): ?>
  <div class="card"><div class="empty">
    <div class="empty__icon">🔍</div>
    Nessuna risorsa con questi filtri.<br>
    <span class="small">Prova ad allargare il budget o a togliere una competenza.</span>
    <div class="mt"><a class="btn btn--ghost btn--sm" href="<?= View::url('/richiedente') ?>">Azzera i filtri</a></div>
  </div></div>
<?php else: ?>
  <div class="cards-grid">
  <?php foreach ($results as $r): ?>
    <div class="card">
      <div class="card__body">
        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
          <div>
            <div class="list__title"><?= View::e($r['title']) ?></div>
            <div class="list__sub"><?= View::e(Enums::label(Enums::SENIORITY, $r['seniority'])) ?></div>
          </div>
          <?php if ($r['score'] !== null): ?><span class="score"><?= (int) $r['score'] ?>%</span><?php endif; ?>
        </div>

        <div class="mt">
          <?php foreach (array_slice($r['skills'], 0, 4) as $s): ?>
            <span class="chip <?= in_array($s['skill_id'], $f['skills'], true) ? 'chip--brand' : '' ?>"><?= View::e($s['name']) ?></span>
          <?php endforeach; ?>
          <?php if (count($r['skills']) > 4): ?><span class="chip">+<?= count($r['skills']) - 4 ?></span><?php endif; ?>
        </div>

        <div class="kv mt">
          <span class="kv__k">Tariffa</span>
          <span class="kv__v"><?= View::money($r['rate_min']) ?>–<?= View::money($r['rate_max']) ?>
            <?= View::e(Enums::RATE_UNIT_SHORT[$r['rate_unit']] ?? '') ?></span>
        </div>
        <div class="kv">
          <span class="kv__k">Modalita'</span>
          <span class="kv__v"><?= View::e(Enums::label(Enums::WORK_MODE, $r['work_mode'])) ?>
            <?= $r['city'] ? '· ' . View::e($r['city']) : '' ?></span>
        </div>
        <div class="kv">
          <span class="kv__k">Disponibilita'</span>
          <span class="kv__v"><?= View::e(Enums::label(Enums::AVAILABILITY, $r['availability'])) ?>
            <?= $r['operational_status'] === 'ATTIVA' ? '🟢' : '🟠' ?></span>
        </div>
      </div>
      <div class="card__foot">
        <a class="btn btn--primary btn--block btn--sm" href="<?= View::url('/richiedente/risorsa/' . $r['id']) ?>">
          Vedi e richiedi
        </a>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
