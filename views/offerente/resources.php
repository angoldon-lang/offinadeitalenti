<?php use App\Core\{Csrf, View}; use App\Domain\Enums; use App\Support\Ui; ?>
<h1 class="h1">Le mie risorse</h1>
<a class="btn btn--primary btn--block mb" href="<?= View::url('/offerente/risorse/nuova') ?>">+ Nuova risorsa</a>

<?php if (!$resources): ?>
  <div class="card"><div class="empty">
    <div class="empty__icon">👥</div>
    Nessuna risorsa a catalogo.<br><span class="small">Creane una: quando l'approviamo diventa visibile ai richiedenti.</span>
  </div></div>
<?php else: ?>
  <?php foreach ($resources as $r): ?>
    <div class="card">
      <div class="card__head">
        <div>
          <div class="list__title"><?= View::e($r['title']) ?></div>
          <div class="list__sub">
            <?= View::e(Enums::label(Enums::SENIORITY, $r['seniority'])) ?> ·
            <?= View::money($r['rate_min']) ?>–<?= View::money($r['rate_max']) ?>
            <?= View::e(Enums::RATE_UNIT_SHORT[$r['rate_unit']] ?? '') ?> ·
            <?= View::e(Enums::label(Enums::WORK_MODE, $r['work_mode'])) ?>
          </div>
        </div>
        <?= Ui::resourceBadge($r['publication_status']) ?>
      </div>

      <?php if ($r['publication_status'] === 'REJECTED' && $r['rejection_reason']): ?>
        <div class="wstate wstate--rose"><strong>Da correggere:</strong> <?= View::e($r['rejection_reason']) ?></div>
      <?php endif; ?>

      <div class="card__foot actions">
        <a class="btn btn--ghost btn--sm" href="<?= View::url('/offerente/risorse/' . $r['id']) ?>">Modifica</a>

        <?php if (in_array($r['publication_status'], ['DRAFT', 'REJECTED'], true)): ?>
          <form method="post" action="<?= View::url('/offerente/risorse/' . $r['id'] . '/invia') ?>">
            <?= Csrf::field() ?>
            <button class="btn btn--primary btn--sm">Invia in approvazione</button>
          </form>
        <?php endif; ?>

        <?php if ($r['publication_status'] === 'PUBLISHED'): ?>
          <form method="post" action="<?= View::url('/offerente/risorse/' . $r['id'] . '/stato') ?>">
            <?= Csrf::field() ?>
            <button class="btn btn--ghost btn--sm">
              <?= $r['operational_status'] === 'ATTIVA' ? '🟢 Attiva → segna Occupata' : '🟠 Occupata → segna Attiva' ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
