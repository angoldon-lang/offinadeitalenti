<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Database, Request, Response, Session, Validator, View};

final class ProfileController
{
    public function show(Request $r): void
    {
        echo View::page('profile', ['title' => 'Il mio profilo', 'user' => Auth::user()]);
    }

    public function updateProfile(Request $r): void
    {
        $user = Auth::user();

        $name  = trim((string) $r->input('full_name', ''));
        $phone = trim((string) $r->input('phone', ''));

        $v = new Validator();
        $v->required($name, 'full_name', 'Nome e cognome');

        if ($v->fails()) {
            Session::flash('error', (string) $v->firstError());
            Response::redirect('/profilo');
        }

        Database::execute(
            'UPDATE users SET full_name = ?, phone = ?, updated_at = ? WHERE id = ?',
            [$name, $phone ?: null, Database::now(), $user['id']]
        );

        Session::flash('success', 'Profilo aggiornato.');
        Response::redirect('/profilo');
    }

    public function updatePassword(Request $r): void
    {
        $user = Auth::user();

        $current = (string) $r->input('current_password', '');
        $new     = (string) $r->input('new_password', '');

        $v = new Validator();
        // La password attuale si verifica sempre: una sessione rubata non deve
        // poter cambiare le credenziali.
        $v->rule(password_verify($current, (string) $user['password_hash']), 'current_password', 'La password attuale non e\' corretta.')
          ->minLength($new, 10, 'new_password', 'Nuova password')
          ->required($new, 'new_password', 'Nuova password')
          ->rule($new === (string) $r->input('new_password_confirm'), 'new_password_confirm', 'Le due password non coincidono.')
          ->rule($new !== $current, 'new_password', 'La nuova password deve essere diversa da quella attuale.');

        if ($v->fails()) {
            Session::flash('error', (string) $v->firstError());
            Response::redirect('/profilo');
        }

        Database::execute(
            'UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
            [Auth::hash($new), Database::now(), $user['id']]
        );

        // Cambio di credenziali: si rigenera l'identificativo di sessione.
        Session::regenerate();
        Audit::log('PASSWORD_CHANGED', 'user', $user['id']);

        Session::flash('success', 'Password aggiornata.');
        Response::redirect('/profilo');
    }
}
