<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $comanda->folio }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background: #fff;
            width: 80mm;
            padding: 6mm 4mm 8mm;
        }

        .center  { text-align: center; }
        .right   { text-align: right; }
        .bold    { font-weight: bold; }
        .small   { font-size: 10px; }
        .large   { font-size: 16px; }
        .xlarge  { font-size: 20px; }

        .sep-solid  { border-top: 1px solid #000; margin: 4px 0; }
        .sep-dashed { border-top: 1px dashed #000; margin: 4px 0; }

        .row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 4px;
        }

        .row .nombre {
            flex: 1;
            word-break: break-word;
        }

        .row .precio {
            white-space: nowrap;
            font-weight: bold;
        }

        .obs {
            font-size: 10px;
            padding-left: 8px;
            color: #444;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 15px;
            font-weight: bold;
            margin: 4px 0;
        }

        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #E8642E;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            font-family: sans-serif;
        }

        .back-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-family: sans-serif;
        }

        @media print {
            .print-btn { display: none; }
            body { width: 80mm; }
        }
    </style>
</head>
<body>

    {{-- Encabezado --}}
    <div class="center">
        <div class="xlarge bold">{{ $comanda->tienda->nombre }}</div>
        @if($comanda->tienda->direccion)
            <div class="small">{{ $comanda->tienda->direccion }}</div>
        @endif
    </div>

    <div class="sep-dashed"></div>

    <div class="center">
        <div class="bold">*** COMANDA ***</div>
        <div class="large bold">{{ $comanda->folio }}</div>
        <div class="small">{{ $comanda->created_at->format('d/m/Y H:i') }}</div>
    </div>

    <div class="sep-dashed"></div>

    {{-- Mesa y mesero --}}
    <div>Mesa: <span class="bold">{{ $comanda->mesa->nombre ?: 'Mesa '.$comanda->mesa->numero }}</span></div>
    <div>Mesero: <span class="bold">{{ $comanda->mesero?->name ?? '—' }}</span></div>

    <div class="sep-solid"></div>

    {{-- Items --}}
    @php $total = 0; @endphp
    @foreach($comanda->items as $item)
        @php
            $sub   = $item->subtotal();
            $total += $sub;
            $precio = '$' . number_format($sub, 0, '.', '.');
        @endphp
        <div class="row">
            <div class="nombre">
                <span class="bold">{{ $item->cantidad }}x</span> {{ $item->productoMenu?->nombre ?? '(eliminado)' }}
            </div>
            <div class="precio">{{ $precio }}</div>
        </div>
        @if($item->observacion)
            <div class="obs">&gt; {{ $item->observacion }}</div>
        @endif
    @endforeach

    <div class="sep-dashed"></div>

    <div class="total-row">
        <span>TOTAL</span>
        <span>${{ number_format($total, 0, '.', '.') }}</span>
    </div>

    <div class="sep-solid"></div>

    {{-- Cliente --}}
    @if($comanda->cliente_nombre)
        <div class="small">
            <div>Cliente: <span class="bold">{{ $comanda->cliente_nombre }}</span></div>
            @if($comanda->cliente_cc)
                <div>CC: {{ $comanda->cliente_cc }}</div>
            @endif
            @if($comanda->cliente_telefono)
                <div>Tel: {{ $comanda->cliente_telefono }}</div>
            @endif
        </div>
        <div class="sep-dashed"></div>
    @endif

    {{-- Pie --}}
    <div class="center small">
        <div class="bold">¡Gracias por su visita!</div>
        <div>Vuelva pronto</div>
    </div>

    <a href="{{ route('comandas.mesas') }}" class="back-btn">← Mesas</a>
    <button class="print-btn" onclick="window.print()">Imprimir</button>

    <script>window.onload = () => window.print();</script>
</body>
</html>
