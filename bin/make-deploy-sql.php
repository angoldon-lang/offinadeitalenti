<?php
declare(strict_types=1);
/**
 * Prepara i file SQL da incollare in phpMyAdmin su Aruba (niente SSH, niente CLI).
 *
 *   php bin/make-deploy-sql.php --admin=tua@email.it [--password='...']
 *
 * Produce in deploy/sql/:
 *   01-schema.sql        tutte le tabelle (un solo incolla)
 *   02-trigger-N.sql     un trigger per file: phpMyAdmin non ne digerisce piu' d'uno insieme
 *   03-skills.sql        tassonomia delle competenze
 *   04-admin.sql         utente amministratore con hash gia' calcolato
 */
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database as DB;

$opts  = getopt('', ['admin::', 'password::']);
$email = strtolower(trim((string) ($opts['admin'] ?? 'admin@tallerconsulting.it')));
$pass  = (string) ($opts['password'] ?? '');

if ($pass === '') {
    // Password robusta e leggibile: 4 gruppi separati, niente caratteri ambigui.
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $groups   = [];
    for ($g = 0; $g < 4; $g++) {
        $s = '';
        for ($i = 0; $i < 5; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $groups[] = $s;
    }
    $pass = implode('-', $groups);
}

$dir = dirname(__DIR__) . '/deploy/sql';
@mkdir($dir, 0755, true);

$q = static fn (?string $v): string => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";

// ---- 1. schema e trigger ----------------------------------------------------
$source     = (string) file_get_contents(dirname(__DIR__) . '/migrations/mysql/001_schema.sql');
$statements = array_values(array_filter(
    array_map('trim', explode('-- ;; --', $source)),
    static fn (string $s): bool => $s !== ''
));

$tables   = [];
$triggers = [];
foreach ($statements as $sql) {
    if (stripos($sql, 'CREATE TRIGGER') !== false) {
        $triggers[] = $sql;
    } else {
        $tables[] = $sql;
    }
}

$header = "-- Officina dei Talenti — %s\n"
        . "-- Generato il " . date('d/m/Y H:i') . "\n"
        . "-- Incolla in phpMyAdmin (scheda SQL) e premi Esegui.\n\n";

// Gli statement portano gia' il proprio ';': niente doppioni.
$tables = array_map(static fn (string $s): string => rtrim(trim($s), ';') . ';', $tables);
file_put_contents($dir . '/01-schema.sql',
    sprintf($header, 'Passo 1: tabelle') . implode("\n\n", $tables) . "\n");

foreach ($triggers as $i => $sql) {
    $n = $i + 1;
    preg_match('/CREATE TRIGGER (\w+)/i', $sql, $m);
    // Il corpo del trigger contiene ';': senza cambiare il delimitatore
    // phpMyAdmin spezzerebbe lo statement a meta'.
    $body = rtrim(trim($sql), ';');
    file_put_contents(
        sprintf('%s/02-trigger-%d.sql', $dir, $n),
        sprintf($header, "Passo 2.{$n}: trigger " . ($m[1] ?? '')) .
        "-- IMPORTANTE: incolla SOLO questo blocco, da solo, e premi Esegui.\n" .
        "-- Le righe DELIMITER servono perche' il corpo del trigger contiene punti e virgola.\n\n" .
        "DELIMITER $$\n" . $body . "$$\nDELIMITER ;\n"
    );
}

// ---- 2. tassonomia delle competenze ----------------------------------------
$skills = [
    'HARD' => ['PHP', 'JavaScript', 'TypeScript', 'React', 'Vue', 'Angular', 'Node.js', 'Python', 'Java', 'C#/.NET',
               'Go', 'SQL', 'PostgreSQL', 'MySQL', 'MongoDB', 'Docker', 'Kubernetes', 'AWS', 'Azure', 'Terraform',
               'CI/CD', 'Linux', 'Cybersecurity', 'Power BI', 'SAP', 'Salesforce', 'Android', 'iOS', 'Flutter',
               'UX/UI Design', 'Project Management', 'Data Engineering', 'Machine Learning'],
    'SOFT' => ['Comunicazione', 'Problem solving', 'Lavoro in team', 'Autonomia', 'Leadership',
               'Gestione del cliente', 'Precisione', "Adattabilita'", 'Inglese fluente'],
];

$rows = [];
foreach ($skills as $category => $names) {
    foreach ($names as $name) {
        $slug   = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $name));
        $rows[] = sprintf('(%s, %s, %s, %s, 1)', $q(DB::uuid()), $q($slug), $q($name), $q($category));
    }
}
file_put_contents($dir . '/03-skills.sql',
    sprintf($header, 'Passo 3: competenze selezionabili') .
    "INSERT INTO skills (id, slug, name, category, is_active) VALUES\n" . implode(",\n", $rows) . ";\n");

// ---- 3. amministratore ------------------------------------------------------
$now  = DB::now();
$hash = password_hash($pass, PASSWORD_DEFAULT);

file_put_contents($dir . '/04-admin.sql',
    sprintf($header, 'Passo 4: utente amministratore') .
    "-- Email:    {$email}\n-- Password: {$pass}\n" .
    "-- Cambiala dopo il primo accesso e cancella questo file dal server.\n\n" .
    sprintf(
        "INSERT INTO users (id, organization_id, email, password_hash, full_name, platform_role, org_role, is_active, created_at, updated_at)\n"
        . "VALUES (%s, NULL, %s, %s, %s, 'ADMIN', 'OWNER', 1, %s, %s);\n",
        $q(DB::uuid()), $q($email), $q($hash), $q('Amministratore'), $q($now), $q($now)
    ));

echo "File pronti in deploy/sql/:\n";
foreach (glob($dir . '/*.sql') ?: [] as $f) {
    printf("  %-22s %5d byte\n", basename($f), filesize($f));
}
echo "\nCredenziali amministratore\n  email:    {$email}\n  password: {$pass}\n";
