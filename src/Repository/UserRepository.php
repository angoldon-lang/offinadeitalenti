<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Auth;
use App\Core\Database as DB;

final class UserRepository
{
    public static function emailExists(string $email): bool
    {
        return DB::selectOne('SELECT id FROM users WHERE email = ?', [mb_strtolower($email)]) !== null;
    }

    public static function create(array $data): string
    {
        $id  = DB::uuid();
        $now = DB::now();
        DB::execute(
            'INSERT INTO users (id, organization_id, email, password_hash, full_name, phone, platform_role, org_role, resource_id, is_active, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $id,
                $data['organization_id'] ?? null,
                mb_strtolower($data['email']),
                Auth::hash($data['password']),
                $data['full_name'],
                $data['phone'] ?? null,
                $data['platform_role'],
                $data['org_role'] ?? 'MEMBER',
                $data['resource_id'] ?? null,
                1, $now, $now,
            ]
        );
        return $id;
    }

    public static function forOrganization(string $orgId): array
    {
        return DB::select('SELECT * FROM users WHERE organization_id = ? ORDER BY full_name', [$orgId]);
    }
}
