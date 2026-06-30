<?php

use App\Events\TrasladoActualizado;
use App\Models\MateriaPrima;
use App\Models\Tienda;
use App\Models\Traslado;
use App\Models\TrasladoItem;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nuevo traslado · Traslados')] class extends Component
{
    public int $paso = 1;
    public ?int $origenId  = null;
    public ?int $destinoId = null;
    public string $busqueda = '';
    /** @var array<int, int> */
    public array $cantidades = [];

    public function mount(): void
    {
        if (auth()->user()->tienda_id) {
            $this->origenId = auth()->user()->tienda_id;
            $this->paso = 2;
        }
    }

    public function seleccionarOrigen(int $id): void
    {
        $this->origenId  = $id;
        $this->destinoId = null;
        $this->paso = 2;
    }

    public function seleccionarDestino(int $id): void
    {
        $this->destinoId  = $id;
        $this->cantidades = [];
        $this->paso = 3;
    }

    public function volverPaso(int $paso): void
    {
        if ($paso === 1 && auth()->user()->tienda_id) {
            $this->redirectRoute('traslados.index', navigate: true);
            return;
        }
        $this->paso = $paso;
        if ($paso === 1) { $this->origenId = null; $this->destinoId = null; }
        if ($paso === 2) { $this->destinoId = null; $this->cantidades = []; }
        $this->busqueda = '';
    }

    public function submit(array $cantidades, string $notas = ''): void
    {
        $seleccionados = collect($cantidades)
            ->map(fn($q) => (int)$q)
            ->filter(fn($q) => $q > 0);

        if ($seleccionados->isEmpty()) {
            Flux::toast(variant: 'warning', text: 'Agrega al menos un producto.');
            return;
        }

        $traslado = Traslado::create([
            'folio'             => Traslado::generarFolio(),
            'tienda_origen_id'  => $this->origenId,
            'tienda_destino_id' => $this->destinoId,
            'solicitante_id'    => auth()->id(),
            'estado'            => 'pendiente',
            'notas'             => $notas ?: null,
        ]);

        foreach ($seleccionados as $matId => $qty) {
            TrasladoItem::create([
                'traslado_id'     => $traslado->id,
                'materia_prima_id'=> $matId,
                'cantidad'        => $qty,
            ]);
        }

        try { broadcast(new TrasladoActualizado()); } catch (\Throwable $e) {}
        Flux::toast(variant: 'success', text: "Traslado {$traslado->folio} creado.");
        $this->redirectRoute('traslados.ver', $traslado, navigate: true);
    }
}; ?>

