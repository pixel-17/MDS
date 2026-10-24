@props(['label', 'for', 'ayuda' => null, 'campo' => null])

<div {{ $attributes }}>
    <x-label :for="$for" :value="$label" class="block mb-1" />

    {{ $slot }}

    @if ($ayuda)
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $ayuda }}</p>
    @endif

    <x-input-error :for="$campo ?? $for" class="mt-2" />
</div>