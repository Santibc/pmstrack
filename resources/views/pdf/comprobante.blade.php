<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de entrega {{ $guia->servicio }}</title>
    <style>
        @page { margin: 34px 40px 60px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2328; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        h1 { font-size: 20px; margin: 0; letter-spacing: -0.3px; }
        h2 { font-size: 12px; margin: 0 0 8px; text-transform: none; color: #1f2328; }
        .tenue { color: #6b7482; }
        .naranja { color: #c2530a; }
        .cabecera td { vertical-align: middle; padding-bottom: 14px; border-bottom: 3px solid #f07d2e; }
        .sello { display: inline-block; padding: 5px 12px; border-radius: 20px; background: #e9f6ee; color: #1f7a40; font-weight: bold; font-size: 11px; }
        .grande { font-size: 26px; font-weight: bold; letter-spacing: -0.5px; }
        .caja { border: 1px solid #e6e8ec; border-radius: 8px; padding: 12px 14px; }
        .datos td { padding: 3px 0; vertical-align: top; }
        .datos td.etiqueta { width: 38%; color: #6b7482; }
        .corte { border-top: 1.5px dashed #d3d7de; margin: 16px 0; }
        .foto { width: 100%; border-radius: 8px; border: 1px solid #e6e8ec; }
        .firma { max-height: 96px; max-width: 100%; }
        .huella { font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; color: #6b7482; word-break: break-all; }
        .pie { position: fixed; bottom: -36px; left: 0; right: 0; font-size: 8.5px; color: #6b7482; border-top: 1px solid #e6e8ec; padding-top: 7px; }
    </style>
</head>
<body>
    <div class="pie">
        <table><tr>
            <td>Precision Marketing Solutions Ltda. · Operación de papelería para Credibanco @if ($linea) · {{ $linea }} @endif @if ($direccionPms) · {{ $direccionPms }} @endif</td>
            <td style="text-align: right;">PMS Track · Desarrollado por {{ config('pms.desarrollador.nombre') }} · {{ config('pms.desarrollador.telefono') }}</td>
        </tr></table>
    </div>

    <table class="cabecera"><tr>
        <td style="width: 34%;"><img src="{{ $logo }}" alt="PMS" style="height: 62px;"></td>
        <td>
            <h1>Comprobante de entrega</h1>
            <div class="tenue">Rollos de datáfono · generado el {{ now()->isoFormat('D [de] MMMM [de] YYYY, h:mm a') }}</div>
        </td>
        <td style="text-align: right; width: 22%;"><span class="sello">Entregado</span></td>
    </tr></table>

    <table style="margin-top: 16px;"><tr>
        <td style="width: 50%; vertical-align: top;">
            <div class="tenue">Servicio Credibanco</div>
            <div class="grande">{{ $guia->servicio }}</div>
            <div class="tenue">Guía PMS {{ $guia->numero_guia }}</div>
        </td>
        <td style="vertical-align: top; text-align: right;">
            <div class="tenue">Entregado el</div>
            <div style="font-size: 15px; font-weight: bold;">{{ $guia->entregada_at->isoFormat('dddd D [de] MMMM [de] YYYY') }}</div>
            <div style="font-size: 15px; font-weight: bold;">{{ $guia->entregada_at->isoFormat('h:mm a') }}</div>
            <div class="{{ $guia->cumplioAns() ? 'naranja' : 'tenue' }}" style="margin-top: 3px;">
                ANS {{ $guia->ans_horas }} h · {{ $guia->cumplioAns() ? 'cumplido' : 'superado' }} en {{ \App\Support\Duracion::corta($guia->minutosHastaEntrega()) }}
            </div>
        </td>
    </tr></table>

    <div class="corte"></div>

    <table><tr>
        <td style="width: 49%; vertical-align: top; padding-right: 10px;">
            <div class="caja">
                <h2>Datos del comercio</h2>
                <table class="datos">
                    <tr><td class="etiqueta">Comercio</td><td><strong>{{ $guia->nombre_comercio }}</strong></td></tr>
                    @if ($guia->cadena)<tr><td class="etiqueta">Cadena</td><td>{{ $guia->cadena }}</td></tr>@endif
                    <tr><td class="etiqueta">Código único</td><td>{{ $guia->comercio->codigo_unico }}</td></tr>
                    <tr><td class="etiqueta">Dirección</td><td>{{ $guia->direccionCompleta() }}</td></tr>
                    <tr><td class="etiqueta">Ciudad</td><td>{{ $guia->ciudad->nombre }} · Seccional {{ $guia->seccional->nombre }}</td></tr>
                    <tr><td class="etiqueta">Solicitud</td><td>{{ $guia->fecha_registro->isoFormat('D MMM YYYY, h:mm a') }}</td></tr>
                </table>
            </div>
        </td>
        <td style="vertical-align: top; padding-left: 10px;">
            <div class="caja">
                <h2>Datos de la entrega</h2>
                <table class="datos">
                    <tr><td class="etiqueta">Recibió</td><td><strong>{{ $visita->recibe_nombre }}</strong></td></tr>
                    <tr><td class="etiqueta">Cédula</td><td>{{ number_format((int) $visita->recibe_cedula, 0, ',', '.') }}</td></tr>
                    @if ($visita->recibe_telefono)<tr><td class="etiqueta">Teléfono</td><td>{{ $visita->recibe_telefono }}</td></tr>@endif
                    <tr><td class="etiqueta">Rollos</td><td><strong>{{ $visita->rollos_entregados }} entregados de {{ $visita->rollos_solicitados }}</strong></td></tr>
                    <tr><td class="etiqueta">Repartidor</td><td>{{ $visita->repartidor->nombre }}</td></tr>
                    <tr><td class="etiqueta">Intento</td><td>{{ $visita->numero_intento }}</td></tr>
                    <tr><td class="etiqueta">Datos personales</td><td>{{ $visita->autoriza_datos ? 'Autorización aceptada (Ley 1581 de 2012)' : 'Sin autorización registrada' }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <table style="margin-top: 14px;"><tr>
        <td style="width: 58%; vertical-align: top; padding-right: 10px;">
            <h2>Foto de la entrega</h2>
            @if ($foto)
                <img src="{{ $foto }}" class="foto" alt="Foto de la entrega">
            @else
                <div class="caja tenue">Sin foto.</div>
            @endif
        </td>
        <td style="vertical-align: top; padding-left: 10px;">
            <h2>Firma de quien recibe</h2>
            <div class="caja" style="text-align: center; min-height: 104px;">
                @if ($firma)
                    <img src="{{ $firma }}" class="firma" alt="Firma">
                @else
                    <span class="tenue">Sin firma.</span>
                @endif
            </div>

            <h2 style="margin-top: 14px;">Ubicación del registro</h2>
            <div class="caja">
                @if ($visita->tieneUbicacion())
                    <strong>{{ number_format($visita->latitud, 5, '.', '') }}, {{ number_format($visita->longitud, 5, '.', '') }}</strong><br>
                    <span class="tenue">Precisión del GPS: {{ round((float) $visita->precision_m) }} m</span>
                @else
                    <span class="tenue">El registro se hizo sin señal de GPS.</span>
                @endif
                @if ($visita->dispositivo)<br><span class="tenue">{{ $visita->dispositivo }}@if ($visita->app_version) · app {{ $visita->app_version }}@endif</span>@endif
            </div>
        </td>
    </tr></table>

    <div class="corte"></div>

    <div class="tenue" style="font-size: 9.5px;">
        Evidencia capturada en la app PMS Track en el momento de la entrega. Una visita registrada no se edita.
        @if ($huella)
            <br>Huella de integridad de la foto (SHA-256): <span class="huella">{{ $huella }}</span>
        @endif
    </div>
</body>
</html>
