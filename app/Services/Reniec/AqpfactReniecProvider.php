<?php

namespace App\Services\Reniec;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AqpfactReniecProvider implements ReniecProviderInterface
{
    /** @var string|null */
    private $url;

    /** @var string|null */
    private $token;

    /** @var int|null */
    private $timeoutSeconds;

    /**
     * @param string|null $url
     * @param string|null $token
     * @param int|null $timeoutSeconds
     */
    public function __construct($url, $token, $timeoutSeconds = null)
    {
        $this->url = is_string($url) ? $url : null;
        $this->token = is_string($token) ? $token : null;
        $this->timeoutSeconds = $timeoutSeconds === null ? null : (int) $timeoutSeconds;
    }

    public function consultar(string $dni): ?ReniecPersonData
    {
        if (!$this->configured($this->url) || !$this->configured($this->token)) {
            return null;
        }

        try {
            $response = $this->request()->get($this->url . '/' . $dni);
        } catch (ConnectionException $exception) {
            if ($this->timeoutSeconds === null) {
                throw $exception;
            }

            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $json = $response->json();

        if (!is_array($json) || !isset($json['success']) || $json['success'] === false || !isset($json['data']) || !is_array($json['data'])) {
            return null;
        }

        $data = $json['data'];

        foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento', 'sexo', 'estado_civil', 'direccion', 'numero'] as $key) {
            if (!array_key_exists($key, $data)) {
                return null;
            }
        }

        try {
            $birth = Carbon::createFromFormat('d/m/Y', (string) $data['fecha_nacimiento']);
        } catch (\Throwable $exception) {
            return null;
        }

        if (!$birth instanceof Carbon) {
            return null;
        }

        return new ReniecPersonData(
            (string) $data['nombres'],
            (string) $data['apellido_paterno'],
            (string) $data['apellido_materno'],
            (string) $data['numero'],
            $birth->format('Y-m-d'),
            $this->gender(isset($data['sexo']) ? (string) $data['sexo'] : ''),
            $data['estado_civil'] === null ? null : (string) $data['estado_civil'],
            $data['direccion'] === null ? null : (string) $data['direccion']
        );
    }

    /**
     * @param string $sexo
     */
    private function gender($sexo): ?string
    {
        if ($sexo === 'VARON') {
            return 'HOMBRE';
        }

        if ($sexo === 'MUJER') {
            return 'MUJER';
        }

        return null;
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function request()
    {
        $pending = Http::withToken($this->token)->acceptJson();

        if ($this->timeoutSeconds !== null) {
            $pending = $pending
                ->connectTimeout($this->timeoutSeconds)
                ->timeout($this->timeoutSeconds);
        }

        return $pending;
    }

    /**
     * @param string|null $value
     */
    private function configured($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
