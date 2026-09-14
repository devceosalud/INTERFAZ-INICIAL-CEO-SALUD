<?php

namespace App\Http\Livewire;

use App\Models\CashierShift;
use App\Models\CashMovement;
use Livewire\Component;

class CashMovements extends Component
{
    public ?int $movimientoId = null;

    public $tipo = 'INGRESO';
    public ?string $concepto = '';
    public $monto = 0;
    public ?CashierShift $turno = null;
    public ?string $buscar = '';
    public $modal = false;

    protected $rules = [
        'tipo' => 'required|in:INGRESO,EGRESO',
        'concepto' => 'required|string|max:255',
        'monto' => 'required|numeric|min:0.01',
    ];

    protected $messages = [
        'tipo.required' => 'Seleccione el tipo de movimiento',
        'tipo.in' => 'El tipo de movimiento no es válido',

        'concepto.required' => 'Ingrese el concepto',
        'concepto.max' => 'El concepto no puede superar los 255 caracteres',

        'monto.required' => 'Ingrese el monto',
        'monto.numeric' => 'El monto debe ser numérico',
        'monto.min' => 'El monto debe ser mayor a 0.'
    ];

    public function mount()
    {
        $this->turno = CashierShift::where('user_id', auth()->id())
            ->where('estado', 'ABIERTO')
            ->latest('abierto_en')
            ->first();
    }


    public function abrirModal()
    {
        if (!$this->turno) {
            session()->flash('error', 'No tienes un turno de caja abierto');
            return;
        }

        $this->resetValidation();

        $this->movimientoId = null;
        $this->tipo = 'INGRESO';
        $this->concepto = '';
        $this->monto = '';

        $this->modal = true;
    }

    public function guardar()
    {
        if (!$this->turno) {
            session()->flash('error', 'No tienes un turno de caja abierto.');
            return;
        }
        //dd($this->turno->id);

        $this->validate();

        if ($this->movimientoId) {
            $movimiento = CashMovement::where('id', $this->movimientoId)
                ->where('cashier_shift_id', $this->turno->id)
                ->firstOrFail();

            $movimiento->update([
                'tipo' => $this->tipo,
                'concepto' => $this->concepto,
                'monto' => $this->monto,
            ]);

            session()->flash('succes', 'Movimiento actualizado correctamente');
        } else {

            CashMovement::create([
                'cashier_shift_id' => $this->turno->id,
                'tipo' => $this->tipo,
                'concepto' => $this->concepto,
                'monto' => $this->monto,
                'user_id' => auth()->id()
            ]);

            session()->flash('success', 'Movimiento registrado correctamente');
        }

        $this->cerrarModal();
    }

    public function editar(?int $id)
    {
        if (!$this->turno) {
            return;
        }

        $movimiento = CashMovement::where('id', $id)
            ->where('cashier_shift_id', $this->turno->id)
            ->firstOrFail();

        $this->movimientoId = $movimiento->id;
        $this->tipo = $movimiento->tipo;
        $this->concepto = $movimiento->concepto;
        $this->monto = $movimiento->monto;

        $this->resetValidation();

        $this->modal = true;
    }

    public function eliminar(?int $id)
    {
        if (!$this->turno) {
            return;
        }

        $movimiento = CashMovement::where('id', $id)
            ->where('cashier_shift_id', $this->turno->id)
            ->firstOrFail();

        $movimiento->delete();

        session()->flash('success', 'Movimiento eliminado correctamente.');
    }

    public function cerrarModal()
    {
        $this->modal = false;

        $this->movimientoId = null;
        $this->tipo = 'INGRESO';
        $this->concepto = '';
        $this->monto = '';

        $this->resetValidation();
    }

    public function render()
    {
        $movimientos = collect();

        if ($this->turno) {
            $movimientos = CashMovement::with('user')
                ->where('cashier_shift_id', $this->turno->id)
                ->where(function ($query) {
                    $query->where('concepto', 'like', '%' . $this->buscar . '%')
                        ->orWhere('tipo', 'like', '%' . $this->buscar . '%');
                })
                ->latest()
                ->get();
        }
        return view('livewire.cash-movements', [
            'movimientos' => $movimientos
        ]);
    }
}
