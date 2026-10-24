<?php

namespace App\Http\Controllers\Papeleta;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PapeletaArchivoController extends Controller
{
    public function show(Papeleta $papeleta, string $tipo): StreamedResponse
    {
        $this->authorize('verArchivos', $papeleta);

        $path = match ($tipo) {
            'adjunto-inicial' => $papeleta->adjunto_inicial_path,
            'retorno-foto' => $papeleta->retorno?->foto_path,
            'justificacion-observacion' => $papeleta->observacion_adjunto_path,
            'respuesta-posthoc' => $papeleta->posthoc_adjunto_path,
            default => abort(404),
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404, 'El archivo no existe.');

        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
