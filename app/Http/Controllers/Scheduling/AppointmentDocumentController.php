<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Support\Scheduling\SchedulingCapability as Capability;
use App\Support\Scheduling\AppointmentDocumentAccess as Access;
use App\Services\Scheduling\ReplaceAppointmentDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppointmentDocumentController extends Controller
{
    public function patientIndex(Request $r, int $patientId)
    {
        \App\Models\Patient::findOrFail($patientId);
        $appointments = Appointment::visibleToAgendaUser($r->user()->id)->where('patient_id', $patientId)
            ->whereHas('documents')->with('documents')->orderByDesc('fecha_cita')->limit(100)->get();
        return response()->json(['appointments' => $appointments->map(fn ($a) => ['appointment_id' => $a->id,
            'fecha' => substr($a->fecha_cita, 0, 10), 'documents' => $a->documents->filter(fn($d)=>$this->canReadEvidence($r,$d))->values()->map(fn ($d) => $this->payload($d))])]);
    }
    private function appointment(Request $r, int $id, bool $write = false): Appointment
    {
        $a = Appointment::visibleToAgendaUser($r->user()->id)->whereKey($id)->firstOr(fn () => abort(404));
        if ($write) { abort_unless($r->user()->can(Capability::UPDATE), 403); }
        return $a;
    }
    private function canReadEvidence(Request $r,AppointmentDocument $d):bool {
        return Access::read($r->user(),$d);
    }
    private function payload(AppointmentDocument $d): array
    {
        return ['id' => $d->id, 'type' => $d->type, 'purpose'=>$d->purpose, 'label' => $d->label, 'url' => $d->url,
            'document_kind'=>$d->document_kind??'UNCLASSIFIED','version'=>ReplaceAppointmentDocument::version($d),
            'can_replace'=>Access::write(auth()->user())&&!$d->payment_id&&$d->purpose!=='REEVALUATION_ORDER'
                &&!\App\Models\AppointmentReevaluationIndication::where('evidence_document_id',$d->id)->exists(),
            'payment_id'=>$d->payment_id,'reviewed_at'=>$d->reviewed_at?->toISOString(),
            'review_source'=>$d->receipt_review['source']??null,
            'download_url' => $d->private_path ? route('scheduling.mvp.documents.download', [$d->appointment_id, $d->id]) : null];
    }
    public function index(Request $r, int $appointmentId)
    {
        $a = $this->appointment($r, $appointmentId);
        return response()->json(['documents' => $a->documents->filter(fn($d)=>$this->canReadEvidence($r,$d))->values()->map(fn ($d) => $this->payload($d)), 'can_write' => $r->user()->can(Capability::UPDATE),
            'can_upload_private'=>Access::write($r->user())]);
    }
    public function store(Request $r, int $appointmentId)
    {
        $a = $this->appointment($r, $appointmentId, true);
        abort_unless(Access::write($r->user()),403);
        $data = $r->validate(['label' => 'required|string|max:120', 'url' => 'nullable|url|max:2048|starts_with:https://|required_without:file',
            'file' => ['nullable', 'file', new \App\Rules\PrivateAppointmentFile(), 'max:8192', 'required_without:url'],
            'document_kind'=>['sometimes',\Illuminate\Validation\Rule::in(Access::KINDS)]]);
        abort_if($r->filled('url') && $r->hasFile('file'), 422, 'Adjunta un archivo o un link por documento.');
        $document=null;
        try{$document=\Illuminate\Support\Facades\DB::transaction(function()use($a,$r,$data,&$document){
            $document = $r->hasFile('file')
                ? app(\App\Services\Scheduling\AppointmentProofStorage::class)->store($a,$r->user()->id,$r->file('file'),$data['label'])
                : $a->documents()->create(['type'=>'EXTERNAL_LINK','label'=>$data['label'],'url'=>$data['url'],'actor_user_id'=>$r->user()->id]);
            $document->update(['document_kind'=>$data['document_kind']??'ADMINISTRATIVE']+($r->hasFile('file')?['type'=>'OTHER']:[]));
            app(\App\Services\Scheduling\AppointmentHistory::class)->record($a,'DOCUMENTO_PRIVADO_ADJUNTADO',$r->user()->id,['metadata'=>['document_id'=>$document->id,'document_kind'=>$document->document_kind]]);return $document;
        });}catch(\Throwable $e){if($document?->private_path)Storage::disk('local')->delete($document->private_path);throw $e;}
        return response()->json($this->payload($document), 201);
    }

    public function update(Request $r, int $appointmentId, int $documentId)
    {
        $d = $this->appointment($r, $appointmentId, true)->documents()->whereKey($documentId)->firstOr(fn () => abort(404));
        abort_unless($this->canReadEvidence($r,$d),404);
        if(!$d->payment_id)abort_unless(Access::write($r->user()),403);
        $rules = ['label' => 'required|string|max:120'];
        if ($d->type === 'EXTERNAL_LINK') { $rules['url'] = 'required|url|max:2048|starts_with:https://'; }
        $data=$r->validate($rules);
        return \Illuminate\Support\Facades\DB::transaction(function()use($d,$r,$data){
            $d->update($data);
            app(\App\Services\Scheduling\AppointmentHistory::class)->record($d->appointment,$d->payment_id?'COMPROBANTE_PAGO_ETIQUETADO':'DOCUMENTO_PRIVADO_ETIQUETADO',$r->user()->id,
                ['metadata'=>['document_id'=>$d->id,'payment_id'=>$d->payment_id]]);
            return response()->json($this->payload($d));
        });
    }
    public function destroy(Request $r, int $appointmentId, int $documentId)
    {
        // Soft delete keeps private evidence for retention/review; user cannot download it anymore.
        $d=$this->appointment($r, $appointmentId, true)->documents()->whereKey($documentId)->firstOr(fn () => abort(404));
        abort_unless($this->canReadEvidence($r,$d),404);
        if(!$d->payment_id)abort_unless(Access::write($r->user()),403);
        abort_if($d->purpose==='REEVALUATION_ORDER'||\App\Models\AppointmentReevaluationIndication::where('evidence_document_id',$d->id)->exists(),422,'La evidencia médica acreditada se conserva íntegra.');
        return \Illuminate\Support\Facades\DB::transaction(function()use($d,$r){
            $d->delete();
            app(\App\Services\Scheduling\AppointmentHistory::class)->record($d->appointment,$d->payment_id?'COMPROBANTE_PAGO_OCULTADO':'DOCUMENTO_PRIVADO_OCULTADO',$r->user()->id,
                ['metadata'=>['document_id'=>$d->id,'payment_id'=>$d->payment_id,'private_file_retained'=>true]]);
            return response()->noContent();
        });
    }
    public function download(Request $r, int $appointmentId, int $documentId)
    {
        $d = $this->appointment($r, $appointmentId)->documents()->whereKey($documentId)->firstOr(fn () => abort(404));
        abort_unless($this->canReadEvidence($r,$d) && $d->private_path && Storage::disk('local')->exists($d->private_path), 404);
        return Storage::disk('local')->download($d->private_path, basename($d->private_path),
            ['Content-Type' => $d->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
    public function replace(Request $r,int $appointmentId,int $documentId,ReplaceAppointmentDocument $service){
        abort_unless(Access::write($r->user()),403);
        $data=$r->validate(['request_key'=>'required|uuid','expected_version'=>'required|string|regex:/\A[a-f0-9]{64}\z/',
            'label'=>'required|string|max:120','document_kind'=>['required',\Illuminate\Validation\Rule::in(Access::KINDS)],'reason'=>'required|string|min:3|max:500',
            'file'=>['required','file',new \App\Rules\PrivateAppointmentFile(),'max:8192']]);
        unset($data['file']);return response()->json($this->payload($service->replace($appointmentId,$documentId,$r->user(),$data,$r->file('file'))));
    }
}
