@props(['label', 'for'])

<div class="max-w-xs flex-1 min-w-[11rem]">
    <x-label :for="$for" :value="$label" class="block mb-1" />

    {{ $slot }}
</div>
