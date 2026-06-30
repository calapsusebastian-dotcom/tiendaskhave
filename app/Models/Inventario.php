<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventario extends Model
{
    protected $table = 'inventarios';

    protected $fillable = ['tienda_id', 'fecha', 'estado', 'notas', 'created_by', 'diligenciado_by', 'verificado_by'];

    protected $casts = ['fecha' => 'date'];

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventarioItem::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function diligenciador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'diligenciado_by');
    }

    public function verificador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'verificado_by');
    }

    public function progreso(): array
    {
        $total    = $this->items()->count();
        $llenados = $this->items()->where('cantidad', '>', 0)->count();
        return ['llenados' => $llenados, 'total' => $total];
    }
}
