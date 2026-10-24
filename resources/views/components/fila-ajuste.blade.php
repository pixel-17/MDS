@props(['titulo', 'descripcion' => null])

<div {{ $attributes->class('py-4 first:pt-0 last:pb-0') }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-tinta-950 dark:text-white">{{ $titulo }}</p>
            @if ($descripcion)
                <p class="mt-0.5 text-sm text-tinta-600 dark:text-zinc-400">{{ $descripcion }}</p>
            @endif
        </div>

        @if ($slot->isNotEmpty())
            <div class="shrink-0">{{ $slot }}</div>
        @endif
    </div>

    @isset($detalle)
        <div class="mt-3">{{ $detalle }}</div>
    @endisset
</div>
