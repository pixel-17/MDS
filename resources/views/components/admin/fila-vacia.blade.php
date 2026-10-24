@props(['colspan'])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-sm text-tinta-500 dark:text-tinta-200">
        <x-icon name="inbox-empty" class="size-8 mx-auto mb-2 text-tinta-300 dark:text-tinta-500" />
        {{ $slot }}
    </td>
</tr>
