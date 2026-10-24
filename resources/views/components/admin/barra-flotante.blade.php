
<div {{ $attributes->merge(['class' => 'sticky bottom-4 z-30 flex items-center justify-end gap-3 rounded-2xl border border-tinta-100 bg-white/90 px-4 py-3 shadow-lg backdrop-blur dark:border-white/10 dark:bg-tinta-950/90']) }}
     style="bottom: max(1rem, env(safe-area-inset-bottom, 0px));">
    {{ $slot }}
</div>
