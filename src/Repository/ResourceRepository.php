<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database as DB;

final class ResourceRepository
{
    public static function find(string $id): ?array
    {
        return DB::selectOne('SELECT * FROM resources WHERE id = ?', [$id]);
    }

    /** Recupera una risorsa verificando che appartenga all'organizzazione. */
    public static function findOwned(string $id, string $orgId): ?array
    {
        return DB::selectOne('SELECT * FROM resources WHERE id = ? AND organization_id = ?', [$id, $orgId]);
    }

    public static function forOrganization(string $orgId): array
    {
        return DB::select(
            'SELECT * FROM resources WHERE organization_id = ? ORDER BY updated_at DESC',
            [$orgId]
        );
    }

    public static function save(?string $id, string $orgId, array $d): string
    {
        $now = DB::now();
        // Normalizzazione oraria -> giornaliera: senza questa lo slider budget
        // confronterebbe 50 €/h con 400 €/gg.
        $factor = $d['rate_unit'] === 'HOURLY' ? 8 : 1;

        $fields = [
            'title'              => $d['title'],
            'description'        => $d['description'] ?: null,
            'seniority'          => $d['seniority'],
            'availability'       => $d['availability'],
            'engagement'         => $d['engagement'],
            'available_from'     => $d['available_from'] ?: null,
            'rate_min'           => $d['rate_min'],
            'rate_max'           => $d['rate_max'],
            'rate_unit'          => $d['rate_unit'],
            'rate_negotiable'    => !empty($d['rate_negotiable']) ? 1 : 0,
            'daily_rate_min'     => $d['rate_min'] * $factor,
            'daily_rate_max'     => $d['rate_max'] * $factor,
            'work_mode'          => $d['work_mode'],
            'city'               => $d['city'] ?: null,
            'province'           => $d['province'] ?: null,
            'languages'          => $d['languages'] ?: null,
            'operational_status' => $d['operational_status'],
            'updated_at'         => $now,
        ];

        if ($id === null) {
            $id                       = DB::uuid();
            $fields['id']             = $id;
            $fields['organization_id'] = $orgId;
            $fields['publication_status'] = 'DRAFT';
            $fields['created_at']     = $now;

            $cols = implode(',', array_keys($fields));
            $ph   = implode(',', array_fill(0, count($fields), '?'));
            DB::execute("INSERT INTO resources ($cols) VALUES ($ph)", array_values($fields));
            return $id;
        }

        $set = implode(', ', array_map(static fn ($c) => "$c = ?", array_keys($fields)));
        DB::execute(
            "UPDATE resources SET $set WHERE id = ? AND organization_id = ?",
            array_merge(array_values($fields), [$id, $orgId])
        );
        return $id;
    }

    public static function setPublicationStatus(string $id, string $status, ?string $reason = null, ?string $reviewer = null): void
    {
        DB::execute(
            'UPDATE resources
                SET publication_status = ?, rejection_reason = ?, reviewed_by = ?,
                    published_at = CASE WHEN ? = \'PUBLISHED\' THEN ? ELSE published_at END,
                    updated_at = ?
              WHERE id = ?',
            [$status, $reason, $reviewer, $status, DB::now(), DB::now(), $id]
        );
    }

    public static function setOperationalStatus(string $id, string $orgId, string $status): void
    {
        DB::execute(
            'UPDATE resources SET operational_status = ?, updated_at = ? WHERE id = ? AND organization_id = ?',
            [$status, DB::now(), $id, $orgId]
        );
    }

    public static function pendingReview(): array
    {
        return DB::select(
            'SELECT r.*, o.legal_name AS org_name
               FROM resources r JOIN organizations o ON o.id = r.organization_id
              WHERE r.publication_status = ?
           ORDER BY r.updated_at',
            ['IN_REVIEW']
        );
    }

