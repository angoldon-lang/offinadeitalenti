<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = []): string
    {
        $data = array_merge(self::$shared, $data);
        return self::capture($template, $data);
    }

    /** Rende una vista dentro il layout applicativo. */
    public static function page(string $template, array $data = [], string $layout = 'layout'): string
    {
        $data           = array_merge(self::$shared, $data);
        $data['content'] = self::capture($template, $data);
        return self::capture($layout, $data);
    }

    private static function capture(string $template, array $data): string
    {
        $file = dirname(__DIR__, 2) . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vista non trovata: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(string $path = ''): string
    {
        return (string) Config::get('app.base_path', '') . $path;
    }

    /** Formatta un importo in euro all'italiana. */
    public static function money(float|string|null $amount, string $currency = '€'): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }
        return number_format((float) $amount, 2, ',', '.') . ' ' . $currency;
    }

    /** Formatta una quantita' (giorni o ore) senza decimali inutili. */
    public static function qty(float|string|null $value): string
    {
        $value = (float) $value;
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    public static function date(?string $date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '—';
        }
        try {
            return (new \DateTimeImmutable($date))->format($format);
        } catch (\Throwable) {
            return $date;
        }
    }
}
