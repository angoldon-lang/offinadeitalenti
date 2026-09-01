<?php
declare(strict_types=1);

/**
 * Front controller unico. Tutte le richieste passano da qui: e' il punto in
 * cui si applicano sessione, ruoli e CSRF, senza eccezioni.
 */
// Due layout possibili su hosting condiviso (vedi DEPLOY-ARUBA.md):
//  A) document root -> public/ , applicazione nella cartella superiore
//  B) tutto dentro la document root, con src/ config/ storage/ protetti da .htaccess
$appRoot = is_file(dirname(__DIR__) . '/src/bootstrap.php') ? dirname(__DIR__) : __DIR__;
require $appRoot . '/src/bootstrap.php';

use App\Controller\{AdminController, AuthController, ClientController, ContractController,
                    HomeController, InvoiceController, OffererController, TimesheetController};
use App\Core\{Auth, Request, Response, Router, View};

// Intestazioni di sicurezza di base (su Aruba non c'e' un reverse proxy che le aggiunga).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

$request = new Request();
$router  = new Router();

View::share('currentUser', Auth::user());
View::share('unread', Auth::id() ? \App\Repository\NotificationRepository::unreadCount((string) Auth::id()) : 0);

// ---- pubbliche --------------------------------------------------------------
$router->get('/',            [HomeController::class, 'index']);
$router->get('/login',       [AuthController::class, 'showLogin']);
$router->post('/login',      [AuthController::class, 'login']);
$router->get('/registrati',  [AuthController::class, 'showRegister']);
$router->post('/registrati', [AuthController::class, 'register']);
$router->get('/logout',      [AuthController::class, 'logout']);

$ALL = ['OFFERENTE', 'RICHIEDENTE', 'RESOURCE_USER', 'ADMIN'];

$router->get('/in-attivazione', [AuthController::class, 'pending'], $ALL);
$router->get('/notifiche',      [HomeController::class, 'notifications'], $ALL);

// ---- area OFFERENTE ---------------------------------------------------------
$router->group(['OFFERENTE'], function (Router $r): void {
    $r->get('/offerente',                       [OffererController::class, 'dashboard']);
    $r->get('/offerente/risorse',               [OffererController::class, 'resources']);
    $r->get('/offerente/risorse/{id}',          [OffererController::class, 'resourceForm']);
    $r->post('/offerente/risorse/{id}',         [OffererController::class, 'saveResource']);
    $r->post('/offerente/risorse/{id}/invia',   [OffererController::class, 'submitResource']);
    $r->post('/offerente/risorse/{id}/stato',   [OffererController::class, 'toggleStatus']);
    $r->get('/offerente/richieste',             [OffererController::class, 'requests']);
    $r->post('/offerente/richieste/{id}',       [OffererController::class, 'respondRequest']);
    $r->get('/offerente/rendicontazione',       [TimesheetController::class, 'index']);
});

// ---- area RICHIEDENTE -------------------------------------------------------
$router->group(['RICHIEDENTE'], function (Router $r): void {
    $r->get('/richiedente',                     [ClientController::class, 'search']);
    $r->get('/richiedente/risorsa/{id}',        [ClientController::class, 'resourceDetail']);
    $r->post('/richiedente/risorsa/{id}',       [ClientController::class, 'createRequest']);
    $r->get('/richiedente/richieste',           [ClientController::class, 'requests']);
    $r->get('/richiedente/approvazioni',        [ClientController::class, 'approvals']);
});

// ---- area RISORSA (accesso limitato al proprio time-sheet) ------------------
$router->get('/risorsa', [TimesheetController::class, 'myWeek'], ['RESOURCE_USER']);

// ---- rendicontazione: entita' condivisa, permessi diversi per ruolo ---------
$router->get('/rendicontazione/{contract}/{year}/{week}', [TimesheetController::class, 'week'], $ALL);
$router->get('/rendicontazione/{id}',                     [TimesheetController::class, 'show'], $ALL);
$router->post('/rendicontazione/{id}/giorno',             [TimesheetController::class, 'updateDay'], ['OFFERENTE', 'RESOURCE_USER', 'ADMIN']);
$router->post('/rendicontazione/{id}/invia',              [TimesheetController::class, 'submit'], ['OFFERENTE', 'RESOURCE_USER']);
$router->post('/rendicontazione/{id}/approva',            [TimesheetController::class, 'approve'], ['RICHIEDENTE']);
$router->post('/rendicontazione/{id}/rifiuta',            [TimesheetController::class, 'reject'], ['RICHIEDENTE']);

// ---- contratti e fatture: condivisi fra le parti ----------------------------
$router->get('/contratti',                    [ContractController::class, 'index'], $ALL);
$router->get('/contratti/nuovo',              [ContractController::class, 'form'], ['OFFERENTE', 'ADMIN']);
$router->post('/contratti/nuovo',             [ContractController::class, 'create'], ['OFFERENTE', 'ADMIN']);
$router->get('/contratti/{id}',               [ContractController::class, 'show'], $ALL);
$router->post('/contratti/{id}',              [ContractController::class, 'update'], ['OFFERENTE', 'ADMIN']);
$router->post('/contratti/{id}/documento',    [ContractController::class, 'uploadDocument'], ['OFFERENTE', 'RICHIEDENTE', 'ADMIN']);
$router->get('/documenti/{id}',               [ContractController::class, 'downloadDocument'], $ALL);

$router->get('/fatture',                      [InvoiceController::class, 'index'], $ALL);
$router->get('/fatture/da-fatturare',         [InvoiceController::class, 'billing'], ['OFFERENTE']);
$router->post('/fatture/crea',                [InvoiceController::class, 'create'], ['OFFERENTE']);
$router->get('/fatture/{id}',                 [InvoiceController::class, 'show'], $ALL);
$router->post('/fatture/{id}/documento',      [InvoiceController::class, 'uploadFile'], ['OFFERENTE', 'ADMIN']);
$router->get('/fatture/{id}/scarica',         [InvoiceController::class, 'download'], $ALL);
$router->post('/fatture/{id}/stato',          [InvoiceController::class, 'updateStatus'], ['ADMIN']);

// ---- area AMMINISTRATORE ----------------------------------------------------
$router->group(['ADMIN'], function (Router $r): void {
    $r->get('/admin',                                 [AdminController::class, 'dashboard']);
    $r->get('/admin/organizzazioni',                  [AdminController::class, 'organizations']);
    $r->get('/admin/organizzazioni/{id}',             [AdminController::class, 'organization']);
    $r->post('/admin/organizzazioni/{id}/scadenza',   [AdminController::class, 'setExpiry']);
    $r->post('/admin/organizzazioni/{id}/stato',      [AdminController::class, 'setOrgStatus']);
    $r->get('/admin/moderazione',                     [AdminController::class, 'moderation']);
    $r->get('/admin/moderazione/{id}',                [AdminController::class, 'reviewResource']);
    $r->post('/admin/moderazione/{id}',               [AdminController::class, 'moderate']);
    $r->get('/admin/monitor',                         [AdminController::class, 'monitor']);
    $r->get('/admin/pagamenti',                       [AdminController::class, 'payments']);
    $r->post('/admin/rendicontazione/{id}/override',  [AdminController::class, 'overrideTimesheet']);
    $r->get('/admin/audit',                           [AdminController::class, 'auditLog']);
});

try {
    $router->dispatch($request);
} catch (\Throwable $e) {
    if (\App\Core\Config::isLocal()) {
        throw $e;
    }
    error_log('[' . date('c') . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::abort(500, 'Si e\' verificato un errore. Riprova o contatta l\'assistenza.');
}
