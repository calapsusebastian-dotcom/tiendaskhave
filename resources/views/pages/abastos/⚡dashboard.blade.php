<?php

use App\Models\Pedido;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard · Logística Proveedores'), Lazy] class extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php
    $porAprobar = Pedido::where('estado', 'por_aprobar')->count();
    $enCurso    = Pedido::whereIn('estado', ['enviado'])->count();
    $recibidos  = Pedido::where('estado', 'recibido')->count();
    $gasto      = \DB::table('pedido_items')
                    ->join('pedidos', 'pedidos.id', '=', 'pedido_items.pedido_id')
                    ->where('pedidos.estado', 'recibido')
                    ->whereMonth('pedidos.updated_at', now()->month)
                    ->whereYear('pedidos.updated_at', now()->year)
                    ->sum(\DB::raw('pedido_items.cantidad * pedido_items.precio_unitario'));
    $recientes  = Pedido::with(['tienda','proveedor'])
                    ->withSum('items as total', \DB::raw('cantidad * precio_unitario'))
                    ->latest()->limit(8)->get();
@endphp

<div class="space-y-4"
    x-data
    x-init="echoWhen(e => e.channel('logistica').listen('.PedidoActualizado', () => window.dispatchEvent(new CustomEvent('pedido-actualizado'))))"
    @pedido-actualizado.window="$wire.$refresh()">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Dashboard</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Logística Proveedores · {{ now()->format('d M Y') }}</p>
        </div>
        <flux:button href="{{ route('abastos.pedidos.crear') }}" variant="primary" icon="plus" size="sm" wire:navigate>
            Nuevo pedido
        </flux:button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['icon' => 'clock',        'stripe' => 'bg-amber-400',   'bg' => 'bg-amber-50 dark:bg-amber-900/20',     'text' => 'text-amber-500 dark:text-amber-400',   'value' => $porAprobar,                        'label' => 'Por aprobar'],
            ['icon' => 'truck',        'stripe' => 'bg-blue-400',    'bg' => 'bg-blue-50 dark:bg-blue-900/20',       'text' => 'text-blue-500 dark:text-blue-400',     'value' => $enCurso,                           'label' => 'En curso'],
            ['icon' => 'check-circle', 'stripe' => 'bg-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'text' => 'text-emerald-500 dark:text-emerald-400','value' => $recibidos,                         'label' => 'Recibidos'],
            ['icon' => 'banknotes',    'stripe' => 'bg-[#E8642E]',   'bg' => 'bg-orange-50 dark:bg-orange-900/20',   'text' => 'text-[#E8642E]',                       'value' => '$'.number_format($gasto,0,'.',','), 'label' => 'Gasto del mes'],
        ] as $s)
            <div class="relative bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4 overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-0.5 {{ $s['stripe'] }}"></div>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] text-zinc-400 font-medium">{{ $s['label'] }}</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white mt-1 leading-none">{{ $s['value'] }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl {{ $s['bg'] }} flex items-center justify-center shrink-0">
                        <flux:icon :icon="$s['icon']" class="w-5 h-5 {{ $s['text'] }}" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>


    {{-- Pedidos recientes --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-zinc-100 dark:border-zinc-800">
            <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wide">Pedidos recientes</p>
            <a href="{{ route('abastos.pedidos.index') }}" wire:navigate
               class="text-xs text-[#E8642E] hover:underline font-medium">Ver todos →</a>
        </div>
        <table class="w-full">
            <thead>
                <tr class="bg-zinc-50 dark:bg-zinc-800/40">
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-4">Folio</th>
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Tienda</th>
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3 hidden md:table-cell">Proveedor</th>
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Estado</th>
                    <th class="text-right text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-4">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                @foreach($recientes as $pedido)
                    @php [$bc,$bl] = match($pedido->estado) {
                        'por_aprobar' => ['amber','Por aprobar'],
                        'pendiente'   => ['blue','Pendiente'],
                        'enviado'     => ['violet','Enviado'],
                        'recibido'    => ['green','Recibido'],
                        'rechazado'   => ['red','Rechazado'],
                        default       => ['zinc',$pedido->estado],
                    }; @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                        <td class="py-2 px-4 font-mono text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $pedido->folio }}</td>
                        <td class="py-2 px-3">
                            <div class="flex items-center gap-1.5">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white shrink-0"
                                     style="background-color:{{ $pedido->tienda->color }}">{{ $pedido->tienda->codigo }}</div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400 hidden sm:inline">{{ $pedido->tienda->nombre }}</span>
                            </div>
                        </td>
                        <td class="py-2 px-3 text-xs text-zinc-400 hidden md:table-cell">{{ $pedido->proveedor->nombre }}</td>
                        <td class="py-2 px-3"><flux:badge color="{{ $bc }}" size="sm">{{ $bl }}</flux:badge></td>
                        <td class="py-2 px-4 text-right text-xs font-semibold text-zinc-900 dark:text-white">${{ number_format($pedido->total ?? 0, 0, '.', ',') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
