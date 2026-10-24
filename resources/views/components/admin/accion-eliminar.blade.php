

@props(['mensaje', 'accion'])

<button
    type="button"
    x-on:click="mssConfirmar(@js($mensaje), { peligro: true }).then((ok) => ok && $wire.{{ $accion }})"
    {{ $attributes->merge(['class' => 'btn-row-danger']) }}
>
    <x-icon name="trash" class="size-3.5" />
    {{ $slot->isEmpty() ? 'Eliminar' : $slot }}
</button>