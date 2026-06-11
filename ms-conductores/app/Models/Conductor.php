<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Conductor extends Model
{
    protected $table = 'conductores';

    protected $fillable = [
        'nombres', 'apellidos', 'documento', 'telefono', 'correo',
        'numero_licencia', 'categoria_licencia', 'fecha_vencimiento_licencia', 'estado',
    ];

    protected $casts = ['fecha_vencimiento_licencia' => 'date:Y-m-d'];
}
