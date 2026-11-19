<?php

namespace Database\Seeders;

use App\Enums\ResultadoVisita;
use App\Enums\Rol;
use App\Enums\TipoNovedad;
use App\Models\Bodega;
use App\Models\Causal;
use App\Models\Direccion;
use App\Models\Guia;
use App\Models\Novedad;
use App\Models\Parada;
use App\Models\Repartidor;
use App\Models\Ruta;
use App\Models\Seccional;
use App\Models\User;
use App\Models\Zona;
use App\Services\Calendario\CalendarioLaboral;
use App\Services\Cargas\CargaService;
use App\Services\Direcciones\NormalizadorDirecciones;
use App\Services\Inventario\InventarioService;
use App\Services\Planeacion\PlaneacionService;
use App\Services\Rutas\RutaService;
use App\Services\Visitas\DatosVisita;
use App\Services\Visitas\VisitaService;
use App\Services\Zonificacion\ZonificacionService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\ImagenesDemo;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Datos de demostración. Recorre la operación completa con los mismos
 * servicios que usa el sistema: carga del Excel, zonificación, planeación,
 * rutas, visitas con evidencia, cierres e inventario.
 *
 * Los comercios, personas, teléfonos y cédulas son ficticios.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private const int DIAS_DE_HISTORIA = 12;

    /** Repartidor de cada zona, por seccional. */
    private const array EQUIPO = [
        'BOG' => [
            ['Carlos Rodríguez', '80123456', 'Moto', 'ABC12D', ['Z01']],
            ['Andrés Gómez', '79456123', 'Moto', 'KLM34E', ['Z02']],
            ['Luis Martínez', '1020456789', 'Moto', 'TRE56F', ['Z03', 'Z06']],
            ['Jhon Pérez', '1015432876', 'Carro', 'HJK789', ['Z04']],
            ['Diana Ríos', '52734418', 'Moto', 'QWE90G', ['Z05']],
            ['Miguel Torres', '1032456123', 'Moto', 'ZXC21H', ['Z07']],
            ['Pedro Ávila', '80765432', 'Moto', 'BNM43J', []],
        ],
        'MED' => [
            ['Sandra Castro', '43567890', 'Moto', 'MDE12A', ['Z01', 'Z02']],
            ['Julián Restrepo', '71234567', 'Moto', 'MDE34B', ['Z03']],
        ],
        'CAL' => [
            ['Camilo Ortiz', '94321654', 'Moto', 'CLO56C', ['Z01', 'Z02']],
        ],
    ];

    /** Ciudad y rangos de nomenclatura de cada zona: [ciudad, vía, desde, hasta, cruce desde, cruce hasta, sufijo]. */
    private const array GEOGRAFIA = [
        'BOG' => [
            'Z01' => ['BOGOTA', 'CL', 116, 170, 7, 20, ''],
            'Z02' => ['BOGOTA', 'CL', 45, 94, 5, 15, ''],
            'Z03' => ['BOGOTA', 'CL', 6, 26, 3, 10, ''],
            'Z04' => ['BOGOTA', 'KR', 68, 110, 13, 26, ''],
            'Z05' => ['BOGOTA', 'CL', 128, 170, 50, 110, ''],
            'Z06' => ['BOGOTA', 'CL', 1, 40, 10, 30, ' SUR'],
            'Z07' => ['BOGOTA', 'KR', 72, 90, 38, 60, ' SUR'],
        ],
        'MED' => [
            'Z01' => ['MEDELLIN', 'KR', 32, 48, 1, 12, ' SUR'],
            'Z02' => ['MEDELLIN', 'KR', 65, 82, 30, 48, ''],
            'Z03' => ['BELLO', 'CL', 44, 58, 40, 56, ''],
        ],
        'CAL' => [
            'Z01' => ['CALI', 'AV', 2, 9, 12, 40, ' NORTE'],
            'Z02' => ['CALI', 'KR', 50, 100, 3, 20, ''],
        ],
    ];

    private const array DEPARTAMENTOS = ['BOG' => 'BOGOTA, D. C.', 'MED' => 'ANTIOQUIA', 'CAL' => 'VALLE DEL CAUCA'];

    private const array TIPOS = ['Café', 'Droguería', 'Panadería', 'Ferretería', 'Restaurante', 'Supermercado', 'Óptica', 'Papelería', 'Hotel', 'Lavandería', 'Tienda Naturista', 'Minimercado', 'Distribuidora', 'Pizzería', 'Barbería', 'Veterinaria', 'Licorera', 'Cigarrería', 'Floristería', 'Heladería'];

    private const array NOMBRES = ['Altamira', 'La Sabana', 'Pan de Yuca', 'Los Andes', 'Sabor Paisa', 'Mercaplaza', 'Lux', 'Las Nieves', 'Casa Botánica', 'Express', 'Vida Sana', 'El Portal', 'El Granero', 'Don Pepe', 'La Espiga', 'El Tornillo', 'Santa Mónica', 'Visión Clara', 'Mirador', 'San Rafael', 'El Recreo', 'La 85', 'Monserrate', 'El Dorado', 'Kiko', 'Andino', 'Bienestar', 'La Pradera', 'El Fogón', 'Patio Bonito'];

    private const array PERSONAS = ['Rosa Castellanos', 'Mónica Herrera', 'Laura Jiménez', 'Nicolás Vargas', 'Bertha Cruz', 'Jonathan Vega', 'Estefanía Mora', 'Paula Acero', 'Jairo Suárez', 'Karen Henao', 'Luisa Flórez', 'Germán Peña', 'Valentina Coral', 'Yesica Novoa', 'Camila Duarte', 'Fabio Vargas', 'Andrea Carvajal', 'Sebastián Osorio', 'Lizeth Rojas', 'William Aguilar'];

    private CargaService $cargas;

    private ZonificacionService $zonificacion;

    private PlaneacionService $planeacion;

    private RutaService $rutas;

    private VisitaService $visitas;

    private InventarioService $inventario;

    private User $admin;

    /** @var Collection<string, Collection<int, array<string, mixed>>> Comercios ficticios por seccional. */
    private Collection $comercios;

    /** @var array<string, string> Clave de dirección → "SECCIONAL|ZONA". */
    private array $zonaDeComercio = [];

    private int $servicio = 3930000;

    private int $semilla = 0;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Los datos de demostración no se cargan en producción.');

            return;
        }

        if (Guia::query()->exists()) {
            $this->command?->warn('Ya hay guías cargadas: no se siembran los datos de demostración.');

            return;
        }

        mt_srand(20261112);
        $this->cargas = app(CargaService::class);
        $this->zonificacion = app(ZonificacionService::class);
        $this->planeacion = app(PlaneacionService::class);
        $this->rutas = app(RutaService::class);
        $this->visitas = app(VisitaService::class);
        $this->inventario = app(InventarioService::class);
        $this->admin = User::query()->role(Rol::Administradora->value)->firstOrFail();

        $ahora = CarbonImmutable::now();
        $dias = $this->diasHabilesAnteriores($ahora, self::DIAS_DE_HISTORIA);

        $this->crearRepartidores();
        $this->crearComercios();

        // Existencias iniciales, antes del primer día de historia.
        Carbon::setTestNow($dias->first()->subDay()->setTime(16, 30));
        foreach (['BOG' => [24, 0], 'MED' => [9, 0], 'CAL' => [5, 120]] as $codigo => [$cajas, $sueltos]) {
            $this->inventario->entrada($this->bodega($codigo), $cajas * 360 + $sueltos, 'R-'.(10300 + $cajas), today(), $this->admin);
        }

        foreach ($dias as $indice => $dia) {
            $this->command?->getOutput()->write("\r  Simulando la operación: ".$dia->isoFormat('ddd D MMM').' ('.($indice + 1).'/'.$dias->count().')   ');
            $this->simularDia($dia, $indice);
        }

        $this->command?->getOutput()->writeln('');
        $this->simularHoy($ahora);

        Carbon::setTestNow();
        $this->command?->info('Demostración lista: '.Guia::query()->count().' guías, '.Ruta::query()->count().' rutas.');
    }

    /**
     * Un día completo de operación en el pasado.
     */
    private function simularDia(CarbonImmutable $dia, int $indice): void
    {
        Carbon::setTestNow($dia->setTime(7, 50));
        $this->cargarArchivo($dia, ['BOG' => mt_rand(19, 24), 'MED' => mt_rand(7, 10), 'CAL' => mt_rand(4, 6)], urgentes: $indice % 3 === 0 ? 1 : 0);
        $this->zonificarPendientes();

        Carbon::setTestNow($dia->setTime(8, 0));
        foreach (array_keys(self::EQUIPO) as $codigo) {
            $this->publicar($codigo, $dia);
        }

        foreach (Ruta::query()->with('repartidor.user')->whereDate('fecha', $dia)->get() as $ruta) {
            $this->recorrer($ruta, $dia->setTime(8, mt_rand(2, 25)), cuantas: null, cerrar: true);
        }
    }

    /**
     * El día de hoy, a medio camino: rutas en distintos puntos, novedades y
     * guías nuevas por zonificar y por publicar.
     */
    private function simularHoy(CarbonImmutable $ahora): void
    {
        $this->command?->getOutput()->writeln('  Simulando el día de hoy…');

        // Primera carga de la mañana, planeación y salida a ruta.
        $inicio = $ahora->subHours(4);
        Carbon::setTestNow($inicio->subMinutes(25));
        $this->cargarArchivo($ahora, ['BOG' => 30, 'MED' => 9, 'CAL' => 5], urgentes: 2, registro: $ahora->subHours(6));
        $this->zonificarPendientes();

        Carbon::setTestNow($inicio->subMinutes(10));
        $this->inventario->entrada($this->bodega('BOG'), 3000, 'R-10458', today(), $this->admin);

        Carbon::setTestNow($inicio);
        foreach (array_keys(self::EQUIPO) as $codigo) {
            $this->publicar($codigo, $ahora);
        }

        // Cada repartidor va en un punto distinto de su ruta.
        $avances = [
            '80123456' => [0.4, 6, false],     // Carlos: en reparto
            '79456123' => [0.75, 9, false],    // Andrés: casi termina
            '1020456789' => [0.6, 14, false],  // Luis: dos zonas
            '1015432876' => [0.65, 52, false], // Jhon: sin actividad hace 52 min
            '52734418' => [1.0, 30, true],     // Diana: ruta cerrada
            '1032456123' => [0.5, 21, false],  // Miguel
            '43567890' => [0.45, 11, false],   // Sandra (Medellín)
            '71234567' => [0.0, 0, false],     // Julián: aún no inicia
            '94321654' => [0.3, 17, false],    // Camilo (Cali)
        ];

        foreach (Ruta::query()->with('repartidor.user')->whereDate('fecha', $ahora)->get() as $ruta) {
            [$avance, $haceMinutos, $cerrar] = $avances[$ruta->repartidor->cedula] ?? [0.5, 15, false];

            if ($avance <= 0) {
                continue;
            }

            $total = $ruta->paradas()->count();
            $this->recorrer($ruta, $inicio->addMinutes(mt_rand(3, 12)), cuantas: max(1, (int) round($total * $avance)), cerrar: $cerrar, terminaEn: $ahora->subMinutes($haceMinutos));
        }

        // Novedad reportada desde la app.
        $andres = Repartidor::query()->where('cedula', '79456123')->first();
        Novedad::query()->create([
            'uuid' => (string) Str::uuid(),
            'repartidor_id' => $andres->id,
            'ruta_id' => $this->rutas->delDia($andres, $ahora)?->id,
            'tipo' => TipoNovedad::ViaCerrada,
            'descripcion' => 'Vía cerrada por obra en la Cra 7 con Calle 63; tomo desvío, llego 20 min tarde a la siguiente parada.',
            'reportada_at' => $ahora->subMinutes(34),
            'estado' => Novedad::ABIERTA,
        ]);

        // Segundo archivo del día: guías que todavía no han salido (por zonificar y por publicar).
        Carbon::setTestNow($ahora->subMinutes(18));
        $this->cargarArchivo($ahora, ['BOG' => 11, 'MED' => 3, 'CAL' => 2], urgentes: 1, registro: $ahora->subHours(2), nuevos: 7);
        $this->zonificarPendientes(dejar: 5);

        Carbon::setTestNow($ahora);
    }

    /**
     * Genera el Excel del día en el formato de Credibanco y lo carga con el flujo real.
     *
     * @param  array<string, int>  $cantidades  Servicios por seccional.
     */
    private function cargarArchivo(CarbonImmutable $dia, array $cantidades, int $urgentes = 0, ?CarbonImmutable $registro = null, int $nuevos = 2): void
    {
        $filas = [];

        foreach ($cantidades as $codigo => $cantidad) {
            // Mezcla de comercios conocidos (se zonifican solos) y nuevos.
            $conocidos = $this->comercios[$codigo]->filter(fn (array $c): bool => $c['visto'])->shuffle()->take(max(0, $cantidad - $nuevos));
            $porConocer = $this->comercios[$codigo]->reject(fn (array $c): bool => $c['visto'])->take($cantidad - $conocidos->count());

            foreach ($conocidos->concat($porConocer) as $comercio) {
                $this->comercios[$codigo] = $this->comercios[$codigo]->map(fn (array $c): array => $c['cu'] === $comercio['cu'] ? [...$c, 'visto' => true] : $c);
                $urgente = $urgentes > 0 && $codigo === 'BOG' && $urgentes-- > 0;
                $momento = $registro ?? $dia->subDay()->setTime(mt_rand(8, 17), mt_rand(0, 59));
                $persona = self::PERSONAS[array_rand(self::PERSONAS)];
                $celular = '3'.mt_rand(10, 21).'555'.str_pad((string) mt_rand(100, 199), 4, '0', STR_PAD_LEFT);

                $filas[] = [
                    'SERVICIO' => ++$this->servicio,
                    'CADENA' => mb_strtoupper($comercio['nombre']).' S A S',
                    'CODIGO_UNICO' => $comercio['cu'],
                    'FECHA_REGISTRO' => $momento,
                    'NOMBRE_COMERCIO' => mb_strtoupper($comercio['nombre']),
                    'CODIGO_CIUDAD' => 0,
                    'CIUDAD' => $comercio['ciudad'],
                    'GEOLOCALIZACION' => 'Urbano',
                    'SECCIONAL' => ['BOG' => 'BOGOTA', 'MED' => 'MEDELLIN', 'CAL' => 'CALI'][$codigo],
                    'DEPARTAMENTO' => self::DEPARTAMENTOS[$codigo],
                    'DIRECCION' => $comercio['direccion'],
                    'TELEFONO' => mt_rand(1, 14) === 1 ? '+57 1234567' : '+57 '.$celular,
                    'CONTACTO' => null,
                    'HORARIO_DE_ATENCION' => 'Lunes a Viernes 8:30 - 17:30 - '.($urgente ? '8 horas' : '168 Horas'),
                    'PAPELERIA_ENTREGAR' => [8, 8, 8, 10, 12, 14, 15, 20, 23, 30, 40][array_rand([8, 8, 8, 10, 12, 14, 15, 20, 23, 30, 40])],
                    'OBSERVACIONES' => "Se comunica {$persona} a través de WhatsApp y solicita papelería para su comercio, indica que atiende en horario de 8am a 6pm en la dirección {$comercio['direccion']}, número de contacto {$celular} en la ciudad de ".ucfirst(mb_strtolower($comercio['ciudad'])),
                ];
            }
        }

        $ruta = $this->excel($filas);
        $carga = $this->cargas->procesarRuta($ruta, 'Entrega_diaria_papeleria_'.$dia->format('d-m-Y').'.xlsx', $this->admin);
        $this->cargas->confirmar($carga, $this->admin);
        @unlink($ruta);
    }

    /**
     * La administradora elige la zona de las direcciones nuevas.
     */
    private function zonificarPendientes(int $dejar = 0): void
    {
        $pendientes = Direccion::query()->with('ciudad')->sinZona()->whereHas('guias')->get();

        foreach ($pendientes->slice($dejar) as $direccion) {
            [$codigo, $zona] = explode('|', $this->zonaDeComercio[$direccion->clave_normalizada] ?? 'BOG|Z01');
            $destino = Zona::query()->whereHas('seccional', fn ($q) => $q->where('codigo', $codigo))->where('codigo', $zona)->first();

            if ($destino !== null && $destino->seccional_id === $direccion->ciudad->seccional_id) {
                $this->zonificacion->asignarZona($direccion, $destino, $this->admin);
            }
        }
    }

    private function publicar(string $codigo, CarbonImmutable $dia): void
    {
        $seccional = Seccional::query()->where('codigo', $codigo)->firstOrFail();
        $planeacion = $this->planeacion->obtener($seccional, $dia);
        $zonas = $this->planeacion->zonasDelDia($planeacion)->where('guias', '>', 0);

        if ($zonas->isEmpty()) {
            return;
        }

        foreach (self::EQUIPO[$codigo] as [, $cedula, , , $codigosZona]) {
            $repartidor = Repartidor::query()->with('user')->where('cedula', $cedula)->first();

            foreach ($zonas->whereIn('zona.codigo', $codigosZona) as $fila) {
                $this->planeacion->asignarZona($planeacion, $fila['zona'], $repartidor);
            }
        }

        $this->planeacion->publicar($planeacion, $this->admin);
    }

    /**
     * El repartidor organiza su ruta, sale y registra las visitas.
     */
    private function recorrer(Ruta $ruta, CarbonImmutable $salida, ?int $cuantas, bool $cerrar, ?CarbonImmutable $terminaEn = null): void
    {
        Carbon::setTestNow($salida);
        $paradas = $ruta->paradas()->enOrden()->with('guia')->get();

        // Las guías urgentes van primero; el resto conserva el orden guardado.
        $orden = $paradas->sortBy(fn (Parada $p): array => [$p->guia->es_urgente ? 0 : 1, $p->es_nueva ? 1 : 0])->pluck('id')->all();
        $this->rutas->guardarOrden($ruta, $orden);
        $this->rutas->iniciar($ruta, $salida);

        $pendientes = $ruta->paradas()->enOrden()->with('guia')->get();
        $visitar = $cuantas === null ? $pendientes : $pendientes->take($cuantas);
        $paso = $terminaEn ? max(4, (int) ($salida->diffInMinutes($terminaEn) / max(1, $visitar->count()))) : mt_rand(13, 22);
        $momento = $salida;

        foreach ($visitar->values() as $indice => $parada) {
            $momento = $terminaEn && $indice === $visitar->count() - 1 ? $terminaEn : $momento->addMinutes($paso + mt_rand(-2, 3));
            Carbon::setTestNow($momento);
            $this->visitar($parada, $ruta->repartidor, $momento);
        }

        if ($cerrar) {
            Carbon::setTestNow($momento->addMinutes(mt_rand(15, 35)));
            $esperados = $this->rutas->rollosPorDevolver($ruta->refresh())['esperados'];
            // De vez en cuando el cierre no cuadra, para que se vea la alerta.
            $this->rutas->cerrar($ruta, mt_rand(1, 14) === 1 ? max(0, $esperados - 2) : $esperados);
        }
    }

    private function visitar(Parada $parada, Repartidor $repartidor, CarbonImmutable $momento): void
    {
        $guia = $parada->guia;
        $azar = mt_rand(1, 100);
        $semilla = ++$this->semilla;

        // Las reprogramadas casi siempre se entregan en el segundo intento.
        $resultado = match (true) {
            $guia->intentos > 0 => $azar <= 92 ? ResultadoVisita::Entregado : ResultadoVisita::NoEntregado,
            $azar <= 89 => ResultadoVisita::Entregado,
            $azar <= 96 => ResultadoVisita::NoEntregado,
            default => ResultadoVisita::Anulado,
        };

        $base = [
            'uuid' => (string) Str::uuid(),
            'resultado' => $resultado,
            'registradaAt' => $momento,
            'latitud' => 4.60 + mt_rand(0, 1500) / 10000,
            'longitud' => -74.15 + mt_rand(0, 1100) / 10000,
            'precision' => (float) mt_rand(4, 18),
            'dispositivo' => ['Android · Moto G54', 'Android · Samsung A15', 'Android · Xiaomi Redmi 13'][$semilla % 3],
            'appVersion' => '1.0.0',
        ];

        $archivos = [];

        if ($resultado === ResultadoVisita::Entregado) {
            $archivos = [ImagenesDemo::foto($semilla), ImagenesDemo::firma($semilla)];
            $persona = self::PERSONAS[$semilla % count(self::PERSONAS)];

            $datos = new DatosVisita(...$base, ...[
                'rollosEntregados' => mt_rand(1, 22) === 1 ? max(1, $guia->rollos_solicitados - mt_rand(2, 6)) : $guia->rollos_solicitados,
                'recibeNombre' => $persona,
                'recibeCedula' => (string) mt_rand(10000000, 1099999999),
                'recibeTelefono' => '3'.mt_rand(10, 21).'555'.str_pad((string) mt_rand(100, 199), 4, '0', STR_PAD_LEFT),
                'autorizaDatos' => true,
                'foto' => new UploadedFile($archivos[0], 'foto.jpg', 'image/jpeg', null, true),
                'firma' => new UploadedFile($archivos[1], 'firma.png', 'image/png', null, true),
            ]);
        } else {
            $anulado = $resultado === ResultadoVisita::Anulado;
            $causal = Causal::query()->where('tipo', $resultado->tipoCausal())->where('codigo', 'not like', 'otro%')->where('codigo', '!=', 'solicitud_credibanco')->inRandomOrder()->first();
            $archivos = [ImagenesDemo::foto($semilla, fachada: true)];

            $datos = new DatosVisita(...$base, ...[
                'causalId' => $causal->id,
                'observacion' => $anulado
                    ? 'En la dirección funciona otro negocio; los vecinos indican que el comercio ya no está.'
                    : 'Reja abajo. El vecino informa que abren más tarde. Dejé aviso con el celador.',
                'fachada' => new UploadedFile($archivos[0], 'fachada.jpg', 'image/jpeg', null, true),
            ]);
        }

        $this->visitas->registrar($parada, $repartidor, $datos);

        foreach ($archivos as $archivo) {
            @unlink($archivo);
        }
    }

    private function crearRepartidores(): void
    {
        $password = config('pms.password_inicial') ?: 'PmsTrack2026*';

        foreach (self::EQUIPO as $codigo => $equipo) {
            $seccional = Seccional::query()->where('codigo', $codigo)->firstOrFail();

            foreach ($equipo as [$nombre, $cedula, $vehiculo, $placa, $zonas]) {
                $usuario = User::query()->create([
                    'name' => $nombre,
                    'email' => $cedula.'@repartidor.invalid',
                    'password' => $password,
                    'seccional_id' => $seccional->id,
                    'cargo' => 'Repartidor',
                ]);
                $usuario->assignRole(Rol::Repartidor->value);

                Repartidor::query()->create([
                    'user_id' => $usuario->id,
                    'seccional_id' => $seccional->id,
                    'cedula' => $cedula,
                    'celular' => '300555'.str_pad((string) mt_rand(100, 199), 4, '0', STR_PAD_LEFT),
                    'vehiculo_tipo' => $vehiculo,
                    'placa' => $placa,
                    'zona_habitual_id' => $zonas === [] ? null : Zona::query()->where('seccional_id', $seccional->id)->where('codigo', $zonas[0])->value('id'),
                    'disponible' => $zonas !== [],
                ]);
            }
        }
    }

    /**
     * Comercios ficticios con su dirección, repartidos por las zonas de cada seccional.
     */
    private function crearComercios(): void
    {
        $this->comercios = collect();
        $usados = [];
        $normalizador = app(NormalizadorDirecciones::class);

        foreach (self::GEOGRAFIA as $codigo => $zonas) {
            $lista = collect();
            $porZona = ['BOG' => 16, 'MED' => 12, 'CAL' => 12][$codigo];

            foreach ($zonas as $zona => [$ciudad, $via, $desde, $hasta, $cruceDesde, $cruceHasta, $sufijo]) {
                for ($i = 0; $i < $porZona; $i++) {
                    do {
                        $nombre = self::TIPOS[array_rand(self::TIPOS)].' '.self::NOMBRES[array_rand(self::NOMBRES)];
                    } while (isset($usados[$nombre.$codigo]));
                    $usados[$nombre.$codigo] = true;

                    $numero = mt_rand($desde, $hasta).['', '', '', 'A', 'B'][mt_rand(0, 4)];
                    $cruce = mt_rand($cruceDesde, $cruceHasta).['', '', 'A'][mt_rand(0, 2)];
                    $placa = str_pad((string) mt_rand(1, 98), 2, '0', STR_PAD_LEFT);
                    $palabra = ['CL' => ['Calle', 'Cll', 'CL'], 'KR' => ['Carrera', 'Cra', 'CR'], 'AV' => ['Avenida', 'Av', 'AV']][$via][mt_rand(0, 2)];

                    // La misma dirección puede llegar escrita de varias formas.
                    $direccion = match (mt_rand(0, 3)) {
                        0 => "{$palabra} {$numero}{$sufijo} # {$cruce}-{$placa}",
                        1 => "{$palabra} {$numero}{$sufijo} #{$cruce}-{$placa} local ".mt_rand(1, 9),
                        2 => "{$palabra} {$numero}{$sufijo} {$cruce} {$placa}, {$ciudad}",
                        default => "{$palabra} {$numero}{$sufijo} # {$cruce} - {$placa}",
                    };

                    $this->zonaDeComercio[$normalizador->normalizar($direccion, $ciudad)->clave] = $codigo.'|'.$zona;

                    $lista->push([
                        'cu' => 10000000 + count($usados) * 7919 % 89999999,
                        'nombre' => $nombre,
                        'ciudad' => $ciudad,
                        'direccion' => $direccion,
                        'visto' => false,
                    ]);
                }
            }

            $this->comercios[$codigo] = $lista->shuffle();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function excel(array $filas): string
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet()->setTitle('Hoja1');
        $encabezados = array_keys($filas[0]);
        $hoja->fromArray($encabezados, null, 'A1');

        foreach ($filas as $indice => $fila) {
            foreach ($encabezados as $columna => $nombre) {
                $valor = $fila[$nombre];
                $hoja->setCellValue([$columna + 1, $indice + 2], $valor instanceof CarbonImmutable ? FechaExcel::PHPToExcel($valor->toDateTime()) : $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'pmsdemo').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }

    /**
     * @return Collection<int, CarbonImmutable> Los últimos días hábiles, del más antiguo al más reciente.
     */
    private function diasHabilesAnteriores(CarbonImmutable $hasta, int $cantidad): Collection
    {
        $calendario = app(CalendarioLaboral::class);
        $dias = collect();
        $dia = $hasta->startOfDay();

        while ($dias->count() < $cantidad) {
            $dia = $dia->subDay();

            if ($calendario->esHabil($dia)) {
                $dias->prepend($dia);
            }
        }

        return $dias;
    }

    private function bodega(string $codigo): Bodega
    {
        return Bodega::query()->whereHas('seccional', fn ($q) => $q->where('codigo', $codigo))->firstOrFail();
    }
}
