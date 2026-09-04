<div class="">
    <div class="card">
        <div class="card-body">

            <div class="row">
                @if (session('ok'))
                    <div class="alert alert-success"> {{ session('ok') }} </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger"> {{ session('error') }} </div>
                @endif

                {{-- Si NO hay turno abierto, mostramos el formulario de apertura --}}
                @if (!$turno)
                    <h4 class="mb-3">Abrir turno de caja</h4>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label text-primary">Caja</label>
                            <select wire:model="cajaId" class="form-control">
                                <option value="">-- Selecciones --</option>
                                @foreach ($cajas as $caja)
                                    <option value="{{ $caja->id }}"> {{ $caja->nombre }} </option>
                                @endforeach
                            </select>
                        </div>
                    </div>


                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="mb-3">
                                <label class="form-label text-primary">Monto de apertura (sensillo)</label>
                                <input type="number" step="0.01" wire:model="montoApertura" class="form-control">
                            </div>
                        </div>
                    </div>

                    <button wire:click="abrirTurno" class="btn btn-primary w-100">Abrir Turno</button>
                @else
                    <h4 class="mb-3"> Turno abierto</h4>

                    <div class="border rounded p-3 mb-3">
                        <div class="small text-muted">Caja: {{ $turno->cashier->nombre }} </div>
                        <div class="small text-muted">Abierto: {{ $turno->abierto_en->format('d/m/Y H:i') }} </div>
                        <div class="small text-muted"> Monto apertura: S/.
                            {{ number_format($turno->monto_apertura, 2) }} </div>
                    </div>

                    {{-- RESUMEN INFORMATIVO DEL DIA, SE RECALCULA SOLO --}}
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="border rounded p-2 text-center">
                                <div class="small text-muted">Ventas hoy</div>
                                <div class="h6">S/. {{ number_format($this->resumenHoy['ventas'], 2) }} </div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="border rounded p-2 text-center">
                                <div class="small text-muted">Efectivo hoy</div>
                                <div class="h6">S/. {{ number_format($this->resumenHoy['efectivo'], 2) }} </div>
                            </div>
                        </div>
                    </div>

                    {{-- CIERRE DE TURNO --}}
                    <h5 class="mb-2">Cerrar turno</h5>
                    <div class="small text-muted mb-2">
                        El sistema calcula que deberá haber: <strong>S/. {{ number_format($this->montoSistema, 2) }}
                        </strong>
                    </div>

                    <div class="mb3">
                        <label for="" class="form-label">Monto contado (real, físico)</label>
                        <input type="number" step="0.01" wire:model:live="montoContado" class="form-control">
                    </div>

                    @if ($montoContado > 0)
                        <div class="mb-3 small {{ $this->diferencia < 0 ? 'text-danger' : 'text-success' }} ">
                            Diferencia: S/ {{ number_format($this->diferencia, 2) }}
                            {{ $this->diferencia < 0 ? '(falta)' : ($this->diferencia > 0 ? '(sobra)' : '(cuadrado)') }}
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Observaciones (opcional)</label>
                        <textarea wire:model="observacionesCierre" class="form-control" rows="2"></textarea>
                    </div>

                    <button wire:click="cerrarTurno" class="btn btn-danger w-100">Cerrar turno</button>

                @endif
            </div>
        </div>
    </div>
</div>
