<?php

namespace App\Notifications;

use App\Models\Papeleta;

class PapeletaNotification extends NotificacionEnVivo
{
    public function __construct(
        private readonly Papeleta $papeleta,
        string $tipo,
        string $titulo,
        string $mensaje,
        ?string $url = null,
    ) {
        parent::__construct($tipo, $titulo, $mensaje, $url);
    }

    protected function datosExtra(): array
    {
        return ['papeleta_id' => $this->papeleta->id];
    }

    protected function etiquetaPush(): string
    {
        return "papeleta-{$this->papeleta->id}";
    }

    protected function textoAccion(): string
    {
        return 'Ver papeleta';
    }

    protected function datosPush(): array
    {
        return [
            'tipo' => $this->tipo,
            'papeleta_id' => $this->papeleta->id,
            'url' => $this->url,
        ];
    }
}
