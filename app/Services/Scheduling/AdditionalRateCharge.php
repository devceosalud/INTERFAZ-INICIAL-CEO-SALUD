<?php
namespace App\Services\Scheduling;

use App\Models\AdditionalRate;
use App\Support\Billing\Money;
use Illuminate\Validation\ValidationException;

/** Existing catalogue arithmetic, used only for direct edits of an existing appointment. */
final class AdditionalRateCharge
{
    public function forDate(int $id,string $date,bool $lock=false): AdditionalRate
    {
        $r=AdditionalRate::whereKey($id)->where('estado','ACTIVO')->when($lock,fn($q)=>$q->lockForUpdate())->first();
        if(!$r || ($r->fecha_inicio && substr($r->fecha_inicio,0,10)>$date) || ($r->fecha_fin && substr($r->fecha_fin,0,10)<$date)
            || !in_array($r->tipo_tarifa,['MONTO_FIJO','PORCENTAJE'],true) || !is_numeric($r->tarifa) || $r->tarifa<0)
            throw ValidationException::withMessages(['additional_rate_id'=>'Selecciona una tarifa activa, válida y vigente para la fecha de la cita.']);
        return $r;
    }
    public function cents(int $base,AdditionalRate $rate): int
    {
        $value=Money::cents($rate->tarifa);
        if($rate->tipo_tarifa==='MONTO_FIJO')return $value;
        if($value && $base>intdiv(PHP_INT_MAX-5000,$value))throw ValidationException::withMessages(['additional_rate_id'=>'El recargo supera el importe válido para esta cita.']);
        return intdiv($base*$value+5000,10000);
    }
}
