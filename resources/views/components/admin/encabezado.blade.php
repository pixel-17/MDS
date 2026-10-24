@props(['titulo'])

<div class="flex items-center justify-between gap-4">
    <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
        {{ $titulo }}
    </h2>

    @if ($slot->isNotEmpty())
        <div class="flex items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