@php
    $origen  = $origenId  ? Tienda::find($origenId)  : null;
    $destino = $destinoId ? Tienda::find($destinoId) : null;

    $tiendas = Tienda::orderBy('nombre')->get();

    $materiales = MateriaPrima::with('proveedor')
        ->where('activo', true)
        ->when($busqueda, fn($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'))
        ->orderBy('nombre')
        ->get();
    $agrupados = $materiales->groupBy(fn($m) => $m->proveedor?->nombre ?? 'Sin proveedor');
@endphp

<div class="max-w-2xl mx-auto space-y-4">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('traslados.index')" wire:navigate />
        <div>
            <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Nuevo traslado</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Mover productos entre tiendas</p>
        </div>
    </div>

    {{-- Stepper --}}
    @php $pasos = [1 => 'Origen', 2 => 'Destino', 3 => 'Productos']; @endphp
    <div class="flex items-center">
        @foreach($pasos as $num => $label)
            <div class="flex items-center {{ $num < 3 ? 'flex-1' : '' }}">
                <div class="flex items-center gap-1.5 shrink-0">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold transition-colors
                        {{ $paso > $num ? 'bg-[#E8642E] text-white' : ($paso === $num ? 'bg-[#E8642E] text-white ring-4 ring-orange-100 dark:ring-orange-900/30' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-500') }}">
                        @if($paso > $num)<flux:icon.check class="w-3 h-3" />@else{{ $num }}@endif
                    </div>
                    <span class="text-xs font-medium {{ $paso >= $num ? 'text-zinc-800 dark:text-zinc-200' : 'text-zinc-400' }}">{{ $label }}</span>
                </div>
                @if($num < 3)
                    <div class="flex-1 mx-2 h-px {{ $paso > $num ? 'bg-[#E8642E]' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Paso 1: Origen --}}
    @if($paso === 1)
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4">
            <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 mb-3">¿Desde qué tienda sale el traslado?</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach($tiendas as $t)
                    <button wire:click="seleccionarOrigen({{ $t->id }})"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-zinc-200 dark:border-zinc-700
                                   hover:border-[#E8642E] dark:hover:border-[#E8642E] hover:bg-orange-50/50 dark:hover:bg-zinc-800 transition-all">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white text-sm"
                             style="background-color:{{ $t->color }}">{{ $t->codigo }}</div>
                        <div class="text-center">
                            <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 leading-tight">{{ $t->nombre }}</p>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Paso 2: Destino --}}
    @if($paso === 2)
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4">
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                     style="background-color:{{ $origen->color }}">{{ $origen->codigo }}</div>
                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $origen->nombre }}</span>
                <flux:icon.arrow-right class="w-4 h-4 text-zinc-400 mx-1" />
                <span class="text-xs text-zinc-400">¿A qué tienda?</span>
            </div>
            <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 mb-3">¿A qué tienda llega el traslado?</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach($tiendas->where('id', '!=', $origenId) as $t)
                    <button wire:click="seleccionarDestino({{ $t->id }})"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-zinc-200 dark:border-zinc-700
                                   hover:border-[#E8642E] dark:hover:border-[#E8642E] hover:bg-orange-50/50 dark:hover:bg-zinc-800 transition-all">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white text-sm"
                             style="background-color:{{ $t->color }}">{{ $t->codigo }}</div>
                        <div class="text-center">
                            <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 leading-tight">{{ $t->nombre }}</p>
                        </div>
                    </button>
                @endforeach
            </div>
            <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button size="sm" variant="ghost" wire:click="volverPaso(1)" icon="arrow-left">Volver</flux:button>
            </div>
        </div>
    @endif

    {{-- Paso 3: Productos --}}
    @if($paso === 3)
    <div x-data="{
        cantidades: @js((object) $cantidades),
        notas: '',

        get resumen() {
            let count = 0;
            for (const [k, v] of Object.entries(this.cantidades)) {
                if (parseInt(v) > 0) count++;
            }
            return count;
        },
    }">

        {{-- Ruta resumen --}}
        <div class="flex items-center gap-2 p-3 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0"
                 style="background-color:{{ $origen->color }}">{{ $origen->codigo }}</div>
            <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $origen->nombre }}</span>
            <flux:icon.arrow-right class="w-4 h-4 text-zinc-400" />
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0"
                 style="background-color:{{ $destino->color }}">{{ $destino->codigo }}</div>
            <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $destino->nombre }}</span>
            <div class="flex-1"></div>
            <flux:input wire:model.live.debounce.300ms="busqueda" placeholder="Buscar..." icon="magnifying-glass" size="sm" class="w-44" />
        </div>

        {{-- Lista de materias primas --}}
        <div class="space-y-3 mt-0">
            @forelse($agrupados as $provNombre => $mats)
                <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                    <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $provNombre }}</span>
                    </div>
                    <table class="w-full">
                        <tbody class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                            @foreach($mats as $mat)
                                <tr :class="parseInt(cantidades[{{ $mat->id }}] ?? 0) > 0 ? 'bg-orange-50/40 dark:bg-orange-900/5' : ''"
                                    class="transition-colors">
                                    <td class="px-4 py-2.5">
                                        <p class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                            {{ $mat->nombre }}
                                            @if($mat->codigo_producto)
                                                <span class="font-mono font-normal text-zinc-400 ml-1">{{ $mat->codigo_producto }}</span>
                                            @endif
                                        </p>
                                        <p class="text-[10px] text-zinc-400">{{ $mat->unidad }}</p>
                                    </td>
                                    <td class="px-3 py-2.5 w-24">
                                        <input type="number" min="0"
                                               x-model="cantidades[{{ $mat->id }}]"
                                               placeholder="0"
                                               class="w-20 text-center text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg px-2 py-1.5 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-[#E8642E]" />
                                    </td>
                                    <td class="px-4 py-2.5 text-right w-20">
                                        <span x-show="parseInt(cantidades[{{ $mat->id }}] ?? 0) > 0"
                                              x-text="cantidades[{{ $mat->id }}] + ' {{ $mat->unidad }}'"
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
                    <p class="text-sm text-zinc-400">Sin resultados para "{{ $busqueda }}"</p>
                </div>
            @endforelse
        </div>

        {{-- Notas --}}
        <div class="mt-3">
            <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Notas (opcional)</label>
            <textarea x-model="notas" rows="2" placeholder="Indicaciones, urgencia..."
                      class="w-full text-xs border border-zinc-200 dark:border-zinc-700 rounded-lg px-3 py-2 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-[#E8642E] resize-none"></textarea>
        </div>

        {{-- Footer --}}
        <div class="flex items-center gap-3 mt-3">
            <flux:button size="sm" variant="ghost" wire:click="volverPaso(2)" icon="arrow-left">Volver</flux:button>
            <div class="flex-1"></div>
            <template x-if="resumen > 0">
                <p class="text-xs text-zinc-500" x-text="resumen + (resumen !== 1 ? ' productos seleccionados' : ' producto seleccionado')"></p>
            </template>
            <button @click="$wire.submit(cantidades, notas)"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#E8642E] text-white hover:bg-[#d4561f] transition-colors">
                <flux:icon.paper-airplane class="w-3.5 h-3.5" />
                Crear traslado
            </button>
        </div>

    </div>
    @endif

</div>
