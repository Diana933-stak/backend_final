<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SeguimientoViaje extends Model
{
    protected $table = 'seguimientos_viajes';
    protected $fillable = ['programacion_viaje_id', 'fecha', 'hora', 'estado', 'novedad'];
    protected $casts = ['fecha' => 'date:Y-m-d'];
}
