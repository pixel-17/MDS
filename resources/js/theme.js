

const CLAVE = 'mssc-theme';

function sistemaPrefiereOscuro() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function aplicar(modo) {
    const oscuro = modo === 'dark' || (modo === null && sistemaPrefiereOscuro());
    document.documentElement.classList.toggle('dark', oscuro);
}

window.msscThemeToggle = function () {
    return {
        modo: localStorage.getItem(CLAVE),

        init() {
            aplicar(this.modo);

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (this.modo === null) {
                    aplicar(null);
                }
            });
        },

        alternar() {
            this.modo = this.modo === null ? 'light' : (this.modo === 'light' ? 'dark' : null);

            if (this.modo === null) {
                localStorage.removeItem(CLAVE);
            } else {
                localStorage.setItem(CLAVE, this.modo);
            }

            aplicar(this.modo);
        },

        get etiqueta() {
            if (this.modo === 'light') {
                return 'Modo claro (forzado) — clic para oscuro';
            }

            if (this.modo === 'dark') {
                return 'Modo oscuro (forzado) — clic para automático';
            }

            return 'Automático (según el sistema) — clic para forzar claro';
        },
    };
};
