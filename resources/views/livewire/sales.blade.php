<div class="">
    <div class="card">
        <div class="card-body">
            @if (session('ok'))
                <div class="alert alert-success"> {{ session('ok') }} </div>
            @endif

            @if (!$turno)
                <div class="alert alert-warning">
                    No tienes una caja abierta. Debes abrir tu turno antes de registra Ventas
                </div>
            @else
                {{-- CABECERA: TIPO DE COMPROBANTE --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            <h6 class="fw-bold mb-2">Tipo de Comprobante</h6>
                            <select wire:model.live='tipoComprobante' class="form-control mb-2">
                                <option value="BOLETA">Boleta</option>
                                <option value="FACTURA">Factura</option>
                                <option value="TICKET">Ticket</option>
                            </select>

                            <div class="small text-muted mb-2">
                                Serie: {{ $this->seriePreview['serie'] }} - N° {{ $this->seriePreview['correlativo'] }}
                            </div>

                            @if ($tipoComprobante === 'FACTURA')
                                <div class="form-check">
                                    <input type="checkbox" wire:model.live="aplicaDetraccion">
                                    <label class="form-check-label small">Aplica detracción</label>
                                </div>
                            @endif
                        </div>
                    </div>


                    <div class="col-md-6">
                        <div class="border rounded p-3">
                            {{-- BLOQUE DE QUIEN SE ATIENDE --}}
                            <h6 class="fw-bold mb-2">¿Quién se atiende? <span class="text-danger">*</span></h6>

                            @if ($atiendeNombre)
                                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-3">
                                    <span> {{ $atiendeNombre }} </span>

                                    <button wire:click="cambiarAtiende" class="btn btn-sm btn-link">Cambiar</button>
                                </div>
                            @else
                                <div class="position-relative mb-2">
                                    <input type="text" wire:model.live.debounce.300ms='buscarAtiende'
                                        placeholder="Buscar por nombre/N° identidad" class="form-control">

                                    @if (count($resultadosAtiende) > 0)
                                        <div class="list-group position-absolute w-100 shadow bg-white"
                                            style="z-index:1000">

                                            @foreach ($resultadosAtiende as $p)
                                                <button type="button"
                                                    wire:click="seleccionarAtiende({{ $p['id'] }} , @js($p['nombre_completo']))"
                                                    class="list-group-item list-group-item-action text-dark">
                                                    {{ $p['nombre_completo'] }} — {{ $p['numero_identidad'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                            {{-- BLOQUE DE QUIEN SE ATIENDE --}}


                            {{-- TICKET PENDIENTES --}}
                            @if ($this->ticketsPendientes->isNotEmpty() && !$ticketOrigenId)
                                <div class="alert alert-info mt-2">
                                    <div class="fw-bold mb-2">Este paciente tiene ticket(s) con saldo pendientes</div>
                                    @foreach ($this->ticketsPendientes as $t)
                                        <button wire:click="liquidarTicket({{ $t->id }})"
                                            class="btn btn-sm btn-warning">Liquidar
                                            {{ $t->serie }}-{{ $t->correlativo }} (falta S/
                                            {{ number_format($t->saldo_pendiente, 2) }})
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            {{-- TICKET PENDIENTES --}}



                            {{-- BLOQUE IDENTICO AL DE ARRIBA, PERO PARA EL PAGADOR - MISMO PATRON --}}
                            <h6 class="fw-bold mb-2">¿Quién paga? <small class="text-muted"></small></h6>

                            @if ($tipoComprobante === 'FACTURA')
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Buscar po RUC</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" wire:model="buscarRuc" maxlength="11" class="form-control"
                                            placeholder="RUC (11 DÍGITOS)">
                                        <button wire:click="buscarPorRuc" type="button"
                                            class="btn btn-outline-secondary">Buscar</button>
                                    </div>
                                </div>

                                <input type="text" wire:model='numeroDocCliente' placeholder="RUC"
                                    class="form-control form-control-sm mb-2">
                                <input type="text" wire:model='razonSocialCliente' placeholder="Razón social"
                                    class="form-control form-control-sm mb-2">
                                <input type="text" wire:model='direccionCliente' placeholder="Dirección"
                                    class="form-control form-control-sm mb-2">
                            @else
                                <div class="small text-muted mb-2">
                                    Si dejas vacío, se asume el mismo paciente que se atiende
                                </div>
                                @if ($pagaNombre)
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span> {{ $pagaNombre }} </span>
                                        <button wire:click="cambiarPaga" class="btn btn-sm btn-link">Cambiar</button>
                                    </div>
                                @else
                                    <div class="position-relative">
                                        <input type="text" wire:model.live.debounce.300ms='buscarPaga'
                                            placeholder="Si es distinto al paciente..." class="form-control">

                                        @if (count($resultadosPaga) > 0)
                                            <div class="list-group position-absolute w-100 text-dark shadow bg-white"
                                                style="z-index:1000">
                                                @foreach ($resultadosPaga as $p)
                                                    <button type="button"
                                                        wire:click="seleccionarPaga( {{ $p['id'] }}, '{{ $p['nombre_completo'] }}' )""
                                                        class="list-group-item list-group-item-action text-dark">
                                                        {{ $p['nombre_completo'] }} — {{ $p['numero_identidad'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endif
                            {{-- BLOQUE IDENTICO AL DE ARRIBA, PERO PARA EL PAGADOR - MISMO PATRON --}}
                        </div>
                    </div>
                </div>


                <div class="row">
                    <div class="col-md-6">
                        {{-- PROFESIONAL- --}}
                        <div class="mb-3">
                            <label class="form-label small">Profesional (para buscar consultas)</label>

                            <select wire:model.live="filtroDoctorId" class="form-control">
                                <option value="">-- Selecciona --</option>
                                @foreach ($doctores as $doctor)
                                    <option value="{{ $doctor->id }}"> {{ $doctor->nombre }} </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- PROFESIONAL --}}
                    </div>

                    <div class="col-md-6">
                        {{-- -BUSCADOR DE CITAS MEDICAS --}}
                        <div class="position-relative mb-3">
                            <label class="form-label small">Cobrar una cita agendada</label>
                            <input type="text" wire:model.live.debounce.300ms="buscarCita"
                                placeholder="Buscar cita por paciente o N° de cita..." class="form-control">

                            @if (count($resultadosCitas) > 0)
                                <div class="list-group position-absolute w-100 shadow bg-white" style="z-index:1000">
                                    @foreach ($resultadosCitas as $c)
                                        <div
                                            class="list-group-item d-flex justify-content-between align-items-center text-dark">
                                            <span>{{ $c['texto'] }}</span>

                                            @if ($c['ticket_pendiente_id'])
                                                <button wire:click="liquidarTicket({{ $c['ticket_pendiente_id'] }})"
                                                    class="btn btn-sm btn-warning text-dark">
                                                    Liquidar (falta S/ {{ number_format($c['saldo_pendiente'], 2) }})
                                                </button>
                                            @else
                                                <button
                                                    wire:click="agregarCitaAlCarrito({{ $c['appointment_id'] }}, {{ $c['patient_id'] }}, {{ $c['precio_total'] }}, {{ $c['doctor_id'] }})"
                                                    class="btn btn-sm btn-primary text-dark">
                                                    Cobrar S/ {{ number_format($c['precio_total'], 2) }}
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        {{-- -BUSCADOR DE CITAS MEDICAS --}}
                    </div>
                </div>







                {{-- BUSCADOR TRANVERSAL --}}
                <div class="position-relative mb-3">
                    <input type="text" wire:model.live.debounce.300ms="busqueda"
                        placeholder="Buscar servicio, examen o producto..." class="form-control">

                    @if (count($resultadosBusqueda) > 0)
                        <div class="list-group position-absolute w-100 shadow bg-white" style="z-index:1000">
                            @foreach ($resultadosBusqueda as $r)
                                <button type="button" wire:click="agregarAlCarrito(@js($r))"
                                    class="list-group-item list-group-item-action d-flex justify-content-between text-dark">
                                    <span>
                                        {{ $r['nombre'] }} <small class="text-muted"> ({{ $r['tipo_origen'] }})
                                        </small>
                                    </span>
                                    <span>S/. {{ number_format($r['precio'], 2) }} </span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                {{-- BUSCADOR TRANVERSAL --}}


                {{-- CARRITO --}}
                @if (count($carrito) > 0)
                    @foreach ($carrito as $index => $linea)
                
                        <div class="border rounded p-3 mb-3" wire:key="carrito-{{ $index }}">
                            <div class="row g-3">

                               
                                <div class="col-md-7">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                      
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width:40px;height:40px;">
                                            <i class="bi bi-search text-muted"></i>
                                        </div>

                                        <div class="flex-grow-1">
  
                                            <div class="small text-muted">{{ strtoupper($linea['item_type']) }}</div>
                                            <div class="small text-muted">Precio Ref.: S/
                                                {{ number_format($linea['precio'], 2) }}</div>
                                            <div class="small text-danger">Comisión médico:
                                                {{ $linea['comision_porcentaje'] }}%</div>
                                        </div>

                                        {{-- el botón de quitar se mueve a la esquina superior derecha de la tarjeta --}}
                                        <button wire:click="quitarDelCarrito({{ $index }})"
                                            class="btn btn-sm btn-outline-danger">×</button>
                                    </div>

                                    <label class="form-label small mb-1">Nombre</label>

                                    <input type="text" wire:model.live="carrito.{{ $index }}.descripcion"
                                        class="form-control form-control-sm mb-2">

                                    <select
                                        wire:change="actualizarDoctorLinea({{ $index }}, $event.target.value)"
                                        class="form-select form-select-sm">
                                        <option value="">-- Ninguno --</option>
                                        @foreach ($doctores as $doctor)
                                            <option value="{{ $doctor->id }}" @selected($linea['doctor_id'] == $doctor->id)>
                                                {{ $doctor->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- COLUMNA DERECHA: precio, cantidad, total --}}
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Precio</label>
                                    <input type="number" step="0.01" min="0"
                                        wire:model.live="carrito.{{ $index }}.precio"
                                        class="form-control form-control-sm mb-2">

                                    <label class="form-label small mb-1">Cantidad</label>
                                    <input type="number" min="1"
                                        wire:model.live="carrito.{{ $index }}.cantidad"
                                        class="form-control form-control-sm mb-2">

                                    <div class="text-end mt-2">
                                        <div class="small text-muted">Total</div>
                                        <div class="h5 mb-0">S/
                                            {{ number_format($linea['precio'] * $linea['cantidad'], 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif


                {{-- TOTALES Y PAGOS --}}
                @if (count($carrito) > 0)
                    @php
                        $calculo = $this->calculoCarrito;
                    @endphp

                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex gap-2 flex-wrap">
                                <div>
                                    <label class="form-label small mb-1">Efectivo</label>
                                    <input type="number" step="0.01" wire:model.live="pagoEfectivo"
                                        class="form-control form-control-sm" style="width:100px">
                                </div>

                                <div>
                                    <label class="form-label small mb-1">Tarjeta</label>
                                    <input type="number" step="0.01" wire:model.live="pagoTarjeta"
                                        class="form-control form-control-sm mb-1" style="width:100px">
                                    @if ($pagoTarjeta > 0)
                                        <input type="text" wire:model="numeroOperacionTarjeta"
                                            placeholder="N° operación" class="form-control form-control-sm"
                                            style="width:100px">
                                    @endif
                                </div>

                                <div>
                                    <label class="form-label small mb-1">Yape</label>
                                    <input type="number" step="0.01" wire:model.live="pagoYape"
                                        class="form-control form-control-sm mb-1" style="width:100px">
                                    @if ($pagoYape > 0)
                                        <input type="text" wire:model="numeroOperacionYape"
                                            placeholder="N° operación" class="form-control form-control-sm"
                                            style="width:100px">
                                    @endif
                                </div>

                                <div>
                                    <label class="form-label small mb-1">Plin</label>
                                    <input type="number" step="0.01" wire:model.live="pagoPlin"
                                        class="form-control form-control-sm mb-1" style="width:100px">
                                    @if ($pagoPlin > 0)
                                        <input type="text" wire:model="numeroOperacionPlin"
                                            placeholder="N° operación" class="form-control form-control-sm"
                                            style="width:100px">
                                    @endif
                                </div>
                            </div>

                            @if ($this->vuelto > 0)
                                <div class="small text-muted mt-2">Vuelto: S/ {{ number_format($this->vuelto, 2) }}
                                </div>
                            @endif
                        </div>

                        <div class="col-md-6 text-end">
                            <div class="small text-muted">Op. Gravada: S/
                                {{ number_format($calculo['total_gravado'], 2) }} </div>

                            @if ($calculo['total_exonerado'] > 0)
                                <div class="small text-muted">Op. Exonerada: S/.
                                    {{ number_format($calculo['total_exonerado'], 2) }} </div>
                            @endif

                            @if ($ticketOrigenId)
                                <div class="small text-success">
                                    Ya pagado (adelanto): S/
                                    {{ number_format($calculo['total'] - $this->montoACobrar, 2) }}
                                </div>
                                <div class="small text-warning fw-bold">
                                    A cobrar hoy: S/ {{ number_format($this->montoACobrar, 2) }}
                                </div>
                            @endif

                            <div class="small text-muted">IGV: S/ {{ number_format($calculo['igv'], 2) }}</div>
                            <h4>A pagar: S/ {{ number_format($this->montoACobrar, 2) }}</h4>
                            <button wire:click="guardarVenta" class="btn btn-primary btn-lg">Guardar venta</button>
                        </div>

                        @if (session('error'))
                            <div class="aler alert-danger"> {{ session('error') }} </div>
                        @endif

                    </div>
                @endif
            @endif
 
            <script>
                window.addEventListener('venta-guardada', event => {
                    window.open(
                        `/receptionist/${event.detail.voucherId}/imprimir`,
                        '_blank'
                    );
                });
            </script>
        </div>
    </div>
</div>
