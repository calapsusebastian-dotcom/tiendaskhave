<?php

use App\Models\Proveedor;
use Flux\Flux;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Proveedores · Logística Proveedores'), Lazy] class extends Component
{
    public string $nombre = '';
    public string $contacto = '';
    public string $telefono = '';
    public string $email = '';
    public string $categoria = '';
    public ?int $editandoId = null;

    #[Renderless]
    public function nuevo(): void
    {
        $this->reset(['nombre', 'contacto', 'telefono', 'email', 'categoria', 'editandoId']);
        Flux::modal('form-proveedor')->show();
    }

    #[Renderless]
    public function editar(int $id): void
    {
        $p = Proveedor::findOrFail($id);
        $this->editandoId = $id;
        $this->nombre    = $p->nombre;
        $this->contacto  = $p->contacto ?? '';
        $this->telefono  = $p->telefono ?? '';
        $this->email     = $p->email ?? '';
        $this->categoria = $p->categoria ?? '';
        Flux::modal('form-proveedor')->show();
    }

    public function guardar(): void
    {
        $data = $this->validate([
            'nombre'    => 'required|string|max:100',
            'contacto'  => 'nullable|string|max:100',
            'telefono'  => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:100',
            'categoria' => 'nullable|string|max:50',
        ]);

        if ($this->editandoId) {
            Proveedor::findOrFail($this->editandoId)->update($data);
            $msg = 'Proveedor actualizado.';
        } else {
            Proveedor::create($data);
            $msg = 'Proveedor creado.';
        }

        Flux::modal('form-proveedor')->close();
        $this->reset(['nombre', 'contacto', 'telefono', 'email', 'categoria', 'editandoId']);
        Flux::toast(variant: 'success', text: $msg);
    }

    public function toggleActivo(int $id): void
    {
        $p = Proveedor::findOrFail($id);
        $p->update(['activo' => ! $p->activo]);
        Flux::toast(variant: 'success', text: $p->activo ? 'Proveedor activado.' : 'Proveedor desactivado.');
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php $proveedores = Proveedor::withCount('materiasPrimas')->orderBy('nombre')->get(); @endphp

<div class="space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Proveedores</h1>
            <p class="text-xs text-zinc-400 mt-0.5">{{ $proveedores->count() }} proveedor{{ $proveedores->count() !== 1 ? 'es' : '' }} registrado{{ $proveedores->count() !== 1 ? 's' : '' }}</p>
        </div>
        <flux:button variant="primary" icon="plus" size="sm" wire:click="nuevo">Nuevo proveedor</flux:button>
    </div>

    {{-- Grid --}}
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
        @foreach($proveedores as $p)
            <div class="bg-white dark:bg-zinc-900 rounded-xl border {{ $p->activo ? 'border-zinc-200 dark:border-zinc-800' : 'border-zinc-100 dark:border-zinc-800/50 opacity-60' }} overflow-hidden">
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                                <flux:icon.building-storefront class="w-4 h-4 text-zinc-500 dark:text-zinc-400" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-zinc-900 dark:text-white leading-tight">{{ $p->nombre }}</p>
                                <p class="text-[10px] text-zinc-400 mt-0.5">{{ $p->categoria }}</p>
                            </div>
                        </div>
                        <flux:badge color="{{ $p->activo ? 'green' : 'zinc' }}" size="sm">
                            {{ $p->activo ? 'Activo' : 'Inactivo' }}
                        </flux:badge>
                    </div>

                    <div class="space-y-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                        @if($p->contacto)
                            <div class="flex items-center gap-1.5">
                                <flux:icon.user class="w-3 h-3 shrink-0 text-zinc-400" />
                                <span>{{ $p->contacto }}</span>
                            </div>
                        @endif
                        @if($p->telefono)
                            <div class="flex items-center gap-1.5">
                                <flux:icon.phone class="w-3 h-3 shrink-0 text-zinc-400" />
                                <span>{{ $p->telefono }}</span>
                            </div>
                        @endif
                        @if($p->email)
                            <div class="flex items-center gap-1.5">
                                <flux:icon.envelope class="w-3 h-3 shrink-0 text-zinc-400" />
                                <span class="truncate">{{ $p->email }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between px-4 py-2 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/40">
                    <span class="text-[10px] text-zinc-400">{{ $p->materias_primas_count }} producto{{ $p->materias_primas_count !== 1 ? 's' : '' }}</span>
                    <div class="flex gap-1.5">
                        <button wire:click="editar({{ $p->id }})"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                            <flux:icon.pencil class="w-3 h-3" />
                            Editar
                        </button>
                        <button wire:click="toggleActivo({{ $p->id }})"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium transition-colors
                                    {{ $p->activo
                                        ? 'text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20'
                                        : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20' }}">
                            @if($p->activo)
                                <flux:icon.x-circle class="w-3 h-3" />
                                Inactivar
                            @else
                                <flux:icon.check-circle class="w-3 h-3" />
                                Activar
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Modal form --}}
    <flux:modal name="form-proveedor" class="max-w-md">
        <form wire:submit="guardar" class="space-y-3">
            <div class="mb-1">
                <flux:heading size="lg">{{ $editandoId ? 'Editar proveedor' : 'Nuevo proveedor' }}</flux:heading>
                <flux:subheading>{{ $editandoId ? 'Modifica los datos del proveedor.' : 'Registra un nuevo proveedor en el catálogo.' }}</flux:subheading>
            </div>

            <flux:input wire:model.blur="nombre"    label="Nombre *"   placeholder="Nombre del proveedor" required autofocus size="sm" />
            <flux:input wire:model.blur="categoria" label="Categoría"  placeholder="Café y granos, Lácteos, Panadería..." size="sm" />

            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model.blur="contacto" label="Contacto"  placeholder="Responsable" size="sm" />
                <flux:input wire:model.blur="telefono" label="Teléfono"  placeholder="55 1234 5678" type="tel" size="sm" />
            </div>
            <flux:input wire:model.blur="email" label="Correo" placeholder="ventas@proveedor.mx" type="email" size="sm" />

            <div class="flex justify-end gap-2 pt-1">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" size="sm">
                    {{ $editandoId ? 'Guardar cambios' : 'Crear proveedor' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

</div>
