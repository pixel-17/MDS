<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait EscuchaNotificacionesEnVivo
{
    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $id = Auth::id();

        if ($this->escuchaCambiosDeEstado()) {
            return [
                "echo-private:App.Models.User.{$id},PapeletaActualizada" => 'refrescarPorCambioDeEstado',
            ];
        }

        return [
            "echo-notification:App.Models.User.{$id}" => 'refrescarPorNotificacionEnVivo',
        ];
    }

    protected function escuchaCambiosDeEstado(): bool
    {
        return true;
    }

    public function refrescarPorCambioDeEstado(): void {}

    public function refrescarPorNotificacionEnVivo(): void
    {

        $this->dispatch('notificacion-sonido');
    }
}
