<?php

namespace App\Http\Livewire\Concerns;

trait RequiresRole
{
    protected function requireRole(string ...$roles): void
    {
        abort_unless(
            auth()->check() && auth()->user()->hasAnyRole($roles),
            403,
            'This action is unauthorized.'
        );
    }
}

