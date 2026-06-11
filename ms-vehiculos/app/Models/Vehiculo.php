<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Vehiculo extends Model
{
    protected $table = 'vehiculos';
    protected $fillable = ['placa', 'tipo_vehiculo', 'capacidad_carga', 'modelo', 'marca', 'estado'];
    protected $casts = ['capacidad_carga' => 'decimal:2'];
}
