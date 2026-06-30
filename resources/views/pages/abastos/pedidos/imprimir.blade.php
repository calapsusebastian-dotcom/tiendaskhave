<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de compra {{ $pedido->folio }}</title>
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
        .folio-block .folio { font-size: 20px; font-weight: 700; color: #E8642E; font-family: monospace; }
        .folio-block .fecha { font-size: 11px; color: #666; margin-top: 4px; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 6px;
        }
        .badge-amber   { background: #fef3c7; color: #92400e; }
        .badge-blue    { background: #dbeafe; color: #1e40af; }
        .badge-violet  { background: #ede9fe; color: #5b21b6; }
        .badge-green   { background: #d1fae5; color: #065f46; }
        .badge-red     { background: #fee2e2; color: #991b1b; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .info-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
        }

        .info-card .label {
            font-size: 10px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .info-card .value {
            font-size: 13px;
            font-weight: 600;
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
            margin-bottom: 24px;
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

        thead th:last-child { text-align: right; }

        tbody tr { border-bottom: 1px solid #f3f4f6; }
        tbody tr:last-child { border-bottom: none; }

        tbody td {
            padding: 9px 12px;
            font-size: 12px;
            color: #374151;
        }

        tbody td:last-child {
            text-align: right;
            font-weight: 600;
            color: #111;
        }

        tfoot tr { border-top: 2px solid #e5e7eb; }
        tfoot td {
            padding: 10px 12px;
            font-size: 14px;
            font-weight: 700;
        }
        tfoot td:last-child { text-align: right; color: #E8642E; }

        .notas {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 24px;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-top: 40px;
        }

        .firma-line {
            border-top: 1px solid #374151;
            padding-top: 6px;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
            margin-top: 40px;
        }

        .print-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #E8642E;
            color: white;
            border: none;
            padding: 10px 20px;
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
            <div style="font-size:11px; color:#6b7280; margin-bottom:2px;">ORDEN DE COMPRA</div>
            <div class="folio">{{ $pedido->folio }}</div>
            <div class="fecha">{{ $pedido->created_at->format('d/m/Y H:i') }}</div>
            @php $badges = [
                'por_aprobar' => ['amber',  'Por aprobar'],
                'pendiente'   => ['blue',   'Pendiente'],
                'enviado'     => ['violet', 'Enviado'],
                'recibido'    => ['green',  'Recibido'],
                'rechazado'   => ['red',    'Rechazado'],
            ]; [$bc, $bl] = $badges[$pedido->estado] ?? ['amber', $pedido->estado]; @endphp
            <span class="badge badge-{{ $bc }}">{{ $bl }}</span>
        </div>
    </div>

    {{-- Info tienda / proveedor --}}
    <div class="info-grid">
        <div class="info-card">
            <div class="label">Tienda</div>
            <div class="value">{{ $pedido->tienda->nombre }}</div>
            <div class="sub">{{ $pedido->tienda->direccion }}</div>
        </div>
        <div class="info-card">
            <div class="label">Proveedor</div>
            <div class="value">{{ $pedido->proveedor->nombre }}</div>
            <div class="sub">{{ $pedido->proveedor->categoria }}@if($pedido->proveedor->telefono) · {{ $pedido->proveedor->telefono }}@endif</div>
        </div>
    </div>

    {{-- Productos --}}
    <div class="section-title">Productos solicitados</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Unidad</th>
                <th style="text-align:center">Cant.</th>
                <th style="text-align:right">Precio unit.</th>
                <th style="text-align:right">Subtotal</th>
                <th style="text-align:right">Con IVA</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->items as $i => $item)
                <tr>
                    <td style="color:#9ca3af">{{ $i + 1 }}</td>
                    <td>
                        {{ $item->materiaPrima->nombre }}
                        @if($item->materiaPrima->codigo_producto)
                            <span style="font-family:monospace; color:#9ca3af; font-size:10px; margin-left:6px">{{ $item->materiaPrima->codigo_producto }}</span>
                        @endif
                    </td>
                    <td style="color:#6b7280">{{ $item->materiaPrima->unidad }}</td>
                    <td style="text-align:center; font-weight:600">{{ $item->cantidad }}</td>
                    <td style="text-align:right">${{ number_format($item->precio_unitario, 2) }}</td>
                    <td style="text-align:right">${{ number_format($item->subtotalBase(), 2) }}</td>
                    <td style="text-align:right; font-weight:600">
                        ${{ number_format($item->subtotal(), 2) }}
                        <div style="font-size:10px; color:#6b7280; font-weight:normal">IVA {{ (int)$item->iva > 0 ? $item->iva.'%' : 'Exento' }}</div>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Total con IVA</td>
                <td>${{ number_format($pedido->total(), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Notas --}}
    @if($pedido->notas)
        <div class="section-title">Notas</div>
        <div class="notas">{{ $pedido->notas }}</div>
    @endif

    {{-- Firmas --}}
    <div class="footer">
        <div>
            <div class="firma-line">Solicitado por</div>
        </div>
        <div>
            <div class="firma-line">Autorizado por</div>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">Imprimir</button>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
