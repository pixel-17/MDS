<?php

namespace App\Http\Controllers\Trabajador;

use App\Actions\Papeleta\MarcarRetornoAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\MarcarRetornoRequest;
use App\Models\Papeleta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RetornoController extends Controller
{
    public function store(MarcarRetornoRequest $request, Papeleta $papeleta, MarcarRetornoAction $action): RedirectResponse
    {
        $fotoPath = $request->file('foto')->store('papeletas/retornos', 'local');

        try {
            $action->normal($papeleta, Auth::user(), [
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
