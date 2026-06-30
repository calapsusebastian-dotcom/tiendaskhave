<?php

use App\Models\Mesa;
use App\Models\Tienda;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mesas · Comandas'), Lazy] class extends Component
{
    public int $paso = 1;
    public ?int $tiendaId = null;

    public ?int $editandoMesaId = null;
    public string $mesaNumero = '';
    public string $mesaNombre = '';
    public string $mesaCapacidad = '4';

    public function mount(): void
    {
        if (auth()->user()->tienda_id) {
            $this->tiendaId = auth()->user()->tienda_id;
            $this->paso = 2;
        }
    }

    public function seleccionarTienda(int $id): void
    {
        $this->tiendaId = $id;
        $this->paso = 2;
    }

    public function volver(): void
    {
        $this->paso = 1;
        $this->tiendaId = null;
    }

    #[Renderless]
    public function nuevaMesa(): void
    {
        $this->reset(['editandoMesaId', 'mesaNombre']);
        $this->mesaNumero = '';
        $this->mesaCapacidad = '4';
        Flux::modal('form-mesa')->show();
    }

    #[Renderless]
    public function editarMesa(int $id): void
    {
        $mesa = Mesa::findOrFail($id);
        $this->editandoMesaId = $id;
        $this->mesaNumero    = (string) $mesa->numero;
        $this->mesaNombre    = $mesa->nombre ?? '';
        $this->mesaCapacidad = (string) $mesa->capacidad;
        Flux::modal('form-mesa')->show();
    }

    public function guardarMesa(): void
    {
        $this->validate([
            'mesaNumero' => [
                'required', 'integer', 'min:1',
                Rule::unique('mesas', 'numero')
                    ->where('tienda_id', $this->tiendaId)
                    ->ignore($this->editandoMesaId),
            ],
            'mesaNombre'    => 'nullable|string|max:60',
            'mesaCapacidad' => 'required|integer|min:1|max:50',
        ], [
            'mesaNumero.unique' => 'Ya existe una mesa con ese número en esta tienda.',
        ], [
            'mesaNumero'    => 'número',
            'mesaNombre'    => 'nombre',
            'mesaCapacidad' => 'capacidad',
        ]);

        $data = [
            'numero'    => (int) $this->mesaNumero,
            'nombre'    => $this->mesaNombre ?: null,
            'capacidad' => (int) $this->mesaCapacidad,
            'tienda_id' => $this->tiendaId,
        ];

        if ($this->editandoMesaId) {
            Mesa::findOrFail($this->editandoMesaId)->update($data);
            $msg = 'Mesa actualizada.';
        } else {
            // Posición inicial automática en cuadrícula
            $count      = Mesa::where('tienda_id', $this->tiendaId)->count();
            $data['pos_x'] = ($count % 5) * 148 + 16;
            $data['pos_y'] = (int) floor($count / 5) * 180 + 16;
            Mesa::create($data);
            $msg = 'Mesa creada.';
        }

        Flux::modal('form-mesa')->close();
        $this->reset(['editandoMesaId', 'mesaNombre']);
        $this->mesaNumero = '';
        $this->mesaCapacidad = '4';
        Flux::toast(variant: 'success', text: $msg);
    }

    public function eliminarMesa(int $id): void
    {
        Mesa::findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: 'Mesa eliminada.');
    }

    #[Renderless]
    public function moverMesa(int $id, int $x, int $y): void
    {
        Mesa::where('id', $id)->where('tienda_id', $this->tiendaId)
            ->update(['pos_x' => max(0, $x), 'pos_y' => max(0, $y)]);
    }

    public function irAMesa(int $mesaId): void
    {
        $this->redirectRoute('comandas.tomar', ['mesa' => $mesaId], navigate: true);
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php
    $tienda = $tiendaId ? Tienda::find($tiendaId) : null;
    $mesas  = $tiendaId
        ? Mesa::where('tienda_id', $tiendaId)->with('comandaActiva')->orderBy('numero')->get()
        : collect();
    $isAdmin = auth()->user()->hasRole('admin');
@endphp

<style>
    #tablero-mesas {
        background-color: #f4f4f5;
        background-image: radial-gradient(circle, #d1d5db 1.5px, transparent 1.5px);
        background-size: 28px 28px;
    }
    .dark #tablero-mesas {
        background-color: #18181b;
        background-image: radial-gradient(circle, #3f3f46 1.5px, transparent 1.5px);
    }
</style>

<div class="max-w-5xl mx-auto space-y-4"
    @if($tiendaId)
    x-data
    x-init="echoWhen(e => e.channel('comandas.{{ $tiendaId }}').listen('.MesaActualizada', () => window.dispatchEvent(new CustomEvent('mesa-actualizada'))))"
    @mesa-actualizada.window="$wire.$refresh()"
    @endif>

    <div>
        <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Mesas</h1>
        <p class="text-xs text-zinc-400 mt-0.5">Selecciona una mesa para tomar el pedido</p>
    </div>

    {{-- Paso 1: Tienda --}}
    @if($paso === 1)
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4">
            <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 mb-3">¿De qué tienda?</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach(Tienda::orderBy('nombre')->get() as $t)
                    <button wire:click="seleccionarTienda({{ $t->id }})"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-zinc-200 dark:border-zinc-700
                                   hover:border-[#E8642E] hover:bg-orange-50/50 dark:hover:bg-zinc-800 transition-all">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white text-sm"
                             style="background-color:{{ $t->color }}">{{ $t->codigo }}</div>
                        <div class="text-center">
                            <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 leading-tight">{{ $t->nombre }}</p>
                            <p class="text-[10px] text-zinc-400 mt-0.5">{{ $t->direccion }}</p>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Paso 2: Tablero --}}
    @if($paso === 2 && $tienda)
        {{-- Barra superior --}}
        <div class="flex items-center gap-2">
            @if(!auth()->user()->tienda_id)
                <button wire:click="volver" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors">
                    <flux:icon.arrow-left class="w-4 h-4" />
                </button>
            @endif
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white"
                     style="background-color:{{ $tienda->color }}">{{ $tienda->codigo }}</div>
                <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $tienda->nombre }}</span>
            </div>
            <div class="flex-1"></div>
            @if($isAdmin)
                @if($mesas->isNotEmpty())
                    <p class="text-[10px] text-zinc-400">Arrastra para reubicar</p>
                @endif
                <flux:button size="sm" icon="plus" wire:click="nuevaMesa">Mesa</flux:button>
            @endif
        </div>

        {{-- Tablero --}}
        @if($mesas->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <flux:icon.squares-2x2 class="w-8 h-8 text-zinc-300 mb-2" />
                <p class="text-sm text-zinc-400">No hay mesas configuradas.</p>
                @if($isAdmin)
                    <flux:button size="sm" class="mt-3" wire:click="nuevaMesa">Agregar primera mesa</flux:button>
                @endif
            </div>
        @else
            <div
                id="tablero-mesas"
                class="relative rounded-xl border border-zinc-300 dark:border-zinc-700 overflow-hidden select-none"
                style="height: 560px;"
                x-data="{
                    draggingId: null,
                    moved: false,
                    oX: 0, oY: 0,
                    sL: 0, sT: 0,
                    isAdmin: {{ $isAdmin ? 'true' : 'false' }},

                    down(event, id) {
                        if (event.button !== 0) return;
                        this.draggingId = id;
                        this.moved = false;
                        const el = document.getElementById('mesa-' + id);
                        const er = el.getBoundingClientRect();
                        this.oX = event.clientX - er.left;
                        this.oY = event.clientY - er.top;
                        this.sL = parseInt(el.style.left) || 0;
                        this.sT = parseInt(el.style.top) || 0;
                    },

                    move(event) {
                        if (this.draggingId === null || !this.isAdmin) return;
                        const canvas = document.getElementById('tablero-mesas');
                        const cr = canvas.getBoundingClientRect();
                        const el = document.getElementById('mesa-' + this.draggingId);
                        if (!el) return;
                        const x = Math.max(0, Math.min(event.clientX - cr.left - this.oX, canvas.offsetWidth - el.offsetWidth - 2));
                        const y = Math.max(0, Math.min(event.clientY - cr.top - this.oY, canvas.offsetHeight - el.offsetHeight - 2));
                        el.style.left = x + 'px';
                        el.style.top  = y + 'px';
                        this.moved = Math.abs(x - this.sL) > 4 || Math.abs(y - this.sT) > 4;
                    },

                    up() {
                        if (this.draggingId === null) return;
                        const el = document.getElementById('mesa-' + this.draggingId);
                        const id = this.draggingId;
                        this.draggingId = null;
                        if (this.moved && el) {
                            $wire.moverMesa(id, parseInt(el.style.left) || 0, parseInt(el.style.top) || 0);
                        } else if (!this.moved) {
                            $wire.irAMesa(id);
                        }
                        this.moved = false;
                    }
                }"
                @mousemove="move($event)"
                @mouseup="up()"
                @mouseleave="up()"
                :class="draggingId !== null ? 'cursor-grabbing' : ''"
            >
                @foreach($mesas as $mesa)
                    @php
                        $cap          = $mesa->capacidad;
                        $chairsTop    = max(1, min(3, (int) ceil($cap / 4)));
                        $chairsBottom = max(1, min(3, (int) ceil($cap / 4)));
                        $hasLeft      = $cap > 2;
                        $hasRight     = $cap > 2;

                        $borderClass = match($mesa->estado) {
                            'libre'     => 'border-emerald-300 dark:border-emerald-700',
                            'ocupada'   => 'border-amber-300 dark:border-amber-700',
                            'en_cuenta' => 'border-red-300 dark:border-red-700',
                            default     => 'border-zinc-200 dark:border-zinc-700',
                        };
                        $bgClass = match($mesa->estado) {
                            'libre'     => 'bg-emerald-50 dark:bg-emerald-950/40',
                            'ocupada'   => 'bg-amber-50 dark:bg-amber-950/40',
                            'en_cuenta' => 'bg-red-50 dark:bg-red-950/40',
                            default     => 'bg-zinc-50 dark:bg-zinc-900',
                        };
                        $tableClass = match($mesa->estado) {
                            'libre'     => 'bg-emerald-200 dark:bg-emerald-800 border-emerald-400 dark:border-emerald-600',
                            'ocupada'   => 'bg-amber-200 dark:bg-amber-800 border-amber-400 dark:border-amber-600',
                            'en_cuenta' => 'bg-red-200 dark:bg-red-800 border-red-400 dark:border-red-600',
                            default     => 'bg-zinc-200 dark:bg-zinc-700 border-zinc-300',
                        };
                        $chairClass = match($mesa->estado) {
                            'libre'     => 'bg-emerald-400 dark:bg-emerald-600',
                            'ocupada'   => 'bg-amber-400 dark:bg-amber-600',
                            'en_cuenta' => 'bg-red-400 dark:bg-red-600',
                            default     => 'bg-zinc-400',
                        };
                        $numClass = match($mesa->estado) {
                            'libre'     => 'text-emerald-900 dark:text-emerald-100',
                            'ocupada'   => 'text-amber-900 dark:text-amber-100',
                            'en_cuenta' => 'text-red-900 dark:text-red-100',
                            default     => 'text-zinc-700 dark:text-zinc-300',
                        };
                        $badgeClass = match($mesa->estado) {
                            'libre'     => 'text-emerald-700 bg-emerald-100 dark:text-emerald-300 dark:bg-emerald-900/40',
                            'ocupada'   => 'text-amber-700 bg-amber-100 dark:text-amber-300 dark:bg-amber-900/40',
                            'en_cuenta' => 'text-red-600 bg-red-100 dark:text-red-300 dark:bg-red-900/40',
                            default     => 'text-zinc-500 bg-zinc-100',
                        };

                        $posX = $mesa->pos_x ?? 16;
                        $posY = $mesa->pos_y ?? 16;
                    @endphp

                    <div
                        id="mesa-{{ $mesa->id }}"
                        class="absolute group"
                        style="left: {{ $posX }}px; top: {{ $posY }}px;"
                        :class="isAdmin ? 'cursor-grab' : 'cursor-pointer'"
                        @mousedown="down($event, {{ $mesa->id }})"
                    >
                        {{-- Tarjeta de mesa --}}
                        <div class="relative w-28 flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 shadow-sm
                                    {{ $borderClass }} {{ $bgClass }}
                                    hover:shadow-md transition-shadow">

                            {{-- Botón editar (solo admin) --}}
                            @if($isAdmin)
                                <button
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-md flex items-center justify-center
                                           text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200
                                           hover:bg-white/70 dark:hover:bg-zinc-700/70 transition-colors"
                                    wire:click="editarMesa({{ $mesa->id }})"
                                    @mousedown.stop
                                    title="Editar mesa"
                                >
                                    <flux:icon.pencil class="w-3 h-3" />
                                </button>
                            @endif

                            {{-- Figura de mesa con sillas --}}
                            <div class="flex flex-col items-center py-1">
                                {{-- Sillas arriba --}}
                                <div class="flex gap-1.5 mb-0.5">
                                    @for ($i = 0; $i < $chairsTop; $i++)
                                        <div class="w-5 h-2.5 rounded-t-full {{ $chairClass }}"></div>
                                    @endfor
                                </div>
                                {{-- Mesa + sillas laterales --}}
                                <div class="flex items-center">
                                    @if($hasLeft)
                                        <div class="w-2.5 h-5 rounded-l-full {{ $chairClass }}"></div>
                                    @endif
                                    <div class="w-14 h-10 rounded-lg border-2 {{ $tableClass }} flex items-center justify-center">
                                        <span class="text-sm font-bold {{ $numClass }}">{{ $mesa->numero }}</span>
                                    </div>
                                    @if($hasRight)
                                        <div class="w-2.5 h-5 rounded-r-full {{ $chairClass }}"></div>
                                    @endif
                                </div>
                                {{-- Sillas abajo --}}
                                <div class="flex gap-1.5 mt-0.5">
                                    @for ($i = 0; $i < $chairsBottom; $i++)
                                        <div class="w-5 h-2.5 rounded-b-full {{ $chairClass }}"></div>
                                    @endfor
                                </div>
                            </div>

                            {{-- Nombre --}}
                            @if($mesa->nombre)
                                <p class="text-[10px] font-semibold text-center leading-tight {{ $numClass }}">
                                    {{ $mesa->nombre }}
                                </p>
                            @endif

                            {{-- Estado --}}
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-full {{ $badgeClass }}">
                                {{ $mesa->etiquetaEstado() }}
                            </span>

                            {{-- Folio activo --}}
                            @if($mesa->comandaActiva)
                                <p class="text-[9px] font-mono text-zinc-500 dark:text-zinc-400 -mt-0.5">
                                    {{ $mesa->comandaActiva->folio }}
                                </p>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>

            {{-- Leyenda --}}
            <div class="flex items-center gap-5 text-[10px] text-zinc-400">
                <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-emerald-400"></div>Libre</div>
                <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-amber-400"></div>Ocupada</div>
                <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-red-400"></div>En cuenta</div>
            </div>
        @endif
    @endif

    {{-- Modal: agregar / editar mesa --}}
    <flux:modal name="form-mesa" class="w-80">
        <flux:heading size="sm">{{ $editandoMesaId ? 'Editar mesa' : 'Nueva mesa' }}</flux:heading>
        <div class="mt-4 space-y-3">
            <flux:input wire:model="mesaNumero" label="Número de mesa" type="number" min="1" placeholder="1" />
            <flux:input wire:model="mesaNombre" label="Nombre (opcional)" placeholder="Terraza, Bar, Privado..." />
            <flux:input wire:model="mesaCapacidad" label="Capacidad (personas)" type="number" min="1" max="50" />
        </div>
        <div class="flex justify-between mt-5">
            @if($editandoMesaId)
                <flux:button variant="danger" size="sm" wire:click="eliminarMesa({{ $editandoMesaId }})"
                             wire:confirm="¿Eliminar esta mesa? Se eliminarán sus comandas.">
                    Eliminar
                </flux:button>
            @else
                <div></div>
            @endif
            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" size="sm" wire:click="guardarMesa">
                    {{ $editandoMesaId ? 'Guardar cambios' : 'Crear mesa' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
