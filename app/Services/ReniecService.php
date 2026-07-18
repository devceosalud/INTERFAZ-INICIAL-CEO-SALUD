<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ReniecService
{

    protected $token;
    protected $url;

    public function __construct()
    {
        $this->token = config('apidatosperu.aqpfact.token');
        $this->url = config('apidatosperu.aqpfact.url_dni');
    }

    public function consultar($dni)
    {
        $response = Http::withToken($this->token)
            ->acceptJson()
            ->get("{$this->url}/{$dni}");

        if (!$response->successful()) {
            return null;
        }

        $json = $response->json();

        Log::info('RESPUESTA RENIEC', $json);

        if (!isset($json['success']) || $json['success'] === false) {
            return null;
        }

        return [
            'nombre' => $json['data']['nombres'],
            'apellido_paterno' => $json['data']['apellido_paterno'],
            'apellido_materno' => $json['data']['apellido_materno'],
            'fecha_nacimiento' => Carbon::createFromFormat('d/m/Y', $json['data']['fecha_nacimiento'])->format('Y-m-d'),
            'genero' => match ($json['data']['sexo']) {
                'VARON' => 'HOMBRE',
                'MUJER' => 'MUJER',
                default => null,
            },
            'estado_civil' => $json['data']['estado_civil'],
            'direccion' => $json['data']['direccion'],
            'numero_identidad' => $json['data']['numero'],
            'ocupacion' => null,
            'grado_instruccion' => null,
            'telefono' => null,
            'email' => null,
            'channel_id' => null,
            'interaction_medium_id' => null,
            'tipo_identificacion' => 'DNI',
        ];

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


        /*
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
        */
    }
}
