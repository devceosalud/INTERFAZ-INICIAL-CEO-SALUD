<div>
    @if (session('ok'))
        <div class="alert alert-success"> {{ session('ok') }} </div>
    @endif

    @if (session('error'))
        <div class="aler alert-danger"> {{ session('error') }} </div>
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
                                <div class="list-group position-absolute w-100 shadow" style="z-index: 10">

                                    @foreach ($resultadosAtiende as $p)
                                        <button type="button"
                                            wire:click="seleccionarAtiende({{ $p['id'] }} , @js($p['nombre_completo']))"
                                            class="list-group-item list-group-item-action">
                                            {{ $p['nombre_completo'] }} — {{ $p['numero_identidad'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif



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
                                    <div class="list-group position-absolute w-100 shadow" style="z-index: 10">
                                        @foreach ($resultadosPaga as $p)
                                            <button type="button"
                                                wire:click="seleccionarPaga( {{ $p['id'] }}, '{{ $p['nombre_completo'] }}' )""
                                                class="list-group-item list-group-item-action">
                                                {{ $p['nombre_completo'] }} — {{ $p['numero_identidad'] }}
                                            </button>
                                        @endforeach
                                    </div>

                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif


</div>
