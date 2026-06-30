<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Traslado extends Model
{
    protected $fillable = [
        'folio', 'tienda_origen_id', 'tienda_destino_id',
        'solicitante_id', 'estado', 'imov', 'notas',
    ];

    public function tiendaOrigen(): BelongsTo
    {
        return $this->belongsTo(Tienda::class, 'tienda_origen_id');
    }

    public function tiendaDestino(): BelongsTo
    {
        return $this->belongsTo(Tienda::class, 'tienda_destino_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TrasladoItem::class);
    }

    public static function generarFolio(): string
    {
        $ultimo = static::latest('id')->value('folio');
        $numero = $ultimo ? (int) substr($ultimo, 4) + 1 : 1001;
        return 'TRL-' . $numero;
    }
}
