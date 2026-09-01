<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database as DB;

final class ContractRepository
{
    public static function find(string $id): ?array
    {
        return DB::selectOne(
            'SELECT c.*, r.title AS resource_title,
                    po.legal_name AS provider_name, co.legal_name AS client_name
               FROM contracts c
          LEFT JOIN resources r      ON r.id  = c.resource_id
               JOIN organizations po ON po.id = c.provider_org_id
               JOIN organizations co ON co.id = c.client_org_id
              WHERE c.id = ?',
            [$id]
        );
    }

    /** Un contratto e' visibile solo alle sue due parti (o all'admin). */
    public static function findForOrg(string $id, ?string $orgId, bool $isAdmin): ?array
    {
        $contract = self::find($id);
        if (!$contract) {
            return null;
        }
        if ($isAdmin) {
            return $contract;
        }
        if ($orgId !== null && ($contract['provider_org_id'] === $orgId || $contract['client_org_id'] === $orgId)) {
            return $contract;
        }
        return null;
    }

    public static function forOrganization(string $orgId): array
    {
        return DB::select(
            'SELECT c.*, r.title AS resource_title,
                    po.legal_name AS provider_name, co.legal_name AS client_name,
                    (SELECT COUNT(*) FROM contract_documents d WHERE d.contract_id = c.id) AS docs_count
               FROM contracts c
          LEFT JOIN resources r      ON r.id  = c.resource_id
               JOIN organizations po ON po.id = c.provider_org_id
               JOIN organizations co ON co.id = c.client_org_id
              WHERE c.provider_org_id = ? OR c.client_org_id = ?
           ORDER BY c.status, c.end_date DESC',
            [$orgId, $orgId]
        );
    }

    public static function all(): array
    {
        return DB::select(
            'SELECT c.*, r.title AS resource_title,
                    po.legal_name AS provider_name, co.legal_name AS client_name
               FROM contracts c
          LEFT JOIN resources r      ON r.id  = c.resource_id
               JOIN organizations po ON po.id = c.provider_org_id
               JOIN organizations co ON co.id = c.client_org_id
           ORDER BY c.status, c.end_date DESC'
        );
    }

    /** Contratti attivi su cui il fornitore deve rendicontare. */
    public static function activeForProvider(string $orgId): array
    {
        return DB::select(
            'SELECT c.*, r.title AS resource_title, co.legal_name AS client_name
               FROM contracts c
          LEFT JOIN resources r      ON r.id  = c.resource_id
               JOIN organizations co ON co.id = c.client_org_id
              WHERE c.provider_org_id = ? AND c.status = ? AND c.timesheet_required = 1
                AND c.start_date <= ?
           ORDER BY co.legal_name',
            [$orgId, 'ACTIVE', (new \DateTimeImmutable('today'))->format('Y-m-d')]
        );
    }

    public static function create(array $d): string
    {
        $id  = DB::uuid();
        $now = DB::now();
        DB::execute(
            'INSERT INTO contracts (id, code, provider_org_id, client_org_id, resource_id, request_id, status,
                                    start_date, end_date, agreed_rate, rate_unit, timesheet_required,
                                    auto_approve_after_days, visibility, notes, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $id, $d['code'], $d['provider_org_id'], $d['client_org_id'], $d['resource_id'] ?: null,
                $d['request_id'] ?? null, $d['status'] ?? 'DRAFT', $d['start_date'], $d['end_date'],
                $d['agreed_rate'], $d['rate_unit'], !empty($d['timesheet_required']) ? 1 : 0,
                $d['auto_approve_after_days'] ?: null, $d['visibility'] ?? 'CONDIVISO', $d['notes'] ?: null,
                $now, $now,
            ]
        );
        return $id;
    }

    public static function update(string $id, array $d): void
    {
        DB::execute(
            'UPDATE contracts
                SET status = ?, start_date = ?, end_date = ?, agreed_rate = ?, rate_unit = ?,
                    timesheet_required = ?, auto_approve_after_days = ?, notes = ?, updated_at = ?
              WHERE id = ?',
            [
                $d['status'], $d['start_date'], $d['end_date'], $d['agreed_rate'], $d['rate_unit'],
                !empty($d['timesheet_required']) ? 1 : 0, $d['auto_approve_after_days'] ?: null,
                $d['notes'] ?: null, DB::now(), $id,
            ]
        );
    }

    public static function nextCode(): string
    {
        $year = date('Y');
        $row  = DB::selectOne(
            'SELECT COUNT(*) AS n FROM contracts WHERE code LIKE ?',
            ["CTR-{$year}-%"]
        );
        return sprintf('CTR-%s-%04d', $year, ((int) ($row['n'] ?? 0)) + 1);
    }

    // ---- documenti ---------------------------------------------------------

    public static function documents(string $contractId): array
    {
        return DB::select(
            'SELECT * FROM contract_documents WHERE contract_id = ? ORDER BY doc_type, version DESC',
            [$contractId]
        );
    }

    public static function findDocument(string $id): ?array
    {
        return DB::selectOne('SELECT * FROM contract_documents WHERE id = ?', [$id]);
    }

    public static function addDocument(string $contractId, array $file, string $docType, string $visibility, ?string $signedAt, string $userId): string
    {
        // Le versioni non si sovrascrivono: la v2 affianca la v1.
        $row     = DB::selectOne(
            'SELECT MAX(version) AS v FROM contract_documents WHERE contract_id = ? AND doc_type = ?',
            [$contractId, $docType]
        );
        $version = ((int) ($row['v'] ?? 0)) + 1;

        $id = DB::uuid();
        DB::execute(
            'INSERT INTO contract_documents (id, contract_id, doc_type, version, file_name, storage_key, file_size, file_hash, visibility, uploaded_by, signed_at, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$id, $contractId, $docType, $version, $file['file_name'], $file['key'], $file['size'], $file['hash'],
             $visibility, $userId, $signedAt ?: null, DB::now()]
        );
        return $id;
    }

    /** Il documento e' scaricabile da chi e' parte del contratto, nel rispetto della visibilita'. */
    public static function canAccessDocument(array $doc, array $contract, ?string $orgId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ($orgId === null) {
            return false;
        }
        $isProvider = $contract['provider_org_id'] === $orgId;
        $isClient   = $contract['client_org_id'] === $orgId;

        return match ($doc['visibility']) {
            'CONDIVISO'           => $isProvider || $isClient,
            'PRIVATO_OFFERENTE'   => $isProvider,
            'PRIVATO_RICHIEDENTE' => $isClient,
            default               => false,   // SOLO_ADMIN
        };
    }
}
