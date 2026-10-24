

async function resolverDireccion(lat, lng) {
    const controlador = new AbortController();
    const expira = setTimeout(() => controlador.abort(), 8000);

    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&addressdetails=0&accept-language=es`;
        const respuesta = await fetch(url, {
            signal: controlador.signal,
            headers: { Accept: 'application/json' },
        });

        if (!respuesta.ok) {
            return null;
        }

        const datos = await respuesta.json();

        return datos.display_name ?? null;
    } catch (error) {

        return null;
    } finally {
        clearTimeout(expira);
    }
}

function formatearFechaHora(fecha) {
    const dos = (n) => String(n).padStart(2, '0');

    return {
        fecha: `${dos(fecha.getDate())}/${dos(fecha.getMonth() + 1)}/${fecha.getFullYear()}`,
        hora: `${dos(fecha.getHours())}:${dos(fecha.getMinutes())}:${dos(fecha.getSeconds())}`,
    };
}

function formatearUbicacion(direccion, lat, lng) {
    if (direccion) return direccion;
    if (lat && lng) return `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;

    return 'Obteniendo ubicación…';
}

function partirTexto(ctx, texto, anchoMaximo) {
    const palabras = texto.split(' ');
    const lineas = [];
    let lineaActual = '';

    palabras.forEach((palabra) => {
        const intento = lineaActual ? `${lineaActual} ${palabra}` : palabra;

        if (ctx.measureText(intento).width > anchoMaximo && lineaActual) {
            lineas.push(lineaActual);
            lineaActual = palabra;
        } else {
            lineaActual = intento;
        }
    });

    if (lineaActual) {
        lineas.push(lineaActual);
    }

    return lineas;
}

function sellarFoto(canvas, { direccion, lat, lng }) {
    const ctx = canvas.getContext('2d');
    const ancho = canvas.width;
    const alto = canvas.height;

    const { fecha, hora } = formatearFechaHora(new Date());
    const textoUbicacion = formatearUbicacion(direccion, lat, lng);

    const paddingX = Math.round(ancho * 0.04);
    const tamanoGrande = Math.max(18, Math.round(ancho * 0.05));
    const tamanoPequeno = Math.max(13, Math.round(ancho * 0.032));
    const interlineado = Math.round(tamanoPequeno * 1.3);

    ctx.font = `${tamanoPequeno}px sans-serif`;
    const lineasDireccion = partirTexto(ctx, `📍 ${textoUbicacion}`, ancho - paddingX * 2).slice(0, 2);

    const lineas = [`🕒 ${fecha} ${hora}`, ...lineasDireccion];
    const altoBanda = Math.round(tamanoGrande * 1.6) + lineasDireccion.length * interlineado + 18;

    const degradado = ctx.createLinearGradient(0, alto - altoBanda, 0, alto);
    degradado.addColorStop(0, 'rgba(0, 0, 0, 0)');
    degradado.addColorStop(0.35, 'rgba(0, 0, 0, 0.55)');
    degradado.addColorStop(1, 'rgba(0, 0, 0, 0.75)');
    ctx.fillStyle = degradado;
    ctx.fillRect(0, alto - altoBanda, ancho, altoBanda);

    ctx.textBaseline = 'bottom';
    ctx.fillStyle = '#ffffff';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.6)';
    ctx.shadowBlur = 4;

    let y = alto - 14;

    for (let i = lineas.length - 1; i >= 0; i -= 1) {
        const esFecha = i === 0;
        ctx.font = `${esFecha ? 'bold ' : ''}${esFecha ? tamanoGrande : tamanoPequeno}px sans-serif`;
        ctx.fillText(lineas[i], paddingX, y);
        y -= esFecha ? Math.round(tamanoGrande * 1.5) : interlineado;
    }

    ctx.shadowBlur = 0;
}

window.msscRetornoCamara = function () {
    return {
        // 'inicial' -> 'abriendo' -> 'en-vivo' -> 'capturada'
        estado: 'inicial',
        enviando: false,

        stream: null,
        facingMode: 'environment',

        lat: '',
        lng: '',
        direccion: null,
        ubicacionLista: false,
        ubicacionError: '',
        camaraError: '',

        relojId: null,
        horaActual: '',

        fotoBlob: null,
        fotoPreviewUrl: null,

        iniciar() {
            this.estado = 'abriendo';
            this.camaraError = '';
            this.ubicacionError = '';

            this.iniciarReloj();

            this.iniciarUbicacion();
        },

        iniciarReloj() {
            this.actualizarHora();
            this.detenerReloj();
            this.relojId = setInterval(() => this.actualizarHora(), 1000);
        },

        actualizarHora() {
            const { fecha, hora } = formatearFechaHora(new Date());
            this.horaActual = `${fecha} ${hora}`;
        },

        detenerReloj() {
            if (this.relojId) {
                clearInterval(this.relojId);
                this.relojId = null;
            }
        },

        textoUbicacion() {
            return formatearUbicacion(this.direccion, this.lat, this.lng);
        },

        async conectarCamara() {
            if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
                this.camaraError = 'Este dispositivo no soporta cámara en vivo.';
                this.estado = 'inicial';
                this.detenerReloj();
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: this.facingMode } },
                    audio: false,
                });

                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.estado = 'en-vivo';
            } catch (error) {
                this.camaraError = 'No se pudo abrir la cámara: ' + (error.message || 'permiso denegado.');
                this.estado = 'inicial';
                this.detenerReloj();
            }
        },

        async cambiarCamara() {

            this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
            this.detenerStream();
            await this.conectarCamara();
        },

        iniciarUbicacion() {
            if (!('geolocation' in navigator)) {
                this.ubicacionError = 'Este dispositivo no soporta geolocalización.';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                async (posicion) => {
                    this.lat = posicion.coords.latitude;
                    this.lng = posicion.coords.longitude;
                    this.ubicacionLista = true;
                    this.direccion = await resolverDireccion(this.lat, this.lng);
                },
                (error) => {
                    this.ubicacionError = 'No se pudo obtener tu ubicación: ' + error.message;
                },
                { enableHighAccuracy: true, timeout: 15000 }
            );
        },

        capturar() {
            if (this.estado !== 'en-vivo' || !this.ubicacionLista) {
                return;
            }

            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            sellarFoto(canvas, { direccion: this.direccion, lat: this.lat, lng: this.lng });

            canvas.toBlob(
                (blob) => {
                    this.fotoBlob = blob;
                    this.fotoPreviewUrl = URL.createObjectURL(blob);
                    this.estado = 'capturada';

                    this.detenerStream();
                    this.detenerReloj();
                },
                'image/jpeg',
                0.85
            );
        },

        reintentar() {
            if (this.fotoPreviewUrl) {
                URL.revokeObjectURL(this.fotoPreviewUrl);
            }
            this.fotoBlob = null;
            this.fotoPreviewUrl = null;
            this.iniciar();
        },

        detenerStream() {
            this.stream?.getTracks().forEach((pista) => pista.stop());
            this.stream = null;
        },

        prepararEnvio(evento) {
            if (this.enviando) {
                evento.preventDefault();
                return;
            }

            if (!this.fotoBlob || !this.lat || !this.lng) {
                evento.preventDefault();
                this.camaraError = 'Primero captura la foto de retorno.';
                return;
            }

            const archivo = new File([this.fotoBlob], 'retorno.jpg', { type: 'image/jpeg' });
            const transferencia = new DataTransfer();
            transferencia.items.add(archivo);
            this.$refs.inputFoto.files = transferencia.files;

            this.enviando = true;
        },
    };
};