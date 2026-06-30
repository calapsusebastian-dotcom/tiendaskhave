<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mesa extends Model
{
    protected $table = 'mesas';

    protected $fillable = ['tienda_id', 'numero', 'nombre', 'capacidad', 'estado', 'pos_x', 'pos_y'];

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    public function comandas(): HasMany
    {
        return $this->hasMany(Comanda::class);
    }

    public function comandaActiva(): HasOne
    {
        return $this->hasOne(Comanda::class)->whereIn('estado', ['abierta', 'en_cuenta']);
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'libre'     => 'Libre',
            'ocupada'   => 'Ocupada',
            'en_cuenta' => 'En cuenta',
            default     => $this->estado,
        };
    }
}
