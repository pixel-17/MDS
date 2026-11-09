@props(['papeletaId'])

@auth
    <div
        x-data="{
            papeletaId: {{ (int) $papeletaId }},
            actualizando: false,

            init() {
                if (! window.Echo) {
                    return;
                }

                // Sin leading-dot, Echo le antepondría el namespace por defecto
                // (App.Events) y lo convertiría a puntos; con el dot se usa tal
                // cual el nombre que Laravel manda por defecto (clase completa
                // con backslashes), que es el mismo que ya escuchan
                // EscuchaNotificacionesEnVivo e InsigniaBandeja — no se le puso
                // broadcastAs() a propósito, para no duplicar el nombre en dos
                // formatos distintos.
                window.Echo.private('App.Models.User.{{ auth()->id() }}')
                    .listen('.App\\Events\\PapeletaActualizada', (e) => {
                        if (e.papeleta_id === this.papeletaId) {
                            this.actualizarContenido();
                        }
                    });
            },

            selectorContenedor() {
                return `[data-papeleta-id='${this.papeletaId}']`;
            },

            async actualizarContenido() {
                const actuales = Array.from(document.querySelectorAll(this.selectorContenedor()));
                if (actuales.length === 0 || this.actualizando) {
                    return;
                }

                // No pisar lo que el trabajador/jefe está escribiendo ahora mismo
                // en ningún nodo marcado (textarea de respuesta, etc.).
                const hayFocoActivo = actuales.some((nodo) => document.activeElement
                    && nodo.contains(document.activeElement)
                    && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName));

                if (hayFocoActivo) {
                    return;
                }

                this.actualizando = true;

                try {
                    const resp = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        cache: 'no-store',
                    });

                    if (! resp.ok) {
                        return;
                    }

                    const html = await resp.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const nuevos = Array.from(doc.querySelectorAll(this.selectorContenedor()));

                    // Mismo marcador, distinta cantidad de nodos: no se sabe
                    // emparejarlos uno a uno, mejor no tocar nada.
                    if (nuevos.length !== actuales.length) {
                        return;
                    }

                    actuales.forEach((destino, i) => {
                        destino.innerHTML = nuevos[i].innerHTML;

                        if (window.Alpine) {
                            window.Alpine.initTree(destino);
                        }
                    });
                } catch (e) {
                    // Sin conexión o Reverb/red con problemas: se queda con lo que había
                    // en pantalla y lo intenta de nuevo en el próximo evento.
                } finally {
                    this.actualizando = false;
                }
            },
        }"
        class="hidden"
        aria-hidden="true"
    ></div>
@endauth
