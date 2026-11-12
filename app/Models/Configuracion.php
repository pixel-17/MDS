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

    public function valorFormateado(): string
    {
        if (preg_match('/(_HORA_(INICIO|FIN)|^BLOQUE_ALMUERZO_(INICIO|FIN))$/', $this->clave)) {
            return "{$this->valor} h";
        }

        if ($this->clave === 'HORARIO_ORDINARIO_DIAS_LABORABLES') {
            $nombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

            return collect(explode(',', $this->valor))
                ->filter(fn ($dia) => $dia !== '')
                ->map(fn ($dia) => $nombres[((int) $dia) - 1] ?? $dia)
                ->implode(', ');
        }

        if (str_ends_with($this->clave, '_MINUTOS')) {
            return "{$this->valor} min";
        }

        if (str_ends_with($this->clave, '_HORAS_HABILES')) {
            return "{$this->valor} horas hábiles";
        }

        if (str_ends_with($this->clave, '_DIAS_HABILES')) {
            return "{$this->valor} días hábiles";
        }

        return $this->valor;
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
