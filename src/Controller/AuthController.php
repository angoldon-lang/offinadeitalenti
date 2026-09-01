<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Database, Request, Response, Session, Validator, View};
use App\Repository\{OrganizationRepository, UserRepository};

final class AuthController
{
    public function showLogin(Request $r): void
    {
        if (Auth::user()) {
            Response::redirect(Auth::homeFor((string) Auth::role()));
        }
        echo View::page('auth/login', ['title' => 'Accedi']);
    }

    public function login(Request $r): void
    {
        $email    = (string) $r->input('email', '');
        $password = (string) $r->input('password', '');

        $user = Auth::attempt($email, $password);
        if (!$user) {
            Session::flash('error', 'Email o password non corretti.');
            Response::redirect('/login');
        }

        Audit::log('LOGIN', 'user', $user['id']);

        $intended = Session::get('_intended');
        Session::forget('_intended');

        Response::redirect(is_string($intended) && $intended !== '/login' ? $intended : Auth::homeFor($user['platform_role']));
    }

    public function logout(Request $r): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    public function showRegister(Request $r): void
    {
        $type = $r->query('tipo') === 'richiedente' ? 'RICHIEDENTE' : 'OFFERENTE';
        echo View::page('auth/register', [
            'title' => 'Registrati',
            'type'  => $type,
            'old'   => Session::get('_old', []),
        ]);
        Session::forget('_old');
    }

    /**
     * Registrazione: crea organizzazione + utente OWNER in PENDING_APPROVAL.
     * L'attivazione e la durata dell'account restano una decisione manuale
     * dell'amministratore: non esiste alcun pagamento automatico.
     */
    public function register(Request $r): void
    {
        $type = $r->input('type') === 'RICHIEDENTE' ? 'RICHIEDENTE' : 'OFFERENTE';

        $data = [
            'type'       => $type,
            'legal_name' => (string) $r->input('legal_name', ''),
            'vat_number' => (string) $r->input('vat_number', ''),
            'sector'     => (string) $r->input('sector', ''),
            'size_range' => (string) $r->input('size_range', ''),
            'phone'      => (string) $r->input('phone', ''),
            'full_name'  => (string) $r->input('full_name', ''),
            'email'      => (string) $r->input('email', ''),
        ];

        $password = (string) $r->input('password', '');

        $v = new Validator();
        $v->required($data['legal_name'], 'legal_name', 'Ragione sociale')
          ->required($data['full_name'], 'full_name', 'Nome referente')
          ->required($data['email'], 'email', 'Email')
          ->email($data['email'], 'email')
          ->required($password, 'password', 'Password')
          ->minLength($password, 10, 'password', 'Password')
          ->rule($password === (string) $r->input('password_confirm'), 'password_confirm', 'Le due password non coincidono.')
          ->rule(!UserRepository::emailExists($data['email']), 'email', 'Questa email e\' gia\' registrata.');

        if ($v->fails()) {
            Session::set('_old', $data);
            Session::flash('error', (string) $v->firstError());
            Response::redirect('/registrati?tipo=' . strtolower($type));
        }

        $userId = Database::transaction(function () use ($data, $password) {
            $orgId = OrganizationRepository::create($data);
            return UserRepository::create([
                'organization_id' => $orgId,
                'email'           => $data['email'],
                'password'        => $password,
                'full_name'       => $data['full_name'],
                'phone'           => $data['phone'],
                'platform_role'   => $data['type'],
                'org_role'        => 'OWNER',
            ]);
        });

        Audit::log('REGISTER', 'user', $userId, ['type' => $type]);

        Auth::attempt($data['email'], $password);
        Response::redirect('/in-attivazione');
    }

    /**
     * Schermata di attesa. L'utente puo' comunque preparare le risorse in
     * bozza: quando l'admin attiva, il catalogo e' gia' pronto.
     */
    public function pending(Request $r): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::redirect('/login');
        }
        if (($user['org_status'] ?? '') !== 'PENDING_APPROVAL') {
            Response::redirect(Auth::homeFor((string) $user['platform_role']));
        }
        echo View::page('auth/pending', ['title' => 'Profilo in attivazione', 'user' => $user]);
    }
}
