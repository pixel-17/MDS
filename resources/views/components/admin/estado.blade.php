@props(['activo', 'etiquetaSi' => 'Activo', 'etiquetaNo' => 'Inactivo'])

@if ($activo)
    <span class="badge-tinta bg-registro-100 text-registro-700 ring-registro-500/40 dark:bg-registro-500/20 dark:text-registro-100 dark:ring-registro-500/40">
        <span class="size-1.5 rounded-full bg-registro-500"></span>
        {{ $etiquetaSi }}
    </span>
@else
    <span class="badge-tinta bg-gray-100 text-gray-700 ring-gray-400/40 dark:bg-white/10 dark:text-gray-300 dark:ring-white/15">
        <span class="size-1.5 rounded-full bg-gray-400"></span>
        {{ $etiquetaNo }}
    </span>
@endif
