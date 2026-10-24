
@php
    $flash = collect([
        ['tipo' => 'error', 'texto' => session('error')],
        ['tipo' => 'success', 'texto' => session('status') ?? session('success') ?? session('mensaje')],
        ['tipo' => 'warning', 'texto' => session('warning')],
        ['tipo' => 'info', 'texto' => session('info')],
    ])->first(fn ($m) => filled($m['texto']));
@endphp

@if ($flash)
    <x-aviso-toast :tipo="$flash['tipo']">{{ $flash['texto'] }}</x-aviso-toast>
@endif
