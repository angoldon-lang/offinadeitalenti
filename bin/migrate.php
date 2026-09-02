<?php
declare(strict_types=1);
/**
 * Esegue le migrazioni SQL del driver configurato.
 *   php bin/migrate.php            applica lo schema
 *   php bin/migrate.php --fresh    azzera e riapplica (solo sviluppo)
 */
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

$driver = Database::driver();
$fresh  = in_array('--fresh', $argv, true);

if ($fresh) {
    if ($driver === 'sqlite') {
        $path = (string) Config::get('db.path');
        if (is_file($path)) {
            unlink($path);
            echo "Database SQLite azzerato.\n";
        }
    } else {
        fwrite(STDERR, "--fresh e' disponibile solo su SQLite (sviluppo).\n");
        exit(1);
    }
}

$dir   = dirname(__DIR__) . '/migrations/' . $driver;
$files = glob($dir . '/*.sql') ?: [];
sort($files);

if ($files === []) {
    fwrite(STDERR, "Nessuna migrazione trovata in {$dir}\n");
    exit(1);
}

$pdo = Database::pdo();
foreach ($files as $file) {
    echo '→ ' . basename($file) . "\n";
    // Si divide SOLO su righe che sono esattamente il marcatore: la stringa
    // puo' comparire anche dentro un commento, e spezzarla li' romperebbe il file.
    $statements = array_filter(
        array_map('trim', (array) preg_split('/^--\s*;;\s*--\s*$/m', (string) file_get_contents($file))),
        static fn (string $s): bool => $s !== '' && !preg_match('/^(--[^\n]*\n?)+$/', $s)
    );

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            fwrite(STDERR, "\nErrore su:\n" . substr($sql, 0, 200) . "...\n" . $e->getMessage() . "\n");
            exit(1);
        }
    }
    echo '  ' . count($statements) . " statement eseguiti\n";
}

echo "Migrazioni completate ({$driver}).\n";
