<?php

use App\Models\Tienda;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Usuarios · Logística Proveedores')] class extends Component
{
    #[Url] public string $busqueda = '';

    public ?int   $editandoId         = null;
    public string $nombre             = '';
    public string $email              = '';
    public string $password           = '';
    public ?int   $tiendaId           = null;
    /** @var string[] */
    public array  $rolesSeleccionados = [];

    private function resetForm(): void
    {
        $this->reset(['editandoId', 'nombre', 'email', 'password', 'tiendaId']);
        $this->rolesSeleccionados = [];
    }

    public function abrirNuevo(): void
    {
        $this->resetForm();
        Flux::modal('modal-usuario')->show();
    }

    public function abrirEditar(int $id): void
    {
        $this->resetForm();
        $u = User::with('roles')->findOrFail($id);
        $this->editandoId         = $id;
        $this->nombre             = $u->name;
        $this->email              = $u->email;
        $this->tiendaId           = $u->tienda_id;
        $this->rolesSeleccionados = $u->roles->pluck('name')->toArray();
        Flux::modal('modal-usuario')->show();
    }

    public function guardar(): void
    {
        $rules = [
            'nombre' => 'required|string|max:100',
            'email'  => 'required|email|max:150|unique:users,email' . ($this->editandoId ? ",{$this->editandoId}" : ''),
        ];

        if (! $this->editandoId) {
            $rules['password'] = 'required|string|min:8';
        } elseif ($this->password !== '') {
            $rules['password'] = 'string|min:8';
        }

        $this->validate($rules, [], ['nombre' => 'Nombre', 'email' => 'Correo', 'password' => 'Contraseña']);

        if (empty($this->rolesSeleccionados)) {
            $this->addError('roles', 'Selecciona al menos un módulo.');
            return;
        }

        if ($this->editandoId) {
            $u = User::findOrFail($this->editandoId);
            $data = ['name' => $this->nombre, 'email' => $this->email, 'tienda_id' => $this->tiendaId];
            if ($this->password !== '') {
                $data['password'] = Hash::make($this->password);
            }
            $u->update($data);
            $u->syncRoles($this->rolesSeleccionados);
            Flux::toast(variant: 'success', text: 'Usuario actualizado.');
        } else {
            $u = User::create([
                'name'      => $this->nombre,
                'email'     => $this->email,
                'password'  => Hash::make($this->password),
                'tienda_id' => $this->tiendaId,
            ]);
            $u->syncRoles($this->rolesSeleccionados);
            Flux::toast(variant: 'success', text: 'Usuario creado.');
        }

        Flux::modal('modal-usuario')->close();
        $this->resetForm();
    }

    public function eliminar(int $id): void
    {
        if ($id === auth()->id()) {
            Flux::toast(variant: 'warning', text: 'No puedes eliminar tu propio usuario.');
            return;
        }
        User::findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: 'Usuario eliminado.');
    }
}; ?>

@php
    $usuarios = User::with(['roles', 'tienda'])
        ->when($busqueda, fn($q) => $q->where('name', 'like', "%{$busqueda}%")->orWhere('email', 'like', "%{$busqueda}%"))
        ->orderBy('name')
        ->get();

    $tiendas = Tienda::orderBy('nombre')->get();

    $colorRol = fn(string $r) => match($r) {
        'admin'                 => 'rose',
        'logistica'             => 'orange',
        'logistica.pedidos'     => 'blue',
        'logistica.materias'    => 'violet',
        'logistica.proveedores' => 'amber',
        'inventarios'           => 'cyan',
        'comandas'              => 'green',
        'comandas.mesas'        => 'emerald',
        'comandas.historial'    => 'teal',
        'comandas.carta'        => 'lime',
        'traslados'             => 'sky',
        'traslados.imov'        => 'indigo',
        default                 => 'zinc',
    };

    $etiquetaRol = fn(string $r) => match($r) {
        'admin'                 => 'Administrador',
        'logistica'             => 'Logística Proveedores',
        'logistica.pedidos'     => 'Pedidos',
        'logistica.materias'    => 'Materias primas',
        'logistica.proveedores' => 'Proveedores',
        'inventarios'           => 'Inventarios',
        'comandas'              => 'Comandas',
        'comandas.mesas'        => 'Mesas',
        'comandas.historial'    => 'Historial',
        'comandas.carta'        => 'Carta',
        'traslados'             => 'Traslados',
        'traslados.imov'        => 'IMOV',
        default                 => $r,
    };

    // Estructura de módulos para el formulario
    $modulos = [
        'Administración' => [
            'admin' => 'Administrador (acceso total)',
        ],
        'Logística de Proveedores' => [
            'logistica'             => 'Logística Proveedores (acceso completo al módulo)',
            'logistica.pedidos'     => 'Solo Pedidos',
            'logistica.materias'    => 'Solo Materias primas',
            'logistica.proveedores' => 'Solo Proveedores',
        ],
        'Inventarios' => [
            'inventarios' => 'Inventarios (diligenciar y consultar inventarios)',
        ],
        'Comandas' => [
            'comandas'           => 'Comandas (acceso completo al módulo)',
            'comandas.mesas'     => 'Solo Mesas y tomar pedido',
            'comandas.historial' => 'Solo Historial',
            'comandas.carta'     => 'Solo Carta (gestionar menú)',
        ],
        'Traslados' => [
            'traslados'      => 'Traslados (crear y gestionar traslados entre tiendas)',
            'traslados.imov' => 'Solo registrar IMOV en traslados',
        ],
    ];
