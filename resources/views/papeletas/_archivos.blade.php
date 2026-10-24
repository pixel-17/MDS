
@php
    $archivosPapeleta = array_filter([
        'adjunto-inicial' => $papeleta->adjunto_inicial_path ? ['Adjunto inicial', 'paper-clip'] : null,
        'retorno-foto' => $papeleta->retorno?->foto_path ? ['Foto del retorno', 'photo'] : null,
        'justificacion-observacion' => $papeleta->observacion_adjunto_path ? ['Adjunto de la respuesta a la observación', 'document'] : null,
    ]);
@endphp

@if ($archivosPapeleta && Auth::user()->can('verArchivos', $papeleta))
    <div class="glass-card p-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-3">Archivos adjuntos</h3>
        <div class="flex flex-wrap gap-2">
            @foreach ($archivosPapeleta as $tipoArchivo => [$etiquetaArchivo, $iconoArchivo])
                <a href="{{ route('papeletas.archivo', ['papeleta' => $papeleta, 'tipo' => $tipoArchivo]) }}"
                   class="btn-row">
                    <x-icon name="{{ $iconoArchivo }}" class="size-3.5" />
                    {{ $etiquetaArchivo }}
                </a>
            @endforeach
        </div>
    </div>
@endif