<?php

use App\Events\PedidoActualizado;
use App\Models\MateriaPrima;
use App\Models\Pedido;
use App\Models\PedidoItem;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar pedido · Logística Proveedores')] class extends Component
{
    public int $pedidoId;
    public string $busqueda = '';
    /** @var array<int, int> */
    public array $cantidades = [];

    public function mount(Pedido $pedido): void
    {
        $tiendaUsuario = auth()->user()->tienda_id;
        if ($tiendaUsuario && $pedido->tienda_id !== $tiendaUsuario) {
            $this->redirectRoute('abastos.pedidos.index', navigate: true);
            return;
        }

        $this->pedidoId = $pedido->id;

        foreach ($pedido->items as $item) {
            $this->cantidades[$item->materia_prima_id] = $item->cantidad;
        }
    }

    #[Computed]
    public function pedido(): Pedido
    {
        return Pedido::with(['tienda', 'proveedor'])->findOrFail($this->pedidoId);
    }

    public function guardar(array $cantidades, string $notas = ''): void
    {
        $seleccionados = collect($cantidades)
            ->map(fn($qty) => (int)$qty)
            ->filter(fn($qty) => $qty > 0);

        if ($seleccionados->isEmpty()) {
            Flux::toast(variant: 'warning', text: 'Agrega al menos un producto.');
            return;
        }

        $pedido     = Pedido::findOrFail($this->pedidoId);
        $materiales = MateriaPrima::whereIn('id', $seleccionados->keys())->get()->keyBy('id');

        $porProveedor = [];
        foreach ($seleccionados as $matId => $qty) {
            $mat = $materiales->get($matId);
            if (!$mat) continue;
            $porProveedor[$mat->proveedor_id][] = ['mat' => $mat, 'qty' => $qty];
        }

        $folios = [];

        foreach ($porProveedor as $proveedorId => $lineItems) {
            if ($proveedorId == $pedido->proveedor_id) {
                $pedido->items()->delete();
                foreach ($lineItems as $line) {
                    PedidoItem::create([
                        'pedido_id'        => $pedido->id,
                        'materia_prima_id' => $line['mat']->id,
                        'cantidad'         => $line['qty'],
                        'precio_unitario'  => (float)$line['mat']->precio,
                        'iva'              => (int)$line['mat']->iva,
                    ]);
                }
                $pedido->update(['notas' => $notas ?: null]);
                $folios[] = $pedido->folio;
            } else {
                $nuevo = Pedido::create([
                    'folio'        => Pedido::generarFolio(),
                    'tienda_id'    => $pedido->tienda_id,
                    'proveedor_id' => $proveedorId,
                    'estado'       => 'por_aprobar',
                    'notas'        => $notas ?: null,
                ]);
                foreach ($lineItems as $line) {
                    PedidoItem::create([
                        'pedido_id'        => $nuevo->id,
                        'materia_prima_id' => $line['mat']->id,
                        'cantidad'         => $line['qty'],
                        'precio_unitario'  => (float)$line['mat']->precio,
                        'iva'              => (int)$line['mat']->iva,
                    ]);
                }
                $folios[] = $nuevo->folio;
            }
        }

        if (!isset($porProveedor[$pedido->proveedor_id])) {
            $pedido->items()->delete();
            $pedido->update(['notas' => $notas ?: null]);
            $folios[] = $pedido->folio . ' (sin items)';
        }

        $msg = count($folios) === 1
            ? $folios[0] . ' actualizado.'
            : 'Pedidos actualizados: ' . implode(', ', $folios);

        try { broadcast(new PedidoActualizado()); } catch (\Throwable $e) {}
        Flux::toast(variant: 'success', text: $msg);
        $this->redirectRoute('abastos.pedidos.index', navigate: true);
    }
}; ?>

