<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Database, Request, Response, Session, Validator, View};
use App\Domain\Enums;
use App\Repository\{ContractRepository, InvoiceRepository, NotificationRepository, OrganizationRepository,
                    ResourceRepository, SkillRepository, TimesheetRepository};

final class AdminController
{
    public function dashboard(Request $r): void
    {
        $counts = [
            'pending_orgs'      => count(OrganizationRepository::all('PENDING_APPROVAL')),
            'pending_resources' => count(ResourceRepository::pendingReview()),
            'expiring'          => count(OrganizationRepository::expiringWithin(30)),
            'to_approve'        => (int) (Database::selectOne('SELECT COUNT(*) AS n FROM timesheets WHERE status = ?', ['SUBMITTED'])['n'] ?? 0),
            'overdue_invoices'  => (int) (Database::selectOne('SELECT COUNT(*) AS n FROM invoices WHERE payment_status = ?', ['SCADUTA'])['n'] ?? 0),
        ];

        echo View::page('admin/dashboard', [
            'title'    => 'Back-office',
            'counts'   => $counts,
            'expiring' => OrganizationRepository::expiringWithin(30),
            'stale'    => $this->staleWeeks(),
            'totals'   => InvoiceRepository::totals(),
        ]);
    }

    /** Settimane ferme da troppo tempo: la lista dei solleciti da fare. */
    private function staleWeeks(): array
    {
        $limit = (new \DateTimeImmutable('today'))->modify('-5 days')->format('Y-m-d');
        return Database::select(
            'SELECT t.*, c.code AS contract_code, po.legal_name AS provider_name, co.legal_name AS client_name
               FROM timesheets t
               JOIN contracts c      ON c.id  = t.contract_id
               JOIN organizations po ON po.id = c.provider_org_id
               JOIN organizations co ON co.id = c.client_org_id
              WHERE t.status IN (?, ?) AND t.week_end <= ?
           ORDER BY t.week_start
              LIMIT 20',
            ['DRAFT', 'SUBMITTED', $limit]
        );
    }

    // ---- organizzazioni e durata account -----------------------------------

    public function organizations(Request $r): void
    {
        echo View::page('admin/organizations', [
            'title' => 'Organizzazioni',
            'orgs'  => OrganizationRepository::all((string) $r->query('stato') ?: null, (string) $r->query('tipo') ?: null),
            'filterStatus' => (string) $r->query('stato', ''),
            'filterType'   => (string) $r->query('tipo', ''),
        ]);
    }

    public function organization(Request $r): void
    {
        $org = OrganizationRepository::find((string) $r->param('id'));
        if (!$org) {
            Response::abort(404, 'Organizzazione non trovata.');
        }

        echo View::page('admin/organization', [
            'title'      => $org['legal_name'],
            'org'        => $org,
            'users'      => \App\Repository\UserRepository::forOrganization($org['id']),
            'extensions' => Database::select('SELECT * FROM account_extensions WHERE organization_id = ? ORDER BY created_at DESC', [$org['id']]),
            'contracts'  => ContractRepository::forOrganization($org['id']),
            'daysLeft'   => OrganizationRepository::daysLeft($org['access_expires_at']),
        ]);
    }

    /**
     * Attivazione o proroga: qui l'admin imposta a mano la durata del profilo,
     * legata al contratto cartaceo. Ogni modifica finisce nello storico.
     */
    public function setExpiry(Request $r): void
    {
        $org = OrganizationRepository::find((string) $r->param('id'));
        if (!$org) {
            Response::abort(404, 'Organizzazione non trovata.');
        }

        $expiry = (string) $r->input('access_expires_at', '');
        $v      = new Validator();
        $v->required($expiry, 'access_expires_at', 'Data di scadenza')
          ->date($expiry, 'access_expires_at', 'Data di scadenza');

        if ($v->fails()) {
            Session::flash('error', (string) $v->firstError());
            Response::redirect('/admin/organizzazioni/' . $org['id']);
        }

        OrganizationRepository::activate(
            $org['id'],
            $expiry,
            (string) $r->input('external_contract_ref', ''),
            (string) $r->input('admin_notes', ''),
            (string) Auth::id()
        );

        NotificationRepository::notifyOrg(
            $org['id'],
            'ACCOUNT_ACTIVATED',
            'Profilo attivo',
            'Il tuo profilo e\' attivo fino al ' . View::date($expiry) . '.',
            '/'
        );

        Audit::log('ORG_EXPIRY_SET', 'organization', $org['id'], [
            'from' => $org['access_expires_at'],
            'to'   => $expiry,
        ]);
        Session::flash('success', 'Account attivo fino al ' . View::date($expiry) . '.');
        Response::redirect('/admin/organizzazioni/' . $org['id']);
    }

    public function setOrgStatus(Request $r): void
    {
        $org = OrganizationRepository::find((string) $r->param('id'));
        if (!$org) {
            Response::abort(404, 'Organizzazione non trovata.');
        }

        $status = (string) $r->input('status', '');
        if (!array_key_exists($status, Enums::ORG_STATUS)) {
            Response::abort(422, 'Stato non valido.');
        }

        OrganizationRepository::setStatus($org['id'], $status);
        Audit::log('ORG_STATUS_CHANGED', 'organization', $org['id'], ['from' => $org['status'], 'to' => $status]);
        Session::flash('success', 'Stato aggiornato a "' . Enums::label(Enums::ORG_STATUS, $status) . '".');
        Response::redirect('/admin/organizzazioni/' . $org['id']);
    }

