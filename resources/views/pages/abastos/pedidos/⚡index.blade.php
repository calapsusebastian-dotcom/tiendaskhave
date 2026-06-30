<?php

use App\Events\PedidoActualizado;
use App\Models\Pedido;
use App\Models\Tienda;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Pedidos · Logística Proveedores'), Lazy] class extends Component
{
    use WithFileUploads;

    #[Url] public string $filtroTienda = 'all';
    #[Url] public string $filtroEstado = 'all';
    public ?int   $verDetalle          = null;
    // Modal enviado
    public ?int   $enviandoId          = null;
    public string $cufe                = '';
    #[Validate('nullable|file|mimes:pdf|max:10240')]
    public $facturaPdf                 = null;
    // Modal recibido (tienda)
    public ?int   $recibiendoTiendaId  = null;
    public bool   $recibidoOk          = true;
    public string $observacion         = '';
    // Modal terminado (admin + IMOV)
    public ?int   $terminandoId        = null;
    public string $imov                = '';

    public function verPedido(int $id): void
    {
        $this->verDetalle = $id;
        \Flux\Flux::modal('detalle-pedido')->show();
    }

    public function abrirEnviado(int $id): void
    {
        $this->enviandoId = $id;
        $this->cufe       = '';
        $this->facturaPdf = null;
        \Flux\Flux::modal('modal-enviado')->show();
    }

    public function confirmarEnviado(): void
    {
        $this->validate([
            'cufe'       => 'required|string|max:200',
            'facturaPdf' => 'required|file|mimes:pdf|max:10240',
        ], [], ['cufe' => 'Código CUFE', 'facturaPdf' => 'Factura PDF']);

        $path = $this->facturaPdf->store('facturas', 'public');

        Pedido::findOrFail($this->enviandoId)->update([
            'estado'       => 'enviado',
            'enviado_at'   => now(),
            'cufe'         => $this->cufe,
            'factura_path' => $path,
        ]);

        try { broadcast(new PedidoActualizado()); } catch (\Throwable $e) {}
        \Flux\Flux::modal('modal-enviado')->close();
        \Flux\Flux::modal('detalle-pedido')->close();
        $this->reset(['enviandoId', 'cufe', 'verDetalle']);
        $this->facturaPdf = null;
        \Flux\Flux::toast(variant: 'success', text: 'Pedido marcado como enviado.');
    }

    public function abrirRecibidoTienda(int $id): void
    {
        $this->recibiendoTiendaId = $id;
        $this->recibidoOk         = true;
        $this->observacion        = '';
        \Flux\Flux::modal('modal-recibido-tienda')->show();
    }

    public function confirmarRecibidoTienda(): void
    {
        $this->validate(
            ['observacion' => 'nullable|string|max:500'],
            [],
            ['observacion' => 'Observación']
        );

        Pedido::findOrFail($this->recibiendoTiendaId)->update([
            'estado'      => 'recibido',
            'recibido_at' => now(),
            'recibido_ok' => $this->recibidoOk,
            'observacion' => $this->observacion ?: null,
        ]);

        try { broadcast(new PedidoActualizado()); } catch (\Throwable $e) {}
        \Flux\Flux::modal('modal-recibido-tienda')->close();
        \Flux\Flux::modal('detalle-pedido')->close();
        $this->reset(['recibiendoTiendaId', 'recibidoOk', 'observacion', 'verDetalle']);
        \Flux\Flux::toast(variant: 'success', text: 'Pedido marcado como recibido.');
    }

    public function abrirTerminado(int $id): void
    {
        $this->terminandoId = $id;
        $this->imov         = '';
        \Flux\Flux::modal('modal-terminado')->show();
    }

    public function confirmarTerminado(): void
    {
        $this->validate(['imov' => 'required|string|max:100'], [], ['imov' => 'IMOV']);

        Pedido::findOrFail($this->terminandoId)->update([
            'estado'       => 'terminado',
            'terminado_at' => now(),
            'imov'         => $this->imov,
        ]);

        try { broadcast(new PedidoActualizado()); } catch (\Throwable $e) {}
        \Flux\Flux::modal('modal-terminado')->close();
        \Flux\Flux::modal('detalle-pedido')->close();
        $this->reset(['terminandoId', 'imov', 'verDetalle']);
        \Flux\Flux::toast(variant: 'success', text: 'Pedido terminado.');
    }

    public function eliminar(int $id): void
    {
        $p = Pedido::findOrFail($id);
        $folio = $p->folio;
        $p->items()->delete();
        $p->delete();
        try { broadcast(new PedidoActualizado()); } catch (\Throwable $e) {}
        \Flux\Flux::modal('detalle-pedido')->close();
        $this->verDetalle = null;
        \Flux\Flux::toast(variant: 'success', text: $folio . ' eliminado.');
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $tsCol = match($estado) {
            'aprobado'  => 'aprobado_at',
            'enviado'   => 'enviado_at',
            'recibido'  => 'recibido_at',
            'terminado' => 'terminado_at',
            default     => null,
        };
        $data = ['estado' => $estado];
        if ($tsCol) $data[$tsCol] = now();
        Pedido::findOrFail($id)->update($data);
        try { broadcast(new PedidoActualizado()); } catch (\Throwable $e) {}
        $this->verDetalle = null;
        \Flux\Flux::modal('detalle-pedido')->close();
        \Flux\Flux::toast(variant: 'success', text: 'Estado actualizado.');
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholder');
    }
}; ?>

