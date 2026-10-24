<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

abstract class NotificacionEnVivo extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function __construct(
        protected readonly string $tipo,
        protected readonly string $titulo,
        protected readonly string $mensaje,
        protected readonly ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class, 'broadcast'];
    }

    protected function datosExtra(): array
    {
        return [];
    }

    abstract protected function etiquetaPush(): string;

    abstract protected function textoAccion(): string;

    abstract protected function datosPush(): array;

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'tipo' => $this->tipo,
            ...$this->datosExtra(),
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            ...$this->payload(),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $mensaje = (new WebPushMessage)
            ->title($this->titulo)
            ->body($this->mensaje)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag($this->etiquetaPush());

        $datos = $this->datosPush();

        if ($datos !== []) {
            $mensaje->data($datos);
        }

        if ($this->url) {
            $mensaje->action($this->textoAccion(), 'ver');
        }

        return $mensaje;
    }
}
