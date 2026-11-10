
function soportaBadging() {
    return 'setAppBadge' in navigator;
}

export function actualizarBadgeApp(cantidad) {
    if (!soportaBadging()) {
        return;
    }

    const numero = Number(cantidad) || 0;

    if (numero > 0) {
        navigator.setAppBadge(numero).catch(() => {});
    } else {
        navigator.clearAppBadge().catch(() => {});
    }
}

document.addEventListener('livewire:init', () => {
    Livewire.on('contador-notificaciones-actualizado', ({ cantidad }) => {
        actualizarBadgeApp(cantidad);
    });
});