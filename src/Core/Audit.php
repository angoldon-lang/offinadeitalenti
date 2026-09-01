<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    /** Log append-only: ogni azione amministrativa e ogni accesso a un documento. */
    public static function log(string $action, string $entityType, ?string $entityId = null, array $diff = []): void
    {
        $user = Auth::user();
        Database::execute(
            'INSERT INTO audit_log (actor_id, actor_email, action, entity_type, entity_id, diff, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $user['id'] ?? null,
                $user['email'] ?? null,
                $action,
                $entityType,
                $entityId,
                $diff === [] ? null : json_encode($diff, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                Database::now(),
            ]
        );
    }
}
