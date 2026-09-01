<?php
declare(strict_types=1);

namespace App\Core;

final class Storage
{
    /**
     * Salva un PDF caricato in una cartella FUORI dalla document root.
     * Ritorna la chiave relativa da salvare a database.
     *
     * @throws \RuntimeException con un messaggio mostrabile all'utente
     */
    public static function storePdf(array $file, string $folder): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage((int) ($file['error'] ?? 4)));
        }

        $maxBytes = (int) Config::get('storage.max_bytes', 20 * 1024 * 1024);
        if ($file['size'] > $maxBytes) {
            throw new \RuntimeException('Il file supera il limite di ' . round($maxBytes / 1048576) . ' MB.');
        }

        // Il tipo dichiarato dal browser non e' attendibile: si verifica il contenuto.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);
        if ($mime !== 'application/pdf') {
            throw new \RuntimeException('Sono ammessi solo file PDF (rilevato: ' . $mime . ').');
        }

        $base = rtrim((string) Config::get('storage.documents'), '/');
        $dir  = $base . '/' . trim($folder, '/');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossibile creare la cartella dei documenti.');
        }

        $hash = hash_file('sha256', $file['tmp_name']);
        $name = bin2hex(random_bytes(16)) . '.pdf';
        $dest = $dir . '/' . $name;

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $dest)
            : rename($file['tmp_name'], $dest);

        if (!$moved) {
            throw new \RuntimeException('Salvataggio del file non riuscito.');
        }
        @chmod($dest, 0640);

        return [
            'key'       => trim($folder, '/') . '/' . $name,
            'file_name' => self::sanitizeName((string) ($file['name'] ?? 'documento.pdf')),
            'size'      => (int) $file['size'],
            'hash'      => $hash,
        ];
    }

    public static function absolutePath(string $key): string
    {
        $base = rtrim((string) Config::get('storage.documents'), '/');
        $path = $base . '/' . ltrim($key, '/');

        // Difesa contro il path traversal su una chiave manomessa a database.
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, (string) realpath($base))) {
            Response::abort(404, 'Documento non disponibile.');
        }
        return $real;
    }

    public static function delete(string $key): void
    {
        $base = rtrim((string) Config::get('storage.documents'), '/');
        $real = realpath($base . '/' . ltrim($key, '/'));
        if ($real !== false && str_starts_with($real, (string) realpath($base))) {
            @unlink($real);
        }
    }

    private static function sanitizeName(string $name): string
    {
        $name = preg_replace('/[^\w\-. ]+/u', '_', $name) ?? 'documento.pdf';
        return mb_substr($name, 0, 120);
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Il file e\' troppo grande per il server.',
            UPLOAD_ERR_PARTIAL                        => 'Caricamento interrotto: riprova.',
            UPLOAD_ERR_NO_FILE                        => 'Nessun file selezionato.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Il server non riesce a salvare il file.',
            default                                   => 'Caricamento non riuscito.',
        };
    }
}
