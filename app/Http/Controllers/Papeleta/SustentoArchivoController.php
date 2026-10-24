<?php

namespace App\Http\Controllers\Papeleta;

use App\Http\Controllers\Controller;
use App\Models\Sustento;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SustentoArchivoController extends Controller
{
    public function show(Sustento $sustento): StreamedResponse|Response
    {
        $this->authorize('verSustento', $sustento);

        if (! $sustento->archivo_path || ! Storage::disk('local')->exists($sustento->archivo_path)) {
            abort(404, 'El sustento todavía no tiene un archivo presentado.');
        }

        return Storage::disk('local')->response($sustento->archivo_path);
    }
}
