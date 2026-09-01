<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $data = [];

    public static function load(string $dir): void
    {
        $local   = $dir . '/config.local.php';
        $example = $dir . '/config.example.php';
        $file    = is_file($local) ? $local : $example;

        if (!is_file($file)) {
            throw new \RuntimeException('Configurazione non trovata: copia config.example.php in config.local.php');
        }
        self::$data = require $file;
    }

    /** Accesso con notazione puntata: Config::get('db.host') */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function isLocal(): bool
    {
        return self::get('app.env') === 'local';
    }
}
