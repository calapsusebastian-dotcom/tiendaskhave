<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tienda extends Model
{
    protected $table = 'tiendas';

    protected $fillable = ['nombre', 'codigo', 'color', 'direccion'];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
