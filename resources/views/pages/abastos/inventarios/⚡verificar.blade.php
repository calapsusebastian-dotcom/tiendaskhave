<?php

use App\Models\Inventario;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Verificar inventario')] class extends Component
{
    public Inventario $inventario;
    public array      $sistemaCantidades = [];
    public bool       $yaVerificado      = false;

    public function mount(Inventario $inventario): void
    {
        if ($inventario->estado === 'borrador') {
            $this->redirectRoute('abastos.inventarios.diligenciar', $inventario, navigate: true);
            return;
        }

        $this->yaVerificado = $inventario->estado === 'verificado';

        $tiendaUsuario = auth()->user()->tienda_id;
        if ($tiendaUsuario && $inventario->tienda_id !== $tiendaUsuario) {
            $this->redirectRoute('abastos.inventarios.index', navigate: true);
            return;
        }

        $this->inventario = $inventario->load(['tienda', 'items.materiaPrima.proveedor']);

        foreach ($inventario->items as $item) {
            if ($item->cantidad_sistema === null) {
                $this->sistemaCantidades[$item->materia_prima_id] = '';
            } else {
                $val = (float) $item->cantidad_sistema;
                $this->sistemaCantidades[$item->materia_prima_id] = rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
            }
        }
    }

    public function guardar(): void
    {
        foreach ($this->sistemaCantidades as $mpId => $valor) {
            $this->inventario->items()
                ->where('materia_prima_id', $mpId)
                ->update(['cantidad_sistema' => is_numeric($valor) ? (float) $valor : null]);
        }

        $this->inventario->update([
            'estado'        => 'verificado',
            'verificado_by' => auth()->id(),
        ]);

        \Flux\Flux::toast(variant: 'success', text: 'Verificación guardada.');
        $this->redirectRoute('abastos.inventarios.index', navigate: true);
    }
}; ?>

@php
    $grupos  = $inventario->items->groupBy(fn($i) => $i->materiaPrima->proveedor?->nombre ?? 'Sin proveedor');
    $total   = $inventario->items->count();
    $hayDiff = false;

    // Pre-calcular diferencias
    $diffs = [];
    foreach ($inventario->items as $item) {
        $mpId    = $item->materia_prima_id;
        $contado = (float)($item->cantidad ?? 0);
        $sist    = isset($sistemaCantidades[$mpId]) && $sistemaCantidades[$mpId] !== ''
            ? (float) $sistemaCantidades[$mpId]
            : null;
        $diff    = $sist !== null ? ($sist - $contado) : null;
        $diffs[$mpId] = ['contado' => $contado, 'sistema' => $sist, 'diff' => $diff];
        if ($diff !== null && $diff != 0) $hayDiff = true;
    }
@endphp

<div class="space-y-4">

    {{-- Sticky header --}}
    <div class="sticky top-0 z-20 bg-white dark:bg-zinc-900 -mx-4 px-4 border-b border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div class="h-1 {{ $hayDiff ? 'bg-red-400' : 'bg-emerald-500' }}"></div>

        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('abastos.inventarios.index') }}" wire:navigate
                   class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-zinc-400 hover:text-zinc-700 transition-colors shrink-0">
                    <flux:icon icon="arrow-left" class="w-3.5 h-3.5" />
                </a>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $inventario->fecha->format('d/m/Y') }}
                    </span>
                    <div class="flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white shrink-0"
                             style="background-color:{{ $inventario->tienda->color }}">{{ $inventario->tienda->codigo }}</div>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $inventario->tienda->nombre }}</span>
                    </div>
                    @if($yaVerificado)
                        <flux:badge color="violet" size="sm">Verificado</flux:badge>
                    @else
                        <flux:badge color="lime" size="sm">Por verificar</flux:badge>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @if(! $yaVerificado)
                    <flux:button size="sm" variant="primary" wire:click="guardar" wire:loading.attr="disabled"
                                 wire:confirm="¿Guardar verificación y marcar como verificado?">
                        <span wire:loading.remove wire:target="guardar">Guardar verificación</span>
                        <span wire:loading wire:target="guardar">Guardando...</span>
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Grupos por proveedor --}}
    @foreach($grupos as $proveedor => $items)
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">

            <div class="flex items-center justify-between px-3 py-1.5 bg-zinc-50 dark:bg-zinc-800/40 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-1.5">
                    <div class="w-1.5 h-1.5 rounded-full bg-violet-400"></div>
                    <span class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-300">{{ $proveedor }}</span>
                </div>
            </div>

            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                        <th class="text-left text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-3">Producto</th>
                        <th class="text-center text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-2 w-16">Und</th>
                        <th class="text-center text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-3 w-20">Contado</th>
                        <th class="text-center text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-3 w-24">Sistema</th>
                        <th class="text-center text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-3 w-24">Diferencia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/50">
                    @foreach($items as $item)
                        @php
                            $mpId   = $item->materia_prima_id;
                            $d      = $diffs[$mpId];
                            $diff   = $d['diff'];
                            $isOk   = $diff !== null && $diff == 0;
                            $isBad  = $diff !== null && $diff != 0;
                        @endphp
                        <tr class="{{ $isBad ? 'bg-red-50/50 dark:bg-red-900/10' : ($isOk ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : '') }} transition-colors">
                            <td class="py-1.5 px-3">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1 h-1 rounded-full shrink-0 {{ $isBad ? 'bg-red-400' : ($isOk ? 'bg-emerald-400' : 'bg-zinc-200') }}"></div>
                                    <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $item->materiaPrima->nombre }}</span>
                                    @if($item->materiaPrima->codigo_producto)
                                        <span class="font-mono text-[9px] text-zinc-400">{{ $item->materiaPrima->codigo_producto }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-1.5 px-2 text-center">
                                <span class="text-[9px] font-medium text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">
                                    {{ $item->materiaPrima->unidad }}
                                </span>
                            </td>
                            {{-- Contado (solo lectura) --}}
                            <td class="py-1.5 px-3 text-center">
                                <span class="text-xs font-bold {{ $item->cantidad !== null ? 'text-zinc-700 dark:text-zinc-300' : 'text-zinc-300 dark:text-zinc-600' }}">
                                    {{ $item->cantidad !== null ? rtrim(rtrim(number_format((float)$item->cantidad, 2, '.', ''), '0'), '.') : '—' }}
                                </span>
                            </td>
                            {{-- Sistema (editable) --}}
                            <td class="py-1.5 px-3">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    wire:model.blur="sistemaCantidades.{{ $mpId }}"
                                    @if($yaVerificado) disabled @endif
                                    placeholder="—"
                                    class="w-full text-center text-xs font-bold rounded-md border px-2 py-1 transition-colors focus:outline-none focus:ring-2 focus:ring-violet-400/30 focus:border-violet-400 disabled:opacity-60 disabled:cursor-not-allowed
                                           {{ $isBad
                                               ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300'
                                               : ($isOk
                                                   ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300'
                                                   : 'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400') }}"
                                />
                            </td>
                            {{-- Diferencia --}}
                            <td class="py-1.5 px-3 text-center">
                                @if($diff !== null)
                                    <span class="text-xs font-bold {{ $diff == 0 ? 'text-emerald-600 dark:text-emerald-400' : ($diff > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400') }}">
                                        {{ $diff > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($diff, 2, '.', ''), '0'), '.') }}
                                    </span>
                                @else
                                    <span class="text-[10px] text-zinc-300 dark:text-zinc-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

</div>