@php
    $pedido    = $this->pedido;
    $agrupados = MateriaPrima::with('proveedor')
        ->where('activo', true)
        ->whereHas('proveedor', fn($q) => $q->where('activo', true))
        ->when($busqueda, fn($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'))
        ->orderBy('nombre')
        ->get()
        ->groupBy('proveedor_id');

    $materialesAlpine = $agrupados->flatten()->map(fn($m) => [
        'id'    => $m->id,
        'precio' => (float) $m->precio,
    ])->values();
@endphp

<div class="max-w-2xl mx-auto space-y-4"
    x-data="{
        cantidades: @js((object) $cantidades),
        notas: @js($pedido->notas ?? ''),
        materiales: @js($materialesAlpine),

        get resumen() {
            let count = 0, total = 0;
            for (const m of this.materiales) {
                const qty = parseInt(this.cantidades[m.id] ?? 0);
                if (qty > 0) { count++; total += qty * m.precio; }
            }
            return { count, total };
        },

        fmt(n) { return '$' + Math.round(n).toLocaleString('es-CO'); },
    }">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Editar pedido</h1>
            <p class="text-xs text-zinc-400 mt-0.5 font-mono">{{ $pedido->folio }} · {{ $pedido->tienda->nombre }}</p>
        </div>
        <flux:button href="{{ route('abastos.pedidos.index') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            Cancelar
        </flux:button>
    </div>

    {{-- Buscador --}}
    <div class="flex items-center gap-2 p-3 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
        <div class="flex items-center gap-2 shrink-0">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white"
                 style="background-color:{{ $pedido->tienda->color }}">{{ $pedido->tienda->codigo }}</div>
            <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $pedido->tienda->nombre }}</span>
        </div>
        <div class="w-px h-5 bg-zinc-200 dark:bg-zinc-700 mx-1 shrink-0"></div>
        <flux:input wire:model.live.debounce.300ms="busqueda" placeholder="Buscar producto..." icon="magnifying-glass" class="flex-1" size="sm" />
    </div>

    {{-- Productos agrupados por proveedor --}}
    <div class="space-y-3">
        @forelse($agrupados as $proveedorId => $mats)
            @php $prov = $mats->first()->proveedor; @endphp
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $prov?->nombre }}</span>
                    <span class="text-[10px] text-zinc-400">{{ $prov?->categoria }}</span>
                </div>
                <table class="w-full">
                    <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                        @foreach($mats as $mat)
                            <tr :class="parseInt(cantidades[{{ $mat->id }}] ?? 0) > 0 ? 'bg-orange-50/40 dark:bg-orange-900/5' : ''"
                                class="transition-colors">
                                <td class="px-4 py-2.5">
                                    <p class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $mat->nombre }}
                                        @if($mat->codigo_producto)
                                            <span class="font-mono font-normal text-zinc-400 ml-1">{{ $mat->codigo_producto }}</span>
                                        @endif
                                    </p>
                                    <p class="text-[10px] text-zinc-400">${{ number_format($mat->precio, 2) }} / {{ $mat->unidad }}</p>
                                </td>
                                <td class="px-3 py-2.5 w-24">
                                    <input type="number" min="0"
                                           x-model="cantidades[{{ $mat->id }}]"
                                           placeholder="0"
                                           class="w-20 text-center text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg px-2 py-1.5 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-[#E8642E]" />
                                </td>
                                <td class="px-4 py-2.5 text-right w-24">
                                    <span x-show="parseInt(cantidades[{{ $mat->id }}] ?? 0) > 0"
                                          x-text="fmt(parseInt(cantidades[{{ $mat->id }}] ?? 0) * {{ (float)$mat->precio }})"
                                          class="text-xs font-semibold text-[#E8642E]"></span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-12 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <flux:icon.magnifying-glass class="w-6 h-6 text-zinc-300 mb-2" />
                <p class="text-sm text-zinc-400">Sin resultados para "{{ $busqueda }}"</p>
            </div>
        @endforelse
    </div>

    {{-- Notas --}}
    <div>
        <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Notas (opcional)</label>
        <textarea x-model="notas" rows="2" placeholder="Urgencia, indicaciones especiales..."
                  class="w-full text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-[#E8642E] resize-none"></textarea>
    </div>

    {{-- Footer --}}
    <div class="flex items-center gap-3">
        <div class="flex-1 flex items-center justify-between bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 shadow-sm">
            <template x-if="resumen.count > 0">
                <div class="flex items-center justify-between w-full">
                    <p class="text-xs text-zinc-500" x-text="resumen.count + (resumen.count !== 1 ? ' productos' : ' producto')"></p>
                    <p class="text-sm font-bold text-zinc-900 dark:text-white" x-text="fmt(resumen.total)"></p>
                </div>
            </template>
            <template x-if="resumen.count === 0">
                <div class="flex items-center justify-between w-full">
                    <p class="text-xs text-zinc-400">Sin productos seleccionados</p>
                    <p class="text-sm font-bold text-zinc-300 dark:text-zinc-600">$0</p>
                </div>
            </template>
        </div>
        <button @click="$wire.guardar(cantidades, notas)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#E8642E] text-white hover:bg-[#d4561f] transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Guardar cambios
        </button>
    </div>

</div>
