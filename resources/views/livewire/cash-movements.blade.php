<div>

    {{-- MENSAJES --}}
    @if (session()->has('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif


    {{-- ENCABEZADO --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">
                    Movimientos de Caja
                </h5>

                @if ($turno)
                    <small class="text-muted">Turno #{{ $turno->id }}</small>
                @else
                    <small class="text-danger">No tienes un turno abierto</small>
                @endif
            </div>

            <button type="button" class="btn btn-primary" wire:click="abrirModal"
                @if (!$turno) disabled @endif>
                <i class="fas fa-plus"></i>
                Nuevo movimiento
            </button>
        </div>


        {{-- BUSCADOR --}}
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" placeholder="Buscar movimiento..." wire:model="buscar">
                </div>
            </div>


            {{-- TABLA --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tipo</th>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Usuario</th>
                            <th>Fecha</th>
                            <th width="120">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimientos as $movimiento)
                            <tr>
                                <td>
                                    {{ $movimiento->id }}
                                </td>
                                <td>
                                    @if ($movimiento->tipo === 'INGRESO')
                                        <span class="badge badge-success"> INGRESO</span>
                                    @else
                                        <span class="badge badge-danger">EGRESO </span>
                                    @endif
                                </td>

                                <td>{{ $movimiento->concepto }}</td>
                                <td>S/ {{ number_format($movimiento->monto, 2) }}</td>
                                <td>{{ $movimiento->user->name ?? '-' }} </td>
                                <td>
                                    {{ $movimiento->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-pimary"
                                        wire:click="editar({{ $movimiento->id }})">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-danger"
                                        wire:click="eliminar({{ $movimiento->id }})"
                                        onclick="return confirm('¿Está seguro de eliminar este movimiento?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">
                                    No hay movimientos registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    {{-- MODAL --}}
    @if ($modal)
        <div class="modal fade show" style="display: block; background: rgba(0,0,0,.5);" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $movimientoId ? 'Editar movimiento' : 'Nuevo movimiento' }}
                        </h5>

                        <button type="button" class="close btn btn-sm btn-dark m-1" wire:click="cerrarModal">
                            <span>&times;</span>
                        </button>
                    </div>


                    <div class="modal-body">
                        {{-- TIPO --}}
                        <div class="form-group">
                            <label>Tipo </label>

                            <select class="form-control" wire:model="tipo">
                                <option value="INGRESO">INGRESO</option>
                                <option value="EGRESO">EGRESO</option>
                            </select>
                            @error('tipo')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>


                        {{-- CONCEPTO --}}
                        <div class="form-group">
                            <label> Concepto </label>
                            <input type="text" class="form-control" wire:model.defer="concepto"
                                placeholder="Ej. Compra de útiles">
                            @error('concepto')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>


                        {{-- MONTO --}}
                        <div class="form-group">
                            <label>Monto</label>
                            <input type="number" step="0.01" min="0.01" class="form-control"
                                wire:model.defer="monto" placeholder="0.00">
                            @error('monto')
                                <span class="text-danger">{{ $message }} </span>
                            @enderror
                        </div>
                    </div>


                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cerrarModal">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="guardar">
                            <i class="fas fa-save"></i>
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
