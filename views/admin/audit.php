<?php use App\Core\View; ?>
<h1 class="h1">Audit log</h1>
<p class="muted small">Registro in sola aggiunta: azioni amministrative, approvazioni e accessi ai documenti.</p>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Quando</th><th>Chi</th><th>Azione</th><th>Entita'</th><th>Dettaglio</th></tr></thead>
      <tbody>
        <?php foreach ($entries as $e): ?>
          <tr>
            <td><?= View::date($e['created_at'], 'd/m/Y H:i') ?></td>
            <td><?= View::e($e['actor_email'] ?: '—') ?></td>
            <td><?= View::e($e['action']) ?></td>
            <td><?= View::e($e['entity_type']) ?></td>
            <td><?= View::e(mb_substr((string) $e['diff'], 0, 80)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
