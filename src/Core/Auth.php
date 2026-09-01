<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): ?array
    {
        $user = Database::selectOne(
            'SELECT * FROM users WHERE email = ? AND is_active = 1',
            [mb_strtolower(trim($email))]
        );

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        Session::regenerate();
        Session::set('user_id', $user['id']);
        Database::execute('UPDATE users SET last_login_at = ? WHERE id = ?', [Database::now(), $user['id']]);

        return $user;
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    /** Utente corrente con l'organizzazione gia' unita (una sola query per richiesta). */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = Session::get('user_id');
        if (!is_string($id)) {
            return null;
        }

        $row = Database::selectOne(
            'SELECT u.*,
                    o.legal_name        AS org_name,
                    o.type              AS org_type,
                    o.status            AS org_status,
                    o.access_expires_at AS org_expires_at
               FROM users u
          LEFT JOIN organizations o ON o.id = u.organization_id
              WHERE u.id = ? AND u.is_active = 1',
            [$id]
        );

        return self::$user = $row ?: null;
    }

    public static function id(): ?string
    {
        return self::user()['id'] ?? null;
    }

    public static function orgId(): ?string
    {
        return self::user()['organization_id'] ?? null;
    }

    public static function role(): ?string
    {
        return self::user()['platform_role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'ADMIN';
    }

    /**
     * L'organizzazione puo' compiere azioni che modificano lo stato?
     * In GRACE/EXPIRED/SUSPENDED si resta in sola lettura, ma contratti,
     * time-sheet e fatture restano SEMPRE consultabili: sono documenti
     * amministrativi della controparte.
     */
    public static function canWrite(): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        return (self::user()['org_status'] ?? null) === 'ACTIVE';
    }

    public static function requireRole(array $roles, ?Request $request = null): void
    {
        $user = self::user();

        if (!$user) {
            if ($request && $request->wantsJson()) {
                Response::json(['error' => 'Sessione scaduta.'], 401);
            }
            Session::set('_intended', $request?->path ?? '/');
            Response::redirect('/login');
        }

        if (!in_array($user['platform_role'], $roles, true)) {
            Response::abort(403, 'Questa area non e\' accessibile con il tuo profilo.');
        }

        if (($user['org_status'] ?? null) === 'PENDING_APPROVAL' && !self::isAdmin()) {
            // L'utente puo' entrare, ma solo sulla schermata di attesa.
            $path = $request?->path ?? '';
            if (!str_starts_with($path, '/in-attivazione') && !str_starts_with($path, '/logout')) {
                Response::redirect('/in-attivazione');
            }
        }
    }

    /** Blocca una scrittura quando l'account non e' attivo. */
    public static function requireWrite(): void
    {
        if (!self::canWrite()) {
            Response::abort(403, 'Il tuo account non e\' attivo: puoi consultare i dati ma non modificarli. Contatta l\'amministratore per il rinnovo.');
        }
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** Home di atterraggio per ruolo: ogni area e' un'applicazione separata. */
    public static function homeFor(string $role): string
    {
        return match ($role) {
            'ADMIN'         => '/admin',
            'OFFERENTE'     => '/offerente',
            'RICHIEDENTE'   => '/richiedente',
            'RESOURCE_USER' => '/risorsa',
            default         => '/',
        };
    }
}
