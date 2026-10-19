<?php
namespace App\Support\Scheduling;
use App\Models\AppointmentDocument;
use App\Models\User;
use App\Services\Scheduling\ReevaluationIndicationService;
final class AppointmentDocumentAccess
{
    public const VIEW='appointment.documents.view';
    public const MANAGE='appointment.documents.manage';
    public const PATIENT_PURPOSE = 'PATIENT_PROVIDED';
    public const KINDS=['PRESCRIPTION','REPORT','ADMINISTRATIVE'];
    public static function read(User $actor,AppointmentDocument $doc):bool{
        if($doc->purpose==='REEVALUATION_ORDER'||\App\Models\AppointmentReevaluationIndication::where('evidence_document_id',$doc->id)->exists())
            return $actor->can(ReevaluationIndicationService::CAPABILITY);
        if($doc->purpose===self::PATIENT_PURPOSE)return self::patientRead($actor);
        // Ledger-linked reviewed payment proofs retain ordinary appointment visibility.
        if($doc->payment_id)return true;
        // No automatic assumption that an unclassified inherited file/link is non-medical.
        return $actor->can(self::VIEW);
    }
    public static function patientRead(User $actor): bool {
        return $actor->is_active && $actor->can(SchedulingCapability::MVP_ACCESS) && $actor->can(SchedulingCapability::VIEW)
            && ($actor->hasAnyRole(['ADMINISTRADOR','COMERCIAL']) || $actor->can(self::VIEW));
    }
    public static function patientReplace(User $actor): bool {
        return self::patientUpload($actor) && ($actor->hasRole('ADMINISTRADOR') || self::write($actor));
    }
    public static function patientUpload(User $actor): bool {
        return self::patientRead($actor) && ($actor->hasAnyRole(['ADMINISTRADOR','COMERCIAL']) || self::write($actor));
    }
    public static function write(User $actor):bool{return $actor->can(self::VIEW)&&$actor->can(self::MANAGE)&&$actor->can(SchedulingCapability::UPDATE);}
}
