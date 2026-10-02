<?php

namespace App\Services;

use App\Services\Reniec\ApisPeruReniecProvider;
use App\Services\Reniec\AqpfactReniecProvider;
use App\Services\Reniec\ReniecProviderInterface;

class ReniecService
{
    /**
     * The inherited caller keeps the previous behavior when no timeout is given.
     * Agenda passes a short timeout so a slow provider becomes a manual registration
     * instead of blocking the screen. A connection failure then returns null.
     *
     * @param mixed $dni
     * @param int|null $timeoutSeconds
     * @return array<string, mixed>|null
     */
    public function consultar($dni, $timeoutSeconds = null)
    {
        $provider = $this->provider($timeoutSeconds);

        if ($provider === null) {
            return null;
        }

        $person = $provider->consultar(trim((string) $dni));

        if ($person === null) {
            return null;
        }

        return $person->toLookupArray();
    }

    /**
     * @param int|null $timeoutSeconds
     */
    private function provider($timeoutSeconds): ?ReniecProviderInterface
    {
        $selected = config('apidatosperu.reniec_provider');

        if ($selected === 'aqpfact') {
            return new AqpfactReniecProvider(
                config('apidatosperu.aqpfact.url_dni'),
                config('apidatosperu.aqpfact.token'),
                $timeoutSeconds
            );
        }

        if ($selected === 'apisperu') {
            return new ApisPeruReniecProvider(
                config('apidatosperu.apisperu.dni_url'),
                config('apidatosperu.apisperu.dni_token'),
                $timeoutSeconds
            );
        }

        return null;
    }
}
