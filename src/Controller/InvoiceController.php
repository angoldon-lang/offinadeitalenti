<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Request, Response, Session, Storage, View};
use App\Domain\Enums;
use App\Repository\{InvoiceRepository, NotificationRepository, TimesheetRepository};

final class InvoiceController
{
    public function index(Request $r): void
    {
        $invoices = Auth::isAdmin()
            ? InvoiceRepository::all((string) $r->query('stato') ?: null)
            : InvoiceRepository::forOrganization((string) Auth::orgId());

        echo View::page('invoices/index', [
            'title'    => 'Fatture',
            'invoices' => $invoices,
            'totals'   => Auth::isAdmin() ? InvoiceRepository::totals() : [],
            'filter'   => (string) $r->query('stato', ''),
        ]);
    }

    public function show(Request $r): void
    {
        $invoice = InvoiceRepository::find((string) $r->param('id'));
        if (!$invoice) {
            Response::abort(404, 'Fattura non trovata.');
        }
        if (!Auth::isAdmin() && !in_array(Auth::orgId(), [$invoice['provider_org_id'], $invoice['client_org_id']], true)) {
            Response::abort(403, 'Questa fattura non riguarda la tua organizzazione.');
        }

        echo View::page('invoices/show', [
            'title'      => 'Fattura ' . ($invoice['number'] ?: ''),
            'invoice'    => $invoice,
            'timesheets' => InvoiceRepository::timesheets($invoice['id']),
        ]);
    }

    /**
     * Riepilogo di fatturazione: le settimane approvate del periodo,
     * gia' moltiplicate per la tariffa congelata all'approvazione.
     * Il sistema NON emette fatture fiscali: prepara il conteggio.
     */
    public function billing(Request $r): void
    {
        $orgId = (string) Auth::orgId();
        $from  = (string) ($r->query('da') ?: (new \DateTimeImmutable('first day of last month'))->format('Y-m-d'));
        $to    = (string) ($r->query('a')  ?: (new \DateTimeImmutable('last day of this month'))->format('Y-m-d'));

        $billable = TimesheetRepository::billable($orgId, $from, $to);

        // Raggruppamento per cliente: una fattura per cliente, non per settimana.
        $byClient = [];
        foreach ($billable as $row) {
            $byClient[$row['client_org_id']]['client_name'] = $row['client_name'];
            $byClient[$row['client_org_id']]['rows'][]       = $row;
            $byClient[$row['client_org_id']]['total']        = ($byClient[$row['client_org_id']]['total'] ?? 0) + (float) $row['amount'];
        }

        echo View::page('invoices/billing', [
            'title'    => 'Da fatturare',
            'byClient' => $byClient,
            'from'     => $from,
            'to'       => $to,
        ]);
    }

    public function create(Request $r): void
    {
        Auth::requireWrite();
        $orgId        = (string) Auth::orgId();
        $timesheetIds = array_values(array_filter((array) $r->array('timesheets'), 'is_string'));

        if ($timesheetIds === []) {
            Session::flash('error', 'Seleziona almeno una settimana da fatturare.');
            Response::redirect('/fatture/da-fatturare');
        }

        // Ogni settimana deve appartenere a questo fornitore ed essere approvata.
        $clientOrgId = null;
        $periodStart = null;
        $periodEnd   = null;
        foreach ($timesheetIds as $tsId) {
            $ts = TimesheetRepository::find($tsId);
            if (!$ts || $ts['provider_org_id'] !== $orgId || $ts['status'] !== 'APPROVED' || $ts['invoice_id'] !== null) {
                Session::flash('error', 'Una delle settimane selezionate non e\' fatturabile.');
                Response::redirect('/fatture/da-fatturare');
            }
            if ($clientOrgId !== null && $clientOrgId !== $ts['client_org_id']) {
                Session::flash('error', 'Una fattura puo\' raggruppare solo settimane dello stesso cliente.');
                Response::redirect('/fatture/da-fatturare');
            }
            $clientOrgId = $ts['client_org_id'];
            $periodStart = $periodStart === null ? $ts['week_start'] : min($periodStart, $ts['week_start']);
            $periodEnd   = $periodEnd   === null ? $ts['week_end']   : max($periodEnd, $ts['week_end']);
        }

        $file = null;
        if (!empty($_FILES['document']['name'])) {
            try {
                $file = Storage::storePdf($_FILES['document'], 'fatture/' . $orgId);
            } catch (\RuntimeException $e) {
                Session::flash('error', $e->getMessage());
                Response::redirect('/fatture/da-fatturare');
            }
        }

        $id = InvoiceRepository::createFromTimesheets([
            'number'          => (string) $r->input('number', ''),
            'provider_org_id' => $orgId,
            'client_org_id'   => (string) $clientOrgId,
            'contract_id'     => null,
            'period_start'    => (string) $periodStart,
            'period_end'      => (string) $periodEnd,
            'issue_date'      => (string) $r->input('issue_date', ''),
            'due_date'        => (string) $r->input('due_date', ''),
            'vat_rate'        => $r->float('vat_rate', 22.0),
            'payment_status'  => 'EMESSA',
            'file_name'       => $file['file_name'] ?? null,
            'storage_key'     => $file['key'] ?? null,
            'uploaded_by'     => (string) Auth::id(),
            'notes'           => (string) $r->input('notes', ''),
        ], $timesheetIds);

        NotificationRepository::notifyOrg(
            (string) $clientOrgId,
            'INVOICE_ISSUED',
            'Nuova fattura ricevuta',
            'Periodo ' . $periodStart . ' – ' . $periodEnd,
            '/fatture/' . $id
        );

        Audit::log('INVOICE_CREATED', 'invoice', $id, ['weeks' => count($timesheetIds)]);
        Session::flash('success', 'Fattura creata: ' . count($timesheetIds) . ' settimane marcate come fatturate.');
        Response::redirect('/fatture/' . $id);
    }

