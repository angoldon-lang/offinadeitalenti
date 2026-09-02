<?php use App\Core\{Csrf, View}; use App\Domain\Enums; ?>
<h1 class="h1">Il mio profilo</h1>

<div class="card card--pad">
  <div class="kv"><span class="kv__k">Email</span><span class="kv__v"><?= View::e($user['email']) ?></span></div>
  <div class="kv"><span class="kv__k">Ruolo</span><span class="kv__v"><?= View::e($user['platform_role']) ?></span></div>
  <?php if (!empty($user['org_name'])): ?>
    <div class="kv"><span class="kv__k">Organizzazione</span><span class="kv__v"><?= View::e($user['org_name']) ?></span></div>
    <div class="kv"><span class="kv__k">Account attivo fino al</span>
      <span class="kv__v"><?= View::date($user['org_expires_at']) ?></span></div>
  <?php endif; ?>
</div>

<div class="card card--pad">
  <h2 class="h3">Dati personali</h2>
  <form method="post" action="<?= View::url('/profilo') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="full_name">Nome e cognome</label>
      <input type="text" id="full_name" name="full_name" required value="<?= View::e($user['full_name']) ?>">
    </div>
    <div class="field">
      <label for="phone">Telefono</label>
      <input type="text" id="phone" name="phone" inputmode="tel" value="<?= View::e($user['phone'] ?? '') ?>">
    </div>
    <button class="btn btn--primary btn--block">Salva</button>
  </form>
</div>

<div class="card card--pad">
  <h2 class="h3">Cambia password</h2>
  <form method="post" action="<?= View::url('/profilo/password') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="current_password">Password attuale</label>
      <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
    </div>
    <div class="field">
      <label for="new_password">Nuova password</label>
      <input type="password" id="new_password" name="new_password" required minlength="10" autocomplete="new-password">
      <div class="field__hint">Almeno 10 caratteri.</div>
    </div>
    <div class="field">
      <label for="new_password_confirm">Conferma nuova password</label>
      <input type="password" id="new_password_confirm" name="new_password_confirm" required autocomplete="new-password">
    </div>
    <button class="btn btn--primary btn--block">Aggiorna password</button>
  </form>
</div>

<a class="btn btn--ghost btn--block" href="<?= View::url('/logout') ?>">Esci</a>
