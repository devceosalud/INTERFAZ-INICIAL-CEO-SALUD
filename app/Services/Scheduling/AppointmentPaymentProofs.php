<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Receipt evidence accompanies a manually authorized payment; it never creates one. */
final class AppointmentPaymentProofs
{
    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024;
    public static function rules(): array
    {
        return [
            'proof' => ['nullable','file','prohibits:proofs',new \App\Rules\PrivateAppointmentFile(),'max:8192'],
            'proofs' => 'nullable|array|max:5',
            'proofs.*' => ['required','file',new \App\Rules\PrivateAppointmentFile(),'max:8192'],
            'proof_review' => 'nullable|array:reviewed,source,date,time',
            'proof_review.reviewed' => 'nullable|required_with:proof,proofs|accepted_if:proof_review.source,MANUAL,BROWSER_OCR',
            'proof_review.source' => 'required_with:proof,proofs|in:MANUAL,BROWSER_OCR',
            'proof_review.date' => 'nullable|date_format:Y-m-d',
            'proof_review.time' => 'nullable|date_format:H:i',
        ];
    }
    public static function messages(): array
    {
        return ['proof_review.reviewed.required_with'=>'Confirma la revisión humana de todos los comprobantes antes de registrar.',
            'proof_review.reviewed.accepted_if'=>'Confirma la revisión humana de todos los comprobantes antes de registrar.',
            'proof_review.source.required_with'=>'Indica si la revisión fue manual o usó sugerencias OCR.',
            'proofs.max'=>'Adjunta como máximo cinco comprobantes por pago.'];
    }
    public static function files(UploadedFile|array|null $input, array $data): array
    {
        $files=$input instanceof UploadedFile?[$input]:($input??[]);
        if (!$files) return [];
        Validator::make(['proofs'=>$files,'proof_review'=>$data['proof_review']??null],self::rules(),self::messages())->validate();
        if (array_sum(array_map(fn($f)=>(int)$f->getSize(),$files))>self::MAX_TOTAL_BYTES) {
            throw ValidationException::withMessages(['proofs'=>'El conjunto de comprobantes excede 20 MB.']);
        }
        return $files;
    }
    public static function fingerprint(array $files): string
    {
        return implode(':',array_map(fn(UploadedFile $file)=>hash_file('sha256',$file->getRealPath()),$files));
    }
    public function store(Appointment $appointment, Payment $payment, int $actor, array $files, array $review, array &$paths): void
    {
        abort_unless((int)$payment->user_id===$actor && (float)$payment->monto>0,403);
        $ids=[];
        foreach ($files as $file) {
            $doc=app(AppointmentProofStorage::class)->storePaymentProof($appointment,$actor,$file,$payment,$review);
            $paths[]=$doc->private_path; $ids[]=$doc->id;
        }
        if ($ids) app(AppointmentHistory::class)->record($appointment,'COMPROBANTES_PAGO_REVISADOS',$actor,
            ['metadata'=>['payment_id'=>$payment->id,'document_ids'=>$ids,'source'=>$review['source'],
                'human_reviewed'=>true,'bank_validation'=>false]]);
    }
}
