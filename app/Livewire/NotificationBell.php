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
        $notificacion = Auth::user()->notifications()->whereKey($id)->first();

        $notificacion?->markAsRead();
    }

    public function marcarTodasComoLeidas(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        $usuario = Auth::user();

        return view('livewire.notification-bell', [
            'notificaciones' => $usuario->notifications()->latest()->take($this->porMostrar)->get(),
            'noLeidas' => $usuario->unreadNotifications()->count(),
        ]);
    }
}
