<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Audit, Auth, Request, Response, Session, Storage, Validator, View};
use App\Domain\Enums;
use App\Repository\{ContractRepository, NotificationRepository, OrganizationRepository, ResourceRepository, TimesheetRepository};

final class ContractController
{
    public function index(Request $r): void
    {
        $contracts = Auth::isAdmin()
            ? ContractRepository::all()
            : ContractRepository::forOrganization((string) Auth::orgId());

        echo View::page('contracts/index', ['title' => 'Contratti', 'contracts' => $contracts]);
    }

    public function show(Request $r): void
    {
        $contract = ContractRepository::findForOrg((string) $r->param('id'), Auth::orgId(), Auth::isAdmin());
        if (!$contract) {
            Response::abort(404, 'Contratto non trovato.');
        }

        echo View::page('contracts/show', [
            'title'      => $contract['code'],
            'contract'   => $contract,
            'documents'  => ContractRepository::documents($contract['id']),
            'timesheets' => TimesheetRepository::indexForContract($contract['id']),
        ]);
    }

    public function form(Request $r): void
    {
        $orgId = Auth::orgId();
        echo View::page('contracts/form', [
            'title'     => 'Nuovo contratto',
            'code'      => ContractRepository::nextCode(),
            'clients'   => OrganizationRepository::all('ACTIVE', 'RICHIEDENTE'),
            'providers' => Auth::isAdmin() ? OrganizationRepository::all('ACTIVE', 'OFFERENTE') : [],
            'resources' => Auth::isAdmin()
                ? \App\Core\Database::select('SELECT id, title, organization_id FROM resources ORDER BY title')
                : ResourceRepository::forOrganization((string) $orgId),
        ]);
    }

    /**
     * Il contratto e' il perno del sistema: porta con se' la tariffa
     * concordata, che e' la sola base di calcolo della rendicontazione.
     */
    public function create(Request $r): void
    {
        Auth::requireWrite();

        $providerOrgId = Auth::isAdmin()
            ? (string) $r->input('provider_org_id', '')
            : (string) Auth::orgId();

        $data = [
            'code'                    => ContractRepository::nextCode(),
            'provider_org_id'         => $providerOrgId,
            'client_org_id'           => (string) $r->input('client_org_id', ''),
            'resource_id'             => (string) $r->input('resource_id', ''),
            'status'                  => in_array($r->input('status'), Enums::keys(Enums::CONTRACT_STATUS), true) ? (string) $r->input('status') : 'DRAFT',
            'start_date'              => (string) $r->input('start_date', ''),
            'end_date'                => (string) $r->input('end_date', ''),
            'agreed_rate'             => $r->float('agreed_rate', 0.0),
            'rate_unit'               => $r->input('rate_unit') === 'HOURLY' ? 'HOURLY' : 'DAILY',
            'timesheet_required'      => $r->bool('timesheet_required'),
            'auto_approve_after_days' => $r->int('auto_approve_after_days'),
            'visibility'              => in_array($r->input('visibility'), Enums::keys(Enums::DOC_VISIBILITY), true) ? (string) $r->input('visibility') : 'CONDIVISO',
            'notes'                   => (string) $r->input('notes', ''),
        ];

        $v = new Validator();
        $v->required($data['client_org_id'], 'client_org_id', 'Cliente')
          ->required($data['provider_org_id'], 'provider_org_id', 'Fornitore')
          ->rule($data['provider_org_id'] !== $data['client_org_id'], 'client_org_id', 'Fornitore e cliente devono essere diversi.')
          ->date($data['start_date'], 'start_date', 'Data inizio')
          ->required($data['start_date'], 'start_date', 'Data inizio')
          ->date($data['end_date'], 'end_date', 'Data fine')
          ->required($data['end_date'], 'end_date', 'Data fine')
          ->rule($data['end_date'] >= $data['start_date'], 'end_date', 'La data di fine non puo\' precedere quella di inizio.')
          ->numericRange($data['agreed_rate'], 1, 100000, 'agreed_rate', 'Tariffa concordata');

        if ($v->fails()) {
            Session::flash('error', (string) $v->firstError());
            Response::redirect('/contratti/nuovo');
        }

        $id = ContractRepository::create($data);
        Audit::log('CONTRACT_CREATED', 'contract', $id, ['code' => $data['code']]);

        if ($data['status'] === 'ACTIVE') {
            NotificationRepository::notifyOrg(
                $data['client_org_id'],
                'CONTRACT_ACTIVE',
                'Nuovo contratto attivo',
                $data['code'] . ': da ora e\' attiva la rendicontazione settimanale.',
                '/contratti/' . $id
            );
        }

        Session::flash('success', 'Contratto ' . $data['code'] . ' creato.');
        Response::redirect('/contratti/' . $id);
    }

