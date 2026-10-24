
const MOSTRAR_TRAS_MS = 250;

let enVuelo = 0;
let temporizador = null;

function pintar() {
    const raiz = document.documentElement;

    if (enVuelo > 0) {
        if (temporizador === null && ! raiz.classList.contains('lw-cargando')) {
            temporizador = setTimeout(() => {
                temporizador = null;
                raiz.classList.add('lw-cargando');
                raiz.setAttribute('aria-busy', 'true');
            }, MOSTRAR_TRAS_MS);
        }

        return;
    }

    clearTimeout(temporizador);
    temporizador = null;
    raiz.classList.remove('lw-cargando');
    raiz.removeAttribute('aria-busy');
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ respond, succeed, fail }) => {
        enVuelo++;
        pintar();

        let cerrado = false;
        const terminar = () => {
            if (cerrado) return;
            cerrado = true;
            enVuelo = Math.max(0, enVuelo - 1);
            pintar();
        };

        respond(terminar);
        succeed(terminar);
        fail(terminar);
    });
});
