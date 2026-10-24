<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HorasAcumuladasExport implements Export, WithMultipleSheets
{
    public function __construct(
        protected Collection $resumen,
        protected Collection $detalle,
    ) {}

    public function sheets(): array
    {
        return [
            'Resumen mensual' => new HorasAcumuladasResumenSheet($this->resumen),
            'Detalle diario' => new HorasAcumuladasDetalleSheet($this->detalle),
        ];
    }
}
