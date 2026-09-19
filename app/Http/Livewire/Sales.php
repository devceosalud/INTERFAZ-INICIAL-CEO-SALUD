<?php

namespace App\Http\Livewire;

use App\Models\Appointment;
use App\Models\CashierShift;
use App\Models\Doctor;
use App\Models\Item;
use App\Models\Patient;
use App\Models\Voucher;
use App\Models\VoucherSerie;
use App\Services\SunatService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Iterator;
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


    public string $busqueda = '';
    public array $resultadosBusqueda = [];


    public array $carrito = [];

    public $pagoEfectivo = 0;
    public $pagoTarjeta = 0;
    public $pagoYape = 0;
    public $pagoPlin = 0;


    public $numeroOperacionTarjeta = '';
    public $numeroOperacionYape = '';
    public $numeroOperacionPlin = '';


    public $entidadOrigen = '';
    public $entidadDestino = '';

    public ?int $voucherGuardadoId = null;

    public ?int $ticketOrigenId = null;


    public string $buscarCita = '';
    public array $resultadosCitas = [];


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
            'correlativo' => $serie->correlativo_actual + 1,
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
        $this->buscarAtiende = '';
        $this->resultadosAtiende = [];
    }

    public function cambiarAtiende()
    {
        $this->atiendeId = null;
        $this->atiendeNombre = null;
        $this->buscarAtiende = '';
        $this->resultadosAtiende = [];
    }


    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
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


    public function updatedBuscarPaga()
    {
        if (strlen($this->buscarPaga) < 2) {
            $this->resultadosPaga = [];
            return;
        }

        $this->resultadosPaga = Patient::query()
            ->selectRaw("id, numero_identidad, CONCAT_WS(' ',nombre, apellido_paterno, apellido_materno) as nombre_completo")
            ->where(function ($q) {
                $q->whereRaw("CONCAT_WS(' ',nombre, apellido_paterno, apellido_materno) LIKE ?", ["%{$this->buscarPaga}%"])
                    ->orWhere('numero_identidad', 'like', "%{$this->buscarPaga}%");
            })
            ->limit(8)
            ->get()
            ->toArray();
    }

    public function seleccionarPaga(int $patientId, string $nombre)
    {
        $this->pagaId = $patientId;
        $this->pagaNombre = $nombre;
        $this->buscarPaga = '';
        $this->resultadosPaga = [];
    }

    public function cambiarPaga()
    {
        $this->pagaId = null;
        $this->pagaNombre = null;
        $this->buscarPaga = '';
        $this->resultadosPaga = [];
    }

    public function updatedBusqueda()
    {
        if (strlen($this->busqueda) < 2) {
            $this->resultadosBusqueda = [];
            return;
        }

        $items = DB::table("items")
            ->select(
                'id',
                'nombre',
                DB::raw("'item' as tipo_origen"),
                'categoria',
                'precio_venta as precio',
                'comision_medico_porcentaje as comision',
                'afectacion_igv',
                'codigo_sunat',
                'unidad_medida_sunat'
            )
            ->where('vendible', true)
            ->where('estado', 'ACTIVO')
            ->where('nombre', 'like', "%{$this->busqueda}%");

        $servicios = DB::table('services')
            ->join('doctor_services', 'doctor_services.service_id', '=', 'services.id')
            ->select(
                'services.id',
                'services.nombre',
                DB::raw("'servicio' as tipo_origen"),
                DB::raw("'CONSULTA MEDICA' as categoria"),
                'doctor_services.precio_primera_consulta as precio',
                DB::raw("0 as comision"),
                DB::raw("'10' as afectacion_igv"),
                DB::raw("NULL as codigo_sunat"),
                DB::raw("'ZZ' as unidad_medida_sunat")
            )
            ->where('services.estado', 'ACTIVO')
            ->where('services.nombre', 'like', "%{$this->busqueda}%");

        $this->resultadosBusqueda = $items->unionAll($servicios)
            ->limit(15)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
        //dd($this->resultadosBusqueda);
    }

    public function agregarAlCarrito(array $resultado)
    {
        //dd($resultado);
        $this->carrito[] = [
            'item_type' => $resultado['tipo_origen'],
            'item_id' => $resultado['id'],
            'descripcion' => $resultado['nombre'],
            'precio' => (float) ($resultado['precio'] ?? 0),
            'cantidad' => (float) ($resultado['cantidad'] ?? 1),
            'afectacion_igv' => $resultado['afectacion_igv'],
            'codigo_sunat' => $resultado['codigo_sunat'],
            'unidad_medida_sunat' => $resultado['unidad_medida_sunat'],
            'doctor_id' => $resultado['tipo_origen'] === 'servicio' ? $this->filtroDoctorId : null,
            'comision_porcentaje' => (float) $resultado['comision']
        ];

        $this->busqueda = '';
        $this->resultadosBusqueda = [];
    }

    public function getCalculoCarritoProperty()
    {
        $totalGravado = 0;
        $totalExonerado = 0;
        $totalInacfecto = 0;
        $igv = 0;

        foreach ($this->carrito as $linea) {

            $precio = (float) ($linea['precio'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            $totalLinea = round($precio * $cantidad, 2);

            if ($linea['afectacion_igv'] === '10') {
                $base = round($totalLinea / 1.18, 2);
                $igv += round($totalLinea - $base, 2);
                $totalGravado += $base;
            } elseif ($linea['afectacion_igv'] === '20') {
                $totalExonerado += $totalLinea;
            } else {
                $totalInacfecto += $totalLinea;
            }
        }

        $subtotal  = round($totalGravado + $totalExonerado + $totalInacfecto, 2);

        return [
            'total_gravado' => round($totalGravado, 2),
            'total_exonerado' => round($totalExonerado, 2),
            'total_inafecto' => round($totalInacfecto, 2),
            'subtotal' => $subtotal,
            'igv' => round($igv, 2),
            'total' => round($subtotal  + $igv, 2), // lo que el paciente paga en total
        ];
    }

    public function getTotalPagadoProperty()
    {
        return (float)($this->pagoEfectivo ?: 0) + (float)($this->pagoTarjeta ?: 0)
            + (float)($this->pagoYape ?: 0) + (float)($this->pagoPlin ?: 0);
    }

    public function getVueltoProperty()
    {
        return max(0, $this->totalPagado - $this->montoACobrar); // CORREGIDO
    }


    public function quitarDelCarrito(int $index)
    {
        unset($this->carrito[$index]);
        $this->carrito = array_values($this->carrito); //reordena el indice
    }

    public function actualizarDoctorLinea(int $index, ?int $doctorId)
    {
        $this->carrito[$index]['doctor_id'] = $doctorId;
    }

    public function updatedTipoComprobante()
    {
        if ($this->tipoComprobante !== 'FACTURA') {
            $this->tipoDocCliente = null;
            $this->numeroDocCliente = null;
            $this->razonSocialCliente = null;
            $this->direccionCliente = null;
            $this->aplicaDetraccion = false;
            $this->buscarRuc = '';
        }
    }


    public function getTicketsPendientesProperty()
    {
        if (!$this->atiendeId) {
            return collect();
        }

        return Voucher::where('patient_id', $this->atiendeId)
            ->where('tipo_comprobante', 'TICKET')
            ->whereDoesntHave('childVouchers')
            ->whereHas('items', fn($q) => $q->where('item_type', 'cita'))
            ->get();
    }

    public function liquidarTicket(int $ticketId)
    {
        $ticket = Voucher::with('items')->findOrFail($ticketId);

        $this->ticketOrigenId = $ticket->id;
        $this->tipoComprobante = 'BOLETA'; //el cajero lo puede cambiar a FACTURA si hace falta

        $this->carrito = $ticket->items->map(fn($item) => [
            'item_type' => $item->item_type,
            'item_id' => $item->item_id,
            'descripcion' => $item->descripcion,
            'precio' => (float) $item->precio_unitario,
            'cantidad' => (float) $item->cantidad,
            'afectacion_igv' => (float) $item->afectacion_igv,
            'codigo_sunat' => $item->codigo_sunat,
            'unidad_medida_sunat' => $item->unidad_medida_sunat,
            'doctor_id' => $item->doctor_id,
            'comision_porcentaje' => (float) $item->comision_porcentaje
        ])->toArray();

        $this->buscarCita = '';
        $this->resultadosCitas = [];
    }


    public function getMontoACobrarProperty(): float
    {
        if ($this->ticketOrigenId) {
            $ticket = Voucher::find($this->ticketOrigenId);
            return $ticket ? $ticket->saldo_pendiente : $this->calculoCarrito['total'];
        }

        return $this->calculoCarrito['total'];
    }



    public function updatedBuscarCita()
    {
        if (strlen($this->buscarCita) < 2) {
            $this->resultadosCitas = [];
            return;
        }

        $this->resultadosCitas = Appointment::query()
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->join('doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->join('doctor_services', function ($j) {
                $j->on('doctor_services.doctor_id', '=', 'appointments.doctor_id')
                    ->on('doctor_services.service_id', '=', 'appointments.service_id');
            })
            ->leftJoin('additional_rates', 'additional_rates.id', '=', 'appointments.additional_rate_id')
            ->where(function ($q) {
                $q->whereRaw("CONCAT_WS(' ', patients.nombre, patients.apellido_paterno, patients.apellido_materno) LIKE ?", ["%{$this->buscarCita}%"])
                    ->orWhere('appointments.numero_cita', 'like', "%{$this->buscarCita}%");
            })
            ->whereNotIn('appointments.estado_cita', ['CANCELADO', 'NO_ASISTIO'])

            ->select(
                'appointments.id',
                'appointments.numero_cita',
                'appointments.patient_id',
                'appointments.fecha_cita',
                'appointments.hora_cita',
                'appointments.estado_cita',
                'doctors.id as doctor_id',
                'doctors.nombre as doctor_nombre',
                'services.nombre as servicio_nombre',
                'doctor_services.precio_primera_consulta',
                DB::raw('COALESCE(additional_rates.tarifa,0) as tarifa_adicional')
            )
            ->limit(10)
            ->get()
            ->map(function ($c) {
                $precioTotal = (float) $c->precio_primera_consulta + (float) $c->tarifa_adicional;
                $ticket = Voucher::where('tipo_comprobante', 'TICKET')
                    ->whereHas('items', fn($q) => $q->where('item_type', 'cita')->where('item_id', $c->id))
                    ->latest()
                    ->first();

                if ($ticket && Voucher::where('parent_voucher_id', $ticket->id)->exists()) {
                    return null;
                }

                return [
                    'appointment_id' => $c->id,
                    'patient_id' => $c->patient_id,
                    'doctor_id' => $c->doctor_id,
                    'texto' => "{$c->servicio_nombre} — Dr. {$c->doctor_nombre} — " . Carbon::parse($c->fecha_cita)->format('d/m') . " {$c->hora_cita} [{$c->estado_cita}]",
                    'precio_total' => $precioTotal,
                    'ticket_pendiente_id' => $ticket?->id,
                    'saldo_pendiente' => $ticket?->saldo_pendiente ?? $precioTotal,
                ];
            })
            ->filter() // quita los null (citas ya formalizadas del todo)
            ->values()
            ->toArray();
    }


    public function agregarCitaAlCarrito(int $appointmentId, int $patientId, float $precio, ?int $doctorId)
    {
        if (!$this->atiendeId) {
            $paciente = Patient::find($patientId);
            $this->atiendeId = $patientId;
            $this->atiendeNombre = trim("{$paciente->nombre} {$paciente->apellido_paterno} {$paciente->apellido_materno}");
        }


        $this->carrito[] = [
            'item_type' => 'cita',
            'item_id' => $appointmentId,
            'descripcion' => 'Consulta médica',
            'precio' => $precio,
            'cantidad' => 1,
            'afectacion_igv' => '10', // confirma con tu contador si las consultas son GRAVADA o EXONERADA
            'codigo_sunat' => null,
            'unidad_medida_sunat' => 'ZZ',
            'doctor_id' => $doctorId,
            'comision_porcentaje' => 0,
        ];

        $this->buscarCita = '';
        $this->resultadosCitas = [];
    }


    public function guardarVenta()
    {
        if (!$this->turno) {
            session()->flash('error', 'No tiene una caja abierta. Abre tu turno antes de vender');
            return;
        }

        if (empty($this->carrito)) {
            session()->flash('error', 'Agregar al menos un producto o servicio.');
            return;
        }

        if (!$this->atiendeId) {
            session()->flash('error', 'Debes seleccionar quién se atiende');
            return;
        }

        $calculo = $this->calculoCarrito;

        $montoRequerido = $calculo['total'];
        if ($this->ticketOrigenId) {
            $montoRequerido = Voucher::findOrFail($this->ticketOrigenId)->saldo_pendiente;
        }

        if ($this->tipoComprobante === 'FACTURA' && (!$this->numeroDocCliente || !$this->razonSocialCliente)) {
            session()->flash('error', 'Para FACTURA, el RUC y la razón social son obligatorios.');
            return;
        }

        if ($this->tipoComprobante !== 'TICKET' && $this->totalPagado < $montoRequerido) {
            session()->flash('error', 'El pago no cubre el saldo pendiente.');
            return;
        }

        $montoRequerido = $calculo['total'];
        if ($this->ticketOrigenId) {
            $montoRequerido = Voucher::findOrFail($this->ticketOrigenId)->saldo_pendiente;
        }

        if ($this->tipoComprobante !== 'TICKET' && $this->totalPagado < $montoRequerido) {
            session()->flash('error', 'El pago no cubre el saldo pendiente.');
            return;
        }


        $voucherId = DB::transaction(function () use ($calculo) {
            $serie = VoucherSerie::where('tipo_comprobante', $this->tipoComprobante)
                ->where('estado', 'ACTIVO')
                ->lockForUpdate()
                ->firstOrFail(); // a diferencia de first(), lanza una excepcion si no encuentra nada, en vez de devolver null silenciosamente

            $correlativo = $serie->correlativo_actual + 1;
            $serie->update(['correlativo_actual' => $correlativo]);
            $estado = $this->totalPagado >= $calculo['total'] ? 'PAGADO' : 'PARCIAL';

            $voucher = Voucher::create([
                'tipo_comprobante' => $this->tipoComprobante,
                'serie' => $serie->serie,
                'correlativo' => $correlativo,
                'patient_id' => $this->atiendeId,
                'paga_patient_id' => $this->pagaId ?: $this->atiendeId,
                'tipo_doc_cliente' => $this->tipoDocCliente,
                'numero_doc_cliente' => $this->numeroDocCliente,
                'razon_social_cliente' => $this->razonSocialCliente,
                'direccion_cliente' => $this->direccionCliente,
                'total_gravado' => $calculo['total_gravado'],
                'total_exonerado' => $calculo['total_exonerado'],
                'total_inafecto' => $calculo['total_inafecto'],
                'subtotal' => $calculo['subtotal'],
                'igv' => $calculo['igv'],
                'total' => $calculo['total'],
                'condicion_pago' => 'CONTADO',
                'estado' => $estado,
                'cashier_shift_id' => $this->turno->id,
                'user_id' => auth()->id(),
                'aplica_detraccion' => $this->aplicaDetraccion,
                'tipo_detraccion' => $this->tipoDetraccion,
                'requiere_sunat' => $this->tipoComprobante !== 'TICKET',
                'estado_sunat' => $this->tipoComprobante !== 'TICKET' ? 'PENDIENTE' : 'NO_APLICA',
                'parent_voucher_id' => $this->ticketOrigenId, // null si es venta nueva, o el id del ticket si liquida uno
            ]);

            if ($this->ticketOrigenId) {
                Voucher::where('id', $this->ticketOrigenId)->update(['estado' => 'PAGADO']);
            }

            foreach ($this->carrito as $linea) {
                $precio = (float) ($linea['precio'] ?? 0);
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $totalLinea = round($precio * $cantidad, 2);

                $base = $linea['afectacion_igv'] === '10' ? round($totalLinea / 1.18, 2) : $totalLinea;
                $igvLinea = $linea['afectacion_igv'] === '10' ? round($totalLinea - $base, 2) : 0;

                $voucher->items()->create([
                    'item_type' => $linea['item_type'],
                    'item_id' => $linea['item_id'],
                    'descripcion' => $linea['descripcion'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'total' => $totalLinea,
                    'afectacion_igv' => $linea['afectacion_igv'],
                    'igv_monto' => $igvLinea,
                    'codigo_sunat' => $linea['codigo_sunat'],
                    'unidad_medida_sunat' => $linea['unidad_medida_sunat'],
                    'doctor_id' => $linea['doctor_id'],
                    'comision_porcentaje' => $linea['comision_porcentaje'],
                    'comision_monto' => round($totalLinea * $linea['comision_porcentaje'] / 100, 2)
                ]);

                if ($linea['item_type'] === 'item') {
                    Item::where('id', $linea['item_id'])
                        ->where('tipo', 'PRODUCTO')
                        ->decrement('stock_actual', $linea['cantidad']);
                }
            }

            // cada método trae también su número de operación
            foreach (
                [
                    'EFECTIVO' => ['monto' => $this->pagoEfectivo, 'operacion' => null, 'origen' => null, 'destino' => null],
                    'TARJETA' => ['monto' => $this->pagoTarjeta, 'operacion' => $this->numeroOperacionTarjeta, 'origen' => $this->entidadOrigen, 'destino' => $this->entidadDestino],
                    'YAPE' => ['monto' => $this->pagoYape, 'operacion' => $this->numeroOperacionYape, 'origen' => null, 'destino' => null],
                    'PLIN' => ['monto' => $this->pagoPlin, 'operacion' => $this->numeroOperacionPlin, 'origen' => null, 'destino' => null],
                ] as $metodo => $datos
            ) {
                if ($datos['monto'] > 0) {
                    $voucher->payments()->create([
                        'metodo_pago' => $metodo,
                        'monto' => $datos['monto'],
                        'numero_operacion' => $datos['operacion'] ?: null,
                        'entidad_origen' => $datos['origen'] ?: null,
                        'entidad_destino' => $datos['destino'] ?: null,
                        'user_id' => auth()->id(),
                        'cashier_shift_id' => $this->turno->id,
                    ]);
                }
            }

            return $voucher->id;
        });

        $this->voucherGuardadoId = $voucherId;

        //Livewire 3 $this->dispatch('venta-guardada', voucherId: $voucherId);
        $this->dispatchBrowserEvent('venta-guardada', [
            'voucherId' => $voucherId
        ]);

        $this->reset([
            'carrito',
            'atiendeId',
            'atiendeNombre',
            'pagaId',
            'pagaNombre',
            'pagoEfectivo',
            'pagoTarjeta',
            'pagoYape',
            'pagoPlin',
            'tipoDocCliente',
            'numeroDocCliente',
            'razonSocialCliente',
            'direccionCliente',
            'buscarRuc'
        ]);

        session()->flash('ok', 'Venta registrada correctamente');
    }



    public function render()
    {
        return view('livewire.sales');
    }
}
