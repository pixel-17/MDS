

window.msscGpsToggle = function () {
    return {
        estado: 'Consultando permiso…',

        async init() {
            if (!('geolocation' in navigator)) {
                this.estado = 'Este navegador no soporta ubicación.';
                return;
            }

            await this.consultar();
        },

        async consultar() {
            if (!('permissions' in navigator)) {
                this.estado = 'Pulsa "solicitar permiso" para activarlo.';
                return;
            }

            try {
                const resultado = await navigator.permissions.query({ name: 'geolocation' });
                this.actualizarEstado(resultado.state);
                resultado.onchange = () => this.actualizarEstado(resultado.state);
            } catch (error) {
                this.estado = 'Pulsa "solicitar permiso" para activarlo.';
            }
        },

        actualizarEstado(state) {
            const etiquetas = {
                granted: 'Permiso concedido',
                denied: 'Permiso denegado (actívalo desde los ajustes del navegador)',
                prompt: 'Aún no se ha pedido el permiso',
            };

            this.estado = etiquetas[state] || state;
        },

        pedirPermiso() {
            navigator.geolocation.getCurrentPosition(
                () => this.consultar(),
                () => this.consultar()
            );
        },
    };
};

window.msscCamaraToggle = function () {
    return {
        estado: 'Consultando permiso…',

        async init() {
            if (!('mediaDevices' in navigator)) {
                this.estado = 'Este navegador no soporta cámara.';
                return;
            }

            await this.consultar();
        },

        async consultar() {
            if (!('permissions' in navigator)) {
                this.estado = 'Pulsa "solicitar permiso" para activarlo.';
                return;
            }

            try {
                const resultado = await navigator.permissions.query({ name: 'camera' });
                this.actualizarEstado(resultado.state);
                resultado.onchange = () => this.actualizarEstado(resultado.state);
            } catch (error) {
                // Algunos navegadores (ej. Firefox) no exponen 'camera' en permissions.query.
                this.estado = 'Pulsa "solicitar permiso" para activarlo.';
            }
        },

        actualizarEstado(state) {
            const etiquetas = {
                granted: 'Permiso concedido',
                denied: 'Permiso denegado (actívalo desde los ajustes del navegador)',
                prompt: 'Aún no se ha pedido el permiso',
            };

            this.estado = etiquetas[state] || state;
        },

        async pedirPermiso() {
            try {
                const flujo = await navigator.mediaDevices.getUserMedia({ video: true });
                flujo.getTracks().forEach((pista) => pista.stop());
            } catch (error) {

            }

            await this.consultar();
        },
    };
};

window.msscVolumenNotificacion = function (volumenInicial) {
    return {
        volumen: volumenInicial,

        init() {
            this.$watch('volumen', (valor) => {
                this.volumen = Number(valor);
            });

            this.$wire.on('mssc-volumen-actualizado', ({ volumen }) => {
                this.volumen = volumen;

                window.MSSC_VOLUMEN_NOTIFICACION = volumen;
            });
        },

        probar() {
            window.msscReproducirSonidoNotificacion(this.volumen);
        },
    };
};

let promptDiferido = null;

window.addEventListener('beforeinstallprompt', (evento) => {
    evento.preventDefault();
    promptDiferido = evento;
    window.dispatchEvent(new CustomEvent('mssc-instalable'));
});

window.msscInstalarApp = function () {
    return {
        instalable: false,

        init() {
            window.addEventListener('mssc-instalable', () => {
                this.instalable = true;
            });
        },

        async instalar() {
            if (!promptDiferido) {
                return;
            }

            promptDiferido.prompt();
            await promptDiferido.userChoice;
            promptDiferido = null;
            this.instalable = false;
        },
    };
};