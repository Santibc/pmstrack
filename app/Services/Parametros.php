<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Parametro;
use Illuminate\Support\Facades\Cache;

/**
 * Parámetros de operación (Configuración). Los valores por defecto viven aquí,
 * de modo que el sistema funciona aunque la tabla aún no tenga la fila.
 */
final class Parametros
{
    private const string CACHE = 'parametros.todos';

    /**
     * Definición de cada parámetro: valor por defecto, tipo, grupo, etiqueta y descripción.
     *
     * @var array<string, array{defecto: string, tipo: string, grupo: string, etiqueta: string, descripcion: string}>
     */
    public const array DEFINICION = [
        'ans_horas_defecto' => ['defecto' => '168', 'tipo' => 'entero', 'grupo' => 'ans', 'etiqueta' => 'ANS por defecto (horas)', 'descripcion' => 'Se usa cuando el campo HORARIO_DE_ATENCION no trae las horas.'],
        'ans_tipo_horas' => ['defecto' => 'calendario', 'tipo' => 'opcion', 'grupo' => 'ans', 'etiqueta' => 'Cómo se cuentan las horas del ANS', 'descripcion' => 'Calendario (corridas) o hábiles (lunes a viernes en horario laboral). Por confirmar con Credibanco.'],
        'ans_umbral_urgente_horas' => ['defecto' => '8', 'tipo' => 'entero', 'grupo' => 'ans', 'etiqueta' => 'ANS urgente hasta (horas)', 'descripcion' => 'Las guías con este ANS o menos se resaltan como urgentes.'],
        'ans_meta_cumplimiento' => ['defecto' => '95', 'tipo' => 'entero', 'grupo' => 'ans', 'etiqueta' => 'Meta de cumplimiento de ANS (%)', 'descripcion' => 'Referencia que se muestra en los dashboards.'],
        'horario_laboral_inicio' => ['defecto' => '08:30', 'tipo' => 'hora', 'grupo' => 'ans', 'etiqueta' => 'Inicio del horario laboral', 'descripcion' => 'Solo aplica si el ANS se cuenta en horas hábiles.'],
        'horario_laboral_fin' => ['defecto' => '17:30', 'tipo' => 'hora', 'grupo' => 'ans', 'etiqueta' => 'Fin del horario laboral', 'descripcion' => 'Solo aplica si el ANS se cuenta en horas hábiles.'],
        'sabado_habil' => ['defecto' => '0', 'tipo' => 'booleano', 'grupo' => 'operacion', 'etiqueta' => 'El sábado es día hábil', 'descripcion' => 'Define el siguiente día hábil al reprogramar una guía no entregada.'],
        'max_intentos' => ['defecto' => '3', 'tipo' => 'entero', 'grupo' => 'operacion', 'etiqueta' => 'Número máximo de intentos', 'descripcion' => 'Al llegar a este número de intentos fallidos la administradora decide si se devuelve a Credibanco.'],
        'guia_consecutivo' => ['defecto' => '1', 'tipo' => 'entero', 'grupo' => 'operacion', 'etiqueta' => 'Próximo consecutivo de guía PMS', 'descripcion' => 'Número con el que se arma la próxima guía (formato MES-D-0000).'],
        'rollos_por_caja' => ['defecto' => '360', 'tipo' => 'entero', 'grupo' => 'inventario', 'etiqueta' => 'Rollos por caja', 'descripcion' => 'Los rollos llegan de Credibanco en cajas. Se usa para mostrar y registrar el inventario en cajas y rollos sueltos.'],
        'alerta_sin_actividad_min' => ['defecto' => '45', 'tipo' => 'entero', 'grupo' => 'alertas', 'etiqueta' => 'Alerta sin actividad (minutos)', 'descripcion' => 'Avisa si un repartidor en ruta no registra visitas en este tiempo.'],
        'alerta_inicio_ruta_hora' => ['defecto' => '09:00', 'tipo' => 'hora', 'grupo' => 'alertas', 'etiqueta' => 'Hora límite para iniciar la ruta', 'descripcion' => 'Avisa si a esta hora el repartidor no ha tocado «Empezar ruta».'],
        'bloqueo_intentos' => ['defecto' => '5', 'tipo' => 'entero', 'grupo' => 'seguridad', 'etiqueta' => 'Intentos de ingreso antes de bloquear', 'descripcion' => 'Intentos fallidos consecutivos que bloquean temporalmente al usuario.'],
        'bloqueo_minutos' => ['defecto' => '15', 'tipo' => 'entero', 'grupo' => 'seguridad', 'etiqueta' => 'Duración del bloqueo (minutos)', 'descripcion' => 'Tiempo que el usuario queda bloqueado tras superar los intentos.'],
        'consulta_publica_limite' => ['defecto' => '10', 'tipo' => 'entero', 'grupo' => 'seguridad', 'etiqueta' => 'Consultas públicas por minuto (por IP)', 'descripcion' => 'Límite de la consulta sin iniciar sesión.'],
        'contacto_linea' => ['defecto' => '', 'tipo' => 'texto', 'grupo' => 'contacto', 'etiqueta' => 'Línea de servicio PMS', 'descripcion' => 'Se muestra en la consulta pública y en el comprobante.'],
        'contacto_direccion' => ['defecto' => '', 'tipo' => 'texto', 'grupo' => 'contacto', 'etiqueta' => 'Dirección de PMS', 'descripcion' => 'Se muestra en la consulta pública y en el comprobante.'],
        'app_version_actual' => ['defecto' => '1.0.0', 'tipo' => 'texto', 'grupo' => 'app', 'etiqueta' => 'Versión vigente de la app', 'descripcion' => 'La app avisa cuando hay una versión completa más reciente.'],
        'app_version_minima' => ['defecto' => '1.0.0', 'tipo' => 'texto', 'grupo' => 'app', 'etiqueta' => 'Versión mínima permitida', 'descripcion' => 'Por debajo de esta versión la app pide actualizar para continuar.'],
    ];

    /** @var array<string, string|null>|null */
    private ?array $valores = null;

    public function texto(string $clave): string
    {
        return (string) ($this->todos()[$clave] ?? self::DEFINICION[$clave]['defecto'] ?? '');
    }

    public function entero(string $clave): int
    {
        return (int) $this->texto($clave);
    }

    public function booleano(string $clave): bool
    {
        return filter_var($this->texto($clave), FILTER_VALIDATE_BOOLEAN);
    }

    public function rollosPorCaja(): int
    {
        return max(1, $this->entero('rollos_por_caja'));
    }

    public function maxIntentos(): int
    {
        return max(1, $this->entero('max_intentos'));
    }

    public function guardar(string $clave, ?string $valor): void
    {
        $definicion = self::DEFINICION[$clave] ?? ['tipo' => 'texto', 'grupo' => 'general', 'etiqueta' => $clave, 'descripcion' => ''];

        Parametro::query()->updateOrCreate(['clave' => $clave], [
            'valor' => $valor,
            'tipo' => $definicion['tipo'],
            'grupo' => $definicion['grupo'],
            'etiqueta' => $definicion['etiqueta'],
            'descripcion' => $definicion['descripcion'],
        ]);

        $this->olvidar();
    }

    public function olvidar(): void
    {
        $this->valores = null;
        Cache::forget(self::CACHE);
    }

    /**
     * @return array<string, string|null>
     */
    private function todos(): array
    {
        return $this->valores ??= Cache::remember(
            self::CACHE,
            now()->addHour(),
            fn (): array => Parametro::query()->pluck('valor', 'clave')->all(),
        );
    }
}
