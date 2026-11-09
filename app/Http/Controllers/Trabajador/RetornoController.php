<?php

namespace App\Http\Controllers\Trabajador;

use App\Actions\Papeleta\MarcarRetornoAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Concerns\ResuelveUsuarioAutenticado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\MarcarRetornoRequest;
use App\Models\Papeleta;
use App\Services\ArchivoSubidaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;


class RetornoController extends Controller
{
    use ResuelveUsuarioAutenticado;

    public function store(MarcarRetornoRequest $request, Papeleta $papeleta, MarcarRetornoAction $action, ArchivoSubidaService $archivos): RedirectResponse
    {
        $fotoPath = $archivos->guardar($request->file('foto'), 'papeletas/retornos');

        if ($fotoPath === false) {
            return back()->with('error', 'No se pudo subir la foto de retorno. Intenta de nuevo.');
        }

        try {
            $action->normal($papeleta, $this->usuarioAutenticado(), [
                'foto_path' => $fotoPath,
                'latitud' => $request->input('latitud'),
                'longitud' => $request->input('longitud'),
            ]);
        } catch (PapeletaException $e) {
            
            Storage::disk('local')->delete($fotoPath);

            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Storage::disk('local')->delete($fotoPath);

            throw $e;
        }

        return redirect()
            ->route('trabajador.papeletas.show', $papeleta)
            ->with('success', 'Retorno registrado.');
    }
}
