<?php
/**
 * Copia questo file in config/config.local.php e adatta i valori.
 * config.local.php NON va in git (vedi .gitignore).
 */
return [
    'app' => [
        'name'      => 'Officina dei Talenti',
        'env'       => 'production',   // 'local' abilita gli errori a schermo
        'base_path' => '',             // '' se in document root, '/portale' se in sottocartella
        'timezone'  => 'Europe/Rome',
    ],
    'db' => [
        // Su Aruba Hosting Basic Linux: driver 'mysql'.
        // I parametri esatti sono nel pannello Aruba > Database > Gestione.
        'driver'   => 'mysql',
        'host'     => 'sql.tallerconsulting.it',
        'port'     => 3306,
        'database' => 'Sql1234567_1',
        'username' => 'Sql1234567',
        'password' => 'CAMBIAMI',
        'charset'  => 'utf8mb4',
        // usato solo con driver 'sqlite' (sviluppo locale)
        'path'     => __DIR__ . '/../storage/dev.sqlite',
    ],
    'storage' => [
        // Cartella FUORI da public/. I PDF non devono mai avere un URL diretto.
        'documents' => __DIR__ . '/../storage/documents',
        'logs'      => __DIR__ . '/../storage/logs',
        'max_bytes' => 20 * 1024 * 1024,
    ],
    'mail' => [
        // Aruba Basic non ha SMTP autenticato in uscita affidabile da PHP mail():
        // in produzione conviene lo SMTP della casella del dominio.
        'enabled' => false,
        'from'    => 'noreply@tallerconsulting.it',
        'from_name' => 'Officina dei Talenti',
    ],
    'security' => [
        'session_name'   => 'odt_session',
        'grace_days'     => 15,
        'request_expiry_days' => 7,
    ],
];
