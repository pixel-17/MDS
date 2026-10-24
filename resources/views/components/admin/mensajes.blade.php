
@if (request()->hasHeader('X-Livewire'))
    @if (session('error'))
        <x-aviso-toast tipo="error">{{ session('error') }}</x-aviso-toast>
    @elseif (session('mensaje'))
        <x-aviso-toast tipo="success">{{ session('mensaje') }}</x-aviso-toast>
    @endif
@endif
