<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;
use App\Core\View;

// Autoloader PSR-4 minimale: nessuna dipendenza da Composer, cosi' il deploy
// su hosting condiviso e' una semplice copia FTP.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file     = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Config::load(dirname(__DIR__) . '/config');
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Rome'));

if (Config::isLocal()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', rtrim((string) Config::get('storage.logs'), '/') . '/php-error.log');
}

if (PHP_SAPI !== 'cli') {
    Session::start();
    View::share('appName', Config::get('app.name'));
}
