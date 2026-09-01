<?php
declare(strict_types=1);
/**
 * Crea l'archivio da caricare via FTP su Aruba.
 *   php bin/package.php            layout A (document root su public/)
 *   php bin/package.php --flat     layout B (tutto dentro la document root)
 */
$root = dirname(__DIR__);
$flat = in_array('--flat', $argv, true);
$out  = $root . '/dist/officina-' . date('Ymd-His') . ($flat ? '-flat' : '') . '.zip';

@mkdir($root . '/dist', 0755, true);

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Impossibile creare {$out}\n");
    exit(1);
}

$skip = ['dist', '.git', 'storage/dev.sqlite', 'config/config.local.php', 'storage/logs', 'storage/documents'];
$dirs = ['src', 'views', 'migrations', 'bin', 'config', 'public'];

foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        foreach ($skip as $s) {
            if (str_starts_with($rel, $s)) {
                continue 2;
            }
        }
        // Nel layout "flat" il contenuto di public/ finisce nella radice.
        $dest = $flat && str_starts_with($rel, 'public/') ? substr($rel, 7) : $rel;
        $zip->addFile($file->getPathname(), $dest);
    }
}

// cartelle scrivibili, create vuote
foreach (['storage/documents', 'storage/logs'] as $d) {
    $zip->addEmptyDir($d);
    $zip->addFromString($d . '/.gitkeep', '');
}
$zip->addFromString('storage/.htaccess', "Require all denied\nDeny from all\n");
$zip->addFromString('config/.htaccess', "Require all denied\nDeny from all\n");
if ($flat) {
    // Nel layout flat anche il codice sta sotto la document root: va chiuso.
    foreach (['src', 'views', 'migrations', 'bin'] as $d) {
        $zip->addFromString($d . '/.htaccess', "Require all denied\nDeny from all\n");
    }
}

$count = $zip->numFiles;
$zip->close();

echo "Archivio: {$out}\n";
echo "File: {$count}\n";
echo $flat
    ? "Layout FLAT: scompatta il contenuto direttamente nella document root Aruba.\n"
    : "Layout STANDARD: carica la cartella e punta il dominio su public/.\n";
