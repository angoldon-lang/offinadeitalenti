<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Request, Response, Session, Validator, View};
use App\Domain\Enums;
use App\Repository\{ContractRepository, RequestRepository, ResourceRepository, SkillRepository, TimesheetRepository};

final class OffererController
{
    public function dashboard(Request $r): void
    {
        $orgId = (string) Auth::orgId();

        // La home apre su "cosa devo fare oggi", non sui grafici.
        $contracts = ContractRepository::activeForProvider($orgId);
        $toFill    = [];
        foreach ($contracts as $contract) {
            $index = TimesheetRepository::indexForContract($contract['id']);
            foreach (\App\Support\Week::forContract($contract['start_date'], $contract['end_date']) as $week) {
                $key = $week['iso_year'] . '-' . $week['iso_week'];
                $ts  = $index[$key] ?? null;
                if ($ts === null || in_array($ts['status'], ['DRAFT', 'REJECTED'], true)) {
                    $toFill[] = ['contract' => $contract, 'week' => $week, 'timesheet' => $ts];
                }
            }
        }
        // dalla settimana piu' recente
        usort($toFill, static fn ($a, $b) => [$b['week']['iso_year'], $b['week']['iso_week']] <=> [$a['week']['iso_year'], $a['week']['iso_week']]);

        echo View::page('offerente/dashboard', [
            'title'          => 'Dashboard',
            'resources'      => ResourceRepository::forOrganization($orgId),
            'pendingRequests'=> RequestRepository::countPendingForProvider($orgId),
            'toFill'         => array_slice($toFill, 0, 6),
            'toFillCount'    => count($toFill),
            'recent'         => TimesheetRepository::recentForOrg($orgId, 'provider', 5),
        ]);
    }

    public function resources(Request $r): void
    {
        echo View::page('offerente/resources', [
            'title'     => 'Le mie risorse',
            'resources' => ResourceRepository::forOrganization((string) Auth::orgId()),
        ]);
    }

    public function resourceForm(Request $r): void
    {
        $id       = $r->param('id');
        $resource = null;

        if ($id && $id !== 'nuova') {
            $resource = ResourceRepository::findOwned($id, (string) Auth::orgId());
            if (!$resource) {
                Response::abort(404, 'Risorsa non trovata.');
            }
        }

        echo View::page('offerente/resource_form', [
            'title'    => $resource ? 'Modifica risorsa' : 'Nuova risorsa',
            'resource' => $resource,
            'skills'   => SkillRepository::grouped(),
            'selected' => $resource ? array_column(SkillRepository::forResource($resource['id']), 'id') : [],
        ]);
    }

    public function saveResource(Request $r): void
    {
        Auth::requireWrite();
        $orgId = (string) Auth::orgId();
        $id    = $r->param('id');
        $id    = ($id && $id !== 'nuova') ? $id : null;

        if ($id !== null && !ResourceRepository::findOwned($id, $orgId)) {
            Response::abort(404, 'Risorsa non trovata.');
        }

        $data = [
            'title'              => (string) $r->input('title', ''),
            'description'        => (string) $r->input('description', ''),
            'seniority'          => (string) $r->input('seniority', ''),
            'availability'       => (string) $r->input('availability', ''),
            'engagement'         => (string) $r->input('engagement', ''),
            'available_from'     => (string) $r->input('available_from', ''),
            'rate_min'           => $r->float('rate_min', 0.0),
            'rate_max'           => $r->float('rate_max', 0.0),
            'rate_unit'          => (string) $r->input('rate_unit', 'DAILY'),
            'rate_negotiable'    => $r->bool('rate_negotiable'),
            'work_mode'          => (string) $r->input('work_mode', ''),
            'city'               => (string) $r->input('city', ''),
            'province'           => (string) $r->input('province', ''),
            'languages'          => (string) $r->input('languages', ''),
            'operational_status' => $r->input('operational_status') === 'OCCUPATA' ? 'OCCUPATA' : 'ATTIVA',
        ];

        $skills = array_values(array_filter((array) $r->array('skills'), 'is_string'));

        $v = new Validator();
        $v->required($data['title'], 'title', 'Nome/Ruolo')
          ->in($data['seniority'], Enums::keys(Enums::SENIORITY), 'seniority', 'Livello di esperienza')
          ->in($data['availability'], Enums::keys(Enums::AVAILABILITY), 'availability', 'Disponibilita\'')
          ->in($data['engagement'], Enums::keys(Enums::ENGAGEMENT), 'engagement', 'Impegno')
          ->in($data['rate_unit'], ['DAILY', 'HOURLY'], 'rate_unit', 'Unita\' tariffa')
          ->in($data['work_mode'], Enums::keys(Enums::WORK_MODE), 'work_mode', 'Modalita\' di lavoro')
          ->numericRange($data['rate_min'], 1, 100000, 'rate_min', 'Tariffa minima')
          ->numericRange($data['rate_max'], 1, 100000, 'rate_max', 'Tariffa massima')
          ->rule($data['rate_max'] >= $data['rate_min'], 'rate_max', 'La tariffa massima deve essere maggiore o uguale alla minima.')
          ->rule($skills !== [], 'skills', 'Seleziona almeno una competenza.')
          // La localita' e' obbligatoria se il lavoro non e' interamente da remoto.
          ->rule($data['work_mode'] === 'REMOTO' || $data['city'] !== '', 'city', 'La localita\' e\' obbligatoria per le modalita\' Onsite e Ibrido.');

        if ($v->fails()) {
            Session::flash('error', (string) $v->firstError());
            Response::redirect('/offerente/risorse/' . ($id ?? 'nuova'));
        }

        $resourceId = ResourceRepository::save($id, $orgId, $data);
        SkillRepository::syncResource($resourceId, $skills);

        Audit::log($id ? 'RESOURCE_UPDATED' : 'RESOURCE_CREATED', 'resource', $resourceId);
        Session::flash('success', $id ? 'Risorsa aggiornata.' : 'Risorsa creata. Inviala in approvazione quando e\' pronta.');
        Response::redirect('/offerente/risorse/' . $resourceId);
    }

