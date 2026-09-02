<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Database, Request, Response, Session, Validator, View};
use App\Repository\UserRepository;

/**
 * Installazione iniziale: crea il primo amministratore dal browser.
 *
 * Accessibile SOLO finche' non esiste alcun amministratore. Appena ne esiste
 * uno la pagina risponde 404 e resta inutilizzabile: non serve ricordarsi di
 * cancellare nulla dal server, e nessuna password finisce mai dentro un file.
 */
final class SetupController
{
    private function isOpen(): bool
    {
        $row = Database::selectOne('SELECT COUNT(*) AS n FROM users WHERE platform_role = ?', ['ADMIN']);
        return ((int) ($row['n'] ?? 0)) === 0;
    }

    private function guard(): void
    {
        if (!$this->isOpen()) {
            Response::abort(404, 'L\'installazione e\' gia\' stata completata.');
        }
    }

    public function show(Request $r): void
    {
        $this->guard();

        // Se lo schema non e' ancora stato applicato, la query sopra sarebbe
        // gia' fallita: arrivare qui significa che il database risponde.
        echo View::page('setup', [
            'title'  => 'Installazione',
            'skills' => (int) (Database::selectOne('SELECT COUNT(*) AS n FROM skills')['n'] ?? 0),
        ], 'layout_public');
    }

    public function create(Request $r): void
    {
        $this->guard();

        $email = mb_strtolower(trim((string) $r->input('email', '')));
        $name  = trim((string) $r->input('full_name', ''));
        $pass  = (string) $r->input('password', '');

        $v = new Validator();
        $v->required($name, 'full_name', 'Nome e cognome')
          ->required($email, 'email', 'Email')
          ->email($email, 'email')
          ->required($pass, 'password', 'Password')
          ->minLength($pass, 12, 'password', 'Password')
          ->rule($pass === (string) $r->input('password_confirm'), 'password_confirm', 'Le due password non coincidono.')
          ->rule(!UserRepository::emailExists($email), 'email', 'Questa email e\' gia\' registrata.');

        if ($v->fails()) {
            Session::flash('error', (string) $v->firstError());
            Response::redirect('/installazione');
        }

        $id = UserRepository::create([
            'organization_id' => null,
            'email'           => $email,
            'password'        => $pass,
            'full_name'       => $name,
            'platform_role'   => 'ADMIN',
            'org_role'        => 'OWNER',
        ]);

        Audit::log('SETUP_ADMIN_CREATED', 'user', $id);
        Auth::attempt($email, $pass);

        Session::flash('success', 'Amministratore creato. Da questo momento la pagina di installazione non e\' piu\' raggiungibile.');
        Response::redirect('/admin');
    }
}
