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
        ])->post('https://api.httpsms.com/v1/messages/send', [
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
        ])->post('https://api.textbee.dev/api/v1/gateway/send-sms', [
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


        /*$ch = curl_init('https://api.textbee.dev/api/v1/gateway/send-sms');

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' .  config('textbee.textbee.key'),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'deviceId' => '6a91ab67f3dc6f0f7b909c4e',
                'recipients' => ['+51924080517'],
                'message' =>  'Cita pendiente a las 11:45 con el dr quiroz ',
            ]),
        ]);

        echo curl_exec($ch); */
    }
}
