<?php

use App\Models\Comanda;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Estación de Impresión · Comandas')] class extends Component
{
    public function buscarComanda(string $folio): ?array
    {
        $c = Comanda::with(['mesa', 'tienda', 'items.productoMenu', 'mesero'])
            ->where('folio', strtoupper(trim($folio)))
            ->first();

        if (!$c) return null;

        return [
            'folio'    => $c->folio,
            'mesa'     => $c->mesa ? ($c->mesa->nombre ?: "Mesa {$c->mesa->numero}") : '—',
            'tienda'   => $c->tienda?->nombre ?? '—',
            'mesero'   => $c->mesero?->name ?? '—',
            'cliente'  => $c->cliente_nombre,
            'cc'       => $c->cliente_cc,
            'telefono' => $c->cliente_telefono,
            'items'    => $c->items->map(fn($i) => [
                'nombre'      => $i->productoMenu?->nombre ?? '(eliminado)',
                'cantidad'    => $i->cantidad,
                'precio'      => (float) $i->precio_unitario,
                'subtotal'    => $i->subtotal(),
                'observacion' => $i->observacion,
            ])->toArray(),
            'fecha'    => $c->created_at->format('d/m/Y H:i'),
        ];
    }
}; ?>



<div class="max-w-lg mx-auto space-y-4" x-data="estacionImpresion">

    {{-- Header --}}
    <div>
        <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Estación de Impresión</h1>
        <p class="text-xs text-zinc-400 mt-0.5">Mantén esta página abierta en el PC conectado a la impresora</p>
    </div>

    {{-- Estado QZ Tray --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4 space-y-3">

        <div class="flex items-center justify-between">
            <p class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wide">QZ Tray</p>
            <button @click="conectar()"
                    x-show="estado !== 'conectado'"
                    class="text-xs px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800
                           text-zinc-600 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                Reconectar
            </button>
        </div>

        {{-- Badge de estado --}}
        <div class="flex items-center gap-2">
            <template x-if="estado === 'conectado'">
                <span class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Conectado
                </span>
            </template>
            <template x-if="estado === 'conectando'">
                <span class="flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    Conectando...
                </span>
            </template>
            <template x-if="estado === 'error'">
                <div class="space-y-1">
                    <span class="flex items-center gap-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        Error — QZ Tray no responde
                    </span>
                    <p class="text-[10px] text-zinc-400 ml-3.5">Verifica que QZ Tray esté instalado y corriendo en este PC.</p>
                    <template x-if="errorMsg">
                        <p class="text-[10px] font-mono text-red-400 ml-3.5 break-all" x-text="errorMsg"></p>
                    </template>
                </div>
            </template>
            <template x-if="estado === 'sin-app'">
                <div class="space-y-1">
                    <span class="flex items-center gap-1.5 text-xs font-medium text-zinc-500">
                        <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                        Librería no encontrada
                    </span>
                    <p class="text-[10px] text-zinc-400 ml-3.5">Descarga qz-tray.js y colócalo en <span class="font-mono">public/js/qz-tray.js</span></p>
                </div>
            </template>
            <template x-if="estado === 'desconectado'">
                <span class="flex items-center gap-1.5 text-xs font-medium text-zinc-400">
                    <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                    Desconectado
                </span>
            </template>
        </div>

        {{-- Selector de impresora --}}
        <template x-if="estado === 'conectado' && impresoras.length > 0">
            <div class="space-y-1.5 pt-1 border-t border-zinc-100 dark:border-zinc-800">
                <label class="text-[10px] font-medium text-zinc-400 uppercase tracking-wide">Impresora POS</label>
                <select x-model="impresora" @change="guardarImpresora()"
                        class="w-full text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg px-3 py-1.5
                               bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200
                               focus:outline-none focus:ring-2 focus:ring-[#E8642E]">
                    <option value="">— Selecciona impresora —</option>
                    <template x-for="p in impresoras" :key="p">
                        <option :value="p" x-text="p"></option>
                    </template>
                </select>
                <template x-if="impresora">
                    <p class="text-[10px] text-emerald-600 dark:text-emerald-400">
                        Guardada. Las comandas se imprimirán aquí automáticamente.
                    </p>
                </template>
            </div>
        </template>

        {{-- Toggle auto-imprimir --}}
        <template x-if="estado === 'conectado'">
            <div class="flex items-center justify-between pt-1 border-t border-zinc-100 dark:border-zinc-800">
                <div>
                    <span class="text-xs text-zinc-700 dark:text-zinc-300 font-medium">Auto-imprimir</span>
                    <p class="text-[10px] text-zinc-400">Imprime cada comanda al guardarse</p>
                </div>
                <button @click="autoImprimir = !autoImprimir"
                        :class="autoImprimir ? 'bg-[#E8642E]' : 'bg-zinc-300 dark:bg-zinc-600'"
                        class="relative w-10 h-6 rounded-full transition-colors focus:outline-none shrink-0">
                    <span :class="autoImprimir ? 'translate-x-5' : 'translate-x-1'"
                          class="absolute top-1 left-0 w-4 h-4 bg-white rounded-full shadow transition-transform"></span>
                </button>
            </div>
        </template>

    </div>

    {{-- Reimprimir manual --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4 space-y-2">
        <p class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wide">Reimprimir comanda</p>
        <div class="flex items-center gap-2">
            <input x-model="folioManual"
                   @keydown.enter="imprimirManual()"
                   placeholder="CMD-1001"
                   class="flex-1 font-mono text-sm border border-zinc-300 dark:border-zinc-600 rounded-lg px-3 py-1.5
                          bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 placeholder-zinc-400
                          focus:outline-none focus:ring-2 focus:ring-[#E8642E]"
                   style="text-transform:uppercase" />
            <button @click="imprimirManual()"
                    :disabled="buscando || !folioManual.trim() || estado !== 'conectado'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                           bg-[#E8642E] text-white hover:bg-[#d4561f] disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                <span x-text="buscando ? '...' : 'Imprimir'"></span>
            </button>
        </div>
    </div>

    {{-- Registro de impresiones --}}
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <div class="px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Registro</span>
                <span x-show="cola.length > 0"
                      class="text-[10px] px-1.5 py-0.5 rounded-full bg-zinc-200 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400"
                      x-text="cola.length"></span>
            </div>
            <button x-show="cola.length > 0" @click="cola = []"
                    class="text-[10px] text-zinc-400 hover:text-red-500 transition-colors">
                Limpiar
            </button>
        </div>

        <template x-if="cola.length === 0">
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <p class="text-xs text-zinc-400">Sin impresiones aún.</p>
                <p class="text-[10px] text-zinc-300 dark:text-zinc-600 mt-1">Las comandas aparecerán aquí al guardarse desde cualquier dispositivo.</p>
            </div>
        </template>

        <template x-if="cola.length > 0">
            <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60 max-h-80 overflow-y-auto">
                <template x-for="(e, i) in cola" :key="i">
                    <div class="flex items-start gap-3 px-4 py-2.5">
                        <span :class="{
                                'bg-emerald-500': e.ok === true,
                                'bg-red-500':     e.ok === false,
                                'bg-amber-400':   e.ok === null,
                              }"
                              class="w-2 h-2 rounded-full shrink-0 mt-1.5"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300"
                                      x-text="e.folio"></span>
                                <span class="text-[10px] text-zinc-400" x-text="e.mesa"></span>
                                <span x-show="e.auto"
                                      class="text-[9px] px-1.5 py-0.5 rounded bg-sky-100 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 font-medium">
                                    AUTO
                                </span>
                            </div>
                            <template x-if="e.ok === false && e.msg">
                                <p class="text-[10px] text-red-500 truncate mt-0.5" x-text="e.msg"></p>
                            </template>
                        </div>
                        <span class="text-[10px] text-zinc-400 shrink-0 mt-0.5" x-text="e.hora"></span>
                    </div>
                </template>
            </div>
        </template>
    </div>

</div>
