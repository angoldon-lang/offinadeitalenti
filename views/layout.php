<?php
/** @var string $content */
/** @var array|null $currentUser */
use App\Core\{Csrf, Session, View};

$role   = $currentUser['platform_role'] ?? '';
$flash  = Session::pullFlash();
$unread = $unread ?? 0;

// Tab bar per ruolo: massimo 4 voci, quella con azioni pendenti porta il badge.
$tabs = match ($role) {
    'OFFERENTE' => [
        ['/offerente', 'Home', '🏠'],
        ['/offerente/risorse', 'Risorse', '👥'],
        ['/offerente/rendicontazione', 'Ore', '📅'],
        ['/contratti', 'Documenti', '📄'],
    ],
    'RICHIEDENTE' => [
        ['/richiedente', 'Cerca', '🔍'],
        ['/richiedente/richieste', 'Richieste', '📨'],
        ['/richiedente/approvazioni', 'Approva', '✅'],
        ['/contratti', 'Documenti', '📄'],
    ],
    'ADMIN' => [
        ['/admin', 'Home', '📊'],
        ['/admin/moderazione', 'Moderazione', '⏳'],
        ['/admin/monitor', 'Monitor', '📅'],
        ['/admin/pagamenti', 'Pagamenti', '💶'],
    ],
    'RESOURCE_USER' => [
        ['/risorsa', 'Le mie ore', '📅'],
    ],
    default => [],
};
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4F46E5">
<title><?= View::e($title ?? 'Officina dei Talenti') ?> · <?= View::e($appName ?? '') ?></title>
<link rel="manifest" href="<?= View::url('/manifest.webmanifest') ?>">
<link rel="stylesheet" href="<?= View::url('/assets/css/app.css?v=1') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚙️</text></svg>">
<meta name="csrf-token" content="<?= View::e(Csrf::token()) ?>">
</head>
<body>

<header class="appbar">
  <a class="appbar__brand" href="<?= View::url('/') ?>">Officina<span>dei Talenti</span></a>
  <div class="appbar__actions">
    <a class="iconbtn" href="<?= View::url('/notifiche') ?>" aria-label="Notifiche">
      🔔<?php if ($unread > 0): ?><span class="dot"><?= (int) $unread ?></span><?php endif; ?>
    </a>
    <a class="iconbtn" href="<?= View::url('/logout') ?>" aria-label="Esci">⏻</a>
  </div>
</header>

<?php if (in_array($currentUser['org_status'] ?? '', ['GRACE', 'EXPIRED', 'SUSPENDED'], true)): ?>
  <div class="banner banner--warn">
    <strong>Account <?= View::e(strtolower(\App\Domain\Enums::label(\App\Domain\Enums::ORG_STATUS, $currentUser['org_status']))) ?>.</strong>
    Puoi consultare contratti, ore e fatture, ma non inserire nuovi dati.
    <?php if (!empty($currentUser['org_expires_at'])): ?>
      Scadenza: <?= View::date($currentUser['org_expires_at']) ?>.
    <?php endif; ?>
  </div>
<?php endif; ?>

<main class="page">
  <?php foreach ($flash as $f): ?>
    <div class="flash flash--<?= View::e($f['type']) ?>" role="status"><?= View::e($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<?php if ($tabs !== []): ?>
<nav class="tabbar" aria-label="Navigazione principale">
  <?php foreach ($tabs as [$href, $label, $icon]):
      $active = $path === View::url($href) || ($href !== '/' && str_starts_with($path, View::url($href) . '/')); ?>
    <a class="tab <?= $active ? 'is-active' : '' ?>" href="<?= View::url($href) ?>">
      <span class="tab__icon" aria-hidden="true"><?= $icon ?></span>
      <span class="tab__label"><?= View::e($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<script src="<?= View::url('/assets/js/app.js?v=1') ?>" defer></script>
</body>
</html>
