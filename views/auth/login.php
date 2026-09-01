<?php use App\Core\{Csrf, View}; ?>
<div class="card card--pad">
  <h1 class="h2">Accedi</h1>
  <form method="post" action="<?= View::url('/login') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="email" autofocus>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn btn--primary btn--block">Entra</button>
  </form>
</div>
<p class="center muted small">
  Non hai un account?
  <a href="<?= View::url('/registrati?tipo=offerente') ?>">Offerente</a> ·
  <a href="<?= View::url('/registrati?tipo=richiedente') ?>">Richiedente</a>
</p>
