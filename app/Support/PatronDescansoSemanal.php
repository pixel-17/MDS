<?php

namespace App\Support;

use Illuminate\Support\Carbon;


final class PatronDescansoSemanal
{
    /**
      * @param  array<int, int>  $diasSemana  1..7, sin repetir
      * @return array{fecha_ancla: Carbon, dias_trabajo: int, dias_descanso: int}|null null si la selección no es válida
      */
    public static function anclaYCiclo(array $diasSemana, Carbon $referencia): ?array
    {
        $dias = array_values(array_unique(array_map('intval', $diasSemana)));

        if ($dias === [] || count($dias) >= 7 || ! self::esArcoContiguo($dias)) {
            return null;
        }

        sort($dias);

        
        
        $primerDiaTrabajo = collect(range(1, 7))
            ->first(fn (int $d) => ! in_array($d, $dias, true) && in_array($d === 1 ? 7 : $d - 1, $dias, true));

        $ancla = $referencia->copy()->startOfWeek(Carbon::MONDAY);
        while ($ancla->dayOfWeekIso !== $primerDiaTrabajo) {
            $ancla->addDay();
        }

        return [
            'fecha_ancla' => $ancla,
            'dias_trabajo' => 7 - count($dias),
            'dias_descanso' => count($dias),
        ];
    }

    /** @param  array<int, int>  $dias  1..7, sin repetir, ya sin duplicados */
    public static function esArcoContiguo(array $dias): bool
    {
        sort($dias);

        for ($inicio = 1; $inicio <= 7; $inicio++) {
            $arco = collect(range(0, count($dias) - 1))
                ->map(fn (int $k) => (($inicio - 1 + $k) % 7) + 1)
                ->sort()
                ->values()
                ->all();

            if ($arco === $dias) {
                return true;
            }
        }

        return false;
    }
}
