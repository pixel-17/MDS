<?php

namespace App\Support;

use DateTimeImmutable;

final class PatronTurnos
{

    public const CLAVES = [
        'M' => 'MANANA',
        'T' => 'TARDE',
        'N' => 'NOCHE',
        'D' => 'DESCANSO',
    ];

    public const PREDEFINIDOS = [
        '6x1 Mañana' => 'M M M M M M D',
        '6x1 Tarde' => 'T T T T T T D',
        '6x1 Noche' => 'N N N N N N D',
        'Rotativo M M T T N D' => 'M M T T N D',
    ];

    public const MAX_MESES = 6;

    public static function parsear(string $patron): ?array
    {
        $tokens = preg_split('/[\s,]+/', mb_strtoupper(trim($patron)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return null;
        }

        $pasos = [];
        foreach ($tokens as $token) {
            if (! isset(self::CLAVES[$token])) {
                return null;
            }
            $pasos[] = self::CLAVES[$token];
        }

        return $pasos;
    }

    public static function offsetContinuacion(array $pasos, array $previos): int
    {
        $n = count($pasos);
        $previos = array_values($previos);
        $largo = count($previos);

        if ($n === 0 || $largo === 0) {
            return 0;
        }

        $mejorPosicion = null;
        $mejorRacha = 0;

        for ($posicion = 0; $posicion < $n; $posicion++) {

            $racha = 0;
            for ($k = 0; $k < $largo; $k++) {
                $codigo = $previos[$largo - 1 - $k];
                if ($codigo === null || $pasos[(($posicion - $k) % $n + $n) % $n] !== $codigo) {
                    break;
                }
                $racha++;
            }

            if ($racha > $mejorRacha) {
                $mejorRacha = $racha;
                $mejorPosicion = $posicion;
            }
        }

        return $mejorPosicion === null ? 0 : ($mejorPosicion + 1) % $n;
    }

    public static function generar(array $pasos, string $desde, string $hasta, int $offset = 0): array
    {
        $n = count($pasos);
        if ($n === 0) {
            return [];
        }

        $dias = [];
        $fecha = new DateTimeImmutable($desde);
        $fin = new DateTimeImmutable($hasta);

        for ($i = 0; $fecha <= $fin; $i++) {
            $dias[$fecha->format('Y-m-d')] = $pasos[($offset + $i) % $n];
            $fecha = $fecha->modify('+1 day');
        }

        return $dias;
    }
}
