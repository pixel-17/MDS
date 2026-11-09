<?php

namespace App\Support;

use Illuminate\Validation\Rule;


final class ReglasDatosUsuario
{
    /**
      * @param  bool  $soloActivos  true para el alta de jefes (un DNI de una
      *                             cuenta desactivada es un reingreso, no un
      *                             duplicado). false para admin: la reactivación
      *                             siempre pasa por Usuarios → Reactivar.
      * @return array<int, mixed>
      */
    public static function dni(?int $ignorarId = null, bool $soloActivos = true): array
    {
        $unique = Rule::unique('users', 'dni')->ignore($ignorarId);

        if ($soloActivos) {
            $unique->where(fn ($q) => $q->where('activo', true));
        }

        return ['required', 'string', 'digits:8', $unique];
    }

    /** @return array<int, mixed> */
    public static function email(?int $ignorarId = null): array
    {
        return ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignorarId)];
    }
}
