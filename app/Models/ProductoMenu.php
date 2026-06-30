<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoMenu extends Model
{
    protected $table = 'productos_menu';

    protected $fillable = ['tienda_id', 'nombre', 'codigo', 'descripcion', 'precio', 'categoria', 'activo'];

    protected $casts = ['precio' => 'decimal:2', 'activo' => 'boolean'];

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    public function comandaItems(): HasMany
    {
        return $this->hasMany(ComandaItem::class);
    }
}
