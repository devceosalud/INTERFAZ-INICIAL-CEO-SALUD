<?php

namespace App\Services\Reniec;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * APIS PERU (apis.net.pe) DNI lookup.
 *
 * Documented call: GET {endpoint}?numero={dni} with Authorization: Bearer.
 * The public DNI payload is flat and only supplies names plus the document number.
 */
class ApisPeruReniecProvider implements ReniecProviderInterface
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
            $response = $this->request()->get($this->url, [
                'numero' => $dni,
            ]);
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

        if (!is_array($json)) {
            return null;
        }

        $nombres = $this->text($json['nombres'] ?? null);
        $paterno = $this->text($json['apellidoPaterno'] ?? null);
        $materno = $this->text($json['apellidoMaterno'] ?? null);
        $documento = $this->text($json['numeroDocumento'] ?? null);

        if ($nombres === null || $paterno === null || $materno === null || $documento === null) {
            return null;
        }

        return new ReniecPersonData(
            $nombres,
            $paterno,
            $materno,
            $documento,
            null,
            null,
            null,
            null
        );
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
     * @param mixed $value
     */
    private function text($value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @param string|null $value
     */
    private function configured($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
