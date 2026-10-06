<?php

namespace App\Services\Reniec;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Optional identity assist; never persists provider responses or credentials. */
class FactilizaReniecProvider implements ReniecProviderInterface
{
    public function __construct(private ?string $baseUrl, private ?string $token, private ?int $timeoutSeconds = null) {}

    public function consultar(string $dni): ?ReniecPersonData
    {
        if (!preg_match('/\A[0-9]{8}\z/', $dni) || !$this->token
            || !filter_var($this->baseUrl, FILTER_VALIDATE_URL) || parse_url($this->baseUrl, PHP_URL_SCHEME) !== 'https') {
            return null;
        }
        try {
            $response = Http::withToken($this->token)->acceptJson()->withoutRedirecting()
                ->connectTimeout(2)->timeout(min(5, max(1, $this->timeoutSeconds ?? 5)))
                ->get(rtrim($this->baseUrl, '/').'/dni/info/'.$dni);
        } catch (ConnectionException $exception) {
            return null;
        }
        $json = $response->json();
        if (!$response->successful() || !is_array($json) || ($json['success'] ?? false) !== true) { return null; }
        $data = $json['data'] ?? null;
        if (!is_array($data) || (string) ($data['numero'] ?? '') !== $dni) { return null; }
        foreach (['nombres', 'apellido_paterno'] as $field) {
            if (!is_string($data[$field] ?? null) || trim($data[$field]) === '' || strlen($data[$field]) > 255) { return null; }
        }
        $materno = $data['apellido_materno'] ?? '';
        if (!is_string($materno) || strlen($materno) > 255) { return null; }
        // Deliberately keep only identity names; optional demographics remain manual.
        return new ReniecPersonData(trim($data['nombres']), trim($data['apellido_paterno']), trim($materno), $dni, null, null, null, null);
    }
}
