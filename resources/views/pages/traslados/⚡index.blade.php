<?php

use App\Models\Tienda;
use App\Models\Traslado;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Traslados'), Lazy] class extends Component
{
    use WithPagination;

    #[Url] public string $busqueda      = '';
    #[Url] public string $estado        = 'all';
    #[Url] public string $filtroTienda  = 'all';
    #[Url] public string $desde         = '';
    #[Url] public string $hasta         = '';

    public function updatingBusqueda():    void { $this->resetPage(); }
    public function updatingEstado():      void { $this->resetPage(); }
    public function updatingFiltroTienda():void { $this->resetPage(); }
    public function updatingDesde():       void { $this->resetPage(); }
    public function updatingHasta():       void { $this->resetPage(); }

    public function mount(): void
    {
        if (auth()->user()->tienda_id) {
            $this->filtroTienda = (string) auth()->user()->tienda_id;
        }
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php
    $user    = auth()->user();
    $tiendas = Tienda::orderBy('nombre')->get();

    $traslados = Traslado::with(['tiendaOrigen', 'tiendaDestino', 'solicitante'])
        ->withCount('items')
        ->when($user->tienda_id, fn($q) => $q->where(function($q2) use ($user) {
            $q2->where('tienda_origen_id', $user->tienda_id)
               ->orWhere('tienda_destino_id', $user->tienda_id);
        }))
        ->when(!$user->tienda_id && $filtroTienda !== 'all', fn($q) => $q->where(function($q2) use ($filtroTienda) {
            $q2->where('tienda_origen_id', $filtroTienda)
               ->orWhere('tienda_destino_id', $filtroTienda);
        }))
        ->when($estado !== 'all', fn($q) => $q->where('estado', $estado))
        ->when($busqueda, fn($q) => $q->where('folio', 'like', '%'.$busqueda.'%'))
        ->when($desde, fn($q) => $q->whereDate('created_at', '>=', $desde))
        ->when($hasta, fn($q) => $q->whereDate('created_at', '<=', $hasta))
        ->latest()
        ->paginate(20);
@endphp

<div class="max-w-5xl mx-auto space-y-4"
    x-data
    x-init="echoWhen(e => e.channel('traslados').listen('.TrasladoActualizado', () => window.dispatchEvent(new CustomEvent('traslado-actualizado'))))"
    @traslado-actualizado.window="$wire.$refresh()">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Traslados</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Movimiento de productos entre tiendas</p>
        </div>
        <flux:button size="sm" icon="plus" :href="route('traslados.crear')" wire:navigate variant="primary">
            Nuevo traslado
        </flux:button>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-3">
        <div class="flex flex-wrap gap-2">
            <flux:input wire:model.live.debounce.300ms="busqueda"
                        placeholder="Buscar por folio..."
                        icon="magnifying-glass" size="sm" class="flex-1 min-w-40" />

            <flux:select wire:model.live="estado" size="sm" class="w-40">
                <flux:select.option value="all">Todos los estados</flux:select.option>
                <flux:select.option value="pendiente">Pendiente</flux:select.option>
                <flux:select.option value="enviado">Enviado</flux:select.option>
                <flux:select.option value="recibido">Recibido</flux:select.option>
                <flux:select.option value="rechazado">Rechazado</flux:select.option>
                <flux:select.option value="cancelado">Cancelado</flux:select.option>
            </flux:select>

            @if(!$user->tienda_id)
                <flux:select wire:model.live="filtroTienda" size="sm" class="w-40">
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
    @if($traslados->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
            <flux:icon.arrow-right-circle class="w-8 h-8 text-zinc-300 mb-2" />
            <p class="text-sm text-zinc-400">No hay traslados con estos filtros.</p>
            <flux:button size="sm" variant="ghost" class="mt-3" :href="route('traslados.crear')" wire:navigate>
                Crear primer traslado
            </flux:button>
        </div>
    @else
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Folio</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">IMOV</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Origen</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Destino</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Estado</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Items</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Solicitante</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                    @foreach($traslados as $tr)
                        @php
                            [$badgeClass, $badgeLabel] = match($tr->estado) {
                                'pendiente' => ['bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'Pendiente'],
                                'enviado'   => ['bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'Enviado'],
                                'recibido'  => ['bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'Recibido'],
                                'rechazado' => ['bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400', 'Rechazado'],
                                'cancelado' => ['bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400', 'Cancelado'],
                                default     => ['bg-zinc-100 text-zinc-500', $tr->estado],
                            };
                        @endphp
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors cursor-pointer"
                            @click="window.Livewire.navigate('{{ route('traslados.ver', $tr) }}')">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs font-semibold text-[#E8642E]">{{ $tr->folio }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($tr->imov)
                                    <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $tr->imov }}</span>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-zinc-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0"
                                         style="background-color:{{ $tr->tiendaOrigen->color }}">{{ $tr->tiendaOrigen->codigo }}</div>
                                    <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $tr->tiendaOrigen->nombre }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0"
                                         style="background-color:{{ $tr->tiendaDestino->color }}">{{ $tr->tiendaDestino->codigo }}</div>
                                    <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $tr->tiendaDestino->nombre }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $badgeClass }}">
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="text-xs text-zinc-500">{{ $tr->items_count }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs text-zinc-500">{{ $tr->solicitante?->name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-xs text-zinc-500">{{ $tr->created_at->format('d/m/Y') }}</p>
                                <p class="text-[10px] text-zinc-400">{{ $tr->created_at->format('H:i') }}</p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($traslados->hasPages())
            <div class="mt-2">{{ $traslados->links() }}</div>
        @endif
    @endif

</div>
