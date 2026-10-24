<?php

namespace App\Http\Controllers\Trabajador;

use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\SubirSustentoRequest;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\States\Papeleta\EnJustificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SustentoController extends Controller
{
    public function store(SubirSustentoRequest $request, Sustento $sustento): RedirectResponse
    {
        if ($sustento->estado !== 'pendiente') {
            return back()->with('error', 'Este sustento ya fue presentado o revisado.');
        }

        if (! $sustento->papeleta->estado->equals(EnJustificacion::class)) {
            return back()->with('error', 'Esta papeleta ya no admite justificación.');
        }

        $archivoPath = $request->file('archivo')->store('papeletas/sustentos', 'local');

        try {
            DB::transaction(function () use ($sustento, $archivoPath) {

                $papeleta = Papeleta::whereKey($sustento->papeleta_id)->lockForUpdate()->firstOrFail();

                if (! $papeleta->estado->equals(EnJustificacion::class)) {
                    throw new PapeletaException('Esta papeleta ya no admite justificación.');
                }

                /** @var Sustento $actual */
                $actual = Sustento::whereKey($sustento->id)->lockForUpdate()->firstOrFail();

                if ($actual->estado !== 'pendiente') {
                    throw new PapeletaException('Este sustento ya fue presentado o revisado.');
                }

                $actual->update([
                    'archivo_path' => $archivoPath,
                    'estado' => 'presentado',
                    'presentado_at' => now(),
                ]);
            });
        } catch (PapeletaException $e) {

            Storage::disk('local')->delete($archivoPath);

            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Storage::disk('local')->delete($archivoPath);

            throw $e;
        }

        return redirect()
            ->route('trabajador.papeletas.show', $sustento->papeleta_id)
            ->with('success', 'Sustento presentado, queda a la espera de revisión.');
    }
}
