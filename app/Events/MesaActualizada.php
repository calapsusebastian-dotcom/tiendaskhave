<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesaActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $tiendaId) {}

    public function broadcastOn(): Channel
    {
        return new Channel("comandas.{$this->tiendaId}");
    }

    public function broadcastAs(): string
    {
        return 'MesaActualizada';
    }
}
