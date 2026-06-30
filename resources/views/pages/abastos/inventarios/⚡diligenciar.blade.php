<?php

use App\Models\Inventario;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Diligenciar inventario')] class extends Component
{
    public Inventario $inventario;
    public array      $cantidades = [];
    public string     $notas      = '';
    public bool       $completado  = false;
    public bool       $verificado  = false;

    public function mount(Inventario $inventario): void
    {
        $tiendaUsuario = auth()->user()->tienda_id;
        if ($tiendaUsuario && $inventario->tienda_id !== $tiendaUsuario) {
            $this->redirectRoute('abastos.inventarios.index', navigate: true);
            return;
        }

        $this->inventario  = $inventario->load(['tienda', 'items.materiaPrima.proveedor']);
        $this->notas       = $inventario->notas ?? '';
        $this->completado  = in_array($inventario->estado, ['completado', 'verificado']);
        $this->verificado  = $inventario->estado === 'verificado';

        foreach ($inventario->items as $item) {
            if ($item->cantidad === null) {
                $this->cantidades[$item->materia_prima_id] = '';
            } else {
                $val = (float) $item->cantidad;
                $this->cantidades[$item->materia_prima_id] = rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
            }
        }
    }

    private function validarCampos(): bool
    {
        $pendientes = collect($this->cantidades)->filter(fn($v) => $v === '')->count();

        if ($pendientes > 0) {
            \Flux\Flux::toast(
                variant: 'danger',
                text: "Faltan {$pendientes} producto(s) sin diligenciar."
            );
            return false;
        }

        return true;
    }

    public function guardar(): void
    {
        if (! $this->validarCampos()) return;

        foreach ($this->cantidades as $mpId => $valor) {
            $this->inventario->items()
                ->where('materia_prima_id', $mpId)
                ->update(['cantidad' => is_numeric($valor) ? (float) $valor : null]);
        }

        $this->inventario->update([
            'notas'            => $this->notas ?: null,
            'diligenciado_by'  => auth()->id(),
        ]);

        \Flux\Flux::toast(variant: 'success', text: 'Inventario guardado.');
    }

    public function completar(): void
    {
        if (! $this->validarCampos()) return;

        foreach ($this->cantidades as $mpId => $valor) {
            $this->inventario->items()
                ->where('materia_prima_id', $mpId)
                ->update(['cantidad' => is_numeric($valor) ? (float) $valor : null]);
        }

        $this->inventario->update([
            'estado'           => 'completado',
            'notas'            => $this->notas ?: null,
            'diligenciado_by'  => auth()->id(),
        ]);
        $this->completado = true;
        $this->verificado = false;
        \Flux\Flux::toast(variant: 'success', text: 'Inventario completado.');
    }

    public function reabrir(): void
    {
        $this->inventario->update(['estado' => 'borrador']);
        $this->completado = false;
        $this->verificado = false;
        \Flux\Flux::toast(text: 'Inventario reabierto como borrador.');
    }
}; ?>

@php
    $grupos   = $inventario->items->groupBy(fn($i) => $i->materiaPrima->proveedor?->nombre ?? 'Sin proveedor');
    $total    = count($cantidades);
    $llenados = collect($cantidades)->filter(fn($v) => $v !== '')->count();
@endphp

