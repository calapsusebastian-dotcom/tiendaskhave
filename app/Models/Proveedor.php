<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'contacto', 'telefono', 'email', 'categoria', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function materiasPrimas(): HasMany
    {
        return $this->hasMany(MateriaPrima::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