@endphp

<div class="space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Usuarios</h1>
            <p class="text-xs text-zinc-400 mt-0.5">{{ $usuarios->count() }} usuario{{ $usuarios->count() !== 1 ? 's' : '' }}</p>
        </div>
        <flux:button variant="primary" icon="plus" size="sm" wire:click="abrirNuevo">
            Nuevo usuario
        </flux:button>
    </div>

    {{-- Buscador --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-3">
        <flux:input wire:model.live.debounce.300ms="busqueda" placeholder="Buscar por nombre o correo..." icon="magnifying-glass" size="sm" />
    </div>

    {{-- Tabla --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        @if($usuarios->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <flux:icon.users class="w-8 h-8 text-zinc-300 dark:text-zinc-600 mb-2" />
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Sin usuarios</p>
            </div>
        @else
            <table class="w-full">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-4">Usuario</th>
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3 hidden sm:table-cell">Correo</th>
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3 hidden md:table-cell">Tienda</th>
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Accesos</th>
                        <th class="py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                    @foreach($usuarios as $u)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="py-2.5 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-[#E8642E] flex items-center justify-center text-[10px] font-bold text-white shrink-0">
                                        {{ $u->initials() }}
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $u->name }}</p>
                                        <p class="text-[10px] text-zinc-400 sm:hidden">{{ $u->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-xs text-zinc-400 hidden sm:table-cell">{{ $u->email }}</td>
                            <td class="py-2.5 px-3 hidden md:table-cell">
                                @if($u->tienda)
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white shrink-0"
                                             style="background-color:{{ $u->tienda->color }}">{{ $u->tienda->codigo }}</div>
                                        <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $u->tienda->nombre }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-300">—</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($u->roles as $rol)
                                        <flux:badge color="{{ $colorRol($rol->name) }}" size="sm">{{ $etiquetaRol($rol->name) }}</flux:badge>
                                    @empty
                                        <span class="text-xs text-zinc-300">Sin acceso</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="abrirEditar({{ $u->id }})"
                                            class="p-1 rounded-md text-zinc-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                                            title="Editar">
                                        <flux:icon.pencil class="w-3.5 h-3.5" />
                                    </button>
                                    @if($u->id !== auth()->id())
                                        <button wire:click="eliminar({{ $u->id }})"
                                                wire:confirm="¿Eliminar a {{ $u->name }}? Esta acción no se puede deshacer."
                                                class="p-1 rounded-md text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                                title="Eliminar">
                                            <flux:icon.trash class="w-3.5 h-3.5" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Modal crear / editar usuario --}}
    <flux:modal name="modal-usuario" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">
                    {{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}
                </h3>
                <p class="text-xs text-zinc-400 mt-0.5">
                    {{ $editandoId ? 'Actualiza los datos y los accesos por módulo.' : 'Completa los datos y selecciona los módulos a los que tendrá acceso.' }}
                </p>
            </div>

            <flux:input wire:model.blur="nombre" label="Nombre completo" placeholder="Ej. Juan García" size="sm" />
            @error('nombre') <p class="text-xs text-red-500 -mt-2">{{ $message }}</p> @enderror

            <flux:input wire:model.blur="email" type="email" label="Correo electrónico" placeholder="juan@tiendas.com" size="sm" />
            @error('email') <p class="text-xs text-red-500 -mt-2">{{ $message }}</p> @enderror

            <div>
                <flux:label>Tienda asignada</flux:label>
                <flux:select wire:model="tiendaId" size="sm" class="mt-1">
                    <flux:select.option value="">Sin tienda asignada</flux:select.option>
                    @foreach($tiendas as $t)
                        <flux:select.option value="{{ $t->id }}">
                            {{ $t->codigo }} — {{ $t->nombre }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model.blur="password" type="password"
                        label="{{ $editandoId ? 'Nueva contraseña (dejar vacío para no cambiar)' : 'Contraseña' }}"
                        placeholder="Mínimo 8 caracteres" size="sm" />
            @error('password') <p class="text-xs text-red-500 -mt-2">{{ $message }}</p> @enderror

            {{-- Permisos por módulo --}}
            <div class="space-y-3">
                <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Acceso por módulo</p>

                @foreach($modulos as $modulo => $items)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div class="px-3 py-1.5 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                            <p class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">{{ $modulo }}</p>
                        </div>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($items as $roleKey => $label)
                                <label class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <input type="checkbox"
                                           wire:model="rolesSeleccionados"
                                           value="{{ $roleKey }}"
                                           class="rounded border-zinc-300 text-[#E8642E] focus:ring-[#E8642E] w-4 h-4" />
                                    <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @error('roles') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 justify-end pt-1">
                <flux:button size="sm" variant="ghost" x-on:click="$flux.modal('modal-usuario').close()">Cancelar</flux:button>
                <flux:button size="sm" variant="primary" wire:click="guardar" icon="check">
                    {{ $editandoId ? 'Guardar cambios' : 'Crear usuario' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
