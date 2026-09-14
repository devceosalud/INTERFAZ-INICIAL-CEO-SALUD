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

    /**═══════════════════════════════════════════════════════════
     * BUSQUEDA POR RUC
     ═══════════════════════════════════════════════════════════*/
    public string $buscarRuc = '';

    /**═══════════════════════════════════════════════════════════
     *  QUIEN SE ATIENDE?
     ═══════════════════════════════════════════════════════════*/
    public string $buscarAtiende = '';
    public array $resultadosAtiende  = [];
    public ?int $atiendeId = null;
    public ?string $atiendeNombre = null;


    /**═══════════════════════════════════════════════════════════
     * QUIEN PAGA?
    ═══════════════════════════════════════════════════════════ */
    public string $buscarPaga = '';
    public array $resultadosPaga = [];
    public ?int $pagaId = null;
    public ?string $pagaNombre = null;


    /**═══════════════════════════════════════════════════════════
     * LISTA DE DOCTORES  DEL SELECT
     ═══════════════════════════════════════════════════════════*/
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

    public $pagoEfectivo = 0;
    public $pagoTarjeta = 0;
    public $pagoYape = 0;
    public $pagoPlin = 0;

    // Número de operación por método (solo aplica a los que no son EFECTIVO)
    public $numeroOperacionTarjeta = '';
    public $numeroOperacionYape = '';
    public $numeroOperacionPlin = '';

    // Detalle bancario (opcional, solo si quieres replicar el nivel de
    // detalle de tu boleta de ejemplo con banco origen/destino)
    public $entidadOrigen = '';
    public $entidadDestino = '';

    public ?int $voucherGuardadoId = null;


    /*═══════════════════════════════════════════════════════════
     * Liquidar tickets con saldo pendiente
     ═══════════════════════════════════════════════════════════*/
    public ?int $ticketOrigenId = null;



    /*═══════════════════════════════════════════════════════════
     * Buscador de citas agendadas
     ═══════════════════════════════════════════════════════════*/
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

    /**═══════════════════════════════════════════════════════════
     * El prefijo updated + el nombre EXACTO  de una propiedad púclica (BuscarAtiende) -> $buscarAtiende
     * es una convención mágica de Livewire: este método corre SOLO cuando esa propiedad cambia - cada vez que el cajero
     * escriba en el input con wire:model="BuscarAtiende"
     ═══════════════════════════════════════════════════════════*/
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

    /**
 * getNombreCompletoAttribute()
 * Cualquier método con el patrón "get{Campo}Attribute" es un
 * ACCESSOR de Eloquent — Laravel lo detecta solo y te permite
 * usarlo como si fuera una columna más: $paciente->nombre_completo
 * (con guion bajo, no camelCase), en vez de tener que llamarlo
 * como método. Internamente arma el nombre juntando las 3 columnas
 * reales que sí existen en la tabla.
 */
    public function getNombreCompletoAttribute(): string
    {
        // trim() al final quita espacios sobrantes si algún apellido
        // viene vacío (ej. sin apellido materno) — sin esto, quedaría
        // un espacio doble en el nombre.
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
    }


    /**═══════════════════════════════════════════════════════════
     * buscarPorRuc(SuantService $sunat)
     * Se dispara al hacer clic en "Buscar" junto al campo de RUC (solo visible cuando tipoComporbante = FACTURA)
     * "SunatService $sunat" en la firma del método es INYECCION DE DEPENDENCIA: Livewire 3 ve ese tipo declarado
     * y construye automáticamente una instancia de SunatService para entregartela lista para usar - no hace
     * falta escribir "new SunatService()" en ningun lado de codigo
     ═══════════════════════════════════════════════════════════*/
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

    /**═══════════════════════════════════════════════════════════
     * updateBuscarPaga() / seleccionarPaga()
     * Mismo patrón que los dos anteriores, aplicada al buscador de ¿Quién paga?
     * (cuando el pagador es una persona, no una empresa)
     ═══════════════════════════════════════════════════════════*/
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

    /**═══════════════════════════════════════════════════════════
     * updatedBusqueda()
     * El buscador tranversal: una sola caja de texto que busca símultáneamente en DOS tablas distintas (items y servicios)
     * y devuelve los resultados mezclados como si vinieran de un solo lugar
     ═══════════════════════════════════════════════════════════*/
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
            ->map(fn ($r) => (array) $r)
            ->toArray();
        //dd($this->resultadosBusqueda);
    }

    /**═══════════════════════════════════════════════════════════
     * agregarAlCarrito()
     * Se dispara al hacer clic en un resultado del buscador
     * Recibe el ARRAY COMPLETO (no solo el id) porque ya trae el precio, la afectacion de IGV
     * el codigo SUNAT, etc. Evita una segunda consulta a la base de datos solo para "volver a preguntar"
     * datos que ya teniamos en la mano
     ═══════════════════════════════════════════════════════════*/
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

        //limpiamos el buscador para que la lista desaparezca y el cajero
        //pueda buscar el siguiente item de inmediato
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

            /**
             * Para que acepte 0 en los input
             */
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
        // ANTES comparaba contra $this->calculoCarrito['total'] (el
        // total COMPLETO) — eso hacía que, si pagabas los S/5.80 que
        // realmente faltaban, el sistema pensara que te faltaban
        // S/10.00 más (porque comparaba contra los S/15.80 completos).
        return max(0, $this->totalPagado - $this->montoACobrar); // CORREGIDO
    }

    /**═══════════════════════════════════════════════════════════
     * QuitarDelCarrito()
     * Elimina una línea especifico, identificada por su POSICION
     * dentro del arrar (no por su id de producto)
     ═══════════════════════════════════════════════════════════*/
    public function quitarDelCarrito(int $index)
    {
        unset($this->carrito[$index]);
        $this->carrito = array_values($this->carrito); //reordena el indice
    }

    /**═══════════════════════════════════════════════════════════
     * actualizarDoctorLinea()
     * Asigna (o cambia) el profesional responsable de UNA línea puntual del carrito
     ═══════════════════════════════════════════════════════════*/
    public function actualizarDoctorLinea(int $index, ?int $doctorId)
    {
        $this->carrito[$index]['doctor_id'] = $doctorId;
    }

    /**═══════════════════════════════════════════════════════════
     * updatedTipoComprobante()
     * Corre automaticamente apenas cambia $tipoComprobante. Su trabaja es "limpiar"
     * campos que dejam de tener sentido al cambiar de tipo - por ejemplo, si estaba en FACTURA
     * con un RUC ya 2buscado, y cambia a BOLETA, no queremos que ese dato quede guardado por error
     * si luego regresa a FACTURA
     ═══════════════════════════════════════════════════════════*/
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

    /**═══════════════════════════════════════════════════════════
     * NUEVO: solo se considera "pendiente de liquidar" un ticket
     * que contenga al menos una CITA. Un ticket de venta directa
     * (gasas, mascarillas, examen pagado al contado) solo tiene
     * items de tipo 'item' — ese es un comprobante final por sí
     * mismo, jamás espera una boleta/factura posterior.
     ═══════════════════════════════════════════════════════════*/
    public function getTicketsPendientesProperty()
    {
        if (!$this->atiendeId) {
            return collect();
        }

        return Voucher::where('patient_id', $this->atiendeId)
            ->where('tipo_comprobante', 'TICKET')
            ->whereDoesntHave('childVouchers')
            ->whereHas('items', fn ($q) => $q->where('item_type', 'cita'))
            ->get();
    }

    /**═══════════════════════════════════════════════════════════
     * El cajero elige uno de los tickets pendientes. Carga el carrito con los MISMOS items
     * del ticket original - no se vuelven a buscar en items/service, proque el precio y la
     * afectacion de IGV ya quedaron fijados en el dia que se creo el ticket, y no deben
     * cambiar aunque el catalogo cambie despues
    ═══════════════════════════════════════════════════════════*/
    public function liquidarTicket(int $ticketId)
    {
        $ticket = Voucher::with('items')->findOrFail($ticketId);

        $this->ticketOrigenId = $ticket->id;
        $this->tipoComprobante = 'BOLETA'; //el cajero lo puede cambiar a FACTURA si hace falta

        $this->carrito = $ticket->items->map(fn ($item) => [
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

    /**═══════════════════════════════════════════════════════════
     * Cuánto debe cobrar el cajero EN ESTE MOMENTO. Si se está
     * liquidando un ticket (o una cita con adelanto), es solo la
     * DIFERENCIA pendiente — no el total del comprobante. Si es una
     * venta nueva desde cero, es el total completo, como siempre.
     ═══════════════════════════════════════════════════════════*/
    public function getMontoACobrarProperty(): float
    {
        if ($this->ticketOrigenId) {
            // Vuelve a consultar el ticket para tener su saldo_pendiente
            // actualizado (por si acaso cambió algo entre que se cargó
            // el carrito y este momento).
            $ticket = Voucher::find($this->ticketOrigenId);
            return $ticket ? $ticket->saldo_pendiente : $this->calculoCarrito['total'];
        }

        return $this->calculoCarrito['total'];
    }



    /**═══════════════════════════════════════════════════════════
     * Buscador de Citas Agendadas
     ═══════════════════════════════════════════════════════════*/
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
            // CORREGIDO: se QUITA el ->where('estado_pagado', '!=', 'PAGADO')
            // de aquí — una cita pagada al 100% todavía puede necesitar
            // su boleta/factura formal, así que no se descarta a este nivel.
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

                // Se busca el TICKET más reciente de esta cita, sin
                // filtrar por estado — puede estar PARCIAL o PAGADO,
                // ambos casos necesitan revisión.
                $ticket = Voucher::where('tipo_comprobante', 'TICKET')
                    ->whereHas('items', fn ($q) => $q->where('item_type', 'cita')->where('item_id', $c->id))
                    ->latest()
                    ->first();

                // Si ese ticket YA tiene una boleta/factura hija (ya se
                // liquidó formalmente), esta cita está 100% resuelta —
                // se descarta de los resultados (devuelve null).
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
                    // Si no hay ticket todavía, el saldo es el precio
                    // completo (nunca se cobró nada). Si hay ticket, es
                    // su saldo real (puede ser 0 si ya pagó todo).
                    'saldo_pendiente' => $ticket?->saldo_pendiente ?? $precioTotal,
                ];
            })
            ->filter() // quita los null (citas ya formalizadas del todo)
            ->values()
            ->toArray();
    }



    /**═══════════════════════════════════════════════════════════
     * Cobro NUEVO  de una cita (primera vez que se cobra, sea al 100%)
     * o dejando un adelanto - el monto real lo decide el cajero despues en los inputs de pago)
     * Ademas de apilarla en el carrito, selecciona automaticamente al paciente de esa cita como
     * "quien se atiende", si  es que el cajero aun no habia elegido a nadie - asi no busca dos veces al mismo paciente
     ═══════════════════════════════════════════════════════════*/
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




    /**═══════════════════════════════════════════════════════════
     * guardarVenta()
     * El metodo más importante: toma todo lo armado  en pantalla (carrito, pagador/paciente y pagos)
     * y lo convierte en filas reales de la base de datos, de forma segura y "todo o nada"
     ═══════════════════════════════════════════════════════════*/
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
        // El saldo real a exigir: si se está liquidando un ticket, es su
        // saldo_pendiente (no el total completo del comprobante nuevo).
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


        // NUEVO: si se está liquidando un ticket, lo que hay que cubrir
        // no es el total completo (¡eso ya se pagó en parte!), sino solo
        // el saldo. saldo_pendiente es el accessor del modelo Voucher
        // (total - suma de sus pagos ya registrados).
        $montoRequerido = $calculo['total'];
        if ($this->ticketOrigenId) {
            $montoRequerido = Voucher::findOrFail($this->ticketOrigenId)->saldo_pendiente;
        }

        if ($this->tipoComprobante !== 'TICKET' && $this->totalPagado < $montoRequerido) {
            session()->flash('error', 'El pago no cubre el saldo pendiente.');
            return;
        }


        /**═══════════════════════════════════════════════════════════
         * LA TRANSACCION: TODO O ANDA
         * DB::transaction() envuelve el codigo de adentro en un bloque atomico: si CUALQUIER
         * linea lanza un erro, Laravel deshace automaticamente TODO lo que alcanzó a guardar antes
         ═══════════════════════════════════════════════════════════*/
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

            // NUEVO: si se está liquidando un ticket, hay que marcarlo
            // como resuelto — si no, seguirá apareciendo para siempre en
            // getTicketsPendientesProperty(), aunque ya se haya cobrado
            // el saldo completo en la boleta que se acaba de crear.
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

            // CORREGIDO: ahora cada método trae también su número de operación
            // (y datos bancarios, si se llenaron) — antes 'numero_operacion'
            // nunca se guardaba porque no existía ningún input para eso.
            foreach ([
                'EFECTIVO' => ['monto' => $this->pagoEfectivo, 'operacion' => null, 'origen' => null, 'destino' => null],
                'TARJETA' => ['monto' => $this->pagoTarjeta, 'operacion' => $this->numeroOperacionTarjeta, 'origen' => $this->entidadOrigen, 'destino' => $this->entidadDestino],
                'YAPE' => ['monto' => $this->pagoYape, 'operacion' => $this->numeroOperacionYape, 'origen' => null, 'destino' => null],
                'PLIN' => ['monto' => $this->pagoPlin, 'operacion' => $this->numeroOperacionPlin, 'origen' => null, 'destino' => null],
            ] as $metodo => $datos) {
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
