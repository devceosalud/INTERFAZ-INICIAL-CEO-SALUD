<?php
namespace App\Support\Scheduling;

final class ClinicalCapability
{
    public const ROLE = 'MEDICO';
    public const VIEW = 'appointment.clinical.view';
    public const START = 'appointment.clinical.start';
    public const FINISH = 'appointment.clinical.finish';
    public const INDICATE = 'appointment.clinical.reevaluation.indicate';
    public const WITHDRAW = 'appointment.clinical.reevaluation.withdraw';
    public const PROCEDURES = 'appointment.clinical.procedures.request';

    public static function all(): array
    {
        return [self::VIEW,self::START,self::FINISH,self::INDICATE,self::WITHDRAW,self::PROCEDURES];
    }
}