    /**
     * Ricerca faccettata del Richiedente.
     * Le skill in AND si risolvono con HAVING COUNT(DISTINCT ...): a questi
     * volumi e' piu' che sufficiente e non richiede denormalizzazione.
     */
    public static function search(array $f): array
    {
        $params = [];
        $sql = 'SELECT r.id, r.title, r.description, r.seniority, r.availability, r.engagement,
                       r.rate_min, r.rate_max, r.rate_unit, r.rate_negotiable,
                       r.daily_rate_min, r.daily_rate_max, r.work_mode, r.city, r.province,
                       r.operational_status, r.updated_at
                  FROM resources r
                  JOIN organizations o ON o.id = r.organization_id';

        // Solo risorse pubblicate di organizzazioni non scadute: un account
        // scaduto viene de-indicizzato, non cancellato.
        $where = ["r.publication_status = 'PUBLISHED'", "o.status IN ('ACTIVE','GRACE')"];

        if (!empty($f['q'])) {
            $where[]  = '(r.title LIKE ? OR r.description LIKE ?)';
            $params[] = '%' . $f['q'] . '%';
            $params[] = '%' . $f['q'] . '%';
        }
        if (!empty($f['seniority'])) {
            $in       = implode(',', array_fill(0, count($f['seniority']), '?'));
            $where[]  = "r.seniority IN ($in)";
            $params   = array_merge($params, $f['seniority']);
        }
        if (!empty($f['work_mode'])) {
            $in      = implode(',', array_fill(0, count($f['work_mode']), '?'));
            $where[] = "r.work_mode IN ($in)";
            $params  = array_merge($params, $f['work_mode']);
        }
        if (!empty($f['availability'])) {
            $in      = implode(',', array_fill(0, count($f['availability']), '?'));
            $where[] = "r.availability IN ($in)";
            $params  = array_merge($params, $f['availability']);
        }
        if (!empty($f['engagement'])) {
            $where[]  = 'r.engagement = ?';
            $params[] = $f['engagement'];
        }
        if (!empty($f['city'])) {
            $where[]  = 'r.city LIKE ?';
            $params[] = '%' . $f['city'] . '%';
        }
        // Il budget si confronta sempre sulla tariffa normalizzata a giornata.
        if (!empty($f['budget_max'])) {
            $where[]  = 'r.daily_rate_min <= ?';
            $params[] = (float) $f['budget_max'];
        }
        if (!empty($f['budget_min'])) {
            $where[]  = 'r.daily_rate_max >= ?';
            $params[] = (float) $f['budget_min'];
        }
        if (empty($f['include_busy'])) {
            $where[] = "r.operational_status = 'ATTIVA'";
        }

        $skills = array_values(array_filter((array) ($f['skills'] ?? [])));
        if ($skills !== []) {
            $in   = implode(',', array_fill(0, count($skills), '?'));
            $mode = ($f['skill_mode'] ?? 'AND') === 'OR' ? 'OR' : 'AND';

            $sql .= " JOIN resource_skills rs ON rs.resource_id = r.id AND rs.skill_id IN ($in)";
            $params = array_merge($skills, $params);
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);

        if ($skills !== []) {
            $sql .= ' GROUP BY r.id';
            if (($f['skill_mode'] ?? 'AND') !== 'OR') {
                // Deve possederle TUTTE. Il conteggio e' un intero derivato da
                // count(), non un input: va scritto nella query e non legato come
                // parametro, altrimenti viene confrontato come stringa.
                $sql .= ' HAVING COUNT(DISTINCT rs.skill_id) = ' . count($skills);
            }
        }

        $sql .= match ($f['sort'] ?? 'recent') {
            'rate_asc'  => ' ORDER BY r.daily_rate_min ASC',
            'rate_desc' => ' ORDER BY r.daily_rate_max DESC',
            'avail'     => " ORDER BY CASE r.availability WHEN 'IMMEDIATA' THEN 0 WHEN 'ENTRO_1_MESE' THEN 1 ELSE 2 END",
            default     => ' ORDER BY r.updated_at DESC',
        };

        $sql .= ' LIMIT 100';

        return DB::select($sql, $params);
    }

    /**
     * Percentuale di skill richieste effettivamente coperte dalla risorsa.
     * E' il "match score" mostrato in card: banale da calcolare, molto utile
     * a chi sfoglia 40 profili.
     *
     * @param array<int, array{skill_id:string}> $resourceSkills
     * @param array<int, string>                 $wantedSkillIds
     */
    public static function matchScore(array $resourceSkills, array $wantedSkillIds): ?int
    {
        $wanted = array_values(array_filter($wantedSkillIds));
        if ($wanted === []) {
            return null;
        }
        $owned   = array_column($resourceSkills, 'skill_id');
        $covered = count(array_intersect($wanted, $owned));

        return (int) round($covered / count($wanted) * 100);
    }
}
