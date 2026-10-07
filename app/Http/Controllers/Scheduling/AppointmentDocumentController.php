<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Support\Scheduling\SchedulingCapability as Capability;
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
            'fecha' => substr($a->fecha_cita, 0, 10), 'documents' => $a->documents->map(fn ($d) => $this->payload($d))])]);
    }
    private function appointment(Request $r, int $id, bool $write = false): Appointment
    {
        $a = Appointment::visibleToAgendaUser($r->user()->id)->whereKey($id)->firstOr(fn () => abort(404));
        if ($write) { abort_unless($r->user()->can(Capability::UPDATE)
            || ($r->user()->can(Capability::CREATE) && (int) ($a->responsible_user_id ?? $a->user_id) === (int) $r->user()->id), 403); }
        return $a;
    }
    private function payload(AppointmentDocument $d): array
    {
        return ['id' => $d->id, 'type' => $d->type, 'label' => $d->label, 'url' => $d->url,
            'download_url' => $d->private_path ? route('scheduling.mvp.documents.download', [$d->appointment_id, $d->id]) : null];
    }
    public function index(Request $r, int $appointmentId)
    {
        $a = $this->appointment($r, $appointmentId);
        return response()->json(['documents' => $a->documents->map(fn ($d) => $this->payload($d)), 'can_write' => $r->user()->can(Capability::UPDATE)
            || ($r->user()->can(Capability::CREATE) && (int) ($a->responsible_user_id ?? $a->user_id) === (int) $r->user()->id)]);
    }
    public function store(Request $r, int $appointmentId)
    {
        $a = $this->appointment($r, $appointmentId, true);
        $data = $r->validate(['label' => 'required|string|max:120', 'url' => 'nullable|url|max:2048|starts_with:https://|required_without:file',
            'file' => ['nullable', 'file', new \App\Rules\PrivateAppointmentFile(), 'max:8192', 'required_without:url']]);
        abort_if($r->filled('url') && $r->hasFile('file'), 422, 'Adjunta un archivo o un link por documento.');
        $document = $r->hasFile('file')
            ? app(\App\Services\Scheduling\AppointmentProofStorage::class)->store($a, $r->user()->id, $r->file('file'), $data['label'])
            : $a->documents()->create(['type' => 'EXTERNAL_LINK', 'label' => $data['label'], 'url' => $data['url'], 'actor_user_id' => $r->user()->id]);
        return response()->json($this->payload($document), 201);
    }

    public function update(Request $r, int $appointmentId, int $documentId)
    {
        $d = $this->appointment($r, $appointmentId, true)->documents()->whereKey($documentId)->firstOr(fn () => abort(404));
        $rules = ['label' => 'required|string|max:120'];
        if ($d->type === 'EXTERNAL_LINK') { $rules['url'] = 'required|url|max:2048|starts_with:https://'; }
        $d->update($r->validate($rules)); return response()->json($this->payload($d));
    }
    public function destroy(Request $r, int $appointmentId, int $documentId)
    {
        // Soft delete keeps private evidence for retention/review; user cannot download it anymore.
        $this->appointment($r, $appointmentId, true)->documents()->whereKey($documentId)->firstOr(fn () => abort(404))->delete(); return response()->noContent();
    }
    public function download(Request $r, int $appointmentId, int $documentId)
    {
        $d = $this->appointment($r, $appointmentId)->documents()->whereKey($documentId)->firstOr(fn () => abort(404));
        abort_unless($d->private_path && Storage::disk('local')->exists($d->private_path), 404);
        return Storage::disk('local')->download($d->private_path, basename($d->private_path),
            ['Content-Type' => $d->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
