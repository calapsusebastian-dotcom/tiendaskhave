<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrasladoItem extends Model
{
    protected $fillable = ['traslado_id', 'materia_prima_id', 'cantidad'];

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(Traslado::class);
    }

    public function materiaPrima(): BelongsTo
    {
        return $this->belongsTo(MateriaPrima::class);
    }
}
