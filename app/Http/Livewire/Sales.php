<?php

namespace App\Http\Livewire;

use App\Models\CashierShift;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\VoucherSerie;
use Livewire\Component;

class Sales extends Component
{
    public ?CashierShift $turno = null;

    /**
     * CABECERA DEL COMPROBANTE
     */
    public string $tipoComprobante = "BOLETA";
    public ?string $tipoDocCliente = null;
    public ?string $numeroDocCliente = null;
    public ?string $razonSocialCliente = null;
    public ?string $direccionCliente = null;

    public bool $aplicaDetraccion = false;
    public ?string $tipoDetraccion = null;

    /**
     * QUIEN PAGA? / QUIEN SE ATIENDE?
     */
    public string $buscarAtiende = '';
    public array $resultadosAtiende  = [];
    public ?int $atiendeId = null;
    public ?string $atiendeNombre = null;

    public string $buscarPaga = '';
    public array $resultadosPaga = [];
    public ?int $pagaId = null;
    public ?string $pagaNombre = null;


    public ?string $doctores = null;

    public function mount()
    {
        $this->turno = CashierShift::where('user_id', auth()->id())
            ->where('estado', 'ABIERTO')
            ->latest('abierto_en')
            ->first();

        $this->doctores = Doctor::where('estado', 'ACTIVO')->orderBy('nombre')->get();
    }

    public function getSeriePreviewProperty()
    {
        $serie = VoucherSerie::where('tipo_comprobante', $this->tipoComprobante)
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$serie) {
            return ['serie' => '----', 'correlativo' => 0];
        }

        return [
            'serie' => $serie->serie,
            'correlativo' => $serie->correlatico_actual + 1,
        ];
    }

    public function updatedBuscarAtiende()
    {
        if (strlen($this->buscarAtiende) < 2) {
            $this->resultadosAtiende = [];
            return;
        }

        $this->resultadosAtiende = Patient::query()
            ->selectRaw("id, numero_identidad, CONCAT_WS(' ',nombre, apellido_paterno, apellido_materno) as nombre_completo")
            ->where(function ($q) {
                $q->whereRaw("CONCAT_WS(' ',nombre, apellido_paterno, apellido_materno) LIKE ?", ["%{$this->buscarAtiende}%"])
                    ->orWhere('numero_identidad', 'like', "%{$this->buscarAtiende}%");
            })
            ->limit(8)
            ->get()
            ->toArray();

           //dd($this->resultadosAtiende); 
    }

    public function render()
    {
        return view('livewire.sales');
    }
}
