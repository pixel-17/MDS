<?php

namespace App\Livewire\Navegacion;

use App\Models\Papeleta;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class InsigniaBandeja extends Component
{

    #[Locked]
    public string $bandeja = 'jefe';

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $id = Auth::id();

        return [
            "echo-notification:App.Models.User.{$id}" => '$refresh',
            "echo-private:App.Models.User.{$id},PapeletaActualizada" => '$refresh',
        ];
    }

    public function render(): View
    {
        return view('livewire.navegacion.insignia-bandeja', [
            'cantidad' => $this->contar(),
        ]);
    }

    private function contar(): int
    {
        $usuario = Auth::user();

        if (! $usuario) {
            return 0;
        }

        return match ($this->bandeja) {

            'jefe' => Papeleta::where(fn ($raiz) => $raiz
                ->where(fn ($propias) => $propias
                    ->deJefeInmediato($usuario)
                    ->where(function ($query) {
                        $query->whereState('estado', PendienteJefe::class)
                            ->orWhereState('estado', ObservadaPorRrhh::class);
                    }))

                ->orWhere(fn ($posthoc) => $posthoc
                    ->where('autorizado_con_rrhh_fuera_horario', true)
                    ->where('revision_posthoc_estado', 'observada')
                    ->where('resuelto_por_jefe_id', $usuario->id)))
                ->count(),

            'rrhh' => $usuario->hasRole('rrhh')
                ? Papeleta::whereState('estado', PendienteRrhh::class)
                    ->orWhere(fn ($q) => $q
                        ->where('autorizado_con_rrhh_fuera_horario', true)
                        ->whereIn('revision_posthoc_estado', ['pendiente', 'respondida']))
                    ->count()
                : 0,
            default => 0,
        };
    }
}
