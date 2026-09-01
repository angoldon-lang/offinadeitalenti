<?php use App\Core\View; ?>
<div class="card card--pad">
  <h1 class="h1">Competenze tecniche,<br>senza intermediari lenti.</h1>
  <p class="muted">Le societa' di consulenza pubblicano i profili disponibili. Le aziende cercano,
     filtrano e prenotano. Contratti, ore lavorate e fatture in un unico posto.</p>
  <a class="btn btn--primary btn--block mt" href="<?= View::url('/registrati?tipo=offerente') ?>">Offro competenze</a>
  <a class="btn btn--ghost btn--block mt" href="<?= View::url('/registrati?tipo=richiedente') ?>">Cerco competenze</a>
</div>
<div class="card card--pad">
  <h2 class="h3">Come funziona</h2>
  <div class="kv"><span class="kv__k">1. Registrazione</span><span class="kv__v">Attivazione entro 24 h</span></div>
  <div class="kv"><span class="kv__k">2. Catalogo</span><span class="kv__v">Profili verificati</span></div>
  <div class="kv"><span class="kv__k">3. Contratto</span><span class="kv__v">Tariffa concordata</span></div>
  <div class="kv"><span class="kv__k">4. Ogni settimana</span><span class="kv__v">Ore → approvazione → fattura</span></div>
</div>
<p class="center muted small">Hai gia' un account? <a href="<?= View::url('/login') ?>">Accedi</a></p>
