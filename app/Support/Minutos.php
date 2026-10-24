<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Minutos
{
    public static function entre(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        return max(0, intdiv($hasta->getTimestamp() - $desde->getTimestamp(), 60));
    }
}