    public function update(Request $r): void
    {
        Auth::requireWrite();
        $contract = ContractRepository::findForOrg((string) $r->param('id'), Auth::orgId(), Auth::isAdmin());
        if (!$contract) {
            Response::abort(404, 'Contratto non trovato.');
        }
        // Solo l'admin o il fornitore possono cambiare le condizioni economiche.
        if (!Auth::isAdmin() && $contract['provider_org_id'] !== Auth::orgId()) {
            Response::abort(403, 'Solo il fornitore o l\'amministratore possono modificare il contratto.');
        }

        ContractRepository::update($contract['id'], [
            'status'                  => in_array($r->input('status'), Enums::keys(Enums::CONTRACT_STATUS), true) ? (string) $r->input('status') : $contract['status'],
            'start_date'              => (string) $r->input('start_date', $contract['start_date']),
            'end_date'                => (string) $r->input('end_date', $contract['end_date']),
            'agreed_rate'             => $r->float('agreed_rate', (float) $contract['agreed_rate']),
            'rate_unit'               => $r->input('rate_unit') === 'HOURLY' ? 'HOURLY' : 'DAILY',
            'timesheet_required'      => $r->bool('timesheet_required'),
            'auto_approve_after_days' => $r->int('auto_approve_after_days'),
            'notes'                   => (string) $r->input('notes', ''),
        ]);

        Audit::log('CONTRACT_UPDATED', 'contract', $contract['id']);
        Session::flash('success', 'Contratto aggiornato. Le settimane gia\' approvate mantengono la tariffa con cui sono state approvate.');
        Response::redirect('/contratti/' . $contract['id']);
    }

    public function uploadDocument(Request $r): void
    {
        Auth::requireWrite();
        $contract = ContractRepository::findForOrg((string) $r->param('id'), Auth::orgId(), Auth::isAdmin());
        if (!$contract) {
            Response::abort(404, 'Contratto non trovato.');
        }

        try {
            $file = Storage::storePdf($_FILES['document'] ?? [], 'contratti/' . $contract['id']);
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/contratti/' . $contract['id']);
        }

        $docType    = array_key_exists((string) $r->input('doc_type'), Enums::CONTRACT_DOC_TYPE) ? (string) $r->input('doc_type') : 'ORDINE';
        $visibility = array_key_exists((string) $r->input('visibility'), Enums::DOC_VISIBILITY) ? (string) $r->input('visibility') : 'CONDIVISO';

        $docId = ContractRepository::addDocument(
            $contract['id'], $file, $docType, $visibility,
            (string) $r->input('signed_at', ''), (string) Auth::id()
        );

        Audit::log('CONTRACT_DOC_UPLOADED', 'contract_document', $docId, ['contract' => $contract['code']]);
        Session::flash('success', 'Documento caricato.');
        Response::redirect('/contratti/' . $contract['id']);
    }

    /**
     * Download: il file non ha un URL pubblico. Si controlla prima il
     * permesso, poi si serve il contenuto, e ogni accesso lascia traccia.
     */
    public function downloadDocument(Request $r): void
    {
        $doc = ContractRepository::findDocument((string) $r->param('id'));
        if (!$doc) {
            Response::abort(404, 'Documento non trovato.');
        }
        $contract = ContractRepository::find($doc['contract_id']);
        if (!$contract || !ContractRepository::canAccessDocument($doc, $contract, Auth::orgId(), Auth::isAdmin())) {
            Response::abort(403, 'Non hai accesso a questo documento.');
        }

        Audit::log('CONTRACT_DOC_DOWNLOADED', 'contract_document', $doc['id']);
        Response::download(Storage::absolutePath($doc['storage_key']), $doc['file_name']);
    }
}
