<?php

namespace App\Livewire\Profile;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeviceSettingsForm extends Component
{
    public int $volumen = 80;

    public bool $permiteGps = false;

    public bool $permiteCamara = false;

    public function mount(): void
    {
        $usuario = Auth::user();

        $this->volumen = $usuario->volumen_notificacion;
        $this->permiteGps = $usuario->permite_gps;
        $this->permiteCamara = $usuario->permite_camara;
    }

    public function guardar(): void
    {
        $usuario = Auth::user();

        $usuario->forceFill([
            'volumen_notificacion' => max(0, min(100, $this->volumen)),
            'permite_gps' => $this->permiteGps,
            'permite_camara' => $this->permiteCamara,
        ])->save();

        $this->dispatch('saved');

        $this->dispatch('mssc-volumen-actualizado', volumen: $this->volumen);
    }

    public function render(): View
    {
        return view('profile.device-settings-form');
    }
}
