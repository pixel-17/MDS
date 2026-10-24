@props(['href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn-primary']) }}>
    <x-icon name="plus-circle" class="size-4" />
    {{ $slot }}
</a>
