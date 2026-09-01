<?php

namespace App\Http\Controllers\message;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MessageController extends Controller
{
    //
    public function enviarSms()
    {
        //HTTPSMS
        $response = Http::withHeaders([
            'x-api-key' => config('httpsms.httpsms.key'),
            'Accept' => 'application/json',
        ])->post(config('httpsms.httpsms.url'), [
            'content' => 'Hola, su cita en CEO SALUD ha sido confirmada.',
            'from' => config('httpsms.httpsms.from'),
            'to' => '+51924080517',
        ]);

        dd([
            'status' => $response->status(),
            'body' => $response->json(),
        ]);





        /*$response = Http::withHeaders([
            'x-api-key' => config('textbee.textbee.key'),
        ])->post(env('textbee.textbee.url'), [
            'recipients' => ['+51924080517'],
            'message' => 'Enviado automáticamente desde CEO SALUD.',
            'deviceId' => config('textbee.textbee.device_id'),
        ]);

        if ($response->successful()) {
            $data = $response->json();

            if ($data['data']['success'] ?? false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mensaje enviado a la cola correctamente.',
                    'batch_id' => $data['data']['smsBatchId'],
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Error al enviar SMS.',
            'error' => $response->json(),
        ], $response->status()); */

    }
}
