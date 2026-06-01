<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Clientes</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; font-size: 12px; padding: 40px; line-height: 1.4; }
        .header { text-align: right; margin-bottom: 20px; font-size: 11px; }
        .title { text-align: center; font-weight: bold; font-size: 14px; margin: 30px 0 10px 0; letter-spacing: 2px; }
        .subtitle { text-align: center; font-size: 11px; margin-bottom: 20px; }
        .line { border-bottom: 1px solid #000; margin: 10px 0 20px 0; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { border: none; padding: 6px 8px; text-align: left; font-size: 10px; }
        table th { border-bottom: 1px solid #000; font-weight: bold; padding-bottom: 5px; text-align: center; }
        table td.centro { text-align: center; }
        .cliente-row { border-bottom: 1px solid #eee; }
        .cliente-row:last-child { border-bottom: 1px solid #ccc; }
        .total-row { font-weight: bold; background-color: #f5f5f5; border-top: 2px solid #000; }
        .footer { text-align: center; font-size: 10px; margin-top: 40px; color: #666; }
    </style>
</head>

<body>
    <div class="header">
        <div>{{ $fecha_reporte }}</div>
    </div>

    <div class="title">REPORTE DE CLIENTES</div>
    <div class="subtitle">Reporte General de Clientes</div>

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th class="centro">DNI</th>
                <th>Apellidos y Nombres</th>
                <th class="centro">Sexo</th>
                <th>Domicilio</th>
                <th>Ciudad</th>
                <th>Zona</th>
                <th>Dirección Negocio</th>
                <th>Giro</th>
                <th>Teléfonos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($clientes as $cliente)
                @php $negocio = $cliente->negocio; @endphp
                <tr class="cliente-row">
                    <td class="centro">{{ $cliente->DNI }}</td>
                    <td>{{ $cliente->NombresApellidos }}</td>
                    <td class="centro">{{ $cliente->Sexo === 'M' ? 'M' : ($cliente->Sexo === 'F' ? 'F' : '-') }}</td>
                    <td>{{ $cliente->Domicilio ?? '-' }}</td>
                    <td>{{ $negocio?->ciudad?->Nombre ?? '-' }}</td>
                    <td>{{ $negocio?->zona?->Nombre ?? '-' }}</td>
                    <td>{{ $negocio?->DireccionNegocio ?? '-' }}</td>
                    <td>{{ $negocio?->giro?->Descripcion ?? '-' }}</td>
                    <td>{{ $negocio?->telefonos?->pluck('Telefono')?->implode(', ') ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align: center;">No hay datos disponibles</td></tr>
            @endforelse
            @if($clientes->count() > 0)
                <tr class="total-row">
                    <td colspan="8" style="text-align: right;">TOTAL CLIENTES:</td>
                    <td class="centro">{{ $clientes->count() }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <p>Documento generado automáticamente por el sistema JALUD</p>
    </div>
</body>
</html>
