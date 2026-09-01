<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Request, Response, Session, Validator, View};
use App\Domain\Enums;
use App\Repository\{NotificationRepository, RequestRepository, ResourceRepository, SkillRepository, TimesheetRepository};

final class ClientController
{
    /**
     * Il Richiedente atterra direttamente sulla ricerca, non su una home.
     * I filtri vivono nella query string: la ricerca e' condivisibile.
     */
    public function search(Request $r): void
    {
        $filters = [
            'q'            => (string) $r->query('q', ''),
            'skills'       => array_values(array_filter((array) $r->array('skills'), 'is_string')),
            'skill_mode'   => $r->query('skill_mode') === 'OR' ? 'OR' : 'AND',
            'seniority'    => array_values(array_intersect((array) $r->array('seniority'), Enums::keys(Enums::SENIORITY))),
            'work_mode'    => array_values(array_intersect((array) $r->array('work_mode'), Enums::keys(Enums::WORK_MODE))),
            'availability' => array_values(array_intersect((array) $r->array('availability'), Enums::keys(Enums::AVAILABILITY))),
            'engagement'   => in_array($r->query('engagement'), Enums::keys(Enums::ENGAGEMENT), true) ? $r->query('engagement') : null,
            'city'         => (string) $r->query('city', ''),
            'budget_min'   => $r->query('budget_min') !== null && $r->query('budget_min') !== '' ? (float) $r->query('budget_min') : null,
            'budget_max'   => $r->query('budget_max') !== null && $r->query('budget_max') !== '' ? (float) $r->query('budget_max') : null,
            'include_busy' => $r->query('include_busy') === '1',
            'sort'         => (string) $r->query('sort', 'recent'),
        ];

        $results   = ResourceRepository::search($filters);
        $skillsMap = SkillRepository::forResources(array_column($results, 'id'));

        // Match score: quante delle skill richieste sono effettivamente coperte.
        foreach ($results as &$row) {
            $row['skills'] = $skillsMap[$row['id']] ?? [];
            $row['score']  = ResourceRepository::matchScore($row['skills'], $filters['skills']);
        }
        unset($row);

        echo View::page('richiedente/search', [
            'title'      => 'Cerca risorse',
            'filters'    => $filters,
            'results'    => $results,
            'allSkills'  => SkillRepository::grouped(),
            'view'       => $r->query('vista') === 'lista' ? 'lista' : 'card',
        ]);
    }

    /**
     * Dettaglio anonimo: competenze e condizioni sì, identita' del fornitore no.
     * L'anonimato cade solo quando la richiesta viene accettata.
     */
    public function resourceDetail(Request $r): void
    {
        $resource = ResourceRepository::find((string) $r->param('id'));
        if (!$resource || $resource['publication_status'] !== 'PUBLISHED') {
            Response::abort(404, 'Risorsa non disponibile.');
        }

        $orgId    = (string) Auth::orgId();
        $existing = \App\Core\Database::selectOne(
            'SELECT * FROM resource_requests WHERE resource_id = ? AND client_org_id = ? ORDER BY created_at DESC',
            [$resource['id'], $orgId]
        );

        echo View::page('richiedente/resource_detail', [
            'title'    => $resource['title'],
            'resource' => $resource,
            'skills'   => SkillRepository::forResource($resource['id']),
            'existing' => $existing,
        ]);
    }

    public function createRequest(Request $r): void
    {
        Auth::requireWrite();
        $resource = ResourceRepository::find((string) $r->param('id'));
        if (!$resource || $resource['publication_status'] !== 'PUBLISHED') {
            Response::abort(404, 'Risorsa non disponibile.');
        }

        $brief = (string) $r->input('project_brief', '');
        $v     = new Validator();
        $v->required($brief, 'project_brief', 'Descrizione del progetto')
          ->minLength($brief, 20, 'project_brief', 'Descrizione del progetto')
          ->date($r->input('desired_start_date'), 'desired_start_date', 'Data di inizio');

        if ($v->fails()) {
            Session::flash('error', (string) $v->firstError());
            Response::redirect('/richiedente/risorsa/' . $resource['id']);
        }

        $id = RequestRepository::create([
            'resource_id'        => $resource['id'],
            'client_org_id'      => (string) Auth::orgId(),
            'created_by'         => (string) Auth::id(),
            'project_brief'      => $brief,
            'estimated_duration' => (string) $r->input('estimated_duration', ''),
            'desired_start_date' => (string) $r->input('desired_start_date', ''),
            'budget_hint'        => $r->float('budget_hint'),
            'budget_unit'        => $r->input('budget_unit') === 'HOURLY' ? 'HOURLY' : 'DAILY',
        ]);

        NotificationRepository::notifyOrg(
            $resource['organization_id'],
            'REQUEST_RECEIVED',
            'Nuova richiesta risorsa',
            'Hai ricevuto una richiesta per ' . $resource['title'] . '.',
            '/offerente/richieste'
        );

        Audit::log('REQUEST_CREATED', 'resource_request', $id);
        Session::flash('success', 'Richiesta inviata. Il fornitore ha 7 giorni per rispondere.');
        Response::redirect('/richiedente/richieste');
    }

    public function requests(Request $r): void
    {
        echo View::page('richiedente/requests', [
            'title'    => 'Le mie richieste',
            'requests' => RequestRepository::forClient((string) Auth::orgId()),
        ]);
    }

    /** Coda di approvazione dei time-sheet settimanali. */
    public function approvals(Request $r): void
    {
        $orgId = (string) Auth::orgId();
        echo View::page('richiedente/approvals', [
            'title'   => 'Approvazioni',
            'pending' => TimesheetRepository::pendingForClient($orgId),
            'recent'  => TimesheetRepository::recentForOrg($orgId, 'client', 15),
        ]);
    }
}
