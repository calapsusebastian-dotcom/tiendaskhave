<?php

use App\Events\TrasladoActualizado;
use App\Models\Traslado;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Traslado · Detalle')] class extends Component
{
    public Traslado $traslado;

    public function mount(Traslado $traslado): void
    {
        $this->traslado = $traslado->load(['tiendaOrigen', 'tiendaDestino', 'solicitante', 'items.materiaPrima']);
    }

    public function guardarImov(string $imov): void
    {
        if (!auth()->user()->hasAnyRole(['admin', 'traslados.imov'])) {
            Flux::toast(variant: 'warning', text: 'No tienes permiso para registrar el IMOV.');
            return;
        }
        $this->traslado->update(['imov' => trim($imov) ?: null]);
        $this->traslado->refresh();
        Flux::toast(variant: 'success', text: 'IMOV guardado correctamente.');
    }

    public function cambiarEstado(string $nuevoEstado): void
    {
        $user    = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $tienda  = $user->tienda_id;

        $permitidos = match($this->traslado->estado) {
            'pendiente' => ($isAdmin || $tienda === $this->traslado->tienda_origen_id)  ? ['enviado', 'cancelado'] : [],
            'enviado'   => ($isAdmin || $tienda === $this->traslado->tienda_destino_id) ? ['recibido', 'rechazado'] : [],
            default     => [],
        };

        if (!in_array($nuevoEstado, $permitidos)) {
            Flux::toast(variant: 'warning', text: 'No tienes permiso para este cambio de estado.');
            return;
        }

        $this->traslado->update(['estado' => $nuevoEstado]);
        $this->traslado->refresh();

        $labels = [
            'enviado'   => 'marcado como enviado',
            'cancelado' => 'cancelado',
            'recibido'  => 'recibido',
            'rechazado' => 'rechazado',
        ];

        try { broadcast(new TrasladoActualizado()); } catch (\Throwable $e) {}
        Flux::toast(variant: 'success', text: "Traslado {$this->traslado->folio} {$labels[$nuevoEstado]}.");
    }
}; ?>

