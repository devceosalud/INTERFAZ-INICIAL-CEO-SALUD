<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
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

    /**
     * The inherited caller keeps the previous behavior when no timeout is given.
     * Agenda passes a short timeout so a slow provider becomes a manual registration
     * instead of blocking the screen. A connection failure then returns null.
     */
    public function consultar($dni, $timeoutSeconds = null)
    {
        $pending = Http::withToken($this->token)->acceptJson();

        if ($timeoutSeconds !== null) {
            $pending = $pending
                ->connectTimeout((int) $timeoutSeconds)
                ->timeout((int) $timeoutSeconds);
        }

        try {
            $response = $pending->get("{$this->url}/{$dni}");
        } catch (ConnectionException $exception) {
            if ($timeoutSeconds === null) {
                throw $exception;
            }

            return null;
        }

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
