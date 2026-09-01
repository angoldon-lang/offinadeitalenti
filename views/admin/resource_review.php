<?php use App\Core\{Csrf, View}; use App\Domain\Enums; ?>
<h1 class="h1"><?= View::e($resource['title']) ?></h1>
<p class="muted"><?= View::e($org['legal_name'] ?? '') ?></p>

<div class="card card--pad">
  <div class="kv"><span class="kv__k">Esperienza</span><span class="kv__v"><?= View::e(Enums::label(Enums::SENIORITY, $resource['seniority'])) ?></span></div>
  <div class="kv"><span class="kv__k">Tariffa</span><span class="kv__v"><?= View::money($resource['rate_min']) ?>–<?= View::money($resource['rate_max']) ?> <?= View::e(Enums::RATE_UNIT_SHORT[$resource['rate_unit']] ?? '') ?></span></div>
  <div class="kv"><span class="kv__k">Modalita'</span><span class="kv__v"><?= View::e(Enums::label(Enums::WORK_MODE, $resource['work_mode'])) ?><?= $resource['city'] ? ' · ' . View::e($resource['city']) : '' ?></span></div>
  <div class="kv"><span class="kv__k">Disponibilita'</span><span class="kv__v"><?= View::e(Enums::label(Enums::AVAILABILITY, $resource['availability'])) ?></span></div>
  <?php if ($resource['description']): ?><p class="mt small"><?= nl2br(View::e($resource['description'])) ?></p><?php endif; ?>
  <div class="mt">
    <?php foreach ($skills as $s): ?><span class="chip"><?= View::e($s['name']) ?></span><?php endforeach; ?>
  </div>
</div>

<div class="card card--pad">
  <form method="post" action="<?= View::url('/admin/moderazione/' . $resource['id']) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="approve">
    <button class="btn btn--success btn--block">✓ Approva e pubblica</button>
  </form>
  <form method="post" action="<?= View::url('/admin/moderazione/' . $resource['id']) ?>" class="mt">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="reject">
    <div class="field">
      <label for="reason">Motivo del rifiuto</label>
      <textarea id="reason" name="reason" minlength="5"
                placeholder="Es. tariffa incoerente con la seniority dichiarata"></textarea>
    </div>
    <button class="btn btn--danger btn--block">Rifiuta con motivazione</button>
  </form>
</div>
