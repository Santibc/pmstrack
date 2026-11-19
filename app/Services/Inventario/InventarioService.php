<?php

declare(strict_types=1);

namespace App\Services\Inventario;

use App\Enums\EstadoParada;
use App\Enums\ResultadoVisita;
use App\Enums\TipoMovimiento;
use App\Exceptions\OperacionNoPermitida;
use App\Models\Bodega;
use App\Models\MovimientoInventario;
use App\Models\Ruta;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventario de rollos por bodega (documento técnico 5.6).
 * Saldo = entradas − salidas a ruta + devoluciones ± ajustes.
 *
 * Es el único punto que modifica el saldo de una bodega: cada cambio queda
 * como un movimiento con el saldo resultante.
 */
final class InventarioService
{
    /**
     * Remisión de rollos que llega de Credibanco.
     */
    public function entrada(Bodega $bodega, int $cantidad, string $remision, ?CarbonInterface $fecha, User $usuario): MovimientoInventario
    {
        $this->exigirPositivo($cantidad);

        return $this->registrar($bodega, TipoMovimiento::Entrada, $cantidad, [
            'detalle' => 'Remisión Credibanco '.$remision,
            'remision_numero' => $remision,
            'remision_fecha' => $fecha ?? today(),
            'origen' => 'web',
            'user_id' => $usuario->id,
        ]);
    }

    /**
     * Rollos que salen de bodega con un repartidor al publicar la planeación.
     */
    public function salidaRuta(Ruta $ruta, int $cantidad, string $detalle, ?User $usuario, string $origen = 'planeacion'): MovimientoInventario
    {
        $this->exigirPositivo($cantidad);

        return $this->registrar($this->bodegaDe($ruta), TipoMovimiento::SalidaRuta, -$cantidad, [
            'detalle' => $detalle,
            'origen' => $origen,
            'ruta_id' => $ruta->id,
            'repartidor_id' => $ruta->repartidor_id,
            'user_id' => $usuario?->id,
        ]);
    }

    /**
     * Registra lo que el repartidor realmente sacó de bodega (cajas completas o
     * rollos sueltos). La diferencia frente a lo ya descontado al publicar la
     * planeación queda como una salida adicional o como una devolución.
     */
    public function ajustarDespacho(Ruta $ruta, int $rollosReales, User $usuario): ?MovimientoInventario
    {
        if ($ruta->estaCerrada()) {
            throw new OperacionNoPermitida('La ruta ya está cerrada: usa un ajuste de inventario.');
        }

        if ($rollosReales < 0) {
            throw new OperacionNoPermitida('La cantidad de rollos no puede ser negativa.');
        }

        $diferencia = $rollosReales - $ruta->rollos_salida;

        if ($diferencia === 0) {
            return null;
        }

        return DB::transaction(function () use ($ruta, $rollosReales, $diferencia, $usuario): MovimientoInventario {
            $ruta->loadMissing('repartidor.user');
            $ruta->update(['rollos_salida' => $rollosReales]);

            return $diferencia > 0
                ? $this->salidaRuta($ruta, $diferencia, $ruta->repartidor->nombre.' · despacho adicional', $usuario, 'web')
                : $this->devolucion($ruta, abs($diferencia), $ruta->repartidor->nombre.' · ajuste del despacho', 'web', $usuario);
        });
    }

    /**
     * Rollos que el repartidor devuelve a bodega al cerrar su ruta.
     */
    public function devolucion(Ruta $ruta, int $cantidad, string $detalle, string $origen = 'app', ?User $usuario = null): MovimientoInventario
    {
        $this->exigirPositivo($cantidad);

        return $this->registrar($this->bodegaDe($ruta), TipoMovimiento::Devolucion, $cantidad, [
            'detalle' => $detalle,
            'origen' => $origen,
            'ruta_id' => $ruta->id,
            'repartidor_id' => $ruta->repartidor_id,
            'user_id' => $usuario?->id,
        ]);
    }

    /**
     * Ajuste manual, con motivo obligatorio. La cantidad lleva signo.
     */
    public function ajuste(Bodega $bodega, int $cantidad, string $motivo, User $usuario): MovimientoInventario
    {
        if ($cantidad === 0) {
            throw new OperacionNoPermitida('El ajuste debe sumar o restar al menos un rollo.');
        }

        if (trim($motivo) === '') {
            throw new OperacionNoPermitida('El ajuste necesita un motivo.');
        }

        return $this->registrar($bodega, TipoMovimiento::Ajuste, $cantidad, [
            'detalle' => 'Ajuste de inventario',
            'motivo' => $motivo,
            'origen' => 'web',
            'user_id' => $usuario->id,
        ]);
    }