    // ---- moderazione dei profili -------------------------------------------

    public function moderation(Request $r): void
    {
        echo View::page('admin/moderation', [
            'title'     => 'Moderazione',
            'resources' => ResourceRepository::pendingReview(),
        ]);
    }

    public function reviewResource(Request $r): void
    {
        $resource = ResourceRepository::find((string) $r->param('id'));
        if (!$resource) {
            Response::abort(404, 'Risorsa non trovata.');
        }

        echo View::page('admin/resource_review', [
            'title'    => $resource['title'],
            'resource' => $resource,
            'skills'   => SkillRepository::forResource($resource['id']),
            'org'      => OrganizationRepository::find($resource['organization_id']),
        ]);
    }

    public function moderate(Request $r): void
    {
        $resource = ResourceRepository::find((string) $r->param('id'));
        if (!$resource) {
            Response::abort(404, 'Risorsa non trovata.');
        }

        $approve = $r->input('action') === 'approve';
        $reason  = trim((string) $r->input('reason', ''));

        if (!$approve && mb_strlen($reason) < 5) {
            Session::flash('error', 'Indica il motivo del rifiuto: serve all\'offerente per correggere.');
            Response::redirect('/admin/moderazione/' . $resource['id']);
        }

        ResourceRepository::setPublicationStatus(
            $resource['id'],
            $approve ? 'PUBLISHED' : 'REJECTED',
            $approve ? null : $reason,
            (string) Auth::id()
        );

        NotificationRepository::notifyOrg(
            $resource['organization_id'],
            $approve ? 'RESOURCE_PUBLISHED' : 'RESOURCE_REJECTED',
            $approve ? 'Risorsa pubblicata' : 'Risorsa da correggere',
            $approve
                ? $resource['title'] . ' e\' ora visibile ai richiedenti.'
                : $resource['title'] . ': ' . mb_substr($reason, 0, 140),
            '/offerente/risorse'
        );

        Audit::log($approve ? 'RESOURCE_APPROVED' : 'RESOURCE_REJECTED', 'resource', $resource['id'], ['reason' => $reason ?: null]);
        Session::flash('success', $approve ? 'Risorsa pubblicata.' : 'Risorsa rifiutata con motivazione.');
        Response::redirect('/admin/moderazione');
    }

    // ---- monitor rendicontazione e pagamenti -------------------------------

    public function monitor(Request $r): void
    {
        $status = (string) $r->query('stato', '');
        echo View::page('admin/monitor', [
            'title'      => 'Monitor rendicontazione',
            'timesheets' => TimesheetRepository::monitor(
                array_key_exists($status, Enums::TIMESHEET_STATUS) ? $status : null,
                (string) $r->query('da') ?: null
            ),
            'filter'     => $status,
        ]);
    }

    public function payments(Request $r): void
    {
        echo View::page('admin/payments', [
            'title'    => 'Stato pagamenti',
            'invoices' => InvoiceRepository::all((string) $r->query('stato') ?: null),
            'totals'   => InvoiceRepository::totals(),
            'filter'   => (string) $r->query('stato', ''),
        ]);
    }

    /**
     * Correzione amministrativa di una settimana gia' approvata.
     * E' l'unico varco all'immutabilita': richiede una motivazione, passa
     * dall'override esplicito e finisce sia in audit_log sia nello storico
     * della settimana.
     */
    public function overrideTimesheet(Request $r): void
    {
        $timesheet = TimesheetRepository::find((string) $r->param('id'));
        if (!$timesheet) {
            Response::abort(404, 'Settimana non trovata.');
        }

        $reason = trim((string) $r->input('reason', ''));
        $status = (string) $r->input('status', '');

        if (mb_strlen($reason) < 10) {
            Session::flash('error', 'Serve una motivazione esplicita (almeno 10 caratteri) per riaprire una settimana approvata.');
            Response::redirect('/rendicontazione/' . $timesheet['id']);
        }
        if (!array_key_exists($status, Enums::TIMESHEET_STATUS)) {
            Response::abort(422, 'Stato non valido.');
        }

        $user = Auth::user();
        Database::withAdminOverride(function () use ($timesheet, $status) {
            Database::execute(
                'UPDATE timesheets SET status = ?, updated_at = ? WHERE id = ?',
                [$status, Database::now(), $timesheet['id']]
            );
        });
        TimesheetRepository::event($timesheet['id'], $timesheet['status'], $status, (string) $user['id'], (string) $user['full_name'], 'OVERRIDE ADMIN: ' . $reason);

        Audit::log('TIMESHEET_ADMIN_OVERRIDE', 'timesheet', $timesheet['id'], [
            'from'   => $timesheet['status'],
            'to'     => $status,
            'reason' => $reason,
        ]);
        Session::flash('success', 'Settimana riportata a "' . Enums::label(Enums::TIMESHEET_STATUS, $status) . '". L\'operazione e\' tracciata.');
        Response::redirect('/rendicontazione/' . $timesheet['id']);
    }

    public function auditLog(Request $r): void
    {
        echo View::page('admin/audit', [
            'title'   => 'Audit log',
            'entries' => Database::select('SELECT * FROM audit_log ORDER BY id DESC LIMIT 200'),
        ]);
    }
}
