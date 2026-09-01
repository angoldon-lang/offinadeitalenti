<?php use App\Core\{Csrf, View}; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1">Richieste ricevute</h1>

<?php if (!$requests): ?>
  <div class="card"><div class="empty"><div class="empty__icon">📨</div>Nessuna richiesta al momento.</div></div>
<?php else: foreach ($requests as $q): ?>
  <div class="card">
    <div class="card__head">
      <div>
        <div class="list__title"><?= View::e($q['resource_title']) ?></div>
        <div class="list__sub">
          <?php /* Il nome del cliente compare solo dopo l'accettazione. */ ?>
          <?= $q['client_name']
                ? View::e($q['client_name'])
                : View::e(trim(($q['client_sector'] ?? 'Azienda') . ' · ' . ($q['client_size'] ?? ''), ' ·')) . ' (anonimo)' ?>
          · <?= View::e(Ui::ago($q['created_at'])) ?>
        </div>
      </div>
      <span class="badge badge--<?= $q['status'] === 'REQUESTED' ? 'amber' : ($q['status'] === 'DECLINED' ? 'rose' : 'emerald') ?>">
        <?= View::e(Enums::label(Enums::REQUEST_STATUS, $q['status'])) ?>
      </span>
    </div>
    <div class="card__body">
      <p class="small"><?= nl2br(View::e($q['project_brief'])) ?></p>
      <div class="kv"><span class="kv__k">Durata</span><span class="kv__v"><?= View::e($q['estimated_duration'] ?: '—') ?></span></div>
      <div class="kv"><span class="kv__k">Inizio</span><span class="kv__v"><?= View::date($q['desired_start_date']) ?></span></div>
      <div class="kv"><span class="kv__k">Budget indicato</span>
        <span class="kv__v"><?= $q['budget_hint'] ? View::money($q['budget_hint']) . ' ' . View::e(Enums::RATE_UNIT_SHORT[$q['budget_unit']] ?? '') : '—' ?></span></div>
    </div>
    <?php if ($q['status'] === 'REQUESTED'): ?>
      <div class="card__foot actions">
        <form method="post" action="<?= View::url('/offerente/richieste/' . $q['id']) ?>" style="flex:1">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="accept">
          <button class="btn btn--success btn--sm btn--block">Accetta</button>
        </form>
        <form method="post" action="<?= View::url('/offerente/richieste/' . $q['id']) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="decline">
          <button class="btn btn--ghost btn--sm">Rifiuta</button>
        </form>
      </div>
    <?php elseif ($q['status'] === 'ACCEPTED'): ?>
      <div class="card__foot">
        <a class="btn btn--primary btn--sm" href="<?= View::url('/contratti/nuovo') ?>">Crea contratto</a>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; endif; ?>
