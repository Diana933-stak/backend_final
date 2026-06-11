<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Usuario extends Model
{
    protected $table = 'usuarios';

    protected $fillable = [
        'nombre', 'correo', 'usuario', 'contrasena', 'rol',
        'token', 'sesion_activa', 'estado',
    ];

    protected $hidden = ['contrasena', 'token'];

    protected $casts = ['sesion_activa' => 'boolean'];
}