    public function uploadFile(Request $r): void
    {
        Auth::requireWrite();
        $invoice = InvoiceRepository::find((string) $r->param('id'));
        if (!$invoice) {
            Response::abort(404, 'Fattura non trovata.');
        }
        if (!Auth::isAdmin() && $invoice['provider_org_id'] !== Auth::orgId()) {
            Response::abort(403, 'Solo il fornitore puo\' allegare il PDF della fattura.');
        }

        try {
            $file = Storage::storePdf($_FILES['document'] ?? [], 'fatture/' . $invoice['provider_org_id']);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/fatture/' . $invoice['id']);
        }

        InvoiceRepository::attachFile($invoice['id'], $file, (string) Auth::id());
        Audit::log('INVOICE_FILE_UPLOADED', 'invoice', $invoice['id']);
        Session::flash('success', 'PDF della fattura caricato.');
        Response::redirect('/fatture/' . $invoice['id']);
    }

    public function download(Request $r): void
    {
        $invoice = InvoiceRepository::find((string) $r->param('id'));
        if (!$invoice || !$invoice['storage_key']) {
            Response::abort(404, 'Nessun PDF allegato a questa fattura.');
        }
        if (!Auth::isAdmin() && !in_array(Auth::orgId(), [$invoice['provider_org_id'], $invoice['client_org_id']], true)) {
            Response::abort(403, 'Non hai accesso a questo documento.');
        }

        Audit::log('INVOICE_DOWNLOADED', 'invoice', $invoice['id']);
        Response::download(Storage::absolutePath($invoice['storage_key']), $invoice['file_name'] ?: 'fattura.pdf');
    }

    /** Lo stato del pagamento lo aggiorna solo l'amministratore. */
    public function updateStatus(Request $r): void
    {
        $invoice = InvoiceRepository::find((string) $r->param('id'));
        if (!$invoice) {
            Response::abort(404, 'Fattura non trovata.');
        }

        $status = (string) $r->input('payment_status', '');
        if (!array_key_exists($status, Enums::PAYMENT_STATUS)) {
            Response::abort(422, 'Stato pagamento non valido.');
        }

        InvoiceRepository::updatePaymentStatus(
            $invoice['id'],
            $status,
            (string) $r->input('paid_at', ''),
            $r->float('paid_amount'),
            (string) $r->input('notes', '') ?: null
        );

        Audit::log('INVOICE_STATUS_CHANGED', 'invoice', $invoice['id'], [
            'from' => $invoice['payment_status'],
            'to'   => $status,
        ]);
        Session::flash('success', 'Stato pagamento aggiornato a "' . Enums::label(Enums::PAYMENT_STATUS, $status) . '".');
        Response::redirect('/fatture/' . $invoice['id']);
    }
}
