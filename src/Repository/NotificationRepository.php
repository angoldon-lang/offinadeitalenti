<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database as DB;

final class NotificationRepository
{
    /** Notifica tutti gli utenti di un'organizzazione. */
    public static function notifyOrg(string $orgId, string $type, string $title, ?string $body, ?string $link): void
    {
        $users = DB::select('SELECT id FROM users WHERE organization_id = ? AND is_active = 1', [$orgId]);
        foreach ($users as $user) {
            self::create($user['id'], $type, $title, $body, $link);
        }
    }

    public static function create(string $userId, string $type, string $title, ?string $body, ?string $link): void
    {
        DB::execute(
            'INSERT INTO notifications (id, user_id, type, title, body, link, created_at) VALUES (?,?,?,?,?,?,?)',
            [DB::uuid(), $userId, $type, $title, $body, $link, DB::now()]
        );
    }

    public static function forUser(string $userId, int $limit = 30): array
    {
        return DB::select(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}",
            [$userId]
        );
    }

    public static function unreadCount(string $userId): int
    {
        $row = DB::selectOne('SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
        return (int) ($row['n'] ?? 0);
    }

    public static function markAllRead(string $userId): void
    {
        DB::execute('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [DB::now(), $userId]);
    }
}