@php
    $tr = $traslado;
    [$badgeClass, $badgeLabel] = match($tr->estado) {
        'pendiente' => ['bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'Pendiente'],
        'enviado'   => ['bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'Enviado'],
        'recibido'  => ['bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'Recibido'],
        'rechazado' => ['bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400', 'Rechazado'],
        'cancelado' => ['bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400', 'Cancelado'],
        default     => ['bg-zinc-100 text-zinc-500', $tr->estado],
    };
@endphp

<div class="max-w-3xl mx-auto space-y-4">

    {{-- Header --}}
    <div class="flex items-start gap-3">
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('traslados.index')" wire:navigate />
        <div class="flex-1">
            <div class="flex items-center gap-3 flex-wrap">
                <p class="font-mono text-lg font-bold text-[#E8642E]">{{ $tr->folio }}</p>
                <span class="text-[10px] px-2.5 py-1 rounded-full font-semibold {{ $badgeClass }}">{{ $badgeLabel }}</span>
            </div>
            <p class="text-xs text-zinc-400 mt-0.5">{{ $tr->created_at->format('d/m/Y H:i') }} · {{ $tr->solicitante?->name }}</p>
        </div>
    </div>

    {{-- Ruta origen → destino --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4">
        <div class="flex items-center gap-4">
            <div class="flex-1 text-center">
                <p class="text-[9px] font-semibold text-zinc-400 uppercase tracking-wide mb-2">Origen</p>
                <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-white text-sm mx-auto mb-2"
                     style="background-color:{{ $tr->tiendaOrigen->color }}">{{ $tr->tiendaOrigen->codigo }}</div>
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $tr->tiendaOrigen->nombre }}</p>
            </div>

            <div class="flex flex-col items-center gap-1 shrink-0">
                @if($tr->estado === 'enviado')
                    <div class="flex items-center gap-1">
                        <div class="w-16 h-0.5 bg-blue-400"></div>
                        <flux:icon.arrow-right class="w-5 h-5 text-blue-400" />
                    </div>
                    <p class="text-[9px] text-blue-500 font-medium">En tránsito</p>
                @elseif($tr->estado === 'recibido')
                    <div class="flex items-center gap-1">
                        <div class="w-16 h-0.5 bg-emerald-400"></div>
                        <flux:icon.check-circle class="w-5 h-5 text-emerald-500" />
                    </div>
                    <p class="text-[9px] text-emerald-600 font-medium">Entregado</p>
                @else
                    <div class="flex items-center gap-1">
                        <div class="w-16 h-0.5 bg-zinc-300 dark:bg-zinc-600"></div>
                        <flux:icon.arrow-right class="w-5 h-5 text-zinc-400" />
                    </div>
                @endif
            </div>

            <div class="flex-1 text-center">
                <p class="text-[9px] font-semibold text-zinc-400 uppercase tracking-wide mb-2">Destino</p>
                <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-white text-sm mx-auto mb-2"
                     style="background-color:{{ $tr->tiendaDestino->color }}">{{ $tr->tiendaDestino->codigo }}</div>
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $tr->tiendaDestino->nombre }}</p>
            </div>
        </div>
    </div>

    {{-- Acciones de estado --}}
    @php $uid = auth()->user()->tienda_id; $isAdmin = auth()->user()->hasRole('admin'); @endphp

    @if($tr->estado === 'pendiente' && ($isAdmin || $uid === $tr->tienda_origen_id))
        <div class="flex items-center gap-2">
            <flux:button wire:click="cambiarEstado('enviado')" variant="primary" icon="paper-airplane" size="sm">
                Marcar como enviado
            </flux:button>
            <flux:button wire:click="cambiarEstado('cancelado')" variant="ghost" size="sm"
                         wire:confirm="¿Cancelar el traslado {{ $tr->folio }}?">
                Cancelar traslado
            </flux:button>
        </div>
    @elseif($tr->estado === 'enviado' && ($isAdmin || $uid === $tr->tienda_destino_id))
        <div class="flex items-center gap-2">
            <flux:button wire:click="cambiarEstado('recibido')" variant="primary" icon="check-circle" size="sm">
                Confirmar recepción
            </flux:button>
            <flux:button wire:click="cambiarEstado('rechazado')" variant="ghost" size="sm"
                         wire:confirm="¿Rechazar el traslado {{ $tr->folio }}?">
                Rechazar
            </flux:button>
        </div>
    @endif

    {{-- IMOV del traslado --}}
    @php $puedeImov = auth()->user()->hasAnyRole(['admin', 'traslados.imov']); @endphp
    <div x-data="{ imov: '{{ $tr->imov ?? '' }}', editando: {{ (!$tr->imov && $puedeImov) ? 'true' : 'false' }} }"
         class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[9px] font-semibold text-zinc-400 uppercase tracking-wide">Número IMOV</p>
            @if($puedeImov)
                <button x-show="!editando" @click="editando = true"
                        class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors">
                    <flux:icon.pencil class="w-3 h-3" />
                    Editar
                </button>
            @endif
        </div>

        {{-- Vista --}}
        <p x-show="!editando" class="font-mono text-base font-bold text-zinc-800 dark:text-zinc-200"
           x-text="imov || '—'"></p>

        {{-- Edición (solo si tiene rol) --}}
        @if($puedeImov)
        <div x-show="editando" class="flex items-center gap-2">
            <input x-model="imov"
                   placeholder="Ej. IMOV-00123"
                   @keydown.enter="$wire.guardarImov(imov)"
                   class="flex-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg px-3 py-1.5
                          bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 placeholder-zinc-400
                          focus:outline-none focus:ring-2 focus:ring-[#E8642E] transition-colors" />
            <button @click="$wire.guardarImov(imov)"
                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#E8642E] text-white hover:bg-[#d4561f] transition-colors">
                <flux:icon.check class="w-3.5 h-3.5" />
                Guardar
            </button>
            @if($tr->imov)
            <button @click="imov = '{{ $tr->imov }}'; editando = false"
                    class="text-xs text-zinc-400 hover:text-zinc-600 px-1">
                Cancelar
            </button>
            @endif
        </div>
        @endif
    </div>

    {{-- Items --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <div class="px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Productos trasladados</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-zinc-100 dark:border-zinc-800">
                    <th class="px-4 py-2 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Producto</th>
                    <th class="px-4 py-2 text-left text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Unidad</th>
                    <th class="px-4 py-2 text-right text-[10px] font-semibold text-zinc-500 uppercase tracking-wide w-20">Cantidad</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                @foreach($tr->items as $item)
                    <tr>
                        <td class="px-4 py-2.5">
                            <p class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $item->materiaPrima?->nombre ?? '(eliminado)' }}
                            </p>
                            @if($item->materiaPrima?->codigo_producto)
                                <span class="font-mono text-[10px] text-zinc-400">{{ $item->materiaPrima->codigo_producto }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="text-xs text-zinc-500">{{ $item->materiaPrima?->unidad ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $item->cantidad }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Notas --}}
    @if($tr->notas)
        <div class="bg-zinc-50 dark:bg-zinc-800 rounded-xl px-4 py-3">
            <p class="text-[9px] font-semibold text-zinc-400 uppercase tracking-wide mb-1">Notas</p>
            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $tr->notas }}</p>
        </div>
    @endif

</div>
