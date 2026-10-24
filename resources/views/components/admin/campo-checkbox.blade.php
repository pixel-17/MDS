@props(['label', 'ayuda' => null, 'for' => null])

<label class="flex items-center gap-2">
    <x-checkbox :id="$for" {{ $attributes }} />
    <span class="text-sm">{{ $label }}</span>
</label>

@if ($ayuda)
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $ayuda }}</p>
@endif

@if ($for)
    <x-input-error :for="$for" class="mt-1" />
@endif
