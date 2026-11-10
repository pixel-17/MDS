<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
    ];

    public static function valorDe(string $clave, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            self::claveCache($clave),
            fn () => static::where('clave', $clave)->value('valor')
        ) ?? $default;
    }

    public static function valorEnteroDe(string $clave, int $default = 0): int
    {
        $valor = self::valorDe($clave, $default);

        return is_numeric($valor) ? (int) $valor : $default;
    }

    public static function valorTextoDe(string $clave, string $default = ''): string
    {
        $valor = self::valorDe($clave, $default);

        return is_scalar($valor) ? (string) $valor : $default;
    }

    protected static function booted(): void
    {
        static::saved(fn (self $configuracion) => Cache::forget(self::claveCache($configuracion->clave)));
        static::deleted(fn (self $configuracion) => Cache::forget(self::claveCache($configuracion->clave)));
    }

    protected static function claveCache(string $clave): string
    {
        return "configuracion:{$clave}";
    }
}
