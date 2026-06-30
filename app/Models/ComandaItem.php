<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaItem extends Model
{
    protected $table = 'comanda_items';

    protected $fillable = ['comanda_id', 'producto_menu_id', 'cantidad', 'precio_unitario', 'observacion'];

    protected $casts = ['precio_unitario' => 'decimal:2'];

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    public function productoMenu(): BelongsTo
    {
        return $this->belongsTo(ProductoMenu::class);
    }

    public function subtotal(): float
    {
        return $this->cantidad * (float) $this->precio_unitario;
    }
}
