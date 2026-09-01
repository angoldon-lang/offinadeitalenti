<?php
/**
 * La schermata piu' usata dell'applicazione: una settimana, sette giorni,
 * un totale. Obiettivo dichiarato: compilare una settimana standard in meno
 * di 15 secondi, con una mano sola.
 */
use App\Core\{Csrf, View};
use App\Domain\Enums;
use App\Support\{Ui, Week};

$isHourly = $timesheet['unit'] === 'HOURLY';
$rate     = (float) $timesheet['contract_rate'];
$byDate   = [];
foreach ($days as $d) { $byDate[$d['work_date']] = $d; }

$weekDays = Week::days((int) $timesheet['iso_year'], (int) $timesheet['iso_week']);
$amount   = (float) $timesheet['total_quantity'] * $rate;
?>
<div class="<?= $canEdit || $canApprove ? 'has-totalbar' : '' ?>">

  <div class="week-nav">
    <a href="<?= View::url('/rendicontazione/' . $contract['id'] . '/' . $prevWeek[0] . '/' . $prevWeek[1]) ?>"
       aria-label="Settimana precedente">‹</a>
    <div class="week-nav__title">
      <strong>Settimana <?= (int) $timesheet['iso_week'] ?></strong>
      <span><?= View::e(Week::label((int) $timesheet['iso_year'], (int) $timesheet['iso_week'])) ?></span>
    </div>
    <?php $future = Week::startOf($nextWeek[0], $nextWeek[1]) > new DateTimeImmutable('today'); ?>
    <?php if ($future): ?>
      <span class="disabled" aria-hidden="true">›</span>
    <?php else: ?>
      <a href="<?= View::url('/rendicontazione/' . $contract['id'] . '/' . $nextWeek[0] . '/' . $nextWeek[1]) ?>"
         aria-label="Settimana successiva">›</a>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card__head">
      <div>
        <div class="list__title"><?= View::e($isProvider ? $timesheet['client_name'] : $timesheet['provider_name']) ?></div>
        <div class="list__sub">
          <?= View::e($timesheet['resource_title'] ?? $timesheet['contract_code']) ?> ·
          <?= View::money($rate) ?> <?= View::e(Enums::label(Enums::RATE_UNIT, $timesheet['contract_unit'])) ?>
        </div>
      </div>
      <?= Ui::timesheetBadge($timesheet['status']) ?>
    </div>

    <?php if ($timesheet['status'] === 'REJECTED' && $timesheet['rejection_reason']): ?>
      <div class="wstate wstate--rose">
        <strong>Rifiutata:</strong> <?= View::e($timesheet['rejection_reason']) ?><br>
        <span class="small">Correggi i giorni e reinvia.</span>
      </div>
    <?php elseif ($timesheet['status'] === 'SUBMITTED'): ?>
      <div class="wstate wstate--amber">
        Inviata il <?= View::date($timesheet['submitted_at'], 'd/m/Y H:i') ?> ·
        in attesa di approvazione da <?= View::e($timesheet['client_name']) ?>.
      </div>
    <?php elseif (in_array($timesheet['status'], ['APPROVED', 'INVOICED', 'PAID'], true)): ?>
      <div class="wstate wstate--emerald">
        Approvata il <?= View::date($timesheet['reviewed_at'], 'd/m/Y') ?> ·
        importo congelato a <?= View::money($timesheet['amount']) ?>
        (tariffa <?= View::money($timesheet['rate_snapshot']) ?>).
      </div>
    <?php endif; ?>

    <div id="ts-days"
         data-timesheet="<?= View::e($timesheet['id']) ?>"
         data-unit="<?= View::e($timesheet['unit']) ?>"
         data-rate="<?= View::e((string) $rate) ?>"
         data-endpoint="<?= View::url('/rendicontazione/' . $timesheet['id'] . '/giorno') ?>"
         data-editable="<?= $canEdit ? '1' : '0' ?>">
      <?php foreach ($weekDays as $i => $day):
          $date    = $day->format('Y-m-d');
          $row     = $byDate[$date] ?? ['quantity' => 0, 'day_type' => 'NON_LAVORATO', 'note' => null];
          $qty     = (float) $row['quantity'];
          $weekend = (int) $day->format('N') >= 6;
          $holiday = $holidays[$date] ?? null;
          $cls     = $weekend ? ' day--off' : ($holiday ? ' day--holiday' : '');
      ?>
      <div class="day<?= $cls ?>" data-date="<?= $date ?>">
        <div class="day__label">
          <b><?= Week::DAY_LABELS[$i] ?> <?= $day->format('j') ?></b>
          <span><?= $holiday ? View::e($holiday) : ($weekend ? 'weekend' : $day->format('m/Y')) ?></span>
        </div>

        <?php if ($isHourly): ?>
          <div class="stepper">
            <button type="button" class="js-step" data-delta="-1" <?= $canEdit ? '' : 'disabled' ?> aria-label="Diminuisci">−</button>
            <input type="number" class="js-qty" inputmode="decimal" step="0.5" min="0" max="24"
                   value="<?= View::qty($qty) ?>" <?= $canEdit ? '' : 'disabled' ?> aria-label="Ore del <?= $date ?>">
            <button type="button" class="js-step" data-delta="1" <?= $canEdit ? '' : 'disabled' ?> aria-label="Aumenta">+</button>
          </div>
        <?php else: ?>
          <div class="seg" role="group" aria-label="Giornata del <?= $date ?>">
            <?php foreach ([['0', 0.0], ['½', 0.5], ['1', 1.0]] as [$label, $value]): ?>
              <button type="button" class="js-seg <?= abs($qty - $value) < 0.001 ? 'is-on' : '' ?>"
                      data-value="<?= $value ?>" <?= $canEdit ? '' : 'disabled' ?>><?= $label ?></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <button type="button" class="day__more js-more" <?= $canEdit ? '' : 'disabled' ?>
                aria-label="Tipo giornata e nota">⋮</button>
      </div>
      <?php if (!empty($row['note']) || ($row['day_type'] !== 'LAVORO' && $row['day_type'] !== 'NON_LAVORATO')): ?>
        <div class="day__note">
          <?= View::e(Enums::DAY_TYPE_ICON[$row['day_type']] ?? '') ?>
          <?= View::e(Enums::label(Enums::DAY_TYPE, $row['day_type'])) ?>
          <?= !empty($row['note']) ? '· ' . View::e($row['note']) : '' ?>
        </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($canApprove): ?>
    <div class="card card--pad">
      <h2 class="h3">Approvazione</h2>
      <p class="muted">Totale dichiarato: <strong><?= View::e(Ui::quantity($timesheet['total_quantity'], $timesheet['unit'])) ?></strong>
         per <strong><?= View::money($amount) ?></strong>.</p>
      <div class="actions">
        <form method="post" action="<?= View::url('/rendicontazione/' . $timesheet['id'] . '/approva') ?>" style="flex:1">
          <?= Csrf::field() ?>
          <button class="btn btn--success btn--block">✓ Approva</button>
        </form>
        <button class="btn btn--ghost" type="button" data-sheet-open="reject-sheet">Rifiuta</button>
      </div>
    </div>

    <div class="sheet" id="reject-sheet">
      <div class="sheet__scrim" data-sheet-close></div>
      <div class="sheet__panel">
        <div class="sheet__grip"></div>
        <h2 class="h3">Motivo del rifiuto</h2>
        <p class="muted small">Senza spiegazione il fornitore non sa cosa correggere: la motivazione e' obbligatoria.</p>
        <form method="post" action="<?= View::url('/rendicontazione/' . $timesheet['id'] . '/rifiuta') ?>">
          <?= Csrf::field() ?>
          <div class="field">
            <textarea name="reason" required minlength="5"
                      placeholder="Es. Mercoledi' non risulta presenza in sede"></textarea>
          </div>
          <button class="btn btn--danger btn--block">Rifiuta la settimana</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($events): ?>
    <div class="card">
      <div class="card__head"><h2 class="h3">Storico</h2></div>
      <ul class="list">
        <?php foreach ($events as $ev): ?>
          <li class="list__item">
            <div class="list__main">
              <div class="list__title small">
                <?= View::e(Enums::label(Enums::TIMESHEET_STATUS, $ev['to_status'])) ?>
                <?= $ev['actor_name'] ? '· ' . View::e($ev['actor_name']) : '' ?>
              </div>
              <?php if ($ev['reason']): ?><div class="list__sub"><?= View::e($ev['reason']) ?></div><?php endif; ?>
            </div>
            <span class="muted small nowrap"><?= View::date($ev['created_at'], 'd/m H:i') ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (($currentUser['platform_role'] ?? '') === 'ADMIN' && in_array($timesheet['status'], ['APPROVED','INVOICED','PAID','SUBMITTED'], true)): ?>
    <div class="card card--pad">
      <h2 class="h3">Override amministrativo</h2>
      <p class="muted small">Riapre una settimana bloccata. L'operazione richiede una motivazione ed e' tracciata
         nell'audit log e nello storico della settimana.</p>
      <form method="post" action="<?= View::url('/admin/rendicontazione/' . $timesheet['id'] . '/override') ?>">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="ov-status">Riporta a</label>
          <select name="status" id="ov-status">
            <option value="DRAFT">Da compilare (riapre al fornitore)</option>
            <option value="SUBMITTED">In attesa di approvazione</option>
            <option value="APPROVED">Approvato</option>
          </select>
        </div>
        <div class="field">
          <label for="ov-reason">Motivazione</label>
          <textarea name="reason" id="ov-reason" required minlength="10"
                    placeholder="Es. errore di battitura confermato via email dal cliente il 12/03"></textarea>
        </div>
        <button class="btn btn--danger btn--block">Applica override</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<div class="totalbar">
  <div class="saveline" id="ts-saveline">&nbsp;</div>
  <div class="totalbar__row">
    <span class="totalbar__qty">
      <span id="ts-total"><?= View::qty($timesheet['total_quantity']) ?></span>
      <span class="muted" style="font-size:14px"><?= $isHourly ? 'ore' : 'giorni' ?></span>
    </span>
    <span class="totalbar__amount" id="ts-amount"><?= View::money($amount) ?></span>
  </div>
  <button class="btn btn--primary btn--block" type="button" data-sheet-open="submit-sheet">
    Invia in approvazione →
  </button>