<div class="space-y-4"
     x-data="{
         total: {{ $total }},
         filledCount: {{ $llenados }},
         update() {
             this.filledCount = [...this.$el.querySelectorAll('.qty-input')].filter(el => el.value !== '').length;
         },
         get pct() { return this.total > 0 ? Math.round(this.filledCount / this.total * 100) : 0; }
     }"
     @input="update()">

    {{-- Header pegajoso --}}
    <div class="sticky top-0 z-20 bg-white dark:bg-zinc-900 -mx-4 px-4 border-b border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
        {{-- Línea de progreso en el borde superior --}}
        <div class="h-1 bg-zinc-100 dark:bg-zinc-800">
            <div class="h-full transition-all duration-300"
                 :class="pct === 100 ? 'bg-emerald-500' : 'bg-[#E8642E]'"
                 :style="'width:' + pct + '%'"></div>
        </div>

        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('abastos.inventarios.index') }}" wire:navigate
                   class="w-7 h-7 rounded-lg border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-zinc-400 hover:text-zinc-700 hover:border-zinc-300 transition-colors shrink-0">
                    <flux:icon icon="arrow-left" class="w-3.5 h-3.5" />
                </a>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                            {{ $inventario->fecha->format('d/m/Y') }}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <div class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white shrink-0"
                                 style="background-color:{{ $inventario->tienda->color }}">{{ $inventario->tienda->codigo }}</div>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ $inventario->tienda->nombre }}</span>
                        </div>
                        @if($verificado)
                            <flux:badge color="violet" size="sm">Verificado</flux:badge>
                        @elseif($completado)
                            <flux:badge color="green" size="sm">Completado</flux:badge>
                        @else
                            <flux:badge color="amber" size="sm">Borrador</flux:badge>
                        @endif
                        <span class="text-[10px] text-zinc-400" x-text="filledCount + '/{{ $total }} · ' + pct + '%'"></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @if($completado)
                    @if(auth()->user()->hasRole('admin'))
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="reabrir"
                                     wire:confirm="¿Reabrir este inventario para editar?">
                            Reabrir
                        </flux:button>
                    @endif
                    @if(! $verificado)
                        <a href="{{ route('abastos.inventarios.verificar', $inventario) }}" wire:navigate>
                            <flux:button size="sm" variant="primary" icon="clipboard-document-check">
                                Verificar
                            </flux:button>
                        </a>
                    @endif
                @else
                    <flux:button size="sm" variant="ghost" wire:click="guardar" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="guardar">Guardar</span>
                        <span wire:loading wire:target="guardar">Guardando...</span>
                    </flux:button>
                    <flux:button size="sm" variant="primary" wire:click="completar" wire:loading.attr="disabled"
                                 wire:confirm="¿Marcar este inventario como completado?">
                        <span wire:loading.remove wire:target="completar">Completar</span>
                        <span wire:loading wire:target="completar">Guardando...</span>
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Productos por proveedor --}}
    @foreach($grupos as $proveedor => $items)
        @php
            $llen = $items->filter(fn($i) => isset($cantidades[$i->materia_prima_id]) && $cantidades[$i->materia_prima_id] !== '')->count();
        @endphp
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">

            {{-- Encabezado proveedor --}}
            <div class="flex items-center justify-between px-3 py-1.5 bg-zinc-50 dark:bg-zinc-800/40 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-1.5">
                    <div class="w-1.5 h-1.5 rounded-full bg-[#E8642E]"></div>
                    <span class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-300">{{ $proveedor }}</span>
                </div>
                <span class="text-[9px] text-zinc-400">{{ $llen }}/{{ $items->count() }}</span>
            </div>

            {{-- Tabla de productos --}}
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                        <th class="text-left text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-3 w-7">#</th>
                        <th class="text-left text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-2">Producto</th>
                        <th class="text-center text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-2 w-16">Unidad</th>
                        <th class="text-center text-[9px] font-semibold text-zinc-400 uppercase tracking-wide py-1.5 px-3 w-20">Cantidad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/50">
                    @foreach($items as $i => $item)
                        @php $mpId = $item->materia_prima_id; $filled = isset($cantidades[$mpId]) && $cantidades[$mpId] !== ''; @endphp
                        <tr class="{{ $filled ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : '' }} hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="py-1 px-3">
                                <span class="text-[9px] text-zinc-300 dark:text-zinc-600 font-mono">{{ $i + 1 }}</span>
                            </td>
                            <td class="py-1 px-2">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-1 h-1 rounded-full shrink-0 {{ $filled ? 'bg-emerald-400' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
                                    <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $item->materiaPrima->nombre }}</span>
                                    @if($item->materiaPrima->codigo_producto)
                                        <span class="font-mono text-[9px] text-zinc-400">{{ $item->materiaPrima->codigo_producto }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-1 px-2 text-center">
                                <span class="text-[9px] font-medium text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">
                                    {{ $item->materiaPrima->unidad }}
                                </span>
                            </td>
                            <td class="py-1 px-3">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    wire:model.blur="cantidades.{{ $mpId }}"
                                    @if($completado) disabled @endif
                                    placeholder="—"
                                    class="qty-input w-16 text-center text-xs font-bold rounded-md border
                                           {{ $filled
                                               ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300'
                                               : 'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400' }}
                                           disabled:opacity-50 disabled:cursor-not-allowed
                                           focus:outline-none focus:ring-2 focus:ring-[#E8642E]/30 focus:border-[#E8642E]
                                           px-2 py-1 transition-colors"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    {{-- Notas --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4">
        <flux:textarea
            wire:model="notas"
            label="Observaciones"
            placeholder="Anota cualquier novedad del inventario..."
            rows="2"
            :disabled="$completado"
        />
    </div>

</div>
