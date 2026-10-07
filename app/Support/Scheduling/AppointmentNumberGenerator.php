<?php

namespace App\Support\Scheduling;

use Illuminate\Support\Str;

class AppointmentNumberGenerator
{
    /**
     * Preserve the inherited number whenever it is free. A retry receives a short ULID
     * suffix so simultaneous appointments for different doctors cannot share the UNIQUE key.
     */
    public function generate(int $attempt = 1): string
    {
        $base = 'CIT-'.now()->format('YmdHis');

        if ($attempt === 1) {
            return $base;
        }

        return $base.'-'.substr((string) Str::ulid(), -8);
    }
}
