<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama\Concerns;

trait MuestraFicha
{
    public ?int $personaId = null;

    public ?int $destinoMover = null;

    public function verPersona(int $id): void
    {
        $this->personaId = $id;
        $this->destinoMover = null;
        $this->resetErrorBag('destinoMover');
    }

    public function cerrarPersona(): void
    {
        $this->personaId = null;
        $this->destinoMover = null;
        $this->resetErrorBag('destinoMover');
    }

    public function moverDesdeFicha(): void
    {
        $this->autorizar();

        $personaId = $this->personaId;
        $destinoId = $this->destinoMover;

        if (! $this->modoEdicion || $personaId === null) {
            return;
        }

        if ($destinoId === null) {
            $this->addError('destinoMover', 'Elige la unidad de destino.');

            return;
        }

        $this->cerrarPersona();
        $this->proponerMovimiento($personaId, $destinoId);
    }
}
