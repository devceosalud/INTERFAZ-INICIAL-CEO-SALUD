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
                        <input type="text" wire:model='numeroDocCliente' placeholder=""
                            class="form-control form-control-sm mb-2">
                        <input type="text" wire:model='razonSocialCliente' placeholder=""
                            class="form-control form-control-sm mb-2">
                        <input type="text" wire:model='direccionCliente' placeholder=""
                            class="form-control form-control-sm mb-s">


                        <div class="form-check">
                            <input type="checkbox" wire:model.live='aplicaDetraccion' class="form-check-input">
                            <label class="form-check-label small mt-2">Aplica detracción</label>
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

                            <button wire:click="$set('atiendeId', null)" class="btn btn-sm btn-link">Cambiar</button>
                        </div>
                    @else
                        <div class="position-relative mb-2">
                            <input type="text" wire:model.live.debounce.300ms='buscarAtiende'
                                placeholder="Buscar por nombre/N° identidad" class="form-control">

                            @if (count($resultadosAtiende) > 0)
                                <div class="list-group position-absolute w-100 shadow" style="z-index: 10">

                                    @foreach ($resultadosAtiende as $p)
                                        <button type="button"
                                            wire:click="seleccionarAtiende({{ $p['id'] }} , {{ $p['nombre_completo'] }})"
                                            class="list-group-item list-group-item-action">
                                            {{ $p['nombre_completo'] }} — {{ $p['numero_identidad'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif


</div>
