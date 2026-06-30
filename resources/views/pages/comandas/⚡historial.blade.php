<?php

use App\Models\Comanda;
use App\Models\Tienda;
use Flux\Flux;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Historial · Comandas'), Lazy] class extends Component
{
    use WithPagination;

    #[Url] public string $busqueda    = '';
    #[Url] public string $estado      = 'all';
    #[Url] public string $filtroTienda = 'all';
    #[Url] public string $desde       = '';
    #[Url] public string $hasta       = '';

    public ?Comanda $viendoComanda = null;

    public function updatingBusqueda():     void { $this->resetPage(); }
    public function updatingEstado():       void { $this->resetPage(); }
    public function updatingFiltroTienda(): void { $this->resetPage(); }
    public function updatingDesde():        void { $this->resetPage(); }
    public function updatingHasta():        void { $this->resetPage(); }

    public function mount(): void
    {
        if (auth()->user()->tienda_id) {
            $this->filtroTienda = (string) auth()->user()->tienda_id;
        }
    }

    public function ver(int $id): void
    {
        $this->viendoComanda = Comanda::with(['mesa.tienda', 'tienda', 'mesero', 'items.productoMenu'])
            ->findOrFail($id);
        Flux::modal('ver-comanda')->show();
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php
    $user    = auth()->user();
    $tiendas = Tienda::orderBy('nombre')->get();

    $comandas = Comanda::with(['mesa', 'tienda', 'mesero'])
        ->withCount('items')
        ->withSum('items as suma', \DB::raw('cantidad * precio_unitario'))
        ->when($user->tienda_id,
            fn($q) => $q->where('tienda_id', $user->tienda_id),
            fn($q) => $q->when($filtroTienda !== 'all', fn($q2) => $q2->where('tienda_id', $filtroTienda))
        )
        ->when($estado !== 'all', fn($q) => $q->where('estado', $estado))
        ->when($busqueda, fn($q) => $q->where(function($q2) use ($busqueda) {
            $q2->where('folio', 'like', '%'.$busqueda.'%')
               ->orWhere('jfac', 'like', '%'.$busqueda.'%')
               ->orWhereHas('mesa', fn($m) => $m->where('numero', 'like', '%'.$busqueda.'%')->orWhere('nombre', 'like', '%'.$busqueda.'%'))
               ->orWhereHas('mesero', fn($m) => $m->where('name', 'like', '%'.$busqueda.'%'));
        }))
        ->when($desde, fn($q) => $q->whereDate('created_at', '>=', $desde))
        ->when($hasta, fn($q) => $q->whereDate('created_at', '<=', $hasta))
        ->latest()
        ->paginate(20);
@endphp

<div class="max-w-5xl mx-auto space-y-4"
    x-data
    x-init="echoWhen(e => e.channel('comandas').listen('.ComandaActualizada', () => window.dispatchEvent(new CustomEvent('comanda-actualizada'))))"
    @comanda-actualizada.window="$wire.$refresh()">

    <div class="flex items-center gap-3">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Historial de comandas</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Registro de todas las comandas</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-3">
        <div class="flex flex-wrap gap-2">
            <flux:input wire:model.live.debounce.300ms="busqueda"
                        placeholder="Folio, mesa, mesero..."
                        icon="magnifying-glass" size="sm" class="flex-1 min-w-40" />

            <flux:select wire:model.live="estado" size="sm" class="w-36">
                <flux:select.option value="all">Todos los estados</flux:select.option>
                <flux:select.option value="abierta">Abierta</flux:select.option>
                <flux:select.option value="en_cuenta">En cuenta</flux:select.option>
                <flux:select.option value="cerrada">Cerrada</flux:select.option>
            </flux:select>

            @if(!$user->tienda_id)
                <flux:select wire:model.live="filtroTienda" size="sm" class="w-36">
                    <flux:select.option value="all">Todas las tiendas</flux:select.option>
                    @foreach($tiendas as $t)
                        <flux:select.option value="{{ $t->id }}">{{ $t->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model.live="desde" type="date" size="sm" class="w-36" />
            <flux:input wire:model.live="hasta" type="date" size="sm" class="w-36" />
        </div>
    </div>

    {{-- Tabla --}}
    @if($comandas->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
            <flux:icon.clipboard-document-list class="w-8 h-8 text-zinc-300 mb-2" />
            <p class="text-sm text-zinc-400">No hay comandas con estos filtros.</p>
        </div>
    @else
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Folio</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">JFAC</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Mesa</th>
                        @if(!$user->tienda_id)
                            <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Tienda</th>
                        @endif
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Mesero</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Estado</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Items</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Total</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Fecha</th>
                        <th class="px-4 py-2.5 w-20"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                    @foreach($comandas as $c)
                        @php
                            $total = $c->suma ?? 0;
                            [$badgeClass, $badgeLabel] = match($c->estado) {
                                'abierta'   => ['bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'Abierta'],
                                'en_cuenta' => ['bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'En cuenta'],
                                'cerrada'   => ['bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400', 'Cerrada'],
                                default     => ['bg-zinc-100 text-zinc-500', $c->estado],
                            };
                        @endphp
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors cursor-pointer"
                            wire:click="ver({{ $c->id }})">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs font-semibold text-[#E8642E]">{{ $c->folio }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($c->jfac)
                                    <span class="font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $c->jfac }}</span>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-zinc-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs text-zinc-700 dark:text-zinc-300">
                                    Mesa {{ $c->mesa->numero }}{{ $c->mesa->nombre ? ' · '.$c->mesa->nombre : '' }}
                                </span>
                            </td>
                            @if(!$user->tienda_id)
                                <td class="px-4 py-3">
                                    <span class="text-xs text-zinc-500">{{ $c->tienda->nombre }}</span>
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <span class="text-xs text-zinc-500">{{ $c->mesero?->name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $badgeClass }}">
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-xs text-zinc-500">{{ $c->items_count }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                    ${{ number_format($total, 0, '.', ',') }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-xs text-zinc-500">{{ $c->created_at->format('d/m/Y') }}</p>
                                <p class="text-[10px] text-zinc-400">{{ $c->created_at->format('H:i') }}</p>
                            </td>
                            <td class="px-4 py-3" wire:click.stop>
                                <a href="{{ route('comandas.imprimir', $c) }}" target="_blank"
                                   class="p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-400 hover:text-zinc-600 transition-colors inline-flex items-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($comandas->hasPages())
            <div class="mt-2">
                {{ $comandas->links() }}
            </div>
        @endif
    @endif

    {{-- Modal detalle comanda --}}
    <flux:modal name="ver-comanda" class="w-full max-w-xl">
        @if($viendoComanda)
            @php
                $vc = $viendoComanda;
                $vcTotal = $vc->items->sum(fn($i) => $i->cantidad * (float)$i->precio_unitario);
                [$vcBadgeClass, $vcBadgeLabel] = match($vc->estado) {
                    'abierta'   => ['bg-emerald-100 text-emerald-700', 'Abierta'],
                    'en_cuenta' => ['bg-amber-100 text-amber-700', 'En cuenta'],
                    'cerrada'   => ['bg-zinc-100 text-zinc-500', 'Cerrada'],
                    default     => ['bg-zinc-100 text-zinc-500', $vc->estado],
                };
            @endphp

            {{-- Encabezado modal --}}
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <p class="font-mono text-lg font-bold text-[#E8642E]">{{ $vc->folio }}</p>
                    <p class="text-xs text-zinc-400 mt-0.5">{{ $vc->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <span class="text-[10px] px-2.5 py-1 rounded-full font-semibold {{ $vcBadgeClass }}">
                    {{ $vcBadgeLabel }}
                </span>
            </div>

            {{-- Info --}}
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3">
                    <p class="text-[9px] font-semibold text-zinc-400 uppercase tracking-wide mb-1">Mesa</p>
                    <p class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Mesa {{ $vc->mesa->numero }}</p>
                    @if($vc->mesa->nombre)
                        <p class="text-[10px] text-zinc-400">{{ $vc->mesa->nombre }}</p>
                    @endif
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3">
                    <p class="text-[9px] font-semibold text-zinc-400 uppercase tracking-wide mb-1">Tienda</p>
                    <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 leading-tight">{{ $vc->tienda->nombre }}</p>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-3">
                    <p class="text-[9px] font-semibold text-zinc-400 uppercase tracking-wide mb-1">Mesero</p>
                    <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 leading-tight">{{ $vc->mesero?->name ?? '—' }}</p>
                </div>
            </div>

            {{-- Cliente --}}
            @if($vc->cliente_nombre)
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                    <p class="text-[9px] font-semibold text-blue-400 uppercase tracking-wide mb-1">Cliente</p>
                    <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 leading-tight">{{ $vc->cliente_nombre }}</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                    <p class="text-[9px] font-semibold text-blue-400 uppercase tracking-wide mb-1">CC</p>
                    <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $vc->cliente_cc ?? '—' }}</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                    <p class="text-[9px] font-semibold text-blue-400 uppercase tracking-wide mb-1">Teléfono</p>
                    <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $vc->cliente_telefono ?? '—' }}</p>
                    @if($vc->cliente_correo)
                        <p class="text-[9px] text-zinc-400 mt-0.5">{{ $vc->cliente_correo }}</p>
                    @endif
                </div>
            </div>
            @endif

            {{-- Productos --}}
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden mb-4">
                <table class="w-full">
                    <thead>
                        <tr class="bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-3 py-2 text-left text-[9px] font-semibold text-zinc-500 uppercase tracking-wide">Producto</th>
                            <th class="px-3 py-2 text-center text-[9px] font-semibold text-zinc-500 uppercase tracking-wide w-12">Cant.</th>
                            <th class="px-3 py-2 text-right text-[9px] font-semibold text-zinc-500 uppercase tracking-wide w-24">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                        @foreach($vc->items as $item)
                            <tr>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        @if($item->productoMenu?->codigo)
                                            <span class="font-mono text-[9px] text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded shrink-0">{{ $item->productoMenu->codigo }}</span>
                                        @endif
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $item->productoMenu?->nombre ?? '(producto eliminado)' }}</span>
                                    </div>
                                    @if($item->observacion)
                                        <p class="text-[10px] text-zinc-400 italic mt-0.5 pl-0.5">{{ $item->observacion }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $item->cantidad }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                        ${{ number_format($item->cantidad * (float)$item->precio_unitario, 0, '.', ',') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="flex items-center justify-between px-3 py-2.5 bg-zinc-50 dark:bg-zinc-800/60 border-t border-zinc-200 dark:border-zinc-700">
                    <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">Total</span>
                    <span class="text-sm font-bold text-[#E8642E]">${{ number_format($vcTotal, 0, '.', ',') }}</span>
                </div>
            </div>

            @if($vc->jfac)
                <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg px-3 py-2.5 mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-[9px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide mb-0.5">Factura JFAC</p>
                        <p class="text-sm font-bold font-mono text-emerald-700 dark:text-emerald-300">{{ $vc->jfac }}</p>
                    </div>
                    <flux:icon.document-check class="w-5 h-5 text-emerald-400" />
                </div>
            @endif

            @if($vc->notas)
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg px-3 py-2.5 mb-4">
                    <p class="text-[9px] font-semibold text-zinc-400 uppercase tracking-wide mb-1">Notas</p>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $vc->notas }}</p>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cerrar</flux:button>
                </flux:modal.close>
                <a href="{{ route('comandas.imprimir', $vc) }}" target="_blank">
                    <flux:button size="sm" icon="printer">Imprimir</flux:button>
                </a>
            </div>
        @endif
    </flux:modal>

</div>