    /** Invio in moderazione: da qui la scheda e' in sola lettura. */
    public function submitResource(Request $r): void
    {
        Auth::requireWrite();
        $resource = ResourceRepository::findOwned((string) $r->param('id'), (string) Auth::orgId());
        if (!$resource) {
            Response::abort(404, 'Risorsa non trovata.');
        }
        if (!in_array($resource['publication_status'], ['DRAFT', 'REJECTED'], true)) {
            Session::flash('error', 'Questa risorsa non e\' in bozza.');
            Response::redirect('/offerente/risorse');
        }

        ResourceRepository::setPublicationStatus($resource['id'], 'IN_REVIEW');
        Audit::log('RESOURCE_SUBMITTED', 'resource', $resource['id']);
        Session::flash('success', 'Risorsa inviata in approvazione: la esaminiamo entro 24 ore lavorative.');
        Response::redirect('/offerente/risorse');
    }

    /** Attiva/Occupata: asse indipendente dalla pubblicazione. */
    public function toggleStatus(Request $r): void
    {
        Auth::requireWrite();
        $orgId    = (string) Auth::orgId();
        $resource = ResourceRepository::findOwned((string) $r->param('id'), $orgId);
        if (!$resource) {
            Response::abort(404, 'Risorsa non trovata.');
        }

        $new = $resource['operational_status'] === 'ATTIVA' ? 'OCCUPATA' : 'ATTIVA';
        ResourceRepository::setOperationalStatus($resource['id'], $orgId, $new);
        Session::flash('success', 'Risorsa segnata come ' . Enums::label(Enums::RESOURCE_OP_STATUS, $new) . '.');
        Response::redirect('/offerente/risorse');
    }

    public function requests(Request $r): void
    {
        echo View::page('offerente/requests', [
            'title'    => 'Richieste ricevute',
            'requests' => RequestRepository::forProvider((string) Auth::orgId()),
        ]);
    }

    public function respondRequest(Request $r): void
    {
        Auth::requireWrite();
        $request = RequestRepository::find((string) $r->param('id'));
        if (!$request || $request['provider_org_id'] !== Auth::orgId()) {
            Response::abort(404, 'Richiesta non trovata.');
        }

        $accept = $r->input('action') === 'accept';
        if ($accept) {
            RequestRepository::respond($request['id'], 'ACCEPTED');
            \App\Repository\NotificationRepository::notifyOrg(
                $request['client_org_id'],
                'REQUEST_ACCEPTED',
                'Richiesta accettata',
                $request['resource_title'] . ' e\' disponibile: ora vedi i contatti del fornitore.',
                '/richiedente/richieste'
            );
            Session::flash('success', 'Richiesta accettata. Le identita\' sono ora visibili a entrambe le parti.');
        } else {
            RequestRepository::respond($request['id'], 'DECLINED', (string) $r->input('reason', ''));
            Session::flash('success', 'Richiesta rifiutata.');
        }

        Audit::log($accept ? 'REQUEST_ACCEPTED' : 'REQUEST_DECLINED', 'resource_request', $request['id']);
        Response::redirect('/offerente/richieste');
    }
}
