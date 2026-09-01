<div class="container py-4">

    @if (session('ok'))
        <div class="alert alert-success"> {{ session('ok') }} </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger"> {{ session('error') }} </div>
    @endif

    {{-- Si NO hay turno abierto, mostramos el formulario de apertura --}}
    @if (!$turno)
        <h4 class="mb-3">Abrir turno de caja</h4>

        <div class="mb-3">
            <label class="form-label">Caja</label>
            <select write:model="cajaId" class="form-select">
                <option value="">-- Selecciones --</option>
                @foreach ($cajas as $caja)
                    <option value="{{ $caja->id }} "> {{ $caja->nombre }} </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Monto de apertura (sensillo)</label>
            <input type="number" step="0.01" wire.model="montoApertura" class="form-control">
        </div>
    @else

    
    @endif

</div>
