<?php use App\Core\View; ?>
<!doctype html>
<html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($title) ?></title>
<link rel="stylesheet" href="<?= View::url('/assets/css/app.css?v=1') ?>">
</head>
<body class="body--public">
<main class="page page--public">
  <div class="card card--pad center">
    <div class="empty__icon" aria-hidden="true"><?= $status === 404 ? '🧭' : '⚠️' ?></div>
    <h1 class="h2"><?= View::e($title) ?></h1>
    <p class="muted"><?= View::e($message ?: 'Qualcosa non ha funzionato.') ?></p>
    <a class="btn btn--primary" href="<?= View::url('/') ?>">Torna alla home</a>
  </div>
</main>
</body></html>
