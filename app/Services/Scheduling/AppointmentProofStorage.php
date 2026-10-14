<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentProofStorage
{
    public function store(Appointment $appointment, int $actor, UploadedFile $file, string $label='Comprobante de pago'): AppointmentDocument
    {
        return $this->persist($appointment,$actor,$file,$label,[]);
    }
    public function storePaymentProof(Appointment $appointment, int $actor, UploadedFile $file, Payment $payment, array $review): AppointmentDocument
    {
        $hash=hash_file('sha256',$file->getRealPath());
        if (AppointmentDocument::withTrashed()->where('payment_proof_hash',$hash)->exists()) throw $this->duplicate();
        try {
            return $this->persist($appointment,$actor,$file,'Comprobante del pago #'.$payment->id,[
                'payment_id'=>$payment->id,'payment_proof_hash'=>$hash,'reviewed_by_user_id'=>$actor,'reviewed_at'=>now(),
                'receipt_review'=>['source'=>$review['source'],'date'=>$review['date']??null,'time'=>$review['time']??null,
                    'human_reviewed'=>true,'bank_validated'=>false],
            ]);
        } catch (QueryException $e) {
            $driver=$appointment->getConnection()->getDriverName();
            $duplicate=($driver==='mysql' && ($e->errorInfo[1]??null)===1062)
                || ($driver==='sqlite' && ($e->errorInfo[1]??null)===19);
            if ($duplicate && (str_contains($e->getMessage(),'appointment_docs_proof_hash_unique')
                || str_contains($e->getMessage(),'appointment_documents.payment_proof_hash'))) throw $this->duplicate();
            throw $e;
        }
    }
    private function duplicate(): ValidationException
    {
        return ValidationException::withMessages(['proofs'=>'Este comprobante ya fue registrado. Revisa la operación antes de cobrar; no se registró otro pago.']);
    }
    private function persist(Appointment $appointment,int $actor,UploadedFile $file,string $label,array $extra): AppointmentDocument
    {
        Validator::make(['file'=>$file],['file'=>['required','file',new \App\Rules\PrivateAppointmentFile(),'max:8192']])->validate();
        $mime=\App\Rules\PrivateAppointmentFile::mime($file);
        $extension=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'][$mime];
        $path=$file->storeAs('appointment-documents',Str::uuid().'.'.$extension,'local');
        if (!$path) throw new \RuntimeException('No se pudo almacenar el comprobante.');
        try {
            return $appointment->documents()->create($extra+['type'=>'PAYMENT_PROOF','label'=>$label,'private_path'=>$path,
                'mime'=>$mime,'size'=>$file->getSize(),'actor_user_id'=>$actor]);
        } catch (\Throwable $error) {Storage::disk('local')->delete($path);throw $error;}
    }
}
