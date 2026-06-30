<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    protected $table = 'pedido_items';

    protected $fillable = ['pedido_id', 'materia_prima_id', 'cantidad', 'precio_unitario', 'iva'];

    protected $casts = ['precio_unitario' => 'decimal:2'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function materiaPrima(): BelongsTo
    {
        return $this->belongsTo(MateriaPrima::class);
    }

    public function subtotalBase(): float
    {
        return $this->cantidad * (float) $this->precio_unitario;
    }

    public function valorIva(): float
    {
        return $this->subtotalBase() * ((int) $this->iva / 100);
    }

    public function subtotal(): float
    {
        return $this->subtotalBase() + $this->valorIva();
    }
}
