<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Ruta extends Model
{
    protected $table = 'rutas';
    protected $fillable = ['ciudad_origen', 'ciudad_destino', 'distancia', 'tiempo_estimado', 'observaciones'];
    protected $casts = ['distancia' => 'decimal:2'];

    public function programaciones(): HasMany
    {
        return $this->hasMany(ProgramacionViaje::class, 'ruta_id');
    }
}
