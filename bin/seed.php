<?php
declare(strict_types=1);
/**
 * Dati iniziali.
 *   php bin/seed.php            solo tassonomia skill + amministratore
 *   php bin/seed.php --demo     aggiunge organizzazioni, risorse e un contratto di prova
 *
 * La password dell'amministratore si passa da riga di comando:
 *   php bin/seed.php --admin=io@dominio.it --password='...'
 */
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Auth;
use App\Core\Database as DB;
use App\Repository\{ContractRepository, OrganizationRepository, ResourceRepository, SkillRepository, UserRepository};
use App\Support\Week;

$opts     = getopt('', ['demo', 'admin::', 'password::']);
$demo     = array_key_exists('demo', $opts);
$adminMail = $opts['admin']    ?? 'admin@tallerconsulting.it';
$adminPass = $opts['password'] ?? bin2hex(random_bytes(6));

$now = DB::now();

// ---- tassonomia delle competenze -------------------------------------------
// Chiusa e curata: le skill libere producono 40 varianti di "React" e
// distruggono i filtri.
$skills = [
    'HARD' => ['PHP', 'JavaScript', 'TypeScript', 'React', 'Vue', 'Angular', 'Node.js', 'Python', 'Java', 'C#/.NET',
               'Go', 'SQL', 'PostgreSQL', 'MySQL', 'MongoDB', 'Docker', 'Kubernetes', 'AWS', 'Azure', 'Terraform',
               'CI/CD', 'Linux', 'Cybersecurity', 'Power BI', 'SAP', 'Salesforce', 'Android', 'iOS', 'Flutter',
               'UX/UI Design', 'Project Management', 'Data Engineering', 'Machine Learning'],
    'SOFT' => ['Comunicazione', 'Problem solving', 'Lavoro in team', 'Autonomia', 'Leadership',
               'Gestione del cliente', 'Precisione', 'Adattabilita\'', 'Inglese fluente'],
];

$created = 0;
foreach ($skills as $category => $names) {
    foreach ($names as $name) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? $name);
        if (DB::selectOne('SELECT id FROM skills WHERE slug = ?', [$slug])) {
            continue;
        }
        DB::execute('INSERT INTO skills (id, slug, name, category, is_active) VALUES (?,?,?,?,1)',
            [DB::uuid(), $slug, $name, $category]);
        $created++;
    }
}
echo "Skill inserite: {$created}\n";

// ---- amministratore ---------------------------------------------------------
if (!UserRepository::emailExists($adminMail)) {
    UserRepository::create([
        'organization_id' => null,
        'email'           => $adminMail,
        'password'        => $adminPass,
        'full_name'       => 'Amministratore',
        'platform_role'   => 'ADMIN',
        'org_role'        => 'OWNER',
    ]);
    echo "Admin creato: {$adminMail}\n";
    if (!isset($opts['password'])) {
        echo "  password generata: {$adminPass}\n  (cambiala al primo accesso)\n";
    }
} else {
    echo "Admin gia' presente: {$adminMail}\n";
}

if (!$demo) {
    echo "Fatto.\n";
    exit(0);
}

// ---- dati dimostrativi ------------------------------------------------------
$expiry = (new DateTimeImmutable('today'))->modify('+1 year')->format('Y-m-d');

$providerId = OrganizationRepository::create([
    'type' => 'OFFERENTE', 'legal_name' => 'Nordest Consulting S.r.l.', 'vat_number' => '01234567890',
    'sector' => 'Consulenza IT', 'size_range' => '20-50', 'phone' => '045 1234567',
]);
$clientId = OrganizationRepository::create([
    'type' => 'RICHIEDENTE', 'legal_name' => 'Acme Manufacturing S.p.A.', 'vat_number' => '09876543210',
    'sector' => 'Manifatturiero', 'size_range' => '250+', 'phone' => '02 7654321',
]);

$adminId = DB::selectOne('SELECT id FROM users WHERE platform_role = ?', ['ADMIN'])['id'];
OrganizationRepository::activate($providerId, $expiry, 'Contratto quadro 2026/01', 'Demo', $adminId);
OrganizationRepository::activate($clientId,   $expiry, 'Contratto quadro 2026/02', 'Demo', $adminId);

