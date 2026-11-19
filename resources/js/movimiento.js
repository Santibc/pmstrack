import autoAnimate from '@formkit/auto-animate';

/**
 * Movimiento de la interfaz. La regla: nada se mueve por adorno; solo cuando
 * responde a una acción del usuario o a un dato que cambió.
 */
const sinMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const formato = new Intl.NumberFormat('es-CO');

/**
 * Lee la primera cifra de un texto ("94,6 %", "1.248", "−214") con el formato colombiano.
 */
function leerCifra(texto) {
    const coincidencia = texto.match(/[-−+]?\d[\d.]*(?:,\d+)?/);

    if (!coincidencia) {
        return null;
    }

    const cruda = coincidencia[0];
    const decimales = cruda.includes(',') ? cruda.split(',')[1].length : 0;
    const valor = parseFloat(cruda.replace(/\./g, '').replace(',', '.').replace('−', '-'));

    return Number.isNaN(valor) ? null : { valor, decimales, cruda, signo: cruda.startsWith('+') };
}

function escribirCifra(valor, decimales, signo) {
    const texto = new Intl.NumberFormat('es-CO', { minimumFractionDigits: decimales, maximumFractionDigits: decimales }).format(valor);

    return (signo && valor > 0 ? '+' : '') + texto.replace('-', '−');
}

document.addEventListener('alpine:init', () => {
    /**
     * x-animar: las filas y tarjetas que entran, salen o cambian de lugar se
     * reacomodan con una transición en vez de saltar (por ejemplo cuando el
     * seguimiento en vivo se actualiza).
     */
    window.Alpine.directive('animar', (el) => {
        if (!sinMovimiento()) {
            autoAnimate(el, { duration: 240, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' });
        }
    });

    /**
     * x-cifra: cuando el servidor actualiza un número, este transita del valor
     * anterior al nuevo en lugar de cambiar de golpe.
     */
    window.Alpine.directive('cifra', (el, _, { cleanup }) => {
        let actual = leerCifra(el.textContent);
        let cuadro = null;

        const observador = new MutationObserver(() => {
            const nueva = leerCifra(el.textContent);

            if (!nueva || !actual || nueva.valor === actual.valor || sinMovimiento()) {
                actual = nueva ?? actual;

                return;
            }

            const plantilla = el.textContent;
            const desde = actual.valor;
            const inicio = performance.now();
            const duracion = 520;
            actual = nueva;

            cancelAnimationFrame(cuadro);
            observador.disconnect();

            const paso = (ahora) => {
                const t = Math.min(1, (ahora - inicio) / duracion);
                const suave = 1 - Math.pow(1 - t, 3);
                const valor = desde + (nueva.valor - desde) * suave;

                el.textContent = plantilla.replace(nueva.cruda, escribirCifra(t === 1 ? nueva.valor : valor, nueva.decimales, nueva.signo));

                if (t < 1) {
                    cuadro = requestAnimationFrame(paso);
                } else {
                    observador.observe(el, { childList: true, characterData: true, subtree: true });
                }
            };

            cuadro = requestAnimationFrame(paso);
        });

        observador.observe(el, { childList: true, characterData: true, subtree: true });
        cleanup(() => {
            observador.disconnect();
            cancelAnimationFrame(cuadro);
        });
    });

    /**
     * Buscador global (Ctrl/⌘ + K): estado de apertura y navegación con el teclado.
     */
    window.Alpine.data('buscadorGlobal', () => ({
        abierto: false,
        activo: 0,

        abrir() {
            this.abierto = true;
            this.activo = 0;
            this.$nextTick(() => this.$refs.entrada?.focus());
        },

        cerrar() {
            this.abierto = false;
        },

        opciones() {
            return [...this.$root.querySelectorAll('[data-opcion]')];
        },

        mover(paso) {
            const total = this.opciones().length;

            if (total === 0) {
                return;
            }

            this.activo = (this.activo + paso + total) % total;
            this.opciones()[this.activo]?.scrollIntoView({ block: 'nearest' });
        },

        elegir() {
            this.opciones()[this.activo]?.click();
        },
    }));
});

/**
 * Al cambiar de pantalla el contenido entra con un fundido corto, para que la
 * navegación se sienta continua (las páginas no se recargan).
 */
document.addEventListener('livewire:navigated', () => {
    const contenido = document.getElementById('contenido');

    if (!contenido || sinMovimiento()) {
        return;
    }

    contenido.classList.remove('entrada-pagina');
    void contenido.offsetWidth;
    contenido.classList.add('entrada-pagina');
});

export { formato };
