
window.msscMapaSede = function (config) {
    return {
        mapa: null,
        marcador: null,
        circulo: null,

        async init() {
            const { default: L } = await import('./sede-mapa-leaflet');

            if (! this.$refs.mapa?.isConnected) {
                return;
            }

            this.mapa = L.map(this.$refs.mapa).setView([config.lat, config.lng], config.zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(this.mapa);

            this.marcador = L.marker([config.lat, config.lng], { draggable: true }).addTo(this.mapa);
            this.circulo = L.circle([config.lat, config.lng], {
                radius: config.radio,
                color: '#0f1c2e',
                fillOpacity: 0.15,
            }).addTo(this.mapa);

            this.marcador.on('dragend', () => this.moverA(this.marcador.getLatLng()));

            this.mapa.on('click', (evento) => {
                this.marcador.setLatLng(evento.latlng);
                this.moverA(evento.latlng);
            });

            this.$wire.$watch('radioMetros', (valor) => {
                if (this.circulo && valor) {
                    this.circulo.setRadius(Number(valor));
                }
            });
        },

        destroy() {
            this.mapa?.remove();
            this.mapa = null;
        },

        moverA(latlng) {
            this.circulo.setLatLng(latlng);
            this.$wire.set('latitud', Number(latlng.lat.toFixed(7)));
            this.$wire.set('longitud', Number(latlng.lng.toFixed(7)));
        },
    };
};