UserRepository::create(['organization_id' => $providerId, 'email' => 'offerente@demo.it', 'password' => 'demo-offerente-2026',
    'full_name' => 'Giulia Ferrari', 'platform_role' => 'OFFERENTE', 'org_role' => 'OWNER']);
UserRepository::create(['organization_id' => $clientId, 'email' => 'richiedente@demo.it', 'password' => 'demo-richiedente-2026',
    'full_name' => 'Marco Conti', 'platform_role' => 'RICHIEDENTE', 'org_role' => 'OWNER']);

$catalogue = [
    ['Senior React Developer', 'SENIOR', 420, 520, 'DAILY', 'IBRIDO', 'Milano', ['react', 'typescript', 'javascript', 'problem-solving']],
    ['DevOps Engineer',        'MID',    380, 460, 'DAILY', 'REMOTO', null,     ['docker', 'kubernetes', 'aws', 'ci-cd']],
    ['Data Engineer',          'SENIOR',  55,  70, 'HOURLY','REMOTO', null,     ['python', 'sql', 'data-engineering']],
    ['Tech Lead Java',         'TECH_LEAD', 600, 700, 'DAILY', 'ONSITE', 'Verona', ['java', 'sql', 'leadership']],
    ['Junior Frontend Developer', 'JUNIOR', 180, 240, 'DAILY', 'IBRIDO', 'Padova', ['javascript', 'vue', 'lavoro-in-team']],
];

$firstResourceId = null;
foreach ($catalogue as [$title, $seniority, $min, $max, $unit, $mode, $city, $slugs]) {
    $rid = ResourceRepository::save(null, $providerId, [
        'title' => $title, 'description' => 'Profilo dimostrativo generato dal seed.',
        'seniority' => $seniority, 'availability' => 'IMMEDIATA', 'engagement' => 'FULL_TIME',
        'available_from' => null, 'rate_min' => $min, 'rate_max' => $max, 'rate_unit' => $unit,
        'rate_negotiable' => true, 'work_mode' => $mode, 'city' => $city, 'province' => null,
        'languages' => 'Italiano, Inglese', 'operational_status' => 'ATTIVA',
    ]);
    $ids = [];
    foreach ($slugs as $slug) {
        $row = DB::selectOne('SELECT id FROM skills WHERE slug = ?', [$slug]);
        if ($row) { $ids[] = $row['id']; }
    }
    SkillRepository::syncResource($rid, $ids);
    ResourceRepository::setPublicationStatus($rid, 'PUBLISHED', null, $adminId);
    $firstResourceId ??= $rid;
}
echo "Risorse pubblicate: " . count($catalogue) . "\n";

// Contratto attivo: e' cio' che accende la rendicontazione.
$contractId = ContractRepository::create([
    'code' => ContractRepository::nextCode(), 'provider_org_id' => $providerId, 'client_org_id' => $clientId,
    'resource_id' => $firstResourceId, 'status' => 'ACTIVE',
    'start_date' => (new DateTimeImmutable('today'))->modify('-6 weeks')->format('Y-m-d'),
    'end_date'   => (new DateTimeImmutable('today'))->modify('+6 months')->format('Y-m-d'),
    'agreed_rate' => 450, 'rate_unit' => 'DAILY', 'timesheet_required' => true,
    'auto_approve_after_days' => null, 'visibility' => 'CONDIVISO', 'notes' => 'Contratto dimostrativo',
]);

echo "Contratto attivo: " . DB::selectOne('SELECT code FROM contracts WHERE id = ?', [$contractId])['code'] . "\n";
echo "\nCredenziali demo:\n";
echo "  admin       {$adminMail} / " . (isset($opts['password']) ? '(quella indicata)' : $adminPass) . "\n";
echo "  offerente   offerente@demo.it / demo-offerente-2026\n";
echo "  richiedente richiedente@demo.it / demo-richiedente-2026\n";
