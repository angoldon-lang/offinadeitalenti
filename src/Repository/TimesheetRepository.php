<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database as DB;
use App\Support\Week;

/**
 * Rendicontazione settimanale: la parte che regge il valore contrattuale
 * del portale. Tre invarianti, tutte imposte anche dal database:
 *   1. una sola settimana per contratto  (UNIQUE contract_id, iso_year, iso_week)
 *   2. il totale e' sempre la somma delle giornate (mai un valore dal client)
 *   3. un time-sheet approvato non si modifica (trigger + override tracciato)
 */
final class TimesheetRepository
{
    public static function find(string $id): ?array
    {
        return DB::selectOne(
            'SELECT t.*,
                    c.code            AS contract_code,
                    c.agreed_rate     AS contract_rate,
                    c.rate_unit       AS contract_unit,
                    c.provider_org_id, c.client_org_id, c.start_date AS contract_start, c.end_date AS contract_end,
                    r.title           AS resource_title,
                    po.legal_name     AS provider_name,
                    co.legal_name     AS client_name
               FROM timesheets t
               JOIN contracts c      ON c.id  = t.contract_id
          LEFT JOIN resources r      ON r.id  = c.resource_id
               JOIN organizations po ON po.id = c.provider_org_id
               JOIN organizations co ON co.id = c.client_org_id
              WHERE t.id = ?',
            [$id]
        );
    }

    public static function days(string $timesheetId): array
    {
        return DB::select(
            'SELECT * FROM timesheet_days WHERE timesheet_id = ? ORDER BY work_date',
            [$timesheetId]
        );
    }

    /**
     * Restituisce la settimana, creandola precompilata se non esiste ancora.
     * La precompilazione e' il motivo per cui compilare costa 15 secondi:
     * l'utente corregge le eccezioni invece di inserire tutto.
     */
    public static function ensureWeek(array $contract, int $isoYear, int $isoWeek): array
    {
        $existing = DB::selectOne(
            'SELECT id FROM timesheets WHERE contract_id = ? AND iso_year = ? AND iso_week = ?',
            [$contract['id'], $isoYear, $isoWeek]
        );
        if ($existing) {
            return self::find($existing['id']);
        }

        $start = Week::startOf($isoYear, $isoWeek);
        $end   = Week::endOf($isoYear, $isoWeek);
        $id    = DB::uuid();
        $now   = DB::now();

        try {
            DB::transaction(function () use ($id, $contract, $isoYear, $isoWeek, $start, $end, $now) {
                DB::execute(
                    'INSERT INTO timesheets (id, contract_id, iso_year, iso_week, week_start, week_end, status, unit, total_quantity, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$id, $contract['id'], $isoYear, $isoWeek, $start->format('Y-m-d'), $end->format('Y-m-d'),
                     'DRAFT', $contract['rate_unit'], 0, $now, $now]
                );

                $holidays = Week::holidays((int) $start->format('Y')) + Week::holidays((int) $end->format('Y'));
                $cStart   = new \DateTimeImmutable($contract['start_date']);
                $cEnd     = new \DateTimeImmutable($contract['end_date']);

                foreach (Week::days($isoYear, $isoWeek) as $day) {
                    $date      = $day->format('Y-m-d');
                    $isWeekend = (int) $day->format('N') >= 6;
                    $inRange   = $day >= $cStart && $day <= $cEnd;
                    $isHoliday = isset($holidays[$date]);

                    if ($isWeekend || !$inRange) {
                        [$type, $qty] = ['NON_LAVORATO', 0];
                    } elseif ($isHoliday) {
                        [$type, $qty] = ['FESTIVO', 0];
                    } else {
                        // Default sui feriali coperti dal contratto: giornata piena
                        // (o 8 ore se il contratto e' a ore).
                        [$type, $qty] = ['LAVORO', $contract['rate_unit'] === 'HOURLY' ? 8 : 1];
                    }

                    DB::execute(
                        'INSERT INTO timesheet_days (id, timesheet_id, work_date, day_type, quantity, updated_at)
                         VALUES (?,?,?,?,?,?)',
                        [DB::uuid(), $id, $date, $type, $qty, $now]
                    );
                }
            });
        } catch (\PDOException $e) {
            // Corsa fra due richieste (doppio tap su rete lenta): il vincolo
            // UNIQUE ha gia' protetto il dato, si rilegge la riga vincente.
            $row = DB::selectOne(
                'SELECT id FROM timesheets WHERE contract_id = ? AND iso_year = ? AND iso_week = ?',
                [$contract['id'], $isoYear, $isoWeek]
            );
            if (!$row) {
                throw $e;
            }
            return self::find($row['id']);
        }

