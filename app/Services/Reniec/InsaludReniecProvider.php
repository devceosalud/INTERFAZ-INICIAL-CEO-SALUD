<?php

namespace App\Services\Reniec;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * INSALUD DNI name lookup. The provider response contains names, not
 * a document-number echo; do not treat the data as identity verification.
 * No patient or clinical record is written by this provider.
 */
final class InsaludReniecProvider implements ReniecProviderInterface
{
    private const URL = 'https://api-centralizado.insalud.pe/api/v1/query/dni/sunat';

    public function __construct(private ?int $timeoutSeconds = null) {}

    public function consultar(string $dni): ?ReniecPersonData
    {
        if (!preg_match('/\A[0-9]{8}\z/', $dni)) {
            return null;
        }

        try {
            $response = Http::acceptJson()->withoutRedirecting()
                ->connectTimeout(2)->timeout(min(5, max(1, $this->timeoutSeconds ?? 5)))
                ->get(self::URL, ['document' => $dni, 'type' => 1]);
        } catch (ConnectionException $exception) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $json = $response->json();
        if (!is_array($json) || ($json['success'] ?? false) !== true || !is_array($json['data'] ?? null)) {
            return null;
        }

        $data = $json['data'];
        foreach (['nombres', 'apellido_paterno', 'apellido_materno'] as $key) {
            if (!is_string($data[$key] ?? null)
                || trim($data[$key]) === ''
                || mb_strlen(trim($data[$key])) > 255) {
                return null;
            }
        }

        return new ReniecPersonData(
            trim($data['nombres']),
            trim($data['apellido_paterno']),
            trim($data['apellido_materno']),
            $dni,
            null, null, null, null
        );
    }
}
