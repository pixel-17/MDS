<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class ReglasDatosUsuario
{

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
