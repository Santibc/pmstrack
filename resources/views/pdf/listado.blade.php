<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Servicios e histórico</title>
    <style>
        @page { margin: 30px 30px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2328; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        .tenue { color: #6b7482; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #1f2328; color: #fff; text-align: left; padding: 6px 7px; font-size: 8.5px; }
        td { padding: 5px 7px; border-bottom: 1px solid #e6e8ec; vertical-align: top; }
        tr:nth-child(even) td { background: #f7f8fa; }
        .derecha { text-align: right; }
        .pie { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 8px; color: #6b7482; }
    </style>
</head>
<body>
    <div class="pie">PMS Track · Precision Marketing Solutions Ltda. · generado el {{ now()->isoFormat('D MMM YYYY, h:mm a') }} · Desarrollado por {{ config('pms.desarrollador.nombre') }} · {{ config('pms.desarrollador.telefono') }}</div>

    <h1>Servicios e histórico</h1>
    <div class="tenue">
        {{ $guias->count() }} servicios
        @if (! empty($filtros['desde']) && ! empty($filtros['hasta'])) · del {{ \Carbon\Carbon::parse($filtros['desde'])->isoFormat('D MMM YYYY') }} al {{ \Carbon\Carbon::parse($filtros['hasta'])->isoFormat('D MMM YYYY') }} @endif
        @if ($recortado) · se muestran los {{ $maximo }} más recientes; descarga el Excel para verlos todos @endif
    </div>

    <table>
        <thead>
            <tr><th>Servicio</th><th>Registro</th><th>Comercio</th><th>Código único</th><th>Dirección</th><th>Ciudad</th><th class="derecha">Rollos</th><th>Estado</th><th>Entrega / motivo</th><th>ANS</th></tr>
        </thead>
        <tbody>
            @foreach ($guias as $guia)
                <tr>
                    <td><strong>{{ $guia->servicio }}</strong></td>
                    <td>{{ $guia->fecha_registro->format('d/m/Y H:i') }}</td>
                    <td>{{ $guia->nombre_comercio }}</td>
                    <td>{{ $guia->comercio->codigo_unico }}</td>
                    <td>{{ $guia->direccionCompleta() }}</td>
                    <td>{{ $guia->ciudad->nombre }}</td>
                    <td class="derecha">{{ $guia->rollos_solicitados }}</td>
                    <td>{{ $guia->estado->etiquetaServicio() }}</td>
                    <td>{{ $guia->entregada_at?->format('d/m/Y H:i') ?? $guia->ultimaVisita?->causal?->nombre ?? '—' }}</td>
                    <td>{{ $guia->semaforoTexto() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
