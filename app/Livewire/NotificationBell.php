<?php

namespace App\Livewire;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;


class NotificationBell extends Component
{
    use EscuchaNotificacionesEnVivo;

    public int $porMostrar = 8;

    
    protected function escuchaCambiosDeEstado(): bool
    {
        return false;
    }

    public function marcarComoLeida(string $id): void
    {
        /** @var \App\Models\User $usuario */
        $usuario = Auth::user();

        $notificacion = $usuario->notifications()->whereKey($id)->first();

        $notificacion?->markAsRead();
    }

    public function marcarTodasComoLeidas(): void
    {
        /** @var \App\Models\User $usuario */
        $usuario = Auth::user();

        $usuario->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        /** @var \App\Models\User $usuario */
        $usuario = Auth::user();

        return view('livewire.notification-bell', [
            'notificaciones' => $usuario->notifications()->latest()->take($this->porMostrar)->get(),
            'noLeidas' => $usuario->unreadNotifications()->count(),
        ]);
    }
}
