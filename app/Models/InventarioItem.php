<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioItem extends Model
{
    protected $table = 'inventario_items';

    protected $fillable = ['inventario_id', 'materia_prima_id', 'cantidad', 'cantidad_sistema'];

    protected $casts = ['cantidad' => 'decimal:2', 'cantidad_sistema' => 'decimal:2'];

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class);
    }

    public function materiaPrima(): BelongsTo
    {
        return $this->belongsTo(MateriaPrima::class);
    }
}
