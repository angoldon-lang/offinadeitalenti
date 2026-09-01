<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path, int $status = 302): never
    {
        $base = (string) Config::get('app.base_path', '');
        header('Location: ' . $base . $path, true, $status);
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        $titles = [
            400 => 'Richiesta non valida',
            401 => 'Accesso richiesto',
            403 => 'Non autorizzato',
            404 => 'Pagina non trovata',
            419 => 'Sessione scaduta',
            422 => 'Dati non validi',
            500 => 'Errore interno',
        ];
        $title = $titles[$status] ?? 'Errore';
        echo View::render('error', ['status' => $status, 'title' => $title, 'message' => $message]);
        exit;
    }

    /** Download di un file privato: mai un URL diretto al filesystem. */
    public static function download(string $absolutePath, string $fileName): never
    {
        if (!is_file($absolutePath)) {
            self::abort(404, 'Il file non esiste piu\'.');
        }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($absolutePath);
        exit;
    }
}
