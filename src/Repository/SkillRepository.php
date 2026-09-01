<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database as DB;

final class SkillRepository
{
    /** @return array{HARD: array, SOFT: array} */
    public static function grouped(): array
    {
        $rows    = DB::select('SELECT * FROM skills WHERE is_active = 1 ORDER BY category, name');
        $grouped = ['HARD' => [], 'SOFT' => []];
        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }
        return $grouped;
    }

    public static function all(): array
    {
        return DB::select('SELECT * FROM skills WHERE is_active = 1 ORDER BY category, name');
    }

    public static function forResource(string $resourceId): array
    {
        return DB::select(
            'SELECT s.*, rs.level, rs.years
               FROM resource_skills rs
               JOIN skills s ON s.id = rs.skill_id
              WHERE rs.resource_id = ?
           ORDER BY s.category, s.name',
            [$resourceId]
        );
    }

    /** Mappa resource_id => elenco skill, per non fare una query per card. */
    public static function forResources(array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }
        $in   = implode(',', array_fill(0, count($resourceIds), '?'));
        $rows = DB::select(
            "SELECT rs.resource_id, s.id AS skill_id, s.name, s.category, rs.level
               FROM resource_skills rs JOIN skills s ON s.id = rs.skill_id
              WHERE rs.resource_id IN ($in)
           ORDER BY s.category, rs.level DESC, s.name",
            $resourceIds
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row['resource_id']][] = $row;
        }
        return $map;
    }

    public static function syncResource(string $resourceId, array $skillIds, array $levels = []): void
    {
        DB::transaction(function () use ($resourceId, $skillIds, $levels) {
            DB::execute('DELETE FROM resource_skills WHERE resource_id = ?', [$resourceId]);
            foreach (array_unique($skillIds) as $skillId) {
                if (!is_string($skillId) || $skillId === '') {
                    continue;
                }
                DB::execute(
                    'INSERT INTO resource_skills (resource_id, skill_id, level) VALUES (?,?,?)',
                    [$resourceId, $skillId, isset($levels[$skillId]) ? (int) $levels[$skillId] : 3]
                );
            }
        });
    }
}