</div>

<div class="sheet" id="submit-sheet">
  <div class="sheet__scrim" data-sheet-close></div>
  <div class="sheet__panel">
    <div class="sheet__grip"></div>
    <h2 class="h3">Confermi l'invio?</h2>
    <p class="muted">
      <strong id="confirm-qty"><?= View::e(Ui::quantity($timesheet['total_quantity'], $timesheet['unit'])) ?></strong> ·
      <strong id="confirm-amount"><?= View::money($amount) ?></strong><br>
      a <?= View::e($timesheet['client_name']) ?>, settimana <?= (int) $timesheet['iso_week'] ?>.
    </p>
    <p class="muted small">Dopo l'invio la settimana non e' piu' modificabile fino alla risposta del cliente.</p>
    <form method="post" action="<?= View::url('/rendicontazione/' . $timesheet['id'] . '/invia') ?>">
      <?= Csrf::field() ?>
      <button class="btn btn--primary btn--block">Invia in approvazione</button>
    </form>
    <button class="btn btn--ghost btn--block mt" type="button" data-sheet-close>Annulla</button>
  </div>
</div>
<?php elseif ($canApprove): ?>
<div class="totalbar">
  <div class="totalbar__row">
    <span class="totalbar__qty"><?= View::e(Ui::quantity($timesheet['total_quantity'], $timesheet['unit'])) ?></span>
    <span class="totalbar__amount"><?= View::money($amount) ?></span>
  </div>
  <form method="post" action="<?= View::url('/rendicontazione/' . $timesheet['id'] . '/approva') ?>">
    <?= Csrf::field() ?>
    <button class="btn btn--success btn--block">✓ Approva la settimana</button>
  </form>