@php
    $usuarioTiendaId = auth()->user()->tienda_id;
    $esAdmin = auth()->user()->hasRole('admin');
    $todasTiendas = $usuarioTiendaId ? collect() : Tienda::orderBy('nombre')->get();

    $query = Pedido::with(['tienda','proveedor'])
        ->withSum('items as total', \DB::raw('cantidad * precio_unitario'))
        ->latest();

    if ($usuarioTiendaId) {
        $query->where('tienda_id', $usuarioTiendaId);
    } elseif ($filtroTienda !== 'all') {
        $query->where('tienda_id', $filtroTienda);
    }

    if ($filtroEstado !== 'all') $query->where('estado', $filtroEstado);
    $pedidos = $query->paginate(30);
@endphp

<div class="space-y-4"
    x-data
    x-init="echoWhen(e => e.channel('logistica').listen('.PedidoActualizado', () => window.dispatchEvent(new CustomEvent('pedido-actualizado'))))"
    @pedido-actualizado.window="$wire.$refresh()">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Pedidos</h1>
            <p class="text-xs text-zinc-400 mt-0.5">{{ $pedidos->total() }} resultado{{ $pedidos->total() !== 1 ? 's' : '' }}</p>
        </div>
        <flux:button href="{{ route('abastos.pedidos.crear') }}" variant="primary" icon="plus" size="sm" wire:navigate>
            Nuevo pedido
        </flux:button>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-wrap gap-2 p-3 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
        @if(!$usuarioTiendaId)
        <div class="flex-1 min-w-36">
            <flux:select wire:model.live="filtroTienda" size="sm">
                <flux:select.option value="all">Todas las tiendas</flux:select.option>
                @foreach($todasTiendas as $t)
                    <flux:select.option value="{{ $t->id }}">{{ $t->nombre }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @endif
        <div class="flex-1 min-w-36">
            <flux:select wire:model.live="filtroEstado" size="sm">
                <flux:select.option value="all">Todos los estados</flux:select.option>
                <flux:select.option value="por_aprobar">Por aprobar</flux:select.option>
                <flux:select.option value="aprobado">Aprobado</flux:select.option>
                <flux:select.option value="enviado">Enviado</flux:select.option>
                <flux:select.option value="recibido">Recibido</flux:select.option>
                <flux:select.option value="terminado">Terminado</flux:select.option>
                <flux:select.option value="rechazado">Rechazado</flux:select.option>
            </flux:select>
        </div>
        @if((!$usuarioTiendaId && $filtroTienda !== 'all') || $filtroEstado !== 'all')
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('filtroTienda','all'); $set('filtroEstado','all')">
                Limpiar
            </flux:button>
        @endif
    </div>

    {{-- Tabla --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        @if($pedidos->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <flux:icon.clipboard-document-list class="w-8 h-8 text-zinc-300 dark:text-zinc-600 mb-2" />
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Sin pedidos</p>
                <p class="text-xs text-zinc-400 mt-0.5">Cambia los filtros o crea un nuevo pedido.</p>
            </div>
        @else
            <table class="w-full">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-4">Folio</th>
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Tienda</th>
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3 hidden sm:table-cell">Proveedor</th>
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3 hidden md:table-cell">Fecha</th>
                        <th class="text-left text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-3">Estado</th>
                        <th class="text-right text-[10px] font-medium text-zinc-400 uppercase tracking-wide py-2 px-4">Total</th>
                        <th class="py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                    @foreach($pedidos as $pedido)
                        @php [$bc,$bl] = match($pedido->estado) {
                            'por_aprobar' => ['amber','Por aprobar'],
                            'aprobado'    => ['blue','Aprobado'],
                            'enviado'     => ['violet','Enviado'],
                            'recibido'    => ['cyan','Recibido'],
                            'terminado'   => ['green','Terminado'],
                            'rechazado'   => ['red','Rechazado'],
                            default       => ['zinc',$pedido->estado],
                        }; @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="py-2 px-4">
                                <p class="font-mono text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $pedido->folio }}</p>
                                @if($pedido->imov)
                                    <p class="text-[10px] text-green-600 dark:text-green-400 font-medium mt-0.5">IMOV: {{ $pedido->imov }}</p>
                                @endif
                            </td>
                            <td class="py-2 px-3">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0"
                                         style="background-color:{{ $pedido->tienda->color }}">{{ $pedido->tienda->codigo }}</div>
                                    <span class="text-xs text-zinc-700 dark:text-zinc-300 hidden sm:inline">{{ $pedido->tienda->nombre }}</span>
                                </div>
                            </td>
                            <td class="py-2 px-3 text-xs text-zinc-400 hidden sm:table-cell">{{ $pedido->proveedor->nombre }}</td>
                            <td class="py-2 px-3 text-xs text-zinc-400 hidden md:table-cell">{{ $pedido->created_at->format('d/m/Y') }}</td>
                            <td class="py-2 px-3"><flux:badge color="{{ $bc }}" size="sm">{{ $bl }}</flux:badge></td>
                            <td class="py-2 px-4 text-right text-xs font-semibold text-zinc-900 dark:text-white">${{ number_format($pedido->total ?? 0, 0, '.', ',') }}</td>
                            <td class="py-2 px-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="verPedido({{ $pedido->id }})"
                                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                            title="Ver detalle">
                                        <flux:icon.eye class="w-3.5 h-3.5" />
                                    </button>
                                    @if(!$usuarioTiendaId)
                                    <a href="{{ route('abastos.pedidos.editar', $pedido->id) }}" wire:navigate>
                                        <button class="p-1 rounded-md text-zinc-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="Editar">
                                            <flux:icon.pencil class="w-3.5 h-3.5" />
                                        </button>
                                    </a>
                                    <button wire:click="eliminar({{ $pedido->id }})"
                                            wire:confirm="¿Eliminar el pedido {{ $pedido->folio }}? Esta acción no se puede deshacer."
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

    {{-- Modal enviado (CUFE + factura PDF) --}}
    <flux:modal name="modal-enviado" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Marcar como enviado</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Adjunta el código CUFE y la factura en PDF.</p>
            </div>
            <flux:input wire:model="cufe" label="Código CUFE" placeholder="Ej. abc123..." />
            @error('cufe') <p class="text-xs text-red-500 -mt-2">{{ $message }}</p> @enderror

            <div>
                <flux:label>Factura (PDF)</flux:label>
                <div class="mt-1">
                    <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg cursor-pointer hover:border-[#E8642E] hover:bg-orange-50/50 dark:hover:bg-zinc-800 transition-colors">
                        <input type="file" wire:model="facturaPdf" accept=".pdf" class="hidden" />
                        @if($facturaPdf)
                            <flux:icon.document-check class="w-6 h-6 text-[#E8642E] mb-1" />
                            <p class="text-xs font-medium text-[#E8642E]">{{ $facturaPdf->getClientOriginalName() }}</p>
                            <p class="text-[10px] text-zinc-400">{{ number_format($facturaPdf->getSize() / 1024, 0) }} KB</p>
                        @else
                            <flux:icon.arrow-up-tray class="w-5 h-5 text-zinc-400 mb-1" />
                            <p class="text-xs text-zinc-500">Haz clic para subir el PDF</p>
                            <p class="text-[10px] text-zinc-400 mt-0.5">Máximo 10 MB</p>
                        @endif
                    </label>
                </div>
                @error('facturaPdf') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button size="sm" variant="ghost" x-on:click="$flux.modal('modal-enviado').close()">Cancelar</flux:button>
                <flux:button size="sm" variant="primary" wire:click="confirmarEnviado" icon="truck">Confirmar envío</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal recibido (tienda) --}}
    <flux:modal name="modal-recibido-tienda" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Confirmar recepción</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Indica si se recibió todo correctamente.</p>
            </div>
            <label class="flex items-center gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                <input type="checkbox" wire:model="recibidoOk" class="rounded border-zinc-300 text-[#E8642E] focus:ring-[#E8642E] w-4 h-4" />
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Se recibió todo OK</span>
            </label>
            <flux:textarea wire:model="observacion" label="Observaciones (opcional)" placeholder="Ej. Faltaron 2 unidades de harina..." rows="3" />
            @error('observacion') <p class="text-xs text-red-500 -mt-2">{{ $message }}</p> @enderror
            <div class="flex gap-2 justify-end">
                <flux:button size="sm" variant="ghost" x-on:click="$flux.modal('modal-recibido-tienda').close()">Cancelar</flux:button>
                <flux:button size="sm" variant="primary" wire:click="confirmarRecibidoTienda" icon="check-circle">Confirmar recepción</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal terminado (admin + IMOV) --}}
    <flux:modal name="modal-terminado" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Marcar como terminado</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Ingresa el número de IMOV para cerrar el pedido.</p>
            </div>
            <flux:input wire:model="imov" label="IMOV" placeholder="Ej. 123456" autofocus />
            @error('imov') <p class="text-xs text-red-500 -mt-2">{{ $message }}</p> @enderror
            <div class="flex gap-2 justify-end">
                <flux:button size="sm" variant="ghost" x-on:click="$flux.modal('modal-terminado').close()">Cancelar</flux:button>
                <flux:button size="sm" variant="primary" wire:click="confirmarTerminado" icon="check-badge">Confirmar</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal detalle (línea del tiempo) --}}
    <flux:modal name="detalle-pedido" class="max-w-lg w-full">
        @if($verDetalle)
            @php
                $p = Pedido::with(['tienda','proveedor','items.materiaPrima'])->find($verDetalle);
                $ordenEstados = ['por_aprobar','aprobado','enviado','recibido','terminado'];
                $posActual = $p ? array_search($p->estado, $ordenEstados) : false;
                if ($p && $p->estado === 'rechazado') $posActual = -1;
            @endphp
            @if($p)
            <div class="space-y-4">

                {{-- Encabezado --}}
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-bold text-zinc-900 dark:text-white font-mono">{{ $p->folio }}</p>
                        <p class="text-xs text-zinc-400 mt-0.5">Creado el {{ $p->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @php [$bc,$bl] = match($p->estado) {
                        'por_aprobar' => ['amber','Por aprobar'],
                        'aprobado'    => ['blue','Aprobado'],
                        'enviado'     => ['violet','Enviado'],
                        'recibido'    => ['cyan','Recibido'],
                        'terminado'   => ['green','Terminado'],
                        'rechazado'   => ['red','Rechazado'],
                        default       => ['zinc',$p->estado],
                    }; @endphp
                    <flux:badge color="{{ $bc }}" size="sm">{{ $bl }}</flux:badge>
                </div>

                {{-- Tienda + Proveedor --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="flex items-center gap-2 p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0"
                             style="background-color:{{ $p->tienda->color }}">{{ $p->tienda->codigo }}</div>
                        <div class="min-w-0">
                            <p class="text-[9px] text-zinc-400">Tienda</p>
                            <p class="text-xs font-semibold text-zinc-900 dark:text-white truncate">{{ $p->tienda->nombre }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <div class="w-6 h-6 rounded-lg bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center shrink-0">
                            <flux:icon.building-storefront class="w-3 h-3 text-zinc-500" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] text-zinc-400">Proveedor</p>
                            <p class="text-xs font-semibold text-zinc-900 dark:text-white truncate">{{ $p->proveedor->nombre }}</p>
                        </div>
                    </div>
                </div>

                {{-- Productos --}}
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div class="bg-zinc-50 dark:bg-zinc-800 px-3 py-1.5 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                        <p class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wide">Productos</p>
                    </div>
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <th class="px-3 py-1.5 text-left text-[10px] font-medium text-zinc-400">Producto</th>
                                <th class="px-3 py-1.5 text-center text-[10px] font-medium text-zinc-400">Cant.</th>
                                <th class="px-3 py-1.5 text-right text-[10px] font-medium text-zinc-400">P. Unit.</th>
                                <th class="px-3 py-1.5 text-right text-[10px] font-medium text-zinc-400">Subtotal</th>
                                <th class="px-3 py-1.5 text-center text-[10px] font-medium text-zinc-400">IVA</th>
                                <th class="px-3 py-1.5 text-right text-[10px] font-medium text-zinc-400">Con IVA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($p->items as $item)
                                <tr>
                                    <td class="px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300">
                                        <p>{{ $item->materiaPrima->nombre }}</p>
                                        @if($item->materiaPrima->codigo_producto)
                                            <p class="font-mono text-[10px] text-zinc-400 mt-0.5">{{ $item->materiaPrima->codigo_producto }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs text-zinc-500">{{ $item->cantidad }} {{ $item->materiaPrima->unidad }}</td>
                                    <td class="px-3 py-2 text-right text-xs text-zinc-500">${{ number_format($item->precio_unitario,0,'.',',') }}</td>
                                    <td class="px-3 py-2 text-right text-xs text-zinc-600 dark:text-zinc-400">${{ number_format($item->subtotalBase(),0,'.',',') }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded
                                            {{ (int)$item->iva === 19 ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' :
                                               ((int)$item->iva === 5  ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400'
                                                                        : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500') }}">
                                            {{ (int)$item->iva === 0 ? 'Exento' : $item->iva.'%' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs font-semibold text-zinc-900 dark:text-white">${{ number_format($item->subtotal(),0,'.',',') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <td colspan="5" class="px-3 py-2 text-xs font-medium text-zinc-500">Total</td>
                                <td class="px-3 py-2 text-right text-sm font-bold text-zinc-900 dark:text-white">${{ number_format($p->total(),0,'.',',') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if($p->notas)
                    <div class="px-3 py-2 rounded-lg bg-zinc-50 dark:bg-zinc-800 text-xs text-zinc-500">
                        <span class="font-medium text-zinc-600 dark:text-zinc-300">Notas:</span> {{ $p->notas }}
                    </div>
                @endif

                {{-- Línea del tiempo --}}
                <div>
                    <p class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wide mb-3">Historial del proceso</p>
                    @php
                        $pasos = [
                            ['key' => 'por_aprobar', 'label' => 'Solicitud creada',  'fecha' => $p->created_at,   'color' => 'amber',  'pos' => 0],
                            ['key' => 'aprobado',    'label' => 'Aprobado',          'fecha' => $p->aprobado_at,  'color' => 'blue',   'pos' => 1],
                            ['key' => 'enviado',     'label' => 'Enviado',           'fecha' => $p->enviado_at,   'color' => 'violet', 'pos' => 2],
                            ['key' => 'recibido',    'label' => 'Recibido en tienda','fecha' => $p->recibido_at,  'color' => 'cyan',   'pos' => 3],
                            ['key' => 'terminado',   'label' => 'Terminado',         'fecha' => $p->terminado_at, 'color' => 'green',  'pos' => 4],
                        ];
                        if ($p->estado === 'rechazado') {
                            $pasos[] = ['key' => 'rechazado', 'label' => 'Rechazado', 'fecha' => $p->updated_at, 'color' => 'red', 'pos' => 99];
                        }
                        $totalPasos = count($pasos);
                    @endphp
                    <div class="relative">
                        {{-- Línea vertical --}}
                        <div class="absolute left-[11px] top-3 bottom-3 w-0.5 bg-zinc-200 dark:bg-zinc-700"></div>

                        <div class="space-y-1">
                        @foreach($pasos as $i => $paso)
                            @php
                                if ($p->estado === 'rechazado') {
                                    $done = ($paso['key'] === 'rechazado') || ($paso['pos'] < 1);
                                } else {
                                    $done = $posActual !== false && $paso['pos'] <= $posActual;
                                }
                                $current = $paso['key'] === $p->estado;
                                $colorMap = [
                                    'amber'  => ['ring' => 'bg-amber-500',  'soft' => 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800'],
                                    'blue'   => ['ring' => 'bg-blue-500',   'soft' => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'],
                                    'violet' => ['ring' => 'bg-violet-500', 'soft' => 'bg-violet-50 dark:bg-violet-900/20 border-violet-200 dark:border-violet-800'],
                                    'cyan'   => ['ring' => 'bg-cyan-500',   'soft' => 'bg-cyan-50 dark:bg-cyan-900/20 border-cyan-200 dark:border-cyan-800'],
                                    'green'  => ['ring' => 'bg-green-500',  'soft' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'],
                                    'red'    => ['ring' => 'bg-red-500',    'soft' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'],
                                ];
                                $c = $colorMap[$paso['color']] ?? $colorMap['amber'];
                            @endphp
                            <div class="relative flex gap-3 pb-3 last:pb-0">
                                {{-- Círculo --}}
                                <div class="relative z-10 flex-shrink-0 w-[23px] flex justify-center pt-0.5">
                                    @if($done)
                                        <div class="w-[22px] h-[22px] rounded-full {{ $c['ring'] }} flex items-center justify-center
                                            {{ $current ? 'ring-2 ring-offset-2 ring-offset-white dark:ring-offset-zinc-900 ' . $c['ring'] : '' }}">
                                            <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-[22px] h-[22px] rounded-full bg-white dark:bg-zinc-900 border-2 border-zinc-300 dark:border-zinc-600"></div>
                                    @endif
                                </div>

                                {{-- Contenido --}}
                                <div class="flex-1 min-w-0 pb-1">
                                    <div class="flex items-center justify-between gap-2 min-h-[22px]">
                                        <p class="text-xs font-semibold {{ $done ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-400 dark:text-zinc-600' }}">
                                            {{ $paso['label'] }}
                                        </p>
                                        @if($paso['fecha'])
                                            <p class="text-[10px] text-zinc-400 shrink-0">{{ $paso['fecha']->format('d/m/Y H:i') }}</p>
                                        @elseif(!$done)
                                            <p class="text-[10px] text-zinc-300 dark:text-zinc-600 shrink-0">Pendiente</p>
                                        @endif
                                    </div>

                                    @if($done)
                                        {{-- Detalle por etapa --}}
                                        @if($paso['key'] === 'por_aprobar')
                                            {{-- Info ya mostrada arriba (tienda, proveedor, productos) --}}
                                        @endif

                                        @if($paso['key'] === 'enviado' && ($p->cufe || $p->factura_path))
                                            <div class="mt-1.5 p-2 rounded-md border {{ $c['soft'] }} space-y-1.5">
                                                @if($p->cufe)
                                                    <div class="flex items-start gap-1.5">
                                                        <span class="text-[9px] font-bold text-violet-600 dark:text-violet-400 shrink-0 mt-0.5 uppercase tracking-wide">CUFE</span>
                                                        <p class="font-mono text-[9px] text-violet-800 dark:text-violet-300 break-all leading-relaxed flex-1" style="word-break:break-all">{{ $p->cufe }}</p>
                                                        <button onclick="navigator.clipboard.writeText('{{ addslashes($p->cufe) }}')" title="Copiar" class="shrink-0 text-violet-400 hover:text-violet-600 p-0.5 transition-colors">
                                                            <flux:icon.clipboard-document class="w-3 h-3" />
                                                        </button>
                                                    </div>
                                                @endif
                                                @if($p->factura_path)
                                                    <a href="{{ Storage::url($p->factura_path) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-[10px] font-medium text-violet-600 dark:text-violet-400 hover:underline">
                                                        <flux:icon.document-arrow-down class="w-3 h-3" />
                                                        Ver factura PDF
                                                    </a>
                                                @endif
                                            </div>
                                        @endif

                                        @if($paso['key'] === 'recibido')
                                            <div class="mt-1.5 p-2 rounded-md border text-[10px]
                                                {{ $p->recibido_ok
                                                    ? 'bg-cyan-50 dark:bg-cyan-900/20 border-cyan-200 dark:border-cyan-800 text-cyan-700 dark:text-cyan-400'
                                                    : 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400' }}">
                                                <p class="font-semibold">{{ $p->recibido_ok ? '✓ Todo recibido OK' : '⚠ Recibido con observaciones' }}</p>
                                                @if($p->observacion)
                                                    <p class="mt-0.5 text-zinc-500 dark:text-zinc-400">{{ $p->observacion }}</p>
                                                @endif
                                            </div>
                                        @endif

                                        @if($paso['key'] === 'terminado' && $p->imov)
                                            <div class="mt-1.5 p-2 rounded-md border {{ $c['soft'] }} text-[10px] text-green-700 dark:text-green-400">
                                                <span class="font-semibold">IMOV:</span> {{ $p->imov }}
                                            </div>
                                        @endif

                                        @if($paso['key'] === 'rechazado')
                                            <div class="mt-1.5 p-2 rounded-md border {{ $c['soft'] }} text-[10px] text-red-700 dark:text-red-400">
                                                <p class="font-semibold">Pedido rechazado</p>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        </div>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="flex flex-wrap gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    @if(!$usuarioTiendaId)
                        @if($p->estado === 'por_aprobar')
                            <flux:button size="sm" variant="primary" wire:click="cambiarEstado({{ $p->id }},'aprobado')" icon="check">Aprobar</flux:button>
                            <flux:button size="sm" variant="danger"  wire:click="cambiarEstado({{ $p->id }},'rechazado')" icon="x-mark">Rechazar</flux:button>
                        @elseif($p->estado === 'aprobado')
                            <flux:button size="sm" variant="primary" wire:click="abrirEnviado({{ $p->id }})" icon="truck">Marcar enviado</flux:button>
                        @elseif($p->estado === 'recibido')
                            <flux:button size="sm" variant="primary" wire:click="abrirTerminado({{ $p->id }})" icon="check-badge">Marcar terminado</flux:button>
                        @endif
                        <a href="{{ route('abastos.pedidos.editar', $p->id) }}" wire:navigate>
                            <flux:button size="sm" variant="ghost" icon="pencil">Editar</flux:button>
                        </a>
                    @else
                        @if($p->estado === 'enviado')
                            <flux:button size="sm" variant="primary" wire:click="abrirRecibidoTienda({{ $p->id }})" icon="check-circle">Marcar recibido</flux:button>
                        @endif
                    @endif
                    <a href="{{ route('abastos.pedidos.imprimir', $p->id) }}" target="_blank">
                        <flux:button size="sm" variant="ghost" icon="printer">Imprimir</flux:button>
                    </a>
                </div>

            </div>
            @endif
        @endif
    </flux:modal>

    @if($pedidos->hasPages())
        <div class="mt-2">{{ $pedidos->links() }}</div>
    @endif

</div>
