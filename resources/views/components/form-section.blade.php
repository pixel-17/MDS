@props(['submit', 'peligro' => false])

<x-seccion :titulo="$title" :descripcion="$description" :peligro="$peligro" {{ $attributes }}>
    <form wire:submit="{{ $submit }}">
        <div class="grid grid-cols-6 gap-x-6 gap-y-5 px-5 py-5 sm:px-6 sm:py-6">
            {{ $form }}
        </div>

        @isset($actions)
            <div class="flex items-center justify-end gap-3 border-t border-tinta-100 bg-tinta-50/60 px-5 py-3 dark:border-white/10 dark:bg-white/5 sm:px-6">
                {{ $actions }}
            </div>
        @endisset
    </form>
</x-seccion>
