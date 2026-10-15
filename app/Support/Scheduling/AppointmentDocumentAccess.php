<?php
namespace App\Support\Scheduling;
use App\Models\AppointmentDocument;
use App\Models\User;
use App\Services\Scheduling\ReevaluationIndicationService;
final class AppointmentDocumentAccess
{
    public const VIEW='appointment.documents.view';
    public const MANAGE='appointment.documents.manage';
    public const KINDS=['PRESCRIPTION','REPORT','ADMINISTRATIVE'];
    public static function read(User $actor,AppointmentDocument $doc):bool{
        if($doc->purpose==='REEVALUATION_ORDER'||\App\Models\AppointmentReevaluationIndication::where('evidence_document_id',$doc->id)->exists())
            return $actor->can(ReevaluationIndicationService::CAPABILITY);
        // Ledger-linked reviewed payment proofs retain ordinary appointment visibility.
        if($doc->payment_id)return true;
        // No automatic assumption that an unclassified inherited file/link is non-medical.
        return $actor->can(self::VIEW);
    }
    public static function write(User $actor):bool{return $actor->can(self::VIEW)&&$actor->can(self::MANAGE)&&$actor->can(SchedulingCapability::UPDATE);}
}
