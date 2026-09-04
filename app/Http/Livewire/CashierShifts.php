<?php

namespace App\Http\Livewire;

use App\Models\Cashier;
use App\Models\CashierShift;
use Livewire\Component;

class CashierShifts extends Component
{
    /**═══════════════════════════════════════════════════════════
     * EL TURNO DEL CAJERO LOGUEADO, SI EXISTE
     * SI ES NULL => SIGNIFICA QUE DEBE ABRIR UNO ANTES DE PODER VENDER 
       ═══════════════════════════════════════════════════════════*/
    public ?CashierShift $turno = null;


    /**═══════════════════════════════════════════════════════════
     * DATOS PARA ABRIR TURNO
       ═══════════════════════════════════════════════════════════*/
    public $cajas = []; //LISTA DE CAJAS FISICAS DISPONIBLE PARA EL SELECT
    public ?int $cajaId = null;  //CAJA ELEGIDA
    public ?float $montoApertura = null; //SENCILLO CON EL QUE ARRANCA EL DIA


    /**═══════════════════════════════════════════════════════════
     * DATOS PARA CERRAR TURNO
       ═══════════════════════════════════════════════════════════*/
    public float $montoContado = 0; // LO QUE EL CAJERO CUENTA FISICAMENTE
    public string $observacionesCierre = '';


    public function mount()
    {
        //BUSCAMOS SI EL CAJERO LOGUEADO YA TIENE UN TURNO ABIERTO
        //ESTO DECIDE QUE PANTALLA MOSTRAR (ABRIR - CERRAR)
        $this->turno = CashierShift::where('user_id', auth()->id())
            ->where('estado', 'ABIERTO')
            ->latest('abierto_en')
            ->first();

        //SOLO CARGAMOS LA LISTA DE CAJAS SI HACE FALTA MOSTRARLA
        //(ES DECIR, SI EL CAJERO TODAVIA NO TIENE TURNO ABIERTO)
        if (!$this->turno) {
            $this->cajas = Cashier::where('estado', 'ACTIVO')->orderBy('nombre')->get();
        }
    }

    /**═══════════════════════════════════════════════════════════
     * MUESTRA EN VIVO LO QUE LLEVARIA VENDIDO ESTE TURNO 
     * PARA QUE EL CAJERO TENGA UNA REFERENCIA MIENTRAS TRABAJA 
     * (NO ES OBLIGATORIO, ES INFORMATIVO - AYUDA A DETECTAR PROBLEMAS ANTES DEL CIERRE, NO SOLO AL FINAL DEL DIA)
      ═══════════════════════════════════════════════════════════ */
    public function getResumenHoyProperty()
    {
        if (!$this->turno) {
            return [
                'ventas' => 0,
                'efectivo' => 0
            ];
        }

        return [
            //suma() => DE UNA RELACION HASMANY CUENTA TODAS LAS FILAS
            //RELACIONADAS Y SUMA LA COLUMNA INDICADA, EN UNA SOLA CONSULTA SQL
            //(NO TRAE LOS REGISTROS A PHP PARA SUMARLO)
            'ventas' => $this->turno->vouchers()->where('estado', '!=', 'ANULADO')->sum('total'),
            'efectivo' => $this->turno->payments()->where('metodo_pago', 'EFECTIVO')->sum('monto')
        ];
    }

    /**═══════════════════════════════════════════════════════════
     * EL CALCULO QUE SE LE MUESTRA AL CAJERO ANTES DE QUE INGRESE A SU CONTEO FISICO
     * ASI COMPARA LO QUE EL CONTO CONTRA LO QUE EL SISTEMA ESPERA, EN TIEMPO REAL
      ═══════════════════════════════════════════════════════════*/
    public function getMontoSistemaProperty()
    {
        return $this->turno ? $this->turno->calcularMontoSistema() : 0;
    }

    public function getDiferenciaProperty()
    {
        return round($this->montoContado - $this->montoSistema, 2);
    }

    /**═══════════════════════════════════════════════════════════
     * ABRIR TURNO
      ═══════════════════════════════════════════════════════════*/
    public function abrirTurno()
    {
        //dd($this->cajaId);
        if (!$this->cajaId) {
            session()->flash('error', 'Seleccione una caja');
            return;
        }

        //IMPORTANTE RDN1: NADIE PUEDE TENER DOS TURNOS ABIERTOS A LA VEZ
        //ESTO EVITA, POR EJEMPLO, QUE UN CAJERO ABRA SIN QUERER DOS VECES (DOBLE CLICK)
        //Y TERMINE CON VENTAS REPARTIDAS EN DOS TURNOS DISTINTOS POR ERROR
        $yaTieneAbierto = CashierShift::where('user_id', auth()->id())
            ->where('estado', 'ABIERTO')
            ->exists();
        //exists() => ES MAS RAPIDO DE count() > 0, PORQUE MYSQL PUEDE PARAR EN CUANTO ENCUENTRA EL PRIMERO

        if ($yaTieneAbierto) {
            session()->flash('error', 'Ya tienes un turno abierto.');
            return;
        }

        //IMPORTANTE RDN2: ESA CAJA FISICA NO PUEDE ESTAR SIENDO USADA POR OTRO CAJERO AL MISMO TIEMPO
        //EVITA QUE DOS PERSONAS COBREN "DESDE LA MISMA CAJA" Y SE MEZCLEN LOS NUMEROS EN EL ARQUEO
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


    /**═══════════════════════════════════════════════════════════
     * CERRAR TURNO
      ═══════════════════════════════════════════════════════════*/
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

        //RESETEAMOS EL ESTADO DEL COMPONENTE PARA QUE, SI EL CAJERO SE QUEDE EN LA PANTALLA
        //VEA EL FORMULARIO DE  "abrir turno" DE NUEVO EN VEZ DEL QUE ACABA DE CERRAR
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
