<?php

namespace App\Http\Livewire;

use App\Models\Cashier;
use App\Models\CashierShift;
use Livewire\Component;

class CashierShifts extends Component
{
    public ?CashierShift $turno = null;


    public $cajas = []; //LISTA DE CAJAS FISICAS DISPONIBLE PARA EL SELECT
    public ?int $cajaId = null;  //CAJA ELEGIDA
    public $montoApertura = 0; //SENCILLO CON EL QUE ARRANCA EL DIA


    public float $montoContado = 0; // LO QUE EL CAJERO CUENTA FISICAMENTE
    public string $observacionesCierre = '';


    public function mount()
    {
        $this->turno = CashierShift::where('user_id', auth()->id())
            ->where('estado', 'ABIERTO')
            ->latest('abierto_en')
            ->first();

        if (!$this->turno) {
            $this->cajas = Cashier::where('estado', 'ACTIVO')
                ->whereNotIn('id', function ($query) {
                    $query->select('cashier_id')
                        ->from('cashier_shifts')
                        ->where('estado', 'ABIERTO');
                })
                ->orderBy('nombre')
                ->get();
            //dd($this->cajas);
        }
    }


    public function getResumenHoyProperty()
    {
        if (!$this->turno) {
            return [
                'ventas' => 0,
                'efectivo' => 0
            ];
        }

        return [
            'ventas' => $this->turno->vouchers()->where('estado', '!=', 'ANULADO')->sum('total'),
            'efectivo' => $this->turno->payments()->where('metodo_pago', 'EFECTIVO')->sum('monto')
        ];
    }


    public function getMontoSistemaProperty()
    {
        return $this->turno ? $this->turno->calcularMontoSistema() : 0;
    }

    public function getDiferenciaProperty()
    {
        return round($this->montoContado - $this->montoSistema, 2);
    }

    public function abrirTurno()
    {
        //dd($this->cajaId);
        if($this->montoApertura == ''){ 
           session()->flash('error', 'El valo de Monto de apertura no puede estar vacio o rellene con un 0');
           return;
        }
        
        if (!$this->cajaId) {
            session()->flash('error', 'Seleccione una caja');
            return;
        }


        $yaTieneAbierto = CashierShift::where('user_id', auth()->id())
            ->where('estado', 'ABIERTO')
            ->exists();
        //exists() => ES MAS RAPIDO DE count() > 0, PORQUE MYSQL PUEDE PARAR EN CUANTO ENCUENTRA EL PRIMERO

        if ($yaTieneAbierto) {
            session()->flash('error', 'Ya tienes un turno abierto.');
            return;
        }

        $cajaOcupada = CashierShift::where('cashier_id', $this->cajaId)
            ->where('estado', 'ABIERTO')
            ->exists();

        if ($cajaOcupada) {
            session()->flash('error', 'Esa caja ya está siendo usada por otro cajero.');
            return;
        }

        $this->turno = CashierShift::create([
            'cashier_id' => $this->cajaId,
            'user_id' => auth()->id(),
            'monto_apertura' => $this->montoApertura,
            'abierto_en' => now(),
            'estado' => 'ABIERTO'
        ]);

        session()->flash('ok', 'Turno abierto correctamente.');
    }


    public function cerrarTurno()
    {
        if (!$this->turno) {
            return; // POR SEGURIDAD: SI DE ALGUN MODO NO HAY TURNO, NO HACEMOS NADA
        }

        $montoSistema = $this->montoSistema; //CONGELAMOS EL VALOR CALCULADO, PARA NO RECALCULAR DOS VECES

        $this->turno->update([
            'monto_sistema' => $montoSistema,
            'monto_contado' => $this->montoContado,
            'diferencia' => round($this->montoContado - $montoSistema, 2),
            'observaciones_cierre' => $this->observacionesCierre,
            'cerrado_en' => now(),
            'estado' => 'CERRADO'
        ]);

        $this->turno = null;
        $this->cajas = Cashier::where('estado', 'activo')->orderBy('nombre')->get();
        $this->reset(['montoContado', 'observacionesCierre', 'cajaId', 'montoApertura']);

        session()->flash('ok', 'Turno cerrado correctamente');
    }

    public function render()
    {
        return view('livewire.cashier-shifts');
    }
}
