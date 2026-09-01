<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database as DB;

final class OrganizationRepository
{
    public static function find(string $id): ?array
    {
        return DB::selectOne('SELECT * FROM organizations WHERE id = ?', [$id]);
    }

    public static function create(array $data): string
    {
        $id  = DB::uuid();
        $now = DB::now();
        DB::execute(
            'INSERT INTO organizations (id, type, legal_name, vat_number, sector, size_range, website, phone, address, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $id, $data['type'], $data['legal_name'], $data['vat_number'] ?: null,
                $data['sector'] ?? null, $data['size_range'] ?? null, $data['website'] ?? null,
                $data['phone'] ?? null, $data['address'] ?? null, 'PENDING_APPROVAL', $now, $now,
            ]
        );
        return $id;
    }

    /** Attivazione manuale: e' qui che l'admin decide la durata del profilo. */
    public static function activate(string $id, string $expiresAt, ?string $ref, ?string $notes, string $adminId): void
    {
        $org = self::find($id);
        DB::transaction(function () use ($id, $expiresAt, $ref, $notes, $adminId, $org) {
            DB::execute(
                'UPDATE organizations
                    SET status = ?, access_expires_at = ?, grace_ends_at = ?, external_contract_ref = ?,
                        admin_notes = ?, approved_by = ?, approved_at = ?, updated_at = ?
                  WHERE id = ?',
                ['ACTIVE', $expiresAt, self::graceEnd($expiresAt), $ref, $notes, $adminId, DB::now(), DB::now(), $id]
            );
            DB::execute(
                'INSERT INTO account_extensions (id, organization_id, previous_expiry, new_expiry, reason, external_ref, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,?)',
                [DB::uuid(), $id, $org['access_expires_at'] ?? null, $expiresAt, 'Attivazione/proroga manuale', $ref, $adminId, DB::now()]
            );
        });
    }

    public static function setStatus(string $id, string $status): void
    {
        DB::execute('UPDATE organizations SET status = ?, updated_at = ? WHERE id = ?', [$status, DB::now(), $id]);
    }

    public static function graceEnd(string $expiresAt): string
    {
        $days = (int) \App\Core\Config::get('security.grace_days', 15);
        return (new \DateTimeImmutable($expiresAt))->modify("+{$days} days")->format('Y-m-d');
    }

    /** @return array<int, array<string,mixed>> */
    public static function all(?string $status = null, ?string $type = null): array
    {
        $sql    = 'SELECT o.*, (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id) AS users_count
                     FROM organizations o WHERE 1=1';
        $params = [];
        if ($status) {
            $sql .= ' AND o.status = ?';
            $params[] = $status;
        }
        if ($type) {
            $sql .= ' AND o.type = ?';
            $params[] = $type;
        }
        return DB::select($sql . ' ORDER BY CASE WHEN o.status = ? THEN 0 ELSE 1 END, o.legal_name', array_merge($params, ['PENDING_APPROVAL']));
    }

    /** Account in scadenza entro N giorni: la lista di lavoro commerciale dell'admin. */
    public static function expiringWithin(int $days): array
    {
        $limit = (new \DateTimeImmutable('today'))->modify("+{$days} days")->format('Y-m-d');
        return DB::select(
            'SELECT * FROM organizations
              WHERE status IN (?, ?) AND access_expires_at IS NOT NULL AND access_expires_at <= ?
           ORDER BY access_expires_at',
            ['ACTIVE', 'GRACE', $limit]
        );
    }

    public static function daysLeft(?string $expiresAt): ?int
    {
        if (!$expiresAt) {
            return null;
        }
        $today = new \DateTimeImmutable('today');
        return (int) $today->diff(new \DateTimeImmutable($expiresAt))->format('%r%a');
    }
}
