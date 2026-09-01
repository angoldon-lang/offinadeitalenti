<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Request, Response, Session, View};
use App\Repository\{ContractRepository, NotificationRepository, TimesheetRepository};
use App\Support\Week;

/**
 * Rendicontazione settimanale. E' l'unico controller usato da tutti e tre i
 * ruoli, con permessi diversi sulla stessa entita':
 *   - il fornitore compila e invia
 *   - il cliente approva o rifiuta
 *   - l'admin osserva e, se serve, corregge con override tracciato
 */
final class TimesheetController
{
    /** Elenco delle settimane da compilare, per contratto. */
    public function index(Request $r): void
    {
        $orgId     = (string) Auth::orgId();
        $contracts = ContractRepository::activeForProvider($orgId);

        $rows = [];
        foreach ($contracts as $contract) {
            $index = TimesheetRepository::indexForContract($contract['id']);
            foreach (Week::forContract($contract['start_date'], $contract['end_date']) as $week) {
                $key    = $week['iso_year'] . '-' . $week['iso_week'];
                $rows[] = [
                    'contract'  => $contract,
                    'iso_year'  => $week['iso_year'],
                    'iso_week'  => $week['iso_week'],
                    'timesheet' => $index[$key] ?? null,
                ];
            }
        }

        echo View::page('offerente/timesheets', [
            'title'     => 'Rendicontazione',
            'rows'      => $rows,
            'contracts' => $contracts,
        ]);
    }

    /**
     * La schermata cardine: una settimana, sette giorni, un totale.
     * Accessibile a fornitore (scrittura), cliente (lettura) e admin.
     */
    public function week(Request $r): void
    {
        $contract = ContractRepository::findForOrg((string) $r->param('contract'), Auth::orgId(), Auth::isAdmin());
        if (!$contract) {
            Response::abort(404, 'Contratto non trovato.');
        }

        $isoYear = (int) ($r->param('year') ?? 0);
        $isoWeek = (int) ($r->param('week') ?? 0);
        if ($isoYear < 2000 || $isoWeek < 1 || $isoWeek > 53) {
            Response::abort(400, 'Settimana non valida.');
        }

        $timesheet = TimesheetRepository::ensureWeek($contract, $isoYear, $isoWeek);
        $this->renderWeek($timesheet, $contract);
    }

    public function show(Request $r): void
    {
        $timesheet = TimesheetRepository::find((string) $r->param('id'));
        if (!$timesheet) {
            Response::abort(404, 'Settimana non trovata.');
        }
        $contract = ContractRepository::findForOrg($timesheet['contract_id'], Auth::orgId(), Auth::isAdmin());
        if (!$contract) {
            Response::abort(403, 'Questa settimana non riguarda la tua organizzazione.');
        }
        $this->renderWeek($timesheet, $contract);
    }

    private function renderWeek(array $timesheet, array $contract): void
    {
        $orgId      = Auth::orgId();
        $isProvider = $contract['provider_org_id'] === $orgId;
        $isClient   = $contract['client_org_id'] === $orgId;

        $days     = TimesheetRepository::days($timesheet['id']);
        $holidays = Week::holidays((int) $timesheet['iso_year']);

        echo View::page('timesheet/week', [
            'title'      => 'Settimana ' . $timesheet['iso_week'],
            'timesheet'  => $timesheet,
            'contract'   => $contract,
            'days'       => $days,
            'holidays'   => $holidays,
            'events'     => TimesheetRepository::events($timesheet['id']),
            // La compilazione e' possibile solo al fornitore, solo in bozza,
            // solo con account attivo.
            'canEdit'    => $isProvider && in_array($timesheet['status'], ['DRAFT', 'REJECTED'], true) && Auth::canWrite(),
            'canApprove' => $isClient && $timesheet['status'] === 'SUBMITTED' && Auth::canWrite(),
            'isProvider' => $isProvider,
            'prevWeek'   => Week::shift((int) $timesheet['iso_year'], (int) $timesheet['iso_week'], -1),
            'nextWeek'   => Week::shift((int) $timesheet['iso_year'], (int) $timesheet['iso_week'], +1),
        ]);
    }

    /**
     * Salvataggio di una giornata (chiamata dal client, anche dalla coda
     * offline). Risponde in JSON con il totale RICALCOLATO DAL SERVER:
     * il client non decide mai quanto vale una settimana.
     */
    public function updateDay(Request $r): void
    {
        $timesheet = TimesheetRepository::find((string) $r->param('id'));
        if (!$timesheet) {
            Response::json(['error' => 'Settimana non trovata.'], 404);
        }

        $contract = ContractRepository::findForOrg($timesheet['contract_id'], Auth::orgId(), Auth::isAdmin());
        if (!$contract || $contract['provider_org_id'] !== Auth::orgId()) {
            Response::json(['error' => 'Non puoi modificare questa settimana.'], 403);
        }
        if (!in_array($timesheet['status'], ['DRAFT', 'REJECTED'], true)) {
            Response::json([
                'error'  => 'Questa settimana e\' gia\' stata inviata: le modifiche locali non sono state applicate.',
                'status' => $timesheet['status'],
            ], 409);
        }
        if (!Auth::canWrite()) {
            Response::json(['error' => 'Account non attivo: sola lettura.'], 403);
        }

        $date = (string) $r->input('date', '');
        $day  = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date) {
            Response::json(['error' => 'Data non valida.'], 422);
        }

        $quantity = (float) str_replace(',', '.', (string) $r->input('quantity', '0'));
        $max      = $timesheet['unit'] === 'HOURLY' ? 24 : 1;
        if ($quantity < 0 || $quantity > $max) {
            Response::json(['error' => "Valore fuori intervallo (0–{$max})."], 422);
        }