    /**
     * Indicadores de la bodega para un día.
     *
     * @return array{en_bodega: int, saldo_apertura: int, entraron: int, salieron: int, volvieron: int, en_la_calle: int, dias_cobertura: int|null, ultima_remision: string|null, guias_en_ruta: int, zonas_en_ruta: int, visitas_sin_entrega: int, repartidores_con_rollos: int}
     */
    public function resumenDelDia(Bodega $bodega, CarbonInterface $fecha): array
    {
        $movimientos = $bodega->movimientos()->whereDate('created_at', $fecha)->get();
        $primero = $movimientos->sortBy('id')->first();
        $porRepartidor = $this->rollosEnLaCalle($bodega, $fecha);

        $rutas = Ruta::query()->where('seccional_id', $bodega->seccional_id)->whereDate('fecha', $fecha)
            ->withCount('paradas')
            ->get();

        return [
            'en_bodega' => (int) $bodega->saldo,
            'saldo_apertura' => $primero ? $primero->saldo - $primero->cantidad : (int) $bodega->saldo,
            'entraron' => (int) $movimientos->where('tipo', TipoMovimiento::Entrada)->sum('cantidad'),
            'salieron' => (int) abs($movimientos->where('tipo', TipoMovimiento::SalidaRuta)->sum('cantidad')),
            'volvieron' => (int) $movimientos->where('tipo', TipoMovimiento::Devolucion)->sum('cantidad'),
            'en_la_calle' => (int) $porRepartidor->sum('en_mano'),
            'dias_cobertura' => $this->diasDeCobertura($bodega, $fecha),
            'ultima_remision' => $movimientos->where('tipo', TipoMovimiento::Entrada)->sortByDesc('id')->first()?->remision_numero,
            'guias_en_ruta' => (int) $rutas->sum('paradas_count'),
            'zonas_en_ruta' => DB::table('paradas')->whereIn('ruta_id', $rutas->pluck('id'))->whereIn('estado', array_map(fn (EstadoParada $e) => $e->value, EstadoParada::activas()))->distinct()->count('zona_id'),
            'visitas_sin_entrega' => DB::table('paradas')->whereIn('ruta_id', $rutas->pluck('id'))->whereIn('estado', [EstadoParada::NoEntregada->value, EstadoParada::Anulada->value])->count(),
            'repartidores_con_rollos' => $porRepartidor->where('en_mano', '>', 0)->count(),
        ];
    }

    /**
     * Rollos que cada repartidor tiene en la calle en la fecha.
     *
     * @return Collection<int, array{ruta: Ruta, salieron: int, entregados: int, devueltos: int|null, en_mano: int}>
     */
    public function rollosEnLaCalle(Bodega $bodega, CarbonInterface $fecha): Collection
    {
        return Ruta::query()
            ->with('repartidor.user')
            ->where('seccional_id', $bodega->seccional_id)
            ->whereDate('fecha', $fecha)
            ->withSum(['visitas as rollos_entregados_sum' => fn ($q) => $q->whereNull('anulada_at')->where('resultado', ResultadoVisita::Entregado)], 'rollos_entregados')
            ->orderByDesc('rollos_salida')
            ->get()
            ->map(function (Ruta $ruta): array {
                $entregados = (int) $ruta->rollos_entregados_sum;

                return [
                    'ruta' => $ruta,
                    'salieron' => (int) $ruta->rollos_salida,
                    'entregados' => $entregados,
                    'devueltos' => $ruta->estaCerrada() ? (int) $ruta->rollos_devueltos_declarados : null,
                    'en_mano' => $ruta->estaCerrada() ? 0 : max(0, $ruta->rollos_salida - $entregados),
                ];
            });
    }

    /**
     * Para cuántos días alcanza el saldo, según el consumo neto de los últimos 15 días.
     */
    private function diasDeCobertura(Bodega $bodega, CarbonInterface $fecha): ?int
    {
        $consumo = $bodega->movimientos()
            ->whereIn('tipo', [TipoMovimiento::SalidaRuta, TipoMovimiento::Devolucion])
            ->whereBetween('created_at', [$fecha->copy()->subDays(15)->startOfDay(), $fecha->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as dia, SUM(cantidad) as neto')
            ->groupBy('dia')
            ->pluck('neto')
            ->map(fn ($neto): int => (int) abs((int) $neto))
            ->filter();

        if ($consumo->isEmpty() || $bodega->saldo <= 0) {
            return null;
        }

        return (int) floor($bodega->saldo / max(1, $consumo->avg()));
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function registrar(Bodega $bodega, TipoMovimiento $tipo, int $cantidad, array $atributos): MovimientoInventario
    {
        return DB::transaction(function () use ($bodega, $tipo, $cantidad, $atributos): MovimientoInventario {
            $bloqueada = Bodega::query()->lockForUpdate()->findOrFail($bodega->id);
            $saldo = $bloqueada->saldo + $cantidad;

            $bloqueada->forceFill(['saldo' => $saldo])->save();
            $bodega->setAttribute('saldo', $saldo);

            return MovimientoInventario::query()->create([
                'bodega_id' => $bloqueada->id,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'saldo' => $saldo,
                ...$atributos,
            ]);
        });
    }

    private function bodegaDe(Ruta $ruta): Bodega
    {
        return Bodega::query()->where('seccional_id', $ruta->seccional_id)->firstOr(
            fn () => throw new OperacionNoPermitida('La seccional de la ruta no tiene bodega configurada.'),
        );
    }

    private function exigirPositivo(int $cantidad): void
    {
        if ($cantidad <= 0) {
            throw new OperacionNoPermitida('La cantidad de rollos debe ser mayor que cero.');
        }
    }
}
