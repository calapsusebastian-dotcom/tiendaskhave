<?php

use App\Models\Inventario;
use App\Models\MateriaPrima;
use App\Models\Tienda;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Inventarios'), Lazy] class extends Component
{
    use WithPagination;

    #[Url] public string $filtroTienda = 'all';

    public bool   $modalNuevo  = false;
    public string $nuevaTienda = '';
    public string $nuevaFecha  = '';

    public function mount(): void
    {
        $this->nuevaFecha  = now()->toDateString();
        $tiendaUsuario     = auth()->user()->tienda_id;
        $this->nuevaTienda = $tiendaUsuario ? (string) $tiendaUsuario : '';
    }

    public function updatedFiltroTienda(): void
    {
        $this->resetPage();
    }

    public function abrirNuevo(): void
    {
        $tiendaUsuario     = auth()->user()->tienda_id;
        $this->nuevaTienda = $tiendaUsuario ? (string) $tiendaUsuario : '';
        $this->nuevaFecha  = now()->toDateString();
        $this->modalNuevo  = true;
    }

    public function crearInventario(): void
    {
        $tiendaUsuario = auth()->user()->tienda_id;
        if ($tiendaUsuario) {
            $this->nuevaTienda = (string) $tiendaUsuario;
        }

        $this->validate([
            'nuevaTienda' => 'required|exists:tiendas,id',
            'nuevaFecha'  => 'required|date',
        ], [], ['nuevaTienda' => 'Tienda', 'nuevaFecha' => 'Fecha']);

        $inventario = Inventario::create([
            'tienda_id'  => $this->nuevaTienda,
            'fecha'      => $this->nuevaFecha,
            'estado'     => 'borrador',
            'created_by' => auth()->id(),
        ]);

        MateriaPrima::where('activo', true)->orderBy('nombre')->each(function ($mp) use ($inventario) {
            $inventario->items()->create([
                'materia_prima_id' => $mp->id,
                'cantidad'         => null,
            ]);
        });

        $this->redirectRoute('abastos.inventarios.diligenciar', $inventario, navigate: true);
    }

    public function eliminar(int $id): void
    {
        if (! auth()->user()->hasRole('admin')) return;
        Inventario::findOrFail($id)->delete();
        \Flux\Flux::toast(variant: 'success', text: 'Inventario eliminado.');
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php
    $tiendaUsuario = auth()->user()->tienda_id;
    $tiendas       = Tienda::orderBy('nombre')->get();

    $inventarios = Inventario::with(['tienda', 'items', 'diligenciador'])
        ->when($tiendaUsuario, fn($q) => $q->where('tienda_id', $tiendaUsuario))
        ->when(! $tiendaUsuario && $filtroTienda !== 'all', fn($q) => $q->where('tienda_id', $filtroTienda))
        ->latest('fecha')
        ->latest('id')
        ->paginate(20);
@endphp

<div>
<div class="space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Inventarios</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Registro diario de productos por tienda</p>
        </div>
        <flux:button variant="primary" icon="plus" size="sm" wire:click="abrirNuevo">
            Nuevo inventario
        </flux:button>
    </div>

    {{-- Filtro (solo para admin/sin tienda asignada) --}}
    @if(! $tiendaUsuario)
    <div class="flex gap-2">
        <flux:select wire:model.live="filtroTienda" size="sm" class="w-44">
            <flux:select.option value="all">Todas las tiendas</flux:select.option>
            @foreach($tiendas as $t)
                <flux:select.option value="{{ $t->id }}">{{ $t->nombre }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>
    @endif

    {{-- Tabla --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-zinc-50 dark:bg-zinc-800/40 border-b border-zinc-100 dark:border-zinc-800">
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-4">Fecha</th>
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Tienda</th>
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Estado</th>
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Progreso</th>
                    <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Realizado por</th>
                    <th class="text-right text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-4">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                @forelse($inventarios as $inv)
                    @php
                        $total    = $inv->items->count();
                        $llenados = $inv->items->whereNotNull('cantidad')->count();
                        $pct      = $total > 0 ? round($llenados / $total * 100) : 0;
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                        <td class="py-2.5 px-4">
                            <span class="font-mono text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $inv->fecha->format('d/m/Y') }}
                            </span>
                            @if($inv->fecha->isToday())
                                <span class="ml-1.5 text-[9px] font-semibold text-[#E8642E] uppercase">Hoy</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                     style="background-color:{{ $inv->tienda->color }}">{{ $inv->tienda->codigo }}</div>
                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $inv->tienda->nombre }}</span>
                            </div>
                        </td>
                        <td class="py-2.5 px-3">
                            @if($inv->estado === 'verificado')
                                <flux:badge color="violet" size="sm">Verificado</flux:badge>
                            @elseif($inv->estado === 'completado')
                                <flux:badge color="green" size="sm">Completado</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">Borrador</flux:badge>
                            @endif
                        </td>
                        <td class="py-2.5 px-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden w-20">
                                    <div class="h-full rounded-full {{ $pct === 100 ? 'bg-emerald-500' : 'bg-[#E8642E]' }} transition-all"
                                         style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="text-[10px] text-zinc-400">{{ $llenados }}/{{ $total }}</span>
                            </div>
                        </td>
                        <td class="py-2.5 px-3">
                            @if($inv->diligenciador)
                                <div class="flex items-center gap-1.5">
                                    <div class="w-5 h-5 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-[8px] font-bold text-zinc-600 dark:text-zinc-300 shrink-0">
                                        {{ strtoupper(substr($inv->diligenciador->name, 0, 1)) }}
                                    </div>
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400 truncate max-w-[100px]">{{ $inv->diligenciador->name }}</span>
                                </div>
                            @else
                                <span class="text-xs text-zinc-300 dark:text-zinc-600">Pendiente</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($inv->estado === 'completado')
                                    <a href="{{ route('abastos.inventarios.verificar', $inv) }}" wire:navigate>
                                        <flux:button size="xs" variant="ghost" icon="clipboard-document-check">
                                            Verificar
                                        </flux:button>
                                    </a>
                                @endif
                                <a href="{{ $inv->estado === 'verificado'
                                        ? route('abastos.inventarios.verificar', $inv)
                                        : route('abastos.inventarios.diligenciar', $inv) }}" wire:navigate>
                                    <flux:button size="xs" variant="ghost" icon="pencil-square">
                                        {{ $inv->estado === 'borrador' ? 'Diligenciar' : 'Ver' }}
                                    </flux:button>
                                </a>
                                @if(auth()->user()->hasRole('admin'))
                                    <flux:button size="xs" variant="ghost" icon="trash"
                                        wire:click="eliminar({{ $inv->id }})"
                                        wire:confirm="¿Eliminar este inventario? Esta acción no se puede deshacer."
                                        class="text-red-400 hover:text-red-600" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-sm text-zinc-400">
                            No hay inventarios registrados. Crea uno nuevo para comenzar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($inventarios->hasPages())
            <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800">
                {{ $inventarios->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal nuevo inventario --}}
<flux:modal name="modal-nuevo" wire:model="modalNuevo" class="w-96">
    <div class="overflow-hidden rounded-xl">
        {{-- Header con acento --}}
        <div class="bg-gradient-to-r from-[#E8642E] to-orange-400 px-6 py-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <flux:icon icon="clipboard-document-check" class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">Nuevo inventario</h3>
                    <p class="text-xs text-orange-100 mt-0.5">
                        {{ $tiendaUsuario ? 'Selecciona la fecha del inventario' : 'Selecciona la tienda y la fecha' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Cuerpo --}}
        <div class="p-6 space-y-5 bg-white dark:bg-zinc-900">

            {{-- Tienda --}}
            @if($tiendaUsuario)
                @php $tiendaFija = $tiendas->firstWhere('id', $tiendaUsuario); @endphp
                @if($tiendaFija)
                <div class="flex items-center gap-3 p-3 rounded-xl border border-[#E8642E] bg-orange-50 dark:bg-orange-900/10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold text-white shrink-0"
                         style="background-color:{{ $tiendaFija->color }}">{{ $tiendaFija->codigo }}</div>
                    <span class="text-sm font-medium text-[#E8642E]">{{ $tiendaFija->nombre }}</span>
                    <flux:icon icon="check-circle" class="w-4 h-4 text-[#E8642E] ml-auto" />
                </div>
                @endif
            @else
            <div class="space-y-2">
                <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Tienda</label>
                <div class="grid grid-cols-1 gap-2">
                    @foreach($tiendas as $t)
                        <div wire:click="$set('nuevaTienda', '{{ $t->id }}')"
                             class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all
                                    {{ (string)$nuevaTienda === (string)$t->id
                                        ? 'border-[#E8642E] bg-orange-50 dark:bg-orange-900/10'
                                        : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600' }}">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-bold text-white shrink-0"
                                 style="background-color:{{ $t->color }}">{{ $t->codigo }}</div>
                            <span class="text-sm font-medium {{ (string)$nuevaTienda === (string)$t->id ? 'text-[#E8642E]' : 'text-zinc-700 dark:text-zinc-300' }}">
                                {{ $t->nombre }}
                            </span>
                            @if((string)$nuevaTienda === (string)$t->id)
                                <flux:icon icon="check-circle" class="w-4 h-4 text-[#E8642E] ml-auto" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Fecha --}}
            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Fecha del inventario</label>
                <flux:input wire:model="nuevaFecha" type="date" />
            </div>

            {{-- Botones --}}
            <div class="flex gap-2 pt-1">
                <flux:button variant="ghost" class="flex-1" wire:click="$set('modalNuevo', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" class="flex-1" wire:click="crearInventario" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="crearInventario">Crear inventario</span>
                    <span wire:loading wire:target="crearInventario">Creando...</span>
                </flux:button>
            </div>
        </div>
    </div>
</flux:modal>


</div>
