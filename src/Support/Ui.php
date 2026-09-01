<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\View;
use App\Domain\Enums;

/** Piccoli helper di presentazione condivisi dalle viste. */
final class Ui
{
    /** Badge di stato: colore + icona + etichetta, mai il solo colore. */
    public static function badge(string $color, string $icon, string $label): string
    {
        return sprintf(
            '<span class="badge badge--%s"><span aria-hidden="true">%s</span> %s</span>',
            View::e($color),
            View::e($icon),
            View::e($label)
        );
    }

    public static function timesheetBadge(?string $status): string
    {
        [$color, $icon] = Enums::TIMESHEET_BADGE[$status] ?? ['slate', '○'];
        return self::badge($color, $icon, Enums::label(Enums::TIMESHEET_STATUS, $status ?? 'DRAFT'));
    }

    public static function paymentBadge(?string $status): string
    {
        [$color, $icon] = Enums::PAYMENT_BADGE[$status] ?? ['slate', '○'];
        return self::badge($color, $icon, Enums::label(Enums::PAYMENT_STATUS, $status ?? 'DA_EMETTERE'));
    }

    public static function resourceBadge(?string $status): string
    {
        return match ($status) {
            'PUBLISHED' => self::badge('emerald', '✓', 'Pubblicata'),
            'IN_REVIEW' => self::badge('amber', '◔', 'In approvazione'),
            'REJECTED'  => self::badge('rose', '✕', 'Da correggere'),
            'ARCHIVED'  => self::badge('slate', '▤', 'Archiviata'),
            default     => self::badge('slate', '○', 'Bozza'),
        };
    }

    public static function orgBadge(?string $status): string
    {
        return match ($status) {
            'ACTIVE'    => self::badge('emerald', '✓', 'Attivo'),
            'GRACE'     => self::badge('amber', '◔', 'In scadenza'),
            'EXPIRED'   => self::badge('rose', '!', 'Scaduto'),
            'SUSPENDED' => self::badge('rose', '✕', 'Sospeso'),
            default     => self::badge('slate', '○', 'In attivazione'),
        };
    }

    /** Unita' di misura leggibile: "3,5 giorni" oppure "28 ore". */
    public static function quantity(float|string|null $value, ?string $unit): string
    {
        $n     = (float) $value;
        $label = $unit === 'HOURLY'
            ? ($n === 1.0 ? 'ora' : 'ore')
            : ($n === 1.0 ? 'giorno' : 'giorni');

        return View::qty($n) . ' ' . $label;
    }

    /** "3 giorni fa", per le liste dove la data esatta non serve. */
    public static function ago(?string $datetime): string
    {
        if (!$datetime) {
            return '—';
        }
        $diff = (new \DateTimeImmutable('now'))->getTimestamp() - (new \DateTimeImmutable($datetime))->getTimestamp();

        return match (true) {
            $diff < 90       => 'poco fa',
            $diff < 3600     => intdiv($diff, 60) . ' min fa',
            $diff < 86400    => intdiv($diff, 3600) . ' h fa',
            $diff < 2592000  => intdiv($diff, 86400) . ' gg fa',
            default          => View::date($datetime),
        };
    }
}
