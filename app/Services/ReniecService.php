<?php

namespace App\Services;

use Carbon\Carbon;
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
    }
}
