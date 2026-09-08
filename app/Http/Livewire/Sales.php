<?php

namespace App\Http\Livewire;

use App\Models\CashierShift;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\VoucherSerie;
use App\Services\SunatService;
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
    public string $buscarRuc = '';
    public string $buscarAtiende = '';
    public array $resultadosAtiende  = [];
    public ?int $atiendeId = null;
    public ?string $atiendeNombre = null;

    public string $buscarPaga = '';
    public array $resultadosPaga = [];
    public ?int $pagaId = null;
    public ?string $pagaNombre = null;


    public $doctores = [];
    public ?int $filtroDoctorId = null;


    /*═══════════════════════════════════════════════════════════
     * BUSCADOR TRANSVERSAL
     * "Transversal" porque junta resultados de DOS tablas
     * distintas (items y services) en una sola lista.
     ═══════════════════════════════════════════════════════════*/
    public string $busqueda = '';
    public array $resultadosBusqueda = [];


    public array $carrito = [];

    public float $pagoEfectivo = 0;
    public float $pagoTarjeta = 0;
    public float $pagoYape = 0;
    public float $pagoPlin = 0;

    public ?int $voucherGuardadoId = null;



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

    public function seleccionarAtiende(int $patientId, string $nombre)
    {
        $this->atiendeId = $patientId;
        $this->atiendeNombre = $nombre;
    }

    public function cambiarAtiende()
    {
        $this->atiendeId = null;
        $this->atiendeNombre = null;
        $this->buscarAtiende = '';
        $this->resultadosAtiende = [];
    }

    public function buscarPorRuc(SunatService $sunat)
    {
        if (strlen($this->buscarRuc) !== 11) {
            session()->flash('error', 'El RUC debe tener 11 dígitos');
            return;
        }

        $empresa = $sunat->consultar($this->buscarRuc);

        if (!$empresa || empty($empresa['razon_social'])) {
            session()->flash('error', 'No se encontró información para ese RUC');
            return;
        }

        if (strtoupper($empresa['estado'] ?? '') !== 'ACTIVO') {
            session()->flash('error', "Atención: esta empresa figura como '{$empresa['estado']}' ante SUNAT, Verifica antes de continuar.");
        }

        $this->tipoDocCliente = '6';
        $this->numeroDocCliente = $empresa['ruc'];
        $this->razonSocialCliente = $empresa['razon_social'];

        $this->direccionCliente = $empresa['direccion_completa'] ?? $empresa['direccion'];
        $this->pagaId = null;
        $this->pagaNombre = null;

        $this->buscarRuc = '';
    }

    public function cambiarPaga()
    {
        $this->pagaId = null;
        $this->pagaNombre = null;
        $this->buscarPaga = '';
        $this->resultadosPaga = [];

    }

    public function render()
    {
        return view('livewire.sales');
    }
}
