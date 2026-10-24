@props(['columnas'])

<div class="glass-card overflow-x-auto">
    <table class="min-w-full divide-y divide-tinta-100 dark:divide-white/10">
        <thead>
            <tr class="text-left text-xs uppercase text-tinta-500 dark:text-tinta-200">
                @foreach ($columnas as $columna)
                    <th scope="col" class="px-4 py-3">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-tinta-100 dark:divide-white/10">
            {{ $slot }}
        </tbody>
    </table>
</div>
