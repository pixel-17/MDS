<?php

namespace App\Livewire\Turnos;

use App\Livewire\Concerns\RequiereAdmin;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\EquipoDelJefeService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Programar horarios — administración')]
class ProgramacionAdmin extends Component
{
    use RequiereAdmin;

    private const MAX_RESULTADOS = 15;

    public string $buscar = '';

    #[Locked]
    public ?int $jefeId = null;

    public function mount(): void
    {
        $this->autorizarAdmin();
    }

    public function seleccionarJefe(int $id, EquipoDelJefeService $equipos): void
    {
        $this->autorizarAdmin();

        $jefe = User::findOrFail($id);

        if (! $equipos->idsDeJefes()->contains($jefe->id)) {
            session()->now('error', $jefe->nombre_completo.' no es jefe de nadie: búscalo como trabajador.');

            return;
        }

        $this->jefeId = $jefe->id;
    }

    public function quitarJefe(): void
    {
        $this->autorizarAdmin();

        $this->jefeId = null;
    }

    public function render(EquipoDelJefeService $equipos): View
    {
        $this->autorizarAdmin();

        $palabras = preg_split('/\s+/', trim($this->buscar), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $resultados = collect();

        if ($palabras !== [] && mb_strlen(trim($this->buscar)) >= 2) {
            $consulta = User::with('unidadOrganica')->orderBy('name')->orderBy('apellido');

            foreach ($palabras as $palabra) {
                $consulta->where(fn ($q) => $q
                    ->where('name', 'like', "%{$palabra}%")
                    ->orWhere('apellido', 'like', "%{$palabra}%")
                    ->orWhere('dni', 'like', "%{$palabra}%")
                    ->orWhere('email', 'like', "%{$palabra}%"));
            }

            $resultados = $consulta->limit(self::MAX_RESULTADOS + 1)->get();
        }

        return view('livewire.turnos.programacion-admin', [
            'resultados' => $resultados->take(self::MAX_RESULTADOS),
            'hayMas' => $resultados->count() > self::MAX_RESULTADOS,
            'idsJefes' => $equipos->idsDeJefes()->all(),

            'idsJefesDeArea' => UnidadOrganica::whereNotNull('jefe_id')->whereHas('hijos')->pluck('jefe_id')->map(fn ($id) => (int) $id)->all(),
            'jefe' => $this->jefeId ? User::find($this->jefeId) : null,
        ]);
    }
}
