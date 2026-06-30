<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MateriaPrima extends Model
{
    protected $table = 'materias_primas';

    protected $fillable = ['nombre', 'codigo_producto', 'unidad', 'precio', 'iva', 'proveedor_id', 'activo'];

    protected $casts = ['precio' => 'decimal:2', 'activo' => 'boolean'];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function pedidoItems(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }
}
