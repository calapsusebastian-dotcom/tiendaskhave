<?php

use App\Models\MateriaPrima;
use App\Models\Proveedor;
use Flux\Flux;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Materias primas · Logística Proveedores'), Lazy] class extends Component
{
    #[Url] public string $busqueda = '';
    #[Url] public string $filtroProveedor = 'all';

    public string $nombre = '';
    public string $codigoProducto = '';
    public string $unidad = '';
    public string $precio = '';
    public string $iva = '19';
    public ?int $proveedorId = null;
    public ?int $editandoId = null;

    #[Renderless]
    public function nuevo(): void
    {
        $this->reset(['nombre', 'codigoProducto', 'unidad', 'precio', 'proveedorId', 'editandoId']);
        $this->iva = '19';
        Flux::modal('form-materia')->show();
    }

    #[Renderless]
    public function editar(int $id): void
    {
        $m = MateriaPrima::findOrFail($id);
        $this->editandoId      = $id;
        $this->nombre          = $m->nombre;
        $this->codigoProducto  = $m->codigo_producto ?? '';
        $this->unidad          = $m->unidad;
        $this->precio          = (string) $m->precio;
        $this->iva             = (string) $m->iva;
        $this->proveedorId     = $m->proveedor_id;
        Flux::modal('form-materia')->show();
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'nombre'         => 'required|string|max:100',
            'codigoProducto' => 'nullable|string|max:50',
            'unidad'         => 'required|in:LT,GRAMO,KG,UND',
            'precio'         => 'required|numeric|min:0',
            'iva'            => 'required|in:19,5,0',
            'proveedorId'    => 'required|exists:proveedores,id',
        ], [], [
            'nombre'         => 'nombre',
            'codigoProducto' => 'código de producto',
            'unidad'         => 'unidad',
            'precio'         => 'precio',
            'iva'            => 'IVA',
            'proveedorId'    => 'proveedor',
        ]);

        $payload = [
            'nombre'          => $data['nombre'],
            'codigo_producto' => $data['codigoProducto'] ?: null,
            'unidad'          => $data['unidad'],
            'precio'          => $data['precio'],
            'iva'             => (int) $data['iva'],
            'proveedor_id'    => $data['proveedorId'],
        ];

        if ($this->editandoId) {
            MateriaPrima::findOrFail($this->editandoId)->update($payload);
            $msg = 'Materia prima actualizada.';
        } else {
            MateriaPrima::create(array_merge($payload, ['activo' => true]));
            $msg = 'Materia prima creada.';
        }

        Flux::modal('form-materia')->close();
        $this->reset(['nombre', 'codigoProducto', 'unidad', 'precio', 'proveedorId', 'editandoId']);
        $this->iva = '19';
        Flux::toast(variant: 'success', text: $msg);
    }

    public function toggleActivo(int $id): void
    {
        $m = MateriaPrima::findOrFail($id);
        $m->update(['activo' => !$m->activo]);
        Flux::toast(variant: 'success', text: $m->activo ? 'Producto activado.' : 'Producto inactivado.');
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php
    $proveedores = Proveedor::orderBy('nombre')->get();

    $grupos = MateriaPrima::with('proveedor')
        ->when($filtroProveedor !== 'all', fn($q) => $q->where('proveedor_id', $filtroProveedor))
        ->when($busqueda, fn($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'))
        ->orderBy('nombre')
        ->get()
        ->groupBy('proveedor_id');

    $total = $grupos->flatten()->count();
@endphp

<div class="space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Materias primas</h1>
            <p class="text-xs text-zinc-400 mt-0.5">{{ $total }} producto{{ $total !== 1 ? 's' : '' }} en catálogo</p>
        </div>
        <flux:button variant="primary" icon="plus" size="sm" wire:click="nuevo">Nueva materia prima</flux:button>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-wrap gap-2 p-3 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
        <flux:input wire:model.live.debounce.300ms="busqueda" placeholder="Buscar..." icon="magnifying-glass" class="flex-1 min-w-40" size="sm" />
        <flux:select wire:model.live="filtroProveedor" class="min-w-44" size="sm">
            <flux:select.option value="all">Todos los proveedores</flux:select.option>
            @foreach($proveedores as $prov)
                <flux:select.option value="{{ $prov->id }}">{{ $prov->nombre }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Grupos por proveedor --}}
    @forelse($grupos as $proveedorId => $mats)
        @php $prov = $mats->first()->proveedor; @endphp
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                <div>
                    <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $prov?->nombre }}</span>
                    <span class="text-[10px] text-zinc-400 ml-2">{{ $prov?->categoria }}</span>
                </div>
                <span class="text-[10px] text-zinc-400">{{ $mats->count() }} productos</span>
            </div>
            <table class="w-full">
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                    @foreach($mats as $mat)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors {{ !$mat->activo ? 'opacity-50' : '' }}">
                            <td class="py-2 px-4">
                                <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $mat->nombre }}
                                    @if($mat->codigo_producto)
                                        <span class="font-mono font-normal text-zinc-400 ml-1">{{ $mat->codigo_producto }}</span>
                                    @endif
                                </p>
                            </td>
                            <td class="py-2 px-3 text-center">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400">
                                    {{ $mat->unidad }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-center">
                                @php $ivaLabel = match((int)$mat->iva) { 19 => '19%', 5 => '5%', default => 'Exento' }; @endphp
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                    {{ (int)$mat->iva === 19 ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' :
                                       ((int)$mat->iva === 5  ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400'
                                                              : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400') }}">
                                    IVA {{ $ivaLabel }}
                                </span>
                            </td>
                            <td class="py-2 px-4 text-right text-xs font-semibold text-zinc-900 dark:text-white">${{ number_format($mat->precio, 2) }}</td>
                            <td class="py-2 px-3 text-right w-28">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="editar({{ $mat->id }})"
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                            title="Editar">
                                        <flux:icon.pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button wire:click="toggleActivo({{ $mat->id }})"
                                            wire:confirm="{{ $mat->activo ? '¿Inactivar este producto? No aparecerá en los pedidos.' : '¿Activar este producto?' }}"
                                            class="flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-medium transition-colors
                                                {{ $mat->activo
                                                    ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-500'
                                                    : 'text-zinc-400 bg-zinc-100 dark:bg-zinc-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600' }}"
                                            title="{{ $mat->activo ? 'Inactivar' : 'Activar' }}">
                                        @if($mat->activo)
                                            <flux:icon.check-circle class="w-3.5 h-3.5" />
                                            Activo
                                        @else
                                            <flux:icon.x-circle class="w-3.5 h-3.5" />
                                            Inactivo
                                        @endif
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center py-16 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 text-center">
            <flux:icon.magnifying-glass class="w-6 h-6 text-zinc-300 dark:text-zinc-600 mb-2" />
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Sin resultados</p>
        </div>
    @endforelse

    {{-- Modal --}}
    <flux:modal name="form-materia" class="max-w-md">
        <form wire:submit="guardar" class="space-y-3">
            <div class="mb-1">
                <flux:heading size="lg">{{ $editandoId ? 'Editar materia prima' : 'Nueva materia prima' }}</flux:heading>
                <flux:subheading>{{ $editandoId ? 'Modifica los datos del producto.' : 'Agrega un nuevo producto al catálogo.' }}</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model.blur="nombre" label="Nombre *" placeholder="Ej: Leche entera" required autofocus size="sm" />
                <flux:input wire:model.blur="codigoProducto" label="Código de producto" placeholder="Ej: MP-001" size="sm" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="unidad" label="Unidad *" size="sm">
                    <flux:select.option value="">Selecciona...</flux:select.option>
                    <flux:select.option value="LT">LT — Litro</flux:select.option>
                    <flux:select.option value="GRAMO">GRAMO</flux:select.option>
                    <flux:select.option value="KG">KG — Kilogramo</flux:select.option>
                    <flux:select.option value="UND">UND — Unidad</flux:select.option>
                </flux:select>
                <flux:select wire:model="iva" label="IVA *" size="sm">
                    <flux:select.option value="19">19%</flux:select.option>
                    <flux:select.option value="5">5%</flux:select.option>
                    <flux:select.option value="0">Exento</flux:select.option>
                </flux:select>
            </div>

            <flux:input wire:model.blur="precio" label="Precio (sin IVA) *" placeholder="0.00" type="number" step="0.01" min="0" required size="sm" />

            <flux:select wire:model="proveedorId" label="Proveedor *" size="sm">
                <flux:select.option value="">Selecciona un proveedor...</flux:select.option>
                @foreach($proveedores as $prov)
                    <flux:select.option value="{{ $prov->id }}">{{ $prov->nombre }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2 pt-1">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" size="sm">
                    {{ $editandoId ? 'Guardar cambios' : 'Crear producto' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

</div>
