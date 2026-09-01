<?php
declare(strict_types=1);
/**
 * Job giornaliero. Su Aruba Hosting Basic si imposta dal pannello
 * (Gestione Hosting > Cron job) con una esecuzione al giorno:
 *
 *   /usr/bin/php /web/htdocs/www.tallerconsulting.it/home/bin/cron.php
 *
 * Fa tre cose, tutte idempotenti:
 *   1. degrada gli account la cui scadenza (impostata a mano) e' passata
 *   2. marca come scadute le fatture oltre il termine
 *   3. crea le notifiche di sollecito su settimane ferme e scadenze vicine
 */
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Config;
use App\Core\Database as DB;
use App\Repository\NotificationRepository;

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$now   = DB::now();
$log   = static fn (string $m) => print('[' . date('c') . "] {$m}\n");

// 1. scadenze account -------------------------------------------------------
$grace = (int) Config::get('security.grace_days', 15);

$entering = DB::select(
    'SELECT id, legal_name, access_expires_at FROM organizations
      WHERE status = ? AND access_expires_at IS NOT NULL AND access_expires_at < ?',
    ['ACTIVE', $today]
);
foreach ($entering as $org) {
    $graceEnd = (new DateTimeImmutable($org['access_expires_at']))->modify("+{$grace} days")->format('Y-m-d');
    DB::execute('UPDATE organizations SET status = ?, grace_ends_at = ?, updated_at = ? WHERE id = ?',
        ['GRACE', $graceEnd, $now, $org['id']]);
    NotificationRepository::notifyOrg($org['id'], 'ACCOUNT_GRACE', 'Account scaduto',
        'Hai ' . $grace . ' giorni di tolleranza: contatta l\'amministratore per il rinnovo.', '/');
}
$log('Account passati in tolleranza: ' . count($entering));

$expired = DB::execute(
    'UPDATE organizations SET status = ?, updated_at = ? WHERE status = ? AND grace_ends_at IS NOT NULL AND grace_ends_at < ?',
    ['EXPIRED', $now, 'GRACE', $today]
);
$log("Account scaduti (risorse de-indicizzate): {$expired}");

// preavvisi a 30 e 7 giorni
foreach ([30, 7] as $days) {
    $target = (new DateTimeImmutable('today'))->modify("+{$days} days")->format('Y-m-d');
    $orgs   = DB::select('SELECT id, legal_name FROM organizations WHERE status = ? AND access_expires_at = ?', ['ACTIVE', $target]);
    foreach ($orgs as $org) {
        NotificationRepository::notifyOrg($org['id'], 'ACCOUNT_EXPIRING', "Account in scadenza fra {$days} giorni",
            'Contatta l\'amministratore per rinnovare il profilo.', '/');
    }
    if ($orgs) { $log("Preavvisi a {$days} giorni: " . count($orgs)); }
}

// 2. fatture scadute --------------------------------------------------------
$overdue = DB::execute(
    'UPDATE invoices SET payment_status = ?, updated_at = ? WHERE payment_status IN (?, ?) AND due_date IS NOT NULL AND due_date < ?',
    ['SCADUTA', $now, 'EMESSA', 'INVIATA', $today]
);
$log("Fatture marcate scadute: {$overdue}");

// 3. solleciti sulla rendicontazione ----------------------------------------
$stale = DB::select(
    'SELECT t.id, t.iso_week, t.status, c.provider_org_id, c.client_org_id
       FROM timesheets t JOIN contracts c ON c.id = t.contract_id
      WHERE t.status IN (?, ?) AND t.week_end < ?',
    ['DRAFT', 'SUBMITTED', (new DateTimeImmutable('today'))->modify('-4 days')->format('Y-m-d')]
);
foreach ($stale as $ts) {
    if ($ts['status'] === 'DRAFT') {
        NotificationRepository::notifyOrg($ts['provider_org_id'], 'TIMESHEET_REMINDER',
            'Settimana ' . $ts['iso_week'] . ' da compilare', 'Bastano pochi secondi.', '/offerente/rendicontazione');
    } else {
        NotificationRepository::notifyOrg($ts['client_org_id'], 'TIMESHEET_REMINDER',
            'Settimana ' . $ts['iso_week'] . ' da approvare', 'Il fornitore attende la tua conferma.', '/richiedente/approvazioni');
    }
}
$log('Solleciti inviati: ' . count($stale));

// 4. auto-approvazione, solo se concordata sul contratto --------------------
$auto = DB::select(
    'SELECT t.id, t.status, c.agreed_rate, t.total_quantity, c.auto_approve_after_days, t.submitted_at
       FROM timesheets t JOIN contracts c ON c.id = t.contract_id
      WHERE t.status = ? AND c.auto_approve_after_days IS NOT NULL',
    ['SUBMITTED']
);
$autoCount = 0;
foreach ($auto as $ts) {
    if (!$ts['submitted_at']) { continue; }
    $deadline = (new DateTimeImmutable($ts['submitted_at']))->modify('+' . (int) $ts['auto_approve_after_days'] . ' days');
    if ($deadline > new DateTimeImmutable('now')) { continue; }

    $rate = (float) $ts['agreed_rate'];
    DB::execute(
        'UPDATE timesheets SET status = ?, rate_snapshot = ?, amount = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND status = ?',
        ['APPROVED', $rate, (float) $ts['total_quantity'] * $rate, $now, $now, $ts['id'], 'SUBMITTED']
    );
    \App\Repository\TimesheetRepository::event($ts['id'], 'SUBMITTED', 'APPROVED', null, 'Sistema', 'Auto-approvazione da contratto');
    $autoCount++;
}
$log("Auto-approvazioni: {$autoCount}");
$log('Cron completato.');
