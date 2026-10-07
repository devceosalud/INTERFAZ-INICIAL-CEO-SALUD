<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\AppointmentDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AppointmentProofStorage
{
    public function store(Appointment $appointment, int $actor, UploadedFile $file, string $label = 'Comprobante de pago'): AppointmentDocument
    {
        Validator::make(['file' => $file], ['file' => ['required', 'file', new \App\Rules\PrivateAppointmentFile(), 'max:8192']])->validate();
        $mime = \App\Rules\PrivateAppointmentFile::mime($file);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime];
        $path = $file->storeAs('appointment-documents', Str::uuid().'.'.$extension, 'local');
        if (!$path) { throw new \RuntimeException('No se pudo almacenar el comprobante.'); }
        try {
            return $appointment->documents()->create(['type' => 'PAYMENT_PROOF', 'label' => $label, 'private_path' => $path,
                'mime' => $mime, 'size' => $file->getSize(), 'actor_user_id' => $actor]);
        } catch (\Throwable $error) { Storage::disk('local')->delete($path); throw $error; }
    }
}
