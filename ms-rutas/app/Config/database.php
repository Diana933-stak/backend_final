<?php

declare(strict_types=1);

namespace App\Config;

use Illuminate\Database\Capsule\Manager as Capsule;

final class Database
{
    public static function boot(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'ms_rutas_db',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