        self::recalculate($id);
        return self::find($id);
    }

    /** Aggiorna una singola giornata. Il totale non arriva MAI dal client. */
    public static function updateDay(string $timesheetId, string $date, float $quantity, ?string $dayType, ?string $note): array
    {
        $max = 24;
        $quantity = max(0, min($max, $quantity));

        DB::execute(
            'UPDATE timesheet_days
                SET quantity = ?, day_type = COALESCE(?, day_type), note = ?, updated_at = ?
              WHERE timesheet_id = ? AND work_date = ?',
            [$quantity, $dayType, $note !== '' ? $note : null, DB::now(), $timesheetId, $date]
        );

        return self::recalculate($timesheetId);
    }

    /** Ricalcola totale e importo stimato dalla somma delle giornate. */
    public static function recalculate(string $timesheetId): array
    {
        $row = DB::selectOne(
            'SELECT COALESCE(SUM(quantity), 0) AS total FROM timesheet_days WHERE timesheet_id = ?',
            [$timesheetId]
        );
        $total = round((float) ($row['total'] ?? 0), 2);

        DB::execute(
            'UPDATE timesheets SET total_quantity = ?, updated_at = ? WHERE id = ?',
            [$total, DB::now(), $timesheetId]
        );

        $ts = self::find($timesheetId);
        return [
            'total'  => $total,
            'amount' => $ts ? $total * (float) $ts['contract_rate'] : 0.0,
        ];
    }

    public static function submit(string $id, string $userId, string $userName): void
    {
        DB::transaction(function () use ($id, $userId, $userName) {
            DB::execute(
                'UPDATE timesheets SET status = ?, submitted_by = ?, submitted_at = ?, rejection_reason = NULL, updated_at = ?
                  WHERE id = ? AND status IN (?, ?)',
                ['SUBMITTED', $userId, DB::now(), DB::now(), $id, 'DRAFT', 'REJECTED']
            );
            self::event($id, 'DRAFT', 'SUBMITTED', $userId, $userName, null);
        });
    }

    /**
     * Approvazione: congela la tariffa di contratto sulla settimana.
     * Da questo momento il record e' immutabile (trigger di database).
     */
    public static function approve(string $id, string $userId, string $userName): void
    {
        $ts = self::find($id);
        if (!$ts || $ts['status'] !== 'SUBMITTED') {
            throw new \RuntimeException('La settimana non e\' in attesa di approvazione.');
        }

        $rate   = (float) $ts['contract_rate'];
        $amount = round((float) $ts['total_quantity'] * $rate, 2);

        DB::transaction(function () use ($id, $userId, $userName, $rate, $amount) {
            DB::execute(
                'UPDATE timesheets
                    SET status = ?, rate_snapshot = ?, amount = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ?
                  WHERE id = ? AND status = ?',
                ['APPROVED', $rate, $amount, $userId, DB::now(), DB::now(), $id, 'SUBMITTED']
            );
            self::event($id, 'SUBMITTED', 'APPROVED', $userId, $userName, null);
        });
    }

    public static function reject(string $id, string $userId, string $userName, string $reason): void
    {
        DB::transaction(function () use ($id, $userId, $userName, $reason) {
            DB::execute(
                'UPDATE timesheets
                    SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ?
                  WHERE id = ? AND status = ?',
                ['REJECTED', $reason, $userId, DB::now(), DB::now(), $id, 'SUBMITTED']
            );
            self::event($id, 'SUBMITTED', 'REJECTED', $userId, $userName, $reason);
        });
    }

    public static function event(string $tsId, ?string $from, string $to, ?string $actorId, ?string $actorName, ?string $reason): void
    {
        DB::execute(
            'INSERT INTO timesheet_events (id, timesheet_id, from_status, to_status, actor_id, actor_name, reason, created_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [DB::uuid(), $tsId, $from, $to, $actorId, $actorName, $reason, DB::now()]
        );
    }

    public static function events(string $tsId): array
    {
        return DB::select(
            'SELECT * FROM timesheet_events WHERE timesheet_id = ? ORDER BY created_at DESC',
            [$tsId]
        );
    }

    /** Settimane gia' registrate per un contratto, indicizzate per "anno-settimana". */
    public static function indexForContract(string $contractId): array
    {
        $rows = DB::select('SELECT * FROM timesheets WHERE contract_id = ?', [$contractId]);
        $map  = [];
        foreach ($rows as $row) {
            $map[$row['iso_year'] . '-' . $row['iso_week']] = $row;
        }
        return $map;
    }

    /** Coda di approvazione del Richiedente. */
    public static function pendingForClient(string $clientOrgId): array
    {
        return DB::select(
            'SELECT t.*, c.code AS contract_code, c.agreed_rate, c.rate_unit,
                    r.title AS resource_title, po.legal_name AS provider_name
               FROM timesheets t
               JOIN contracts c      ON c.id  = t.contract_id
          LEFT JOIN resources r      ON r.id  = c.resource_id
               JOIN organizations po ON po.id = c.provider_org_id
              WHERE c.client_org_id = ? AND t.status = ?
           ORDER BY t.week_start',
            [$clientOrgId, 'SUBMITTED']
        );
    }

    /** Ultime settimane di un'organizzazione, come fornitore o come cliente. */
    public static function recentForOrg(string $orgId, string $side, int $limit = 20): array
    {
        $column = $side === 'provider' ? 'c.provider_org_id' : 'c.client_org_id';
        return DB::select(
            "SELECT t.*, c.code AS contract_code, c.agreed_rate, c.rate_unit,
                    r.title AS resource_title,
                    po.legal_name AS provider_name, co.legal_name AS client_name
               FROM timesheets t
               JOIN contracts c      ON c.id  = t.contract_id
          LEFT JOIN resources r      ON r.id  = c.resource_id
               JOIN organizations po ON po.id = c.provider_org_id
               JOIN organizations co ON co.id = c.client_org_id
              WHERE {$column} = ?
           ORDER BY t.week_start DESC
              LIMIT {$limit}",
            [$orgId]
        );
    }

    /** Monitor amministratore: tutte le settimane con filtro di stato. */
    public static function monitor(?string $status = null, ?string $from = null): array
    {
        $sql    = 'SELECT t.*, c.code AS contract_code, r.title AS resource_title,
                          po.legal_name AS provider_name, co.legal_name AS client_name
                     FROM timesheets t
                     JOIN contracts c      ON c.id  = t.contract_id
                LEFT JOIN resources r      ON r.id  = c.resource_id
                     JOIN organizations po ON po.id = c.provider_org_id
                     JOIN organizations co ON co.id = c.client_org_id
                    WHERE 1=1';
        $params = [];
        if ($status) {
            $sql     .= ' AND t.status = ?';
            $params[] = $status;
        }
        if ($from) {
            $sql     .= ' AND t.week_start >= ?';
            $params[] = $from;
        }
        return DB::select($sql . ' ORDER BY t.week_start DESC, co.legal_name LIMIT 300', $params);
    }

    /** Settimane approvate e non ancora fatturate, base del riepilogo. */
    public static function billable(string $providerOrgId, string $from, string $to): array
    {
        return DB::select(
            'SELECT t.*, c.code AS contract_code, c.agreed_rate, c.rate_unit, c.client_org_id,
                    r.title AS resource_title, co.legal_name AS client_name
               FROM timesheets t
               JOIN contracts c      ON c.id  = t.contract_id
          LEFT JOIN resources r      ON r.id  = c.resource_id
               JOIN organizations co ON co.id = c.client_org_id
              WHERE c.provider_org_id = ? AND t.status = ? AND t.invoice_id IS NULL
                AND t.week_start >= ? AND t.week_end <= ?
           ORDER BY co.legal_name, t.week_start',
            [$providerOrgId, 'APPROVED', $from, $to]
        );
    }
}