</div>
<?php endif; ?>

<!-- Foglio "tipo giornata e nota", condiviso da tutte le righe -->
<div class="sheet" id="day-sheet">
  <div class="sheet__scrim" data-sheet-close></div>
  <div class="sheet__panel">
    <div class="sheet__grip"></div>
    <h2 class="h3" id="day-sheet-title">Giornata</h2>
    <div class="field">
      <label for="day-type">Tipo di giornata</label>
      <select id="day-type">
        <?php foreach (Enums::DAY_TYPE as $k => $label): ?>
          <option value="<?= $k ?>"><?= View::e(Enums::DAY_TYPE_ICON[$k] . ' ' . $label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!$isHourly): ?>
    <div class="field">
      <label for="day-qty">Quantita' (giorni)</label>
      <input type="number" id="day-qty" step="0.25" min="0" max="1" inputmode="decimal">
      <div class="field__hint">Per i casi fuori standard: 0,25 o 0,75.</div>
    </div>
    <?php endif; ?>
    <div class="field">
      <label for="day-note">Nota</label>
      <input type="text" id="day-note" maxlength="200" placeholder="Es. trasferta Milano">
    </div>
    <button class="btn btn--primary btn--block" id="day-save">Salva</button>
    <button class="btn btn--ghost btn--block mt" type="button" data-sheet-close>Annulla</button>
  </div>
</div>

<script src="<?= View::url('/assets/js/timesheet.js?v=1') ?>" defer></script>
