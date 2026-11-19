import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import L from 'leaflet';
import './movimiento';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Legend, LinearScale, Tooltip);

Chart.defaults.font.family = "'Hanken Grotesk', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 13;
Chart.defaults.color = '#6b7482';
Chart.defaults.plugins.legend.display = false;
Chart.defaults.plugins.tooltip.backgroundColor = '#1f2328';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.titleFont = { weight: '600' };

const colores = {
    naranja: '#f07d2e',
    gris: '#c9ced6',
    verde: '#2fa455',
    azul: '#3b6fe0',
    ambar: '#e5a30b',
    rojo: '#d92d20',
    violeta: '#8b6fe8',
};

// Al cambiar de pantalla se detienen las gráficas para que no se dibujen sobre un lienzo que ya no existe.
document.addEventListener('livewire:navigating', () => {
    Object.values(Chart.instances).forEach((grafica) => grafica.destroy());
});

const numero = (valor) => new Intl.NumberFormat('es-CO').format(valor);

/**
 * Alpine viene incluido en Livewire: aquí solo se registran los componentes
 * de la plataforma (gráficas, mapa y utilidades).
 */
document.addEventListener('alpine:init', () => {
    /**
     * Barras agrupadas: servicios recibidos y entregados por día.
     * datos = { etiquetas: [], series: [{ nombre, color, valores: [] }], resaltar?: índice }
     */
    window.Alpine.data('graficaBarras', (datos) => {
        // Fuera del estado reactivo de Alpine: envuelta en su Proxy, Chart.js
        // sigue dibujando sobre un lienzo que Livewire ya reemplazó.
        let grafica = null;

        return {
            init() {
                grafica = new Chart(this.$refs.lienzo, {
                    type: 'bar',
                    data: {
                        labels: datos.etiquetas,
                        datasets: datos.series.map((serie) => ({
                            label: serie.nombre,
                            data: serie.valores,
                            backgroundColor: colores[serie.color] ?? serie.color,
                            borderRadius: 4,
                            borderSkipped: false,
                            barPercentage: 0.82,
                            categoryPercentage: 0.62,
                            maxBarThickness: 18,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: {
                                    color: (contexto) => (contexto.index === datos.resaltar ? '#c2530a' : '#6b7482'),
                                    font: (contexto) => ({ weight: contexto.index === datos.resaltar ? '700' : '400' }),
                                    maxRotation: 0,
                                    autoSkipPadding: 12,
                                },
                            },
                            y: {
                                beginAtZero: true,
                                border: { display: false, dash: [4, 4] },
                                grid: { color: '#e6e8ec' },
                                ticks: { precision: 0, maxTicksLimit: 5, padding: 8 },
                            },
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    title: (items) => datos.titulos?.[items[0].dataIndex] ?? items[0].label,
                                    label: (item) => ` ${item.dataset.label}: ${numero(item.parsed.y)}`,
                                },
                            },
                        },
                    },
                });
            },

            destroy() {
                grafica?.stop();
                grafica?.destroy();
                grafica = null;
            },
        };
    });

    /**
     * Dona de estados. datos = { etiquetas: [], valores: [], colores: [] }
     */
    window.Alpine.data('graficaDona', (datos) => {
        let grafica = null;

        return {
            init() {
                const total = datos.valores.reduce((suma, valor) => suma + valor, 0);

                grafica = new Chart(this.$refs.lienzo, {
                    type: 'doughnut',
                    data: {
                        labels: datos.etiquetas,
                        datasets: [
                            {
                                data: total > 0 ? datos.valores : [1],
                                backgroundColor: total > 0 ? datos.colores.map((color) => colores[color] ?? color) : ['#e6e8ec'],
                                borderWidth: 0,
                                spacing: total > 0 ? 2 : 0,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            tooltip: {
                                enabled: total > 0,
                                callbacks: { label: (item) => ` ${item.label}: ${numero(item.parsed)}` },
                            },
                        },
                    },
                });
            },

            destroy() {
                grafica?.stop();
                grafica?.destroy();
                grafica = null;
            },
        };
    });

    /**
     * Mapa con el punto donde se registró la entrega y su radio de precisión.
     */
    window.Alpine.data('mapaEntrega', ({ latitud, longitud, precision }) => ({
        mapa: null,

        init() {
            const punto = [latitud, longitud];

            this.mapa = L.map(this.$refs.mapa, { scrollWheelZoom: false, attributionControl: true }).setView(punto, 17);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.mapa);

            if (precision) {
                L.circle(punto, {
                    radius: Math.max(precision, 6),
                    color: colores.naranja,
                    weight: 1.5,
                    fillColor: colores.naranja,
                    fillOpacity: 0.16,
                }).addTo(this.mapa);
            }

            L.marker(punto, {
                icon: L.divIcon({
                    className: 'pin-entrega',
                    iconSize: [36, 44],
                    iconAnchor: [18, 42],
                    html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 44" width="36" height="44"><path d="M18 43C18 43 33 28.5 33 17A15 15 0 1 0 3 17C3 28.5 18 43 18 43Z" fill="${colores.naranja}" stroke="#fff" stroke-width="2"/><circle cx="18" cy="17" r="5.5" fill="#fff"/></svg>`,
                }),
            }).addTo(this.mapa);

            // El mapa puede crearse dentro de un contenedor que aún no tiene tamaño.
            setTimeout(() => this.mapa?.invalidateSize(), 150);
        },

        destroy() {
            this.mapa?.remove();
        },
    }));

    /**
     * Copia un texto al portapapeles y avisa durante un momento.
     */
    window.Alpine.data('copiar', (texto) => ({
        copiado: false,

        async copiar() {
            try {
                await navigator.clipboard.writeText(texto);
            } catch {
                const area = Object.assign(document.createElement('textarea'), { value: texto });
                document.body.append(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }

            this.copiado = true;
            setTimeout(() => (this.copiado = false), 2200);
        },
    }));
});
