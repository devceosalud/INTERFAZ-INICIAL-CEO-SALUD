<?php

namespace App\Http\Controllers\Api\patient;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class PatientController extends Controller
{
    //
    public function show(Request $request)
    {
        $patient = Patient::where('numero_identidad', $request->numero_identidad)->first();
        if (!$patient) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'patient' => $patient
            ], 200);
        }
    }

    public function search(Request $request)
    {
        $patient = Patient::find($request->id);
        if (!$patient) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'patient' => $patient
            ], 200);
        }
    }


    public function reniec()
    {
        /*
        $token = 'apis-token-1.aTSI1U7KEuT-6bbbCguH-4Y8TI6KS73N';
        $numero = '76395743';
        $client = new Client(['base_uri' => 'https://api.apis.net.pe', 'verify' => true]);
        $parameters = [
            'http_errors' => true,
            'connect_timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Referer' => 'https://apis.net.pe/api-consulta-dni',
                'User-Agent' => 'laravel/guzzle',
                'Accept' => 'application/json',
            ],
            'query' => ['numero' => $numero]
        ];
        $res = $client->request('GET', '/v2/renec/dni', $parameters);
        $response = json_decode($res->getBody()->getContents(), true);
        var_dump($response);
        */


        // Datos
        $token = 'sk_17382.aT6kSUYt4nk43Bd9izc3tTvwDMC1ipW8';
        $dni = '45501816';

        // Iniciar llamada a API
        $curl = curl_init();

        // Buscar dni
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.decolecta.com/v1/reniec/dni?numero=' . $dni,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Referer: https://apis.net.pe/consulta-dni-api',
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        // Datos listos para usar
        $persona = json_decode($response);
        var_dump($persona);
    }
}
