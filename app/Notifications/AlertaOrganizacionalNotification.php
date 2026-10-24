<?php

namespace App\Notifications;

class AlertaOrganizacionalNotification extends NotificacionEnVivo
{
    protected function etiquetaPush(): string
    {
        return "alerta-organizacional-{$this->tipo}";
    }

    protected function textoAccion(): string
    {
        return 'Ver';
    }

    protected function datosPush(): array
    {
        return $this->url ? ['url' => $this->url] : [];
    }
}
