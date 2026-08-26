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
        // Nueva URL global de Textbee (No requiere ID en la ruta)
        //env('TEXTBEE_API_KEY')
        $response = Http::withHeaders([
            'x-api-key' => 'txb_nL3ee9awVFWvTHrtQVjJHc1tLL2WTH8j',
        ])->post('https://api.textbee.dev/api/v1/gateway/send-sms', [
            'recipients' => ['+51924080517'],
            'message' => 'Enviado automáticamente con la API moderna de Textbee.',
        ]);

        if ($response->successful()) {
            return "Mensaje enviado exitosamente.";
        }

        return "Error: " . $response->body();
    }
}
