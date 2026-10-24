<?php

namespace App\Http\Controllers\Trabajador;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\Finalizada;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PdfController extends Controller
{
    public function papeleta(Papeleta $papeleta): Response
    {
        $this->authorize('view', $papeleta);

        $puedeDescargar = $papeleta->estado->equals(Cerrada::class)
            || $papeleta->estado->equals(Finalizada::class);

        abort_unless($puedeDescargar, 403, 'El PDF solo está disponible cuando la papeleta ya fue cerrada o finalizada.');

        $papeleta->load(['motivo', 'sede', 'retorno', 'trabajador', 'resueltoPorJefe', 'resueltoPorRrhh']);

        return Pdf::loadView('pdf.trabajador.papeleta', ['papeleta' => $papeleta])
            ->download('papeleta-'.str_pad((string) $papeleta->id, 4, '0', STR_PAD_LEFT).'.pdf');
    }
}
