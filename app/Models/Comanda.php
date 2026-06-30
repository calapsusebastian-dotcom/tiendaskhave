<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comanda extends Model
{
    protected $table = 'comandas';

    protected $fillable = [
        'folio', 'mesa_id', 'tienda_id', 'mesero_id', 'estado', 'jfac', 'notas',
        'cliente_nombre', 'cliente_cc', 'cliente_telefono', 'cliente_correo',
    ];

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mesero_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComandaItem::class);
    }

    public function total(): float
    {
        return (float) $this->items->sum(fn($item) => $item->cantidad * (float) $item->precio_unitario);
    }

    public static function generarFolio(): string
    {
        $ultimo = static::latest('id')->value('folio');
        $numero = $ultimo ? (int) substr($ultimo, 4) + 1 : 1001;
        return 'CMD-' . $numero;
    }
}
