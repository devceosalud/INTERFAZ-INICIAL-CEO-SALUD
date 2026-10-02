<?php

namespace App\Support\Scheduling;

use App\Models\AdditionalRate;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use RuntimeException;

final class StandardAdditionalRateResolver
{
    public const STANDARD_NAME = 'TARIFA ESTANDAR';

    public function resolve(DateTimeInterface|string|null $onDate = null): AdditionalRate
    {
        $date = $onDate === null
            ? CarbonImmutable::today()
            : CarbonImmutable::parse($onDate);

        $matches = AdditionalRate::query()
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [self::STANDARD_NAME])
            ->where('tipo_tarifa', 'MONTO_FIJO')
            ->where('tarifa', 0)
            ->where('estado', 'ACTIVO')
            ->where(function ($query) use ($date): void {
                $query->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $date->toDateString());
            })
            ->limit(2)
            ->get();

        if ($matches->count() !== 1) {
            throw new RuntimeException(
                'Debe existir exactamente una TARIFA ESTANDAR activa, fija, de importe cero y vigente.'
            );
        }

        return $matches->first();
    }
}