        $dayType = (string) $r->input('day_type', '');
        $dayType = array_key_exists($dayType, \App\Domain\Enums::DAY_TYPE) ? $dayType : null;

        $totals = TimesheetRepository::updateDay(
            $timesheet['id'],
            $date,
            $quantity,
            $dayType,
            (string) $r->input('note', '')
        );

        Response::json([
            'ok'     => true,
            'total'  => $totals['total'],
            'amount' => round($totals['amount'], 2),
            'unit'   => $timesheet['unit'],
        ]);
    }

    public function submit(Request $r): void
    {
        Auth::requireWrite();
        $timesheet = TimesheetRepository::find((string) $r->param('id'));
        if (!$timesheet) {
            Response::abort(404, 'Settimana non trovata.');
        }
        if ($timesheet['provider_org_id'] !== Auth::orgId()) {
            Response::abort(403, 'Solo il fornitore puo\' inviare questa settimana.');
        }
        if (!in_array($timesheet['status'], ['DRAFT', 'REJECTED'], true)) {
            Session::flash('error', 'Questa settimana e\' gia\' stata inviata.');
            Response::redirect('/rendicontazione/' . $timesheet['id']);
        }

        $user = Auth::user();
        TimesheetRepository::submit($timesheet['id'], (string) $user['id'], (string) $user['full_name']);

        NotificationRepository::notifyOrg(
            $timesheet['client_org_id'],
            'TIMESHEET_SUBMITTED',
            'Settimana da approvare',
            'Settimana ' . $timesheet['iso_week'] . ' · ' . ($timesheet['resource_title'] ?? $timesheet['contract_code']),
            '/richiedente/approvazioni'
        );

        Audit::log('TIMESHEET_SUBMITTED', 'timesheet', $timesheet['id']);
        Session::flash('success', 'Settimana inviata in approvazione.');
        Response::redirect('/rendicontazione/' . $timesheet['id']);
    }

    public function approve(Request $r): void
    {
        Auth::requireWrite();
        $timesheet = TimesheetRepository::find((string) $r->param('id'));
        if (!$timesheet) {
            Response::abort(404, 'Settimana non trovata.');
        }
        if ($timesheet['client_org_id'] !== Auth::orgId()) {
            Response::abort(403, 'Solo il cliente puo\' approvare questa settimana.');
        }

        $user = Auth::user();
        try {
            TimesheetRepository::approve($timesheet['id'], (string) $user['id'], (string) $user['full_name']);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/richiedente/approvazioni');
        }

        NotificationRepository::notifyOrg(
            $timesheet['provider_org_id'],
            'TIMESHEET_APPROVED',
            'Settimana approvata',
            'Settimana ' . $timesheet['iso_week'] . ' approvata: e\' pronta per la fatturazione.',
            '/offerente/rendicontazione'
        );

        Audit::log('TIMESHEET_APPROVED', 'timesheet', $timesheet['id'], ['amount' => $timesheet['total_quantity']]);
        Session::flash('success', 'Settimana approvata.');
        Response::redirect($r->input('back') ?: '/richiedente/approvazioni');
    }

    public function reject(Request $r): void
    {
        Auth::requireWrite();
        $timesheet = TimesheetRepository::find((string) $r->param('id'));
        if (!$timesheet) {
            Response::abort(404, 'Settimana non trovata.');
        }
        if ($timesheet['client_org_id'] !== Auth::orgId()) {
            Response::abort(403, 'Solo il cliente puo\' rifiutare questa settimana.');
        }

        // Un rifiuto senza motivazione e' un vicolo cieco per chi ha compilato.
        $reason = trim((string) $r->input('reason', ''));
        if (mb_strlen($reason) < 5) {
            Session::flash('error', 'Indica il motivo del rifiuto: senza spiegazione il fornitore non sa cosa correggere.');
            Response::redirect('/rendicontazione/' . $timesheet['id']);
        }

        $user = Auth::user();
        TimesheetRepository::reject($timesheet['id'], (string) $user['id'], (string) $user['full_name'], $reason);

        NotificationRepository::notifyOrg(
            $timesheet['provider_org_id'],
            'TIMESHEET_REJECTED',
            'Settimana rifiutata',
            'Settimana ' . $timesheet['iso_week'] . ': ' . mb_substr($reason, 0, 120),
            '/offerente/rendicontazione'
        );

        Audit::log('TIMESHEET_REJECTED', 'timesheet', $timesheet['id'], ['reason' => $reason]);
        Session::flash('success', 'Settimana rifiutata: il fornitore e\' stato avvisato.');
        Response::redirect('/richiedente/approvazioni');
    }

    /** Vista dedicata della risorsa: solo la propria settimana corrente. */
    public function myWeek(Request $r): void
    {
        $user = Auth::user();
        if (empty($user['resource_id'])) {
            Response::abort(403, 'Nessuna risorsa associata a questo account.');
        }

        $contracts = \App\Core\Database::select(
            'SELECT c.*, co.legal_name AS client_name, r.title AS resource_title
               FROM contracts c
               JOIN organizations co ON co.id = c.client_org_id
          LEFT JOIN resources r      ON r.id  = c.resource_id
              WHERE c.resource_id = ? AND c.status = ? AND c.timesheet_required = 1
           ORDER BY co.legal_name',
            [$user['resource_id'], 'ACTIVE']
        );

        [$year, $week] = Week::current();

        echo View::page('risorsa/index', [
            'title'     => 'Le mie ore',
            'contracts' => $contracts,
            'isoYear'   => $year,
            'isoWeek'   => $week,
        ]);
    }
}
