<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Elenchi controllati con le rispettive etichette italiane.
 * Il database applica gli stessi valori via CHECK constraint: qui si tiene
 * la sola traduzione per l'interfaccia.
 */
final class Enums
{
    public const ORG_TYPES = ['OFFERENTE' => 'Offerente', 'RICHIEDENTE' => 'Richiedente'];

    public const ORG_STATUS = [
        'PENDING_APPROVAL' => 'In attivazione',
        'ACTIVE'           => 'Attivo',
        'GRACE'            => 'In scadenza',
        'EXPIRED'          => 'Scaduto',
        'SUSPENDED'        => 'Sospeso',
    ];

    public const SENIORITY = [
        'JUNIOR'    => 'Junior',
        'MID'       => 'Mid',
        'SENIOR'    => 'Senior',
        'TECH_LEAD' => 'Tech Lead',
    ];

    public const AVAILABILITY = [
        'IMMEDIATA'     => 'Immediata',
        'ENTRO_1_MESE'  => 'Entro 1 mese',
        'ENTRO_3_MESI'  => 'Entro 3 mesi',
    ];

    public const ENGAGEMENT = ['PART_TIME' => 'Part-time', 'FULL_TIME' => 'Full-time'];

    public const RATE_UNIT = ['DAILY' => 'al giorno', 'HOURLY' => 'all\'ora'];

    public const RATE_UNIT_SHORT = ['DAILY' => '€/gg', 'HOURLY' => '€/h'];

    public const WORK_MODE = ['ONSITE' => 'Onsite', 'REMOTO' => 'Remoto', 'IBRIDO' => 'Ibrido'];

    public const RESOURCE_OP_STATUS = ['ATTIVA' => 'Attiva', 'OCCUPATA' => 'Occupata'];

    public const RESOURCE_PUB_STATUS = [
        'DRAFT'     => 'Bozza',
        'IN_REVIEW' => 'In approvazione',
        'PUBLISHED' => 'Pubblicata',
        'REJECTED'  => 'Rifiutata',
        'ARCHIVED'  => 'Archiviata',
    ];

    public const REQUEST_STATUS = [
        'REQUESTED'      => 'Inviata',
        'ACCEPTED'       => 'Accettata',
        'DECLINED'       => 'Rifiutata',
        'IN_NEGOTIATION' => 'In trattativa',
        'CONTRACTED'     => 'Contrattualizzata',
        'EXPIRED'        => 'Scaduta',
        'CLOSED'         => 'Chiusa',
    ];

    public const CONTRACT_STATUS = [
        'DRAFT'      => 'Bozza',
        'ACTIVE'     => 'Attivo',
        'SUSPENDED'  => 'Sospeso',
        'EXPIRED'    => 'Scaduto',
        'TERMINATED' => 'Chiuso',
    ];

    public const TIMESHEET_STATUS = [
        'DRAFT'     => 'Da compilare',
        'SUBMITTED' => 'In attesa di approvazione',
        'APPROVED'  => 'Approvato',
        'REJECTED'  => 'Rifiutato',
        'INVOICED'  => 'Fatturato',
        'PAID'      => 'Pagato',
    ];

    /** Colore + icona: lo stato non e' mai comunicato dal solo colore. */
    public const TIMESHEET_BADGE = [
        'DRAFT'     => ['slate',   '○'],
        'SUBMITTED' => ['amber',   '◔'],
        'APPROVED'  => ['emerald', '✓'],
        'REJECTED'  => ['rose',    '✕'],
        'INVOICED'  => ['violet',  '▤'],
        'PAID'      => ['green',   '€'],
    ];

    public const DAY_TYPE = [
        'LAVORO'        => 'Lavoro',
        'TRASFERTA'     => 'Trasferta',
        'FERIE'         => 'Ferie',
        'PERMESSO'      => 'Permesso',
        'MALATTIA'      => 'Malattia',
        'FESTIVO'       => 'Festivo',
        'NON_LAVORATO'  => 'Non lavorato',
    ];

    public const DAY_TYPE_ICON = [
        'LAVORO'       => '💼',
        'TRASFERTA'    => '🚗',
        'FERIE'        => '🏖',
        'PERMESSO'     => '📄',
        'MALATTIA'     => '🩺',
        'FESTIVO'      => '🎉',
        'NON_LAVORATO' => '—',
    ];

    public const PAYMENT_STATUS = [
        'DA_EMETTERE' => 'Da emettere',
        'EMESSA'      => 'Emessa',
        'INVIATA'     => 'Inviata',
        'PAGATA'      => 'Pagata',
        'SCADUTA'     => 'Scaduta',
        'CONTESTATA'  => 'Contestata',
    ];

    public const PAYMENT_BADGE = [
        'DA_EMETTERE' => ['slate',   '○'],
        'EMESSA'      => ['violet',  '▤'],
        'INVIATA'     => ['amber',   '◔'],
        'PAGATA'      => ['green',   '€'],
        'SCADUTA'     => ['rose',    '!'],
        'CONTESTATA'  => ['rose',    '✕'],
    ];

    public const CONTRACT_DOC_TYPE = [
        'NDA'      => 'NDA',
        'QUADRO'   => 'Contratto quadro',
        'ORDINE'   => 'Ordine',
        'SOW'      => 'Statement of Work',
        'ADDENDUM' => 'Addendum',
        'ALTRO'    => 'Altro',
    ];

    public const DOC_VISIBILITY = [
        'CONDIVISO'            => 'Condiviso con la controparte',
        'PRIVATO_OFFERENTE'    => 'Solo offerente',
        'PRIVATO_RICHIEDENTE'  => 'Solo richiedente',
        'SOLO_ADMIN'           => 'Solo amministratore',
    ];

    public static function label(array $map, ?string $key): string
    {
        return $map[$key] ?? (string) $key;
    }

    public static function keys(array $map): array
    {
        return array_keys($map);
    }
}
