<?php

namespace App\Http\Livewire;

use App\Http\Livewire\Concerns\RequiresRole;
use App\Models\Appointment;
use App\Models\CashierShift;
use App\Models\Doctor;
use App\Models\Item;
use App\Models\Patient;
use App\Models\Voucher;
use App\Models\VoucherSerie;
use App\Services\SunatService;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Iterator;
use Livewire\Component;

class Sales extends Component
{
    use RequiresRole;

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
        $this->requireRole('RECEPCION');

        $this->turno = CashierShift::where('user_id', auth()->id())
            ->where('estado', 'ABIERTO')
            ->latest('abierto_en')
            ->first();

        $this->doctores = Doctor::where('estado', 'ACTIVO')->orderBy('nombre')->get();
    }

    public function hydrate()
    {
        $this->requireRole('RECEPCION');
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
        $this->resetValidation('catalog');
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

        $results = $items->limit(15)->get()->map(fn ($row) => (array) $row);
        if ($this->filtroDoctorId) {
            $resolver = app(ActiveDoctorServiceResolver::class);
            $assignments = $resolver->query()->with('service:id,nombre')
                ->where('doctor_id', $this->filtroDoctorId)
                ->whereHas('service', fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%"))->get();
            try {
                $resolver->assertUniquePairs($assignments);
                $results = $results->concat($assignments->map(fn ($row) => [
                    'id' => (int) $row->service_id, 'nombre' => $row->service->nombre,
                    'tipo_origen' => 'servicio', 'categoria' => 'CONSULTA MEDICA',
                    'precio' => (float) $row->precio_primera_consulta, 'comision' => 0,
                    'afectacion_igv' => '10', 'codigo_sunat' => null, 'unidad_medida_sunat' => 'ZZ',
                ]));
            } catch (ValidationException $exception) {
                $this->addError('catalog', $exception->errors()['service_id'][0]);
            }
        }
        $this->resultadosBusqueda = $results->take(15)->values()->all();
        //dd($this->resultadosBusqueda);
    }

    public function agregarAlCarrito(array $resultado)
    {
        if (in_array($resultado['tipo_origen'], ['cita', Appointment::class], true)) {
            $appointment = Appointment::visibleToAgendaUser((int) auth()->id())->whereKey($resultado['id'])->firstOr(fn () => abort(404));
            $resultado['precio'] = (float) $appointment->precio_programado;
        }
        if ($resultado['tipo_origen'] === 'servicio') {
            $this->resetValidation('catalog');
            if (!$this->filtroDoctorId) {
                $this->addError('catalog', 'Seleccione un médico antes de agregar un servicio.');
                return;
            }
            try {
                $assignment = app(ActiveDoctorServiceResolver::class)->resolve($this->filtroDoctorId, (int) $resultado['id']);
                $resultado['precio'] = (float) $assignment->precio_primera_consulta;
            } catch (ValidationException $exception) {
                $this->addError('catalog', $exception->errors()['service_id'][0]);
                return;
            }
        }
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

    public function updatedFiltroDoctorId()
    {
        $this->resultadosBusqueda = [];
        $this->updatedBusqueda();
    }

    public function getCalculoCarritoProperty()
    {
        $this->assertCartAppointmentsVisible();
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

        return Voucher::visibleToAgendaUser((int) auth()->id())->where('patient_id', $this->atiendeId)
            ->where('tipo_comprobante', 'TICKET')
            ->whereDoesntHave('childVouchers', fn ($child) => $child->visibleToAgendaUser((int) auth()->id()))
            ->whereHas('items', fn($q) => $q->where('item_type', 'cita'))
            ->get();
    }

    public function liquidarTicket(int $ticketId)
    {
        $ticket = Voucher::visibleToAgendaUser((int) auth()->id())->with('items')->whereKey($ticketId)->firstOr(fn () => abort(404));

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
            $ticket = Voucher::visibleToAgendaUser((int) auth()->id())->whereKey($this->ticketOrigenId)->firstOr(fn () => abort(404));
            return $ticket->saldo_pendiente;
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
            ->visibleToAgendaUser((int) auth()->id())
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->join('doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->join('services', 'services.id', '=', 'appointments.service_id')
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
                'appointments.precio_programado'
            )
            ->limit(10)
            ->get()
            ->map(function ($c) {
                // This appointment already has an economic snapshot; never reprice it from today's catalogue.
                $precioTotal = (float) $c->precio_programado;
                $ticket = Voucher::visibleToAgendaUser((int) auth()->id())->where('tipo_comprobante', 'TICKET')
                    ->whereHas('items', fn($q) => $q->where('item_type', 'cita')->where('item_id', $c->id))
                    ->latest()
                    ->first();

                if ($ticket && Voucher::visibleToAgendaUser((int) auth()->id())->where('parent_voucher_id', $ticket->id)->exists()) {
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
        $appointment = Appointment::visibleToAgendaUser((int) auth()->id())->whereKey($appointmentId)->firstOr(fn () => abort(404));
        if (!$this->atiendeId) {
            $paciente = $appointment->patient;
            $this->atiendeId = $appointment->patient_id;
            $this->atiendeNombre = trim("{$paciente->nombre} {$paciente->apellido_paterno} {$paciente->apellido_materno}");
        }


        $this->carrito[] = [
            'item_type' => 'cita',
            'item_id' => $appointmentId,
            'descripcion' => 'Consulta médica',
            'precio' => (float) $appointment->precio_programado,
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
            $montoRequerido = Voucher::visibleToAgendaUser((int) auth()->id())->whereKey($this->ticketOrigenId)->firstOr(fn () => abort(404))->saldo_pendiente;
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
            $montoRequerido = Voucher::visibleToAgendaUser((int) auth()->id())->whereKey($this->ticketOrigenId)->firstOr(fn () => abort(404))->saldo_pendiente;
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
                    app(\App\Services\Billing\VoucherPaymentRecorder::class)->record($voucher, $this->turno, (int) auth()->id(),
                        \App\Support\Billing\Money::cents($datos['monto']), $metodo, $datos['operacion'] ?: null,
                        $datos['origen'] ?: null, $datos['destino'] ?: null);
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



    /** Public Livewire properties can be changed without going through add-to-cart. */
    private function assertCartAppointmentsVisible(): void
    {
        $ids = collect($this->carrito)
            ->filter(fn (array $line) => in_array($line['item_type'] ?? null, ['cita', Appointment::class], true))
            ->pluck('item_id')->unique()->values();

        if ($ids->isNotEmpty()) {
            $visible = Appointment::visibleToAgendaUser((int) auth()->id())->whereIn('id', $ids)->count();
            abort_unless($visible === $ids->count(), 404);
        }
    }

    public function render()
    {
        return view('livewire.sales');
    }
}
