

<a {{ $attributes->merge(['class' => 'btn-row']) }}>
    <x-icon name="pencil" class="size-3.5" />
    {{ $slot->isEmpty() ? 'Editar' : $slot }}
</a>
