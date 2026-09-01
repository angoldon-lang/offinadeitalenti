<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Config;
use App\Core\Database as DB;

final class RequestRepository
{
    public static function find(string $id): ?array
    {
        return DB::selectOne(
            'SELECT rq.*, r.title AS resource_title, r.organization_id AS provider_org_id,
                    r.seniority, r.rate_min, r.rate_max, r.rate_unit,
                    co.legal_name AS client_name, co.sector AS client_sector, co.size_range AS client_size,
                    po.legal_name AS provider_name
               FROM resource_requests rq
               JOIN resources r      ON r.id  = rq.resource_id
               JOIN organizations co ON co.id = rq.client_org_id
               JOIN organizations po ON po.id = r.organization_id
              WHERE rq.id = ?',
            [$id]
        );
    }

    public static function create(array $d): string
    {
        $id      = DB::uuid();
        $now     = DB::now();
        $expiry  = (int) Config::get('security.request_expiry_days', 7);
        $expires = (new \DateTimeImmutable('today'))->modify("+{$expiry} days")->format('Y-m-d');

        DB::execute(
            'INSERT INTO resource_requests (id, resource_id, client_org_id, created_by, status, project_brief,
                                            estimated_duration, desired_start_date, budget_hint, budget_unit,
                                            expires_at, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$id, $d['resource_id'], $d['client_org_id'], $d['created_by'], 'REQUESTED', $d['project_brief'],
             $d['estimated_duration'] ?: null, $d['desired_start_date'] ?: null,
             $d['budget_hint'] ?: null, $d['budget_unit'] ?: null, $expires, $now, $now]
        );
        return $id;
    }

    public static function forClient(string $orgId): array
    {
        return DB::select(
            'SELECT rq.*, r.title AS resource_title, r.seniority,
                    CASE WHEN rq.status IN (\'ACCEPTED\',\'IN_NEGOTIATION\',\'CONTRACTED\')
                         THEN po.legal_name ELSE NULL END AS provider_name
               FROM resource_requests rq
               JOIN resources r      ON r.id  = rq.resource_id
               JOIN organizations po ON po.id = r.organization_id
              WHERE rq.client_org_id = ?
           ORDER BY rq.created_at DESC',
            [$orgId]
        );
    }

    /**
     * Richieste ricevute dall'offerente. Il nome dell'azienda cliente resta
     * nascosto finche' la richiesta non e' accettata: e' cio' che tiene in
     * piedi il modello di intermediazione.
     */
    public static function forProvider(string $orgId): array
    {
        return DB::select(
            'SELECT rq.*, r.title AS resource_title,
                    CASE WHEN rq.status IN (\'ACCEPTED\',\'IN_NEGOTIATION\',\'CONTRACTED\')
                         THEN co.legal_name ELSE NULL END AS client_name,
                    co.sector AS client_sector, co.size_range AS client_size
               FROM resource_requests rq
               JOIN resources r      ON r.id  = rq.resource_id
               JOIN organizations co ON co.id = rq.client_org_id
              WHERE r.organization_id = ?
           ORDER BY CASE WHEN rq.status = \'REQUESTED\' THEN 0 ELSE 1 END, rq.created_at DESC',
            [$orgId]
        );
    }

    public static function respond(string $id, string $status, ?string $reason = null): void
    {
        DB::execute(
            'UPDATE resource_requests SET status = ?, decline_reason = ?, responded_at = ?, updated_at = ?
              WHERE id = ? AND status = ?',
            [$status, $reason, DB::now(), DB::now(), $id, 'REQUESTED']
        );
    }

    public static function setStatus(string $id, string $status): void
    {
        DB::execute('UPDATE resource_requests SET status = ?, updated_at = ? WHERE id = ?', [$status, DB::now(), $id]);
    }

    public static function countPendingForProvider(string $orgId): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS n FROM resource_requests rq
               JOIN resources r ON r.id = rq.resource_id
              WHERE r.organization_id = ? AND rq.status = ?',
            [$orgId, 'REQUESTED']
        );
        return (int) ($row['n'] ?? 0);
    }
}
