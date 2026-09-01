<?php use App\Core\{Session, View}; $flash = Session::pullFlash(); ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4F46E5">
<title><?= View::e($title ?? '') ?> · <?= View::e($appName ?? '') ?></title>
<link rel="manifest" href="<?= View::url('/manifest.webmanifest') ?>">
<link rel="stylesheet" href="<?= View::url('/assets/css/app.css?v=1') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚙️</text></svg>">
</head>
<body class="body--public">
<header class="appbar appbar--public">
  <a class="appbar__brand" href="<?= View::url('/') ?>">Officina<span>dei Talenti</span></a>
  <a class="btn btn--ghost btn--sm" href="<?= View::url('/login') ?>">Accedi</a>
</header>
<main class="page page--public">
  <?php foreach ($flash as $f): ?>
    <div class="flash flash--<?= View::e($f['type']) ?>" role="status"><?= View::e($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="foot">© <?= date('Y') ?> Officina dei Talenti · tallerconsulting.it</footer>
</body>
</html>
