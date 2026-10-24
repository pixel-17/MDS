<?php

namespace App\Livewire\Concerns;

trait RequiereAdmin
{
    protected function autorizarAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }
}
