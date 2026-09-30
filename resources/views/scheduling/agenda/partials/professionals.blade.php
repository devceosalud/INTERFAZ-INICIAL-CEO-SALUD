<section class="agenda-operations__section agenda-doctors" aria-labelledby="agenda-doctors-title">
    <div class="agenda-pane-head">
        <div>
            <h2 class="agenda-section-title" id="agenda-doctors-title">Médicos</h2>
            <p id="agenda-compare-hint">Cargando disponibilidad…</p>
        </div>
        <label class="agenda-compare-control" title="Permite seleccionar varios médicos para comparar su carga">
            <input type="checkbox" id="agenda-compare-toggle">
            <span>Comparar</span>
        </label>
    </div>

    <label class="agenda-doctor-search">
        <span class="agenda-visually-hidden">Buscar médico</span>
        <input type="search" id="agenda-doctor-search" placeholder="Buscar médico" autocomplete="off">
    </label>

    <div class="agenda-doctor-list" id="agenda-doctor-list">
        @foreach ($doctors as $doctor)
            <div class="agenda-doctor-row" data-doctor-row data-doctor-id="{{ $doctor->id }}"
                data-specialty="{{ $doctor->specialty_id }}"
                data-search="{{ Illuminate\Support\Str::lower($doctor->nombre) }}">
                <input type="checkbox" class="agenda-doctor-check" id="agenda-doctor-{{ $doctor->id }}"
                    value="{{ $doctor->id }}">
                <label class="agenda-doctor-row__body" for="agenda-doctor-{{ $doctor->id }}">
                    <span class="agenda-doctor-row__name">{{ $doctor->nombre }}</span>
                    <span class="agenda-doctor-row__specialty">
                        {{ optional($specialties->firstWhere('id', $doctor->specialty_id))->nombre ?: 'Sin especialidad' }}
                    </span>
                </label>
                <span class="agenda-doctor-row__metrics" data-doctor-metrics>—</span>
            </div>
        @endforeach
    </div>

    <div class="agenda-doctors__footer">
        <span>Un clic abre una agenda.</span>
        <button type="button" class="agenda-text-button" id="agenda-doctor-reset">Primer médico</button>
    </div>
</section>
