<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Aiutanti sulla settimana ISO (lun-dom), l'unita' di rendicontazione.
 */
final class Week
{
    public const DAY_LABELS = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

    public static function startOf(int $isoYear, int $isoWeek): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today'))->setISODate($isoYear, $isoWeek, 1);
    }

    public static function endOf(int $isoYear, int $isoWeek): \DateTimeImmutable
    {
        return self::startOf($isoYear, $isoWeek)->modify('+6 days');
    }

    /** @return array{0:int,1:int} [anno ISO, settimana ISO] */
    public static function of(\DateTimeImmutable $date): array
    {
        return [(int) $date->format('o'), (int) $date->format('W')];
    }

    public static function current(): array
    {
        return self::of(new \DateTimeImmutable('today'));
    }

    /** Le 7 date della settimana, dal lunedi' alla domenica. */
    public static function days(int $isoYear, int $isoWeek): array
    {
        $start = self::startOf($isoYear, $isoWeek);
        $days  = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->modify("+{$i} days");
        }
        return $days;
    }

    /** @return array{0:int,1:int} settimana precedente/successiva */
    public static function shift(int $isoYear, int $isoWeek, int $delta): array
    {
        return self::of(self::startOf($isoYear, $isoWeek)->modify(($delta * 7) . ' days'));
    }

    public static function label(int $isoYear, int $isoWeek): string
    {
        $start = self::startOf($isoYear, $isoWeek);
        $end   = self::endOf($isoYear, $isoWeek);
        $mesi  = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
                  'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

        $m1 = $mesi[(int) $start->format('n')];
        $m2 = $mesi[(int) $end->format('n')];

        return $m1 === $m2
            ? $start->format('j') . '–' . $end->format('j') . ' ' . $m2 . ' ' . $end->format('Y')
            : $start->format('j') . ' ' . $m1 . ' – ' . $end->format('j') . ' ' . $m2 . ' ' . $end->format('Y');
    }

    /**
     * Elenco delle settimane coperte da un contratto, dalla piu' recente.
     * Non si generano settimane future oltre quella corrente: rendicontare
     * in anticipo non ha senso e confonde.
     */
    public static function forContract(string $startDate, string $endDate, ?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $from  = new \DateTimeImmutable($startDate);
        $to    = min(new \DateTimeImmutable($endDate), $today);

        if ($to < $from) {
            return [];
        }

        $weeks  = [];
        $cursor = $from;
        while ($cursor <= $to) {
            [$y, $w] = self::of($cursor);
            $weeks[$y . '-' . $w] = ['iso_year' => $y, 'iso_week' => $w];
            $cursor = $cursor->modify('+7 days');
        }
        // la settimana finale va inclusa anche se il ciclo l'ha superata
        [$y, $w] = self::of($to);
        $weeks[$y . '-' . $w] = ['iso_year' => $y, 'iso_week' => $w];

        return array_reverse(array_values($weeks));
    }

    /** Festivita' italiane fisse + Pasquetta, per precompilare la griglia. */
    public static function holidays(int $year): array
    {
        $fixed = [
            "$year-01-01" => 'Capodanno',
            "$year-01-06" => 'Epifania',
            "$year-04-25" => 'Festa della Liberazione',
            "$year-05-01" => 'Festa del Lavoro',
            "$year-06-02" => 'Festa della Repubblica',
            "$year-08-15" => 'Ferragosto',
            "$year-11-01" => 'Ognissanti',
            "$year-12-08" => 'Immacolata',
            "$year-12-25" => 'Natale',
            "$year-12-26" => 'Santo Stefano',
        ];

        // easter_date() richiede l'estensione calendar: si calcola a mano per portabilita'.
        $easter = self::easter($year);
        $fixed[$easter->modify('+1 day')->format('Y-m-d')] = 'Lunedi\' dell\'Angelo';

        return $fixed;
    }

    private static function easter(int $year): \DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
