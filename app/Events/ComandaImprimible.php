<?php

namespace App\Events;

use App\Models\Comanda;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComandaImprimible implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comanda $comanda) {}

    public function broadcastOn(): Channel
    {
        return new Channel('comandas');
    }

    public function broadcastAs(): string
    {
        return 'ComandaImprimible';
    }

    public function broadcastWith(): array
    {
        $c = $this->comanda->load(['mesa', 'tienda', 'items.productoMenu', 'mesero']);

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
            'fecha'    => now()->format('d/m/Y H:i'),
        ];
    }
}
