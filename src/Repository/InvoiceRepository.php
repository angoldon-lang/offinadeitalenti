<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database as DB;

final class InvoiceRepository
{
    public static function find(string $id): ?array
    {
        return DB::selectOne(
            'SELECT i.*, po.legal_name AS provider_name, co.legal_name AS client_name, c.code AS contract_code
               FROM invoices i
               JOIN organizations po ON po.id = i.provider_org_id
               JOIN organizations co ON co.id = i.client_org_id
          LEFT JOIN contracts c      ON c.id  = i.contract_id
              WHERE i.id = ?',
            [$id]
        );
    }

    public static function forOrganization(string $orgId): array
    {
        return DB::select(
            'SELECT i.*, po.legal_name AS provider_name, co.legal_name AS client_name, c.code AS contract_code
               FROM invoices i
               JOIN organizations po ON po.id = i.provider_org_id
               JOIN organizations co ON co.id = i.client_org_id
          LEFT JOIN contracts c      ON c.id  = i.contract_id
              WHERE i.provider_org_id = ? OR i.client_org_id = ?
           ORDER BY COALESCE(i.issue_date, i.created_at) DESC',
            [$orgId, $orgId]
        );
    }

    public static function all(?string $status = null): array
    {
        $sql    = 'SELECT i.*, po.legal_name AS provider_name, co.legal_name AS client_name
                     FROM invoices i
                     JOIN organizations po ON po.id = i.provider_org_id
                     JOIN organizations co ON co.id = i.client_org_id
                    WHERE 1=1';
        $params = [];
        if ($status) {
            $sql     .= ' AND i.payment_status = ?';
            $params[] = $status;
        }
        return DB::select($sql . ' ORDER BY COALESCE(i.due_date, i.created_at) DESC', $params);
    }

    /**
     * Crea la fattura a partire dalle settimane approvate selezionate e le
     * marca come fatturate. L'importo non si digita: si somma.
     */
    public static function createFromTimesheets(array $d, array $timesheetIds): string
    {
        return DB::transaction(function () use ($d, $timesheetIds) {
            $id  = DB::uuid();
            $now = DB::now();

            $net = 0.0;
            foreach ($timesheetIds as $tsId) {
                $ts = DB::selectOne('SELECT amount, status, invoice_id FROM timesheets WHERE id = ?', [$tsId]);
                if (!$ts || $ts['status'] !== 'APPROVED' || $ts['invoice_id'] !== null) {
                    continue;   // solo settimane approvate e non gia' fatturate
                }
                $net += (float) $ts['amount'];
            }

            $vat   = (float) ($d['vat_rate'] ?? 22);
            $total = round($net * (1 + $vat / 100), 2);

            DB::execute(
                'INSERT INTO invoices (id, number, provider_org_id, client_org_id, contract_id, period_start, period_end,
                                       issue_date, due_date, amount_net, vat_rate, amount_total, payment_status,
                                       file_name, storage_key, uploaded_by, notes, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$id, $d['number'] ?: null, $d['provider_org_id'], $d['client_org_id'], $d['contract_id'] ?: null,
                 $d['period_start'], $d['period_end'], $d['issue_date'] ?: null, $d['due_date'] ?: null,
                 round($net, 2), $vat, $total, $d['payment_status'] ?? 'EMESSA',
                 $d['file_name'] ?? null, $d['storage_key'] ?? null, $d['uploaded_by'] ?? null,
                 $d['notes'] ?: null, $now, $now]
            );

            // Il passaggio APPROVED -> INVOICED richiede l'override: il trigger
            // di immutabilita' protegge anche questa transizione legittima.
            DB::withAdminOverride(function () use ($timesheetIds, $id, $now) {
                foreach ($timesheetIds as $tsId) {
                    DB::execute(
                        'UPDATE timesheets SET invoice_id = ?, status = ?, updated_at = ?
                          WHERE id = ? AND status = ? AND invoice_id IS NULL',
                        [$id, 'INVOICED', $now, $tsId, 'APPROVED']
                    );
                }
            });

            return $id;
        });
    }

    public static function updatePaymentStatus(string $id, string $status, ?string $paidAt, ?float $paidAmount, ?string $notes): void
    {
        DB::transaction(function () use ($id, $status, $paidAt, $paidAmount, $notes) {
            DB::execute(
                'UPDATE invoices SET payment_status = ?, paid_at = ?, paid_amount = ?, notes = COALESCE(?, notes), updated_at = ?
                  WHERE id = ?',
                [$status, $paidAt ?: null, $paidAmount, $notes, DB::now(), $id]
            );

            // Le settimane seguono lo stato della fattura che le contiene.
            $target = $status === 'PAGATA' ? 'PAID' : 'INVOICED';
            DB::withAdminOverride(function () use ($id, $target) {
                DB::execute(
                    'UPDATE timesheets SET status = ?, updated_at = ? WHERE invoice_id = ? AND status IN (?, ?)',
                    [$target, DB::now(), $id, 'INVOICED', 'PAID']
                );
            });
        });
    }

    public static function attachFile(string $id, array $file, string $userId): void
    {
        DB::execute(
            'UPDATE invoices SET file_name = ?, storage_key = ?, uploaded_by = ?, updated_at = ? WHERE id = ?',
            [$file['file_name'], $file['key'], $userId, DB::now(), $id]
        );
    }

    public static function timesheets(string $invoiceId): array
    {
        return DB::select(
            'SELECT t.*, c.code AS contract_code, r.title AS resource_title
               FROM timesheets t
               JOIN contracts c ON c.id = t.contract_id
          LEFT JOIN resources r ON r.id = c.resource_id
              WHERE t.invoice_id = ?
           ORDER BY t.week_start',
            [$invoiceId]
        );
    }

    /** Totali per la dashboard pagamenti dell'admin. */
    public static function totals(): array
    {
        $rows   = DB::select('SELECT payment_status, COUNT(*) AS n, SUM(amount_total) AS tot FROM invoices GROUP BY payment_status');
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['payment_status']] = ['n' => (int) $row['n'], 'tot' => (float) $row['tot']];
        }
        return $totals;
    }
}
