<?php

use App\Models\ProductoMenu;
use App\Models\Tienda;
use Flux\Flux;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Carta · Comandas'), Lazy] class extends Component
{
    #[Url] public string $busqueda = '';
    #[Url] public string $filtroTienda = 'all';

    public string $nombre = '';
    public string $codigo = '';
    public string $descripcion = '';
    public string $precio = '';
    public string $categoria = '';
    public bool   $activo = true;
    public ?int   $formTiendaId = null;
    public ?int   $editandoId = null;

    public function mount(): void
    {
        if (auth()->user()->tienda_id) {
            $this->filtroTienda = (string) auth()->user()->tienda_id;
            $this->formTiendaId = auth()->user()->tienda_id;
        }
    }

    #[Renderless]
    public function nuevo(): void
    {
        $this->reset(['nombre', 'codigo', 'descripcion', 'precio', 'categoria', 'editandoId']);
        $this->activo = true;
        if (!auth()->user()->tienda_id) {
            $this->formTiendaId = null;
        }
        Flux::modal('form-producto')->show();
    }

    #[Renderless]
    public function editar(int $id): void
    {
        $p = ProductoMenu::findOrFail($id);
        $this->editandoId   = $id;
        $this->nombre       = $p->nombre;
        $this->codigo       = $p->codigo ?? '';
        $this->descripcion  = $p->descripcion ?? '';
        $this->precio       = (string) $p->precio;
        $this->categoria    = $p->categoria;
        $this->activo       = $p->activo;
        $this->formTiendaId = $p->tienda_id;
        Flux::modal('form-producto')->show();
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'nombre'       => 'required|string|max:100',
            'codigo'       => 'nullable|string|max:50',
            'descripcion'  => 'nullable|string|max:200',
            'precio'       => 'required|numeric|min:0',
            'categoria'    => 'required|string|max:60',
            'activo'       => 'boolean',
            'formTiendaId' => 'required|exists:tiendas,id',
        ], [], [
            'nombre'       => 'nombre',
            'codigo'       => 'código',
            'descripcion'  => 'descripción',
            'precio'       => 'precio',
            'categoria'    => 'categoría',
            'formTiendaId' => 'tienda',
        ]);

        $payload = [
            'nombre'      => $data['nombre'],
            'codigo'      => $data['codigo'] ?: null,
            'descripcion' => $data['descripcion'] ?: null,
            'precio'      => $data['precio'],
            'categoria'   => $data['categoria'],
            'activo'      => $data['activo'],
            'tienda_id'   => $data['formTiendaId'],
        ];

        if ($this->editandoId) {
            ProductoMenu::findOrFail($this->editandoId)->update($payload);
            $msg = 'Producto actualizado.';
        } else {
            ProductoMenu::create($payload);
            $msg = 'Producto creado.';
        }

        Flux::modal('form-producto')->close();
        $this->reset(['nombre', 'codigo', 'descripcion', 'precio', 'categoria', 'editandoId']);
        $this->activo = true;
        Flux::toast(variant: 'success', text: $msg);
    }

    public function eliminar(int $id): void
    {
        ProductoMenu::findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: 'Producto eliminado.');
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php
    $user    = auth()->user();
    $tiendas = Tienda::orderBy('nombre')->get();

    $productos = ProductoMenu::with('tienda')
        ->when($busqueda, fn($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'))
        ->when($user->tienda_id,
            fn($q) => $q->where('tienda_id', $user->tienda_id),
            fn($q) => $q->when($filtroTienda !== 'all', fn($q2) => $q2->where('tienda_id', $filtroTienda))
        )
        ->orderBy('categoria')->orderBy('nombre')
        ->get();

    $agrupados = $productos->groupBy('categoria');
@endphp

<div class="max-w-3xl mx-auto space-y-4">

    <div class="flex items-center gap-3">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Carta</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Productos del menú disponibles para comandas</p>
        </div>
        <div class="flex-1"></div>
        <flux:button size="sm" variant="primary" wire:click="nuevo" icon="plus">Producto</flux:button>
    </div>

    {{-- Filtros --}}
    <div class="flex items-center gap-2">
        <flux:input wire:model.live.debounce.300ms="busqueda" placeholder="Buscar producto..." icon="magnifying-glass" size="sm" class="flex-1" />
        @if(!$user->tienda_id)
            <flux:select wire:model.live="filtroTienda" size="sm" class="w-40">
                <flux:select.option value="all">Todas las tiendas</flux:select.option>
                @foreach($tiendas as $t)
                    <flux:select.option value="{{ $t->id }}">{{ $t->nombre }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    {{-- Lista agrupada por categoría --}}
    @if($agrupados->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
            <flux:icon.book-open class="w-8 h-8 text-zinc-300 mb-2" />
            <p class="text-sm text-zinc-400">No hay productos en la carta.</p>
            <flux:button size="sm" class="mt-3" wire:click="nuevo">Agregar primer producto</flux:button>
        </div>
    @else
        <div class="space-y-3">
            @foreach($agrupados as $categoria => $prods)
                <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                    <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $categoria }}</span>
                    </div>
                    <table class="w-full">
                        <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                            @foreach($prods as $prod)
                                <tr class="{{ !$prod->activo ? 'opacity-50' : '' }}">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $prod->nombre }}</p>
                                            @if($prod->codigo)
                                                <span class="font-mono text-[10px] text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">{{ $prod->codigo }}</span>
                                            @endif
                                        </div>
                                        @if($prod->descripcion)
                                            <p class="text-[10px] text-zinc-400">{{ $prod->descripcion }}</p>
                                        @endif
                                        @if(!$user->tienda_id)
                                            <p class="text-[10px] text-zinc-400">{{ $prod->tienda->nombre }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right w-32">
                                        <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">${{ number_format($prod->precio, 0, '.', ',') }}</p>
                                        <p class="text-[10px] {{ $prod->activo ? 'text-emerald-600' : 'text-zinc-400' }}">
                                            {{ $prod->activo ? 'Activo' : 'Inactivo' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-2.5 w-16 text-right">
                                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editar({{ $prod->id }})" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal --}}
    <flux:modal name="form-producto" class="w-96">
        <flux:heading size="sm">{{ $editandoId ? 'Editar producto' : 'Nuevo producto' }}</flux:heading>
        <div class="mt-4 space-y-3">
            @if(!$user->tienda_id)
                <flux:select wire:model="formTiendaId" label="Tienda">
                    <flux:select.option value="">Selecciona tienda...</flux:select.option>
                    @foreach($tiendas as $t)
                        <flux:select.option value="{{ $t->id }}">{{ $t->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <flux:input wire:model="nombre" label="Nombre" placeholder="Café con leche" />
                </div>
                <flux:input wire:model="codigo" label="Código" placeholder="001" />
            </div>
            <flux:input wire:model="descripcion" label="Descripción (opcional)" placeholder="Con leche de almendras..." />
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="precio" label="Precio" type="number" min="0" step="100" placeholder="5000" />
                <flux:input wire:model="categoria" label="Categoría" placeholder="Bebidas, Platos..." />
            </div>
            @if($editandoId)
                <flux:checkbox wire:model="activo" label="Producto activo" />
            @endif
        </div>
        <div class="flex justify-between mt-5">
            @if($editandoId)
                <flux:button variant="danger" size="sm" wire:click="eliminar({{ $editandoId }})"
                             wire:confirm="¿Eliminar este producto?">
                    Eliminar
                </flux:button>
            @else
                <div></div>
            @endif
            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" size="sm" wire:click="guardar">
                    {{ $editandoId ? 'Guardar' : 'Crear' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
