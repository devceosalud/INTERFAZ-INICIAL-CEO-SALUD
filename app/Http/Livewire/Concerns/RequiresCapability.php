<?php

namespace App\Http\Livewire\Concerns;

trait RequiresCapability
{
    protected function requireCapability(string $capability): void
    {
        abort_unless(
            auth()->check() && auth()->user()->can($capability),
            403,
            'This action is unauthorized.'
        );
    }
}
