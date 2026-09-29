<?php

namespace App\Http\Livewire\Concerns;

trait RequiresSchedulingMvpAccess
{
    use RequiresCapability;

    protected function requireSchedulingCapability(string $capability): void
    {
        abort_unless(config('scheduling.enabled', false), 404);

        $this->requireCapability($capability);
    }
}
