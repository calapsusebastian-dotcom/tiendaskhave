<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = ['folio', 'tienda_id', 'proveedor_id', 'estado', 'notas', 'imov', 'recibido_ok', 'observacion', 'cufe', 'factura_path', 'aprobado_at', 'enviado_at', 'recibido_at', 'terminado_at'];

    protected $casts = [
        'aprobado_at'  => 'datetime',
        'enviado_at'   => 'datetime',
        'recibido_at'  => 'datetime',
        'terminado_at' => 'datetime',
        'recibido_ok'  => 'boolean',
    ];

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function total(): float
    {
        return (float) $this->items->sum(fn ($item) => $item->subtotal());
    }

    public static function generarFolio(): string
    {
        $ultimo = static::latest('id')->value('folio');
        $numero = $ultimo ? (int) substr($ultimo, 4) + 1 : 1001;
        return 'PED-' . $numero;
    }
}
