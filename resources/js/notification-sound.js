

let contextoAudio = null;

function obtenerContexto() {
    if (!contextoAudio) {
        const AudioContextCtor = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextCtor) {
            return null;
        }

        contextoAudio = new AudioContextCtor();
    }

    return contextoAudio;
}

function tono(ctx, salida, momento, frecuencia, duracion, tipo, volumenPico) {
    const oscilador = ctx.createOscillator();
    const ganancia = ctx.createGain();

    oscilador.type = tipo;
    oscilador.frequency.setValueAtTime(frecuencia, momento);

    ganancia.gain.setValueAtTime(0, momento);
    ganancia.gain.linearRampToValueAtTime(volumenPico, momento + 0.002);
    ganancia.gain.exponentialRampToValueAtTime(0.0001, momento + duracion);

    oscilador.connect(ganancia).connect(salida);
    oscilador.start(momento);
    oscilador.stop(momento + duracion + 0.02);

    return ganancia;
}

export function reproducirSonidoNotificacion(volumenPorcentaje) {
    const ctx = obtenerContexto();

    if (!ctx) {
        return;
    }

    if (ctx.state === 'suspended') {
        ctx.resume();
    }

    const porcentaje = volumenPorcentaje ?? window.MSSC_VOLUMEN_NOTIFICACION ?? 80;
    const factor = Math.max(0, Math.min(100, Number(porcentaje))) / 100;

    if (factor === 0) {
        return;
    }

    const filtro = ctx.createBiquadFilter();
    filtro.type = 'highpass';
    filtro.frequency.value = 180;
    filtro.connect(ctx.destination);

    const inicio = ctx.currentTime;

    // Whoosh: barrido C4 -> C5.
    const oscilador = ctx.createOscillator();
    const ganancia = ctx.createGain();
    oscilador.type = 'sine';
    oscilador.frequency.setValueAtTime(261.63, inicio);
    oscilador.frequency.exponentialRampToValueAtTime(523.25, inicio + 0.15);
    ganancia.gain.setValueAtTime(0.001, inicio);
    ganancia.gain.linearRampToValueAtTime(0.22 * factor, inicio + 0.03);
    ganancia.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.18);
    oscilador.connect(ganancia).connect(filtro);
    oscilador.start(inicio);
    oscilador.stop(inicio + 0.2);

    tono(ctx, filtro, inicio + 0.18, 1046.5, 0.15, 'triangle', 0.3 * factor);
}

window.msscReproducirSonidoNotificacion = reproducirSonidoNotificacion;

document.addEventListener('livewire:init', () => {
    Livewire.on('notificacion-sonido', () => {
        reproducirSonidoNotificacion();
    });
});