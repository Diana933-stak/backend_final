<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProgramacionViaje extends Model
{
    protected $table = 'programaciones_viajes';
    protected $fillable = [
        'conductor_id', 'vehiculo_id', 'ruta_id', 'fecha_salida', 'hora_salida',
        'fecha_estimada_llegada', 'observaciones', 'estado',
    ];
    protected $casts = ['fecha_salida' => 'date:Y-m-d', 'fecha_estimada_llegada' => 'date:Y-m-d'];

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'ruta_id');
    }
}
