<?php

use App\Events\ComandaActualizada;
use App\Events\MesaActualizada;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Mesa;
use App\Models\ProductoMenu;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tomar pedido · Comandas')] class extends Component
{
    public Mesa $mesa;
    public ?int $comandaId = null;
    public string $busqueda = '';
    public string $categoriaActiva = '';
    /** @var array<int, int> */
    public array $cantidades = [];
    /** @var array<int, string> */
    public array $observaciones = [];

    public function mount(Mesa $mesa): void
    {
        $this->mesa = $mesa;

        $comanda = Comanda::where('mesa_id', $mesa->id)
            ->whereIn('estado', ['abierta', 'en_cuenta'])
            ->with('items')
            ->first();

        if ($comanda) {
            $this->comandaId = $comanda->id;
            foreach ($comanda->items as $item) {
                $this->cantidades[$item->producto_menu_id] = $item->cantidad;
                if ($item->observacion) {
                    $this->observaciones[$item->producto_menu_id] = $item->observacion;
                }
            }
        } else {
            Flux::modal('cliente-info')->show();
        }
    }

    #[Renderless]
    public function abrirModalCliente(): void
    {
        Flux::modal('cliente-info')->show();
    }

    private function sincronizarComanda(
        string $estado,
        array $cantidades,
        array $observaciones,
        array $cliente,
        string $jfac = ''
    ): Comanda {
        $comanda = $this->comandaId ? Comanda::find($this->comandaId) : null;

        $datosCliente = [
            'cliente_nombre'   => trim($cliente['nombre']   ?? '') ?: null,
            'cliente_cc'       => trim($cliente['cc']       ?? '') ?: null,
            'cliente_telefono' => trim($cliente['telefono'] ?? '') ?: null,
            'cliente_correo'   => trim($cliente['correo']   ?? '') ?: null,
        ];

        if (!$comanda) {
            $comanda = Comanda::create(array_merge([
                'folio'     => Comanda::generarFolio(),
                'mesa_id'   => $this->mesa->id,
                'tienda_id' => $this->mesa->tienda_id,
                'mesero_id' => auth()->id(),
                'estado'    => $estado,
                'jfac'      => $jfac ?: null,
            ], $datosCliente));
            $this->comandaId = $comanda->id;
        } else {
            $comanda->update(array_merge(
                ['estado' => $estado, 'jfac' => $jfac ?: null],
                $datosCliente
            ));
        }

        $mesaEstado = match($estado) {
            'abierta'   => 'ocupada',
            'en_cuenta' => 'en_cuenta',
            'cerrada'   => 'libre',
            default     => $this->mesa->estado,
        };
        $this->mesa->update(['estado' => $mesaEstado]);

        $comanda->items()->delete();
        $seleccionados = collect($cantidades)->map(fn($q) => (int)$q)->filter(fn($q) => $q > 0);
        $productos = ProductoMenu::whereIn('id', $seleccionados->keys())->get()->keyBy('id');

        $rows = [];
        $now  = now();
        foreach ($seleccionados as $prodId => $qty) {
            $prod = $productos->get($prodId);
            if (!$prod) continue;
            $rows[] = [
                'comanda_id'       => $comanda->id,
                'producto_menu_id' => $prod->id,
                'cantidad'         => $qty,
                'precio_unitario'  => (float) $prod->precio,
                'observacion'      => $observaciones[$prodId] ?? null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        if (!empty($rows)) {
            ComandaItem::insert($rows);
        }

        $tiendaId = $this->mesa->tienda_id;
        try { broadcast(new ComandaActualizada($tiendaId)); } catch (\Throwable $e) {}
        try { broadcast(new MesaActualizada($tiendaId)); } catch (\Throwable $e) {}

        return $comanda;
    }

    public function guardar(array $cantidades, array $observaciones, array $cliente): void
    {
        if (empty(trim($cliente['nombre'] ?? '')) || empty(trim($cliente['cc'] ?? '')) || empty(trim($cliente['telefono'] ?? ''))) {
            Flux::toast(variant: 'warning', text: 'Nombre, CC y teléfono del cliente son obligatorios.');
            return;
        }
        $seleccionados = collect($cantidades)->filter(fn($q) => (int)$q > 0);
        if ($seleccionados->isEmpty() && !$this->comandaId) {
            Flux::toast(variant: 'warning', text: 'Agrega al menos un producto.');
            return;
        }
        $this->cantidades    = $cantidades;
        $this->observaciones = $observaciones;
        $comanda = $this->sincronizarComanda('abierta', $cantidades, $observaciones, $cliente);
        $this->redirect(route('comandas.imprimir', $comanda));
    }

    public function pedirCuenta(array $cantidades, array $observaciones, array $cliente): string
    {
        $seleccionados = collect($cantidades)->filter(fn($q) => (int)$q > 0);
        if ($seleccionados->isEmpty() && !$this->comandaId) {
            return 'error:La comanda está vacía.';
        }
        $this->cantidades    = $cantidades;
        $this->observaciones = $observaciones;
        $this->sincronizarComanda('en_cuenta', $cantidades, $observaciones, $cliente);
        return 'ok:Mesa marcada en cuenta.';
    }

    public function cerrarCuenta(array $cantidades, array $observaciones, array $cliente, string $jfac): void
    {
        $jfac = trim($jfac);
        if (empty($jfac)) {
            Flux::toast(variant: 'warning', text: 'El número de factura JFAC es obligatorio.');
            return;
        }
        $this->cantidades    = $cantidades;
        $this->observaciones = $observaciones;
        $comanda = $this->sincronizarComanda('cerrada', $cantidades, $observaciones, $cliente, $jfac);
        Flux::toast(variant: 'success', text: "Cuenta cerrada · {$comanda->folio} · JFAC {$jfac}");
        $this->redirectRoute('comandas.mesas', navigate: true);
    }
}; ?>

@php
    $mesa->loadMissing('tienda');
    $tiendaId = $mesa->tienda_id;

    $productos = ProductoMenu::where('tienda_id', $tiendaId)
        ->where('activo', true)
        ->when($busqueda, fn($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'))
        ->when($categoriaActiva, fn($q) => $q->where('categoria', $categoriaActiva))
        ->orderBy('categoria')->orderBy('nombre')
        ->get();

    $agrupados  = $productos->groupBy('categoria');
    $categorias = $agrupados->keys();

    $comanda = $comandaId ? \App\Models\Comanda::find($comandaId) : null;

    $productosAlpine = $productos->map(fn($p) => [
        'id'     => $p->id,
        'nombre' => $p->nombre,
        'precio' => (float) $p->precio,
    ])->values();
@endphp

<div class="max-w-5xl mx-auto"
    x-data="{
        cantidades: @js((object) $cantidades),
        observaciones: @js((object) $observaciones),
        productos: @js($productosAlpine),

        clienteNombre:   @js($comanda?->cliente_nombre   ?? ''),
        clienteCc:       @js($comanda?->cliente_cc       ?? ''),
        clienteTelefono: @js($comanda?->cliente_telefono ?? ''),
        clienteCorreo:   @js($comanda?->cliente_correo   ?? ''),
        clienteError:    '',
        jfac:            '',
        jfacError:       '',

        get clienteOk() {
            return this.clienteNombre.trim() !== '' &&
                   this.clienteCc.trim() !== '' &&
                   this.clienteTelefono.trim() !== '';
        },

        get cliente() {
            return {
                nombre:   this.clienteNombre,
                cc:       this.clienteCc,
                telefono: this.clienteTelefono,
                correo:   this.clienteCorreo,
            };
        },

        confirmarCliente() {
            if (!this.clienteOk) {
                this.clienteError = 'Nombre, CC y teléfono son obligatorios.';
                return;
            }
            this.clienteError = '';
            $flux.modal('cliente-info').close();
        },

        get resumen() {
            let items = [];
            let total = 0;
            for (const prod of this.productos) {
                const qty = parseInt(this.cantidades[prod.id] ?? 0);
                if (qty > 0) {
                    const sub = qty * prod.precio;
                    total += sub;
                    items.push({ nombre: prod.nombre, qty, sub });
                }
            }
            return { items, total };
        },

        qty(id) { return parseInt(this.cantidades[id] ?? 0); },
        incrementar(id) { this.cantidades[id] = this.qty(id) + 1; },
        decrementar(id) {
            const actual = this.qty(id);
            if (actual <= 1) { delete this.cantidades[id]; delete this.observaciones[id]; }
            else { this.cantidades[id] = actual - 1; }
        },
        fmt(n) { return '$' + Math.round(n).toLocaleString('es-CO'); },
    }">

    {{-- Modal: Datos del cliente --}}
    <flux:modal name="cliente-info" class="w-full max-w-sm space-y-4">

        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center shrink-0">
                <flux:icon.user class="w-5 h-5 text-[#E8642E]" />
            </div>
            <div>
                <flux:heading size="lg">Datos del cliente</flux:heading>
                <flux:subheading>Mesa {{ $mesa->numero }}{{ $mesa->nombre ? ' · '.$mesa->nombre : '' }}</flux:subheading>
            </div>
        </div>

        <div x-show="clienteError"
             x-text="clienteError"
             class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-3 py-2 rounded-lg"></div>

        <flux:input x-model="clienteNombre" label="Nombre completo" placeholder="Ej. Juan Pérez García" required />
        <flux:input x-model="clienteCc" label="Cédula / CC" placeholder="Ej. 1234567890" required />
        <flux:input x-model="clienteTelefono" type="tel" label="Teléfono" placeholder="Ej. 3001234567" required />
        <flux:input x-model="clienteCorreo" type="email" label="Correo electrónico" placeholder="cliente@ejemplo.com" description="Opcional" />

        <div class="flex items-center justify-between pt-1">
            <a href="{{ route('comandas.mesas') }}" wire:navigate
               class="text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors">
                ← Cancelar
            </a>
            <flux:button variant="primary" icon="check" @click="confirmarCliente()">
                Continuar
            </flux:button>
        </div>

    </flux:modal>

    {{-- ============================================================
         HEADER
    ============================================================ --}}
    <div class="flex items-start gap-3 mb-4">
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('comandas.mesas')" wire:navigate />

        @php
            $circleColor = match($mesa->estado) {
                'libre'     => 'bg-emerald-500',
                'ocupada'   => 'bg-amber-500',
                'en_cuenta' => 'bg-red-500',
                default     => 'bg-zinc-400',
            };
        @endphp

        <div class="flex items-start gap-2 min-w-0 flex-1">
            <div class="w-9 h-9 rounded-full {{ $circleColor }} flex items-center justify-center text-white font-bold text-sm shrink-0">
                {{ $mesa->numero }}
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-zinc-900 dark:text-white leading-tight">
                    Mesa {{ $mesa->numero }}{{ $mesa->nombre ? ' · '.$mesa->nombre : '' }}
                </p>
                <p class="text-[10px] text-zinc-400">{{ $mesa->tienda->nombre }}</p>

                {{-- Cliente info strip --}}
                <div x-show="clienteOk" class="mt-1.5 flex items-center gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5 text-[10px] text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2.5 py-0.5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span x-text="clienteNombre" class="font-medium max-w-32 truncate"></span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[10px] text-zinc-500 dark:text-zinc-500 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2.5 py-0.5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                        <span x-text="clienteCc"></span>
                    </div>
                    <button wire:click="abrirModalCliente"
                            class="text-[10px] text-[#E8642E] hover:underline">
                        Editar
                    </button>
                </div>
            </div>
        </div>

        @if($comanda)
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs font-mono text-zinc-400">{{ $comanda->folio }}</span>
                @php
                    $badgeClass = match($comanda->estado) {
                        'abierta'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                        'en_cuenta' => 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
                        default     => 'bg-zinc-100 text-zinc-500',
                    };
                @endphp
                <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $badgeClass }}">
                    {{ match($comanda->estado) { 'abierta' => 'Abierta', 'en_cuenta' => 'En cuenta', default => $comanda->estado } }}
                </span>
            </div>
        @endif
    </div>

    <div class="flex flex-col md:flex-row gap-4">

        {{-- ============================================================
             COLUMNA: Productos
        ============================================================ --}}
        <div class="flex-1 space-y-3 min-w-0">

            {{-- Búsqueda y categorías --}}
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-3 space-y-2">
                <flux:input wire:model.live.debounce.300ms="busqueda" placeholder="Buscar en la carta..." icon="magnifying-glass" size="sm" />
                @if($categorias->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        <button wire:click="$set('categoriaActiva', '')"
                                class="px-2.5 py-1 rounded-full text-[10px] font-medium transition-colors
                                       {{ !$categoriaActiva ? 'bg-[#E8642E] text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}">
                            Todos
                        </button>
                        @foreach($categorias as $cat)
                            <button wire:click="$set('categoriaActiva', '{{ $cat }}')"
                                    class="px-2.5 py-1 rounded-full text-[10px] font-medium transition-colors
                                           {{ $categoriaActiva === $cat ? 'bg-[#E8642E] text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}">
                                {{ $cat }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Lista de productos --}}
            <div class="space-y-3">
                @forelse($agrupados as $categoria => $prods)
                    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                        <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $categoria }}</span>
                        </div>
                        <table class="w-full">
                            <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                                @foreach($prods as $prod)
                                    <tr :class="qty({{ $prod->id }}) > 0 ? 'bg-orange-50/40 dark:bg-orange-900/5' : ''"
                                        class="transition-colors">
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                @if($prod->codigo)
                                                    <span class="font-mono text-[10px] text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded shrink-0">{{ $prod->codigo }}</span>
                                                @endif
                                                <p class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $prod->nombre }}</p>
                                            </div>
                                            @if($prod->descripcion)
                                                <p class="text-[10px] text-zinc-400">{{ $prod->descripcion }}</p>
                                            @endif
                                            <p class="text-[10px] text-zinc-400 mt-0.5">${{ number_format($prod->precio, 0, '.', ',') }}</p>
                                            <div x-show="qty({{ $prod->id }}) > 0" x-cloak class="mt-1.5">
                                                <input
                                                    x-model="observaciones[{{ $prod->id }}]"
                                                    placeholder="Observación (sin azúcar, extra...)"
                                                    class="w-full text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-[#E8642E]"
                                                />
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 w-28 shrink-0">
                                            <div class="flex items-center gap-1.5">
                                                <button @click="decrementar({{ $prod->id }})"
                                                        :disabled="qty({{ $prod->id }}) === 0"
                                                        class="w-7 h-7 rounded-full border border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors disabled:opacity-30">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                                </button>
                                                <span x-text="qty({{ $prod->id }}) || ''" class="w-5 text-center text-xs font-semibold text-zinc-800 dark:text-zinc-200"></span>
                                                <button @click="incrementar({{ $prod->id }})"
                                                        class="w-7 h-7 rounded-full border border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 text-right w-20 shrink-0">
                                            <span x-show="qty({{ $prod->id }}) > 0"
                                                  x-text="fmt(qty({{ $prod->id }}) * {{ (float)$prod->precio }})"
                                                  class="text-xs font-semibold text-[#E8642E]"></span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <flux:icon.magnifying-glass class="w-6 h-6 text-zinc-300 mb-2" />
                        <p class="text-sm text-zinc-400">
                            {{ $busqueda ? 'Sin resultados para "'.$busqueda.'"' : 'No hay productos en la carta para esta tienda.' }}
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ============================================================
             PANEL RESUMEN (sticky)
        ============================================================ --}}
        <div class="md:w-60 lg:w-64 shrink-0">
            <div class="sticky top-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4 space-y-3">
                <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Pedido</p>

                <template x-if="resumen.items.length === 0">
                    <p class="text-xs text-zinc-400 text-center py-6">Sin productos seleccionados</p>
                </template>

                <template x-if="resumen.items.length > 0">
                    <div>
                        <div class="space-y-1.5">
                            <template x-for="line in resumen.items" :key="line.nombre">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-1.5 min-w-0">
                                        <span x-text="line.qty + '×'" class="text-[10px] font-semibold text-zinc-400 shrink-0 mt-px"></span>
                                        <span x-text="line.nombre" class="text-[10px] text-zinc-700 dark:text-zinc-300 leading-tight"></span>
                                    </div>
                                    <span x-text="fmt(line.sub)" class="text-[10px] font-medium text-zinc-700 dark:text-zinc-300 shrink-0"></span>
                                </div>
                            </template>
                        </div>
                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-2.5 mt-2 flex items-center justify-between">
                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Total</span>
                            <span x-text="fmt(resumen.total)" class="text-sm font-bold text-zinc-900 dark:text-white"></span>
                        </div>
                    </div>
                </template>

                <div class="space-y-1.5 pt-1">
                    <button @click="async () => { if (!clienteOk) { $wire.abrirModalCliente(); return; } await $wire.guardar(cantidades, observaciones, cliente); }"
                            class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#E8642E] text-white hover:bg-[#d4561f] transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Guardar
                    </button>
                    @if($comandaId)
                        <button @click="async () => {
                                    if (!clienteOk) { $wire.abrirModalCliente(); return; }
                                    const res = await $wire.pedirCuenta(cantidades, observaciones, cliente);
                                    const [tipo, msg] = res.split(':');
                                    $dispatch('toast-show', { duration: 4000, slots: { text: msg }, dataset: { variant: tipo === 'ok' ? 'success' : 'warning' } });
                                }"
                                class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                            Pedir cuenta
                        </button>
                        <button @click="if (!clienteOk) { $wire.abrirModalCliente(); return; } jfac = ''; jfacError = ''; $flux.modal('modal-cerrar-mesa').show()"
                                class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-500 text-white hover:bg-red-600 transition-colors">
                            Cerrar mesa
                        </button>
                        <a href="{{ route('comandas.imprimir', $comanda) }}" target="_blank"
                           class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Imprimir comanda
                        </a>
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- Modal: Cerrar mesa (requiere JFAC) --}}
    <flux:modal name="modal-cerrar-mesa" class="w-full max-w-sm space-y-4">

        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center shrink-0">
                <flux:icon.document-text class="w-5 h-5 text-red-500" />
            </div>
            <div>
                <flux:heading size="lg">Cerrar mesa</flux:heading>
                <flux:subheading>Mesa {{ $mesa->numero }}{{ $mesa->nombre ? ' · '.$mesa->nombre : '' }}</flux:subheading>
            </div>
        </div>

        {{-- Resumen del total --}}
        <div class="bg-zinc-50 dark:bg-zinc-800 rounded-xl px-4 py-3 flex items-center justify-between">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">Total a cobrar</span>
            <span x-text="fmt(resumen.total)" class="text-sm font-bold text-zinc-900 dark:text-white"></span>
        </div>

        {{-- Campo JFAC --}}
        <div>
            <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                Número de factura <span class="text-red-500">*</span>
            </label>
            <input
                x-model="jfac"
                @input="jfacError = ''"
                @keydown.enter="if (!jfac.trim()) { jfacError = 'El número de factura es obligatorio.'; return; } $wire.cerrarCuenta(cantidades, observaciones, cliente, jfac); $flux.modal('modal-cerrar-mesa').close()"
                placeholder="Ej. JFAC-001234"
                class="w-full text-sm border rounded-lg px-3 py-2 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder-zinc-400
                       focus:outline-none focus:ring-2 focus:ring-red-400 transition-colors"
                :class="jfacError ? 'border-red-400 dark:border-red-500' : 'border-zinc-300 dark:border-zinc-600'"
            />
            <p x-show="jfacError" x-text="jfacError" class="text-xs text-red-500 mt-1"></p>
        </div>

        <div class="flex items-center justify-between pt-1">
            <flux:button variant="ghost" size="sm" x-on:click="$flux.modal('modal-cerrar-mesa').close()">
                Cancelar
            </flux:button>
            <button
                @click="if (!jfac.trim()) { jfacError = 'El número de factura es obligatorio.'; return; } $wire.cerrarCuenta(cantidades, observaciones, cliente, jfac); $flux.modal('modal-cerrar-mesa').close()"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-red-500 text-white hover:bg-red-600 transition-colors">
                <flux:icon.check class="w-4 h-4" />
                Confirmar y liberar mesa
            </button>
        </div>

    </flux:modal>

</div>
