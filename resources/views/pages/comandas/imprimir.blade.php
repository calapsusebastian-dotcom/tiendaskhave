<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda {{ $comanda->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            background: #fff;
            padding: 32px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #E8642E;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .logo img { height: 48px; }

        .folio-block { text-align: right; }
        .folio-block .label { font-size: 11px; color: #6b7280; margin-bottom: 2px; letter-spacing: 0.06em; text-transform: uppercase; }
        .folio-block .folio { font-size: 22px; font-weight: 700; color: #E8642E; font-family: monospace; }
        .folio-block .fecha { font-size: 11px; color: #666; margin-top: 4px; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 6px;
        }
        .badge-green  { background: #d1fae5; color: #065f46; }
        .badge-amber  { background: #fef3c7; color: #92400e; }
        .badge-red    { background: #fee2e2; color: #991b1b; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }

        .info-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .info-card .label {
            font-size: 10px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .info-card .value {
            font-size: 14px;
            font-weight: 700;
            color: #111;
        }

        .info-card .sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        .section-title {
            font-size: 10px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        thead tr {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        thead th {
            padding: 8px 12px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        thead th.right { text-align: right; }

        tbody tr { border-bottom: 1px solid #f3f4f6; }
        tbody tr:last-child { border-bottom: none; }

        tbody td {
            padding: 9px 12px;
            font-size: 12px;
            color: #374151;
            vertical-align: top;
        }

        .obs {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 2px;
            font-style: italic;
        }

        .td-right { text-align: right; }
        .td-center { text-align: center; }
        .td-bold { font-weight: 600; color: #111; }

        .total-row {
            border-top: 2px solid #e5e7eb;
            margin-top: 0;
        }

        .total-row td {
            padding: 12px 12px;
            font-size: 15px;
            font-weight: 700;
        }

        .total-row .total-label { color: #374151; }
        .total-row .total-value { text-align: right; color: #E8642E; }

        .notas-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 20px;
            color: #374151;
            font-size: 11px;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .firma-line {
            border-top: 1px solid #374151;
            padding-top: 6px;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
            margin-top: 48px;
        }

        .print-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #E8642E;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(232,100,46,0.4);
        }

        @media print {
            .print-btn { display: none; }
            body { padding: 16px; }
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <div class="logo">
            <img src="{{ asset('images/Logo Tiendas 2024.png') }}" alt="Tiendas Kahvé">
        </div>
        <div class="folio-block">
            <div class="label">Comanda</div>
            <div class="folio">{{ $comanda->folio }}</div>
            <div class="fecha">{{ $comanda->created_at->format('d/m/Y H:i') }}</div>
            @php
                [$bc, $bl] = match($comanda->estado) {
                    'abierta'   => ['green', 'Abierta'],
                    'en_cuenta' => ['amber', 'En cuenta'],
                    'cerrada'   => ['red',   'Cerrada'],
                    default     => ['amber', $comanda->estado],
                };
            @endphp
            <span class="badge badge-{{ $bc }}">{{ $bl }}</span>
        </div>
    </div>

    {{-- Info: Mesa / Tienda / Mesero --}}
    <div class="info-grid">
        <div class="info-card">
            <div class="label">Mesa</div>
            <div class="value">Mesa {{ $comanda->mesa->numero }}</div>
            @if($comanda->mesa->nombre)
                <div class="sub">{{ $comanda->mesa->nombre }}</div>
            @endif
        </div>
        <div class="info-card">
            <div class="label">Tienda</div>
            <div class="value">{{ $comanda->tienda->nombre }}</div>
            <div class="sub">{{ $comanda->tienda->direccion }}</div>
        </div>
        <div class="info-card">
            <div class="label">Atendido por</div>
            <div class="value">{{ $comanda->mesero?->name ?? '—' }}</div>
            <div class="sub">{{ $comanda->created_at->diffForHumans() }}</div>
        </div>
    </div>

    {{-- Cliente --}}
    @if($comanda->cliente_nombre)
    <div class="section-title">Datos del cliente</div>
    <div class="info-grid" style="margin-bottom:24px;">
        <div class="info-card">
            <div class="label">Nombre</div>
            <div class="value" style="font-size:13px;">{{ $comanda->cliente_nombre }}</div>
        </div>
        <div class="info-card">
            <div class="label">Cédula / CC</div>
            <div class="value" style="font-size:13px;">{{ $comanda->cliente_cc ?? '—' }}</div>
        </div>
        <div class="info-card">
            <div class="label">Teléfono</div>
            <div class="value" style="font-size:13px;">{{ $comanda->cliente_telefono ?? '—' }}</div>
            @if($comanda->cliente_correo)
                <div class="sub">{{ $comanda->cliente_correo }}</div>
            @endif
        </div>
    </div>
    @endif

    {{-- Productos --}}
    <div class="section-title">Productos</div>
    <table>
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th>Producto</th>
                <th class="right" style="width:60px">Cant.</th>
                <th class="right" style="width:90px">Precio unit.</th>
                <th class="right" style="width:90px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comanda->items as $i => $item)
                <tr>
                    <td style="color:#9ca3af">{{ $i + 1 }}</td>
                    <td>
                        @if($item->productoMenu->codigo)
                            <span style="font-family:monospace;font-size:10px;color:#9ca3af;background:#f3f4f6;padding:1px 5px;border-radius:4px;margin-right:5px;">{{ $item->productoMenu->codigo }}</span>
                        @endif
                        {{ $item->productoMenu->nombre }}
                        @if($item->observacion)
                            <div class="obs">{{ $item->observacion }}</div>
                        @endif
                    </td>
                    <td class="td-center td-bold">{{ $item->cantidad }}</td>
                    <td class="td-right">${{ number_format($item->precio_unitario, 0, '.', ',') }}</td>
                    <td class="td-right td-bold">${{ number_format($item->subtotal(), 0, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Total --}}
    <table class="total-row">
        <tr>
            <td class="total-label">Total</td>
            <td class="total-value">${{ number_format($comanda->items->sum(fn($i) => $i->subtotal()), 0, '.', ',') }}</td>
        </tr>
    </table>

    {{-- Notas --}}
    @if($comanda->notas)
        <div class="section-title" style="margin-top:20px">Notas</div>
        <div class="notas-box">{{ $comanda->notas }}</div>
    @endif

    {{-- Firmas --}}
    <div class="footer">
        <div><div class="firma-line">Mesero / Cajero</div></div>
        <div><div class="firma-line">Firma cliente</div></div>
    </div>

    <button class="print-btn" onclick="window.print()">Imprimir</button>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
