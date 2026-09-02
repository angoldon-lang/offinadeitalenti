<?php
declare(strict_types=1);
/**
 * Prepara i file SQL da incollare in phpMyAdmin su Aruba (niente SSH, niente CLI).
 *
 *   php bin/make-deploy-sql.php
 *
 * Produce in deploy/sql/:
 *   01-schema.sql        tutte le tabelle (un solo incolla)
 *   02-trigger-N.sql     un trigger per file: phpMyAdmin non ne digerisce piu' d'uno insieme
 *   03-skills.sql        tassonomia delle competenze
 *
 * L'utente amministratore NON e' incluso: si crea da /installazione, cosi' la
 * password non transita da nessun file.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database as DB;

$dir = dirname(__DIR__) . '/deploy/sql';
@mkdir($dir, 0755, true);

$q = static fn (?string $v): string => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";

// ---- 1. schema e trigger ----------------------------------------------------
$source     = (string) file_get_contents(dirname(__DIR__) . '/migrations/mysql/001_schema.sql');
// Si divide SOLO su righe che sono esattamente il marcatore, e si scartano
// i blocchi di soli commenti: la stringa marcatore puo' comparire dentro un
// commento, e spezzarla li' produrrebbe SQL non valido.
$statements = array_values(array_filter(
    array_map('trim', (array) preg_split('/^--\s*;;\s*--\s*$/m', $source)),
    static fn (string $s): bool => $s !== '' && !preg_match('/^(--[^\n]*\n?)+$/', $s)
));

// Ogni blocco deve iniziare con uno statement SQL una volta tolti i commenti
// iniziali. Se non e' cosi' il file sorgente e' stato diviso male: meglio
// fermarsi qui che consegnare un file che fallisce in phpMyAdmin.
foreach ($statements as $sql) {
    $firstCode = trim((string) preg_replace('/^(\s*--[^\n]*\n)+/', '', $sql));
    if (!preg_match('/^(CREATE|INSERT|ALTER|DROP|SET)\b/i', $firstCode)) {
        fwrite(STDERR, "Blocco SQL non valido, generazione interrotta:\n"
            . substr($firstCode, 0, 160) . "\n");
        exit(1);
    }
}

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

// L'amministratore NON si crea da SQL: la pagina /installazione lo crea dal
// browser e si disattiva da sola. Cosi' nessuna password passa da un file.

echo "File pronti in deploy/sql/:\n";
foreach (glob($dir . '/*.sql') ?: [] as $f) {
    printf("  %-22s %5d byte\n", basename($f), filesize($f));
}
echo "\nL'amministratore si crea dal browser su /installazione dopo aver caricato i file.\n";
